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
 * The frontend controller
 *
 * @subpackage	Controllers
 * @category	Frontend
 */
class Frontend extends Public_Controller {

    /**
     * Routes the request, shows the invoice or payment request,
     * or redirects to the admin if the URI is not found.
     *
     * @access	public
     * @param	string	The method name from the URI
     * @return	void
     */
    public function _remap($method) {
        if (is_callable(array($this, $method))) {
            return call_user_func(array($this, $method));
        }

        if ($method == "third_party") {
            # Trying to access a theme file that does not exist.
            # See if it exists in the default Pancake theme, and if not, continue as usual.

            $string = uri_string();
            $string = str_ireplace("third_party/themes/admin/" . Settings::get('admin_theme') . "/", "third_party/themes/admin/pancake/", $string);
            $string = str_ireplace("third_party/themes/" . Settings::get('admin_theme') . "/", "third_party/themes/pancake/", $string);

            if (file_exists(FCPATH . $string)) {
                redirect($string);
            }
        }

        require_once APPPATH . 'modules/gateways/gateway.php';
        $this->load->helper('typography');
        $this->load->model('invoices/invoice_m');
        $this->load->model('files/files_m');

        $invoice = $this->invoice_m->get($method);

        if (!empty($invoice)) {

            # Switch languages, if necessary.
            switch_language($invoice['language']);
            Business::setBusinessFromClient($invoice['client_id']);

            if (!logged_in() and ! isset($_COOKIE['pancake_is_never_client'])) {
                $this->invoice_m->recordView($invoice['unique_id']);
            }

            $this->template->pdf_mode = FALSE;
            $this->template->invoice = $invoice;

            # If it's an estimate, it's obviously not paid, regardless of what the record says.
            $this->template->is_paid = $invoice['type'] == 'ESTIMATE' ? 0 : $invoice['is_paid'];
            $this->template->type = $invoice['type'];
            $this->template->files = (array) $this->files_m->get_by_unique_id($method);
            $this->template->editable = can('update', $invoice['client_id'], 'estimates_plus_invoices', $invoice['id']);
            $this->template->sendable = can('send', $invoice['client_id'], 'estimates_plus_invoices', $invoice['id']);
            $this->template->is_overdue = (bool) ($invoice > 0 AND $invoice['due_date'] < time());
            $this->template->client_unique_id = $invoice['client_unique_id'];

            $this->template->set_layout('detailed');
            $this->template->build('detailed');
        } else {
            if (is_admin()) {
                redirect('admin');
            } else {
                $this->load->model("users/user_m");
                $this->template->set_partial('notifications', 'partials/notifications');
                $this->user_m->login();
            }
        }
    }

    function frontend_css() {
        header("Content-Type: text/css; charset=utf-8");
        echo Settings::get('frontend_css');
    }

    function frontend_js() {
        header("Content-Type: application/javascript; charset=utf-8");
        echo Settings::get('frontend_js');
    }

    function record_view() {
        # Fake an image.
        header("Content-type: image/png");
        echo base64_decode("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAABnRSTlMA/wBNAADcgN6OAAAADElEQVR4AWP478sAAAObAU3Y5RgbAAAAAElFTkSuQmCC");

        # If email-based view-recording shouldn't be used, don't use it.
        $should_record_email_views = get_instance()->dispatch_return('decide_should_record_email_views', array(), 'boolean');

        if (is_array($should_record_email_views)) {
            # No plugins available.
            $should_record_email_views = true;
        }

        if (!$should_record_email_views) {
            return;
        }

        # Now that that's done, record the view.

        $email = $this->uri->segment(2);
        $unique_id = $this->uri->segment(3);
        $item_type = $this->uri->segment(4);

        if (empty($email) or empty($unique_id)) {
            return;
        }

        $this->load->model('clients/clients_m');
        $this->load->model('invoices/invoice_m');
        $this->load->model('proposals/proposals_m');

        $email = base64_decode($email, true);

        if (empty($item_type)) {
            $item_type = 'invoice_or_estimate';
        }

        switch ($item_type) {
            case 'invoice_or_estimate':
                $invoice = $this->invoice_m->get_by_unique_id($unique_id);
                if (stristr($invoice['email'], $email) !== false) {
                    # It's one of the emails allowed to record this view.
                    $this->invoice_m->recordView($unique_id);
                }
                break;
            case 'proposal':
                $this->proposals_m->recordView($unique_id);
                break;
        }
    }
    
