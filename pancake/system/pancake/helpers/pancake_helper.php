<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Displays an "Access Denied" page.
 *
 * @access	public
 * @return	void
 */
function access_denied() {
    $CI = get_instance();
    echo $CI->template->build('partials/access_denied', array(), true);
    die;
}

/**
 * Fixes UTF-8 Encoding errors in PDFs.
 *
 * Use in everything that's ever going to be displayed in a PDF. Seriously.
 *
 * Leaves the HTML intact (for those who added it on purpose) and doesn't affect the HTML view.
 *
 * @param string $str
 * @return string
 */
function escape($str) {
    return htmlspecialchars_decode(htmlentities($str, ENT_COMPAT, "UTF-8"));
}

function purify_html($dirty_html) {
    static $purifier = null;

    if ($purifier === null) {
        require_once APPPATH . "libraries/HTMLPurifier/HTMLPurifier.standalone.php";
        $config = HTMLPurifier_Config::createDefault();
        if (!file_exists(FCPATH."uploads/htmlpurifier")) {
            if (@mkdir(FCPATH."uploads/htmlpurifier")) {
                $config->set('Cache.SerializerPath', FCPATH."uploads/htmlpurifier");
            } else {
                $config->set('Cache.DefinitionImpl', null);
            }
        } else {
            $config->set('Cache.SerializerPath', FCPATH."uploads/htmlpurifier");
        }
        $purifier = new HTMLPurifier($config);
    }

    return $purifier->purify($dirty_html);
}

function current_user() {
    $CI = &get_instance();
    return $CI->current_user ? $CI->current_user->id : 0;
}

function get_user_full_name_by_id($user_id) {
    $CI = &get_instance();
    $CI->load->model('users/user_m');
    return $CI->user_m->get_full_name($user_id);
}

function get_all_users() {
    $CI = &get_instance();
    $CI->load->model('users/user_m');
    return $CI->user_m->get_all_with_meta();
}

function get_client_unique_id_by_id($client_id) {
    $CI = &get_instance();
    $CI->load->model('clients/clients_m');
    return $CI->clients_m->getUniqueIdById($client_id);
}

/**
 * Sets the appropriate JSON header, the status header, then
 * encodes the output and exits execution with the JSON.
 *
 * @access	public
 * @param	mixed	The output to encode
 * @param	int		The status header
 * @return	void
 */
function output_json($output, $status = 200) {
    if (headers_sent()) {
        show_error(__('Headers have already been sent.'));
    }

    PAN::$CI->output->set_status_header($status);
    PAN::$CI->output->set_header('Content-type: application/json');
    exit(json_encode($output));
}

/**
 * Replaces PHP's file_get_contents in URLs, to get around the allow_url_fopen limitation.
 * Still loads regular files using file_get_contents.
 *
 * @param string $url
 * @return string
 */
function get_url_contents($url, $redirect = true) {

    if (empty($url)) {
        return '';
    }

    # First, let's check whether this is a local file.

    if (stristr($url, FCPATH) !== false) {
        return file_get_contents($url);
    }

    # This is for PDFs, to bypass the need for an external request.
    $config = array();

    if (!file_exists(APPPATH . 'config/template.php')) {
        include APPPATH . '../system/pancake/config/template.php';
    } else {
        include APPPATH . 'config/template.php';
    }

    $theme_location = $config['theme_locations'][0];
    $fcpath = FCPATH;
    $base_url = BASE_URL;

    $buffer = str_ireplace($fcpath, '', $theme_location);
    $buffer = $base_url . $buffer;

    # Check if it's in third_party/themes.
    if (substr($url, 0, strlen($buffer)) == $buffer) {
        $path_without_buffer = substr($url, strlen($buffer), strlen($url) - strlen($buffer));
        $path_without_version = explode('?', $path_without_buffer);
        $path_without_version = $path_without_version[0];
        $path = $theme_location . $path_without_version;

        if (file_exists($path)) {
            return file_get_contents(urldecode($path));
        }
    }

    # Check if it's in uploads.
    $buffer = $base_url . 'uploads/';
    if (substr($url, 0, strlen($buffer)) == $buffer) {
        $path_without_buffer = substr($url, strlen($buffer), strlen($url) - strlen($buffer));
        $path_without_version = explode('?', $path_without_buffer);
        $path_without_version = $path_without_version[0];
        $path = FCPATH . 'uploads/' . $path_without_version;
        if (file_exists($path)) {
            return file_get_contents(urldecode($path));
        }
    }

    if (substr($url, 0, 7) != 'http://') {
        return file_get_contents($url);
    } else {
        include_once APPPATH . 'libraries/HTTP_Request.php';
        $http = new HTTP_Request();

        # This is here to make Google Fonts serve .ttf instead of .woff, which breaks dompdf.
        $http->user_agent = "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_2) AppleWebKit/600.4.10 (KHTML, like Gecko) Version/8.0.4 Safari/600.4.10";

        try {
            $result = $http->request($url);
        } catch (HTTP_Request_Exception $e) {
            deal_with_no_internet($redirect, $url);
            return '';
        }

        return $result;
    }
}

/**
 * Redirects to the no_internet_access page if $redirect is true (which is only true in PDFs), or if a firewall is blocking external resource access completely.
 * Else, defines TEMPORARY_NO_INTERNET_ACCESS which is used in the admin layout, to show a subtle "no internet access" notification.
 *
 * @param boolean $redirect
 */
function deal_with_no_internet($redirect = false, $url = '') {
    if ($redirect) {
        redirect('no_internet_access/' . base64_encode($url));
    } else {
        defined('TEMPORARY_NO_INTERNET_ACCESS') or define('TEMPORARY_NO_INTERNET_ACCESS', true);
    }
}

function get_email_template($template = null, $field = null) {
    $CI = get_instance();
    $CI->load->model('email_settings_templates');
    return $CI->email_settings_templates->get($template, $field);
}

/**
 * Sends a Pancake email. Uses the right Pancake theme,
 * fetches template details from the DB, inserts a record of the email
 * in the client's contact log, processes variables, and everything else you need.
 *
 * Available options:
 *
 * REQUIRED to - the email recipient
 * REQUIRED template - the 'identifier' of the desired template in email_settings_templates
 * REQUIRED data - an array of variables to be processed into the template (can contain sub-arrays)
 * REQUIRED client_id - the client's id, for storing email in the contact log
 * OPTIONAL attachments - an array of files in filename => filedata pairs
 * OPTIONAL subject - if provided, will be used instead of the template's default
 * OPTIONAL message - if provided, will be used instead of the template's default
 * OPTIONAL from - if provided, will be used instead of the system's default
 *
 * The following is added to the "data" array automatically:
 *
 * settings -> An array with all settings
 * logo -> The logo's URL
 * user_display_name -> The display name of the current logged in user (or the {settings:admin_name} if not available)
 * client -> The client's record, WITH {client:access_url}
 *
 * @param array $options
 * @return boolean
 */
