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
 * @since		Version 1.1
 */

/**
 * The admin controller for times
 *
 * @subpackage	Controllers
 * @category	Projects
 */
class Admin_Times extends Admin_Controller {

    /**
     * Load in the dependencies
     *
     * @access	public
     * @return	void
     */
    public function __construct() {
        parent::__construct();

        $this->load->model(array(
            'project_m', 'project_task_m',
            'project_time_m', 'project_milestone_m',
        ));
    }

    public function index() {
        access_denied();
    }

    public function add_hours($project_id=null) {

        can('read', $this->project_m->getClientIdById($project_id), 'projects', $project_id) or access_denied();

        if ($_POST) {

            if (!empty($_POST['task_id'])) {
                can('read', $this->project_task_m->getClientIdById($_POST['task_id']), 'project_tasks', $_POST['task_id']) or access_denied();
            }

            $this->load->model('projects/project_time_m');
            //$this->project_time_m->insert_hours($project_id, $_POST['date'], time_to_decimal($_POST['hours']), empty($_POST['task_id']) ? 0 : $_POST['task_id'], $_POST['note']);
            switch($_POST['day']){
                case 'yesterday':
                    $date = strtotime('-1 day');
                    break;

                case 'other':
                    $date = $_POST['date'];
                    break;

                default:
                    $date = time();
                    break;
            }

            $this->project_time_m->insert_hours($project_id, $date, time_to_decimal($_POST['hours']), empty($_POST['task_id']) ? 0 : $_POST['task_id'], $_POST['note'], ($_POST['start_time'] ? $_POST['start_time'] : null));
            redirect('admin/projects/times/view_entries/project/' . $_POST['project_id']);
        } else {

			$tasks = $this->project_task_m->where('project_id', $project_id)->order_by('name')->get_all();
	        $tasks_select = array('' => '-- Not related to a task --');
	        foreach ($tasks as $task) {
	            $tasks_select[$task->id] = $task->name;
	        }

			$this->load->view('_add_hours', array(
	            'project' => $this->project_m->get_project_by_id($project_id)->row(),
	            'tasks_select' => $tasks_select,
	        ));
		}
    }

    public function create($project_id = NULL) {

        if ($_POST) {

            can('read', $this->project_m->getClientIdById($this->input->post('project_id')), 'projects', $this->input->post('project_id')) or access_denied();

            if (!empty($_POST['task_id'])) {
                can('read', $this->project_task_m->getClientIdById($_POST['task_id']), 'project_tasks', $_POST['task_id']) or access_denied();
            }

            $minutes = $this->_start_end_date_to_minutes(
                    $this->input->post('start_time'), $this->input->post('end_time'), $this->input->post('date')
            );

            $result = $this->project_time_m->insert(array(
                'project_id' => $this->input->post('project_id'),
                'start_time' => $this->input->post('start_time'),
                'end_time' => $this->input->post('end_time'),
                'minutes' => $minutes,
                'date' => $this->input->post('date'),
                'note' => $this->input->post('note'),
                'task_id' => $this->input->post('task_id'),
                'user_id' => $this->current_user->id,
                    ));

            // All form validation is handled in the model, so lets just throw it the data
            if ($result) {
                $message = array('success' => $this->lang->line('times.create.succeeded'));
            } else {
                if ($errors = validation_errors('<p>', '</p>')) {
                    $message = array('error' => $errors);
                } else {
                    $message = array('error' => $this->lang->line('times.create.failed'));
                }
            }

            output_json($message);
        }

        $tasks = $this->project_task_m->where('project_id', $project_id)->order_by('name')->get_all();
        $tasks_select = array('' => '-- Not related to a task --');
        foreach ($tasks as $task) {
            $tasks_select[$task->id] = $task->name;
        }

        $this->load->view('time_form', array(
            'project' => $this->project_m->get_project_by_id($project_id)->row(),
            'tasks_select' => $tasks_select,
        ));
    }

    public function delete($time_id) {

        # This isn't being used?
        # I couldn't find project_time_m->delete_time, which is why I ask.

        // delete time. Ajax Only.
        $time = $this->project_time_m->get_time_by_id($time_id);

        if ($time->num_rows() == 0) {
            $message = array('error' => 'Invalid Object');
        } else {
            $message = array('success' => 'Deleted Object');
            $this->project_time_m->delete_time($time_id);
        }

        output_json($message);
    }

    public function _view_entries($type, $id, $short = false) {

        $this->template->task_id = 0;

        switch ($type) {
            case 'project':
                $entries = $this->project_time_m->get_times_by_project($id);
                $project_id = $id;
                can('read', $this->project_m->getClientIdById($project_id), 'projects', $project_id) or access_denied();
                break;
            case 'task':
                $project_id = $this->db->select('project_id')->where('id', $id)->get('project_tasks')->row_array();
                $project_id = isset($project_id['project_id']) ? $project_id['project_id'] : 0;
                can('read', $this->project_m->getClientIdById($project_id), 'projects', $project_id) or access_denied();
                can('read', $this->project_task_m->getClientIdById($id), 'project_tasks', $id) or access_denied();
                $entries = $this->project_time_m->get_task_entries_by_task($id);
                $this->template->task_id = $id;
                break;
            default:
                show_error('Page not found', 404);
                break;
        }

        $tasks = $this->project_task_m->where('project_id', $project_id)->order_by('name')->get_all();

        $tasks_select = array('' => '-- Not related to a task --');
        foreach ($tasks as $task) {
            $tasks_select[$task->id] = $task->name;
        }

        $tpl_data = array(
            'entries' => $entries,
            'tasks_select' => $tasks_select,
            'project_id' => $project_id,
            'project' => $this->project_m->get($project_id),
        );

        if ($short) {
            $this->load->view('view_entries', $tpl_data);
        } else {
            $this->template->build('view_entries', $tpl_data);
        }
    }

