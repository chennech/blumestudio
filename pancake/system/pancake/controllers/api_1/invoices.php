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
 * The Invoice API controller
 *
 * @subpackage	Controllers
 * @category	API
 */
class Invoices extends REST_Controller {

	public function __construct()
	{
		parent::__construct();

		$this->load->model('invoices/invoice_m');
	}

	/**
	 * Get All Invoices
	 *
	 * Parameters:
	 *  + limit = 5
	 *  + start = 0
	 *  + sort_by = email (default: id)
	 *  + sort_dir = asc (default: asc)
	 *  + client_id = Clients ID
	 *
	 * @link   /api/1/invoices   GET Request
	 */
	public function index_get($type = null)
	{
		$this->list_get($type);
	}

	/**
	 * Get Paid Invoices
	 *
	 * @link   /api/1/invoices/paid   GET Request
	 */
	public function paid_get()
	{
		$this->list_get('paid');
	}

	/**
	 * Get Unpaid Invoices
	 *
	 * @link   /api/1/invoices/unpaid   GET Request
	 */
	public function unpaid_get()
	{
		$this->list_get('unpaid');
	}

	/**
	 * Get Overdue Invoices
	 *
	 * @link   /api/1/invoices/overdue   GET Request
	 */
	public function overdue_get()
	{
		$this->list_get('overdue');
	}

	/**
	 * Get Unsent Invoices
	 *
	 * @link   /api/1/invoices/unsent   GET Request
	 */
	public function unsent_get()
	{
		$this->list_get('unsent');
	}

	/**
	 * Get Estimates
	 *
	 * @link   /api/1/invoices/estimate   GET Request
	 */
	public function estimate_get()
	{
		$this->list_get('estimate');
	}

	/**
	 * Get Invoices
	 *
	 * This isn't really accessed directly and it is more beneficial to just
	 * utilize the quick methods above.
	 *
	 * This endpoint won't be documented, but will remain for backward compatability
	 *
	 * @link   /api/1/invoices/list[/$type]   GET Request
	 */
	public function list_get($type = null)
	{
		if ($this->get('limit') or $this->get('start'))
		{
			$this->invoice_m->limit($this->get('limit'), $this->get('start'));
		}

		if ($this->get('client_id'))
		{
			$this->invoice_m->where('client_id', $this->get('client_id'));
		}

		switch ($type)
		{
			case 'unsent':
				$this->invoice_m->where(array('invoices.last_sent' => 0, 'invoices.type !=' => 'ESTIMATE'));
			break;

			case 'paid':
				$this->invoice_m->where(array('invoices.is_paid' => 1, 'invoices.type !=' => 'ESTIMATE'));
			break;

			// Required to be sent
			case 'unpaid':
				$this->invoice_m->where(array(
					'invoices.is_paid' => 0,
					'invoices.last_sent !=' => 0,
					'invoices.type !=' => 'ESTIMATE'
				));
			break;

			// Required to be sent
			case 'overdue':
				$this->invoice_m->where(array(
					'invoices.is_paid' => 0,
					'invoices.last_sent !=' => 0,
					'due_date <' => time(),
					'invoices.type !=' => 'ESTIMATE'
				));
			break;

			case 'estimate':
				$this->invoice_m->where(array('invoices.type' => 'ESTIMATE'));
			break;

			// If getting all invoices, don't get estimates
			default:
				$this->invoice_m->where(array('invoices.type !=' => 'ESTIMATE'));
		}

		$sort_by = $this->get('sort_by') ? $this->get('sort_by') : 'invoices.id';
		$sort_dir = $this->get('sort_dir') ? $this->get('sort_dir') : 'asc';

		$invoices = $this->invoice_m->order_by($sort_by, $sort_dir)->get_all_for_api();

		$count = count($invoices);
		$invoice_type = (is_null($type)) ? "invoices" : ($type === 'estimate' ? "estimates" : "$type invoices");

		$this->response(array(
			'status' => true,
			'message' => "Found $count $invoice_type",
			'invoices' => $invoices,
			'count' => $count,
		), 200);
	}

        public function fetch_get($type = null) {
            $options = array(
                'include_totals' => !!$this->get('include_totals'),
                'include_partials' => !!$this->get('include_partials'),
                'return_object' => true,
                'type' => $type == 'estimates' ? 'estimates' : 'invoices',
                'offset' => $this->get('start') ? $this->get('start') : 0,
                'per_page' => $this->get('limit') ? $this->get('limit') : PHP_INT_MAX,
                'client_id' => $this->get('client_id') > 0 ? $this->get('client_id') : null,
                'order' => array($this->get('sort_by') ? $this->get('sort_by') : 'id' => $this->get('sort_dir') ? $this->get('sort_dir') : 'asc')
            );

            switch ($type) {
                case 'unsent':
                    $options['sent'] = false;
                    break;
                case 'paid':
                    $options['paid'] = true;
                    break;
                case 'unpaid':
                    $options['paid'] = false;
                    break;
                case 'overdue':
                    $options['overdue'] = true;
                    break;
            }

            $invoices = $this->invoice_m->flexible_get_all($options);

            $count = count($invoices);
            $this->response(array(
                'status' => true,
                'message' => "Found $count {$options['type']}",
                'invoices' => $invoices,
                'count' => $count,
                    ), 200);
        }