function send_pancake_email($options = array()) {

    if (!isset($options['to']) or ! isset($options['template']) or ! isset($options['data']) or ! isset($options['client_id'])) {
        throw new InvalidArgumentException("send_pancake_email() needs to, template, client_id and data arguments.");
    }

    if (!isset($options['attachments'])) {
        $options['attachments'] = array();
    }

    if (!isset($options['subject'])) {
        $options['subject'] = null;
    }

    if (!isset($options['message'])) {
        $options['message'] = null;
    }

    if (!isset($options['from'])) {
        $options['from'] = null;
    }

    if (!isset($options['unique_id'])) {
        $options['unique_id'] = null;
    }

    if (!isset($options['item_type'])) {
        $options['item_type'] = null;
    }

    $CI = &get_instance();
    $CI->load->library('simpletags');
    $CI->load->model('invoices/invoice_m');
    $CI->load->model('clients/clients_m');
    $CI->load->model('invoices/partial_payments_m', 'ppm');
    $CI->load->model('files/files_m');
    $CI->load->model('tickets/ticket_m');
    $CI->load->model('email_settings_templates');

    $template_details = $CI->email_settings_templates->get($options['template']);

    if (empty($options['subject'])) {
        $options['subject'] = $template_details['subject'];
    }

    if (empty($options['message'])) {
        $options['message'] = $template_details['message'];
    }

    $options = $CI->dispatch_return('before_send_pancake_email', $options, 'array');
    if (count($options) == 1) {
        # $options was modified by a plugin.
        $options = reset($options);
    }

    $to = $options['to'];
    $template = $options['template'];
    $data = $options['data'];
    $client_id = $options['client_id'];
    $attachments = $options['attachments'];
    $custom_subject = $options['subject'];
    $custom_message = $options['message'];
    $custom_from = $options['from'];
    $unique_id = $options['unique_id'];
    $item_type = $options['item_type'];

    Business::setBusinessFromClient($client_id);

    if ($custom_from === null) {
        if (logged_in() and Settings::get('send_emails_from_logged_in_user')) {
            $CI->email->from($CI->current_user->email, "{$CI->current_user->first_name} {$CI->current_user->last_name}");
        } else {
            switch ($template) {
                case 'new_invoice':
                case 'new_credit_note':
                case 'new_estimate':
                case 'new_proposal':
                case 'invoice_payment_notification_for_admin':
                case 'invoice_payment_notification_for_client':
                case 'new_ticket_invoice':
                    # reset+explode because we want to get the first address in what may be a comma-separated list.
                    $from = array_reset(explode(',', Business::getBillingEmail()));
                    $from_name = Business::getBillingEmailFrom();
                    break;
                default:
                    # reset+explode because we want to get the first address in what may be a comma-separated list.
                    $from = array_reset(explode(',', Business::getNotifyEmail()));
                    $from_name = Business::getNotifyEmailFrom();
            }

            $CI->email->from($from, $from_name);
        }
    }

    $client = (array) $CI->clients_m->get($client_id);
    $client['access_url'] = site_url(Settings::get('kitchen_route') . '/' . $client['unique_id']);

    $data['client'] = $client;
    $settings = (array) Settings::get_all();

    $data['logo'] = Business::getLogoUrl();
    $data['business'] = Business::getBusiness();
    $data['settings'] = $settings;
    $data['user_display_name'] = logged_in() ? ($CI->current_user->first_name . ' ' . $CI->current_user->last_name) : Business::getAdminName();

    if (isset($data['invoice'])) {

        if (array_key_exists("billable_amount", $data['invoice']) and array_key_exists("currency_code", $data['invoice'])) {
            $data['invoice']['billable_amount'] = Currency::format($data['invoice']['billable_amount'], $data['invoice']['currency_code']);
        }

        if (array_key_exists("unpaid_amount", $data['invoice']) and array_key_exists("currency_code", $data['invoice'])) {
            $data['invoice']['unpaid_amount'] = Currency::format($data['invoice']['unpaid_amount'], $data['invoice']['currency_code']);
        }

        if (array_key_exists("paid_amount", $data['invoice']) and array_key_exists("currency_code", $data['invoice'])) {
            $data['invoice']['paid_amount'] = Currency::format($data['invoice']['paid_amount'], $data['invoice']['currency_code']);
        }

        if (array_key_exists("amount", $data['invoice']) and array_key_exists("currency_code", $data['invoice'])) {
            $data['invoice']['amount'] = Currency::format($data['invoice']['amount'], $data['invoice']['currency_code']);
        }

        if (isset($data['invoice']['due_date'])) {
            $data['invoice']['original_due_date'] = $data['invoice']['due_date'];
            $data['invoice']['due_date'] = format_date($data['invoice']['due_date']);
        }

        # For compatibility.
        $data['estimate'] = $data['invoice'];
        $data['credit_note'] = $data['invoice'];
    }

    $data['settings']['site_name'] = Business::getBrandName();
    $data['settings']['mailing_address'] = Business::getMailingAddress();
    $data['settings']['admin_name'] = Business::getAdminName();
    $data['settings']['notify_email'] = Business::getNotifyEmail();
    $data['settings']['logo_url'] = Business::getLogoUrl();

    $data = $CI->dispatch_return('process_pancake_email_data_array', $data, 'array');
    if (count($data) == 1) {
        # $data was modified by a plugin.
        $data = reset($data);
    }

    $custom_subject = get_instance()->mustache->render($custom_subject, $data);
    $custom_subject = $CI->simpletags->parse($custom_subject, $data);
    $custom_subject = $custom_subject['content'];
    $custom_subject = html_entity_decode($custom_subject);

    $custom_message = get_instance()->mustache->render($custom_message, $data);
    $custom_message = $CI->simpletags->parse($custom_message, $data);
    $custom_message = $custom_message['content'];

    $logo_settings = $CI->dispatch_return('get_logo_settings', array('max-height' => 100, 'max-width' => 320), 'array');
    if (count($logo_settings) == 1) {
        # $logo_settings was modified by a plugin.
        $logo_settings = reset($logo_settings);
    }

    $logo = Business::getLogo(true, false, 1, $logo_settings);
    $logo = get_instance()->mustache->render($logo, $data);
    $logo = $CI->simpletags->parse($logo, $data);
    $logo['content'] = empty($logo['content']) ? "" : "{$logo['content']}<br /><br />";

    # This fixes an issue with the BCC engine.
    $data['bcc'] = '{bcc}';
    $data['tracking_image'] = '{tracking_image}';

    $template = Email_Template::build($template_details['template'], nl2br($custom_message), $logo, $custom_subject);
    $template = get_instance()->mustache->render($template, $data);
    $template = $CI->simpletags->parse($template, $data);
    $template = $template['content'];

    $return = send_pancake_email_raw($to, $custom_subject, $template, $custom_from, $attachments, $unique_id, $item_type);

    $CI->db->reconnect();

    if ($return) {
        $CI->load->model('clients/contact_m');

        if (is_string($to) and strpos($to, ',') !== FALSE) {
            $to = explode(',', $to);
        }

        if (is_string($to)) {
            $to = array($to);
        }

        if (is_array($to)) {
            foreach ($to as $recipient) {
                $recipient = trim($recipient);
                if (!in_array($recipient, $return["failed_recipients"])) {
                    $CI->contact_m->insert(array(
                        'client_id' => $client_id,
                        'method' => 'email',
                        'contact' => $recipient,
                        'subject' => $custom_subject,
                        'content' => $custom_message,
                        'duration' => 0,
                        'sent_date' => now(),
                        'user_id' => logged_in() ? (int) $CI->current_user->id : 0,
                    ), true); # True to skip validation, because validation screws up $_POST. (who did this?!?!) - Bruno
                }
            }
            return true;
        }

        return true;
    } else {
        return false;
    }
}

/**
 * DO NOT USE THIS TO SEND EMAIL.
 * Use send_pancake_email().
 * This function is used only by send_pancake_email().
 *
 * Sends an email as given, without doing any processing.
 *
 * BCCs the email if it's being sent to a client and the BCC setting is turned on.
 *
 * If $from is not provided, the notify_email will be used.
 *
 * @param string|array $to
 * @param string $subject
 * @param string $message
 * @param string $from
 * @param array $attachments
 * @return boolean
 */
