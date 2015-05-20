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
 * The admin controller for the dashboard
 *
 * @subpackage	Controllers
 * @category	Dashboard
 */
class Admin extends Admin_Controller {
    
    function __construct() {
        parent::__construct();
        $this->load->model('invoices/invoice_m');
        $this->load->model('proposals/proposals_m');
        $this->load->model('projects/project_m');
        $this->load->model('projects/project_task_m');
        $this->load->model('projects/project_expense_m');
        $this->load->model('projects/project_time_m');
        $this->load->model('clients/clients_m');
        $this->load->model('kitchen/kitchen_comment_m');
        $this->load->helper('array');
    }

    function backend_css() {
        header("Content-Type: text/css; charset=utf-8");
        echo Settings::get('backend_css');
    }

    function setup_js() {
        header("Content-Type: application/javascript; charset=utf-8");
        echo get_setup_js();
    }

    function backend_js() {
        header("Content-Type: application/javascript; charset=utf-8");
        echo Settings::get('backend_js');
    }

    /**
     * Outputs a nice dashboard for the user
     *
     * @access	public
     * @return	void
     */
    public function index() {
        $dashboard_items = 6;
        
        $this->template->expenses_sum = $this->project_expense_m->get_all_expenses_sum(Settings::fiscal_year_start());
        $this->load->model("invoices/partial_payments_m", "ppm");
        $this->template->paid = $this->ppm->get_paid_total_this_fiscal_year();
        $this->template->unpaid = $this->invoice_m->sent_but_unpaid_totals(null);
        $this->template->hours_worked = $this->project_time_m->get_all_hours_worked(Settings::fiscal_year_start());
        $this->template->overdue = $this->invoice_m->overdue_totals();
        $this->template->active_timers = $this->project_time_m->active_timer_count();
        $this->template->project_count = $this->project_m->count_all_projects();
        $this->template->client_count = $this->clients_m->count_all();
        // sous models
        $overdue_invoices = $this->invoice_m->get_all_overdue(null, 0, $dashboard_items, array('due_date' => 'ASC'));
        $almost_due_and_unseen = $this->invoice_m->get_all_unseen(7, $dashboard_items - count($overdue_invoices));
        $upcoming_invoices = array();

        foreach ($overdue_invoices as $invoice) {
            $due_date = $invoice->due_date == 0 ? PHP_INT_MAX : $invoice->due_date;
            $upcoming_invoices[$due_date.'-'.$invoice->id] = $invoice;
        }

        foreach ($almost_due_and_unseen as $invoice) {
            $due_date = $invoice->due_date == 0 ? PHP_INT_MAX : $invoice->due_date;
            $upcoming_invoices[$due_date.'-'.$invoice->id] = $invoice;
        }

        ksort($upcoming_invoices);
        $this->template->upcoming_invoices = $upcoming_invoices;
        $this->template->team_working_on = $this->project_task_m->get_team_status($this->current_user->id, $dashboard_items);
        $this->template->my_upcoming_tasks = $this->project_task_m->upcoming_tasks_for_user($this->current_user->id, $dashboard_items);
        
        $this->template->projects = $this->project_m->get_for_dashboard();
        $this->template->comments = $this->kitchen_comment_m->get_for_dashboard();

        $this->template->build('dashboard');
    }

    function all_comments() {
        $this->template->comments = $this->kitchen_comment_m->get_for_dashboard(PHP_INT_MAX);
        $this->template->view_all = false;
        $this->template->build('all_comments');
    }
    
    function all_client_activity() {
        $this->template->client_activity_x = PHP_INT_MAX;
        $this->template->view_all = false;
        $this->template->build('all_client_activity');
    }
    
    function all_team_activity() {
        $this->template->team_working_on = $this->project_task_m->get_team_status($this->current_user->id, PHP_INT_MAX);
        $this->template->view_all = false;
        $this->template->build('all_team_activity');
    }

}

/* End of file: admin.php */