        /**
	 * Show Invoice
	 *
	 * Requires EITHER unique_id or id
	 *
	 * @link   /api/1/invoices/show   GET Request
	 */
	public function show_get()
	{
		if (!$this->get('unique_id') and !$this->get('id'))
		{
			$err_msg = 'No unique_id (or id) was provided.';
			$this->response(array('status' => false, 'message' => $err_msg, 'error_message' => $err_msg), 400);
		}

		// Get the Unique ID
		if ($this->get('unique_id')) {
		    $unique_id = $this->get('unique_id');
		} else {
		    $unique_id = $this->invoice_m->getUniqueIdById($this->get('id'));
		}

		// Make sure that the invoice is found
		if ( ! $unique_id OR ! $invoice = $this->invoice_m->get($unique_id))
		{
			$err_msg = 'This invoice could not be found';
			$this->response(array('status' => false, 'message' => $err_msg, 'error_message' => $err_msg), 404);
		}


		$this->response(array(
			'status' => true,
			'message' => 'Found Invoice #' . $invoice['id'],
			'invoice' => $invoice
		), 200);
	}

    function advanced_create_post() {
        if (empty($_POST)) {
            $err_msg = 'No details were provided.';
            $this->response(array('status' => false, 'message' => $err_msg, 'error_message' => $err_msg), 400);
        }

        $post = $this->input->post();
        $post['amount'] = 0; # This has been deprecated but is still around because of compatibility with the old SIMPLE invoices.

        if (!isset($post['is_paid'])) {
            $post['is_paid'] = false;
        }

        $this->load->model("settings/smart_csv_m");

        foreach ($post['items'] as $item_key => $item) {
            if (!isset($item['tax_ids'])) {
                $item['tax_ids'] = array();
            }

            foreach ($item['tax_ids'] as $key => $tax_id) {
                # Correct taxes.
                $this->smart_csv_m->process_tax_including_ids($tax_id);
                $item['tax_ids'][$key] = $tax_id;
            }

            $post['items'][$item_key] = $item;
        }

        $files = array(
            "name" => array(),
            "type" => array(),
            "tmp_name" => array(),
            "error" => array(),
            "size" => array(),
        );

        if (!file_exists(FCPATH."uploads/tmp")) {
            mkdir(FCPATH."uploads/tmp");
        }

        foreach ($post['files'] as $file) {
            $filename = $file['filename'];
            $contents = base64_decode($file['contents']);

            $full_filename = FCPATH."uploads/tmp/".uniqid().".".pathinfo($filename, PATHINFO_EXTENSION);
            file_put_contents($full_filename, $contents);

            $finfo = finfo_open();

            $files["name"][] = $filename;
            $files["type"][] = finfo_file($finfo, $full_filename, FILEINFO_MIME_TYPE);
            $files["tmp_name"][] = $full_filename;
            $files["error"][] = filesize($full_filename);
            $files["size"][] = $filename;
        }

        unset($post['files']);

        if ($unique_id = $this->invoice_m->insert($post, $files)) {
            foreach ($files['tmp_name'] as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }

            $this->response(array('status' => true, 'unique_id' => $unique_id, 'message' => sprintf('Invoice #%s has been created.', $unique_id)), 200);
        } else {
            foreach ($files['tmp_name'] as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }

            $err_msg = current($this->validation_errors());
            $this->response(array('status' => false, 'message' => $err_msg, 'error_message' => $err_msg), 400);
        }
    }

