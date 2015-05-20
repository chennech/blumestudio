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
 * The Clients Model
 *
 * @subpackage	Models
 * @category	Clients
 */
class Clients_m extends Pancake_Model {

    /**
     * @var	string	The name of the clients table
     */
    protected $table = 'clients';
    protected $validate = array(
        array(
            'field' => 'first_name',
            'label' => 'First Name',
            'rules' => ''
        ),
        array(
            'field' => 'email',
            'label' => 'Email',
            'rules' => 'required|valid_emails'
        ),
    );

    function build_permitted_clients_dropdown($item_type, $action, $count_type = '', $empty_label = null, $empty_value = '') {

        if ($empty_label === null) {
            $empty_label = __('global:select');
        }

        $dropdown_array = array($empty_value => $empty_label);
        $clients_dropdown_array = array();
        $assigned_clients = $this->assignments->get_clients_involved($item_type, $action);
        if (count($assigned_clients) > 0) {
            $this->db->where_in('id', $assigned_clients);
            $clients = $this->order_by('first_name')->get_all();
        } else {
            $clients = array();
        }
        foreach ($clients as $client) {
            $buffer = $count_type != '' ? ' (' . get_count($count_type, $client->id) . ')' : '';
            $client_name = client_name($client);
            $clients_dropdown_array += array($client->id => $client_name . $buffer);
        }

        if (Events::has_listeners('sort_clients')) {
            $clients_dropdown_array = get_instance()->dispatch_return('sort_clients', $clients_dropdown_array, 'array');
            $clients_dropdown_array = reset($clients_dropdown_array);
        }

        return $dropdown_array + $clients_dropdown_array;
    }

    function count() {
        where_assigned('clients', 'read');
        return $this->db->count_all_results($this->table);
    }

    function count_all() {
        # Override the original function to take into account User Permissions.
        return $this->count();
    }

    public function get_balance($client_id, $date = null) {
        $CI = &get_instance();
        $CI->load->model('invoices/invoice_m');
        $CI->load->model('invoices/partial_payments_m', 'ppm');
        $CI->load->model('clients/clients_credit_alterations_m');
        
        if ($date === null) {
            $date = time();
        }
        
        return $CI->invoice_m->get_credit_notes_total($client_id, $date) + $CI->clients_credit_alterations_m->get_altered_balance($client_id, $date) - $CI->ppm->get_balance_payments_total($client_id, $date);
    }

    function health($id) {
        $CI = &get_instance();
        $CI->load->model('invoices/partial_payments_m', 'ppm');
        return $CI->ppm->getClientHealth($id);
    }

    function getUniqueIdById($id) {
        $buffer = $this->db->select('unique_id')->where('id', $id)->get($this->table)->row_array();
        return isset($buffer['unique_id']) ? $buffer['unique_id'] : '';
    }

    function getById($id) {
        $buffer = $this->db->where('id', $id)->get($this->table)->row_array();
        if (isset($buffer['unique_id'])) {
            $buffer['access_url'] = site_url(Settings::get('kitchen_route') . '/' . $buffer['unique_id']);
        }
        return $buffer;
    }

    function find_client($company, $first = '', $last = '') {
        $clients = $this->db->get_where($this->table, array('company' => $company))->result_array();
        if (count($clients) != 0) {
            foreach ($clients as $client) {
                if ($client['last_name'] == $last and $client['first_name'] == $first) {
                    return $client;
                }
            }
        }
        return false;
    }

    function find_client_by_login($email, $passphrase) {
        return $this->db->where("email", $email)->where("passphrase", $passphrase)->get("clients")->row();
    }

    function get_clients_csv() {
        $return = array();
        $buffer = $this->db->get($this->table)->result_array();

        foreach ($buffer as $row) {
            $data = array(
                "Title" => $row['title'],
                "First Name" => $row['first_name'],
                "Last Name" => $row['last_name'],
                "Email" => $row['email'],
                "Company" => $row['company'],
                "Address" => $row['address'],
                "Telephone Number" => $row['phone'],
                "Fax Number" => $row['fax'],
                "Mobile Number" => $row['mobile'],
                "Website URL" => $row['website'],
                "Notes" => $row['profile'],
                "Client Area URL" => site_url(Settings::get('kitchen_route') . '/' . $row['unique_id']),
                "Client Area Passphrase" => $row['passphrase'],
                "Language" => $row['language']
            );

            $return[] = $data;
        }

        return $return;
    }
    