function send_pancake_email_raw($to, $subject, $message, $from = null, $attachments = array(), $unique_id = '', $item_type = '', $email_config = null) {

    static $converter = null;
    static $mailer = null;

    if ($mailer === null) {
        if (function_exists('mb_internal_encoding') && ((int) ini_get('mbstring.func_overload')) & 2) {
            $mbEncoding = mb_internal_encoding();
            mb_internal_encoding('ASCII');
        }

        $CI = &get_instance();
        $CI->load->model('settings_m');

        $had_configs = $email_config !== null;

        if (!$had_configs) {
            $email_config = $CI->settings_m->interpret_email_settings();
        }

        $transports = array();

        switch ($email_config['type']) {
            case 'gmail':
            case 'smtp':
                $email_config['smtp_encryption'] = empty($email_config['smtp_encryption']) ? null : $email_config['smtp_encryption'];

                if ($email_config['type'] == "gmail") {
                    # Try connect to smtp.gmail.com:465 using SSL.
                    $transports[] = Swift_SmtpTransport::newInstance("smtp.gmail.com", 465, "ssl")
                        ->setUsername($email_config['smtp_user'])
                        ->setPassword($email_config['smtp_pass']);

                    # If that fails, try to connect to smtp.gmail.com:587 using STARTTLS.
                    $transports[] = Swift_SmtpTransport::newInstance("smtp.gmail.com", 587, "tls")
                        ->setUsername($email_config['smtp_user'])
                        ->setPassword($email_config['smtp_pass']);
                } else {
                    $transports[] = Swift_SmtpTransport::newInstance($email_config['smtp_host'], $email_config['smtp_port'], $email_config['smtp_encryption'])
                        ->setUsername($email_config['smtp_user'])
                        ->setPassword($email_config['smtp_pass']);
                }

                if (!$had_configs) {
                    # Not testing configs, so throw in sendmail and mail(), and wrap around a failover.
                    $transports[] = Swift_SendmailTransport::newInstance('/usr/sbin/sendmail -bs');
                    $transports[] = Swift_MailTransport::newInstance();
                    $transport = Swift_FailoverTransport::newInstance($transports);
                } else {
                    # It's just testing configs, don't wrap around a failover.
                    $transport = reset($transports);
                }
                break;
            default:
                $transport = Swift_FailoverTransport::newInstance(array(
                    Swift_SendmailTransport::newInstance('/usr/sbin/sendmail -bs'),
                    Swift_MailTransport::newInstance()
                ));
                break;
        }

        $mailer = Swift_Mailer::newInstance($transport);

        if (isset($mbEncoding)) {
            mb_internal_encoding($mbEncoding);
        }
    }
    
    if ($converter === null) {
        $converter = new \Markdownify\Converter;
    }
    
    if (is_string($to) and strpos($to, ',') !== FALSE) {
        $to = explode(',', $to);
    } elseif (!is_array($to)) {
        $to = array($to);
    }
    
    $from_name = Business::getBrandName();

    if (empty($from)) {
        # Deal with notify email being a comma-separated list.
        $from = array_reset(explode(',', Business::getNotifyEmail()));
    }

    if (Settings::get('enable_pdf_attachments') == 0) {
        $attachments = array();
    }

    if (function_exists('mb_internal_encoding') && ((int) ini_get('mbstring.func_overload')) & 2) {
        $mbEncoding = mb_internal_encoding();
        mb_internal_encoding('ASCII');
    }

    $swift_message = Swift_Message::newInstance()->setFrom($from, $from_name);

    foreach ($attachments as $filename => $contents) {
        $swift_message->attach(Swift_Attachment::newInstance($contents, $filename, "application/pdf"));
    }

    $failed_recipients = array();
    $num_sent = 0;
    foreach ($to as $recipient) {
        $processed_message = str_ireplace(array(
            '{bcc}',
            '{tracking_image}'
        ), array(
            '',
            (!empty($unique_id) and ! empty($item_type)) ? "<img src='" . site_url("record_view/" . base64_encode($recipient) . "/$unique_id/$item_type") . "' width='1' height='1' />" : ''
        ), $message);

        if (Swift_Validate::email($recipient)) {
            $swift_message
                ->setTo($recipient)
                ->setSubject($subject)
                ->setBody($processed_message, 'text/html')
                ->addPart($converter->parseString($processed_message), 'text/plain');
            $num_sent += $mailer->send($swift_message, $failed_recipients);

            if ($recipient != Business::getNotifyEmail() && Settings::get('bcc')) {
                # It was sent to a client, let's BCC this stuff.
                $buffer = str_ireplace('{bcc}', __("global:bcc_was_sent_to", array($recipient, format_date(time()))) . '<br /><hr /><br />', $message);
                $buffer = str_ireplace('{tracking_image}', ((!empty($unique_id) and ! empty($item_type)) ? "<img src='" . site_url("record_view/" . base64_encode($recipient) . "/$unique_id/$item_type") . "' width='1' height='1' />" : ''), $buffer);

                $swift_message
                    ->setTo(Business::getNotifyEmail())
                    ->setSubject("BCC - " . $subject)
                    ->setBody($buffer, 'text/html')
                    ->addPart($converter->parseString($buffer), 'text/plain');
                $num_sent += $mailer->send($swift_message, $failed_recipients);
            }

        }
    }
    
    if (isset($mbEncoding)) {
        mb_internal_encoding($mbEncoding);
    }

    if ($num_sent > 0) {
        return array(
            "failed_recipients" => $failed_recipients,
            "num_sent" => $num_sent,
        );
    } else {
        return false;
    }
}

function add_column($table, $name, $type, $constraint = null, $default = '', $null = FALSE, $after_field = '') {
    $CI = &get_instance();

    if ($type == 'decimal') {
        if ($CI->db->dbdriver == "mysqli" and is_array($constraint)) {
            $constraint = implode(",", $constraint);
        } elseif ($CI->db->dbdriver == "mysql" and is_string($constraint)) {
            $constraint = explode(",", $constraint);
        }
    }

    $result = $CI->db->query("SHOW COLUMNS FROM " . $CI->db->dbprefix($table) . " LIKE '{$name}'")->row_array();

    if (!isset($result['Field']) or $result['Field'] != $name) {
        $properties = array(
            'type' => $type,
            'null' => $null,
        );

        if ($null === FALSE) {
            $properties['default'] = $default;
        }

        if ($constraint !== NULL) {
            $properties['constraint'] = $constraint;
        }

        return $CI->dbforge->add_column($table, array(
                    $name => $properties,
                        ), $after_field);
    }
}

function drop_column($table, $name) {
    $CI = &get_instance();
    $result = $CI->db->query("SHOW COLUMNS FROM " . $CI->db->dbprefix($table) . " LIKE '{$name}'")->row_array();

    if (isset($result['Field']) and $result['Field'] == $name) {
        return $CI->dbforge->drop_column($table, $name);
    }
}