    function send_error_report() {
        $error_id = $this->uri->segment(2);
        $json = array("success" => false, "error" => "");
        $error = $this->db->where("id", $error_id)->get("error_logs")->row_array();
        if (logged_in()) {
            if (isset($error['id'])) {
                $http = new HTTP_Request();
                
                if ($error['occurrences'] == 1) {
                    $occurrences = "once";
                } elseif ($error['occurrences'] == 2) {
                    $occurrences = "twice";
                } else {
                    $occurrences = "{$error['occurrences']} times";
                }

                $contents = $http->request(PANCAKEAPP_COM_BASE_URL."submit_error_details", "POST", array(
                    "license_key" => Settings::get("license_key"),
                    "subject" => $error['subject'],
                    "error_id" => $error['error_id'],
                    "contents" => "This error has occurred $occurrences so far.\n\nIt occurred for the first time on {$error['first_occurrence']}.",
                    "html" => $error['contents'],
                ));
                $contents = json_decode($contents, true);
                if (!$contents) {
                    $json['error'] = __("error:unknown_error_reporting");
                } else {
                    switch ($contents['status']) {
                        case 'created':
                            $json['success'] = true;
                            $json['email'] = $contents['email'];
                            $this->db->where("id", $error_id)->update("error_logs", array(
                                "is_reported" => 1,
                                "notification_email" => $contents['email'],
                            ));
                            break;
                        case 'already_fixed':
                            $json['success'] = true;
                            $json['version'] = $contents['version'];
                            $this->db->where("id", $error_id)->update("error_logs", array(
                                "is_reported" => 1,
                            ));
                            break;
                        case 'already_being_dealt_with':
                            $json['success'] = true;
                            $this->db->where("id", $error_id)->update("error_logs", array(
                                "is_reported" => 1,
                            ));
                            break;
                        case 'no_user_found':
                            $json['error'] = __("error:no_user_found");
                            break;
                        default:
                            $json['error'] = __("error:unknown_error_reporting");
                            break;
                    }
                }
            } else {
                $json['error'] = __("error:unknown_error_reporting");
            }
        } else {
            $json['error'] = __("error:cannot_send_error_details");
        }
        
        echo json_encode($json);
    }

    function get_processed_estimate() {
        $this->load->helper('typography');

        $this->load->model('invoices/invoice_m');
        $this->load->model('files/files_m');
        $estimate_id = $this->uri->segment(2);

        if (logged_in()) {
            $invoice = $this->invoice_m->flexible_get_all(array('id' => $estimate_id, 'include_totals' => true, 'get_single' => true, 'return_object' => false, 'type' => 'estimates'));
            if (!$invoice) {
                return;
            }

            switch_language($invoice['language']);
            Business::setBusinessFromClient($invoice['client_id']);

            $this->template->is_paid = $this->invoice_m->is_paid($invoice['unique_id']);
            $this->template->files = (array) $this->files_m->get_by_unique_id($invoice['unique_id']);
            $this->template->invoice = (array) $invoice;
            $this->template->is_overdue = (bool) ($invoice > 0 AND $invoice['due_date'] < time());
            $this->template->is_estimate = true;
            $this->template->_layout = false;
            $this->template->build('detailed');
        }
    }

    // ------------------------------------------------------------------------

    /**
     * Renders the invoice or payment request into a PDF and forces the
     * download.
     *
     * @access	public
     * @return	void
     */
    public function pdf() {
        $unique_id = $this->uri->segment(2);

        if (empty($unique_id)) {
            redirect('/');
        }

        $return_html = (bool) $this->uri->segment(3);

        if ($return_html) {
            print get_pdf('invoice', $unique_id, true);
            die;
        }

        get_pdf('invoice', $unique_id, false, true);
    }