    function count_filtered($prefix) {
        where_assigned('clients', 'read');
        $prefix = $this->db->escape_like_str($prefix);
        $this->db->where("(first_name like '$prefix%' or last_name like '$prefix%' or company like '$prefix%')", null, false);
        return $this->db->count_all_results($this->table);
    }

    public function get_filtered($prefix, $limit, $offset)
    {
        where_assigned('clients', 'read');
        $prefix = $this->db->escape_like_str($prefix);
        
        $this->db
            ->where("(first_name like '$prefix%' or last_name like '$prefix%' or company like '$prefix%')", null, false)
            ->order_by('last_name')
            ->order_by('company')
            ->limit($limit, $offset);

        return $this->get_all();
    }

    function get_all_client_ids() {
        $buffer = $this->db->select('id')->get($this->table)->result_array();
        $clients = array();
        foreach ($buffer as $client) {
            $clients[] = $client['id'];
        }
        return $clients;
    }

    function get_for_kitchen($unique_id) {
        $CI = get_instance();
        $CI = $this->load->model('invoices/invoice_m');
        $client = $this->db->where('unique_id', $unique_id)->get('clients')->row();
        $client->paid_total = $CI->invoice_m->paid_totals($client->id, null, true);
        $client->paid_total = $client->paid_total['total'];
        $client->unpaid_total = $CI->invoice_m->unpaid_totals($client->id, null, true);
        $client->unpaid_total = $client->unpaid_total['total'];
        return $client;
    }

    function getBusinessIdentity($client_id) {
        $buffer = $this->db->select("business_identity")->where("id", $client_id)->get("clients")->row_array();
        return isset($buffer['business_identity']) ? $buffer['business_identity'] : Business::ANY_BUSINESS;
    }

    function delete($id) {
        $CI = &get_instance();
        $CI->load->model('invoices/invoice_m');
        $CI->load->model('projects/project_m');
        $CI->load->model('proposals/proposals_m');
        $CI->invoice_m->delete_by_client_id($id);
        $CI->project_m->delete_by_client($id);
        $CI->proposals_m->delete_by_client($id);
        return $this->db->where($this->primary_key, $id)->delete($this->table);
    }

    /**
     * Inserts a new client
     *
     * @access 	public
     * @param 	array 	the client array
     * @return 	int
     */
    public function insert($data, $skip_validation = false) {
        $data['unique_id'] = $this->_generate_unique_id();
        if (isset($data['created']) and is_numeric($data['created'])) {
            # $data['created'] is a timestamp and needs to be converted to MySQL format.
            $data['created'] = date('Y-m-d H:i:s', $data['created']);
        }
        if (isset($data['modified']) and is_numeric($data['modified'])) {
            # $data['modified'] is a timestamp and needs to be converted to MySQL format.
            $data['modified'] = date('Y-m-d H:i:s', $data['modified']);
        }

        if (isset($data['random_passphrase'])) {
            $data['passphrase'] = $this->random_passphrase();
            unset($data['random_passphrase']);
        }

        if (isset($data['email_client'])) {
            // send the email
            unset($data['email_client']);
        }

        if (isset($data['support_user_id'])) {
            $data['support_user_id'] = (int) $data['support_user_id'];
        }

        if (!isset($data['created'])) {
            $data['created'] = date('Y-m-d H:i:s');
        }
        
        $data['owner_id'] = current_user();

        return parent::insert($data, $skip_validation);
    }

    public function update($client_id, $data, $skip_validation = false) {

        if (isset($data['email_client'])) {
            // send the email
            unset($data['email_client']);
        }

        if (isset($data['random_passphrase'])) {
            $data['passphrase'] = $this->random_passphrase();
            unset($data['random_passphrase']);
        }

        $data['support_user_id'] = (int) $data['support_user_id'];

        return parent::update($client_id, $data, $skip_validation);
    }