function get_count($type, $client_id = 0) {

    static $counts = array(
        'paid' => array(),
        'overdue' => array(),
        'sent_but_unpaid' => array(),
        'unsent' => array(),
        'recurring' => array(),
        'estimates' => array(),
        'accepted' => array(),
        'rejected' => array(),
        'unanswered' => array(),
        'credit_notes' => array(),
        'all' => array(),
        'proposals' => array(),
        'proposals_accepted' => array(),
        'proposals_rejected' => array(),
        'proposals_unanswered' => array(),
        'task_comments' => array(),
        'project_comments' => array(),
        'estimates_unsent' => array(),
    );

    $client_id = (int) $client_id;

    if (isset($counts[$type][$client_id])) {
        return $counts[$type][$client_id];
    }

    $CI = &get_instance();
    $CI->load->model('invoices/invoice_m');
    $CI->load->model('projects/project_task_m');
    $CI->load->model('projects/project_m');

    switch ($type) {
        case 'all':
            $counts[$type][$client_id] = get_count('unpaid', $client_id) + get_count('paid', $client_id);
            break;
        case 'proposals':
        case 'proposals_accepted':
        case 'proposals_rejected':
        case 'proposals_unanswered':
            $CI->load->model('proposals/proposals_m');
            $client_id = ($client_id == 0) ? null : $client_id;

            if ($client_id !== NULL) {
                $where = array('client_id' => $client_id);
            } else {
                $where = array();
            }

            if ($type !== 'proposals') {
                # Remove "proposals_" from the type to find the desired status.
                $status = strtoupper(substr($type, strlen('proposals_')));
                $status = $status == "UNANSWERED" ? "" : $status;
                $where['status'] = $status;
            }

            $counts[$type][$client_id] = $CI->proposals_m->count($where);
            break;
        case 'estimates':
            $counts[$type][$client_id] = $CI->invoice_m->countEstimates($client_id);
            break;
        case 'accepted':
            $counts[$type][$client_id] = $CI->invoice_m->countEstimates($client_id, 'ACCEPTED');
            break;
        case 'rejected':
            $counts[$type][$client_id] = $CI->invoice_m->countEstimates($client_id, 'REJECTED');
            break;
        case 'unanswered':
            $counts[$type][$client_id] = $CI->invoice_m->countEstimates($client_id, '');
            break;
        case 'estimates_unsent':
            $counts[$type][$client_id] = $CI->invoice_m->countEstimates($client_id, null, false);
            break;
        case 'credit_notes':
            $counts[$type][$client_id] = $CI->invoice_m->count_credit_notes($client_id);
            break;
        case 'paid':
            $buffer = $CI->invoice_m->paid_totals($client_id == 0 ? null : $client_id);
            $counts[$type][$client_id] = $buffer['count'];
            break;
        case 'overdue':
            $buffer = $CI->invoice_m->overdue_totals($client_id == 0 ? null : $client_id);
            $counts[$type][$client_id] = $buffer['count'];
            break;
        case 'sent_but_unpaid':
            $counts[$type][$client_id] = $CI->invoice_m->count_sent_but_unpaid($client_id == 0 ? null : $client_id);
            break;
        case 'unpaid':
            $buffer = $CI->invoice_m->unpaid_totals($client_id == 0 ? null : $client_id);
            $counts[$type][$client_id] = $buffer['count'];
            break;
        case 'unsent':
            $counts[$type][$client_id] = $CI->invoice_m->count_unsent($client_id == 0 ? null : $client_id);
            break;
        case 'unsent_recurrences':
            $counts[$type][$client_id] = $CI->invoice_m->count_unsent_recurrences($client_id == 0 ? null : $client_id);
            break;
        case 'unsent_not_recurrences':
            $counts[$type][$client_id] = $CI->invoice_m->count_unsent_not_recurrences($client_id == 0 ? null : $client_id);
            break;
        case 'unsent':
            $counts[$type][$client_id] = $CI->invoice_m->count_unsent($client_id == 0 ? null : $client_id);
            break;
        case 'recurring':
            $counts[$type][$client_id] = $CI->invoice_m->count_recurring($client_id == 0 ? null : $client_id);
            break;
        case 'task_comments':
            # In this case, $client_id is actually a task ID.
            $counts[$type][$client_id] = $CI->project_task_m->get_comment_count($client_id);
            break;
        case 'project_comments':
            # In this case, $client_id is actually a project ID.
            $counts[$type][$client_id] = $CI->project_m->get_comment_count($client_id);
            break;
    }

    return $counts[$type][$client_id];
}

function pancake_upload($input, $unique_id_or_comment_id, $type = 'invoice', $client_id = 0, $verify_only = false) {
    $return = array();

    if (!empty($input['name'])) {

        switch ($type) {
            case 'invoice':
                $folder_name = sha1(time() . $unique_id_or_comment_id) . '/';
                break;
            case 'tickets':
                is_dir('uploads/tickets/') or mkdir('uploads/tickets/', 0777);
                $folder_name = 'tickets/' . sha1(time()) . '/';
                break;
            case 'expenses':
                is_dir('uploads/expenses/') or mkdir('uploads/expenses/', 0777);
                $folder_name = 'expenses/' . sha1(time()) . '/';
                break;
            case 'client':
                is_dir('uploads/clients/') or mkdir('uploads/clients/', 0777);
                $folder_name = 'clients/' . $client_id . '-' . sha1(time()) . '/';
                break;
            case 'redactor':
                $folder_name = 'redactor/';
                break;
            default:
                $folder_name = 'branding/';
                break;
        }

        if (!is_array($input['name'])) {
            $input['name'] = array($input['name']);
            $input['tmp_name'] = array($input['tmp_name']);
        }

        for ($i = 0; $i < count($input['name']); $i++) {
            if (empty($input['name'][$i])) {
                continue;
            }
            is_dir('uploads/' . $folder_name) or mkdir('uploads/' . $folder_name, 0777);

            switch ($type) {
                case 'redactor':
                    $real_name = sha1(time()) . "." . pathinfo($input['name'][$i], PATHINFO_EXTENSION);
                    break;
                default:
                    $real_name = basename($input['name'][$i]);
                    break;
            }

            $target_path = 'uploads/' . $folder_name . $real_name;

            # Check the extension.
            $allowed = explode(',', Settings::get('allowed_extensions'));
            $is_allowed = false;
            foreach ($allowed as $one_allowed_extension) {
                $one_allowed_extension = trim($one_allowed_extension);

                if (strtolower(pathinfo($input['name'][$i], PATHINFO_EXTENSION)) == strtolower($one_allowed_extension)) {
                    $is_allowed = true;
                }
            }

            if (!$is_allowed) {
                return NOT_ALLOWED;
            }

            if (!$verify_only) {
                if (move_uploaded_file($input['tmp_name'][$i], $target_path) || rename($input['tmp_name'][$i], $target_path)) {
                    $base_url = explode('://', base_url());
                    $base_without_index = $base_url[0] . '://' . str_ireplace('//', '/', str_ireplace('index.php', '', $base_url[1]));
                    $return[$real_name] = array(
                        'real_name' => $real_name,
                        'folder_name' => $folder_name,
                        'url' => $base_without_index . 'uploads/' . $folder_name . rawurlencode($real_name)
                    );
                } else {
                    return false;
                }
            } else {
                $base_without_index = (substr(base_url(), -10) == 'index.php/') ? substr(base_url(), 0, strlen(base_url()) - 10) . '/' : base_url();
                $return[$real_name] = array(
                    'real_name' => $real_name,
                    'folder_name' => $folder_name,
                    'url' => $base_without_index . 'uploads/' . $folder_name . rawurlencode($real_name)
                );
            }
        }
    }
    return $return;
}

function time_to_decimal($hours_minutes) {
    $hours_minutes = explode(':', $hours_minutes);
    if (count($hours_minutes) == 1) {
        # It's just decimal for hours.
        return $hours_minutes[0];
    } elseif (count($hours_minutes) == 2) {
        # It's hh:mm. 15:30 => 15 + 30/60 => 15.5
        return $hours_minutes[0] + ($hours_minutes[1] / 60);
    } elseif (count($hours_minutes) == 3) {
        # It's hh:mm:ss. 15:30:15 => 15 + 30/60 + 15/3600
        return $hours_minutes[0] + ($hours_minutes[1] / 60) + ($hours_minutes[2] / 3600);
    } else {
        # It's invalid.
        return 0;
    }
}

