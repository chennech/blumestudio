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
 * The Item Model
 *
 * @subpackage	Models
 * @category	Items
 */
class Notification_m extends Pancake_Model {

    function get_latest_client_activity($x = 5) {
        $results = $this->db->where(array('client_id >' => 0))->order_by("created", "desc")->limit($x)->get($this->table)->result();
        return $this->process_results($results);
    }

    protected function process_results($results) {
        $allowed_task_ids = get_assigned_ids('project_tasks', 'read');
        $allowed_invoice_ids = get_assigned_ids('invoices', 'read');
        $allowed_estimate_ids = get_assigned_ids('estimates', 'read');
        $allowed_proposal_ids = get_assigned_ids('proposals', 'read');

        foreach ($results as $key => $notification) {
            if (empty($notification->message)) {
                # Default message.
                $message = "<div class='notification-text'>Notification of context {$notification->context} and action {$notification->action} has not yet been built.</div>";

                $CI = get_instance();

                if ($notification->user_id > 0) {
                    $CI->load->model('users/user_m');
                    $user = $CI->user_m->get_users_by_ids($notification->user_id);
                    $user = reset($user);
                }

                $date = '<span class="date">' . format_date($notification->created, true) . '</span>';

                switch ($notification->context) {
                    case Notify::CONTEXT_ESTIMATE:

                        if (!in_array($notification->context_id, $allowed_estimate_ids)) {
                            unset($results[$key]);
                            continue 2;
                        }

                        $CI->load->model('invoices/invoice_m');
                        $CI->load->model('clients/clients_m');
                        $id = $CI->invoice_m->getUniqueIdById($notification->context_id);
                        $estimate = $CI->invoice_m->get($id);
                        $client = $CI->clients_m->getById($estimate['client_id']);

                        switch ($notification->action) {
                            case Notify::ACTION_VIEWED:
                                $notification->dashboard_class = "activity-invoice-viewed";
                                $message = __('estimates:client_viewed', array('<div class="notification-text"><strong class="client-name">' . client_name($client) . "</strong>", site_url($estimate['unique_id']), $estimate['invoice_number'], $date . '</div>'));
                                break;
                            case Notify::ACTION_ACCEPTED:
                                $notification->dashboard_class = "activity-proposal-accept";
                                $message = __('estimates:client_accepted', array('<div class="notification-text"><strong class="client-name">' . client_name($client) . "</strong>", site_url($estimate['unique_id']), $estimate['invoice_number'], $date . '</div>'));
                                break;
                            case Notify::ACTION_REJECTED:
                                $notification->dashboard_class = "activity-proposal-reject";
                                $message = __('estimates:client_rejected', array('<div class="notification-text"><strong class="client-name">' . client_name($client) . "</strong>", site_url($estimate['unique_id']), $estimate['invoice_number'], $date . '</div>'));
                                break;
                        }
                        break;
                    case Notify::CONTEXT_CREDIT_NOTE:
                        $CI->load->model('invoices/invoice_m');
                        $CI->load->model('clients/clients_m');
                        $id = $CI->invoice_m->getUniqueIdById($notification->context_id);
                        $estimate = $CI->invoice_m->get($id);
                        $client = $CI->clients_m->getById($estimate['client_id']);

                        switch ($notification->action) {
                            case Notify::ACTION_VIEWED:
                                $notification->dashboard_class = "activity-invoice-viewed";
                                $message = __('credit_notes:client_viewed', array('<div class="notification-text"><strong class="client-name">' . client_name($client) . "</strong>", site_url($estimate['unique_id']), $estimate['invoice_number'], $date . '</div>'));
                                break;
                        }
                        break;
                    case Notify::CONTEXT_TASK:

                        if (!in_array($notification->context_id, $allowed_task_ids)) {
                            unset($results[$key]);
                            continue 2;
                        }

                        $CI->load->model('projects/project_task_m');
                        $task = $this->project_task_m->get_task_by_id($notification->context_id)->row_array();

                        switch ($notification->action) {
                            case Notify::ACTION_COMPLETED:
                                $notification->dashboard_class = "activity-proposal-accept";
                                $message = __('tasks:task_completed_by', array(
                                    '<img src="' . get_gravatar($user['email'], 60) . '"  /><div class="notification-text"><strong class="client-name">' . $user['first_name'] . "</strong>", site_url("admin/projects/view/" . $task['project_id']), $task['name'],
                                    $date . '</div>'
                                ));
                                break;
                        }
                        break;
                    case Notify::CONTEXT_INVOICE:

                        if (!in_array($notification->context_id, $allowed_invoice_ids)) {
                            unset($results[$key]);
                            continue 2;
                        }

                        $CI->load->model('invoices/invoice_m');
                        $CI->load->model('clients/clients_m');
                        $id = $CI->invoice_m->getUniqueIdById($notification->context_id);
                        $invoice = $CI->invoice_m->get($id);
                        $client = $CI->clients_m->getById($invoice['client_id']);

                        switch ($notification->action) {
                            case Notify::ACTION_VIEWED:
                                $notification->dashboard_class = "activity-invoice-viewed";
                                $message = __('invoices:client_viewed', array('<div class="notification-text"><strong class="client-name">' . client_name($client) . "</strong>", site_url($invoice['unique_id']), $invoice['invoice_number'], $date . '</div>'));
                                break;
                            case Notify::ACTION_PAID:
                                $notification->dashboard_class = "activity-invoice-paid";
                                $message = __('invoices:client_paid', array('<div class="notification-text"><strong class="client-name">' . client_name($client) . "</strong>", site_url($invoice['unique_id']), $invoice['invoice_number'], $date . '</div>'));
                                break;
                        }
                        break;
                    case Notify::CONTEXT_PROPOSAL:

                        if (!in_array($notification->context_id, $allowed_proposal_ids)) {
                            unset($results[$key]);
                            continue 2;
                        }

                        $CI->load->model('proposals/proposals_m');
                        $CI->load->model('clients/clients_m');
                        $proposal = $this->proposals_m->getAll(null, null, array('id' => $notification->context_id));
                        $proposal = (array) reset($proposal);
                        $client = $CI->clients_m->getById($proposal['client_id']);

                        switch ($notification->action) {
                            case Notify::ACTION_VIEWED:
                                $notification->dashboard_class = "activity-invoice-viewed";
                                $message = __('proposals:client_viewed', array('<div class="notification-text"><strong class="client-name">' . client_name($client) . "</strong>", site_url('proposal/' . $proposal['unique_id']), $proposal['proposal_number'], $date . '</div>'));
                                break;
                            case Notify::ACTION_ACCEPTED:
                                $notification->dashboard_class = "activity-proposal-accept";
                                $message = __('proposals:client_accepted', array('<div class="notification-text"><strong class="client-name">' . client_name($client) . "</strong>", site_url('proposal/' . $proposal['unique_id']), $proposal['proposal_number'], $date . '</div>'));
                                break;
                            case Notify::ACTION_REJECTED:
                                $notification->dashboard_class = "activity-proposal-reject";
                                $message = __('proposals:client_rejected', array('<div class="notification-text"><strong class="client-name">' . client_name($client) . "</strong>", site_url('proposal/' . $proposal['unique_id']), $proposal['proposal_number'], $date . '</div>'));
                                break;
                        }
                        break;
                }

                $results[$key]->message = $message;
                $results[$key]->dashboard_message = get_between($message, '<div class="notification-text">', '</div>');
            }
        }

        return $results;
    }

