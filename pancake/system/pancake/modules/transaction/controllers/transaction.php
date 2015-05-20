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
 * The Transaction Controller
 *
 * @subpackage	Controllers
 * @category	Payments
 */
class Transaction extends Public_Controller {

    /**
     * Set the layout.
     *
     * @access	public
     * @return	void
     */
    public function __construct() {
        parent::__construct();
        require_once APPPATH . 'modules/gateways/gateway.php';
        $this->template->set_layout('index');
    }

    public function receive_notification($gateway = null) {
        $this->load->model('gateways/' . $gateway, 'gateway');
        $this->load->model('invoices/partial_payments_m', 'ppm');
        $data = $this->gateway->process_notification(null);
        Gateway::complete_payment($data['unique_id'], $gateway, $data);
    }

    protected function _secure_redirect($unique_id, $gateway_m) {
        $uri_string = 'transaction/process/' . $unique_id . '/' . $gateway_m;
        $default_url = 'https://' . str_ireplace('http://', '', site_url($uri_string));

        $has_listeners = Events::has_listeners('generate_secure_redirect');

        if ($has_listeners) {
            $url = get_instance()->dispatch_return('generate_secure_redirect', array(
                'uri_string' => $uri_string,
            ));
        } else {
            $url = $default_url;
        }

        if (is_array($url)) {
            // Plugin is not installed; use old format:
            $url = $default_url;
        }

        return redirect($url);
    }

    // ------------------------------------------------------------------------

    /**
     * Sets up the payment gateway form then outputs it with an auto-submit.
     *
     * @access	public
     * @param	string	The unique id of the payment.
     * @return	void
     */
    public function process($unique_id, $gateway = null) {
        $this->load->model('invoices/invoice_m');
        $this->load->model('invoices/partial_payments_m', 'ppm');
        $original_gateway = $gateway;

        $part = $this->ppm->getPartialPayment($unique_id);
        $invoice = $part['invoice'];

        switch_language($invoice['language']);
        Business::setBusinessFromClient($invoice['client_id']);

        unset($part['invoice']);

        if (!isset($part['id']) or count(Gateway::get_frontend_gateways($invoice['real_invoice_id'])) == 0) {
            redirect('');
            return;
        }

        $gateways = Gateway::get_frontend_gateways($invoice['id']);

        if (!$gateway and count($gateways) == 1) {
            foreach ($gateways as $key => $value) {
                $gateway = $key;
            }
        }

        if ($gateway) {
            $this->load->model('gateways/' . $gateway, 'gateway');

            $generated_data = $this->get_generate_data_from_unique_id($unique_id, $gateway);

            $client_fields = call_user_func_array(array($this->gateway, "generate_client_fields"), $generated_data);

            $this->template->custom_head = $this->gateway->custom_head;
            $this->template->use_field_names = $this->gateway->use_field_names;
            $this->template->post_url = $this->gateway->post_url;

            foreach ($client_fields as $key => $field) {
                if (!isset($field['type'])) {
                    $client_fields[$key]['type'] = 'text';
                }
                if (!isset($field['label'])) {
                    $client_fields[$key]['label'] = $key;
                }
                if (!isset($field['value'])) {
                    $client_fields[$key]['value'] = '';
                }
            }

            if (count($client_fields) > 0 and ! isset($_POST['client_fields'])) {
                # Ask for client fields.
                $this->template->client_fields = $client_fields;
                if (!IS_SSL and ! USE_SANDBOX and ! Settings::get('never_use_ssl')) {
                    $this->_secure_redirect($unique_id, $original_gateway);
                }

                $this->template->build('client_fields');
            } else {
                if (isset($_POST['client_fields'])) {
                    foreach ($_POST['client_fields'] as $field => $value) {
                        if (isset($client_fields[$field])) {
                            if ($client_fields[$field]['type'] == 'mmyyyy') {
                                # Convert array into mmyyyy
                                $_POST['client_fields'][$field] = $_POST['client_fields'][$field]['m'] . $_POST['client_fields'][$field]['y'];
                            }
                        }
                    }
                }

                $form = call_user_func_array(array($this->gateway, "generate_payment_form"), $generated_data);

                if ($form === false) {
                    if (count($client_fields) > 0) {
                        # Okay, problems with client_fields.
                        $this->template->errors = $this->gateway->errors;
                        $this->template->client_fields = $client_fields;
                        if (!IS_SSL and ! USE_SANDBOX and ! Settings::get('never_use_ssl')) {
                            $this->_secure_redirect($unique_id, $original_gateway);
                        }

                        $this->template->build('client_fields');
                        return;
                    }
                } elseif (is_array($form)) {
                    Gateway::complete_payment($unique_id, $gateway, $form);
                    switch_theme(false);
                    $this->template->invoice = (array) $this->invoice_m->get_by_unique_id($unique_id);
                    $this->template->files = (array) $this->files_m->get_by_unique_id($unique_id);
                    $this->template->unique_id = $unique_id;
                    $this->template->build('success');
                } else {
                    $this->template->form = $form;
                    $this->template->gateway = $this->gateway->title;
                    $this->template->fee = $this->gateway->fee;
                    $this->template->autosubmit = $this->gateway->autosubmit;
                    $this->template->build('transaction');
                }
            }
        } else {
            $this->template->set_layout('index');
            $this->template->gateways = $gateways;
            $this->template->payment_url = $part['payment_url'];
            $this->template->build('select_payment_method');
        }
    }

    // ------------------------------------------------------------------------