/**
 * Transforms the assigned_user_id to the right ID if it's 0 and there's only one user.
 *
 * @param int $assigned_user_id
 * @return int
 */
function fix_assigned($assigned_user_id) {

    $CI = &get_instance();
    $CI->load->model('users/user_m');

    static $user_id = null;

    if ($user_id === null) {
        $users = $CI->user_m->get_users_list();
        $users = array_keys($users);
        $user_id = reset($users);
    }

    if ($CI->user_m->count_all() == 1) {
        return $user_id;
    } else {
        return $assigned_user_id;
    }
}

function get_pdf($type, $unique_id, $return_html = false, $stream = false) {
    $CI = &get_instance();
    $original_layout = $CI->template->_layout;
    unset($CI->template->_partials['notifications']);
    unset($CI->template->_partials['search']);
    $CI->template->_module = 'frontend';
    require_once APPPATH . 'modules/gateways/gateway.php';
    $CI->load->helper('typography');
    $CI->load->model('proposals/proposals_m');
    $CI->load->model('invoices/invoice_m');
    $CI->load->model('files/files_m');
    $CI->load->model('clients/clients_m');

    $CI->template->pdf_mode = true;
    switch_theme(false);
    asset::add_path($CI->template->get_theme_path());

    if ($type == 'invoice') {

        $invoice = $CI->invoice_m->get($unique_id);
        Business::setBusinessFromClient($invoice['client_id']);

        $data_array = array(
            'site_name' => preg_replace('/[^A-Za-z0-9-]/', '', str_ireplace(' ', '-', strtolower(Business::getBrandName()))),
            'number' => $invoice['invoice_number'],
            'type' => $invoice['type'] == 'DETAILED' ? 'invoice' : strtolower($invoice['type']),
            'phone' => $invoice['phone'],
            'company' => $invoice['company'],
            'date_of_creation' => $invoice['date_entered'],
        );
        $filename = $CI->dispatch_return('pdf_filename_generated', $data_array);

        if (is_array($filename) or empty($filename)) {
            // Plugin is not installed; use old format:
            $filename = "{$data_array['site_name']}-{$data_array['type']}-{$data_array['number']}.pdf";
        }

        switch_language($invoice['language']);

        $CI->template->is_paid = $CI->invoice_m->is_paid($unique_id);
        $CI->template->files = (array) $CI->files_m->get_by_unique_id($unique_id);
        $CI->template->invoice = (array) $invoice;
        $CI->template->type = $invoice['type'];

        $CI->template->set_layout('detailed');
        $html = $CI->template->build('detailed', array(), TRUE);
    } elseif ($type == 'proposal') {
        $proposal = (array) $CI->proposals_m->getByUniqueId($unique_id, true);
        $proposal['client'] = (array) $proposal['client'];
        Business::setBusinessFromClient($proposal['client']['id']);

        switch_language($proposal['client']['language']);

        $data_array = array(
            'site_name' => preg_replace('/[^A-Za-z0-9-]/', '', str_ireplace(' ', '-', strtolower(Business::getBrandName()))),
            'type' => 'proposal',
            'number' => $proposal['proposal_number'],
            'phone' => $proposal['client']['phone'],
            'company' => $proposal['client']['company'],
            'date_of_creation' => $proposal['created'],
        );
        $filename = $CI->dispatch_return('pdf_filename_generated', $data_array);

        if (is_array($filename) or empty($filename)) {
            // Plugin is not installed; use old format:
            $filename = "{$data_array['site_name']}-{$data_array['type']}-{$data_array['number']}.pdf";
        }

        $CI->template->new = (bool) $proposal;
        $result = $CI->db->get('clients')->result_array();
        $clients = array();
        foreach ($result as $row) {
            $row['title'] = $row['first_name'] . ' ' . $row['last_name'] . ($row['company'] ? ' - ' . $row['company'] : '');
            $clients[] = $row;
        }
        $CI->template->clients = $clients;
        $CI->template->proposal = $proposal;
        $CI->template->set_layout('proposal');
        $html = $CI->template->build('proposal', array(), true);
    }

    # Fix dompdf rendering issues.
    # This is here and not in get_pdf_raw() so that you can see the manipulations with /die.
    $html = str_ireplace('border-style: initial;', 'border-style: inherit;', $html);
    $html = str_ireplace('border-color: initial;', 'border-color: inherit;', $html);
    $html = preg_replace_callback("/(<p(?:\\s*style=\"text-align: center;\"\\s*)?>)\\s*(<img[^>]*(display:\\s*block;?)[^>]*>)\\s*(<\/p>)?/i", function($matches) {
        return '<p style="text-align: center;">' . str_ireplace($matches[3], "", $matches[2]) . '</p>';
    }, $html);
    $html = preg_replace_callback("/(<p(?:\\s*style=\"text-align: center;\"\\s*)?>)\\s*(<img[^>]*(margin:\\s*auto;?)[^>]*>)\\s*(<\/p>)?/i", function($matches) {
        return '<p style="text-align: center;">' . str_ireplace($matches[3], "", $matches[2]) . '</p>';
    }, $html);

    if (!$return_html) {
        $pdf = get_pdf_raw($filename, $html, $stream);
    }

    switch_theme(true);
    $CI->template->set_layout($original_layout);
    $CI->template->set_partial('notifications', 'partials/notifications');
    $CI->template->set_partial('search', 'partials/search');

    if ($return_html) {
        return $html;
    } else {
        return array(
            'contents' => $pdf,
            'invoice' => ($type == 'invoice' ? $invoice : $proposal),
            'filename' => $filename
        );
    }
}

