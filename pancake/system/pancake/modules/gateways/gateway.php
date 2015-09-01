<?php

defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Pancake
 *
 * A simple, fast, self-hosted invoicing application
 *
 * @package		Pancake
 * @author		Pancake Dev Team
 * @copyright           Copyright (c) 2010, Pancake Payments
 * @license		http://pancakeapp.com/license
 * @link		http://pancakeapp.com
 * @since		Version 2.2.0
 */
// ------------------------------------------------------------------------

/**
 * The Gateway Class
 *
 * By way of reference: A field's type can be ENABLED, FIELD, CLIENT or INVOICE.
 * A ENABLED field simply defines if a gateway is enabled or not. A FIELD field
 * is a field that can be used by the payment gateway. A CLIENT field defines if
 * a payment gateway is enabled/disabled for a client, and a INVOICE field defines
 * if a payment gateway is enabled/disabled for an invoice.
 *
 * If there is no ENABLED field, it is assumed that the gateway is disabled.
 *
 * If there is no CLIENT field for a client, or no INVOICE field for an invoice,
 * it is assumed that the gateway is enabled for them.
 *
 * @subpackage	Gateway
 * @category	Payments
 */
abstract class Gateway extends Pancake_Model {

    public $gateway;
    public $title;
    public $frontend_title;
    public $table = 'gateway_fields';
    public $version = '1.0';
    public $show_version = false;
    public $author = 'Pancake Dev Team';
    public $notes = '';
    public $fields = array();
    public $fields_descriptions = array();
    public $autosubmit = false;
    public $client_fields = array();
    public $has_payment_page = true;
    public $requires_https = false;
    public $requires_pci = false;
    public $errors = array();
    public $fee = null;
    public $use_field_names = true;
    public $custom_head = '';
    public $post_url = '';

    public function __construct($gateway) {
        parent::__construct();
        $this->gateway = strtolower($gateway);
    }

    /**
     * Get the value of a given field for a gateway.
     * If $field is not provided, all fields will be returned.
     *
     * @param string $field
     * @return array|string
     */
    public function get_field($field = null) {
        $buffer = self::get_fields($this->gateway, 'FIELD', $field);
        return isset($buffer[0]['value']) ? trim($buffer[0]['value']) : '';
    }

    public function error($message) {
        $this->errors[] = $message;
    }

    public function has_errors() {
        return count($this->errors) > 0;
    }

    public function get_client_details($unique_id) {
        $CI = &get_instance();
        $CI->load->model('invoices/partial_payments_m', 'ppm');
        $CI->load->model('invoices/invoice_m');
        $CI->load->model('clients/clients_m');
        $unique_invoice_id = $CI->ppm->getUniqueInvoiceIdByUniqueId($unique_id);
        $client_id = $CI->invoice_m->getClientIdByUniqueId($unique_invoice_id);
        return $CI->clients_m->getById($client_id);
    }

    public function get_client_field($field) {
        return @$_POST['client_fields'][$field];
    }

    public static function get_fields($gateway = null, $type = null, $field = null) {
        $where = array();

        if ($gateway !== null) {
            $where['gateway'] = $gateway;
        }

        if ($type !== null) {
            $where['type'] = $type;
        }

        if ($field !== null) {
            $where['field'] = $field;
        }

        $CI = &get_instance();
        return $CI->db->where($where)->get('gateway_fields')->result_array();
    }

    /**
     * Set the value of a field of a certain type for a gateway.
     *
     * @param string $gateway
     * @param string $field
     * @param mixed $value
     * @param string $type (ENABLED, FIELD, INVOICE or CLIENT)
     * @return boolean
     */
    public static function set_field($gateway, $field, $value, $type) {

        $CI = &get_instance();

        $where = array(
            'gateway' => $gateway,
            'field' => (string) $field, // (string) fixes a MySQL strict error.
            'type' => $type
        );

        $data = array(
            'gateway' => $gateway,
            'field' => (string) $field, // (string) fixes a MySQL strict error.
            'type' => $type,
            'value' => $value
        );

        if ($CI->db->where($where)->count_all_results('gateway_fields') == 0) {
            return $CI->db->insert('gateway_fields', $data);
        } else {
            return $CI->db->where($where)->update('gateway_fields', $data);
        }
    }

