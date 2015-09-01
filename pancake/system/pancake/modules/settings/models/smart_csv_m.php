<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Pancake
 *
 * A simple, fast, self-hosted invoicing application
 *
 * @package		Pancake
 * @author		Pancake Dev Team
 * @copyright           Copyright (c) 2013, Pancake Payments
 * @license		http://pancakeapp.com/license
 * @link		http://pancakeapp.com
 * @since		Version 4.0
 */
// ------------------------------------------------------------------------

/**
 * The Smart CSV Import Model
 *
 * @subpackage	Models
 * @category	Smart_CSV
 */
class Smart_csv_m extends Pancake_Model {

    protected $required_errors = array();
    protected $invalid_errors = array();
    protected $ci = array();

    public function __construct() {
        parent::__construct();

        $this->ci = get_instance();
        $this->ci->load->model('clients/clients_m');
        $this->ci->load->model('clients/clients_credit_alterations_m');
        $this->ci->load->model('users/user_m');
        $this->ci->load->model('settings/currency_m');
        $this->ci->load->model('settings/tax_m');
        $this->ci->load->model('projects/project_m');
        $this->ci->load->model('projects/project_milestone_m');
        $this->ci->load->model('projects/project_task_statuses_m');
        $this->ci->load->model('projects/project_task_m');
        $this->ci->load->model('projects/project_time_m');
        $this->ci->load->model('invoices/invoice_m');
        $this->ci->load->model('invoices/partial_payments_m', 'ppm');
    }

    function get_required_errors() {
        return $this->required_errors;
    }

    function get_invalid_errors() {
        return $this->invalid_errors;
    }

    function errored() {
        return count($this->required_errors) > 0 or count($this->invalid_errors) > 0;
    }

