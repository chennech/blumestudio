<?php

defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Pancake
 *
 * A simple, fast, self-hosted invoicing application
 *
 * @package		Pancake
 * @author		Pancake Dev Team
 * @copyright	Copyright (c) 2010, Pancake Payments
 * @license		http://pancakeapp.com/license
 * @link		http://pancakeapp.com
 * @since		Version 1.0
 */
// ------------------------------------------------------------------------

/**
 * The admin controller for Settings
 *
 * @subpackage	Controllers
 * @category	Settings
 */
class Admin extends Admin_Controller {

    /**
     * Smart_csv_m
     * @var Smart_csv_m
     */
    public $smart_csv_m;

    function __construct() {
        parent::__construct();

        is_admin() or access_denied();
    }

    function verify_integrity() {
        $this->load->model('upgrade/update_system_m');
        echo json_encode($this->update_system_m->verify_integrity());
    }

    /**
     * Lets the user edit the settings
     *
     * @access	public
     * @return	void
     */
    public function index($action = '') {
        include APPPATH . 'modules/gateways/gateway.php';
        $this->load->library('form_validation');
        $this->load->model('settings_m');
        $this->load->model('store/store_m');
        $this->load->model('projects/project_task_statuses_m', 'statuses');
        $this->load->model('tickets/ticket_statuses_m', 'ticket_statuses');
        $this->load->model('tickets/ticket_priorities_m', 'ticket_priorities');
        $this->load->model('tax_m');
        $this->load->model('upgrade/update_system_m', 'update');
        $this->load->model('key_m');
        $this->load->model("business_identities_m");

        $this->form_validation->set_rules('language', 'Language', 'trim');

        $this->form_validation->set_error_delimiters('<span class="form_error">', '</span>');

        if ($_POST and IS_DEMO) {
            # Enforce sendmail and the demo license key.
            $_POST['email_server'] = "sendmail";
            $_POST['mailpath'] = "/usr/sbin/sendmail";
            unset($_POST['license_key']);
        }

        if ($this->form_validation->run()) {

            require_once APPPATH . 'modules/gateways/gateway.php';
            if (!Gateway::processSettingsInput($_POST['gateways'])) {
                $this->template->messages = array('error' => lang('gateways:errorupdating'));
            }
            unset($_POST['gateways']);

            $this->business_identities_m->processSettingsInput($_POST['businesses'], isset($_POST['businesses_new']) ? $_POST['businesses_new'] : array(), $_FILES['businesses'], isset($_FILES['businesses_new']) ? $_FILES['businesses_new'] : array());
            unset($_POST['businesses_new'], $_POST['businesses']);

            $email_template_post = $_POST['email_templates'];
            $this->load->model('email_settings_templates');
            $this->email_settings_templates->store($email_template_post);
            unset($_POST['email_templates']);

            $save_email = $this->settings_m->save_email_settings($_POST);

            if ($save_email === 'no_openssl') {
                $this->template->messages = array('error' => lang('settings:noopenssl'));
            } else {
                unset($_POST['email_server']);
                unset($_POST['smtp_host']);
                unset($_POST['smtp_user']);
                unset($_POST['smtp_pass']);
                unset($_POST['smtp_port']);
                unset($_POST['smtp_encryption']);
                unset($_POST['secure_smtp_host']);
                unset($_POST['secure_smtp_user']);
                unset($_POST['secure_smtp_pass']);
                unset($_POST['secure_smtp_port']);
                unset($_POST['tls_smtp_host']);
                unset($_POST['tls_smtp_user']);
                unset($_POST['tls_smtp_pass']);
                unset($_POST['tls_smtp_port']);
                unset($_POST['gapps_user']);
                unset($_POST['gapps_pass']);
                unset($_POST['gmail_user']);
                unset($_POST['gmail_pass']);
                unset($_POST['mailpath']);
            }

            $_POST['ftp_pasv'] = isset($_POST['ftp_pasv']);
            $_POST['bcc'] = isset($_POST['bcc']);
            $_POST['enable_pdf_attachments'] = isset($_POST['enable_pdf_attachments']);
            $_POST['include_remittance_slip'] = isset($_POST['include_remittance_slip']);
            $_POST['autosave_proposals'] = isset($_POST['autosave_proposals']);
            $_POST['hide_tax_column'] = isset($_POST['hide_tax_column']);
            $_POST['include_time_entry_dates'] = isset($_POST['include_time_entry_dates']);
            $_POST['use_utf8_font'] = isset($_POST['use_utf8_font']);
            $_POST['always_https'] = isset($_POST['always_https']);

            $_POST['kitchen_route'] = empty($_POST['kitchen_route']) ? 'client_area' : $_POST['kitchen_route'];
            @file_put_contents(FCPATH . 'uploads/kitchen_route.txt', $_POST['kitchen_route']);

            if (!empty($_POST['ftp_user'])) {
                $ftp_test = $this->update->test_ftp($_POST['ftp_host'], $_POST['ftp_user'], $_POST['ftp_pass'], $_POST['ftp_port'], $_POST['ftp_path'], $_POST['ftp_pasv']);
                if (!$ftp_test) {
                    $this->template->messages = array('error' => $this->update->get_error());
                } else {
                    $_POST['ftp_path'] = (substr($_POST['ftp_path'], strlen($_POST['ftp_path']) - 1, 1) == '/') ? $_POST['ftp_path'] : $_POST['ftp_path'] . '/';
                }
            }

            if (isset($_POST['license_key']) and ! IS_HOSTED and ! IS_DEMO) {
                $_POST['license_key'] = trim($_POST['license_key']);
                if ($_POST['license_key'] != Settings::get('license_key')) {
                    if (get_url_contents(MANAGE_PANCAKE_BASE_URL . 'verify/key/' . $_POST['license_key']) !== 'valid') {
                        $this->template->messages = array('error' => __('settings:wrong_license_key'));
                    }
                }
            }

            if (isset($_POST['default_tax_id'])) {
                if (is_array($_POST['default_tax_id'])) {
                    $_POST['default_tax_id'] = implode(",", $_POST['default_tax_id']);
                }
            } else {
                $_POST['default_tax_id'] = "0";
            }

            // Taxes
            $tax_update = $this->tax_m->update_taxes($_POST['tax_name'], $_POST['tax_value'], $_POST['tax_reg'], (isset($_POST['tax_compound']) ? $_POST['tax_compound'] : array()));
            $tax_insert = TRUE;
            if (isset($_POST['new_tax_name'])) {
                $tax_insert = $this->tax_m->insert_taxes($_POST['new_tax_name'], $_POST['new_tax_value'], $_POST['new_tax_reg'], (isset($_POST['new_tax_compound']) ? $_POST['new_tax_compound'] : array()));
            }

            unset($_POST['tax_name'], $_POST['tax_value'], $_POST['tax_reg'], $_POST['tax_compound'], $_POST['new_tax_name'], $_POST['new_tax_value'], $_POST['new_tax_reg'], $_POST['new_tax_compound']);

            // Currencies

            if ($this->input->post('currency_name') AND $this->input->post('currency_code') AND $this->input->post('currency_rate')) {
                $this->currency_m->update_currencies($_POST['currency_name'], $_POST['currency_code'], $_POST['currency_rate']);
            }
            $currency_insert = TRUE;
            if ($this->input->post('new_currency_name')) {
                $currency_insert = $this->currency_m->insert_currencies($_POST['new_currency_name'], $_POST['new_currency_code'], $_POST['new_currency_rate']);
            }

            unset($_POST['currency_name'], $_POST['currency_code'], $_POST['currency_rate'], $_POST['new_currency_name'], $_POST['new_currency_code'], $_POST['new_currency_rate']);

            // API Keys

            if ($this->input->post('key_key') AND $this->input->post('key_note')) {
                $this->key_m->update_keys($this->input->post('key_key'), $this->input->post('key_note'));
            }
            if ($this->input->post('new_key')) {
                $this->key_m->insert_keys($this->input->post('new_key'), $this->input->post('new_key_note'));
            }

            unset($_POST['key_key'], $_POST['key_note'], $_POST['new_key'], $_POST['new_key_note']);

            // Statuses

            if (!isset($_POST['statuses'])) {
                $_POST['statuses'] = array();
            }

            if (!isset($_POST['new_statuses'])) {
                $_POST['new_statuses'] = array();
            }

            if (count($_POST['statuses']) > 0) {
                foreach ($this->statuses->get_all() as $row) {
                    if (!isset($_POST['statuses'][$row->id])) {
                        $this->statuses->delete($row->id);
                    } else {
                        $this->statuses->update($row->id, $_POST['statuses'][$row->id]);
                    }
                }
            }
            if (count($_POST['new_statuses']) > 0) {
                foreach ($_POST['new_statuses']['title'] as $key => $title) {
                    $this->statuses->insert(array(
                        'title' => $title,
                        'background_color' => $_POST['new_statuses']['background_color'][$key],
                        'font_color' => $_POST['new_statuses']['font_color'][$key],
                        'text_shadow' => $_POST['new_statuses']['text_shadow'][$key],
                        'box_shadow' => $_POST['new_statuses']['box_shadow'][$key],
                    ));
                }
            }

            unset($_POST['new_statuses'], $_POST['statuses']);

            // Ticket Statuses

            if (!isset($_POST['ticket_statuses'])) {
                $_POST['ticket_statuses'] = array();
            }

            if (!isset($_POST['new_ticket_statuses'])) {
                $_POST['new_ticket_statuses'] = array();
            }

            if (count($_POST['ticket_statuses']) > 0) {
                foreach ($this->ticket_statuses->get_all() as $row) {
                    if (!isset($_POST['ticket_statuses'][$row->id])) {
                        $this->ticket_statuses->delete($row->id);
                    } else {
                        $this->ticket_statuses->update($row->id, $_POST['ticket_statuses'][$row->id]);
                    }
                }
            }
            if (count($_POST['new_ticket_statuses']) > 0) {
                foreach ($_POST['new_ticket_statuses']['title'] as $key => $title) {
                    $this->ticket_statuses->insert(array(
                        'title' => $title,
                        'background_color' => $_POST['new_ticket_statuses']['background_color'][$key],
                        'font_color' => $_POST['new_ticket_statuses']['font_color'][$key],
                        'text_shadow' => $_POST['new_ticket_statuses']['text_shadow'][$key],
                        'box_shadow' => $_POST['new_ticket_statuses']['box_shadow'][$key],
                    ));
                }
            }

            unset($_POST['new_ticket_statuses'], $_POST['ticket_statuses']);

            // Ticket Priorities

            if (!isset($_POST['ticket_priorities'])) {
                $_POST['ticket_priorities'] = array();
            }

            if (!isset($_POST['new_ticket_priorities'])) {
                $_POST['new_ticket_priorities'] = array();
            }

            if (count($_POST['ticket_priorities']) > 0) {
                foreach ($this->ticket_priorities->get_all() as $row) {
                    if (!isset($_POST['ticket_priorities'][$row->id])) {
                        $this->ticket_priorities->delete($row->id);
                    } else {
                        $this->ticket_priorities->update($row->id, $_POST['ticket_priorities'][$row->id]);
                    }
                }
            }
            if (count($_POST['new_ticket_priorities']) > 0) {
                foreach ($_POST['new_ticket_priorities']['title'] as $key => $title) {
                    $this->ticket_priorities->insert(array(
                        'title' => $title,
                        'background_color' => $_POST['new_ticket_priorities']['background_color'][$key],
                        'font_color' => $_POST['new_ticket_priorities']['font_color'][$key],
                        'text_shadow' => $_POST['new_ticket_priorities']['text_shadow'][$key],
                        'box_shadow' => $_POST['new_ticket_priorities']['box_shadow'][$key],
                        'default_rate' => $_POST['new_ticket_priorities']['default_rate'][$key]
                    ));
                }
            }

            unset($_POST['new_ticket_priorities'], $_POST['ticket_priorities']);

            if (!isset($this->template->messages['error']) or empty($this->template->messages['error'])) {
                if ($this->settings_m->update_settings($_POST) AND $tax_update AND $tax_insert) {
                    $this->template->messages = array('success' => __("settings:have_been_updated"));
                    redirect("admin/settings");
                } else {
                    $this->template->messages = array('error' => 'There was an error updating your settings.  Please contact support.');
                }
            }

            // Refresh the settings cache
            $this->settings->reload();
        }

        $this->load->model('email_settings_templates');
        $this->template->email_templates = $this->email_settings_templates->get();

        $this->template->latest_version = Settings::get('latest_version');
        $this->template->outdated = ($this->template->latest_version != '0' and $this->template->latest_version != Settings::get('version'));

        $settings = array();
        foreach (Settings::get_all_including_sensitive() as $name => $value) {
            $settings[$name] = set_value($name, $value);
        }

        // Populate currency dropdown
        $currencies = array();
        foreach (Currency::currencies() as $code => $currency) {
            $currencies[$code] = $currency['name'] . ' (' . $code . ')';
        }

        $this->template->import_types = array(
            'invoices' => __('global:invoices'),
            'estimates' => __('global:estimates'),
            'credit_notes' => __('global:credit_notes'),
            'clients' => __('global:clients'),
            'projects' => __('global:projects'),
            'tasks' => __('global:tasks'),
            'time_entries' => __('global:time_entries'),
            'users' => __('global:users'),
        );
        $this->template->export_types = array(
            'invoices_csv' => __('export:invoices_csv'),
            'expenses_csv' => __('export:expenses_csv'),
            'clients_csv' => __('export:clients_csv'),
                #'proposals' => __('global:proposals'),
                #'estimates' => __('global:estimates'),
                #'clients' => __('global:clients'),
                #'projects' => __('global:projects'),
                #'time_entries' => __('global:time_entries'),
                #'users' => __('global:users'),
        );
        $this->template->languages = $this->settings_m->get_languages();

        if (IS_DEMO) {
            # Hide license key in demo.
            $settings['license_key'] = 'demo-license-key';
        }

        $this->template->currencies = $currencies;
        $this->template->settings = $settings;
        $this->template->api_keys = $this->key_m->get_all();
        $this->template->email_inputs = $this->settings_m->inputs;
        $this->template->guessed_ftp_host = parse_url(site_url(), PHP_URL_HOST);

        $this->template->email_servers = array(
            'gmail' => 'Gmail / Google Apps',
            'smtp' => 'SMTP',
            'default' => __("global:server_default"),
        );

        $email = $this->settings_m->interpret_email_settings();

        if ($email['type'] == 'gmail') {
            $email['smtp_host'] = 'smtp.gmail.com';
        }

        $email = array(
            'type' => isset($_POST['email_server']) ? $_POST['email_server'] : $email['type'],
            'smtp_host' => isset($_POST['smtp_host']) ? $_POST['smtp_host'] : $email['smtp_host'],
            'smtp_user' => isset($_POST['smtp_user']) ? $_POST['smtp_user'] : $email['smtp_user'],
            'smtp_pass' => isset($_POST['smtp_pass']) ? $_POST['smtp_pass'] : $email['smtp_pass'],
            'smtp_port' => isset($_POST['smtp_port']) ? $_POST['smtp_port'] : $email['smtp_port'],
            'smtp_encryption' => isset($_POST['smtp_encryption']) ? $_POST['smtp_encryption'] : $email['smtp_encryption'],
            'gmail_user' => isset($_POST['gmail_user']) ? $_POST['gmail_user'] : $email['gmail_user'],
            'gmail_pass' => isset($_POST['gmail_pass']) ? $_POST['gmail_pass'] : $email['gmail_pass'],
        );

        $this->template->email = $email;

        $this->template->temporary_no_internet_access = defined('TEMPORARY_NO_INTERNET_ACCESS');

        $this->template->task_statuses = (array) $this->statuses->get_all();
        $this->template->ticket_statuses = (array) $this->ticket_statuses->get_all();

        $ticket_statuses_dropdown = array(
            '0' => __('settings:never_send_ticket_invoices_automatically'),
        );
        foreach ($this->template->ticket_statuses as $ticket_status) {
            $ticket_statuses_dropdown[$ticket_status->id] = $ticket_status->title;
        }
        $this->template->ticket_statuses_dropdown = $ticket_statuses_dropdown;

        $this->template->ticket_priorities = (array) $this->ticket_priorities->get_all();

        $this->template->outdated_plugins = $this->store_m->get_outdated_details();

        $this->template->businesses = $this->business_identities_m->getAllBusinesses();

        $this->template->error_logs = $this->db->select("id, subject, notification_email, is_reported, error_id, is_reportable, occurrences, first_occurrence", false)->order_by("first_occurrence", "desc")->get("error_logs")->result_array();

        $this->template->build('index');
    }

