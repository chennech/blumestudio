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
 * The User Model
 *
 * @subpackage	Models
 * @category	Users
 */
class User_m extends Pancake_Model {

    /**
     * @var	string	The name of the clients table
     */
    protected $table = 'users';

    /**
     * Set Validate
     *
     * Needed for users API to dynamically set validation
     *
     * @param  array  Will set validation array
     * @return object
     */
    public function set_validate($validate = array()) {
        $this->validate = $validate;
        return $this;
    }

    public function insert($input, $skip_validation = false) {
        $this->validate = array(
            array(
                'field' => 'username',
                'rules' => 'required',
            ),
            array(
                'field' => 'email',
                'rules' => 'required|valid_email'
            ),
            array(
                'field' => 'password',
                'rules' => 'required'
            ),
            array(
                'field' => 'password_confirm',
                'rules' => 'required|matches[password]'
            ),
        );

        return parent::insert($input, $skip_validation);
    }

    public function search($query) {
        $clients = $this->db->select('users.id, username, first_name, last_name, company, email')->join('meta', 'users.id = meta.user_id', 'LEFT')->get('users')->result_array();

        $buffer = array();
        $details = array();
        $query = strtolower($query);

        foreach ($clients as $row) {
            $subbuffer = array();
            $name = "{$row['first_name']} {$row['last_name']}";
            $name = trim($name);

            $subbuffer[] = levenshtein($query, strtolower($row['username']), 1, 20, 20);
            $subbuffer[] = levenshtein($query, strtolower($row['email']), 1, 20, 20);
            if (!empty($row['company'])) {
                $subbuffer[] = levenshtein($query, strtolower($row['company']), 1, 20, 20);
            }
            if (!empty($name)) {
                $subbuffer[] = levenshtein($query, strtolower($name), 1, 20, 20);
            }

            sort($subbuffer);

            $buffer[$row['id']] = reset($subbuffer);
            $details[$row['id']] = $row['username'];
        }

        asort($buffer);
        $return = array();

        foreach (array_slice($buffer, 0, 3, true) as $id => $levenshtein) {
            $return[] = array(
                'levenshtein' => $levenshtein,
                'name' => $details[$id],
                'id' => $id
            );
        }

        return $return;
    }

    public function update($id, $input, $skip_validation = false) {
        $this->validate = array(
            array(
                'field' => 'email',
                'rules' => 'required|valid_email',
            ),
            array(
                'field' => 'password',
                'rules' => '',
            ),
            array(
                'field' => 'password_confirm',
                'rules' => 'matches[password]',
            ),
        );

        return parent::update($id, $input, $skip_validation);
    }

    public function existsByUsername($username) {
        static $users;

        if (empty($users)) {
            $buffer = $this->db->select('username')->get('users')->result_array();
            foreach ($buffer as $row) {
                $users[] = $row['username'];
            }
        }

        return in_array($username, $users);
    }

    public function getDefaultGroupName() {
        static $name;

        if (empty($name)) {
            $name = $this->db->select('name')->where_in('id', array(1, 2))->order_by('id', 'DESC')->limit(1)->get('groups')->row_array();
            $name = $name['name'];
        }

        return $name;
    }

    function getUserById($id) {
        $buffer = $this->db->where('users.id', $id)->join('meta', 'users.id = meta.user_id', 'LEFT')->get('users')->row_array();
        return (isset($buffer['username']) and ! empty($buffer['username'])) ? $buffer : array();
    }

    function getUsernameByEmail($email) {
        $buffer = $this->db->select('username')->where('email', $email)->get('users')->row_array();
        return isset($buffer['username']) ? $buffer['username'] : '';
    }

    function get_users_by_ids($ids) {
        if (count($ids) == 0) {
            return array();
        }
        return $this->db->where_in('users.id', $ids)->join('meta', 'users.id = meta.user_id', 'LEFT')->get('users')->result_array();
    }