    function get_fields($import_type) {
        switch ($import_type) {
            case 'invoices':
                return array(
                    'client_id' => "Client",
                    'invoice_number' => "Invoice #",
                    'date_entered' => "Date of Creation",
                    'due_date' => "Due Date",
                    'notes' => "Notes",
                    'description' => "Description",
                    'is_viewable' => "Show in client area?",
                    'amount_paid' => "Amount Paid",
                    'payment_date' => "Payment Date",
                    'currency_id' => "Currency",
                    'item_1_name' => "Item 1 Name",
                    'item_1_description' => "Item 1 Description",
                    'item_1_quantity' => "Item 1 Quantity",
                    'item_1_rate' => "Item 1 Rate",
                    'item_1_tax' => "Item 1 Tax (Name, Percentage or Amount)",
                    'item_2_name' => "Item 2 Name",
                    'item_2_description' => "Item 2 Description",
                    'item_2_quantity' => "Item 2 Quantity",
                    'item_2_rate' => "Item 2 Rate",
                    'item_2_tax' => "Item 2 Tax (Name, Percentage or Amount)",
                );
                break;
            case 'estimates':
                return array(
                    'client_id' => "Client",
                    'invoice_number' => "Estimate #",
                    'date_entered' => "Date of Creation",
                    'notes' => "Notes",
                    'description' => "Description",
                    'is_viewable' => "Show in client area?",
                    'currency_id' => "Currency",
                    'item_1_name' => "Item 1 Name",
                    'item_1_description' => "Item 1 Description",
                    'item_1_quantity' => "Item 1 Quantity",
                    'item_1_rate' => "Item 1 Rate",
                    'item_1_tax' => "Item 1 Tax",
                    'item_2_name' => "Item 2 Name",
                    'item_2_description' => "Item 2 Description",
                    'item_2_quantity' => "Item 2 Quantity",
                    'item_2_rate' => "Item 2 Rate",
                    'item_1_tax' => "Item 2 Tax",
                );
                break;
            case 'credit_notes':
                return array(
                    'client_id' => "Client",
                    'invoice_number' => "Credit Note #",
                    'date_entered' => "Date of Creation",
                    'notes' => "Notes",
                    'description' => "Description",
                    'is_viewable' => "Show in client area?",
                    'currency_id' => "Currency",
                    'item_1_name' => "Item 1 Name",
                    'item_1_description' => "Item 1 Description",
                    'item_1_quantity' => "Item 1 Quantity",
                    'item_1_rate' => "Item 1 Rate",
                    'item_1_tax' => "Item 1 Tax",
                    'item_2_name' => "Item 2 Name",
                    'item_2_description' => "Item 2 Description",
                    'item_2_quantity' => "Item 2 Quantity",
                    'item_2_rate' => "Item 2 Rate",
                    'item_1_tax' => "Item 2 Tax",
                );
                break;
            case 'clients':
                return array(
                    'title' => "Title",
                    'first_name' => "First Name",
                    'last_name' => "Last Name",
                    'email' => "Email",
                    'company' => "Company",
                    'address' => "Address",
                    'phone' => "Phone",
                    "fax" => "Fax",
                    "mobile" => "Mobile",
                    'website' => "Website",
                    'profile' => "Notes",
                    'passphrase' => "Passphrase",
                    'created' => "Date of Creation",
                    'credit_balance' => "Credit Balance",
                );
                break;
            case 'projects':
                return array(
                    'name' => "Project Name",
                    'client_id' => "Client",
                    'due_date' => "Due Date",
                    'description' => "Description",
                    'date_entered' => "Date of Creation",
                    'rate' => "Hourly Rate",
                    'completed' => "Completed?",
                    'currency_id' => "Currency",
                    'is_viewable' => "Show in client area?",
                    'projected_hours' => "Projected Hours",
                    'is_archived' => "Archived?"
                );
                break;
            case 'tasks':
                return array(
                    'name' => "Name",
                    'project_id' => 'Project',
                    'milestone_id' => 'Milestone',
                    'parent_id' => 'Task Parent',
                    'rate' => "Hourly Rate",
                    'projected_hours' => "Projected Hours",
                    'notes' => "Notes",
                    'due_date' => "Due Date",
                    'completed' => "Completed?",
                    'is_viewable' => "Show in client area?",
                    'status_id' => "Task Status",
                    'assigned_user_id' => "Assigned User"
                );
                break;
            case 'time_entries':
                return array(
                    'client_id' => "Client",
                    'project_id' => "Project",
                    'task_id' => "Task",
                    'user_id' => "User",
                    'start_time' => "Start Time",
                    'end_time' => "End Time",
                    'hours' => "Hours",
                    'date' => "Date",
                    'note' => "Notes"
                );
                break;
            case 'users':
                return array(
                    'username' => "Username",
                    'password' => "Password",
                    'email' => "Email",
                    'first_name' => "First Name",
                    'last_name' => "Last Name",
                    'company' => "Company",
                    'phone' => "Phone"
                );
                break;
        }
    }

    function get_textareas($import_type) {
        switch ($import_type) {
            case 'invoices':
                return array(
                    'description', 'notes',
                    'item_1_description', 'item_2_description'
                );
                break;
            case 'estimates':
                return array(
                    'description', 'notes',
                    'item_1_description', 'item_2_description'
                );
                break;
            case 'credit_notes':
                return array(
                    'description', 'notes',
                    'item_1_description', 'item_2_description'
                );
                break;
            case 'tasks':
                return array('notes');
                break;
            case 'clients':
                return array(
                    'address', 'profile'
                );
                break;
            case 'projects':
                return array(
                    'description'
                );
                break;
            case 'time_entries':
                return array(
                    'note'
                );
                break;
            case 'users':
                return array();
                break;
        }
    }

    function get_requireds($import_type) {
        switch ($import_type) {
            case 'invoices':
                return array(
                    'client_id',
                );
                break;
            case 'estimates':
                return array(
                    'client_id',
                );
                break;
            case 'credit_notes':
                return array(
                    'client_id',
                );
                break;
            case 'clients':
                return array(
                    'email'
                );
                break;
            case 'tasks':
                return array('name', 'project_id');
                break;
            case 'projects':
                return array(
                    'name',
                    'client_id'
                );
                break;
            case 'time_entries':
                return array(
                    'task_id',
                    'user_id',
                    'hours',
                    'date'
                );
                break;
            case 'users':
                return array(
                    'username',
                    'password',
                    'email',
                    'first_name'
                );
                break;
        }
    }