    function get_changelog() {
        @set_time_limit(0);
        $data = array(
            'update' => $this->update,
            'latest_version' => Settings::get('latest_version'),
            'changelog' => $this->update->get_processed_changelog($this->template->latest_version, null, true),
            'conflicted_files' => $this->update->check_for_conflicts(true)
        );
        $this->load->view('settings/changelog', $data);
    }

    function view_error($error_id) {
        $error = $this->db->where("id", $error_id)->get("error_logs")->row_array();
        echo $error['contents'];
    }

    function delete_error($error_id) {
        if ($this->db->where("id", $error_id)->delete("error_logs")) {
            echo "OK";
        } else {
            echo "NOTOK";
        }
    }

    public function export() {
        if (filter_has_var(INPUT_POST, "export_type")) {
            $this->load->helper("file");
            $export_type = filter_input(INPUT_POST, "export_type", FILTER_SANITIZE_STRING);
            $this->load->model('pie_m', 'pie');
            $contents = $this->pie->export($export_type);
            $filename = $contents['filename'];
            if ($filename) {
                $contents = $contents['contents'];
                $extension = strtolower(substr(strrchr($filename, '.'), 1));
                $extension = $extension ? $extension : "txt";
                $export_type = str_replace(strrchr($export_type, '_'), "", $export_type);

                header('Pragma: public');
                header('Content-type: ' . get_mime_by_extension("file." . $extension));
                header('Content-disposition: attachment;filename=' . $export_type . '.' . $extension);
                echo $contents;
            } else {
                redirect("admin/settings#importexport");
            }
        } else {
            redirect("admin/settings#importexport");
        }
    }

