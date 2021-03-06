<?php defined('BASEPATH') OR exit('No direct script access allowed');
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
 * The Client API controller
 *
 * @subpackage	Controllers
 * @category	API
 */
class Clients extends REST_Controller {

	/**
	 * Clients Editable Columns
	 *
	 * Used to specify the columns that can be passed for insert and update
	 * 
	 * @var array
	 */
	protected $clients_editable_columns = array(		
		'first_name',
		'last_name',
		'title',
		'email',
		'company',
		'address',
		'phone',
		'fax',
		'mobile',
		'website',
		'profile',
		'passphrase',
		/* 
		// Automatically Generated by the system.
		'unique_id',
		'id',
		'created',
		'modified'
		*/
	);

	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct();

		$this->load->model('clients/clients_m');
	}

	/**
	 * Get All Clients
	 *
	 * Parameters:
	 *  + limit = 5
	 *  + start = 0
	 *  + sort_by = email (default: id)
	 *  + sort_dir = asc (default: asc)
	 *
	 * @link   /api/1/clients   GET Request
	 */
	public function index_get()
	{
		$sort_by = $this->get('sort_by') !== false ? $this->get('sort_by') : 'id';
		$sort_dir = $this->get('sort_dir') !== false ? $this->get('sort_dir') : 'asc';
                $limit = $this->get('limit') === false ? PHP_INT_MAX : $this->get('limit');
                $offset = $this->get('start') === false ? 0 : $this->get('start');

		$clients = $this->clients_m
			->select('id, first_name, last_name, title, email, 
				IF(company != "", company, NULL) as company,
				IF(address != "", address, NULL) as address,
				IF(phone != "", phone, NULL) as phone,
				IF(fax != "", fax, NULL) as fax,
				IF(mobile != "", mobile, NULL) as mobile,
				IF(website != "", website, NULL) as website,
				IF(profile != "", profile, NULL) as profile,
				created, modified, unique_id, passphrase', FALSE)
			->order_by($sort_by, $sort_dir)
			->limit($limit, $offset)
			->get_all();
			
                $this->load->model("invoices/partial_payments_m", "ppm");

                foreach ($clients as &$client)
		{
			$client->id = (int) $client->id;
			$client->url = site_url(Settings::get('kitchen_route').'/'.$client->unique_id);
                        
                        $unpaidTotals = $this->ppm->getTotals($client->id);
                        $client->unpaid_total = $unpaidTotals['total'];
                        $paidTotals = $this->ppm->getTotals($client->id, true);
                        $client->paid_total = $paidTotals['total'];
                        $client->total = $client->unpaid_total + $client->paid_total;
                        $overdueTotals = $this->ppm->getTotals($client->id, 'OVERDUE');
                        $client->overdue_total = $overdueTotals['total'];
		}
		
		$count = count($clients);
		$this->response(array(
			'status' => true,
			'message' => "Found $count clients",
			'clients' => $clients,
			'count' => $count
		), 200);
	}

	/**
	 * Show Client
	 * 
	 * @link   /api/1/clients/show    GET Request
	 */
	public function show_get()
	{
		if ( ! $this->get('id'))
		{
			$err_msg = 'No id was provided.';
			$this->response(array('status' => false, 'message' => $err_msg, 'error_message' => $err_msg), 400);
		}
		
		$client = $this->clients_m
			->select('id, first_name, last_name, title, email, 
				IF(company != "", company, NULL) as company,
				IF(address != "", address, NULL) as address,
				IF(phone != "", phone, NULL) as phone,
				IF(fax != "", fax, NULL) as fax,
				IF(mobile != "", mobile, NULL) as mobile,
				IF(website != "", website, NULL) as website,
				IF(profile != "", profile, NULL) as profile,
				created, modified, unique_id, passphrase', FALSE)
			->get($this->get('id'));
		
		if (empty($client))	
		{
			$err_msg = 'This client could not be found.';
			$this->response(array('status' => false, 'message' => $err_msg, 'error_message' => $err_msg), 404);
		}
		else
		{
			$client->id = (int) $client->id;
			$this->response(array('status' => true, 'client' => $client), 200);
		}
	}
	
	/**
	 * New Client
	 * 
	 * @link   /api/1/clients/new    POST Request
	 */
	public function new_post()
	{
		if (empty($_POST))
		{
			$err_msg = 'No details were provided.';
			$this->response(array('status' => false, 'message' => $err_msg, 'error_message' => $err_msg), 400);
		}

		if (!$this->clients_m->validate($this->input->post()))
		{
			$err_msg = current($this->validation_errors());
			$this->response(array('status' => false, 'message' => $err_msg, 'error_message' => $err_msg), 400);
		}
		
		// Assign all client column values allowed that were passed
		$this->load->helper('array');
		$input = elements_exist($this->clients_editable_columns, $this->input->post());

		$now = time();
		$input['created'] = $now;
		$input['modified'] = $now;

		// Insert and skip validation as we've already done it
		$id = $this->clients_m->insert($input, TRUE);
		$this->response(array('status' => true, 'id' => $id, 'message' => sprintf('Client #%s has been created.', $id)), 200);
	}

	/**
	 * Edit Post
	 *
	 * The original documented endpoint.
	 * 
	 * @link   /api/1/clients/edit    POST Request
	 * @param  string   Numeric ID of client
	 */
	public function edit_post($id = null)
	{
		$this->update_post($id);
	}

	/**
	 * Update Post
	 *
	 * @deprecated This should stay for backward compatibility
	 * @link   /api/1/clients/update   POST Request
	 * @param  string   Numeric ID of client
	 */
	public function update_post($id = null)
	{
		if ( ! ($id or $id = $this->post('id')))
		{
			$err_msg = 'No id was provided.';
			$this->response(array('status' => false, 'message' => $err_msg, 'error_message' => $err_msg), 400);
		}
		
		if ( ! $client = $this->clients_m->get($id))
		{
			$err_msg = 'This client does not exist!';
			$this->response(array('status' => false, 'message' => $err_msg, 'error_message' => $err_msg), 404);
		}
		
		$this->load->helper('array');
		$update = elements_exist($this->clients_editable_columns, $this->input->post());
		// We pass the date instead of the timestamp because it isn't converted in update like in insert
		$update['modified'] = date('Y-m-d H:i:s');

		// Only accept client columns from the post
		if ($this->clients_m->update($id, $update))
		{
			$this->response(array('status' => true, 'message' => sprintf('Client #%d has been updated.', $id)), 200);
		}
		else
		{
			$err_msg = current($this->validation_errors());
			$this->response(array('status' => false, 'message' => $err_msg, 'error_message' => $err_msg), 400);
		}
	}
	
	/**
	 * Delete Client
	 *
	 * @link   /api/1/clients/delete   POST Request
	 * @param  string   Numeric ID of client
	 */
	public function delete_post($id = null)
	{
		if ( ! ($id or $id = $this->post('id')))
		{
			$err_msg = 'No id was provided.';
			$this->response(array('status' => false, 'message' => $err_msg, 'error_message' => $err_msg), 400);
		}
		
		if ( ! $client = $this->clients_m->get($id))
		{
			$err_msg = 'This client does not exist!';
			$this->response(array('status' => false, 'message' => $err_msg, 'error_message' => $err_msg), 404);
		}
		
		if ($this->clients_m->delete($id))
		{
			$this->response(array('status' => true, 'message' => sprintf('Client #%d has been deleted.', $id)), 200);
		}
		else
		{
			$err_msg = sprintf('Failed to delete client #%d.', $id);
			$this->response(array('status' => false, 'message' => $err_msg, 'error_message' => $err_msg), 500);
		}
	}

}