    function get_field_types($import_type) {
        switch ($import_type) {
            case 'invoices':
                return array(
                    'client_id' => 'client',
                    'due_date' => 'datetime',
                    'date_entered' => 'datetime',
                    'is_viewable' => 'boolean',
                    'amount_paid' => 'number',
                    'payment_date' => 'datetime',
                    'currency_id' => 'currency',
                    'item_1_quantity' => 'number',
                    'item_1_rate' => 'number',
                    'item_1_tax' => 'tax',
                    'item_2_quantity' => 'number',
                    'item_2_rate' => 'number',
                    'item_2_tax' => 'tax',
                );
                break;
            case 'estimates':
            case 'credit_notes':
                return array(
                    'client_id' => 'client',
                    'date_entered' => 'datetime',
                    'is_viewable' => 'boolean',
                    'currency_id' => 'currency',
                    'item_1_quantity' => 'number',
                    'item_1_rate' => 'number',
                    'item_1_tax' => 'tax',
                    'item_2_quantity' => 'number',
                    'item_2_rate' => 'number',
                    'item_2_tax' => 'tax',
                );
                break;
            case 'tasks':
                return array(
                    'project_id' => 'project',
                    'milestone_id' => 'milestone',
                    'parent_id' => 'task',
                    'rate' => 'number',
                    'projected_hours' => 'hours',
                    'notes' => 'text',
                    'due_date' => 'datetime',
                    'completed' => 'boolean',
                    'is_viewable' => 'boolean',
                    'status_id' => 'task_status',
                    'assigned_user_id' => 'user'
                );
                break;
            case 'clients':
                return array(
                    'email' => 'email',
                    'created' => 'datetime',
                    'website' => 'url',
                    'credit_balance' => 'number',
                );
                break;
            case 'projects':
                return array(
                    'client_id' => 'client',
                    'due_date' => 'datetime',
                    'date_entered' => 'datetime',
                    'rate' => 'number',
                    'completed' => 'boolean',
                    'currency_id' => 'currency',
                    'is_viewable' => 'boolean',
                    'projected_hours' => 'hours',
                    'is_archived' => 'boolean'
                );
                break;
            case 'time_entries':
                return array(
                    'client_id' => 'client',
                    'project_id' => 'project',
                    'task_id' => 'task',
                    'user_id' => 'user',
                    'start_time' => 'time',
                    'end_time' => 'time',
                    'hours' => 'hours',
                    'date' => 'datetime',
                );
                break;
            case 'users':
                return array(
                    'email' => 'email'
                );
                break;
        }
    }