function get_pdf_raw($filename, $html, $stream = false, $paper_size = null, $orientation = 'portrait') {

    if (!is_dir(FCPATH . 'uploads/pdfs/' . $filename)) {
        mkdir(FCPATH . 'uploads/pdfs/' . $filename, 0777, true);
    }

    if ($paper_size === null) {
        $paper_size = Settings::get('pdf_page_size');
    }

    if ($filename === null) {
        $filename = 'file.pdf';
    }

    $dompdf_html_to_pdf = function($html) use ($paper_size, $orientation) {
        include_once APPPATH . 'libraries/dompdf/dompdf_config.custom.inc.php';
        include_once APPPATH . 'libraries/dompdf/dompdf_config.inc.php';
        $dompdf = new DOMPDF();
        $dompdf->set_paper($paper_size, $orientation);
        $dompdf->load_html($html);
        $dompdf->render();
        return $dompdf->output();
    };

    if (!file_exists(FCPATH . 'uploads/pdfs/' . $filename . "/" . md5($html . $paper_size) . ".pdf")) {
        $html_to_pdf_library = Settings::get("html_to_pdf_library") ?: "dompdf";

        if ($html_to_pdf_library == "wkhtmltopdf") {
            # Loading stuff when the protocol is not specified leads to an error.
            # We only do this for wkhtmltopdf, not dompdf, because in dompdf it'd lead to it understanding the Google Fonts calls, which'd break it.
            $wkhtmltopdf_html = preg_replace("/href=(['\"])\/\//ui", "href=$1http://", $html);
            $wkhtmltopdf_html = preg_replace("/url\((['\"]?)\/\//ui", "url($1http://", $wkhtmltopdf_html);

            $temp_dir = FCPATH."uploads/";
            $temp_pdf_filename = $temp_dir.uniqid().".pdf";
            $temp_html_filename = $temp_dir.uniqid().".html";
            file_put_contents($temp_html_filename, $wkhtmltopdf_html);

            $output = array();
            $return_code = 0;

            $command = "/usr/local/bin/wkhtmltopdf --print-media-type " . escapeshellarg($temp_html_filename) . " " . escapeshellarg($temp_pdf_filename) . " 2>&1";
            $result = exec($command, $output, $return_code);

            if (file_exists($temp_pdf_filename)) {
                $pdf_contents = file_get_contents($temp_pdf_filename);
                unlink($temp_pdf_filename);
            }

            if (file_exists($temp_html_filename)) {
                unlink($temp_html_filename);
            }

            if ($return_code !== 0) {
                # Failed to generate using wkhtmltopdf, try using dompdf instead.

                # Code 127 is "no such file or directory".
                if (IS_DEBUGGING && $return_code !== 127) {
                    debug($temp_dir, $command, $output, $return_code);
                }

                $pdf_contents = $dompdf_html_to_pdf($html);
            }
        } else {
            $pdf_contents = $dompdf_html_to_pdf($html);
        }

        if (!IS_DEBUGGING) {
            # Try to cache this PDF.
            # If it fails, it'll just generate one again (as has been the case from 1.0.0 to 4.0.3); it doesn't affect Pancake's functionality.
            # Hence why we don't even care about checking if it succeeded.
            file_put_contents(FCPATH . 'uploads/pdfs/' . $filename . "/" . md5($html . $paper_size) . ".pdf", $pdf_contents);
        }
    } else {
        $pdf_contents = file_get_contents(FCPATH . 'uploads/pdfs/' . $filename . "/" . md5($html . $paper_size) . ".pdf");
    }

    if ($stream) {
        header("Cache-Control: private");
        header("Content-type: application/pdf");
        header("Content-Length: " . mb_strlen($pdf_contents, "8bit"));
        header("Content-Disposition: inline; filename=\"$filename\"");
        echo $pdf_contents;
        flush();
    } else {
        return $pdf_contents;
    }
}

/**
 * Get either a Gravatar URL or complete image tag for a specified email address.
 *
 * @param string $email The email address
 * @param string $s Size in pixels, defaults to 80px [ 1 - 2048 ]
 * @param string $d Default imageset to use [ 404 | mm | identicon | monsterid | wavatar ]
 * @param string $r Maximum rating (inclusive) [ g | pg | r | x ]
 * @param boole $img True to return a complete IMG tag False for just the URL
 * @param array $atts Optional, additional key/value attributes to include in the IMG tag
 * @return String containing either just a URL or a complete image tag
 * @source http://gravatar.com/site/implement/images/php/
 */
function get_gravatar($email, $s = 80, $d = 'mm', $r = 'g', $img = false, $atts = array()) {
    $url = (isset($_SERVER['HTTPS'])) ? 'https://secure.' : 'http://www.';
    $url .= 'gravatar.com/avatar/';
    $url .= md5(strtolower(trim($email)));
    $url .= "?s=$s&d=$d&r=$r";
    if ($img) {
        $url = '<img src="' . $url . '"';
        foreach ($atts as $key => $val) {
            $url .= ' ' . $key . '="' . $val . '"';
        }
        $url .= ' />';
    }
    return $url;
}

function get_timer_attrs($timers, $task_id) {
    if (isset($timers[$task_id])) {
        $task = $timers[$task_id];
    } else {
        $task = array(
            'id' => 0,
            'is_paused' => 0,
            'current_seconds' => 0,
            'last_modified_timestamp' => 0
        );
    }

    return array(
        "task-id" => $task_id,
        "is-paused" => $task['is_paused'],
        "current-seconds" => $task['current_seconds'],
        "last-modified-timestamp" => $task['last_modified_timestamp'],
        "start" => __('global:start_timer'),
        "stop" => __('global:stop_timer')
    );
}

function build_data_attrs($attrs) {
    $html = array();
    foreach ($attrs as $attr_name => $attr_value) {
        if (!preg_match("/^[a-zA-Z_:][-a-zA-Z0-9_:.]*$/u", $attr_name)) {
            throw new Exception("You cannot use the attribute 'data-$attr_name'.");
        }

        $attr_value = str_ireplace('"', "'", $attr_value);
        $html[] = "data-" . $attr_name . '="' . $attr_value . '"';
    }

    return implode(" ", $html);
}

function timer($timers, $task_id) {
    echo build_data_attrs(get_timer_attrs($timers, $task_id));
}

/**
 * Convert hour format to decimal.
 * If it's already decimal, it doesn't change the value.
 *
 * eg. 00:30 returns 0.5 and 0.5 returns 0.5.
 *
 * @param string $value
 * @return string
 */
function process_hours($value) {

    if (empty($value)) {
        $value = "0";
    }

    if (stristr($value, ':') === false) {
        // Decimal.

        if ($value[0] == ".") {
            // It's a decimal that doesn't have a 0 at the start (e.g. ".25"). Add the zero.
            $value = "0" . $value;
        }

        $regex = "/([0-9]+(?:\.[0-9]+)?)/";
        $matches = array();
        $result = preg_match($regex, $value, $matches);
        if ($result === 1) {
            $value = (float) $matches[1];
        } else {
            $value = 0;
        }
    } else {
        // Base60.
        $value = explode(':', $value);
        $hours = $value[0] + ($value[1] / 60);

        if (count($value) == 3) {
            // Include seconds.
            $hours = $hours + ($value[2] / 3600);
        }

        $value = $hours;
    }

    return $value;
}

function protocol() {
    return 'http' . (IS_SSL ? 's' : '') . '://';
}

function switch_language($new_language) {
    return get_instance()->lang->switch_language($new_language);
}

function invoice_item_type_id($item) {
    if (!isset($item['item_type_table'])) {
        return '';
    }

    switch ($item['item_type_table']) {
        case 'project_expenses':
            return "EXPENSE_" . $item['item_type_id'];
            break;
        case 'project_tasks':
            return "TASK_" . $item['item_type_id'];
            break;
        case 'project_milestones':
            return "MILESTONE_" . $item['item_type_id'];
            break;
        default:
            return '';
    }
}

function build_invoice_item_id_link($invoice_item_id) {
    $CI = get_instance();
    $CI->load->model('invoices/invoice_m');
    $invoice = $CI->invoice_m->getByRowId($invoice_item_id);
    if (isset($invoice['unique_id'])) {
        return anchor($invoice['unique_id'], "#" . $invoice['invoice_number']);
    } else {
        return "[Invoice No Longer Exists]";
    }
}

function invoice_item_type($item) {

    if ($item['type'] == 'support_ticket') {
        return "Support Ticket";
    }

    if ($item['item_type_id'] == 0) {
        return __('items:select_standard');
    }

    $tables = array(
        '' => __('items:select_standard'),
        'project_expenses' => __('items:select_expense'),
        'project_tasks' => __('global:task'),
        'project_milestones' => __('milestones:milestone')
    );
    return isset($tables[$item['item_type_table']]) ? $tables[$item['item_type_table']] : __('items:select_standard');
}

/**
 * Generate an Invoice for a billable ticket
 * @param int $ticket_id
 * @param int $client_id
 * @param float $amount
 * @todo Move description text to a template in settings
 */