    /**
     * Displays a basic "payment cancelled" page
     *
     * @access	public
     * @return	void
     */
    public function cancel($unique_id, $gateway) {
        $this->load->model('gateways/' . $gateway, 'gateway');
        $this->gateway->process_cancel($unique_id);
        $this->load->model('invoices/invoice_m');
        $this->load->model('invoices/partial_payments_m', 'ppm');

        $part = $this->ppm->getPartialPayment($unique_id);
        $invoice = $part['invoice'];

        switch_language($invoice['language']);
        Business::setBusinessFromClient($invoice['client_id']);

        $this->template->unique_id = $invoice['unique_id'];
        $this->template->build('cancel');
    }

    // ------------------------------------------------------------------------

    /**
     * Displays a basic "payment success" page
     *
     * @access	public
     * @return	void
     */
    public function success($unique_id, $gateway) {
        $this->load->model('invoices/invoice_m');
        $this->load->model('invoices/partial_payments_m', 'ppm');
        $this->load->model('files/files_m');
        $this->load->model('gateways/' . $gateway, 'gateway');

        $part = $this->ppm->getPartialPayment($unique_id);
        $invoice = $part['invoice'];

        switch_language($invoice['language']);
        Business::setBusinessFromClient($invoice['client_id']);

        $generate_data = $this->get_generate_data_from_unique_id($unique_id, $gateway);
        $data = $this->gateway->process_success($unique_id, $generate_data['amount']);

        if ($data === false or $data === null) {
            $client_fields = call_user_func_array(array($this->gateway, "generate_client_fields"), $generate_data);

            $this->template->custom_head = $this->gateway->custom_head;
            $this->template->use_field_names = $this->gateway->use_field_names;
            $this->template->post_url = $this->gateway->post_url;

            foreach ($client_fields as $key => $field) {
                if (!isset($field['type'])) {
                    $client_fields[$key]['type'] = 'text';
                }
                if (!isset($field['label'])) {
                    $client_fields[$key]['label'] = $key;
                }
                if (!isset($field['value'])) {
                    $client_fields[$key]['value'] = '';
                }
            }

            if (count($client_fields) > 0) {
                # Okay, problems with client_fields.
                $this->template->errors = $this->gateway->errors;
                $this->template->client_fields = $client_fields;
                if (!IS_SSL and ! USE_SANDBOX and ! Settings::get('never_use_ssl')) {
                    $this->_secure_redirect($unique_id, $original_gateway);
                }

                $this->template->build('client_fields');
                return;
            } elseif ($this->gateway->has_errors()) {
                # Only show the "payment failed" page if there are errors specified for this payment gateway (even though the error isn't displayed).
                # This is to deal with the fact that some gateways return null in process_success() because it's a no-op for them.
                # So if $data === null or $data === false, BUT no errors have been set, then this won't run; the build('success') at the end of this function will.
                # - Bruno
                $this->template->build('failed', array(
                    'unique_id' => $unique_id
                ));
                return;
            }
        } elseif (is_array($data)) {
            if (isset($data['txn_id'])) {
                # This gateway uses the success page as the IPN. We need to treat it as such.
                Gateway::complete_payment($unique_id, $gateway, $data);
            }
        }

        switch_theme(false);
        $this->template->invoice = (array) $this->invoice_m->get_by_unique_id($unique_id);
        $this->template->files = (array) $this->files_m->get_by_unique_id($unique_id);
        $this->template->unique_id = $unique_id;
        $this->template->build('success');
    }

    // ------------------------------------------------------------------------

    /**
     * Processes the payment notification info from the payment gateway used.
     * It then sends out the receipt and notification emails.
     *
     * @access	public
     * @param	string	The unique id of the payment.
     * @return	void
     */
    public function ipn($unique_id, $gateway) {
        $this->load->model('gateways/' . $gateway, 'gateway');
        $this->load->model('invoices/partial_payments_m', 'ppm');
        $data = $this->gateway->process_notification($unique_id);
        Gateway::complete_payment($unique_id, $gateway, $data);
    }

    protected function get_generate_data_from_unique_id($unique_id, $gateway) {
        $this->load->model('invoices/invoice_m');
        $this->load->model('invoices/partial_payments_m', 'ppm');

        $part = $this->ppm->getPartialPayment($unique_id);
        $invoice = $part['invoice'];
        unset($part['invoice']);

        if (!isset($part['id']) or count(Gateway::get_frontend_gateways($invoice['real_invoice_id'])) == 0) {
            redirect('');
            return;
        }

        $gateways = Gateway::get_frontend_gateways($invoice['id']);

        if (!$gateway and count($gateways) == 1) {
            foreach (array_keys($gateways) as $key) {
                $gateway = $key;
            }
        }

        $item_name = Settings::get("default_invoice_title") . ' #' . $invoice['invoice_number'];
        $amount = $part['billableAmount'];
        $success = site_url('transaction/success/' . $unique_id . '/' . $gateway);
        $cancel = site_url('transaction/cancel/' . $unique_id . '/' . $gateway);
        $notify = site_url('transaction/ipn/' . $unique_id . '/' . $gateway);
        $currency_code = $invoice['currency_code'] ? $invoice['currency_code'] : Currency::code();

        # If you alter this array, be mindful of the order of the keys.
        # This array is used in call_user_func_array(), so the order matters.
        return array(
            'unique_id' => $unique_id,
            'item_name' => $item_name,
            'amount' => $amount,
            'success' => $success,
            'cancel' => $cancel,
            'notify' => $notify,
            'currency_code' => $currency_code,
            'invoice_number' => $invoice['invoice_number']
        );
    }

}