    function validate_records(&$records, $import_type) {
        $errored = false;

        # Process Records
        $i = 0;
        foreach ($records as $key => $record) {

            $i++;

            foreach (array_keys($this->get_fields($import_type)) as $field) {
                if (!isset($record[$field])) {
                    $records[$key][$field] = '';
                }
            }

            foreach ($this->get_requireds($import_type) as $required) {
                if (empty($record[$required])) {
                    $this->required_errors[] = array(
                        'record' => $i,
                        'field' => $required
                    );
                    $errored = true;
                }
            }

            foreach ($this->get_field_types($import_type) as $field => $type) {
                if (in_array(array('record' => $i, 'field' => $field), $this->required_errors)) {
                    # This is missing, so it's obviously going to be invalid.
                    # No need giving the user two error notices for the same field.
                    continue;
                }

                if (empty($record[$field])) {
                    # It is missing, but is not required,
                    # so it's obviously OK to let it through.
                    continue;
                }

                switch ($type) {
                    case 'email':
                        $regex = "/^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/";
                        if (!preg_match($regex, $record[$field])) {
                            $this->invalid_errors[] = array(
                                'record' => $i,
                                'field' => $field
                            );
                            $errored = true;
                        }
                        break;
                    case 'url':
                        $regex = "/(?i)\\b((?:https?:\/\/|www\\d{0,3}[.]|[a-z0-9.\\-]+[.][a-z]{2,4}\/)(?:[^\\s()<>]+|\\(([^\\s()<>]+|(\\([^\\s()<>]+\\)))*\\))+(?:\\(([^\\s()<>]+|(\\([^\\s()<>]+\\)))*\\)|[^\\s`!()\\[\\]{};:'\".,<>?«»“”‘’]))/";
                        if (!preg_match($regex, $record[$field])) {
                            $this->invalid_errors[] = array(
                                'record' => $i,
                                'field' => $field
                            );
                            $errored = true;
                        }
                        break;
                    case 'datetime':
                        if ((string) (int) $record[$field] != $record[$field]) {
                            # It's not a timestamp; check if PHP will be able to understand it.
                            if (strtotime($record[$field]) === false) {
                                $this->invalid_errors[] = array(
                                    'record' => $i,
                                    'field' => $field
                                );
                                $errored = true;
                            }
                        }
                        break;
                    case 'time':
                        # Check if PHP will be able to understand it.

                        $record[$field] = str_ireplace('.', ':', $record[$field]);
                        if (stristr($record[$field], 'p') !== false and stristr($record[$field], 'pm') === false) {
                            # Has p, but not pm.
                            $record[$field] = str_ireplace('p', 'pm', $record[$field]);
                        }

                        if (stristr($record[$field], 'a') !== false and stristr($record[$field], 'am') === false) {
                            # Has a, but not am.
                            $record[$field] = str_ireplace('a', 'am', $record[$field]);
                        }

                        if (strtotime($record[$field]) === false) {
                            $this->invalid_errors[] = array(
                                'record' => $i,
                                'field' => $field
                            );
                            $errored = true;
                        }
                        break;
                    case 'boolean':
                        $regex = "/^(true|false|yes|no|1|0|y|n)$/i";
                        if (!preg_match($regex, $record[$field])) {
                            $this->invalid_errors[] = array(
                                'record' => $i,
                                'field' => $field
                            );
                            $errored = true;
                        }
                        break;
                    case 'number':
                        $regex = "/([0-9]+(?:\.[0-9]+)?)/";
                        if (!preg_match($regex, $record[$field])) {
                            $this->invalid_errors[] = array(
                                'record' => $i,
                                'field' => $field
                            );
                            $errored = true;
                        }
                        break;
                    case 'currency':
                    case 'task':
                    case 'user':
                    case 'project':
                    case 'task_status':
                        $models = array(
                            'currency' => 'currency_m',
                            'task' => 'project_task_m',
                            'user' => 'user_m',
                            'project' => 'project_m',
                            'task_status' => 'project_task_statuses_m'
                        );

                        $model = $models[$type];
                        $result = $this->ci->$model->search($record[$field]);
                        if (!isset($result[0]) or $result[0]['levenshtein'] > 0) {
                            $this->invalid_errors[] = array(
                                'record' => $i,
                                'field' => $field
                            );
                            $errored = true;
                        }
                        break;
                    case 'milestone':
                        $value = $record['project_id'];
                        $this->process_existing_record($value, 'project_m');
                        $result = $this->ci->project_milestone_m->search($record[$field], $value);
                        if (!isset($result[0]) or $result[0]['levenshtein'] > 0) {
                            $this->invalid_errors[] = array(
                                'record' => $i,
                                'field' => $field
                            );
                            $errored = true;
                        }
                        break;
                    case 'hours':
                        $regex = "/([0-9]+(?:\.[0-9]+)?)/";
                        if (!preg_match($regex, $record[$field]) and stristr($record[$field], ':') === false) {
                            # It's not a number, and it hasn't got hours:minutes[:seconds]. It's invalid.
                            $this->invalid_errors[] = array(
                                'record' => $i,
                                'field' => $field
                            );
                            $errored = true;
                        }
                        break;
                }
            }
        }

        if ($errored) {
            return false;
        }

        return $records;
    }

    function process_currency(&$record) {
        if (!empty($record['currency_id'])) {
            $result = $this->ci->currency_m->search($record['currency_id']);
            $currency_code = isset($result[0]) ? $result[0]['id'] : Settings::get('currency');
            $currency = $this->ci->currency_m->getByCode($currency_code);

            $record['currency_id'] = $currency['id'];
            $record['exchange_rate'] = $currency['rate'];
        } else {
            $record['currency_id'] = 0;
            $record['exchange_rate'] = 1;
        }
    }

    function process_hours(&$value) {
        $value = process_hours($value);
    }

    function process_date(&$value, $now_if_empty = true) {
        if (empty($value)) {
            if ($now_if_empty) {
                $value = time();
            }
        } else {
            $value = strtotime($value);
        }
    }