    public static function random_passphrase() {
        $dict_file = "/usr/share/dict/words";
        $passphrase = '';

        // Uhh. just realized. needs a naughty word filter
        if (false && is_file($dict_file) && is_readable($dict_file)) {
            $content = file_get_contents($dict_file);
            $words = explode("\n", $content);
            unset($content); // too beeg

            $options = array_rand($words, 4);
            foreach ($options as $key => $value) {
                $passphrase .= $words[$value] . ' ';
            }

        } else {
            $characters = '23456789abcdefghjkmnopqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ';
            for ($i = 0; $i < 20; $i++) {
                $passphrase .= $characters[rand(0, strlen($characters) - 1)];
            }
        }
        return trim($passphrase);
    }

    // --------------------------------------------------------------------

    /**
     * Resets the client's unique id
     *
     * @access 	public
     * @param 	array 	the client array
     * @return 	int
     */
    public function reset_unique_id($id) {
        $data['unique_id'] = $this->_generate_unique_id();
        return parent::update($id, $data, TRUE);
    }

    public function search($query) {
        $clients = $this->db->select('id, title, first_name, last_name, company, email')->get('clients')->result_array();

        $buffer = array();
        $details = array();
        $query = strtolower($query);

        foreach ($clients as $row) {
            $subbuffer = array();
            $name = "{$row['title']} {$row['first_name']} {$row['last_name']}";
            $name = trim($name);

            $subbuffer[] = levenshtein($query, strtolower($row['email']), 1, 20, 20);
            if (!empty($row['company'])) {
                $subbuffer[] = levenshtein($query, strtolower($row['company']), 1, 20, 20);
            }
            if (!empty($name)) {
                $subbuffer[] = levenshtein($query, strtolower($name), 1, 20, 20);
            }

            $full_match = "$name".($row['company'] ? " - {$row['company']}" : "");
            $subbuffer[] = levenshtein($query, strtolower($full_match), 1, 20, 20);

            sort($subbuffer);

            $buffer[$row['id']] = reset($subbuffer);
            $details[$row['id']] = $full_match;
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

    function process_clients(&$clients) {
        $CI = get_instance();
        $CI->load->model('invoices/invoice_m');
        $CI->load->model('projects/project_m');

        foreach ($clients as &$client) {
            $client->health = $this->health($client->id);
            $paid_total = $CI->invoice_m->paid_totals($client->id);
            $unpaid_total = $CI->invoice_m->unpaid_totals($client->id);
            $client->paid_total = $paid_total['total'];
            $client->unpaid_total = $unpaid_total['total'];
            $client->project_count = $CI->project_m->get_count_by_client($client->id);
        }
    }

    function send_client_area_email($client_id, $message = null, $subject = null, $emails = null) {
        $client = $this->getById($client_id);

        return send_pancake_email(array(
            'to' => $emails ? $emails : $client['email'],
            'template' => 'client_area_details',
            'client_id' => $client_id,
            'data' => array('client' => $client),
            'subject' => $subject,
            'message' => $message,
        ));
    }

    function fetch_details($first_name, $last_name = '', $organization = '', $email = '', $additional_data = array()) {

        if (empty($last_name) and empty($organization) and empty($email) and is_numeric($first_name)) {
            $client = $this->db->like('profile', '[ORIGINAL_CLIENT_ID='.$first_name.']')->get($this->table)->row_array();
            if (isset($client['id']) and !empty($client['id'])) {
                return $client;
            }
        }

        $count_company = $this->db->where('company', $organization)->count_all_results($this->table);
        if ($count_company > 0) {
            $this->db->where('company', $organization);

            if (!empty($first_name)) {
                $this->db->where('first_name', $first_name);
            }
            if (!empty($last_name)) {
                $this->db->where('last_name', $last_name);
            }

            $result = $this->db->get($this->table)->row_array();
            if (isset($result['id']) and !empty($result['id'])) {
                return $result;
            }
        }

        # If it got this far, it needs to create the client.
        if (empty($first_name)) {
            $first_name = ' ';
        }

        if (empty($last_name)) {
            $last_name = ' ';
        }

        $this->insert(array_merge(array(
            'first_name' => $first_name,
            'last_name' => $last_name,
            'company' => $organization,
            'email' => empty($email) ? Business::getNotifyEmail() : $email
        ), $additional_data));

        $result = $this->db->get_where($this->table, array('id' => $this->db->insert_id()))->row_array();
        $result['new_client'] = true;
        return $result;
    }

    // --------------------------------------------------------------------

    /**
     * Generates the unique id for a client
     *
     * @access	public
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

        return $unique_id;
    }

}

/* End of file: settings_m.php */