function generate_ticket_invoice($ticket_id, $client_id, $priority_id) {
    $due_date = Settings::get('default_invoice_due_date') > 0 ? strtotime("+" . Settings::get('default_invoice_due_date') . " days") : '';
    $CI = &get_instance();

    $CI->load->model('invoices/invoice_m');
    $CI->load->model('clients/client_support_rates_matrix_m');

    $support_rate = $CI->client_support_rates_matrix_m->getByClientIdAndPriorityId($client_id, $priority_id);
    $amount = $support_rate['rate'];
    $tax_id = $support_rate['tax_id'];

    $invoice_data = array(
        'unique_id' => $CI->invoice_m->_generate_unique_id(),
        'client_id' => $client_id,
        'project_id' => '',
        'type' => 'DETAILED',
        'invoice_number' => $CI->invoice_m->_generate_invoice_number(),
        'is_viewable' => '1',
        'is_recurring' => '0',
        'frequency' => 'm',
        'auto_send' => '1',
        'send_x_days_before' => Settings::get('send_x_days_before'),
        'due_date' => $due_date,
        'currency' => '0',
        'description' => '',
        'invoice_item' =>
        array(
            'name' =>
            array(
                0 => sprintf('Invoice for Ticket # %s', $ticket_id), //move this string to settings
            ),
            'qty' =>
            array(
                0 => '1',
            ),
            'rate' =>
            array(
                0 => $amount,
            ),
            'tax_id' =>
            array(
                0 => $tax_id,
            ),
            'item_time_entries' =>
            array(
                0 => '',
            ),
            'item_type_id' =>
            array(
                0 => '',
            ),
            'type' =>
            array(
                0 => 'support_ticket',
            ),
            'total' =>
            array(
                0 => $amount,
            ),
            'description' =>
            array(
                0 => '',
            ),
        ),
        'notes' => '',
        'amount' => $amount,
        'partial-amount' =>
        array(
            1 => '100',
        ),
        'partial-is_percentage' =>
        array(
            1 => '1',
        ),
        'partial-notes' =>
        array(
            1 => '',
        ),
        'date_entered' => time(),
        'partial-due_date' =>
        array(
            1 => $due_date,
        ),
    );

    $result = $CI->invoice_m->insert($invoice_data);

    if ($result) {
        return array("id" => $CI->invoice_m->getIdByUniqueId($result), "uid" => $result);
    }

    return FALSE;
}

/**
 * Get Client support rate data
 */
function get_client_support_matrix($client_id) {
    $CI = &get_instance();

    $CI->load->model('tickets/ticket_priorities_m', 'priorities');

    $CI->load->model('clients/client_support_rates_matrix_m', 'csrm');

    $_client_has_rates = false;

    $ticket_priorities = $CI->priorities->get_all();

    $_client_ticket_priorities = $CI->csrm->byClientId($client_id);

    if ($_client_ticket_priorities) {
        $_client_has_rates = true;

        foreach ($_client_ticket_priorities as $k => $cpriority) {
            foreach ($ticket_priorities as $k2 => &$tpriority) {
                if ($cpriority->priority_id == $tpriority->id) {
                    $tpriority->default_rate = $cpriority->rate;
                }
            }
        }
    } else {
        # The default client support rates.
        $_client_has_rates = true;
        $ticket_priorities = array(
            0 => (object) array(
                'id' => '1',
                'title' => 'Normal',
                'background_color' => '#41b8e3',
                'font_color' => '#ffffff',
                'text_shadow' => '1px 1px #1e83a8',
                'box_shadow' => '0px 1px 1px 0px #1e83a8',
                'default_rate' => '0.00',
            ),
            1 => (object) array(
                'id' => '2',
                'title' => 'Elevated',
                'background_color' => '#88ce5c',
                'font_color' => '#ffffff',
                'text_shadow' => '1px 1px #5ca534',
                'box_shadow' => '0px 1px 1px 0px #62a33d',
                'default_rate' => '0.00',
            ),
            2 => (object) array(
                'id' => '3',
                'title' => 'Urgent',
                'background_color' => '#eb4141',
                'font_color' => '#ffffff',
                'text_shadow' => '1px 1px #b32222',
                'box_shadow' => '0px 1px 1px 0px #b32222',
                'default_rate' => '0.00',
            ),
        );
    }

    return $data = array('ticket_priorities' => $ticket_priorities, 'client_id' => $client_id, 'client_has_rates' => $_client_has_rates);
}

function get_between($content, $start, $end) {
    $r = explode($start, $content);
    if (isset($r[1])) {
        $r = explode($end, $r[1]);
        return $r[0];
    }
    return '';
}

function getTextColor($hexcolor) {
    $r = hexdec(substr($hexcolor, 0, 2));
    $g = hexdec(substr($hexcolor, 2, 2));
    $b = hexdec(substr($hexcolor, 4, 2));
    $yiq = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
    return ($yiq >= 128) ? 'black' : 'white';
}

function get_dropdown($table, $id_field, $value_field, $primary_or_where = null) {
    $db = get_instance()->db;

    if (!empty($primary_or_where)) {
        if (is_array($primary_or_where)) {
            foreach ($primary_or_where as $field => $value) {
                if (is_array($value)) {
                    $db->where_in($field, $value);
                } else {
                    $db->where($field, $value);
                }
            }
        } else {
            $db->where(array($table . '.' . $id_field => $primary_or_where));
        }
    }

    $results = $db->get($table)->result_array();
    $return = array();
    foreach ($results as $row) {
        if (is_callable($value_field)) {
            $return[$row[$id_field]] = call_user_func($value_field, $row);
        } else {
            $return[$row[$id_field]] = $row[$value_field];
        }
    }

    return $return;
}

if (!function_exists('function_usable')) {

    /**
     * Function usable
     *
     * Executes a function_exists() check, and if the Suhosin PHP
     * extension is loaded - checks whether the function that is
     * checked might be disabled in there as well.
     *
     * This is useful as function_exists() will return FALSE for
     * functions disabled via the *disable_functions* php.ini
     * setting, but not for *suhosin.executor.func.blacklist* and
     * *suhosin.executor.disable_eval*. These settings will just
     * terminate script execution if a disabled function is executed.
     *
     * The above described behavior turned out to be a bug in Suhosin,
     * but even though a fix was commited for 0.9.34 on 2012-02-12,
     * that version is yet to be released. This function will therefore
     * be just temporary, but would probably be kept for a few years.
     *
     * @link	http://www.hardened-php.net/suhosin/
     * @param	string	$function_name	Function to check for
     * @return	bool	TRUE if the function exists and is safe to call,
     * 			FALSE otherwise.
     */
    function function_usable($function_name) {
        static $_suhosin_func_blacklist;

        if (function_exists($function_name)) {
            if (!isset($_suhosin_func_blacklist)) {
                if (extension_loaded('suhosin')) {
                    $_suhosin_func_blacklist = explode(',', trim(ini_get('suhosin.executor.func.blacklist')));

                    if (!in_array('eval', $_suhosin_func_blacklist, TRUE) && ini_get('suhosin.executor.disable_eval')) {
                        $_suhosin_func_blacklist[] = 'eval';
                    }
                } else {
                    $_suhosin_func_blacklist = array();
                }
            }

            return !in_array($function_name, $_suhosin_func_blacklist, TRUE);
        }

        return FALSE;
    }

}

if (!function_exists('get_mimes')) {

    /**
     * Returns the MIME types array from config/mimes.php
     *
     * @return	array
     */
    function &get_mimes() {
        static $_mimes = array();

        if (file_exists(APPPATH . 'config/' . ENVIRONMENT . '/mimes.php')) {
            $_mimes = include(APPPATH . 'config/' . ENVIRONMENT . '/mimes.php');
        } elseif (file_exists(APPPATH . 'config/mimes.php')) {
            $_mimes = include(APPPATH . 'config/mimes.php');
        }

        return $_mimes;
    }

}

