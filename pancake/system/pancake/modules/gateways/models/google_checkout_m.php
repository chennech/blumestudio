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
 * The PayPal Gateway
 *
 * @subpackage	Gateway
 * @category	Payments
 */
class Google_checkout_m extends Gateway {

    public $title = 'Google Checkout';
    public $autosubmit = true;
    public $notes = 'You need to set the callback URL in your Google Checkout account to ';

    public function __construct() {
        parent::__construct(__CLASS__);
        $this->notes .= site_url('transaction/receive_notification/google_checkout_m') . ' Then, select "Notification Serial Number".';

        if (!defined('GATEWAY_MERCHANT_ID')) {
            define('GATEWAY_MERCHANT_ID', $this->get_field('merchant_id'));
            define('GATEWAY_MERCHANT_KEY', $this->get_field('merchant_key'));
        }

        $this->fields = array(
            'merchant_id' => "Merchant ID",
            'merchant_key' => "Merchant Key",
            'currency_code' => "Currency Code (eg. USD, GBP, EUR, AUD)"
        );
    }

    public function generate_payment_form($unique_id, $item_name, $amount, $success, $cancel, $notify, $currency_code, $invoice_number) {
        $CI = &get_instance();
        $CI->load->model('invoices/partial_payments_m', 'ppm');

        $this->load->spark('codeigniter-payments/0.1.4/');

        # Let's round the amount.
        $amount = round($amount, 2);

        $new_currency_code = $this->get_field('currency_code');
        $new_currency_code = empty($new_currency_code) ? Currency::code() : $new_currency_code;

        # Convert $amount to new currency code if $currency_code is NOT new currency code.
        if ($currency_code != $new_currency_code) {
            $amount = Currency::convert($amount, $currency_code, $new_currency_code);
        }

        $result = $this->payments->oneoff_payment_button('google_checkout', array(
            'currency_code' => $new_currency_code,
            'items' => array(
                array(
                    'desc' => '',
                    'amt' => round($amount, 2),
                    'name' => $item_name,
                    'qty' => 1,
                    'id' => $unique_id
                )
            ),
            'shipping_options' => array(array(
                    'desc' => 'No Shipping',
                    'amt' => '0.00'
            )),
            'edit_url' => $cancel,
            'continue_url' => $success,
                )
        );

        return $result->details;
    }

    public function process_notification($unique_id) {

        chdir("..");
        $base = APPPATH . 'sparks/codeigniter-payments/0.1.4/src/php-payments/vendor/google_checkout/library/';
        require_once($base . 'googleresponse.php');
        require_once($base . 'googlemerchantcalculations.php');
        require_once($base . 'googlerequest.php');
        require_once($base . 'googlenotificationhistory.php');

        define('RESPONSE_HANDLER_ERROR_LOG_FILE', 'googleerror.log');
        define('RESPONSE_HANDLER_LOG_FILE', 'googlemessage.log');

        //Definitions
        $merchant_id = $this->get_field('merchant_id');  // Your Merchant ID
        $merchant_key = $this->get_field('merchant_key');  // Your Merchant Key
        $server_type = "live";  // change this to go live
        $currency = $this->get_field('currency_code');  // set to GBP if in the UK
        //Create the response object
        $Gresponse = new GoogleResponse($merchant_id, $merchant_key);

        //Setup the log file
        $Gresponse->SetLogFiles('', '', L_OFF);  //Change this to L_ON to log
        //Retrieve the XML sent in the HTTP POST request to the ResponseHandler
        $xml_response = isset($HTTP_RAW_POST_DATA) ?
                $HTTP_RAW_POST_DATA : file_get_contents("php://input");

        //If serial-number-notification pull serial number and request xml
        //Find serial-number ack notification
        $serial_array = array();
        parse_str($xml_response, $serial_array);
        $serial_number = $serial_array["serial-number"];
        
        # Not a valid notification.
        if (!isset($serial_array["serial-number"])) {
            return;
        }

        //Request XML notification
        $Grequest = new GoogleNotificationHistoryRequest($merchant_id, $merchant_key, $server_type);
        $raw_xml_array = $Grequest->SendNotificationHistoryRequest($serial_number);
        if ($raw_xml_array[0] != 200) {
            //Add code here to retry with exponential backoff
        } else {
            $raw_xml = $raw_xml_array[1];
        }

        $Gresponse->SendAck($serial_number, false);

        if (get_magic_quotes_gpc()) {
            $raw_xml = stripslashes($raw_xml);
        }


        //Parse XML to array
        list($root, $data) = $Gresponse->GetParsedXML($raw_xml);

        switch ($root) {
            case "new-order-notification": {
                    $unique_id = $data[$root]['shopping-cart']['items']['item']['merchant-item-id']['VALUE'];
                    $google_order_number = $data[$root]['google-order-number']['VALUE'];
                    $GChargeRequest = new GoogleRequest($merchant_id, $merchant_key, $server_type);
                    $GChargeRequest->SendChargeOrder($google_order_number);
                    $this->db->where('unique_id', $unique_id)->update('partial_payments', array(
                        'txn_id' => $google_order_number
                    ));
                    # Not doing anything here.
                    die;
                    break;
                }
            case "risk-information-notification": {
                    break;
                }
            case "charge-amount-notification": {
                    break;
                }
            case "authorization-amount-notification": {
                    break;
                }
            case "refund-amount-notification": {
                    break;
                }
            case "chargeback-amount-notification": {
                    break;
                }
            case "order-numbers": {
                    break;
                }
            case "invalid-order-numbers": {
                    break;
                }
            case "order-state-change-notification": {
                if ($data[$root]['new-financial-order-state']['VALUE'] == 'CHARGED') {
                    $google_order_number = $data[$root]['google-order-number']['VALUE'];
                    
                    $payment = $this->db->where('txn_id', $google_order_number)->get('partial_payments')->row_array();
                    
                    return array(
                            'unique_id' => $payment['unique_id'],
                            'txn_id' => $google_order_number,
                            'payment_gross' => $payment['amount'],
                            'transaction_fee' => 0,
                            'payment_date' => time(),
                            'payment_type' => 'instant',
                            'payer_status' => 'verified',
                            'item_name' => "-",
                            'payment_status' => 'Completed',
                            'is_paid' => 1,
                        );
                }
                    break;
                }
            default: {
                    break;
                }
        }
        die;
    }

}

function is_associative_array($var) {
    return is_array($var) && !is_numeric(implode('', array_keys($var)));
}

/* In case the XML API contains multiple open tags
  with the same value, then invoke this function and
  perform a foreach on the resultant array.
  This takes care of cases when there is only one unique tag
  or multiple tags.
  Examples of this are "anonymous-address", "merchant-code-string"
  from the merchant-calculations-callback API
 */

function get_arr_result($child_node) {
    $result = array();
    if (isset($child_node)) {
        if (is_associative_array($child_node)) {
            $result[] = $child_node;
        } else {
            foreach ($child_node as $curr_node) {
                $result[] = $curr_node;
            }
        }
    }
    return $result;
}