    /**
     * Process the input from the settings page, store everything properly.
     *
     * @param array $gateways
     * @return boolean
     */
    public static function processSettingsInput($gateways) {
        foreach (self::get_gateways() as $gateway) {
            if (!@isset($gateways[$gateway['gateway']] ['enabled'])) {
                $gateways[$gateway['gateway']]['enabled'] = 0;
            }
        }

        foreach ($gateways as $gateway => $fields) {
            foreach ($fields as $field => $value) {

                $value = trim($value);

                if ($field == 'enabled') {
                    $type = 'ENABLED';
                } else {
                    $type = 'FIELD';
                }
                if (!self::set_field($gateway, $field, $value, $type)) {
                    return false;
                }
            }
        }

        return true;
    }

    public static function duplicateInvoiceGateways($old_invoice_id, $new_invoice_id) {
        $CI = &get_instance();
        $buffer = $CI->db->get_where('gateway_fields', array('type' => 'INVOICE', 'field' => $old_invoice_id))->result_array();
        foreach ($buffer as $row) {
            self::set_field($row['gateway'], $new_invoice_id, $row['value'], 'INVOICE');
        }
        return true;
    }

    public static function processItemInput($item_type, $item, $gateways) {
        $enabled = self::get_enabled_gateways();

        foreach ($enabled as $field) {
            if (!isset($gateways[$field['gateway']])) {
                $gateways[$field['gateway']] = 0;
            }
        }

        foreach ($gateways as $gateway => $enabled) {
            if (!self::set_field($gateway, $item, $enabled, $item_type)) {
                return false;
            }
        }

        return true;
    }

    private static function get_gateway_list($gateway = null) {
        $gateways = array();
        if ($gateway === null) {
            clearstatcache();
            clearstatcache(true);
            foreach (scandir(APPPATH . 'modules/gateways/models') as $file) {
                if (substr($file, strlen($file) - 4, 4) == '.php') {
                    $file = str_ireplace('.php', '', $file);
                    if (file_exists(APPPATH . 'modules/gateways/models/' . $file . '.php')) {
                        $gateways[$file] = $file;
                    }
                }
            }
        } else {
            $gateways[$gateway] = $gateway;
        }
        return $gateways;
    }

    public static function get_gateways($gateway = null) {

        $return = array();
        $enabled = self::get_fields($gateway, 'ENABLED');
        $fields = self::get_fields($gateway, 'FIELD');
        $gateways = self::get_gateway_list($gateway);

        foreach ($gateways as $file) {
            require_once APPPATH . 'modules/gateways/models/' . $file . '.php';
            $object = ucfirst($file);
            $object = new $object();

            $return[$file] = array(
                'gateway' => $file,
                'title' => $object->title,
                'frontend_title' => empty($object->frontend_title) ? $object->title : $object->frontend_title,
                'enabled' => false,
                'version' => $object->version,
                'show_version' => $object->show_version,
                'author' => $object->author,
                'fields' => $object->fields,
                'fields_descriptions' => $object->fields_descriptions,
                'has_payment_page' => $object->has_payment_page,
                'requires_https' => $object->requires_https,
                'requires_pci' => $object->requires_pci,
                'notes' => $object->notes,
                'field_values' => array()
            );
        }

        foreach ($fields as $field) {

            if (!isset($return[$field['gateway']])) {
                continue;
            }

            $return[$field['gateway']]['field_values'][$field['field']] = $field['value'];
        }

        foreach ($enabled as $field) {

            if (!isset($return[$field['gateway']])) {
                continue;
            }

            $return[$field['gateway']]['enabled'] = (bool) $field['value'];
        }

        if ($gateway != null) {
            $return = $return[$gateway];
        }

        return $return;
    }

    public static function get_enabled_gateways() {
        $gateways = self::get_gateways();
        $return = array();
        foreach ($gateways as $gateway => $fields) {
            if ($fields['enabled']) {
                $return[$gateway] = $fields;
            }
        }

        return $return;
    }