    function get_users_list($include_admins = true) {
        $users = $this->get_all_with_meta($include_admins);
        $return = array();
        foreach ($users as $row) {
            $return[$row['id']] = $row['first_name'] . ' ' . $row['last_name'];
        }
        return $return;
    }

    function get_all_with_meta($include_admins = true) {

        if (!$include_admins) {
            $this->db->where('group_id !=', "1");
        }

        $buffer = $this->db->join('meta', 'users.id = meta.user_id', 'LEFT')->get('users')->result_array();
        $return = array();
        foreach ($buffer as $row) {
            $return[$row['id']] = $row;
        }
        return $return;
    }

    function get_full_name($id) {
        $buffer = $this->getUserById($id);
        if (isset($buffer['first_name'])) {
            return $buffer['first_name'] . ' ' . $buffer['last_name'];
        }
    }

    public function login($language = null, $client_unique_id = null) {
        switch_theme(true);

        // This persists the login redirect
        $this->session->set_flashdata('login_redirect', $this->session->flashdata('login_redirect'));

        $login_redirect = $this->session->flashdata('login_redirect') ? $this->session->flashdata('login_redirect') : null;

        if ($this->session->userdata('client_unique_id') and $this->session->userdata('client_passphrase')) {
            redirect($login_redirect ? $login_redirect : Settings::get('kitchen_route') . '/' . $this->session->userdata('client_unique_id'));
        }

        if (logged_in()) {
            redirect($login_redirect ? $login_redirect : "admin");
        }

        if ($language) {
            switch_language($language);
        }

        $this->load->model("clients/clients_m");
        $this->load->library('form_validation');

        // Set the layout
        $this->template->set_layout('login');

        //validate form input
        $this->form_validation->set_rules('username', __("login:username_email"), 'required');
        $this->form_validation->set_rules('password', __('login:password'), 'required');

        if ($this->form_validation->run()) {
            $remember = ($this->input->post('remember') == 1) ? TRUE : FALSE;

            $username = $this->input->post('username');
            $user_by_email = $this->db->select("username")->where("email", $username)->get("users")->row_array();

            if (isset($user_by_email['username'])) {
                $username = $user_by_email['username'];
            }

            if ($this->ion_auth->login($username, $this->input->post('password'), $remember)) {
                $redirect = $this->session->flashdata('login_redirect') ? $this->session->flashdata('login_redirect') : 'admin';
                $this->session->set_flashdata('success', $this->ion_auth->messages());

                setcookie('pancake_is_never_client', '1', time() + 60 * 60 * 24 * 7, '/');

                redirect($redirect);
            }
        }

        # Couldn't login as a user; try to login as a client:

        if ($client_unique_id) {
            $client = $this->clients_m->get_for_kitchen($client_unique_id);
            switch_language($client->language);
            Business::setBusinessFromClient($client->id);
            $redirect = site_url(Settings::get('kitchen_route') . '/' . $client_unique_id);
        } else {
            $redirect = site_url("");
        }

        $rules = array(
            array(
                'field' => 'password',
                'label' => __('login:password'),
                'rules' => 'trim|required|xss_clean'
            ),
            array(
                'field' => 'username',
                'label' => __("login:username_email"),
                'rules' => 'trim|required|xss_clean'
            ),
        );
        $this->form_validation->set_rules($rules);
        if ($this->form_validation->run()) {
            if (!$client_unique_id) {
                # Look for a email/pass.
                $logged_in_client = $this->clients_m->find_client_by_login($this->input->post('username'), $this->input->post('password'));
            } elseif ($client->passphrase == $this->input->post('password')) {
                $logged_in_client = $client;
            }

            if (isset($logged_in_client->id)) {
                $this->session->set_userdata('client_unique_id', $logged_in_client->unique_id);
                $this->session->set_userdata('client_passphrase', $this->input->post('password'));
                redirect(Settings::get('kitchen_route') . '/' . $client_unique_id);
            } else {
                $this->session->set_flashdata('error', "<p>".__("global:incorrect_login")."</p>");
                redirect($redirect);
            }
        }

        $this->template->build('login');
    }

}

/* End of file: user_m.php */