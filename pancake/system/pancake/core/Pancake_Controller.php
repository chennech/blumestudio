<?php

use Pancake\Navigation;

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
 * The admin and public base controllers extend this library
 *
 * @subpackage	Controllers
 */
class Pancake_Controller extends CI_Controller {

    /**
     * @var array	An array of methods to be secured by login
     */
    protected $secured_methods = array();

    // ------------------------------------------------------------------------

    /**
     * The construct loads sets up items needed application wide.
     *
     * @access	public
     * @return	void
     */
    public function __construct()
	{
		global $post_buffer;

		parent::__construct();
                
                # This is here because it somehow makes all strict errors appear.
                # Don't. Even. Ask.
                @$undefined_var++;
                
                # This is here so that any strict errors that these cause appear, thanks to the above statement.
                $this->load->library(array('PAN', 'settings/settings', 'currency', 'template', 'asset', 'search','events'));
                $this->load->helper(array('form', 'date', 'text', 'ion_auth', 'pancake_notifications', 'pancake', 'pancake_logger', 'debug', 'pancake_assignments', 'typography'));
                
                // Disable query logging, which can cause memory_limit fatal errors when trying
		// to perform large updates. If you need the queries to show up in the profiler,
		// set this to TRUE.
		$this->db->save_queries = IS_PROFILING || IS_DEBUGGING;
                $this->output->enable_profiler(IS_PROFILING);

		$this->method = $this->router->fetch_method();

		// Migrate DB to the latest version
		$this->load->library('migration');
		$this->load->model('upgrade/upgrade_m');

		$versions_without_migrations = array('1.0', '1.1', '1.1.1', '1.1.2', '1.1.3', '1.1.4', '2.0', '2.0.1', '2.0.2', '2.0.3');
		# 2.1.0 does not have migrations but can be migrated.

		if (!in_array(PAN::setting('version'), $versions_without_migrations)) {
                    $this->migration->latest();
                } else {
                    $this->upgrade_m->start();
                }

                # Get the latest version if it's been 12 hours since the last time.
		# Automatically update Pancake, if the settings are set to that.
		$this->load->model('upgrade/update_system_m', 'update');
		if ($this->method != 'no_internet_access')
		{
		    $this->update->get_latest_version();
		}
		# If Pancake was just automatically updated, the update system will force a refresh.
		# So by the time it gets here, the NEW Pancake will be running, and the migrations will have run.

                require_once APPPATH . 'libraries/Mustache/Autoloader.php';
                Mustache_Autoloader::register();
                $this->mustache = new Mustache_Engine(array(
                    "strict_callables" => true,
                    "helpers" => array(
                        "__" => function($value, Mustache_LambdaHelper $helper) {
                            return __($value);
                        },
                        "debug" => function($value) {
                            debug($value);
                        }
                    )
                ));

                # Force the user to use Pancake via HTTPS.
                if (Settings::get("always_https") and !IS_SSL) {
                    redirect(str_ireplace('http://', 'https://', site_url(uri_string())));
                }

                $this->load->library('session');
                $this->load->library('ion_auth');

		Currency::set(PAN::setting('currency'));

                # Load english first, to cache it.
                switch_language('english');

                switch_language(Settings::get('language'));

		$this->current_user = $this->template->current_user = $this->ion_auth->get_user();

		$this->load->model(array(
			'users/permission_m',
			'module_m',
		));

		// List available module permissions for this user
		$this->permissions = $this->current_user ? $this->permission_m->get_group($this->current_user->group_id) : array();

		// ! empty($this->permissions['users']['']);

		// Get meta data for the module
		$this->template->module_details = $this->module_details = $this->module_m->get( $this->router->fetch_module() );

                $this->template->title($this->_guess_title());

		log_message('debug', "Pancake_Controller Class Initialized");

		$_POST = $this->process_input($_POST, $post_buffer);
		unset($post_buffer);

                $this->setupNavbar();
                $this->setupQuickLinks();

                $this->load->library('plugins');
                $this->plugins->load_all();

                if (isset($_SERVER['QUERY_STRING']) and !empty($_SERVER['QUERY_STRING'])) {
                    if (empty($_GET)) {
                        # $_GET (and thus, $_REQUEST as well) is getting screwed by .htaccess, fill it up with the right data:
                        parse_str($_SERVER['QUERY_STRING'], $_GET);
                        $_REQUEST = $_REQUEST + $_GET;
                    }
                }

    }