    public function import() {
        $this->load->model('pie_m', 'pie');

        if (isset($_POST['processed_import_data'])) {
            $this->load->model('smart_csv_m');
            $import_type = isset($_POST['import_type']) ? $_POST['import_type'] : 'clients';
            $records = json_decode($_POST['processed_import_data'], true);
            $import = $this->smart_csv_m->import($records, $import_type);
            if ($import) {
                $success = __('settings:imported' . $import_type, array($import['count']));
                if ($import['duplicates'] > 0) {
                    $success .= ' ' . __('settings:xwereduplicates', array($import['duplicates']));
                }
                $this->session->set_flashdata('success', $success);
                redirect('admin/settings');
            } else {
                return $this->_smartcsv();
            }
        }

        if (isset($_FILES['file_to_import']) and $_FILES['file_to_import']['error'][0] != 0) {
            switch ($_FILES['file_to_import']['error'][0]) {
                case 1:
                    # global:upload_ini_size
                    $this->session->set_flashdata('error', __('global:upload_ini_size'));
                    redirect('admin/settings#importexport');
                    break;
                case 4:
                    # settings:nouploadedimportfile
                    $this->session->set_flashdata('error', __('settings:nouploadedimportfile'));
                    redirect('admin/settings#importexport');
                    break;
                default:
                    # global:upload_error
                    $this->session->set_flashdata('error', __('global:upload_error'));
                    redirect('admin/settings#importexport');
                    break;
            }
        } elseif (!isset($_FILES['file_to_import'])) {
            redirect('admin/settings#importexport');
        }

        $import_type = $_POST['import_type'];
        $filename = $_FILES['file_to_import']['tmp_name'][0];
        $ext = pathinfo($_FILES['file_to_import']['name'][0], PATHINFO_EXTENSION);
        $import = $this->pie->import($import_type, $filename, $ext);

        if (!$import) {
            if ($ext == 'csv') {
                return $this->_smartcsv();
            } else {
                $this->template->import_failed = true;
                $this->template->import = true;
                $this->template->build('import_failed');
            }
        } else {
            if ($import) {
                # Everything's perfect.
                $success = array();
                foreach ($import as $type => $details) {
                    $buffer = __('settings:imported' . $type, array($details['count']));
                    if ($details['duplicates'] > 0) {
                        $buffer .= ' ' . __('settings:xwereduplicates', array($details['duplicates']));
                    }
                    $success[] = $buffer;
                }
                $success = implode("<br />", $success);

                $this->session->set_flashdata('success', $success);
                redirect('admin/settings');
            }
        }
    }

