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
 * The payments model
 *
 * @subpackage	Models
 * @category	Payments
 */
class Invoice_m extends Pancake_Model {

    /**
     * @var	string	The payments table name
     */
    protected $table = 'invoices';

    /**
     * @var string	The table that contains the invoice rows
     */
    protected $rows_table = 'invoice_rows';

    /**
     * @var	array	The array of validation rules
     */
    protected $validate = array(
        'client_id' => array(
            'field' => 'client_id',
            'label' => 'Client',
            'rules' => 'required'
        ),
        'type' => array(
            'field' => 'type',
            'label' => 'Type',
            'rules' => 'required'
        ),
        array(
            'field' => 'is_recurring',
            'label' => 'Is recurring',
            'rules' => 'numeric'
        ),
        array(
            'field' => 'currency',
            'label' => 'Currency',
            'rules' => ''
        ),
        array(
            'field' => 'due_date',
            'label' => 'Due Date',
            'rules' => ''
        ),
        array(
            'field' => 'frequency',
            'label' => 'Frequency',
            'rules' => 'max_length[2]'
        ),
        array(
            'field' => 'auto_send',
            'label' => 'Auto Send',
            'rules' => ''
        )
    );

    function count($client_id, $is_paid = false, $since = null) {
        static $cache = null;
        if ($cache === null) {
            where_assigned('estimates_plus_invoices', 'read');
            $this->db->select('is_paid, type, client_id, IF(is_paid, payment_date, date_entered) as accounting_date', false);
            $this->db->where("type", "DETAILED");
            $cache = $this->db->get($this->table)->result_array();
        }

        $count = 0;
        foreach ($cache as $row) {
            if ($client_id !== null and $row['client_id'] != $client_id) {
                continue;
            }

            if ($is_paid and ! $row['is_paid']) {
                continue;
            }

            if (!$is_paid and $row['is_paid']) {
                continue;
            }

            if ($since !== null and $row['accounting_date'] <= $since) {
                continue;
            }

            $count++;
        }

        return $count;
    }

    function countEstimates($client_id, $status = null, $sent = null) {
        where_assigned('estimates', 'read');

        if ($client_id > 0) {
            $this->db->where('client_id', $client_id);
        }

        $this->db->where('type', 'ESTIMATE');

        if ($status !== null) {
            $this->db->where('status', $status);
        }

        if ($sent !== null) {
            if ($sent) {
                $this->db->where('last_sent >', 0);
            } else {
                $this->db->where('last_sent', 0);
            }
        }

        return $this->db->count_all_results($this->table);
    }

    function count_credit_notes($client_id) {
        if ($client_id > 0) {
            $this->db->where('client_id', $client_id);
        }

        $this->db->where('type', 'CREDIT_NOTE');

        return $this->db->count_all_results($this->table);
    }

    function fixNoPartialPayments() {
        $CI = &get_instance();
        $CI->load->model('invoices/partial_payments_m', 'ppm');

        $invoices = $this->flexible_get_all(array('type' => 'invoices', 'include_totals' => true, 'return_object' => false, 'include_partials' => true));
        foreach ($invoices as $invoice) {
            if ($invoice['part_count'] == 0) {
                $CI->ppm->setPartialPayment($invoice['unique_id'], 1, 100, 1, (($invoice['due_date'] > 0) ? $invoice['due_date'] : 0), '');
            }
        }
    }

    function improvePartialPayments() {
        $CI = &get_instance();
        $CI->load->model('invoices/partial_payments_m', 'ppm');

        $this->load->model('settings/pie_m', 'pie');
        $contents = $this->pie->export('invoices');

        $invoices = $this->flexible_get_all(array('type' => 'invoices', 'include_totals' => true, 'return_object' => false, 'include_partials' => true));

        foreach ($invoices as $invoice) {
            if ($invoice['tax_total'] > 0) {
                foreach ($invoice['partial_payments'] as $key => $row) {
                    # If a partial payment is not a percentage, it needs tax added onto it.
                    if (!$row['improved'] and ! $row['is_percentage']) {
                        $moneyAmount = ($row['is_percentage']) ? ( ($row['amount'] / 100) * $invoice['amount'] ) : $row['amount'];
                        $percentageAmount = ($row['is_percentage']) ? ($row['amount'] / 100) : ($row['amount'] / $invoice['amount']);
                        $taxAmount = $invoice['tax_total'] * $percentageAmount;
                        $new_amount = round($moneyAmount + $taxAmount, 2);
                        $CI->ppm->updatePartialPayment($row['unique_id'], array('amount' => $new_amount, 'improved' => 1));
                    }
                }
            }
        }
    }

    function getProposalAmount($proposal_id) {
        $buffer = $this->db->select('amount, exchange_rate')->get_where($this->table, array('proposal_id' => $proposal_id))->result_array();
        $amount = 0;

        # We need to calculate the amount in the default currency, so the totals will add up properly!
        foreach ($buffer as $row) {
            $amount = $amount + ($row['amount'] / $row['exchange_rate']);
        }
        return $amount;
    }

    function convertEstimateToInvoiceByUniqueId($unique_id, $estimate_record = array()) {
        $CI = &get_instance();
        $CI->load->model('invoices/partial_payments_m', 'ppm');

        if (count($estimate_record) == 0) {
            $invoice = $this->db->get_where($this->table, array('unique_id' => $unique_id))->row_array();
        } else {
            $invoice = $estimate_record;
        }

        if ($invoice['type'] == 'ESTIMATE') {
            # First, we need to change the type and number (because we're switching from the estimate numeric system to the invoice numeric system):
            $invoice_number = $this->_generate_invoice_number(); // WARNING: Putting this in the array ruins the where().

            $default_due_date = Settings::get('default_invoice_due_date');
            $default_due_date = $default_due_date === '' ? 0 : strtotime('+' . $default_due_date . ' days');

            $this->db->where('id', $invoice['id'])->update($this->table, array(
                'type' => 'DETAILED',
                'invoice_number' => $invoice_number,
                'due_date' => $default_due_date,
                'last_sent' => 0,
                'payment_date' => 0,
                'is_paid' => 0,
                'is_viewable' => 0, # Reset is_viewable because this is a draft invoice.
                'proposal_id' => 0,
                'last_viewed' => 0,
                'has_sent_notification' => 0
            ));
            # Now we need to give it a partial payment.
            $CI->ppm->setPartialPayment($invoice['unique_id'], 1, 100, 1, $default_due_date, '');
            # Now we're going to let Pancake fix the invoice record. This should always be done when you're messing with partial payments.
            $this->fixInvoiceRecord($invoice['unique_id']);
            # Done! Estimate converted to invoice.
        }

        return $invoice['invoice_number'];
    }

    function fix_all_invoices() {
        $this->load->model('invoices/partial_payments_m', 'ppm');
        $invoices = $this->db->select('unique_id')->get('invoices')->result_array();
        foreach ($invoices as $invoice) {
            $this->invoice_m->fixInvoiceRecord($invoice['unique_id']);
        }
    }

    function convertInvoiceToEstimateByUniqueId($unique_id, $invoice_record = array()) {
        $CI = &get_instance();
        $CI->load->model('invoices/partial_payments_m', 'ppm');

        if (count($invoice_record) == 0) {
            $invoice = $this->db->get_where($this->table, array('unique_id' => $unique_id))->row_array();
        } else {
            $invoice = $invoice_record;
        }

        if ($invoice['type'] != 'ESTIMATE') {
            # First, we need to change the type and number (because we're switching from the invoice numeric system to the estimate numeric system):
            $estimate_number = $this->_generate_invoice_number(null, "ESTIMATE"); // WARNING: Putting this in the array ruins the where().
            $this->db->where('id', $invoice['id'])->update($this->table, array('type' => 'ESTIMATE', 'invoice_number' => $estimate_number, 'is_recurring' => 0, 'recur_id' => 0));
            # Now we need to delete all of its partial payments.
            $CI->ppm->removePartialPayments($invoice['unique_id']);
            # Now we're going to let Pancake fix the estimate record. This should always be done when you're messing with partial payments.
            $this->fixInvoiceRecord($invoice['unique_id']);
            # Done! Invoice converted to estimate.
        }

        return $invoice['invoice_number'];
    }

    function convertEstimateToProjectByUniqueId($unique_id) {

        $CI = &get_instance();
        $CI->load->model('projects/project_m');
        $CI->load->model('projects/project_task_m');
        $invoice = $this->db->get_where($this->table, array('unique_id' => $unique_id))->row_array();
        $invoice_rows = $this->db->get_where('invoice_rows', array('unique_id' => $unique_id))->result_array();
        $total_hours = 0;
        foreach ($invoice_rows as $row) {
            $total_hours = $total_hours + $row['qty'];
        }

        if ($invoice['type'] == 'ESTIMATE') {

            if ($invoice['project_id'] > 0) {
                # Estimate is already associated with a project, just use that to add the tasks.
                $project_id = $invoice['project_id'];
            } else {
                # First, we need to create the project:
                $project_id = $CI->project_m->insert(array(
                    'client_id' => $invoice['client_id'],
                    'name' => __('projects:new_project_from_estimate_with' . (empty($invoice['invoice_number']) ? 'out' : '') . '_number', array($invoice['invoice_number'])),
                    'description' => $invoice['description'],
                    'projected_hours' => $total_hours,
                    'currency_id' => $invoice['currency_id'],
                    'exchange_rate' => $invoice['exchange_rate'],
                    'is_viewable' => $invoice['is_viewable'],
                ));
            }

            # Then, we create the tasks:
            foreach ($invoice_rows as $row) {
                $data = array(
                    'project_id' => $project_id,
                    'name' => $row['name'],
                    'due_date' => 0,
                    'notes' => $row['description'],
                    'parent_task_id' => 0,
                    'rate' => $row['rate'],
                    'milestone_id' => 0,
                    'projected_hours' => $row['qty']
                );

                if ($invoice['is_viewable'] > 0) {
                    $data['is_viewable'] = 1;
                }

                $this->project_task_m->insert_task($data);
            }
        }

        return $invoice['invoice_number'];
    }

    /**
     * When a proposal is deleted, all its estimates' proposal_ids must be reset to 0.
     * That's what this function does, and that's where it's used.
     * @param int $proposal_id
     * @return boolean
     */
    function resetProposalEstimates($proposal_id) {
        return $this->db->where('proposal_id', $proposal_id)->update($this->table, array('proposal_id' => 0));
    }

    function acceptEstimate($estimate_unique_id) {
        if (!logged_in()) {
            $estimate = $this->db->select('id, client_id')->where('unique_id', $estimate_unique_id)->get('invoices')->row_array();
            get_instance()->load->model('notifications/notification_m');
            Notify::client_accepted_estimate($estimate['id'], $estimate['client_id']);
        }
        $this->convertEstimateToProjectByUniqueId($estimate_unique_id);
        return $this->db->where('unique_id', $estimate_unique_id)->update($this->table, array('status' => 'ACCEPTED', 'last_status_change' => time()));
    }

    function rejectEstimate($estimate_unique_id) {
        if (!logged_in()) {
            $estimate = $this->db->select('id, client_id')->where('unique_id', $estimate_unique_id)->get('invoices')->row_array();
            get_instance()->load->model('notifications/notification_m');
            Notify::client_rejected_estimate($estimate['id'], $estimate['client_id']);
        }
        return $this->db->where('unique_id', $estimate_unique_id)->update($this->table, array('status' => 'REJECTED', 'last_status_change' => time()));
    }

    function unanswerEstimate($estimate_unique_id) {
        return $this->db->where('unique_id', $estimate_unique_id)->update($this->table, array('status' => '', 'last_status_change' => time()));
    }

    function _actionProposalEstimates($action, $proposal_id) {
        $estimates = $this->db->select('unique_id, id, type')->get_where($this->table, array('proposal_id' => $proposal_id))->result_array();
        $method = $action . 'Estimate';

        foreach ($estimates as $invoice) {
            $this->$method($invoice['unique_id']);
        }
    }

    function acceptProposalEstimates($proposal_id) {
        return $this->_actionProposalEstimates('accept', $proposal_id);
    }

    function rejectProposalEstimates($proposal_id) {
        return $this->_actionProposalEstimates('reject', $proposal_id);
    }

    function unanswerProposalEstimates($proposal_id) {
        return $this->_actionProposalEstimates('unanswer', $proposal_id);
    }

    function convertProposalInvoicesIntoEstimates($proposal_id) {

        $CI = &get_instance();
        $CI->load->model('invoices/partial_payments_m', 'ppm');

        $invoices = $this->db->select('id, unique_id, type')->get_where($this->table, array('proposal_id' => $proposal_id))->result_array();

        foreach ($invoices as $invoice) {
            if ($invoice['type'] == 'ESTIMATE') {
                # This has already been turned into an estimate, let's not touch it.
                continue;
            } else {
                $this->db->where('id', $invoice['id'])->update($this->table, array('type' => 'ESTIMATE'));
                $CI->ppm->removePartialPayments($invoice['unique_id']);
                $this->fixInvoiceRecord($invoice['unique_id']);
            }
        }
    }