	public function process_input($post, $post_buffer)
	{
		$return = array();

		foreach ($post as $key => $item)
		{
		    if (is_array($item))
			{
				$item = $this->process_input($item, $post_buffer[$key]);
		    }
			else
			{
				if (function_exists('get_magic_quotes_gpc') AND get_magic_quotes_gpc())
				{
				    $post_buffer[$key] = stripslashes($post_buffer[$key]);
				}

				if (strpos($post_buffer[$key], "\r") !== FALSE)
				{
				    $post_buffer[$key] = str_replace(array("\r\n", "\r", "\r\n\n"), PHP_EOL, $post_buffer[$key]);
				}

				$item = $post_buffer[$key];
		    }

		    $return[$key] = $item;
		}

		return $return;
    }

    /**
     * Fills the navbar with all of Pancake's links, labels and dividers.
     *
     * This is the place where you should add new links to the navbar, using the Navigation API.
     */
    protected function setupNavbar() {
        if ($this->current_user) {
            $uri_string = uri_string();

            $this->load->model('projects/project_m');
            $this->load->model('projects/project_time_m');
            $this->load->model('projects/project_timers_m', 'ptm');

            $project_nav_timers = $this->project_m->get_navbar_timers();

            $timers = $this->ptm->get_running_timers();
            $this->template->timers = $timers;

            if (count($project_nav_timers) > 0 and can_for_any_client('read', 'project_tasks')) {

                Navigation::registerNavbarLink("#timers", "global:timers");

                Navigation::registerNavbarLink("admin/projects/app", "global:timer_app", "#timers");
                Navigation::setClass("admin/projects/app", "open-timer-app");

                Navigation::registerNavbarLink("admin/timesheets", "global:timesheets", "#timers");



                $timers_set = array();

                if (count($timers) > 0) {
                    Navigation::setBadge("#timers", count($timers));
                    Navigation::registerNavbarLabel("tasks:timers_running", "#timers");
                    foreach ($timers as $task_id => $task) {
                        $task['id'] = $task['task_id'];
                        $task_id = "#timers-task-" . $task['id'];
                        Navigation::registerNavbarLink($task_id, $task['project_name'] . " &ndash; " . $task['task_name'], "#timers");
                        Navigation::registerNavbarLink("#timers-timer-" . $task['id'], 'global:stop_timer', $task_id);
                        Navigation::setClass("#timers-timer-" . $task['id'], "timer-button stop");
                        Navigation::setContainerClass("#timers-timer-" . $task['id'], "timer navtimer");
                        Navigation::setContainerDataAttributes("#timers-timer-" . $task['id'], get_timer_attrs($timers, $task['id']));
                        $timers_set[] = $task['id'];
                    }
                    Navigation::registerDivider("#timers");
                }


                Navigation::registerNavbarLabel("global:projects", "#timers");

                $i = 0;
                foreach ($project_nav_timers as $project) {
                    if ($i == 10) {
                        break;
                    }

                    $project_id = "#timers-project-" . $project->id;
                    Navigation::registerNavbarLink($project_id, $project->name, "#timers");
                    $sub_i = 0;
                    foreach ($project->tasks as $task) {
                        if ($sub_i == 10) {
                            break;
                        }

                        $task_id = $task['id'];
                        if (in_array($task_id, $timers_set)) {
                            continue;
                        }

                        $task_url_id = "#timers-task-" . $task_id;
                        Navigation::registerNavbarLink($task_url_id, $task['name'], $project_id);
                        Navigation::registerNavbarLink("#timers-timer-" . $task_id, 'global:start_timer', $task_url_id);
                        Navigation::setClass("#timers-timer-" . $task_id, "timer-button play");
                        Navigation::setContainerClass("#timers-timer-" . $task_id, "timer navtimer");
                        Navigation::setContainerDataAttributes("#timers-timer-" . $task_id, get_timer_attrs($timers, $task_id));
                        $sub_i++;
                    }

                    if (count($project->tasks) != $sub_i) {
                        Navigation::registerNavbarLabel(__("global:tasks_ommitted", array((count($project->tasks) - $sub_i))), $project_id);
                    }

                    $i++;
                }

                if (count($project_nav_timers) != $i) {
                    Navigation::registerNavbarLabel(__("global:projects_ommitted", array((count($project_nav_timers) - $i))), "#timers");
                }

            }

            $is_estimate_url = (stripos($uri_string, "admin/invoices/estimates") !== false or stripos($uri_string, "admin/estimates") !== false);
            $is_invoice_url = (stripos($uri_string, "admin/invoices") !== false or stripos($uri_string, "admin/items") !== false);
            $is_credit_notes_url = (stripos($uri_string, "admin/invoices/credit_notes") !== false or stripos($uri_string, "admin/credit_notes") !== false);

            if (can_for_any_client('read', 'invoices')) {
                Navigation::registerNavbarLink("#invoices", "global:invoices");

                if (can_for_any_client('create', 'invoices')) {
                    Navigation::registerNavbarLink("admin/invoices/create", "global:createinvoice", "#invoices");
                }

                Navigation::registerNavbarLink("admin/invoices/all", "global:view_all", "#invoices");
                Navigation::setBadge("admin/invoices/all", get_count("all"));

                Navigation::registerNavbarLink("admin/invoices/paid", "global:paid", "#invoices");
                Navigation::setBadge("admin/invoices/paid", get_count("paid"));

                Navigation::registerNavbarLink("admin/invoices/all_unpaid", "global:unpaid", "#invoices");
                Navigation::setBadge("admin/invoices/all_unpaid", get_count("unpaid"));

                Navigation::registerNavbarLink("admin/invoices/overdue", "global:overdue", "#invoices");
                Navigation::setBadge("admin/invoices/overdue", get_count("overdue"));

                Navigation::registerNavbarLink("admin/invoices/unpaid", "global:sentbutunpaid", "#invoices");
                Navigation::setBadge("admin/invoices/unpaid", get_count("sent_but_unpaid"));

                Navigation::registerNavbarLink("admin/invoices/unsent", "global:unsent", "#invoices");
                Navigation::setBadge("admin/invoices/unsent", get_count("unsent"));

                Navigation::registerNavbarLink("admin/invoices/recurring", "global:recurring", "#invoices");
                Navigation::setBadge("admin/invoices/recurring", get_count("recurring"));


                if ($is_invoice_url and !$is_estimate_url) {
                    Navigation::setContainerClass("#invoices", "active");
                }

            }

            if (is_admin()) {
                Navigation::registerNavbarLink("admin/invoices/reminders", "reminders:reminders", "#invoices");
                Navigation::registerNavbarLink("admin/items", "global:reusableinvoiceitems", "#invoices");
            }

            if (can_for_any_client('read', 'estimates')) {
                Navigation::registerNavbarLink("#estimates", "global:estimates");

                if (can_for_any_client('create', 'estimates')) {
                    Navigation::registerNavbarLink("admin/estimates/create", "estimates:create", "#estimates");
                }

                Navigation::registerNavbarLink("admin/estimates/estimates", "global:view_all", "#estimates");
                Navigation::setBadge("admin/estimates/estimates", get_count("estimates"));

                Navigation::registerNavbarLink("admin/estimates/accepted", "global:accepted", "#estimates");
                Navigation::setBadge("admin/estimates/accepted", get_count("accepted"));

                Navigation::registerNavbarLink("admin/estimates/rejected", "global:rejected", "#estimates");
                Navigation::setBadge("admin/estimates/rejected", get_count("rejected"));

                Navigation::registerNavbarLink("admin/estimates/unanswered", "global:unanswered", "#estimates");
                Navigation::setBadge("admin/estimates/unanswered", get_count("unanswered"));
                
                Navigation::registerNavbarLink("admin/estimates/estimates_unsent", "global:estimates_unsent", "#estimates");
                Navigation::setBadge("admin/estimates/estimates_unsent", get_count("estimates_unsent"));


                if ($is_estimate_url) {
                    Navigation::setContainerClass("#estimates", "active");
                }
            }

            if (can_for_any_client('read', array('projects', 'project_tasks'))) {
                Navigation::registerNavbarLink("admin/projects", "global:projects");

                if (stripos($uri_string, "admin/projects") !== false) {
                    Navigation::setContainerClass("admin/projects", "active");
                }

            }

            if (can_for_any_client('read', 'project_expenses')) {
                Navigation::registerNavbarLink("#expenses", "expenses:expenses");
                Navigation::registerNavbarLink("admin/expenses/index", "global:view_all", "#expenses");
                Navigation::registerNavbarLink("admin/expenses/suppliers", "expenses:suppliers", "#expenses");
                Navigation::registerNavbarLink("admin/expenses/categories", "expenses:categories", "#expenses");

                if (stripos($uri_string, "admin/expenses") !== false) {
                    Navigation::setContainerClass("#expenses", "active");
                }

            }

            if (can_for_any_client('read', 'proposals')) {
                Navigation::registerNavbarLink("#proposals", "global:proposals");

                Navigation::registerNavbarLink("admin/proposals/all", "global:view_all", "#proposals");
                Navigation::setBadge("admin/proposals/all", get_count("proposals"));

                Navigation::registerNavbarLink("admin/proposals/accepted", "global:accepted", "#proposals");
                Navigation::setBadge("admin/proposals/accepted", get_count("proposals_accepted"));

                Navigation::registerNavbarLink("admin/proposals/rejected", "global:rejected", "#proposals");
                Navigation::setBadge("admin/proposals/rejected", get_count("proposals_rejected"));

                Navigation::registerNavbarLink("admin/proposals/unanswered", "global:unanswered", "#proposals");
                Navigation::setBadge("admin/proposals/unanswered", get_count("proposals_unanswered"));

                if (stripos($uri_string, "admin/proposals") !== false) {
                    Navigation::setContainerClass("#proposals", "active");
                }
            }

            if (can_for_any_client('read', 'tickets')) {
                Navigation::registerNavbarLink("admin/tickets", "global:tickets");
                if (stripos($uri_string, "admin/tickets") !== false) {
                    Navigation::setContainerClass("admin/tickets", "active");
                }
            }

            if (can_for_any_client('read', 'invoices')) {
                Navigation::registerNavbarLink("admin/reports", "global:reports");
                if (stripos($uri_string, "admin/reports") !== false) {
                    Navigation::setContainerClass("admin/reports", "active");
                }
            }

            if (can_for_any_client('read', 'clients')) {
                Navigation::registerNavbarLink("admin/clients", "global:clients");
                if (stripos($uri_string, "admin/clients") !== false) {
                    Navigation::setContainerClass("admin/clients", "active");
                }
            }

            if (is_admin()) {
                Navigation::registerNavbarLink("admin/users", "global:users");
                if (stripos($uri_string, "admin/users") !== false) {
                    Navigation::setContainerClass("admin/users", "active");
                }
            }

            if (is_admin()) {
                Navigation::registerNavbarLink("admin/credit_notes/credit_notes", "global:credit_notes");

                if ($is_credit_notes_url) {
                    Navigation::setContainerClass("admin/credit_notes/credit_notes", "active");
                }
            }
        }
    }