    public function timesheet() {
        $this->load->model(array('projects/project_m', 'projects/project_task_m', 'projects/project_time_m'));
        $unique_id = $this->uri->segment(2);
        $pdf = $this->uri->segment(3);

        if (empty($unique_id)) {
            redirect('/');
        }

        $project = $this->project_m->getForTimesheet($unique_id);
        switch_language($project['client']['language']);
        Business::setBusinessFromClient($project['client']['id']);

        $project or redirect('/');

        $pdf = ($pdf == 'pdf');
        $this->template->pdf_mode = $pdf;
        $this->template->timesheet_url_pdf = site_url('timesheet/' . $unique_id . '/pdf');
        $this->template->set_layout('timesheet');
        $this->template->total_hours = $project['total_hours'];
        $this->template->tasks = $project['tasks'];
        $this->template->times = $project['times'];
        $this->template->count_users = $project['user_count'];
        $this->template->project = $project['name'];
        $this->template->client = $project['client'];
        $this->template->project_due_date = $project['due_date'];
        $html = $this->template->build('timesheet', array(), $pdf);
        if ($pdf) {
            $pdf_filename = $this->dispatch_return('pdf_filename_generated', array(
                'type' => 'timesheet',
                'phone' => $project['client']['phone'],
                'company' => $project['client']['company'],
            ));

            if (is_array($pdf_filename)) {
                // Plugin is not installed; use old format:
                $pdf_filename = "timesheet.pdf";
            }

            get_pdf_raw($pdf_filename, $html, true);
        }
    }

    public function reports() {
        if (logged_in()) {
            $this->load->model('reports/reports_m');

            $report = $this->uri->segment(2);
            $ext = $this->uri->segment(3);
            $string = $this->uri->segment(4);
            $string = $this->reports_m->processReportString($string);

            require_once APPPATH . 'modules/gateways/gateway.php';
            $gateways = Gateway::get_gateways();
            $this->template->gateways = $gateways;

            $report_contents = $this->reports_m->get_full($report, $string['from'], $string['to'], $string['client']);

            $pdf_filename = $this->dispatch_return('pdf_filename_generated', array(
                'type' => 'report',
                'report_title' => $report
            ));

            if (is_array($pdf_filename)) {
                // Plugin is not installed; use old format:
                $pdf_filename = "report-{$pdf_filename['report_title']}.pdf";
            }


            $this->template->pdf_mode = $ext == 'pdf';
            $this->template->set_layout('report');
            if ($ext == 'csv') {
                $html = $this->template->build('report', $report_contents, true);
                $html = preg_replace("/&(?![A-Za-z0-9])/i", "&amp;", $html);
                $html = preg_replace("/<(?![!\/A-Za-z0-9])/i", "&lt;", $html);
                $matches = array();
                $html = preg_match_all("/<table.*?>(.*?)<\/table>/s", $html, $matches);
                $html = $matches[1][1];
                $html = preg_match_all("/<tr.*?>(.*?)<\/tr>/s", $html, $matches);
                $html = array();
                $doc = new DOMDocument();
                foreach ($matches[0] as $match) {
                    $row = array();
                    $doc->loadHTML('<?xml encoding="UTF-8">' . $match);
                    $tds = $doc->getElementsByTagName('td');
                    $ths = $doc->getElementsByTagName('th');
                    foreach ($ths as $th) {
                        $colspan = $th->getAttribute('colspan');
                        $row[] = $th->nodeValue;
                        if ($colspan > 0) {
                            $colspan--;
                            while ($colspan > 0) {
                                $row[] = "";
                                $colspan--;
                            }
                        }
                    }

                    foreach ($tds as $td) {
                        $as = $td->getElementsByTagName('a');
                        $remove = array();
                        foreach ($as as $a) {
                            if (stristr($a->getAttribute('class'), "hide-pdf") !== false) {
                                $remove[] = $a;
                            }
                        }
                        foreach ($remove as $a) {
                            $td->removeChild($a);
                        }
                        $row[] = $td->nodeValue;
                    }

                    $html[] = $row;
                }

                $filename = tempnam(sys_get_temp_dir(), "pancake-report-csv-");
                $file = fopen($filename, 'w+');
                foreach ($html as $row) {
                    fputcsv($file, $row);
                }
                fclose($file);
                header('Pragma: public');
                header('Content-type: text/csv');
                $pdf_filename = substr($pdf_filename, 0, -4) . ".csv";
                header('Content-disposition: attachment;filename=' . $pdf_filename);
                echo file_get_contents($filename);
                @unlink($filename);
            } elseif ($ext == 'pdf') {
                $html = $this->template->build('report', $report_contents, true);
                get_pdf_raw($pdf_filename, $html, true, null, 'landscape');
            } else {
                $this->template->build('report', $report_contents);
            }
        } else {
            redirect('');
        }
    }