    function find_invoice($invoice_number, $amount, $client_id) {
        $invoices = $this->flexible_get_all(array('invoice_number' => $invoice_number, 'include_totals' => true, 'return_object' => false));

        if (count($invoices) > 0) {
            foreach ($invoices as $record) {
                if ($record['amount'] == $amount and $client_id == $record['client_id']) {
                    return 'EXISTS';
                }
            }

            return 'DUPLICATE_INVOICE_NUMBER';
        } else {
            return false;
        }
    }

    /**
     * Set the invoice as paid if all its parts are paid.
     * Also changes the due date to the latest due date of the partial payments.
     *
     * Called when creating or editing an invoice, and on IPN.
     *
     * @param string $unique_id
     * @return boolean
     */
    function fixInvoiceRecord($unique_id) {

        $invoice = $this->db->where("unique_id", $unique_id)->get($this->table)->row_array();

        $CI = &get_instance();
        $CI->load->model('invoices/partial_payments_m', 'ppm');
        $parts = $CI->ppm->getInvoicePartialPayments($unique_id, false, true);

        $is_paid = 1;
        $due_date = ($invoice['due_date'] <= 0) ? 0 : $invoice['due_date'];
        $last_part_payment_date = 0;

        foreach ($parts as $part) {
            if (!$part['is_paid']) {
                $is_paid = 0;
            } else {
                
            }

            if ($part['due_date'] > $due_date) {
                $due_date = $part['due_date'];
            }

            if ($part['payment_date'] > $last_part_payment_date) {
                $last_part_payment_date = $part['payment_date'];
            }
        }

        $data = array();

        if (!$is_paid) {
            $data = array(
                'is_paid' => 0,
                'payment_date' => 0
            );
        } else {
            $data = array(
                'is_paid' => $is_paid,
                'payment_date' => $is_paid ? $last_part_payment_date : 0
            );
        }

        if ($invoice['due_date'] != $due_date) {
            $data['due_date'] = $due_date;
        }

        $total_without_tax = 0;
        $rows = $this->rows_with_tax_total(array($invoice['unique_id']));
        foreach ($rows[$invoice['unique_id']] as $row) {
            $total_without_tax += $row['total_pre_tax_post_discounts'];
        }

        $data['amount'] = $total_without_tax;

        if ($data != array()) {
            return $this->db->where('unique_id', $unique_id)->update($this->table, $data);
        } else {
            return true;
        }
    }

    function recordView($unique_id) {
        get_instance()->load->model('notifications/notification_m');

        $invoice = $this->db->select('id, client_id, type')->where('unique_id', $unique_id)->get('invoices')->row_array();

        // Save it to the notification table
        if ($invoice['type'] == 'ESTIMATE') {
            Notify::client_viewed_estimate($invoice['id'], $invoice['client_id']);
        } elseif ($invoice['type'] == 'CREDIT_NOTE') {
            Notify::client_viewed_credit_note($invoice['id'], $invoice['client_id']);
        } else {
            Notify::client_viewed_invoice($invoice['id'], $invoice['client_id']);
        }

        return $this->db->where('unique_id', $unique_id)->update($this->table, array('last_viewed' => time()));
    }

    public function send_payment_receipt_emails($partial_payment_unique_id, $gateway, $data) {
        $CI = &get_instance();
        $CI->load->model('invoices/invoice_m');
        $CI->load->model('clients/clients_m');
        $CI->load->model('invoices/partial_payments_m', 'ppm');
        $CI->load->model('files/files_m');
        $CI->load->model('tickets/ticket_m');

        $unique_id = $partial_payment_unique_id;

        $part = $CI->ppm->getPartialPayment($unique_id);
        $invoice = $part['invoice'];

        $data['payment_method'] = $gateway;

        $files = $CI->files_m->get_by_unique_id($unique_id);
        $files = empty($files) ? array() : array(1);

        $data['first_name'] = $invoice['first_name'];
        $data['last_name'] = $invoice['last_name'];

        $currency_code = Currency::code($invoice['currency_id']);

        $currency = array(
            'code' => $currency_code,
            'symbol' => Currency::symbol($currency_code)
        );

        if (isset($data['gateway_surcharge']) and $data['gateway_surcharge'] > 0) {
            $data['payment_gross'] .= ' including a ' . $currency['code'] . ' ' . $data['gateway_surcharge'] . ' surcharge';
        }

        $invoice['number'] = $invoice['invoice_number'];

        $invoice['total'] = number_format($invoice['total'], 2);
        $data['payment_gross'] = number_format($data['payment_gross'], 2);

        $parser_array = array(
            'invoice' => $invoice,
            'number' => $invoice['invoice_number'],
            'files' => $files,
            'ipn' => $data,
            'currency' => $currency,
        );

        send_pancake_email(array(
            'to' => $invoice['email'],
            'template' => 'invoice_payment_notification_for_client',
            'client_id' => $invoice['client_id'],
            'data' => $parser_array,
        ));

        send_pancake_email(array(
            'to' => Business::getNotifyEmail(),
            'template' => 'invoice_payment_notification_for_admin',
            'client_id' => $invoice['client_id'],
            'data' => $parser_array,
        ));

        return true;
    }

    public function sendNotificationEmail($unique_id, $message = NULL, $subject = null, $emails = null) {
        $this->load->model('clients/clients_m');
        $this->load->model('files/files_m');

        $invoice = (array) $this->get_by_unique_id($unique_id);
        $invoice['url'] = site_url($unique_id);
        $invoice['total'] = number_format($invoice['total'], 2);

        $files = $this->files_m->get_by_unique_id($unique_id);
        $files = empty($files) ? array() : $files;

        $invoice['number'] = $invoice['invoice_number'];

        $parser_array = array(
            'invoice' => $invoice,
            'number' => $invoice['invoice_number'],
            'description' => $invoice['description'],
            'estimate' => $invoice,
            'files' => $files,
        );

        if (Settings::get('enable_pdf_attachments') == 0 or $invoice['type'] == 'SIMPLE') {
            $pdf = array();
        } else {

            $pdf_filename = $this->dispatch_return('pdf_filename_generated', array(
                'site_name' => preg_replace('/[^A-Za-z0-9-]/', '', str_ireplace(' ', '-', strtolower(Business::getBrandName()))),
                'number' => $invoice['invoice_number'],
                'type' => $invoice['type'] == 'ESTIMATE' ? 'estimate' : 'invoice',
                'phone' => $invoice['phone'],
                'company' => $invoice['company'],
                'date_of_creation' => $invoice['date_entered'],
            ));

            if (is_array($pdf_filename)) {
                // Plugin is not installed; use old format:
                $pdf_filename = "{$pdf_filename['site_name']}-{$pdf_filename['type']}-{$pdf_filename['number']}.pdf";
            }

            $pdf = get_pdf('invoice', $unique_id);

            $pdf = array(
                $pdf['filename'] => $pdf['contents']
            );
        }

        $to = $emails ? $emails : $invoice['email'];
        $template = 'new_' . (singular(human_invoice_type($invoice['type'])));

        $result = send_pancake_email(array(
            'to' => $to,
            'template' => $template,
            'client_id' => $invoice['client_id'],
            'data' => $parser_array,
            'attachments' => $pdf,
            'subject' => $subject,
            'message' => $message,
            'unique_id' => $invoice['unique_id'],
            'item_type' => 'invoice_or_estimate'
        ));

        if ($result) {
            $this->update_simple($unique_id, array('last_sent' => time(), 'has_sent_notification' => 1, 'is_viewable' => 1));
            return true;
        } else {
            return false;
        }
    }

    public function count_recurring($client_id = null) {
        where_assigned('invoices', 'read');
        $this->db->where('type', 'DETAILED');
        $this->db->where('is_recurring', 1);
        $this->db->where('id', 'recur_id', false);
        if ($client_id > 0) {
            $this->db->where('client_id', $client_id);
        }

        return $this->db->count_all_results('invoices');
    }

    public function count_unsent($client_id = null) {
        where_assigned('invoices', 'read');
        $this->db->where('type', 'DETAILED');
        $this->db->where('is_paid', 0);
        $this->db->where('last_sent', 0);
        if ($client_id > 0) {
            $this->db->where('client_id', $client_id);
        }

        return $this->db->count_all_results('invoices');
    }

    public function count_unsent_recurrences($client_id = null) {
        where_assigned('invoices', 'read');
        $this->db->where('type', 'DETAILED');
        $this->db->where("recur_id !=", 0);
        $this->db->where('is_paid', 0);
        $this->db->where('last_sent', 0);
        if ($client_id > 0) {
            $this->db->where('client_id', $client_id);
        }

        return $this->db->count_all_results('invoices');
    }

    public function count_unsent_not_recurrences($client_id = null) {
        where_assigned('invoices', 'read');
        $this->db->where('type', 'DETAILED');
        $this->db->where("recur_id", 0);
        $this->db->where('is_paid', 0);
        $this->db->where('last_sent', 0);
        if ($client_id > 0) {
            $this->db->where('client_id', $client_id);
        }

        return $this->db->count_all_results('invoices');
    }

    function count_sent_but_unpaid($client_id = null) {
        where_assigned('invoices', 'read');
        $this->db->where('type', 'DETAILED');
        $this->db->where('is_paid', 0);
        $this->db->where('last_sent !=', 0);
        if ($client_id > 0) {
            $this->db->where('client_id', $client_id);
        }

        return $this->db->count_all_results('invoices');
    }

    // ------------------------------------------------------------------------

    /**
     * Gets the total number of paid invoices and total of those invoices
     *
     * @access	public
     * @param	int		The client id to filter it by
     * @return	array 	An array containing count and total
     */
    public function paid_totals($client_id = NULL, $since = null, $is_viewable = null) {
        $CI = &get_instance();
        $CI->load->model('invoices/partial_payments_m', 'ppm');
        return $CI->ppm->getTotals($client_id, true, $since, $is_viewable);
    }

    // ------------------------------------------------------------------------

    /**
     * Gets the total number of overdue invoices and total of those invoices
     *
     * @access	public
     * @param	int		The client id to filter it by
     * @return	array 	An array containing count and total
     */
    public function overdue_totals($client_id = NULL, $since = null) {
        $CI = &get_instance();
        $CI->load->model('invoices/partial_payments_m', 'ppm');
        return $CI->ppm->getTotals($client_id, 'OVERDUE', $since);
    }

    // ------------------------------------------------------------------------

    /**
     * Gets the total number of unpaid invoices and total of those invoices
     *
     * @access	public
     * @param	int		The client id to filter it by
     * @return	array 	An array containing count and total
     */
    public function unpaid_totals($client_id = NULL, $since = null, $is_viewable = null) {
        $CI = &get_instance();
        $CI->load->model('invoices/partial_payments_m', 'ppm');
        return $CI->ppm->getTotals($client_id, false, $since, $is_viewable);
    }

    /**
     * Gets the total number of sent but unpaid invoices and total of those invoices
     *
     * @access	public
     * @param	int		The client id to filter it by
     * @return	array 	An array containing count and total
     */
    public function sent_but_unpaid_totals($client_id = NULL, $since = null) {
        $CI = &get_instance();
        $CI->load->model('invoices/partial_payments_m', 'ppm');
        return $CI->ppm->getTotals($client_id, 'SENT_BUT_UNPAID', $since);
    }

    // ------------------------------------------------------------------------

    /**
     * Checks if the invoice is paid or not.
     *
     * @access	public
     * @param	string	The unique id of the invoice
     * @return	bool	If the invoice is paid
     */
    public function is_paid($unique_id) {
        $result = $this->db->select("unique_id")
                        ->where(array('invoices.is_paid' => 1, 'unique_id' => $unique_id))
                        ->get($this->table)->result();

        if (empty($result)) {
            return FALSE;
        }

        return TRUE;
    }

    function getRowIdsByUniqueId($unique_id) {
        $rows = $this->db->select('id')->where(array('unique_id' => $unique_id))->get($this->rows_table)->result_array();
        $row_ids = array();
        foreach ($rows as $row) {
            $row_ids[] = $row['id'];
        }
        return $row_ids;
    }

    function getIdByUniqueId($unique_id) {
        $buffer = $this->db->select('id')->where('unique_id', $unique_id)->get($this->table)->row_array();
        return (int) (isset($buffer['id']) ? $buffer['id'] : 0);
    }

    function getClientIdById($id) {
        $buffer = $this->db->select('client_id')->where('id', $id)->get($this->table)->row_array();
        return (int) (isset($buffer['client_id']) ? $buffer['client_id'] : 0);
    }