    public function load_font() {
        if (isset($_REQUEST['font_family']) and isset($_REQUEST['filename'])) {
            echo "<pre>";
            $this->settings_m->install_font_family($_REQUEST['font_family'], FCPATH . $_REQUEST['filename']);
        } else {
            echo "No ?font_family or filename provided.";
        }
    }

    public function test_email() {
        try {
            
            $return = array();
            $to = array_reset(explode(',', Business::getBillingEmail()));
            $return['to'] = $to;
            
            if (!empty($_POST)) {
                $data = $this->settings_m->convert_input_to_settings($_POST);
                $email_config = $this->settings_m->interpret_email_settings($data);
            } else {
                $email_config = $this->settings_m->interpret_email_settings();
            }
            
            if ($email_config['type'] != "default") {
                # Check that the firewall allows connections to this port:
                $errno = 0;
                $errstr = "";
                $fp = @fsockopen(($email_config['smtp_encryption'] == 'ssl' ? 'ssl://' : '').$email_config['smtp_host'], $email_config['smtp_port'], $errno, $errstr, 5);

                if (!$fp) {
                    $return['success'] = false;
                    $return['error'] = __("settings:test_email_connection_error", array(array_end(explode("://", $email_config['smtp_host'])), $email_config['smtp_port'], "$errstr (Error Number: $errno)"));
                    echo json_encode($return);
                    return;
                }
            }
            
            $result = send_pancake_email_raw($to, __("settings:test_email_subject"), __("settings:test_email_message"), null, array(), '', '', $email_config);
            if ($result) {
                $return['success'] = true;
            } else {
                $return['success'] = false;
                $return['error'] = __("error:subtitle");
            }
        } catch (Exception $e) {
            $return['success'] = false;
            $return['error'] = __("settings:test_email_error", array($e->getMessage()));
        }
        
        echo json_encode($return);
    }