    public static function get_enabled_gateway_select_array($include_no_gateway, $client_id) {

        get_instance()->load->model("clients/clients_m");
        $balance = get_instance()->clients_m->get_balance($client_id);

        $gateways = self::get_enabled_gateways();
        if ($include_no_gateway) {
            $return = array(
                '' => __('gateways:nogatewayused'),
                'credit-balance' => __('clients:credit_balance_currently', array(Currency::format($balance))),
            );
        } else {
            $return = array();
        }
        foreach ($gateways as $key => $gateway) {
            $return[$key] = $gateway['title'];
        }
        return $return;
    }

    public static function get_frontend_gateways($invoice_id = null) {
        $buffer = self::get_item_gateways('INVOICE', $invoice_id, true);
        $enabled = self::get_enabled_gateways();

        $return = array();
        foreach ($buffer as $gateway) {
            if ($gateway['has_payment_page'] and isset($enabled[$gateway['gateway']])) {
                $return[$gateway['gateway']] = $gateway;
            }
        }
        return $return;
    }

    /**
     * Returns a list of gateways enabled in Settings AND enabled for a specific invoice.
     *
     * Example return value:
     * array('cash_m', 'stripe_m');
     *
     * @param integer $invoice_id
     * @return array
     */
    public static function get_enabled_invoice_gateways($invoice_id) {
        return array_intersect(array_keys(array_filter(self::get_item_gateways("INVOICE", $invoice_id), function($value) {return $value;})), array_keys(self::get_enabled_gateways()));
    }

    public static function get_item_gateways($type, $item = null, $include_data = false) {
        $gateways = self::get_fields(null, $type, $item);
        # That's all the gateways for $type, with value $item. Okay.

        $available_gateways = self::get_gateway_list();

        $return = array();

        if (!isset($_POST['gateways'])) {
            foreach ($available_gateways as $gateway) {
                $return[$gateway] = true;
            }

            foreach ($gateways as $gateway) {
                $return[$gateway['gateway']] = (bool) $gateway['value'];
            }
        } else {
            foreach ($available_gateways as $gateway) {
                $return[$gateway] = false;
            }

            foreach ($_POST['gateways'] as $gateway => $value) {
                $return[$gateway] = true;
            }
        }

        if ($include_data) {
            $buffer = $return;
            $return = array();

            foreach ($buffer as $gateway => $value) {
                if ($value) {
                    $return[$gateway] = self::get_gateways($gateway);
                }
            }
        }

        return $return;
    }

    public static function complete_payment($unique_id, $gateway, $data) {
        $CI = &get_instance();

        $CI->load->model('invoices/invoice_m');
        $CI->load->model('clients/clients_m');
        $CI->load->model('invoices/partial_payments_m', 'ppm');
        $CI->load->model('files/files_m');
        $CI->load->model('tickets/ticket_m');

        $part = $CI->ppm->getPartialPayment($unique_id);
        $invoice = $part['invoice'];

        if ($data) {
            if ($part['is_paid'] and $data['is_paid']) {
                # Was already paid, and this is a repeated notification. Ignore it.
                return true;
            }

            $data['payment_method'] = $gateway;
            $CI->ppm->updatePartialPayment($unique_id, $data);
            $CI->invoice_m->fixInvoiceRecord($part['unique_invoice_id']);

            if($CI->ticket_m->has_invoice($invoice['id'])){
                $ticket = $CI->ticket_m->get_by('tickets.invoice_id',$invoice['id']);

                $CI->ticket_m->update($ticket->id, array('is_paid'=>1),TRUE);
            }

            get_instance()->load->model('notifications/notification_m');
            Notify::client_paid_invoice($invoice['id'], $invoice['client_id']);
            $CI->invoice_m->send_payment_receipt_emails($unique_id, $gateway, $data);
            return true;
        }
    }

    public function generate_payment_form($unique_id, $item_name, $amount, $success, $cancel, $notify, $currency_code, $invoice_number) {

    }

    public function generate_client_fields($unique_id, $item_name, $amount, $success, $cancel, $notify, $currency_code, $invoice_number) {
        return $this->client_fields;
    }

    public function process_cancel($unique_id) {

    }

    public function process_success($unique_id, $amount) {

    }

    public function process_notification($unique_id) {

    }

}