    public function view_entries($type, $id) {
        $this->_view_entries($type, $id);
    }

    public function view_short_entries($type, $id) {
        $this->_view_entries($type, $id, true);
    }

    public function edit($entry_id) {

        $post = $this->input->post();

        $record = $this->db->where('id', $entry_id)->get('project_times')->row_array();

        if (!isset($record['task_id'])) {
            access_denied();
        }

        if ($record['task_id'] != 0) {
            where_assigned('project_tasks', 'read', 'task_id', 'project_times');
        }

        where_assigned('projects', 'read', 'project_id', 'project_times');

        $date = read_date_picker($post['date']);

        $minutes = $this->_start_end_date_to_minutes($post['start_time'], $post['end_time'], $date);

        $data = array(
            'start_time' => $post['start_time'],
            'end_time' => $post['end_time'],
            'date' => $date,
            'minutes' => $minutes
        );

        if (isset($_POST['note'])) {
            $data['note'] = $post['note'];
        }

        if (isset($_POST['task_id'])) {
            $data['task_id'] = $post['task_id'];
        }


        $this->db
                ->where('id', $entry_id)
                ->update('project_times', $data);

		redirect('/admin/projects/times/view_entries/project/'.$post['project_id']);

    }




    public function ajax_set_entry() {

        $post = $this->input->post();

        $record = $this->db->where('id', $post['id'])->get('project_times')->row_array();

        if (!isset($record['task_id'])) {
            access_denied();
        }

        if ($record['task_id'] != 0) {
            where_assigned('project_tasks', 'read', 'task_id', 'project_times');
        }

        where_assigned('projects', 'read', 'project_id', 'project_times');

        $date = read_date_picker($post['date']);

        $minutes = $this->_start_end_date_to_minutes($post['start_time'], $post['end_time'], $date);

        $data = array(
            'start_time' => $post['start_time'],
            'end_time' => $post['end_time'],
            'date' => $date,
            'minutes' => $minutes
        );

        if (isset($_POST['note'])) {
            $data['note'] = $_POST['note'];
        }

        if (isset($_POST['task_id'])) {
            $data['task_id'] = $_POST['task_id'];
        }

        $this->db
                ->where('id', $post['id'])
                ->update('project_times', $data);

        echo json_encode(array('new_duration' => format_seconds($minutes * 60)));
    }

    public function ajax_delete_entry() {

        $record = $this->db->where('id', $this->input->post('id'))->get('project_times')->row_array();

        if (!isset($record['task_id'])) {
            access_denied();
        }

        if ($record['task_id'] != 0) {
            where_assigned('project_tasks', 'read', 'task_id', 'project_times');
        }

        where_assigned('projects', 'read', 'project_id', 'project_times');

        $this->db
                ->where('id', $this->input->post('id'))
                ->delete('project_times');
    }

    private function _start_end_date_to_minutes($start_time, $end_time, $date) {
        $start_date = new DateTime(date('Y-m-d', $date));
        $end_date = new DateTime(date('Y-m-d', $date));

        // Set the time, as accurate as they gave
        call_user_func_array(array($start_date, 'setTime'), explode(':', trim($start_time)));
        call_user_func_array(array($end_date, 'setTime'), explode(':', trim($end_time)));

        ($end_time < $start_time) and $end_date->modify('+1 day');

        return ($end_date->format('U') - $start_date->format('U')) / 60;
    }


    public function timers_play($task_id, $start_timestamp) {
        can('read', $this->project_task_m->getClientIdById($task_id), 'project_tasks', $task_id) or access_denied();
        $this->load->model('projects/project_timers_m', 'ptm');
        $result = $this->ptm->play($task_id, $start_timestamp);
        echo json_encode(array('result' => $result));
    }

    public function timers_pause($task_id, $pause_timestamp) {
        can('read', $this->project_task_m->getClientIdById($task_id), 'project_tasks', $task_id) or access_denied();
        $this->load->model('projects/project_timers_m', 'ptm');
        $result = $this->ptm->pause($task_id, $pause_timestamp);
        echo json_encode(array('result' => $result));
    }

    public function timers_stop($task_id, $stop_timestamp) {
        can('read', $this->project_task_m->getClientIdById($task_id), 'project_tasks', $task_id) or access_denied();
        $this->load->model('projects/project_timers_m', 'ptm');
        $result = $this->ptm->stop($task_id, $stop_timestamp);
        echo json_encode(array('result' => $result));
    }

}