    public function _smartcsv() {
        $this->load->model('smart_csv_m');

        $import_type = isset($_POST['import_type']) ? $_POST['import_type'] : 'clients';

        if (isset($_FILES['file_to_import']['tmp_name'][0])) {
            $filename = $_FILES['file_to_import']['tmp_name'][0];
            $import_data = $this->pie->csv_to_array($filename);
        } else {
            if (isset($_POST['processed_import_data'])) {
                $processed_import_data = json_decode($_POST['processed_import_data'], true);
                $processed_field_data = json_decode($_POST['processed_field_data'], true);
                $import_data = json_decode(base64_decode($_POST['import_data']), true);
            } else {
                redirect('settings#importexport');
            }
        }

        $this->template->required_errors = $this->smart_csv_m->get_required_errors();
        $this->template->invalid_errors = $this->smart_csv_m->get_invalid_errors();
        $this->template->errored = $this->smart_csv_m->errored();
        $this->template->pancake_fields = $this->smart_csv_m->get_fields($import_type);
        $this->template->textareas = $this->smart_csv_m->get_textareas($import_type);
        $this->template->required_fields = $this->smart_csv_m->get_requireds($import_type);
        $this->template->types = $this->smart_csv_m->get_field_types($import_type);
        $this->template->import_data = $import_data;
        $this->template->processed_import_data = isset($processed_import_data) ? $processed_import_data : array();
        $this->template->processed_field_data = isset($processed_field_data) ? $processed_field_data : array();
        $this->template->import_type = $import_type;

        $fields = array();
        foreach (array_keys($this->template->pancake_fields) as $row) {
            $fields[$row] = "";
        }

        $this->template->initial_fields = json_encode($fields);
        $this->template->build('smart_csv');
    }

}

/* End of file: admin.php */