    function process_time(&$value) {
        $value = str_ireplace('.', ':', $value);
        if (stristr($value, 'p') !== false and stristr($value, 'pm') === false) {
            # Has p, but not pm.
            $value = str_ireplace('p', 'pm', $value);
        }

        if (stristr($value, 'a') !== false and stristr($value, 'am') === false) {
            # Has a, but not am.
            $value = str_ireplace('a', 'am', $value);
        }

        $value = date('H:i', strtotime($value));
    }

    function process_existing_record(&$value, $model) {
        $result = $this->ci->$model->search($value);
        $value = (!isset($result[0]) or $result[0]['levenshtein'] > 0) ? 0 : $result[0]['id'];
    }

    function process_project_id(&$value, $client_id = null) {
        if ($client_id > 0) {
            $this->db->where("client_id", $client_id);
        }
        $result = $this->ci->project_m->search($value);
        $value = (!isset($result[0]) or $result[0]['levenshtein'] > 0) ? 0 : $result[0]['id'];
    }

    function process_task_id(&$value, $client_id = null, $project_id = null) {

        if ($client_id > 0) {
            $this->db->where("client_id", $client_id);
        }

        if ($project_id > 0) {
            $this->db->where("project_id", $project_id);
        }

        $result = $this->ci->project_task_m->search($value);
        $value = (!isset($result[0]) or $result[0]['levenshtein'] > 0) ? 0 : $result[0]['id'];
    }

    function process_client(&$value) {
        $result = $this->ci->clients_m->search($value);
        if (isset($result[0]) and $result[0]['levenshtein'] > 0) {
            # Create the client:
            $name = explode(' ', $value);
            $first_name = $name[0];
            unset($name[0]);
            $last_name = implode(' ', $name);

            $this->db->insert('clients', array(
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => Business::getNotifyEmail()
            ));

            $client_id = $this->db->insert_id();

            $value = $client_id;
        } else {
            $value = $result[0]['id'];
        }
    }

    function process_number(&$value) {
        $regex = "/([0-9]+(?:\.[0-9]+)?)/";
        $matches = array();
        $result = preg_match($regex, $value, $matches);
        if ($result === 1) {
            $value = (float) $matches[1];
        } else {
            $value = 0;
        }
    }

    function process_boolean(&$value) {
        $value = preg_match("/^(true|yes|1|y)$/i", $value) === 1;
    }

    function process_tax(&$value, $total) {
        $regex = "/([0-9]+(?:\.[0-9]+)?)/";
        $matches = array();
        $result = preg_match($regex, $value, $matches);
        if ($result === 1) {
            if (stristr($value, '%') === false) {
                # It's a fixed value, turn to percentage.
                $this->process_number($value);
                $value = ($value/$total) * 100;
            }

            # It's a percentage, search for existing tax or create new one if necessary.
            if ($value > 0) {
                $value = $this->ci->tax_m->create_if_not_exists($value);
            }
        } else {
            $this->process_existing_record($value, 'tax_m');
        }
    }

    function process_tax_including_ids(&$value) {
        $regex = "/([0-9]+(?:\.[0-9]+)?)/";
        $matches = array();
        $result = preg_match($regex, $value, $matches);
        if ($result === 1) {
            if (stristr($value, '%') === false) {
                # It's an ID.
                $this->process_number($value);
            } else {
                # It's a percentage, search for existing tax or create new one if necessary.
                if ($value > 0) {
                    $value = $this->ci->tax_m->create_if_not_exists($value);
                }
            }
        } else {
            # It's a name, look for the record.
            $this->process_existing_record($value, 'tax_m');
        }
    }