    function get_unseen() {
        $unseen = $this->get_many_by(array('seen' => false));
        return $this->process_results($unseen);
    }

}

class Notify {

    const CONTEXT_TASK = 'Task';
    const CONTEXT_INVOICE = 'Invoice';
    const CONTEXT_CREDIT_NOTE = 'Credit Note';
    const CONTEXT_ESTIMATE = 'Estimate';
    const CONTEXT_PROPOSAL = 'Proposal';
    const ACTION_VIEWED = 'viewed';
    const ACTION_COMPLETED = 'completed';
    const ACTION_PAID = 'paid';
    const ACTION_ACCEPTED = 'accepted';
    const ACTION_REJECTED = 'rejected';

    public static function client_viewed_invoice($invoice_id, $client_id) {
        Notify::send(self::CONTEXT_INVOICE, $invoice_id, self::ACTION_VIEWED, null, $client_id);
    }

    public static function client_paid_invoice($invoice_id, $client_id) {
        Notify::send(self::CONTEXT_INVOICE, $invoice_id, self::ACTION_PAID, null, $client_id);
    }

    public static function client_viewed_credit_note($credit_note_id, $client_id) {
        Notify::send(self::CONTEXT_CREDIT_NOTE, $credit_note_id, self::ACTION_VIEWED, null, $client_id);
    }

    public static function client_viewed_estimate($estimate_id, $client_id) {
        Notify::send(self::CONTEXT_ESTIMATE, $estimate_id, self::ACTION_VIEWED, null, $client_id);
    }

    public static function client_accepted_estimate($estimate_id, $client_id) {
        Notify::send(self::CONTEXT_ESTIMATE, $estimate_id, self::ACTION_ACCEPTED, null, $client_id);
    }

    public static function client_rejected_estimate($estimate_id, $client_id) {
        Notify::send(self::CONTEXT_ESTIMATE, $estimate_id, self::ACTION_REJECTED, null, $client_id);
    }

    public static function client_viewed_proposal($proposal_id, $client_id) {
        Notify::send(self::CONTEXT_PROPOSAL, $proposal_id, self::ACTION_VIEWED, null, $client_id);
    }

    public static function client_accepted_proposal($proposal_id, $client_id) {
        Notify::send(self::CONTEXT_PROPOSAL, $proposal_id, self::ACTION_ACCEPTED, null, $client_id);
    }

    public static function client_rejected_proposal($proposal_id, $client_id) {
        Notify::send(self::CONTEXT_PROPOSAL, $proposal_id, self::ACTION_REJECTED, null, $client_id);
    }

    public static function user_completed_task($task_id, $user_id) {
        Notify::send(self::CONTEXT_TASK, $task_id, self::ACTION_COMPLETED, $user_id);
    }

    public static function send($context, $context_id, $action = null, $user_id = null, $client_id = null) {
        $ci = & get_instance();

        // Do not create a notification if an unseen one already exists for that very same thing.

        $existing_notifications = $ci->db->where(array(
                    'context' => $context,
                    'context_id' => $context_id,
                    'action' => $action,
                    'user_id' => $user_id,
                    'client_id' => $client_id,
                    'seen' => 0
                ))->where("FROM_UNIXTIME(created, '%Y-%m-%d') = DATE_FORMAT(NOW(), '%Y-%m-%d')", null, false)->count_all_results('notifications');

        if ($existing_notifications > 0) {
            return;
        }

        $ci->notification_m->insert(array(
            'created' => time(),
            'context' => $context,
            'context_id' => $context_id,
            'action' => $action,
            'message' => "",
            'user_id' => $user_id,
            'client_id' => $client_id,
        ));
    }

}

/* End of file: item_m.php */