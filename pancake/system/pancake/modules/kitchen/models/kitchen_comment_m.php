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
 * @since		Version 3.2
 */
// ------------------------------------------------------------------------

/**
 * The Kitchen Comment Model
 *
 * @subpackage	Models
 * @category	Kitchen
 */
class Kitchen_comment_m extends Pancake_Model {

    /**
     * @var	string	The projects table name
     */
    protected $table = 'comments';

    /**
     * @var	array	The array of validation rules
     */
    protected $validate = array(
        array(
            'field' => 'client_id',
            'label' => 'Client',
            'rules' => 'required'
        ),
        array(
            'field' => 'item_type',
            'label' => 'Type',
            'rules' => 'required'
        ),
        array(
            'field' => 'item_id',
            'label' => 'Item',
            'rules' => 'required'
        ),
        array(
            'field' => 'comment',
            'label' => 'Comment',
            'rules' => 'required'
        )
    );

    // --------------------------------------------------------------------

    /**
     * Retrieves a single comment by its ID
     *
     * @access	public
     * @param	int		The comment id
     * @return	object	The result object
     */
    public function get_comment_by_id($comment_id) {
        $this->db->where('id', $comment_id);
        $this->db->limit(1);

        $query = $this->db->get($this->table);

        if ($query->num_rows() > 0) {
            return $query;
        }
        return FALSE;
    }

    /**
     * Returns a count of all comments belonging to a client
     *
     * @access	public
     * @param   int    The id of the client
     * @return	int
     */
    public function count_all_comments($client_id) {
        return $this->db
                        ->where('client_id', $client_id)
                        ->count_all_results($this->table);
    }

    // --------------------------------------------------------------------

    /**
     * Inserts a new comment
     *
     * @access	public
     * @param	array 	The comment array
     * @return	int
     */
    public function insert_comment($input, $files = array()) {
        if (!$this->validate($input)) {
            return FALSE;
        }

        $CI = &get_instance();
        $CI->load->model('kitchen/kitchen_files_m');
        $upload_result = $CI->kitchen_files_m->verify_uploads($files);
        if ($upload_result === NOT_ALLOWED) {
            return FALSE;
        }

        $this->db->set(array(
            'client_id' => $input['client_id'],
            'user_id' => $input['user_id'],
            'user_name' => $input['user_name'],
            'created' => time(),
            'item_type' => $input['item_type'],
            'item_id' => $input['item_id'],
            'comment' => purify_html($input['comment']),
            'is_private' => $input['is_private']
        ))->insert($this->table);

        $comment_id = $this->db->insert_id();

        $this->send_notification_email($comment_id, (bool) $input['is_private']);

        return $comment_id;
    }

    /**
     * Updates a new comment
     *
     * @access	public
     * @param	array 	The comment array
     * @return	int
     */
    public function update_comment($comment_id, $input, $files = array()) {
        if (!$this->validate($input)) {
            return FALSE;
        }

        $CI = &get_instance();
        $CI->load->model('kitchen/kitchen_files_m');
        $upload_result = $CI->kitchen_files_m->verify_uploads($files);
        if ($upload_result === NOT_ALLOWED) {
            return FALSE;
        }

        $this->db->where('id', $comment_id)->update($this->table, array(
            'client_id' => $input['client_id'],
            'user_id' => $input['user_id'],
            'user_name' => $input['user_name'],
            'created' => time(),
            'item_type' => $input['item_type'],
            'item_id' => $input['item_id'],
            'comment' => $input['comment'],
        ));

        return $comment_id;
    }

    // --------------------------------------------------------------------

    /**
     * Deletes a comment by its ID
     *
     * @access	public
     * @param	int		The comment id
     * @return	object	The result object
     */
    public function delete_comment($comment_id) {
        $this->db->where('id', $comment_id);

        return $this->db->delete($this->table);
    }

    //