    function process(&$record, $import_type) {

        switch ($import_type) {
            case 'invoices':
                $this->process_date($record['date_entered']);
                $this->process_currency($record);
                $record['unique_id'] = $this->ci->invoice_m->_generate_unique_id();
                $this->process_boolean($record['is_viewable']);
                $record['owner_id'] = current_user();
                $this->process_client($record['client_id']);
                $this->process_date($record['due_date'], false);
                $this->process_date($record['payment_date'], false);
                $record['type'] = 'DETAILED';

                $this->process_number($record['item_1_quantity']);
                $this->process_number($record['item_1_rate']);
                $this->process_number($record['item_2_quantity']);
                $this->process_number($record['item_2_rate']);
                $this->process_number($record['amount_paid']);
                $record['amount'] = ($record['item_1_quantity']*$record['item_1_rate']) + ($record['item_2_quantity']*$record['item_2_rate']);
                $this->process_tax($record['item_1_tax'], $record['amount']);
                $this->process_tax($record['item_2_tax'], $record['amount']);

                $record['is_paid'] = $record['amount_paid'] == $record['amount'];
                $record['frequency'] = 'm';
                $record['send_x_days_before'] = Settings::get('send_x_days_before');
                if (!empty($record['payment_date'])) {
                    $record['last_sent'] = $record['payment_date'];
                    $record['last_viewed'] = $record['payment_date'];
                } else {
                    $record['last_sent'] = 0;
                    $record['last_viewed'] = 0;
                }

                # Item Rows
                # Payments

                $line_item_1 = array(
                    'unique_id' => $record['unique_id'],
                    'name' => $record['item_1_name'],
                    'description' => $record['item_1_description'],
                    'qty' => $record['item_1_quantity'],
                    'rate' => $record['item_1_rate'],
                    'total' => $record['item_1_rate'] * $record['item_1_quantity'],
                    'type' => 'standard'
                );

                $this->db->insert('invoice_rows', $line_item_1);

                if ($record['item_2_rate'] != 0 or $record['item_2_quantity'] != 0) {
                    $line_item_2 = array(
                        'unique_id' => $record['unique_id'],
                        'name' => $record['item_2_name'],
                        'description' => $record['item_2_description'],
                        'qty' => $record['item_2_quantity'],
                        'rate' => $record['item_2_rate'],
                        'total' => $record['item_2_rate'] * $record['item_2_quantity'],
                        'type' => 'standard'
                    );
                    $this->db->insert('invoice_rows', $line_item_2);
                }

                $partial_payment = array(
                    'unique_invoice_id' => $record['unique_id'],
                    'is_percentage' => 0,
                    'due_date' => $record['due_date'],
                    'unique_id' => $this->ci->ppm->_generate_unique_id(),
                    'key' => 1,
                    'improved' => 1,
                    'is_paid' => 0,
                    'amount' => 0,
                    'payment_date' => 0,
                    'payment_status' => '',
                    'payment_method' => ''
                );

                if ($record['amount_paid'] > 0 and $record['amount_paid'] != $record['amount']) {
                    # Two parts, one paid and one unpaid.
                    $partial_payment_copy_1 = $partial_payment;
                    $partial_payment_copy_2 = $partial_payment;
                    $partial_payment_copy_1['amount'] = $record['amount_paid'];
                    $partial_payment_copy_2['amount'] = $record['amount'] - $record['amount_paid'];
                    $partial_payment_copy_1['payment_date'] = empty($record['payment_date']) ? $record['date_entered'] : $record['payment_date'];
                    $partial_payment_copy_1['payment_status'] = "Completed";
                    $partial_payment_copy_1['payment_method'] = "cash_m";
                    $partial_payment_copy_1['is_paid'] = 1;
                    $this->db->insert('partial_payments', $partial_payment_copy_1);
                    $this->db->insert('partial_payments', $partial_payment_copy_2);
                } else {
                    # Just one paid or unpaid payment part.
                    $partial_payment_copy = $partial_payment;
                    $partial_payment_copy['amount'] = $record['amount'];
                    if ($record['is_paid']) {
                        $partial_payment_copy['is_paid'] = 1;
                        $partial_payment_copy['payment_date'] = $record['payment_date'];
                        $partial_payment_copy['payment_status'] = "Completed";
                        $partial_payment_copy['payment_method'] = "cash_m";
                    }

                    $this->db->insert('partial_payments', $partial_payment_copy);
                }

                unset($record['item_1_name']);
                unset($record['item_1_description']);
                unset($record['item_1_quantity']);
                unset($record['item_1_rate']);
                unset($record['item_1_tax']);
                unset($record['item_2_name']);
                unset($record['item_2_description']);
                unset($record['item_2_quantity']);
                unset($record['item_2_rate']);
                unset($record['item_2_tax']);
                unset($record['amount_paid']);

                break;
            case 'estimates':
            case 'credit_notes':
                $this->process_date($record['date_entered']);
                $this->process_currency($record);
                $record['unique_id'] = $this->ci->invoice_m->_generate_unique_id();
                $this->process_boolean($record['is_viewable']);
                $record['owner_id'] = current_user();
                $this->process_client($record['client_id']);
                $this->process_date($record['due_date'], false);
                $record['type'] = $import_type == "estimates" ? 'ESTIMATE' : "CREDIT_NOTE";

                $this->process_number($record['item_1_quantity']);
                $this->process_number($record['item_1_rate']);
                $this->process_number($record['item_2_quantity']);
                $this->process_number($record['item_2_rate']);
                $record['amount'] = ($record['item_1_quantity']*$record['item_1_rate']) + ($record['item_2_quantity']*$record['item_2_rate']);
                $this->process_tax($record['item_1_tax'], $record['amount']);
                $this->process_tax($record['item_2_tax'], $record['amount']);

                $record['is_paid'] = 0;
                $record['frequency'] = 'm';
                $record['send_x_days_before'] = Settings::get('send_x_days_before');
                $record['last_sent'] = 0;
                $record['last_viewed'] = 0;

                $line_item_1 = array(
                    'unique_id' => $record['unique_id'],
                    'name' => $record['item_1_name'],
                    'description' => $record['item_1_description'],
                    'qty' => $record['item_1_quantity'],
                    'rate' => $record['item_1_rate'],
                    'total' => $record['item_1_rate'] * $record['item_1_quantity'],
                    'type' => 'standard'
                );

                $this->db->insert('invoice_rows', $line_item_1);

                if ($record['item_2_rate'] != 0 or $record['item_2_quantity'] != 0) {
                    $line_item_2 = array(
                        'unique_id' => $record['unique_id'],
                        'name' => $record['item_2_name'],
                        'description' => $record['item_2_description'],
                        'qty' => $record['item_2_quantity'],
                        'rate' => $record['item_2_rate'],
                        'total' => $record['item_2_rate'] * $record['item_2_quantity'],
                        'type' => 'standard'
                    );
                    $this->db->insert('invoice_rows', $line_item_2);
                }

                unset($record['item_1_name']);
                unset($record['item_1_description']);
                unset($record['item_1_quantity']);
                unset($record['item_1_rate']);
                unset($record['item_1_tax']);
                unset($record['item_2_name']);
                unset($record['item_2_description']);
                unset($record['item_2_quantity']);
                unset($record['item_2_rate']);
                unset($record['item_2_tax']);
                break;
            case 'clients':
                $this->process_date($record['created']);
                $record['created'] = date("Y-m-d H:i:s", $record['created']);
                $record['modified'] = date("Y-m-d H:i:s");
                $record['unique_id'] = $this->ci->clients_m->_generate_unique_id();
                break;
            case 'projects':
                $this->process_client($record['client_id']);
                $this->process_date($record['date_entered']);
                $this->process_date($record['due_date'], false);
                $record['date_updated'] = time();
                $this->process_number($record['rate']);
                $this->process_hours($record['projected_hours']);
                $record['unique_id'] = $this->ci->project_m->_generate_unique_id();
                $this->process_boolean($record['completed']);
                $this->process_boolean($record['is_viewable']);
                $this->process_boolean($record['is_archived']);
                $record['owner_id'] = current_user();
                $this->process_currency($record);
                break;
            case 'time_entries':
                $this->process_client($record['client_id']);
                $this->process_project_id($record['project_id'], $record['client_id']);
                $this->process_task_id($record['task_id'], $record['client_id'], $record['project_id']);

                if ($record['project_id'] == 0) {
                    $record['project_id'] = $this->ci->project_task_m->getProjectIdById($record['task_id']);
                }

                $this->process_existing_record($record['user_id'], 'user_m');
                $this->process_date($record['date']);

                if (empty($record['start_time']) or empty($record['end_time'])) {
                    $this->process_hours($record['hours']);
                    $this->project_time_m->insert_hours($record['project_id'], $record['date'], $record['hours'], $record['task_id'], $record['note'], "08:00", $record['user_id']);
                } else {
                    $this->process_time($record['start_time']);
                    $this->process_time($record['end_time']);
                    $record['minutes'] = (strtotime($record['end_time']) - strtotime($record['start_time'])) / 60;
                    $this->project_time_m->insert(array(
                        'project_id' => $record['project_id'],
                        'start_time' => $record['start_time'],
                        'end_time' => $record['end_time'],
                        'date' => $record['date'],
                        'note' => $record['note'],
                        'task_id' => $record['task_id'],
                        'user_id' => $record['user_id'],
                        'minutes' => $record['minutes']
                    ));
                }
                break;
            case 'tasks':
                $this->process_existing_record($record['project_id'], 'project_m');
                $result = $this->ci->project_milestone_m->search($record['milestone_id'], $record['project_id']);
                $record['milestone_id'] = (!isset($result[0]) or $result[0]['levenshtein'] > 0) ? 0 : $result[0]['id'];
                $this->process_existing_record($record['parent_id'], 'project_task_m');
                $this->process_number($record['rate']);
                $this->process_hours($record['projected_hours']);
                $this->process_date($record['due_date']);
                $this->process_boolean($record['completed']);
                $this->process_boolean($record['is_viewable']);
                $this->process_existing_record($record['status_id'], 'project_task_statuses_m');
                $this->process_existing_record($record['assigned_user_id'], 'user_m');
                $record['owner_id'] = current_user();
                break;
            case 'users':
                $additional_data = array(
                    'first_name' => $record['first_name'],
                    'last_name' => $record['last_name'],
                    'company' => $record['company'],
                    'phone' => $record['phone']
                );

                $this->ion_auth->register($record['username'], $record['password'], $record['email'], $additional_data, $this->ci->user_m->getDefaultGroupName());
                break;
        }
    }