    function download_versions() {
        $this->load->model('upgrade/update_system_m', 'update');
        $segments = $this->uri->total_segments();
        for ($i = 2; $i <= $segments; $i++) {
            $version = $this->uri->segment($i);
            $this->update->remove_version_files($version);
            $this->update->download_version($version);
            echo "VERSION $version DOWNLOADED<br />\n";
        }
        die;
    }

    function wipe_update_files() {
        $this->db->truncate('updates');
        $this->db->truncate('update_files');
        echo "PANCAKE_UPDATES AND PANCAKE_UPDATE_FILES TRUNCATED.<br />\n";
        die;
    }

    function download_version() {
        $version = $this->uri->segment(2);
        $this->load->model('upgrade/update_system_m', 'update');
        $this->update->remove_version_files($version);
        $this->update->download_version($version);
        echo "VERSION $version DOWNLOADED";
        die;
    }

    function check_latest_version() {
        $return = $this->uri->segment(2);
        if (IS_DEMO) {
            Settings::set('auto_update', 1);
        }
        $this->load->model('upgrade/update_system_m', 'update');
        $latest = $this->update->get_latest_version(true);
        if ($return) {
            echo "LATEST VERSION IS $latest.";
        } else {
            redirect('admin');
        }
    }

    public function proposal() {
        require_once APPPATH . 'modules/gateways/gateway.php';
        $pdf = ($this->uri->segment(3) == 'pdf');
        $die = $this->uri->segment(4) != '';
        $unique_id = $this->uri->segment(2);
        !empty($unique_id) or redirect('/');
        $this->load->model('proposals/proposals_m');

        if (!$pdf) {

            $this->load->helper('typography');
            $this->load->model('invoices/invoice_m');
            $this->load->model('files/files_m');
            $this->load->model('clients/clients_m');
            $proposal = (array) $this->proposals_m->getByUniqueId($unique_id, $pdf);
            $proposal or redirect('/');
            $proposal['client'] = (array) $proposal['client'];
            switch_language($proposal['client']['language']);
            Business::setBusinessFromClient($proposal['client']['id']);

            if (!logged_in() and ! isset($_COOKIE['pancake_is_never_client'])) {
                $this->proposals_m->recordView($unique_id);
            }

            $this->template->new = (bool) $proposal;
            $result = $this->db->get('clients')->result_array();
            $clients = array();
            foreach ($result as $row) {
                $row['title'] = $row['first_name'] . ' ' . $row['last_name'] . ($row['company'] ? ' - ' . $row['company'] : '');
                $clients[] = $row;
            }
            $this->template->clients = $clients;

            $this->template->proposal = $proposal;
            $this->template->pdf_mode = $pdf;
            $this->template->set_layout('proposal');

            $this->template->build('proposal');
        } else {
            if ($die) {
                print get_pdf('proposal', $unique_id, true);
                die;
            }

            if (!logged_in() and ! isset($_COOKIE['pancake_is_never_client'])) {
                $this->proposals_m->recordView($unique_id);
            }

            get_pdf('proposal', $unique_id, false, true);
        }
    }

}

/* End of file frontend.php */