function client_name($record_or_id) {
    static $has_listeners = null;
    static $clients = null;

    if ($has_listeners === null) {
        $has_listeners = Events::has_listeners('client_name_generated');
    }
    
    if ($clients === null) {
        $clients = array();
        foreach (get_instance()->db->get("clients")->result_array() as $client) {
            $clients[$client['id']] = $client;
        }
    }
    
    if (empty($record_or_id)) {
        return __("global:nolongerexists");
    }

    if (is_numeric($record_or_id)) {
        $record_or_id = isset($clients[$record_or_id]) ? $clients[$record_or_id] : array();
    }

    $record_or_id = (array) $record_or_id;

    $format = "{{title}} {{first_name}} {{last_name}} {{#first_name}}{{#company}}-{{/company}}{{/first_name}} {{company}}";
    $default_name = get_instance()->mustache->render($format, $record_or_id);
    $default_name = preg_replace("/( +)/", " ", $default_name);
    $default_name = trim($default_name);

    if ($has_listeners) {
        $name = get_instance()->dispatch_return('client_name_generated', array(
            'record' => $record_or_id,
            'generated_name' => $default_name,
        ));
    } else {
        $name = $default_name;
    }

    if (is_array($name)) {
        // Plugin is not installed; use old format:
        $name = $default_name;
    }

    return $name;
}

/**
 * Switches between the admin theme and the frontend theme.
 *
 * This function is recommended for switching themes because it resolves an issue
 * that would cause Pancake not to work properly if a theme folder was deleted.
 *
 * @param boolean $admin
 */
function switch_theme($admin = true) {
    if ($admin) {
        $admin_prefix = 'admin/';
        $theme = PAN::setting('admin_theme');
    } else {
        $admin_prefix = '';
        $theme = PAN::setting('theme');
    }

    if (!file_exists(FCPATH . "third_party/themes/" . $admin_prefix . $theme)) {
        $theme = "pancake";
        # Reset the theme setting, because the theme no longer exists.
        Settings::set(($admin ? "admin_" : "") . 'theme', 'pancake');
    }

    get_instance()->template->set_theme($admin_prefix . $theme);
}

function get_recurring_frequencies_labels($frequency = null) {
    $data = array(
        'w' => __('global:week'),
        'bw' => __('global:biweekly'),
        'm' => __('global:month'),
        'q' => __('global:quarterly'),
        's' => __('global:every_six_months'),
        'y' => __('global:year'),
        'b' => __('global:biyearly'),
        't' => __('global:triennially')
    );

    return $frequency === null ? $data : $data[$frequency];
}

function get_recurring_frequencies_durations($frequency = null) {
    $data = array(
        'w' => "+1 week",
        'bw' => "+2 weeks",
        'm' => "+1 month",
        'q' => "+3 months",
        's' => "+6 months",
        'y' => "+1 year",
        'b' => "+2 years",
        't' => "+3 years",
    );

    return $frequency === null ? $data : $data[$frequency];
}

function implode_to_human_csv($array) {
    $array = implode(", ", $array);
    $search = ", ";
    $replace = " " . __("global:and") . " ";

    $pos = strrpos($array, $search);

    if ($pos !== false) {
        $array = substr_replace($array, $replace, $pos, strlen($search));
    }

    return $array;
}

function string_starts_with($haystack, $needle) {
    return $needle === "" || strpos($haystack, $needle) === 0;
}

function string_ends_with($haystack, $needle) {
    return $needle === "" || substr($haystack, -strlen($needle)) === $needle;
}

function human_invoice_type($type) {
    get_instance()->load->helper('inflector');

    switch ($type) {
        case 'CREDIT_NOTE':
        case 'ESTIMATE':
            $return = plural(strtolower($type));
            break;
        default:
            $return = 'invoices';
            break;
    }

    return $return;
}

/**
 * Currently only formats numbers to 2 digits.
 * If the original number needs more than 2 digits, more digits will be displayed,
 * up to 10 digits.
 *
 * In the future, this will take into account region settings and format numbers accordingly.
 *
 * @param float $amount
 * @param bool $maintain_precision
 */
function pancake_number_format($amount, $maintain_precision = false) {
    $precision_format = function ($amount, $precision = 2) {
        $result = pow(10, $precision);

        if (round(floor($amount * $result), $precision) == round($amount * $result, $precision)) {
            $res = sprintf("%.${precision}f", $amount);
        } else {
            $res = $amount;
        }
        return $res;
    };

    $maximum_precision = $maintain_precision ? 10 : 2;

    $amount = round($amount, $maximum_precision);
    $amount = $precision_format($amount);

    $decimals_left = explode(".", $amount);
    $decimals_left = strlen(end($decimals_left));

# @todo take into account region settings
    $thousands_separator = ",";
    $decimal_separator = ".";

    return number_format($precision_format($amount), $decimals_left, $decimal_separator, $thousands_separator);
}

function elapsed_time() {
    return number_format(microtime(true) - REQUEST_TIME, 3);
}

function get_max_upload_size() {

    $to_bytes = function($val) {
        $val = trim($val);
        $last = strtolower($val[strlen($val) - 1]);
        switch ($last) {
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }

        return $val;
    };

    $from_bytes = function($bytes, $precision = 2) {
        $base = log($bytes) / log(1024);
        $suffixes = array('', 'KB', 'MB', 'GB', 'TB');
        return round(pow(1024, $base - floor($base)), $precision) . $suffixes[floor($base)];
    };

    $upload = $to_bytes(ini_get("upload_max_filesize"));
    $post = $to_bytes(ini_get("post_max_size"));
    $smallest = min(array($upload, $post));

    return $from_bytes($smallest);
}

/**
 * Does the same as reset(), without the "Only variables should be passed by reference" error.
 * @param array $arr
 * @return mixed
 */
function array_reset($arr) {
    return reset($arr);
}

/**
 * Does the same as end(), without the "Only variables should be passed by reference" error.
 * @param array $arr
 * @return mixed
 */
function array_end($arr) {
    return end($arr);
}

/**
 * Deprecated. Use Business::getLogo() instead.
 *
 * @param type $img_only
 * @param type $anchor
 * @param type $h
 * @param type $settings
 */
function logo($img_only = false, $anchor = true, $h = 1, $settings = null) {
    return Business::getLogo($img_only, $anchor, $h, $settings);
}

/**
 * Gets the JS used in the setup.js file.
 * It's here so that it can be used to calculate crc32() of the setup JS,
 * which is then use to create a filename based on the contents,
 * for filename-based cache-busting.
 *
 * @return string
 */
function get_setup_js() {
    $data = array(
        "raw_site_url" => site_url("{url}"),
        "pancake_language_strings" => get_instance()->lang->language,
        "settings" => Settings::get_all(),
        "datePickerFormat" => get_date_picker_format(),
        "task_time_interval" => format_hours(Settings::get('task_time_interval')),
        "pancake_demo" => IS_DEMO,
        "show_task_time_interval_help" => (process_hours(Settings::get('task_time_interval')) > 0),
        "pancake_taxes" => Settings::all_taxes(),
        "pancakeapp_com_base_url" => PANCAKEAPP_COM_BASE_URL,
        "manage_pancake_base_url" => MANAGE_PANCAKE_BASE_URL,
    );

    $str = "var ";
    foreach ($data as $key => $value) {
        if ($value === false) {
            $value = "false";
        } elseif ($value === true) {
            $value = "true";
        } elseif (is_array($value) or is_object($value)) {
            $value = json_encode($value);
        } else {
            $value = '"'.addslashes($value).'"';
        }
        $str .= "$key = $value,";
    }

    return substr($str, 0, -1).";";
}

/* End of file: pancake_helper.php */