    function import($records, $import_type) {

        # Validate everything before importing.
        $records = $this->validate_records($records, $import_type);

        if (!$records) {
            return false;
        }

        # Search for duplicates
        $duplicate_count = 0;


        foreach (array_keys($records) as $key) {
            switch ($import_type) {
                case 'invoices':

                    break;
                case 'estimates':

                    break;
                case 'credit_notes':

                    break;
                case 'clients':
                    if ($this->ci->clients_m->find_client($records[$key]['company'], $records[$key]['first_name'], $records[$key]['last_name'])) {
                        unset($records[$key]);
                        $duplicate_count++;
                    }
                    break;
                case 'projects':

                    break;
                case 'tasks':
                    break;
                case 'time_entries':
                    break;
                case 'users':
                    if ($this->ci->user_m->existsByUsername($records[$key]['username'])) {
                        unset($records[$key]);
                        $duplicate_count++;
                    }
                    break;
            }
        }

        # Process fields for importing
        foreach (array_keys($records) as $key) {
            $this->process($records[$key], $import_type);
        }

        # Store Records
        $table = $this->_map_item_type_table($import_type);
        if (count($records) > 0 and !empty($table)) {
           
            if ($import_type == "clients") {
                $records_without_balance = array();
                $records_with_balance = array();
                
                foreach ($records as $record) {
                    if ($record['credit_balance'] > 0) {
                        $records_with_balance[] = $record;
                    } else {
                        $records_without_balance[] = $record;
                    }
                }
                
                if (count($records_without_balance)) {
                    if (!$this->db->insert_batch($this->_map_item_type_table($import_type), $records_without_balance)) {
                        return false;
                    }
                }
                
                if (count($records_with_balance)) {
                    $table = $this->_map_item_type_table($import_type);
                    foreach ($records_with_balance as $record) {
                        $balance = $record['credit_balance'];
                        unset($record['credit_balance']);
                        $this->db->insert($table, $record);
                        $client_id = $this->db->insert_id();
                        $this->clients_credit_alterations_m->add($client_id, $balance);
                    }
                }
               
            } else {
                if (!$this->db->insert_batch($this->_map_item_type_table($import_type), $records)) {
                    return false;
                }
            }
        }

        return array(
            'count' => count($records),
            'duplicates' => $duplicate_count
        );
    }

    function _map_item_type_table($import_type) {
        switch ($import_type) {
            case 'estimates':
                return 'invoices';
            case 'credit_notes':
                return 'invoices';
            case 'time_entries':
                return '';
            case 'users':
                return '';
            case 'tasks':
                return 'project_tasks';
        }

        return $import_type;
    }

}