    public function send_notification_email($comment_id, $is_private = true) {

        $this->load->model('clients/clients_m');

        $comment = $this->get($comment_id);
        $client = (array) $this->clients_m->get($comment->client_id);
        $settings = (array) Settings::get_all();

        $comment->url = site_url(Settings::get('kitchen_route') . '/' . $client['unique_id'] . '/comments/' . $comment->item_type . '/' . $comment->item_id);

        $comment->comment = nl2br($comment->comment);

        $parser_array = array(
            'comment' => (array) $comment
        );

        switch ($comment->item_type) {
            case 'invoice':
                $this->load->model('invoices/invoice_m');
                $invoice = (array) $this->invoice_m->get_by('id', $comment->item_id);
                $is_viewable_status = $invoice['is_viewable'];
                $parser_array['item'] = ($invoice['type'] == 'ESTIMATE' ? __('global:estimate') : Settings::get('default_invoice_title')) . ' #' . $invoice['invoice_number'];
                break;

            case 'project':
                $this->load->model('projects/project_m');
                $project = (array) $this->project_m->get_by('id', $comment->item_id);
                $is_viewable_status = $project['is_viewable'];
                $subject = __('kitchen:subjectproject') . " " . $project['name'];
                $parser_array['item'] = __('global:project') . ': ' . $project['name'];
                break;

            case 'task':
                $this->load->model('projects/project_task_m', 'tasks');
                $task = (array) $this->tasks->get_by('id', $comment->item_id);
                $is_viewable_status = $task['is_viewable'];
                $subject = __('kitchen:subjecttask') . " " . $task['name'];
                $parser_array['item'] = __('global:task') . ': ' . $task['name'];
                break;

            case 'proposal':
                $this->load->model('proposals/proposals_m');
                $proposal = (array) $this->proposals_m->get_by('id', $comment->item_id);
                $is_viewable_status = $proposal['is_viewable'];
                $subject = __('kitchen:subjectproposal') . " " . $proposal['title'];
                $parser_array['item'] = __('global:proposal') . ' #' . $proposal['proposal_number'] . ': ' . $proposal['title'];
                break;
        }

        $to = array();

        # Always send comments to the admin.
        $to[] = Business::getNotifyEmail();

        # If it's a comment on a task, send it to the assigned user as well,
        # unless he/she is the one who created the comment.
        if ($comment->item_type == 'task') {
            $this->load->model('users/user_m');
            $assigned_user = $this->user_m->getUserById($task['assigned_user_id']);
            $current_user = (logged_in() ? $this->current_user->id : NULL);

            if (isset($assigned_user['email']) and $task['assigned_user_id'] != $current_user) {
                $to[] = $assigned_user['email'];
            }
        }

        # Send the comment to the client, but only if the item is viewable in the client area,
        # and the comment is NOT private, and it's a logged in user that's making the comment, not the client.
        if ($is_viewable_status and ! $is_private and logged_in()) {
            $to[] = $client['email'];
        }

        # Do not send the same notification to the same email more than once.
        # This could happen if, for example, the email of the assigned user for a task
        # matches the Business::getNotifyEmail().
        $to = array_unique($to);

        if (count($to) > 0) {
            $return = send_pancake_email(array(
                'to' => $to,
                'template' => 'new_comment',
                'client_id' => $client['id'],
                'data' => $parser_array,
            ));
        } else {
            $return = true;
        }

        return $return;
    }

    protected function process_result($row) {
        $this->load->model('clients/clients_m');
        $row->files = $this->get_files($row->id);
        $client_unique_id = $this->clients_m->getUniqueIdById($row->client_id);

        # This is called url_for_logged_in_users to emphasize that it is NOT
        # for clients. This is because of the way task URLs are handled (in-admin).
        if ($row->item_type == 'task') {
            $row->url_for_logged_in_users = site_url("admin/projects/tasks/discussion/" . $row->item_id);
        } else {
            $row->url_for_logged_in_users = site_url(Settings::get('kitchen_route') . '/' . $client_unique_id . '/comments/' . $row->item_type . '/' . $row->item_id);
        }
        return $row;
    }

    public function get($id) {
        $row = parent::get($id);
        return $this->process_result($row);
    }

    public function get_by() {
        $row = call_user_func_array('parent::get_by', func_get_args());
        return $this->process_result($row);
    }

    public function get_all() {
        $result = parent::get_all();
        foreach ($result as $key => $row) {
            $result[$key] = $this->process_result($row);
        }
        return $result;
    }

    public function get_files($comment_id) {
        $this->load->model('kitchen/kitchen_files_m');
        $files = $this->kitchen_files_m->get_many_by('comment_id', $comment_id);
        return $files;
    }

    public function where_not_orphan() {
        $this->db->where("
IF(item_type = 'task',
	(select count(*) from " . $this->db->dbprefix("project_tasks") . " where id = item_id),
	IF(item_type = 'invoice',
		(select count(*) from " . $this->db->dbprefix("invoices") . " where id = item_id),
		IF(item_type = 'project',
			(select count(*) from " . $this->db->dbprefix("projects") . " where id = item_id),
			IF (item_type = 'proposal',
				(select count(*) from " . $this->db->dbprefix("proposals") . " where id = item_id),
				null
			)
		)
	)
) = 1
", null, false);
    }

    public function get_for_dashboard($x = 6) {
        $this->where_not_orphan();
        $result = $this->db->order_by('created', 'desc')->limit($x)->get('comments')->result();
        foreach ($result as $key => $row) {
            $result[$key] = $this->process_result($row);
        }

        return $result;
    }

}

/* End of file: kitchen_comment_m.php */