	/**
	 * New Invoice
	 *
	 * @link   /api/1/invoices/new   POST Request
	 */
	public function new_post()
	{
		if (empty($_POST))
		{
			$err_msg = 'No details were provided.';
			$this->response(array('status' => false, 'message' => $err_msg, 'error_message' => $err_msg), 400);
		}

		$post = $this->input->post();
		$items = array();

		if ($this->post('project_id'))
		{
			$this->load->model('projects/project_m');
			$this->load->model('projects/project_task_m');

			if ( ! $project = $this->project_m->get_project_by_id($this->post('project_id')))
			{
				$err_msg = 'This project could not be found.';
				$this->response(array('status' => false, 'message' => $err_msg, 'error_message' => $err_msg), 404);
			}

			// Dan likes weird returns
			$project = $project->row();
			$tasks = $this->project_task_m->get_tasks_by_project($project->id);

			if ($tasks && is_array($tasks))
			{
				foreach ($tasks as $task)
				{
					if ( ! isset($task['name'], $task['tracked_hours'], $task['rate']))
					{
						continue;
					}

					$items[] = array(
						'name' => $task['name'],
						'description' => isset($task['notes']) ? $task['notes'] : '',
						'qty' => $task['tracked_hours'],
						'rate' => $task['rate'],
						'tax_id' => 0,
						'total' => round($task['tracked_hours'] * $task['rate'], 2),
					);
				}
			}

			else
			{
				$err_msg = 'This project has no tasks, so no invoice can be made.';
				$this->response(array('status' => false, 'message' => $err_msg, 'error_message' => $err_msg), 400);
			}
		}

		if ($post_items = $this->input->post('items'))
		{
			if ( ! is_array($post_items))
			{
				$err_msg = 'Items must be an array';
				$this->response(array('status' => false, 'message' => $err_msg, 'error_message' => $err_msg), 400);
			}

			foreach ($post_items as $i => $item)
			{
				if ( ! isset($item['name'], $item['rate'], $item['quantity']))
				{
					// Get the keys they are missing
					$item_required = array_flip(array('name', 'rate', 'quantity'));
					$diff = array_flip(array_diff_key($item_required, $item));

					// Sort is here so that json_encode will make it a numeric array rather than object
					sort($diff);

					$err_msg = 'Line Item ['.$i.'] is missing the following keys: ' . json_encode($diff);
					$this->response(array('status' => false, 'message' => $err_msg, 'error_message' => $err_msg), 400);
				}

				$items[] = array(
					'name' => $item['name'],
					'description' => isset($item['description']) ? $item['description'] : '',
					'qty' => $item['quantity'],
					'rate' => $item['rate'],
					'tax_id' => isset($item['tax_id']) ? $item['tax_id'] : 0,
					'total' => round($item['quantity'] * $item['rate'], 2),
				);
			}
		}

		// So we aren't case sensitive on the invoice type
		if (isset($post['type']))
		{
			$post['type'] = strtoupper($post['type']);
		}

		$input = array(
			'client_id' => $this->post('client_id'),
			'type' => $this->post('type'),
			'amount' => $this->post('amount'),
			'description' => $this->post('description'),
			'notes' => $this->post('notes'),
			'is_paid' => $this->post('is_paid'),
			'due_date' => $this->post('due_date'),
			'is_recurring' => $this->post('is_recurring'),
			'frequency' => $this->post('frequency'),
			'auto_send' => $this->post('auto_send'),
			'currency' => $this->post('currency'),
			'items' => $items
		);

		if ($unique_id = $this->invoice_m->insert($input))
		{
			$this->response(array('status' => true, 'unique_id' => $unique_id, 'message' => sprintf('Invoice #%s has been created.', $unique_id)), 200);
		}
		else
		{
			$err_msg = current($this->validation_errors());
			$this->response(array('status' => false, 'message' => $err_msg, 'error_message' => $err_msg), 400);
		}
	}

	/**
	 * Edit Invoice
	 *
	 * The original documented endpoint.
	 *
	 * @link   /api/1/invoices/edit   POST Request
	 */
	public function edit_post()
	{
		if ( ! $unique_id = $this->post('unique_id'))
		{
			$err_msg = 'No id was provided.';
			$this->response(array('status' => false, 'message' => $err_msg, 'error_message' => $err_msg), 400);
		}

		$invoice = $this->invoice_m->get($unique_id);

		if (empty($invoice))
		{
			$err_msg = 'This invoice does not exist!';
			$this->response(array('status' => false, 'message' => $err_msg, 'error_message' => $err_msg), 404);
		}

		if ($this->invoice_m->update($unique_id, $this->input->post()))
		{
			$this->response(array('status' => true, 'message' => sprintf('Project #%d has been updated.', $unique_id)), 200);
		}
		else
		{
			$err_msg = current($this->validation_errors());
			$this->response(array('status' => false, 'message' => $err_msg, 'error_message' => $err_msg), 400);
		}
	}

	/**
	 * Update Invoice
	 *
	 * @deprecated This should stay for backward compatibility
	 * @link   /api/1/invoices/update   POST Request
	 */
	public function update_post()
	{
		$this->edit_post();
	}

	/**
	 * Delete Invoice
	 *
	 * @link   /api/1/invoices/delete   POST Request
	 */
	public function delete_post($unique_id = null)
	{
		if (!$this->post('unique_id') and !$this->post('id'))
		{
			$err_msg = 'No unique_id (or id) was provided.';
			$this->response(array('status' => false, 'message' => $err_msg, 'error_message' => $err_msg), 400);
		}

		// Get the Unique ID
		if ($this->post('unique_id')) {
		    $unique_id = $this->post('unique_id');
		} else {
		    $unique_id = $this->invoice_m->getUniqueIdById($this->post('id'));
		}

		// Make sure that the invoice is found
		if ( ! $unique_id OR ! $invoice = $this->invoice_m->get($unique_id))
		{
			$err_msg = 'This invoice does not exist!';
			$this->response(array('status' => false, 'message' => $err_msg, 'error_message' => $err_msg), 404);
		}

		// Delete the Invoice!
		$this->invoice_m->delete($unique_id);
		$this->response(array('status' => true, 'message' => 'Invoice #'.$unique_id.' has been deleted.'), 200);
	}

}