    protected function setupQuickLinks() {

        Navigation::registerQuickLinkOwner("admin/projects");
        if (can_for_any_client("create", "projects")) {
            Navigation::registerQuickLink("admin/projects/create", "projects:add", "admin/projects", "plus", "fire-ajax");
            Navigation::registerQuickLink("admin/projects/templates", "projects:createfromtemplate", "admin/projects", "plus", "fire-ajax");

            $this->load->model("projects/project_template_m");
            if ($this->project_template_m->count_all() > 0) {
                Navigation::registerQuickLink("admin/projects/delete_templates", "projects:delete_project_template", "admin/projects", "trash", "fire-ajax");
            }

        }

        Navigation::registerQuickLink("admin/projects/archived", "projects:archive", "admin/projects", "archive");

        Navigation::registerQuickLinkOwner("admin/clients");
        if (is_admin()) {
            Navigation::registerQuickLink("admin/clients/create/", "clients:add", "admin/clients", "plus");
        }

        Navigation::registerQuickLinkOwner("admin/clients/view", function($segments) {
            $id = $segments[4];

            if (can('create', $id, 'projects')) {
                Navigation::registerQuickLink("admin/projects/index/0/$id#create", "projects:add", "admin/clients/view", "plus");
            }

            if (can('create', $id, 'invoices')) {
                Navigation::registerQuickLink("admin/invoices/create/client/$id", "invoices:create", "admin/clients/view", "page-add");
            }

            if (can('create', $id, 'estimates')) {
                Navigation::registerQuickLink("admin/estimates/create/client/$id", "estimates:create", "admin/clients/view", "page-add");
            }

            if (is_admin()) {
                Navigation::registerQuickLink("admin/credit_notes/create/client/$id", "credit_notes:create", "admin/clients/view", "page-add");
            }

            if (is_admin()) {
                Navigation::registerQuickLink("admin/invoices/make_bulk_payment/$id", "invoices:make_bulk_payment", "admin/clients/view", "pricetag-multiple");
            }

            if (can('update', $id, 'clients', $id)) {
                Navigation::registerQuickLink("admin/clients/edit/$id", "clients:edit", "admin/clients/view", "pencil");
                Navigation::registerQuickLink("admin/clients/send_client_area_email/$id", "clients:send_client_area_email", "admin/clients/view", "mail");
            }

            if (can('delete', $id, 'clients', $id)) {
                Navigation::registerQuickLink("admin/clients/delete/$id", "clients:delete", "admin/clients/view", "trash");
            }
        });

        Navigation::registerQuickLinkOwner("admin/emails");
        Navigation::registerQuickLink("admin/emails/create", "emailtemplates:create_template", "admin/emails", "plus");
        Navigation::registerQuickLink("admin/invoices/reminders", "reminders:reminders", "admin/emails");

        Navigation::registerQuickLinkOwner("admin/invoices");
        if (can_for_any_client('create', 'invoices')) {
            Navigation::registerQuickLinkOwner("admin/invoices", function($segments) {
                switch ($segments[2]) {
                    case "credit_notes":
                        $module = "credit_notes";
                        break;
                    case "estimates":
                        $module = "estimates";
                        break;
                    default:
                        switch ($segments[3]) {
                            case "credit_notes":
                                $module = "credit_notes";
                                break;
                            case "estimates":
                                $module = "estimates";
                                break;
                            default:
                                $module = "invoices";
                                break;
                        }
                        break;
                }

                Navigation::registerQuickLink("admin/$module/create", "$module:create", "admin/invoices", "plus");
            });
        }

        Navigation::registerQuickLinkOwner("admin/users");
        Navigation::registerQuickLink("admin/users/create", "users:create_user", "admin/users", "plus", "fire-ajax");

        Navigation::registerQuickLinkOwner("admin/proposals");
        Navigation::registerQuickLink("admin/proposals/create", "proposals:newproposal", "admin/proposals", "plus", "fire-ajax");

        Navigation::registerQuickLinkOwner("admin/invoices/make_bulk_payment", function($segments) {
            Navigation::registerQuickLink("admin/clients/view/{$segments[4]}", "clients:view", "admin/invoices/make_bulk_payment", "eye");
        });

        Navigation::registerQuickLinkOwner("admin/invoices/reminders");
        Navigation::registerQuickLink("admin/emails/create", "emailtemplates:create_template", "admin/invoices/reminders", "plus");
        Navigation::registerQuickLink("admin/emails/all", "emailtemplates:manage", "admin/invoices/reminders");

        Navigation::registerQuickLinkOwner("admin/invoices/estimates");
        if (can_for_any_client('create', 'estimates')) {
            Navigation::registerQuickLink("admin/estimates/create", "estimates:create", "admin/invoices/estimates", "plus");
        }

        Navigation::registerQuickLinkOwner("admin/invoices/credit_notes");
        if (is_admin()) {
            Navigation::registerQuickLink("admin/credit_notes/create", "credit_notes:create", "admin/invoices/credit_notes", "plus");
        }

        Navigation::registerQuickLinkOwner("admin/invoices/created", function($segments) {
            $unique_id = $segments[4];
            $invoice = get_instance()->invoice_m->get($unique_id);
            $module = human_invoice_type($invoice['type']);

            Navigation::registerQuickLink("admin/$module/edit/$unique_id", "$module:edit", "admin/invoices/created", "pencil");
            Navigation::registerQuickLink($unique_id, "$module:preview", "admin/invoices/created", "eye");
        });

    }

    public function _guess_title($module_override = null) {
        $this->load->helper('inflector');
        $method = $this->router->fetch_method();
        $module = $module_override ? $module_override : $this->router->fetch_module();

        // Obviously no title, lets get making one
        $title_parts = array();

        // If the method is something other than index, use that
        if ($method != 'index' and $method != 'all') {
            $title_parts[] = $method;
        }

        // Is there a module? Make sure it is not named the same as the method or controller
        if (!empty($module) AND ! in_array($module, $title_parts)) {

            if ($module == "invoices") {
                $parts = explode("/", $this->uri->uri_string());
                $module = $parts[1];
            }

            $title_parts[] = $module;
        }

        $title_parts = array_reverse($title_parts);

        // Glue the title pieces together using the title separator setting
        $title = humanize(implode(' &raquo; ', $title_parts))." | ".Business::getBrandName();

        return $title;
    }

    /**
     * Dispatch any possible events and return the value.
     */
    public function dispatch_return($event, $value, $return_type = 'string'){
		return Events::has_listeners($event) ? Events::trigger($event, $value, $return_type) : $value;
	}
}

/* End of file: Pancake_Controller.php */