    function getByRowId($invoice_row_id) {
        $buffer = $this->db->select('unique_id')->where('id', $invoice_row_id)->get('invoice_rows')->row_array();
        if (isset($buffer['unique_id'])) {
            return $this->flexible_get_all(array('return_object' => false, 'get_single' => true, 'include_totals' => true, 'unique_id' => $buffer['unique_id']));
        } else {
            return null;
        }
    }

    function getClientIdByUniqueId($unique_id) {
        $buffer = $this->db->select('client_id')->where('unique_id', $unique_id)->get($this->table)->row_array();
        return (int) (isset($buffer['client_id']) ? $buffer['client_id'] : 0);
    }

    function setProposalIdById($id, $proposal_id) {
        return $this->db->where('id', $id)->update($this->table, array('proposal_id' => $proposal_id));
    }

    function getUniqueIdById($id) {
        $buffer = $this->db->select('unique_id')->where('id', $id)->get($this->table)->row_array();
        return isset($buffer['unique_id']) ? $buffer['unique_id'] : NULL;
    }

    function getPermissionsItemTypeByUniqueId($unique_id) {
        $buffer = $this->db->select('type')->where('unique_id', $unique_id)->get($this->table)->row_array();
        if (!isset($buffer['type'])) {
            $buffer['type'] = 'DETAILED';
        }

        return $buffer['type'] == 'ESTIMATE' ? 'estimates' : 'invoices';
    }

    function getInvoiceNumberById($id) {
        $buffer = $this->db->select('invoice_number')->where('id', $id)->get($this->table)->row_array();
        return $buffer['invoice_number'];
    }

    function getIdByInvoiceNumber($number) {
        $buffer = $this->db->select('id')->where('invoice_number', $number)->get($this->table)->row_array();
        return (int) $buffer['id'];
    }

    function getIsRecurringByUniqueId($unique_id) {
        $buffer = $this->db->select('is_recurring')->where('unique_id', $unique_id)->get($this->table)->row_array();
        return (int) $buffer['is_recurring'];
    }

    function getEstimatesForDropdown($client_id) {
        $config = array(
            'client_id' => $client_id,
            'type' => 'estimates',
            'return_object' => false
        );
        $buffer = $this->flexible_get_all($config);
        $return = array();

        foreach ($buffer as $row) {
            if ($row['proposal_id'] == 0) {
                $company = empty($row['company']) ? '' : ' - ' . $row['company'];
                $return[$row['id']] = __('proposals:estimate_number_and_amount', array($row['invoice_number'], Currency::format($row['billable_amount'], $row['currency_symbol'])));
            }
        }

        return $return;
    }

    // ------------------------------------------------------------------------

    /**
     * Retrieves an invoice
     *
     * @access	public
     * @param	string	The unique id of the invoice
     * @return	array 	The payment array
     */
    public function get($unique_id, $field = "unique_id") {
        return $this->flexible_get_all(array($field => $unique_id, 'include_receipts' => true, 'include_totals' => true, 'return_object' => false, 'get_single' => true, 'include_partials' => true, 'type' => 'all'));
    }

    // ------------------------------------------------------------------------

    /**
     * Gets all the invoices from the past 30 days
     *
     * @access	public
     * @param	int		The client id to filter it by
     * @return	object 	An object containing the invoices
     */
    public function past_30_days($client_id = NULL) {
        return $this->flexible_get_all(array(
                    'past_x_days' => 30,
                    'client_id' => $client_id,
                    'include_totals' => true,
        ));
    }

    // ------------------------------------------------------------------------

    /**
     * Gets all the invoices
     *
     * @access	public
     * @param	int		The client id to filter it by
     * @return	object 	An object containing the invoices
     */
    public function get_all_for_api() {
        $this->db
                ->select("invoices.*, IF(date_entered > 0, FROM_UNIXTIME(date_entered), NULL) as date_entered,
			IF(due_date > 0, FROM_UNIXTIME(due_date), NULL) as due_date,
			IF(payment_date > 0, FROM_UNIXTIME(payment_date), NULL) as payment_date,
			clients.first_name, clients.last_name, clients.email, clients.company, clients.phone, currencies.code as currency_code", FALSE)
                ->from($this->table)
                ->join('clients', 'invoices.client_id = clients.id', 'left')
                ->join('currencies', 'invoices.currency_id = currencies.id', 'left');

        $results = $this->db->get()->result();

        foreach ($results as &$row) {
            $row->paid = FALSE;
            $row->overdue = FALSE;

            if ($row->is_paid == 1) {
                $row->paid = TRUE;
            } elseif ($row->due_date < time()) {
                $row->overdue = TRUE;
            }

            $row->id = (int) $row->id;
            $row->client_id = (int) $row->client_id;
            $row->is_paid = (bool) $row->is_paid;
            $row->is_recurring = (bool) $row->is_recurring;
            $row->auto_send = (bool) $row->auto_send;
            $row->auto_send = (bool) $row->auto_send;
            $row->exchange_rate = (float) $row->exchange_rate;
        }

        return $results;
    }

    // ------------------------------------------------------------------------

    /**
     * Gets all the invoices
     *
     * @access	public
     * @param	int		The client id to filter it by
     * @return	object 	An object containing the invoices
     */
    public function get_all($client_id = NULL) {
        $this->db
                ->select("invoices.*, clients.first_name, clients.last_name, clients.email, clients.company, clients.phone, currencies.code as currency_code")
                ->from($this->table)
                ->join('clients', 'invoices.client_id = clients.id', 'left')
                ->join('currencies', 'invoices.currency_id = currencies.id', 'left');

        if ($client_id !== NULL) {
            $this->db->where('invoices.client_id', $client_id);
        }

        $this->db->where('invoices.type', 'DETAILED');

        $results = $this->db->get()->result();

        foreach ($results as & $row) {
            $row->paid = FALSE;
            $row->overdue = FALSE;

            if ($row->is_paid == 1) {
                $row->paid = TRUE;
            } elseif ($row->due_date < time()) {
                $row->overdue = TRUE;
            }
        }

        return $results;
    }

    // ------------------------------------------------------------------------

    /**
     * Gets all the viewable invoices
     *
     * @access	public
     * @param	int		The client id to filter it by
     * @param 	bool 	Whether the invoices should be viewable or not
     * @return	object 	An object containing the invoices
     */
    public function get_all_viewable($client_id = NULL, $is_viewable = TRUE, $type = 'invoices') {
        $data = array('client_id' => $client_id, 'viewable' => true, 'include_totals' => true, 'order' => array('due_date' => 'DESC', 'date_entered' => 'DESC'));
        $data['type'] = $type;

        return $this->flexible_get_all($data);
    }

    // ------------------------------------------------------------------------

    /**
     * Gets by Unique ID.
     *
     * @access	public
     * @param	int		The unique id
     * @return	array 	An array containing the invoice
     */
    public function get_by_unique_id($unique_id) {
        if ($unique_id !== null) {
            return $this->flexible_get_all(array('type' => 'all', 'unique_id' => $unique_id, 'get_single' => true, 'return_object' => false, 'include_totals' => true, 'include_partials' => true));
        } else {
            return array();
        }
    }

    // ------------------------------------------------------------------------

    /**
     * Gets all the paid invoices.  Optionally filtered by client OR by project
     *
     * @access	public
     * @param	int		The client id to filter it by
     * @param       int             The project id to filter it by
     * @return	object 	An object containing the payments
     */
    public function get_all_paid($client_id = NULL, $project_id = NULL, $offset = null) {
        return $this->flexible_get_all(array('client_id' => $client_id, 'project_id' => $project_id, 'paid' => true, 'offset' => $offset, 'include_totals' => true));
    }

    // ------------------------------------------------------------------------

    /**
     * Gets all the unpaid invoices.  Optionally filtered by client.
     *
     * @access	public
     * @param	int		The client id to filter it by
     * @return	object 	An object containing the payments
     */
    public function get_all_unpaid($client_id = NULL, $offset = null) {
        $all_unpaid = $this->flexible_get_all(array('client_id' => $client_id, 'paid' => false, 'offset' => $offset, 'include_totals' => true));

        return $this->dispatch_return('invoice_all_unpaid', $all_unpaid);
    }

    /**
     * Gets all the unpaid invoices that were sent to the client. Optionally filtered by client.
     *
     * @access	public
     * @param	int		The client id to filter it by
     * @return	object 	An object containing the payments
     */
    public function get_all_sent_but_unpaid($client_id = NULL, $offset = null) {

        $sent_but_unpaid = $this->flexible_get_all(array('client_id' => $client_id, 'paid' => false, 'sent' => true, 'offset' => $offset, 'include_totals' => true));
        return $this->dispatch_return('invoice_sent_but_unpaid', $sent_but_unpaid);
    }

    // ------------------------------------------------------------------------

    /**
     * Gets all the overdue invoices.  Optionally filtered by client.
     *
     * @access	public
     * @param	int		The client id to filter it by
     * @return	object 	An object containing the payments
     */
    public function get_all_overdue($client_id = NULL, $offset = null, $limit = null, $order = null) {
        $data = array(
            'paid' => false,
            'overdue' => true,
            'offset' => $offset,
            'include_totals' => true
        );

        if ($limit > 0) {
            $data['per_page'] = $limit;
        }

        if ($client_id) {
            $data['client_id'] = $client_id;
        }

        if ($order !== null) {
            $data['order'] = $order;
        }

        $all_overdue = $this->flexible_get_all($data);
        return $this->dispatch_return('invoice_all_overdue', $all_overdue);
    }

    public function days_overdue($due_date) {

        // Who did this? Huh? Slap yo hand, we only use OLD SKOOL codez up in herez... Foo.
        // $today = new DateTime();
        // $due = new DateTime('@'.$due_date);
        // $diff = $due->diff($today);
        // return $diff->days;

        $start = new DateTime('@' . $due_date);
        $today = new DateTime();
        $days = round(abs($today->format('U') - $start->format('U')) / (60 * 60 * 24));

        return $this->dispatch_return('invoice_days_overdue', $days);
    }

    public function get_all_unseen($days_out = null, $limit = null) {
        $due_in = new DateTime("+$days_out days");
        $ts = strtotime($due_in->format('Y-m-d H:i:s'));

        $data = array('last_seen' => 0, 'paid' => false, 'days_away' => $ts, 'include_totals' => true, 'include_partials' => true, 'overdue' => false, 'sent' => true, 'order' => array('due_date' => 'ASC'));

        if ($limit !== null) {
            $data['offset'] = 0;
            $data['per_page'] = $limit;
        }

        $all_unseen = $this->flexible_get_all($data);
        return $this->dispatch_return('invoice_get_all_unseen', $all_unseen);
    }

    // ------------------------------------------------------------------------

    /**
     * Gets all the estimates.  Optionally filtered by client.
     *
     * @access	public
     * @param	int		The client id to filter it by
     * @return	object 	An object containing the estimates
     */
    public function get_all_estimates($client_id = NULL, $offset = null, $status = null, $sent = null) {
        return $this->flexible_get_all(array('type' => 'estimates', 'client_id' => $client_id, 'offset' => $offset, 'include_totals' => true, 'status' => $status, 'sent' => $sent));
    }

    /**
     * Gets all the credit_notes.  Optionally filtered by client.
     *
     * @access	public
     * @param	int		The client id to filter it by
     * @return	object 	An object containing the credit_notes
     */
    public function get_all_credit_notes($client_id = NULL, $offset = null) {
        return $this->flexible_get_all(array('type' => 'credit_notes', 'client_id' => $client_id, 'offset' => $offset, 'include_totals' => true));
    }

    public function is_estimate($unique_id) {
        $buffer = $this->db->select('type')->where('type', 'ESTIMATE')->where('unique_id', $unique_id)->get($this->table)->row_array();
        return isset($buffer['type']);
    }

    public function get_type($unique_id) {
        $buffer = $this->db->select('type')->where('unique_id', $unique_id)->get($this->table)->row_array();
        return $buffer['type'];
    }

    public function mark_as_sent($unique_id) {
        return $this->db->where('unique_id', $unique_id)->update($this->table, array('last_sent' => time(), 'has_sent_notification' => 1, 'is_viewable' => 1));
    }

    public function duplicate($unique_id, $custom_data = array()) {

        $invoice = $this->db->get_where('invoices', array('unique_id' => $unique_id))->row_array();
        $rows = $this->db->order_by('id', 'asc')->get_where('invoice_rows', array('unique_id' => $unique_id))->result_array();
        $payments = $this->db->get_where('partial_payments', array('unique_invoice_id' => $unique_id))->result_array();

        $newUniqueId = $this->_generate_unique_id();
        $newNumber = $this->_generate_invoice_number(null, $invoice['type']);

        # Okay, now we've got the invoice details, the new number and new unique ID, let's manipulate $invoice to match a new invoice array.

        $old_id = $invoice['id'];
        $old_due_date = $invoice['due_date'];
        unset($invoice['id']);
        $invoice['unique_id'] = $newUniqueId;
        $invoice['invoice_number'] = $newNumber;
        $invoice['date_entered'] = time();
        $invoice['last_sent'] = 0;
        $invoice['payment_date'] = 0;
        $invoice['is_paid'] = 0;
        $invoice['is_viewable'] = 0; # Reset is_viewable because this is a draft invoice.
        $invoice['proposal_id'] = 0;
        $invoice['last_viewed'] = 0;
        $invoice['has_sent_notification'] = 0;

        $invoice = array_merge($invoice, $custom_data);

        $this->db->insert('invoices', $invoice);
        $newId = $this->db->insert_id();

        $row_ids = array();
        foreach ($rows as $row) {
            $original_row_id = $row['id'];
            unset($row['id']);
            $row['unique_id'] = $newUniqueId;
            $this->db->insert('invoice_rows', $row);
            $row_ids[$original_row_id] = $this->db->insert_id();
        }

        $invoice_rows_taxes = $this->db->where_in("invoice_row_id", array_keys($row_ids))->get('invoice_rows_taxes')->result_array();
        $invoice_rows_taxes_batch = array();
        foreach ($invoice_rows_taxes as $row_tax) {
            $invoice_rows_taxes_batch[] = array(
                'tax_id' => $row_tax['tax_id'],
                'invoice_row_id' => $row_ids[$row_tax['invoice_row_id']]
            );
        }
        if (count($invoice_rows_taxes_batch) > 0) {
            $this->db->insert_batch("invoice_rows_taxes", $invoice_rows_taxes_batch);
        }

        $CI = &get_instance();
        $CI->load->model('invoices/partial_payments_m', 'ppm');

        foreach ($payments as $payment) {
            unset($payment['id']);

            # Check if the invoice's due date has changed through $custom_date. If it has, payments should be updated to reflect the new due date.
            $new_due_date = $old_due_date == $invoice['due_date'] ? $payment['due_date'] : $invoice['due_date'];

            $CI->ppm->setPartialPayment($newUniqueId, $payment['key'], $payment['amount'], $payment['is_percentage'], $new_due_date, $payment['notes']);
        }

        require_once APPPATH . 'modules/gateways/gateway.php';
        Gateway::duplicateInvoiceGateways($old_id, $newId);

        $this->fixInvoiceRecord($newUniqueId);
        return array(
            'number' => $newNumber,
            'unique_id' => $newUniqueId,
            'id' => $newId
        );
    }

    function get_estimates_export() {
        $invoices = $this->db->where('type', 'ESTIMATE')->get('invoices')->result_array();
        $buffer_invoice_rows = $this->db->get('invoice_rows')->result_array();
        $invoice_rows = array();

        foreach ($buffer_invoice_rows as $row) {
            if (!isset($invoice_rows[$row['unique_id']])) {
                $invoice_rows[$row['unique_id']] = array();
            }

            $invoice_rows[$row['unique_id']][] = $row;
        }

        $return = array();
        foreach ($invoices as $key => $invoice) {
            $invoice['invoice_rows'] = $invoice_rows[$invoice['unique_id']];
            $return[] = $invoice;
        }
        return $return;
    }

    function get_invoices_csv() {
        $invoices = $this->flexible_get_all(array(
            "return_object" => false,
            "include_totals" => true,
            "include_partials" => true,
        ));

        $return = array();
        $clients = get_dropdown('clients', 'id', "client_name");

        $max_items = 1;
        $max_parts = 1;
        $max_taxes = 0;
        foreach ($invoices as $row) {
            $max_items = max(array(count($row['items']), $max_items));
            $max_parts = max(array(count($row['partial_payments']), $max_parts));

            foreach ($row['items'] as $item) {
                $max_taxes = max(array(count($item['tax_ids']), $max_taxes));
            }
        }

        require_once APPPATH . 'modules/gateways/gateway.php';
        $gateways = Gateway::get_gateways();

        foreach ($invoices as $row) {

            if (!isset($clients[$row['client_id']])) {
                # Expense belongs to a project that no longer exists.
                $client = __("global:na");
            } else {
                $client = $clients[$row['client_id']];
            }

            $items = $row['items'];
            $items = array_merge($items, array());

            $data = array(
                "Client" => $client,
                "Invoice #" => $row['invoice_number'],
                "Date of Creation" => date('r', $row['date_entered']),
                "Due Date" => $row['due_date'] > 0 ? date('c', $row['due_date']) : "",
                "Notes" => $row['notes'],
                "Description" => $row['description'],
                "Show in client area?" => $row['is_viewable'] ? "Yes" : "No",
                "Total Amount (with tax)" => $row['billable_amount'],
                "Total Amount (without tax)" => round($row['amount'], 2),
                "Amount Paid" => $row['paid_amount'],
                "Amount Unpaid" => $row['unpaid_amount'],
                "Payment Date" => $row['payment_date'] > 0 ? date('c', $row['payment_date']) : "",
                "Currency" => $row['currency_code'] ? $row['currency_code'] : Currency::code(),
            );

            $buffer = 1;
            while ($buffer <= $max_items) {
                $item = isset($items[$buffer - 1]) ? $items[$buffer - 1] : null;

                $item_total = $item['qty'] * $item['rate'];
                $item_discount = $item['discount_is_percentage'] ? ($item['discount'] * $item_total / 100) : $item['discount'];

                $data["Item #$buffer Name"] = isset($item) ? $item['name'] : "";
                $data["Item #$buffer Description"] = isset($item) ? $item['description'] : "";
                $data["Item #$buffer Quantity"] = isset($item) ? $item['qty'] : "";
                $data["Item #$buffer Rate"] = isset($item) ? $item['rate'] : "";

                $subbuffer = 1;
                if (isset($item)) {
                    $item['tax_ids'] = array_merge($item['tax_ids'], array());
                }
                while ($subbuffer <= $max_taxes) {
                    $item_tax = "";
                    $item_tax_name = "";

                    if (isset($item['tax_ids'][$subbuffer - 1])) {
                        $item_tax = Settings::tax($item['tax_ids'][$subbuffer - 1]);

                        if ($item_tax) {
                            $item_tax_name = " (" . $item_tax['name'] . ($item_tax['is_compound'] ? " - Compound Tax" : "") . ")";
                            $item_tax = $item_tax['value'] . "%";
                        }
                    }

                    $data["Item #$buffer Tax #{$subbuffer}{$item_tax_name}"] = $item_tax;
                    $subbuffer++;
                }

                $data["Item #$buffer Total Amount (without tax)"] = isset($item) ? $item_total : "";
                $data["Item #$buffer Gross Discount"] = isset($item) ? $item_discount : "";
                $buffer++;
            }

            $buffer = 1;
            while ($buffer <= $max_parts) {
                $part = isset($row['partial_payments'][$buffer]) ? $row['partial_payments'][$buffer] : null;

                if (isset($part)) {
                    $data["Payment #$buffer Gross Amount"] = $part['billableAmount'];
                    $data["Payment #$buffer Is Paid"] = $part['is_paid'] ? "Yes" : "No";
                    $data["Payment #$buffer Due Date"] = $part['due_date'] > 0 ? date('c', $part['due_date']) : "";
                    $data["Payment #$buffer Payment Date"] = $part['payment_date'] > 0 ? date('c', $part['payment_date']) : "";
                    $data["Payment #$buffer Payment Method"] = isset($gateways[$part['payment_method']]) ? $gateways[$part['payment_method']]['title'] : "";
                    $data["Payment #$buffer Transaction ID"] = $part['txn_id'];
                    $data["Payment #$buffer Transaction Fee"] = $part['transaction_fee'] > 0 ? $part['transaction_fee'] : "";
                } else {
                    $data["Payment #$buffer Gross Amount"] = "";
                    $data["Payment #$buffer Is Paid"] = "";
                    $data["Payment #$buffer Due Date"] = "";
                    $data["Payment #$buffer Payment Date"] = "";
                    $data["Payment #$buffer Payment Method"] = "";
                    $data["Payment #$buffer Transaction ID"] = "";
                    $data["Payment #$buffer Transaction Fee"] = "";
                }
                $buffer++;
            }

            $return[] = $data;
        }

        return $return;
    }

    public function flexible_get_all($config) {
        $type = isset($config['type']) ? $config['type'] : 'invoices';
        $from = isset($config['from']) ? $config['from'] : 0;
        $to = isset($config['to']) ? $config['to'] : 0;
        $client_id = isset($config['client_id']) ? $config['client_id'] : NULL;
        $unique_id = isset($config['unique_id']) ? $config['unique_id'] : NULL;
        $id = isset($config['id']) ? $config['id'] : NULL;
        $invoice_number = isset($config['invoice_number']) ? $config['invoice_number'] : NULL;
        $project_id = isset($config['project_id']) ? $config['project_id'] : NULL;
        $overdue = isset($config['overdue']) ? $config['overdue'] : NULL;
        $paid = isset($config['paid']) ? $config['paid'] : NULL;
        $viewable = isset($config['viewable']) ? $config['viewable'] : NULL;
        $recurring = isset($config['recurring']) ? $config['recurring'] : NULL;
        $recurrences = isset($config['recurrences']) ? $config['recurrences'] : NULL;
        $sent = isset($config['sent']) ? $config['sent'] : NULL;
        $object = isset($config['return_object']) ? $config['return_object'] : true;
        $get_single = isset($config['get_single']) ? $config['get_single'] : false;
        $offset = isset($config['offset']) ? $config['offset'] : NULL; # if offset is NOT null, then it was provided, meaning we want pagination
        $include_totals = isset($config['include_totals']) ? $config['include_totals'] : false;
        $include_items = isset($config['include_items']) ? $config['include_items'] : false;
        $include_partials = isset($config['include_partials']) ? $config['include_partials'] : false;
        $include_receipts = isset($config['include_receipts']) ? $config['include_receipts'] : false;
        $past_x_days = isset($config['past_x_days']) ? (int) $config['past_x_days'] : false;
        $order = isset($config['order']) ? $config['order'] : array('due_date' => 'DESC', 'date_entered' => 'DESC', 'id' => 'DESC');
        $per_page = isset($config['per_page']) ? $config['per_page'] : Settings::get('items_per_page');
        $days_away = isset($config['days_away']) ? $config['days_away'] : null;
        $status = isset($config['status']) ? $config['status'] : null;

        $overdue_buffer = '(due_date < ' . time() . ' and last_sent > 0 and is_paid = 0)';

        where_assigned('estimates_plus_invoices', 'read');

        $this->db
                ->select("UNIX_TIMESTAMP(DATE_SUB(FROM_UNIXTIME(IF(due_date > 0, due_date, date_entered )), INTERVAL send_x_days_before DAY)) as date_to_automatically_notify,
		    	invoices.id as real_invoice_id,
                            " . $this->db->dbprefix('invoices') . ".unique_id as real_invoice_unique_id,
			    invoices.is_paid as paid,
			    $overdue_buffer as overdue,
			    invoices.*,
			    clients.address, clients.language, clients.first_name, clients.last_name, clients.email, clients.company, clients.phone, clients.unique_id as client_unique_id,
			    currencies.code as currency_code,
			    (SELECT COUNT(id) FROM " . $this->db->dbprefix('comments') . " WHERE item_id = real_invoice_id AND item_type = 'invoice' and is_private = 0) as total_comments,
			    ", false)
                ->join('clients', 'invoices.client_id = clients.id', 'left')
                ->join('currencies', 'invoices.currency_id = currencies.id', 'left');

        if ($unique_id !== NULL) {
            if (!is_array($unique_id)) {
                $unique_id = array($unique_id);
            }

            $this->db->where_in('invoices.unique_id', $unique_id);
        }

        if ($id !== NULL) {
            $this->db->where('invoices.id', $id);
        }

        if ($invoice_number !== NULL) {
            $this->db->where('invoices.invoice_number', $invoice_number);
        }

        if ($past_x_days) {
            $this->db->where(array('invoices.date_entered >' => strtotime('-' . $past_x_days . ' days')));
        }

        # if offset is NOT null, then it was provided, meaning we want pagination
        if ($offset !== NULL) {
            $this->db->limit($per_page, $offset);
        }

        if ($paid !== NULL) {
            if ($paid) {
                $this->db->where(array('invoices.is_paid' => 1));
            } else {
                $this->db->where(array('invoices.is_paid' => 0));
            }
        }

        if ($viewable !== NULL) {
            if ($viewable) {
                $this->db->where(array('invoices.is_viewable' => 1));
            } else {
                $this->db->where(array('invoices.is_viewable' => 0));
            }
        }

        if ($recurring !== NULL) {
            if ($recurring) {
                $this->db->where(array('invoices.is_recurring' => 1));
                $this->db->where('(' . $this->db->dbprefix('invoices') . '.id = ' . $this->db->dbprefix('invoices') . '.recur_id)', null, false);
            } else {
                $this->db->where(array('invoices.is_recurring' => 0));
            }
        }

        if ($recurrences !== NULL) {
            if ($recurrences) {
                $this->db->where(array('invoices.recur_id !=' => 0));
            } else {
                $this->db->where(array('invoices.recur_id' => 0));
            }
        }

        if ($sent !== NULL) {
            if ($sent) {
                $this->db->where('invoices.last_sent !=', 0);
            } else {
                $this->db->where(array('invoices.last_sent' => 0, 'invoices.is_paid' => 0));
            }
        }

        if ($overdue !== NULL) {
            if ($overdue) {
                $this->db->where('(' . $overdue_buffer . ' = 1)');
            } else {
                $this->db->where('(' . $overdue_buffer . ' = 0)');
            }
        }

        if ($days_away) {
            $this->db->where('due_date <', $days_away);
        }

        if (is_array($client_id)) {
            if (count($client_id) > 0) {
                $this->db->where_in('invoices.client_id', $client_id);
            } else {
                $this->db->where("false", null, false);
            }
        } elseif ($client_id > 0) {
            $this->db->where('invoices.client_id', $client_id);
        }

        if ($project_id !== NULL) {
            $this->db->where('project_id', $project_id);
        }

        if ($status !== null) {
            $this->db->where('status', $status);
        }

        $first_payment_date = 'ifnull((select payment_date from ' . $this->db->dbprefix('partial_payments') . ' where unique_invoice_id = ' . $this->db->dbprefix('invoices') . '.unique_id and payment_date != 0 order by payment_date asc limit 1), 0)';
        $is_paid = $this->db->dbprefix('invoices') . '.is_paid';

        $from = (int) $from;
        $to = (int) $to;

        if ($from > 0 and $to > 0) {
            $this->db->where("date_entered > $from and date_entered < $to", null, false);
        } else {

            if ($from != 0) {
                $this->db->where("date_entered > $from", null, false);
            }

            if ($to != 0) {
                $this->db->where("date_entered < $to", null, false);
            }
        }

        if ($type == 'invoices') {
            $this->db->where('type', 'DETAILED');
        } elseif ($type == 'estimates') {
            $this->db->where('type', 'ESTIMATE');
        } elseif ($type == 'credit_notes') {
            $this->db->where('type', 'CREDIT_NOTE');
        } elseif ($type !== 'all') {
            throw new Exception("Unknown type of record to fetch. Expected invoices/estimates/credit_notes, but got: $type");
        }

        foreach ($order as $field_to_order_by => $desc_or_asc) {
            $this->db->order_by('invoices.' . $field_to_order_by, strtoupper($desc_or_asc));
        }

        $result = $this->db->get($this->table)->result();

        $CI = &get_instance();
        $CI->load->model('invoices/partial_payments_m', 'ppm');
        $CI->ppm->cache();
        $CI->load->model('proposals/proposals_m');
        $CI->load->model('projects/project_expense_m');

        $return = array();

        $unique_ids = array();
        foreach ($result as $invoice) {
            $unique_ids[] = $invoice->real_invoice_unique_id;
        }

        $all_items = $this->rows_with_tax_total($unique_ids);

        foreach ($result as $invoice) {
            $invoice->tax_total = $CI->ppm->get_tax_total($invoice->unique_id);
            $invoice->billable_amount = $invoice->amount + $invoice->tax_total;
            $invoice->client_name = $invoice->first_name . ' ' . $invoice->last_name;
            $invoice->formatted_is_paid = $invoice->is_paid ? __('global:paid') : __('global:unpaid');
            $invoice->url = site_url($invoice->unique_id);
            $invoice->currency_symbol = Currency::symbol($invoice->currency_code);
            $invoice->part_count = $CI->ppm->get_counts_invoice_partial_payments($invoice->unique_id, "all");
            $invoice->paid_part_count = $CI->ppm->get_counts_invoice_partial_payments($invoice->unique_id, "paid");
            $invoice->unpaid_part_count = $CI->ppm->get_counts_invoice_partial_payments($invoice->unique_id, "unpaid");
            $invoice->proposal_num = ($invoice->proposal_id != 0) ? $CI->proposals_m->getProposalNumberById($invoice->proposal_id) : '';

            if ($invoice->type == 'ESTIMATE') {
                $invoice->list_invoice_belongs_to = 'estimates';
            } elseif ($invoice->type == 'CREDIT_NOTE') {
                $invoice->list_invoice_belongs_to = 'credit_notes';
            } else {
                if ($invoice->is_recurring) {
                    $invoice->list_invoice_belongs_to = 'recurring';
                } else {
                    if ($invoice->is_paid) {
                        $invoice->list_invoice_belongs_to = 'paid';
                    } else {
                        if ($invoice->overdue) {
                            $invoice->list_invoice_belongs_to = 'overdue';
                        } else {
                            if ($invoice->last_sent > 0) {
                                $invoice->list_invoice_belongs_to = 'unpaid';
                            } else {
                                $invoice->list_invoice_belongs_to = 'unsent';
                            }
                        }
                    }
                }
            }

            if ($include_totals or $include_items) {
                if ($invoice->type != 'SIMPLE') {

                    $invoice->items = isset($all_items[$invoice->real_invoice_unique_id]) ? $all_items[$invoice->real_invoice_unique_id] : array();
                    $invoice->taxes = array();
                    $invoice->sub_total = 0;
                    $invoice->has_discount = false;
                    $invoice->discounts = array();

                    // Loop through items and build subtotal & tax stuff
                    foreach ($invoice->items as &$item) {
                        if (in_array($item['type'], array("fixed_discount", "percentage_discount"))) {
                            continue;
                        }

                        if ($item['discount'] > 0) {
                            $invoice->has_discount = true;
                        }

                        if ($item['item_type_id'] == 0 and $item['item_type_table'] == '' and $item['type'] == 'expense') {
                            // For backward compatibility:
                            $item['type'] = 'standard';
                        }

                        foreach ($item['taxes'] as $tax_id => $tax_total) {
                            if (!isset($invoice->taxes[$tax_id])) {
                                $invoice->taxes[$tax_id] = 0;
                            }

                            $invoice->taxes[$tax_id] += $tax_total;
                        }

                        // Update sub-total
                        $invoice->sub_total += $item['total'];
                    }

                    $after_discounts = $invoice->sub_total;

                    foreach ($invoice->items as &$item) {
                        if ($item['type'] == "fixed_discount") {
                            $invoice->discounts[] = array(
                                "is_fixed" => true,
                                "value" => $item['discount'],
                                "gross_amount" => $item['discount']
                            );
                            $after_discounts -= $item['discount'];
                        }
                    }

                    foreach ($invoice->items as &$item) {
                        if ($item['type'] == "percentage_discount") {
                            $amount = ($item['discount'] / 100) * $after_discounts;
                            $invoice->discounts[] = array(
                                "is_fixed" => false,
                                "value" => $item['discount'],
                                "gross_amount" => $amount
                            );
                            $after_discounts -= $amount;
                        }
                    }

                    $invoice->sub_total_after_discounts = $after_discounts;
                    $invoice->total = $invoice->sub_total_after_discounts + $invoice->tax_total;

                    if ($include_receipts) {
                        $invoice->receipts = array();
                        foreach ($invoice->items as &$item) {
                            if ($item['type'] == "expense") {
                                $invoice->receipts[$item['item_type_id']] = $item['item_type_id'];
                            }
                        }

                        if (count($invoice->receipts) > 0) {
                            $expenses = $CI->project_expense_m->get_by_ids($invoice->receipts);
                            foreach ($expenses as $expense) {
                                if (!empty($expense['receipt'])) {
                                    $invoice->receipts[$expense['id']] = $expense['receipt'];
                                } else {
                                    unset($invoice->receipts[$expense['id']]);
                                }
                            }
                        }
                    }
                }
            }

            if ($include_totals) {
                if ($invoice->type != 'SIMPLE') {

                    $i = 0;

                    $invoice->paid_amount = $this->ppm->getInvoicePaidAmount($invoice->unique_id);
                    $invoice->unpaid_amount = $invoice->billable_amount - $invoice->paid_amount;
                    $invoice->collected_taxes = array();

                    foreach ($invoice->taxes as $id => $total) {
                        $tax = Settings::tax($id);
                        if (!empty($tax['reg'])) {
                            $i++;
                        }

                        if ($invoice->billable_amount > 0) {
                            $invoice->collected_taxes[$id] = ($invoice->paid_amount * $total) / ($invoice->billable_amount);
                        } else {
                            $invoice->collected_taxes[$id] = 0;
                        }
                    }

                    $invoice->has_tax_reg = ($i > 0);
                    if ($invoice->billable_amount > 0) {
                        $invoice->tax_collected = $invoice->amount ? ( ($invoice->paid_amount * $invoice->tax_total) / ($invoice->billable_amount) ) : 0;
                    } else {
                        $invoice->tax_collected = 0;
                    }
                } else {
                    $invoice->items = array();
                    $invoice->paid_amount = $this->ppm->getInvoicePaidAmount($invoice->unique_id);
                    $invoice->unpaid_amount = $invoice->amount - $invoice->paid_amount;
                    $invoice->tax_collected = 0;
                    $invoice->billable_amount = $invoice->amount;
                    $invoice->tax_total = 0;
                    $invoice->total = $invoice->amount;
                    $invoice->has_tax_reg = 0;
                }
            }

            if ($include_partials) {

                $invoice->total_transaction_fees = 0;

                if (isset($invoice->tax_total)) {
                    $invoice->partial_payments = $CI->ppm->getInvoicePartialPayments($invoice->unique_id, $invoice->total);
                } else {
                    $invoice->partial_payments = $CI->ppm->getInvoicePartialPayments($invoice->unique_id);
                }
                $invoice->next_part_to_pay = 0;

                foreach ($invoice->partial_payments as $part) {
                    if (!$part['is_paid'] and $invoice->next_part_to_pay == 0) {
                        $invoice->next_part_to_pay = $part['key'];
                    }

                    if ($part['is_paid']) {
                        $invoice->total_transaction_fees = $invoice->total_transaction_fees + $part['transaction_fee'];
                    }
                }
            }

            $return[] = $object ? $invoice : (array) $invoice;
        }

        if ($get_single) {
            reset($return);
            return current($return);
        } else {
            return $return;
        }
    }

    /**
     * Grabs an invoice's items, with their tax data attached in an array.
     *
     * Provide $rows if you don't want it to fetch them from the DB.
     *
     * @param array $unique_ids
     * @param array $buffer
     * @param array $rows_taxes
     * @return string
     */
    function rows_with_tax_total($unique_ids, $buffer = null, $rows_taxes = null) {
        if (count($unique_ids) == 0 and $buffer === null) {
            return $unique_ids;
        }

        if ($rows_taxes === null) {
            $rows_taxes_buffer = $this->db->select("invoice_rows_taxes.tax_id, invoice_row_id")->where_in("invoice_rows.unique_id", $unique_ids)->join("invoice_rows", "invoice_rows.id = invoice_rows_taxes.invoice_row_id", "left")->get("invoice_rows_taxes")->result_array();
            $rows_taxes = array();
            foreach ($rows_taxes_buffer as $row) {
                if (!isset($rows_taxes[$row['invoice_row_id']])) {
                    $rows_taxes[$row['invoice_row_id']] = array();
                }

                $rows_taxes[$row['invoice_row_id']][] = $row['tax_id'];
            }
        }
        $taxes = Settings::all_taxes();

        if ($buffer === null) {
            $rows_buffer = $this->db->select("invoice_rows.*, code as currency_code")->order_by("invoice_rows.id")->where_in("invoice_rows.unique_id", $unique_ids)->join("invoices", "invoices.unique_id = invoice_rows.unique_id", "left")->join("currencies", "currencies.id = currency_id", "left")->get("invoice_rows")->result_array();
            $buffer = array();
            foreach ($rows_buffer as $row) {
                $buffer[$row['id']] = $row;
            }
            unset($rows_buffer);
        }

        $return = array();

        $discounts = array();
        $non_discount_items_count = array();

        foreach ($buffer as &$row) {
            if (!isset($return[$row['unique_id']])) {
                $return[$row['unique_id']] = array();
            }

            if (!isset($non_discount_items_count[$row['unique_id']])) {
                $non_discount_items_count[$row['unique_id']] = 0;
            }

            $return[$row['unique_id']][$row['id']] = $row;
            unset($return[$row['unique_id']][$row['id']]['tax_id']);
            $return[$row['unique_id']][$row['id']]['taxes'] = array();
            $return[$row['unique_id']][$row['id']]['tax_ids'] = array();
            $return[$row['unique_id']][$row['id']]['is_taxable'] = false;
            $return[$row['unique_id']][$row['id']]['tax_total'] = 0;
            $return[$row['unique_id']][$row['id']]['billable_total'] = 0;
            $return[$row['unique_id']][$row['id']]['tax_label'] = __("settings:no_tax");
            $return[$row['unique_id']][$row['id']]['taxes_buffer'] = array();

            if (in_array($row['type'], array("fixed_discount", "percentage_discount"))) {
                if (!isset($discounts[$row['unique_id']])) {
                    $discounts[$row['unique_id']] = array();
                }

                $discounts[$row['unique_id']][] = array(
                    "value" => $row['discount'],
                    "is_fixed" => ($row['type'] == "fixed_discount")
                );

                $row['total'] = 0;
            } else {
                $non_discount_items_count[$row['unique_id']] ++;

                if (!isset($row['discount_is_percentage'])) {
                    $row['discount_is_percentage'] = 0;
                }

                if (!isset($row['discount'])) {
                    $row['discount'] = 0;
                }

                # Calculate total, removing item discount.
                $row['total'] = $row['discount_is_percentage'] ? (($row['qty'] * $row['rate']) - (($row['qty'] * $row['rate']) * ($row['discount'] / 100))) : (($row['qty'] * $row['rate']) - $row['discount']);
            }
        }

        # Remove invoice discounts, both fixed and percentage.
        foreach ($buffer as &$row) {
            if (isset($discounts[$row['unique_id']]) and ! in_array($row['type'], array("fixed_discount", "percentage_discount"))) {
                $row['total_pre_tax_post_fixed_discounts'] = $row['total'];

                foreach ($discounts[$row['unique_id']] as $discount) {
                    if ($discount["is_fixed"]) {
                        $row['total_pre_tax_post_fixed_discounts'] -= ($discount['value'] / $non_discount_items_count[$row['unique_id']]);
                    }
                }

                $row['total_pre_tax_post_discounts'] = $row['total_pre_tax_post_fixed_discounts'];

                foreach ($discounts[$row['unique_id']] as $discount) {
                    if (!$discount["is_fixed"]) {
                        $row['total_pre_tax_post_discounts'] -= $row['total_pre_tax_post_discounts'] * ($discount['value'] / 100);
                    }
                }
            }
        }

        foreach ($buffer as &$row) {
            $row['total_pre_tax_post_discounts'] = isset($row['total_pre_tax_post_discounts']) ? $row['total_pre_tax_post_discounts'] : $row['total'];
        }

        # Add taxes.
        foreach ($rows_taxes as $row_id => $row_taxes) {
            $row = &$buffer[$row_id];

            # Add non-compound taxes first.
            $row["total_post_non_compound_tax_post_discounts"] = isset($row['total_pre_tax_post_discounts']) ? $row['total_pre_tax_post_discounts'] : $row['total'];
            foreach ($row_taxes as $tax) {
                if ($tax == 0) {
                    continue;
                }

                if (isset($taxes[$tax]) and ! $taxes[$tax]['is_compound']) {
                    $tax_total = ($taxes[$tax]['value'] / 100) * $row['total_pre_tax_post_discounts'];

                    if (!isset($return[$row['unique_id']][$row['id']]['taxes'][$tax])) {
                        $return[$row['unique_id']][$row['id']]['taxes'][$tax] = 0;
                    }

                    $return[$row['unique_id']][$row['id']]['taxes'][$tax] += $tax_total;
                    $return[$row['unique_id']][$row['id']]['tax_ids'][$tax] = $tax;
                    $return[$row['unique_id']][$row['id']]['is_taxable'] = true;
                    $return[$row['unique_id']][$row['id']]['tax_total'] += $tax_total;
                    $return[$row['unique_id']][$row['id']]['taxes_buffer'][] = $taxes[$tax]['name'];
                    $row["total_post_non_compound_tax_post_discounts"] += $tax_total;
                }
            }

            # Add compound taxes.
            $row["total_post_tax_post_discounts"] = $row['total_post_non_compound_tax_post_discounts'];
            foreach ($row_taxes as $tax) {
                if ($tax == 0) {
                    continue;
                }

                if (isset($taxes[$tax]) and $taxes[$tax]['is_compound']) {
                    $tax_total = ($taxes[$tax]['value'] / 100) * $row['total_post_tax_post_discounts'];

                    if (!isset($return[$row['unique_id']][$row['id']]['taxes'][$tax])) {
                        $return[$row['unique_id']][$row['id']]['taxes'][$tax] = 0;
                    }

                    $return[$row['unique_id']][$row['id']]['taxes'][$tax] += $tax_total;
                    $return[$row['unique_id']][$row['id']]['tax_ids'][$tax] = $tax;
                    $return[$row['unique_id']][$row['id']]['is_taxable'] = true;
                    $return[$row['unique_id']][$row['id']]['tax_total'] += $tax_total;
                    $return[$row['unique_id']][$row['id']]['taxes_buffer'][] = $taxes[$tax]['name'];
                    $row["total_post_tax_post_discounts"] += $tax_total;
                }
            }
        }

        foreach ($buffer as &$row) {
            if (isset($row['total_post_tax_post_discounts'])) {
                $return[$row['unique_id']][$row['id']]['billable_total'] = $row['total_post_tax_post_discounts'];
            } elseif (isset($row['total_pre_tax_post_discounts'])) {
                $return[$row['unique_id']][$row['id']]['billable_total'] = $row['total_pre_tax_post_discounts'];
            } else {
                $return[$row['unique_id']][$row['id']]['billable_total'] = $row['total'];
            }

            if (!isset($row['total_pre_tax_post_discounts']) and $row['total'] == 0) {
                $row['total_pre_tax_post_discounts'] = $row['total'];
            }

            $return[$row['unique_id']][$row['id']]['total_pre_tax_post_discounts'] = $row['total_pre_tax_post_discounts'];

            if ($return[$row['unique_id']][$row['id']]['is_taxable']) {
                $return[$row['unique_id']][$row['id']]['tax_label'] = implode_to_human_csv($return[$row['unique_id']][$row['id']]['taxes_buffer']);
            }
        }

        return $return;
    }

    function get_credit_note_before_date($client_id, $date) {
        $credit_note = $this->db->select("unique_id, date_entered")->where("type", "CREDIT_NOTE")->where("date_entered <=", $date)->where("client_id", $client_id)->order_by("date_entered", "desc")->limit(1)->get("invoices")->row_array();
        if (isset($credit_note['unique_id'])) {
            $rows_with_tax_totals = $this->rows_with_tax_total(array($credit_note['unique_id']));
            $rows_with_tax_totals = array_reduce($rows_with_tax_totals, function($total, $rows) {
                return $total + array_reduce($rows, function($item_total, $value) {
                            return $item_total + Currency::convert($value['billable_total'], $value['currency_code']);
                        });
            });

            return array('total' => $rows_with_tax_totals, 'date' => $credit_note['date_entered']);
        } else {
            return array('total' => 0, 'date' => $date);
        }
    }

    function get_credit_notes_total($client_id, $date = null) {
        
        if ($date === null) {
            $date = time();
        }
        
        $unique_ids = $this->db->select("unique_id")->where("date_entered <=", $date)->where("type", "CREDIT_NOTE")->where("client_id", $client_id)->get("invoices")->result_array();
        $unique_ids = array_map(function($value) {
            return $value['unique_id'];
        }, $unique_ids);
        $rows_with_tax_totals = $this->rows_with_tax_total($unique_ids);
        $rows_with_tax_totals = array_reduce($rows_with_tax_totals, function($total, $rows) {
            return $total + array_reduce($rows, function($item_total, $value) {
                        return $item_total + Currency::convert($value['billable_total'], $value['currency_code']);
                    });
        });

        return $rows_with_tax_totals;
    }

    function calculate_totals_from_line_items($type, $amount, $line_items, $total_to_return = null) {
        $totals = array(
            'total_without_tax' => 0,
            'tax_total' => 0,
            'total_with_tax' => 0,
            'taxes' => array()
        );

        if ($type == "SIMPLE") {
            $totals = array(
                'total_without_tax' => $amount,
                'tax_total' => 0,
                'total_with_tax' => $amount,
                'taxes' => array()
            );

            return $total_to_return === null ? $totals : $totals[$total_to_return];
        }

        $rows_taxes = array();
        foreach ($line_items as $key => &$item) {
            $item['unique_id'] = "NOT_IN_DB_YET";
            $item['id'] = $key;
            if (!in_array($item['type'], array("fixed_discount", "percentage_discount"))) {
                $rows_taxes[$item['id']] = $item['tax_ids'];
            }
        }

        $calculated_totals = $this->rows_with_tax_total(array("NOT_IN_DB_YET"), $line_items, $rows_taxes);

        foreach ($calculated_totals["NOT_IN_DB_YET"] as $item) {
            if (!in_array($item['type'], array("fixed_discount", "percentage_discount"))) {
                $totals['total_without_tax'] += $item['total_pre_tax_post_discounts'];
                $totals['tax_total'] += $item['tax_total'];
                $totals['total_with_tax'] += $item['billable_total'];

                foreach ($item['taxes'] as $tax_id => $tax_amount) {
                    if (!isset($totals["taxes"][$tax_id])) {
                        $totals["taxes"][$tax_id] = 0;
                    }

                    $totals["taxes"][$tax_id] += $tax_amount;
                }
            }
        }

        return $total_to_return === null ? $totals : $totals[$total_to_return];
    }

    public function getEarliestInvoiceDate() {
        where_assigned('estimates_plus_invoices', 'read');
        $buffer = $this->db
                ->select('date_entered')
                ->limit(1)
                ->order_by('date_entered', 'asc')
                ->get($this->table)
                ->row_array();

        if (isset($buffer['date_entered'])) {
            return $buffer['date_entered'];
        } else {
            return time();
        }
    }

    // ------------------------------------------------------------------------

    /**
     * Get a list of invoices joined to task time
     *
     * @access	public
     * @param	string	The project id
     * @return	array  Array of invoice objects
     */
    public function get_linked_invoices($project_id) {
        # Flexible Get All already has permissions, so there's no need to redo it.
        return $this->flexible_get_all(array('include_totals' => true, 'project_id' => $project_id));
    }

    /**
     * Inserts a new invoice
     *
     * @access	public
     * @param	array	The input array
     * @return	string 	The unique id of the payment
     */
    public function insert($input, $files = array()) {

        if ($this->input->post('project_id')) {
            $query = $this->db->select('client_id')->get_where('projects', array('id' => $this->input->post('project_id')))->row();

            $input['client_id'] = $query ? $query->client_id : null;
        }

        if (!$this->validate($input)) {
            return FALSE;
        }

        if ($input['type'] != 'ESTIMATE') {
            array_pop($this->validate);
        }

        $amount = str_replace(array(',', ' '), '', $input['amount']);

        $CI = &get_instance();
        $CI->load->model('invoices/partial_payments_m', 'ppm');
        $line_items = $this->build_invoice_rows_from_input(isset($input['invoice_item']) ? $input['invoice_item'] : $input['items'], isset($input['invoice_item']));
        $validate_invoice_total = $this->calculate_totals_from_line_items($input['type'], $amount, $line_items, 'total_with_tax');
        if (isset($input['partial-amount'])) {
            # We check that partial payments were entered. If not, there's no need to validate them.
            $validation_result = $CI->ppm->validate_partials($validate_invoice_total, !empty($input['is_recurring']), $input['partial-amount'], $input['partial-is_percentage'], 'insert');

            if (!$validation_result) {
                $this->form_validation->_error_array['amount'] = lang('partial:wrongtotal');
                return FALSE;
            }
        }

        $input['invoice_number'] = $this->_generate_invoice_number(isset($input['invoice_number']) ? $input['invoice_number'] : null, $input['type']);

        $unique_id = $this->_generate_unique_id();

        $amount = $this->insert_invoice_rows($unique_id, $line_items);

        $CI->load->model('files/files_m');
        $upload_result = $CI->files_m->verify_uploads($files);
        if ($upload_result === NOT_ALLOWED) {
            $this->form_validation->_error_array['amount'] = __('global:upload_not_allowed');
            return FALSE;
        }

        // Get currency rate for historically accurate invoicing
        if (!empty($input['currency'])) {
            $currency = $this->db
                    ->select('id, rate')
                    ->where('code', $input['currency'])
                    ->get('currencies')
                    ->row() OR show_error(__('invoices:currencydoesnotexist'));

            $this->db->set(array(
                'currency_id' => $currency->id,
                'exchange_rate' => $currency->rate
            ));
        }

        $due_date = isset($input['due_date']) ? ((is_numeric($input['due_date']) and strlen($input['due_date']) == 10) ? $input['due_date'] : strtotime($input['due_date'])) : 0;

        $input['date_entered'] = isset($input['date_entered']) ? $input['date_entered'] : 0;

        if ($input['date_entered'] > 0) {
            $input['date_entered'] = read_date_picker($input['date_entered']);
        }

        $this->db->set(array(
            'owner_id' => current_user(),
            'unique_id' => $unique_id,
            'client_id' => $input['client_id'],
            'amount' => $amount,
            'due_date' => $due_date,
            'invoice_number' => !empty($input['invoice_number']) ? $input['invoice_number'] : '',
            'notes' => !empty($input['notes']) ? $input['notes'] : null,
            'description' => !empty($input['description']) ? $input['description'] : null,
            'payment_hash' => md5(time()),
            'type' => $input['type'],
            'date_entered' => ($input['date_entered'] > 0 ? $input['date_entered'] : time()),
            'is_paid' => !empty($input['is_paid']),
            'send_x_days_before' => isset($input['send_x_days_before']) ? (($input['send_x_days_before'] >= 0) ? $input['send_x_days_before'] : 7) : 7,
            'payment_date' => !empty($input['is_paid']) ? time() : 0,
            'is_viewable' => !empty($input['is_viewable']),
            'is_recurring' => !empty($input['is_recurring']),
            'frequency' => isset($input['frequency']) ? $input['frequency'] : null,
            'auto_send' => (!empty($input['is_recurring']) && !empty($input['auto_send'])),
            'recur_id' => (!empty($input['is_recurring']) && !empty($input['recur_id'])) ? $input['recur_id'] : 0,
            'project_id' => (isset($input['project_id']) ? (int) $input['project_id'] : 0),
        ))->insert($this->table);

        $insert_id = $this->db->insert_id();

        $this->getNextInvoiceReoccurrenceDate($insert_id);

        # Partial Payments. Let's make sure the amounts work properly AFTER creating the invoice (so we can use getInvoiceTotalAmount()). Shall we?

        if ($input['type'] == 'DETAILED') {
            if (!isset($input['partial-amount'])) {
                # No partial payments have been entered, let's create a 100% due when the invoice is due payment plan.
                $CI->ppm->setPartialPayment($unique_id, 1, 100, 1, (($due_date > 0) ? $due_date : 0), '');
                if ($input['is_paid']) {
                    require_once APPPATH . 'modules/gateways/gateway.php';
                    $gateway = array_keys(Gateway::get_enabled_gateways());
                    $gateway = reset($gateway);
                    $CI->ppm->setPartialPaymentDetails($unique_id, 1, time(), $gateway, 'Completed', '', '');
                }
            } else {
                $result = $CI->ppm->processInput($unique_id, $input['partial-amount'], $input['partial-is_percentage'], $input['partial-due_date'], $input['partial-notes'], isset($input['partial-is_paid']) ? $input['partial-is_paid'] : array(), $validate_invoice_total);

                if ($result === 'WRONG_TOTAL') {
                    $this->form_validation->_error_array['amount'] = lang('partial:wrongtotal');
                    $this->delete($unique_id);
                    return FALSE;
                } elseif (!$result) {
                    $this->form_validation->_error_array['amount'] = lang('partial:problemsaving');
                    $this->delete($unique_id);
                    return FALSE;
                }
            }
        }

        // No input number given, use the insert_id
        if (!empty($input['is_recurring']) AND empty($input['recur_id'])) {
            $this->db
                    ->where('unique_id', $unique_id)
                    ->set('recur_id', $insert_id)
                    ->update($this->table);
        }

        $this->fixInvoiceRecord($unique_id);

        # Process any files provided.
        if (!empty($files)) {
            $this->load->model('files/files_m');
            $this->files_m->upload($files, $unique_id);
        }

        return $unique_id;
    }

    function refresh_reoccurring_invoices() {
        $did_work = false;

        $invoices = $this->db->dbprefix('invoices');

        # Get all invoices whose last reoccurrence is in the past, which means that we need to create a new reocurrence for them.
        # If they have no reoccurrence, last reoccurrence is the due_date of the original invoice. If the original had no due_date,
        # the last reocurrence is date_entered, and the next will be date_entered + 1 frequency. - Bruno
        $buffer = $this->db->query("SELECT id, unique_id FROM $invoices WHERE is_recurring = 1 and id = recur_id and
                IF((SELECT due_date FROM $invoices as i2 WHERE recur_id = $invoices.id order by date_entered desc LIMIT 0, 1) > 0, (SELECT due_date FROM $invoices as i2 WHERE recur_id = $invoices.id order by date_entered desc LIMIT 0, 1), date_entered) < UNIX_TIMESTAMP()")->result_array();

        foreach ($buffer as $row) {
            $invoice = $this->get_by_unique_id($row['unique_id']);
            # Need to create new invoices for each of these.

            $due_date = $this->getNextInvoiceReoccurrenceDate($invoice['id']);

            $should_reoccur_invoice = get_instance()->dispatch_return('decide_should_reoccur_invoice', array(
                'invoice' => $invoice,
                'next_reoccurrence_due_date' => $due_date
                    ), 'boolean');

            if (is_array($should_reoccur_invoice)) {
                # No plugins available.
                $should_reoccur_invoice = true;
            }

            if (!$should_reoccur_invoice) {
                continue;
            }

            $details = $this->duplicate($invoice['unique_id'], array(
                'date_entered' => strtotime('-' . $invoice['send_x_days_before'] . ' days', $due_date),
                'due_date' => $due_date,
            ));

            if ($details) {
                echo "Created invoice #" . $details['number'] . (IS_CLI ? PHP_EOL : '<br/>');
            } else {
                echo "Failed to create clone of ID-{$invoice['recur_id']}" . (IS_CLI ? PHP_EOL : '<br/>');
            }

            $did_work = true;

            # Update the next reoccurrence date.
            $this->getNextInvoiceReoccurrenceDate($invoice['id']);
        }

        # Send necessary reoccurring invoice emails.
        $buffer = $this->db->query("SELECT id, unique_id, send_x_days_before, IF(due_date > 0, due_date, date_entered) as due_date FROM $invoices WHERE is_paid = 0 and auto_send = 1 and is_recurring = 1 and last_sent = 0")->result_array();

        foreach ($buffer as $row) {
            if (time() > strtotime('-' . $row['send_x_days_before'] . ' days', $row['due_date'])) {
                # Need to send out notification email!
                $success = $this->sendNotificationEmail($row['unique_id']);

                if ($success) {
                    echo "Sent invoice notification email for invoice #" . $this->getInvoiceNumberById($row['id']) . (IS_CLI ? PHP_EOL : '<br/>');
                } else {
                    echo "Failed to send invoice notification email for invoice #" . $this->getInvoiceNumberById($row['id']) . (IS_CLI ? PHP_EOL : '<br/>');
                }

                $did_work = true;
            }
        }

        # If there were invoices created / sent out, run again to take into account the just-created invoices.
        if ($did_work) {
            $this->refresh_reoccurring_invoices();
        }
    }

    function get_last_reoccurrence($invoice_id) {
        $invoice_id = (int) $invoice_id;
        $unique_id = $this->db
                ->select('unique_id')
                ->where('recur_id', $invoice_id)
                ->where('id !=', $invoice_id)
                ->order_by('date_entered', 'desc')
                ->limit(1)
                ->get('invoices')
                ->row_array();
        $unique_id = reset($unique_id);
        if (empty($unique_id)) {
            return array();
        } else {
            return $this->get($unique_id);
        }
    }

    function getNextInvoiceReoccurrenceDate($invoice_id) {
        $invoice = $this->flexible_get_all(array('type' => 'all', 'id' => $invoice_id, 'get_single' => true, 'return_object' => false));

        if ($invoice['is_recurring'] and $invoice['recur_id'] == $invoice['id']) {
            $buffer = $this->db->where('recur_id', $invoice['id'])->order_by('due_date', 'desc')->limit(1)->get($this->table)->row_array();
            $lastReoccurrence = $buffer['due_date'];

            if ($lastReoccurrence == 0) {
                $lastReoccurrence = $invoice['date_entered'];
            }

            $nextReoccurrence = strtotime(get_recurring_frequencies_durations($invoice['frequency']), $lastReoccurrence);

            $this->db->where('id', $invoice['id'])->update($this->table, array('next_recur_date' => $nextReoccurrence));

            return $this->dispatch_return('invoice_next_reoccurrence_date', $nextReoccurrence);
        } else {
            return $this->dispatch_return('invoice_next_reoccurrence_date', 0);
        }
    }

    // ------------------------------------------------------------------------

    /**
     * Updates the given invoice.
     *
     * @access	public
     * @param	string	The unique id of the invoice
     * @param	array	The input array
     * @return	string	The unique id of the invoice
     */
    public function update($unique_id, $input, $files = array()) {

        if (!$this->validate($input)) {
            return FALSE;
        }
        if ($input['type'] != 'ESTIMATE') {
            array_pop($this->validate);
        }
        array_pop($this->validate);

        $CI = &get_instance();
        $CI->load->model('invoices/partial_payments_m', 'ppm');
        $amount = str_replace(array(',', ' '), '', $input['amount']);

        $line_items = $this->build_invoice_rows_from_input(isset($input['invoice_item']) ? $input['invoice_item'] : $input['items'], isset($input['invoice_item']));
        $validate_invoice_total = $this->calculate_totals_from_line_items($input['type'], $amount, $line_items, 'total_with_tax');
        if (isset($input['partial-amount'])) {
            # We check that partial payments were entered. If not, there's no need to validate them.
            $result = $CI->ppm->validate_partials($validate_invoice_total, !empty($input['is_recurring']), $input['partial-amount'], $input['partial-is_percentage'], 'insert');
        }
        if (!$result) {
            $this->form_validation->_error_array['amount'] = lang('partial:wrongtotal');
            return FALSE;
        }

        $amount = $this->update_invoice_rows($unique_id, $line_items);

        $CI = &get_instance();
        $CI->load->model('files/files_m');
        $upload_result = $CI->files_m->verify_uploads($files);
        if ($upload_result === NOT_ALLOWED) {
            $this->form_validation->_error_array['amount'] = __('global:upload_not_allowed');
            return FALSE;
        }

        if (!isset($input['is_recurring'])) {
            # Let's check the invoice.
            $result = $this->db->get_where('invoices', array('unique_id' => $unique_id))->row_array();
            $input['is_recurring'] = $result['is_recurring'];
            $input['frequency'] = $result['frequency'];
            $input['send_x_days_before'] = $result['send_x_days_before'];
            $input['auto_send'] = $result['auto_send'];
            $input['recur_id'] = $result['recur_id'];
        }

        // If this is a recurring invoice with no history, start it here
        if (!empty($input['is_recurring']) AND empty($input['recur_id'])) {
            $this->db->set('recur_id', 'id', FALSE);
        }

        $data = array(
            'client_id' => $input['client_id'],
            'amount' => $amount,
            'due_date' => strtotime($input['due_date']),
            'invoice_number' => $input['invoice_number'],
            'notes' => $input['notes'],
            'description' => $input['description'],
            'type' => $input['type'],
            'is_paid' => !empty($input['is_paid']),
            'payment_date' => (isset($input['is_paid']) AND $input['is_paid'] == '1') ? time() : 0,
            'is_viewable' => !empty($input['is_viewable']),
            'is_recurring' => !empty($input['is_recurring']),
            'frequency' => $input['frequency'],
            'send_x_days_before' => isset($input['send_x_days_before']) ? (($input['send_x_days_before'] >= 0) ? $input['send_x_days_before'] : 7) : 7,
            'auto_send' => (!empty($input['is_recurring']) AND ! empty($input['auto_send'])),
            'project_id' => (isset($input['project_id']) ? (int) $input['project_id'] : 0),
        );

        if ($input['date_entered'] > 0) {
            $data['date_entered'] = read_date_picker($input['date_entered']);
        }

        $this->db->where('unique_id', $unique_id)->set($data)->update($this->table);

        $this->getNextInvoiceReoccurrenceDate($this->getIdByUniqueId($unique_id));

        # Partial Payments. Let's make sure the amounts work properly AFTER creating the invoice (so we can use getInvoiceTotalAmount()). Shall we?

        if ($input['type'] == 'DETAILED') {
            $result = $CI->ppm->processInput($unique_id, $_POST['partial-amount'], $_POST['partial-is_percentage'], $_POST['partial-due_date'], $_POST['partial-notes'], isset($_POST['partial-is_paid']) ? $_POST['partial-is_paid'] : array(), $validate_invoice_total);

            if ($result === 'WRONG_TOTAL') {
                $this->form_validation->_error_array['amount'] = lang('partial:wrongtotalbutsaved');
                return FALSE;
            } elseif (!$result) {
                $this->form_validation->_error_array['amount'] = lang('partial:problemsavingbutsaved');
                return FALSE;
            }
        } else {
            $CI = &get_instance();
            $CI->load->model('invoices/partial_payments_m', 'ppm');
            $CI->ppm->removePartialPayments($unique_id);
        }

        $this->fixInvoiceRecord($unique_id);
        return $unique_id;
    }

    // ------------------------------------------------------------------------

    /**
     * Updates the given invoice with no validation.
     *
     * @access	public
     * @param	string	The unique id of the invoice
     * @param	array	The array of items
     * @return	mixed
     */
    public function update_simple($unique_id, $data) {
        return $this->db->where('unique_id', $unique_id)->update($this->table, $data);
    }

    // ------------------------------------------------------------------------

    function build_invoice_rows_from_input($items, $html = true) {
        if ($html === TRUE) {
            $items_array = array();

            for ($i = 0; $i < count($items['name']); $i++) {
                $qty = isset($items['qty'][$i]) ? $items['qty'][$i] : 0;

                if (!isset($items['tax_id'][$i])) {
                    $items['tax_id'][$i] = array(0);
                }

                if (!is_array($items['tax_id'][$i])) {
                    $items['tax_id'][$i] = array($items['tax_id'][$i]);
                }

                $tax_ids = array();
                foreach ($items['tax_id'][$i] as $value) {
                    if ($value > 0) {
                        $value = (int) $value;
                        $tax_ids[$value] = $value;
                    }
                }

                $rate = (float) str_replace(',', '', $items['rate'][$i]);

                $item_type_id = explode('_', $items['item_type_id'][$i]);

                if (!isset($item_type_id[1])) {
                    $item_type_id[1] = 0;
                }

                if ($item_type_id[0] == 'MILESTONE') {
                    $item_type_id[0] = 'project_milestones';
                } elseif ($item_type_id[0] == 'TASK') {
                    $item_type_id[0] = 'project_tasks';
                } elseif ($item_type_id[0] == 'EXPENSE') {
                    $item_type_id[0] = 'project_expenses';
                } else {
                    $item_type_id[0] = '';
                }

                $discount = isset($items['discount'][$i]) ? $items['discount'][$i] : 0;
                if (stristr($discount, "%") !== false) {
                    $discount_is_percentage = 1;
                    $discount = str_ireplace("%", "", $discount);
                } else {
                    $discount_is_percentage = 0;
                }

                $total = $qty * $rate;

                # Remove item discount from item total.
                if ($discount_is_percentage) {
                    $total = $total - ($discount * $total / 100);
                } else {
                    $total = $total - $discount;
                }

                if (in_array((isset($items['type'][$i]) ? $items['type'][$i] : 'standard'), array("fixed_discount", "percentage_discount"))) {
                    $total = 0;
                }

                $items_array[] = array(
                    'name' => $items['name'][$i],
                    'description' => $items['description'][$i],
                    'qty' => $qty,
                    'rate' => $rate,
                    'item_time_entries' => explode(',', $items['item_time_entries'][$i]),
                    'item_type_table' => $item_type_id[0],
                    'item_type_id' => $item_type_id[1],
                    'tax_ids' => $tax_ids,
                    'discount' => $discount,
                    'discount_is_percentage' => $discount_is_percentage,
                    'total' => $total,
                    'sort' => $i,
                    'type' => isset($items['type'][$i]) ? $items['type'][$i] : 'standard'
                );
            }

            $items = $items_array;
        }

        return $items;
    }

    /**
     * Creates invoice item records for an invoice.
     *
     * $unique_id is the Unique ID of an invoice.
     *
     * $items is an array in the format given by $this->build_invoice_rows_from_input().
     *
     * @param string $unique_id
     * @param array $items
     * @return float
     */
    public function insert_invoice_rows($unique_id, $items) {
        $CI = get_instance();
        $CI->load->model('projects/project_expense_m');
        $CI->load->model('projects/project_time_m');

        $this->db->trans_start();
        $amount = 0;

        $i = 0;
        foreach ($items as $item) {
            unset($item['id']);

            $item['unique_id'] = $unique_id;

            if (isset($item['tax_total'])) {
                unset($item['tax_total']);
            }

            if (!isset($item['item_time_entries'])) {
                $item['item_time_entries'] = array();
            }

            $time_entry_ids = $item['item_time_entries'];
            unset($item['item_time_entries']);

            $tax_ids = $item['tax_ids'];
            unset($item['tax_ids']);

            $this->db->insert($this->rows_table, $item);

            $row_id = $this->db->insert_id();

            $this->store_taxes($row_id, $tax_ids);

            $CI->project_time_m->mark_as_billed($row_id, $time_entry_ids);

            if (!isset($item['item_type_table'])) {
                $item['item_type_table'] = "";
            }

            if ($item['item_type_table'] == 'project_expenses') {
                $CI->project_expense_m->mark_as_billed($row_id, $item['item_type_id']);
            }

            // Add this item total to the invoice amount
            $amount += $item['total'];

            ++$i;
        }

        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
            return FALSE;
        }

        $this->db->trans_commit();

        return $amount;
    }

    function store_taxes($item_id, $tax_ids) {
        if (is_numeric($tax_ids)) {
            $tax_ids = array($tax_ids);
        }

        $insert_batch = array();
        foreach ($tax_ids as $id) {
            if ($id > 0) {
                $insert_batch[] = array(
                    "tax_id" => $id,
                    "invoice_row_id" => $item_id,
                );
            }
        }

        $this->db->where("invoice_row_id", $item_id)->delete("invoice_rows_taxes");

        if (count($insert_batch)) {
            $this->db->insert_batch("invoice_rows_taxes", $insert_batch);
        }
    }

    // ------------------------------------------------------------------------

    /**
     * Updates the invoice rows.
     *
     * @access	public
     * @param	string	The unique id of the invoice
     * @param	array	The array of items
     * @return	int		The total of the items
     */
    public function update_invoice_rows($unique_id, $items) {
        $CI = get_instance();
        $CI->load->model('projects/project_expense_m');
        $CI->load->model('projects/project_time_m');
        $row_ids = $this->getRowIdsByUniqueId($unique_id);
        $CI->project_time_m->mark_as_unbilled($row_ids);
        $CI->project_expense_m->mark_as_unbilled($row_ids);
        $this->db->where(array('unique_id' => $unique_id))->delete($this->rows_table);
        return $this->insert_invoice_rows($unique_id, $items);
    }

    // ------------------------------------------------------------------------

    /**
     * Deletes an invoice by unique id
     *
     * @access	public
     * @param	string	The unique id of the invoice
     * @return	void
     */
    public function delete($unique_id) {
        $CI = get_instance();
        $CI->load->model('projects/project_expense_m');
        $CI->load->model('projects/project_time_m');
        $row_ids = $this->getRowIdsByUniqueId($unique_id);
        $CI->project_time_m->mark_as_unbilled($row_ids);
        $CI->project_expense_m->mark_as_unbilled($row_ids);

        $buffer = $this->db->select('id, type')->where('unique_id', $unique_id)->get($this->table)->row_array();
        if (isset($buffer['type']) and ! empty($buffer['type'])) {
            if ($buffer['type'] == 'ESTIMATE') {
                # It's an estimate, delete proposal sections that use it.
                $CI = &get_instance();
                $CI->load->model('proposals/proposals_m');
                $CI->proposals_m->deleteEstimateSections($buffer['id']);
            }

            $this->db->where('unique_id', $unique_id)->delete($this->table);
            $this->db->where('unique_id', $unique_id)->delete($this->rows_table);
            $CI = &get_instance();
            $CI->load->model('invoices/partial_payments_m', 'ppm');
            $CI->ppm->deleteInvoicePartialPayments($unique_id);

            $this->db->where('recur_id', $buffer['id'])->update($this->table, array(
                'recur_id' => 0,
                'is_recurring' => 0,
                'next_recur_date' => 0,
                'auto_send' => 0
            ));
        }
    }

    // ------------------------------------------------------------------------

    /**
     * Deletes all the invoices for a client
     *
     * @access	public
     * @param	int		The client id
     * @return	void
     */
    public function delete_by_client_id($client_id) {
        $invoices = $this->db->where('client_id', $client_id)->get($this->table);

        foreach ($invoices->result() as $invoice) {
            $this->delete($invoice->unique_id);
        }
    }

    // ------------------------------------------------------------------------

    /**
     * Updates a payment by the payment hash
     *
     * @access	public
     * @param	string	The hash to update
     * @param	array	The array of data to update
     * @return	void
     */
    public function update_by_hash($hash, $data) {
        return $this->db->where('payment_hash', $hash)->set($data)->update($this->table);
    }

    /**
     * Generates the unique id for an invoice
     *
     * @access	private
     * @return	string
     */
    public function _generate_unique_id() {

        static $unique_ids = null;

        if ($unique_ids === null) {
            $buffer = $this->db->select('unique_id')->get($this->table)->result_array();
            $unique_ids = array();

            foreach ($buffer as $row) {
                $unique_ids[$row['unique_id']] = $row['unique_id'];
            }
        }

        $this->load->helper('string');

        $valid = false;

        while ($valid === false) {
            $unique_id = random_string('alnum', 8);
            if (!isset($unique_ids[$unique_id])) {
                $valid = true;

                # Add this unique ID to list of IDs, because it'll be created.
                $unique_ids[$unique_id] = $unique_id;
            }
        }

        return $this->dispatch_return('invoice_unique_id_generated', $unique_id);
    }

    /**
     * Generates an invoice number
     *
     * @access	private
     * @return	string
     */
    public function _generate_invoice_number($number = null, $type = 'DETAILED') {
        $this->load->helper('string');

        if ($number === null) {
            # Generate invoice number if there are listeners.
            if (Events::has_listeners('generate_number')) {
                $number = get_instance()->dispatch_return('generate_number', array('type' => $type));
                if (is_array($number)) {
                    # No number was custom generated, return to null.
                    $number = null;
                }
            }
        }

        if (!empty($number)) {
            $this->db->where('type', $type);
            if ($this->db->where('invoice_number', $number)->count_all_results($this->table) == 0) {
                return $number;
            }
        }

        $valid = FALSE;

        $invoices_table = $this->db->dbprefix($this->table);

        $result = $this->db->query("select invoice_number from $invoices_table where type = " . $this->db->escape($type) . " order by date_entered asc")->result_array();

        $numbers = array();

        foreach ($result as $row) {
            $numbers[] = $row['invoice_number'];
        }


        $custom_invoice_number = $this->dispatch_return('before_invoice_number_generated', FALSE);

        if ($custom_invoice_number !== FALSE) {
            //custom invoice number count
            $cin_count = $this->db->like('invoice_number', $custom_invoice_number)->count_all_results($this->table) + 1;
            //return the result for a custom invoice
            return $this->dispatch_return('invoice_number_generated', $cin_count);
        }

        $increase_number = function($number) {
            $matches = array();

            if (preg_match("/([0-9]+)$/", $number, $matches)) {
                # Ends with a number; increment the number by itself.
                # This fixes a bug where incrementing "INVOICE-9" would lead to "INVOICE-0", instead of "INVOICE-10", as expected.

                $matched_number = $matches[1];
                $number_without_matched = substr($number, 0, -strlen($matched_number));
                $matched_number++;
                $number = $number_without_matched . $matched_number;
            } else {
                # Does not end with a number. Give it to ++, as it can increment things like letters and whatnot.
                $original_number = $number;
                $number++;

                if ($original_number == $number) {
                    # Is not incrementable; append "2" to it, so that it'll become appendable.
                    $number = "$number 2";
                }
            }

            return $number;
        };

        if (count($numbers) > 0) {
            $invoice_number = end($numbers);

            # If $invoice_number had spaces, $invoice_number++ would do nothing, so we trim them.
            $invoice_number = trim($invoice_number);

            $invoice_number = $increase_number($invoice_number);
        } else {
            $invoice_number = 1;
        }

        while ($valid === FALSE) {
            if (!in_array($invoice_number, $numbers) and $this->db->where('type', $type)->where('invoice_number', $invoice_number)->count_all_results($this->table) == 0) {
                $valid = TRUE;
            } else {
                $old = $invoice_number;
                $invoice_number = $increase_number($invoice_number);
                if ($old == $invoice_number) {
                    # The number is not changing!
                    throw new Exception("The number '$invoice_number' could not be increased automatically.");
                }
            }
        }

        //fire off this event and return the data.
        return $this->dispatch_return('invoice_number_generated', $invoice_number);
    }

}

/* End of file: invoices_m.php */