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
 * @since		Version 3.2.6
 */
// ------------------------------------------------------------------------

/**
 * The Stripe Gateway
 *
 * @subpackage	Gateway
 * @category	Payments
 */
class Stripe_m extends Gateway {

    public $requires_https = true;
    public $requires_pci = false;

    function __construct() {
        parent::__construct(__CLASS__);
        $this->title = 'Stripe';
        $this->frontend_title = __('paypalpro:viacreditcard');
        $this->use_field_names = false;
        $publishable_key = $this->get_field('publishable_key');
        $this->custom_head = <<<stuff
<script type="text/javascript" src="https://js.stripe.com/v1/"></script><script type="text/javascript">Stripe.setPublishableKey('$publishable_key');
$(function() {
  $('#payment-form').submit(function(event) {
    // Disable the submit button to prevent repeated clicks
    $('.submit-button').prop('disabled', true);

    Stripe.createToken({
	  name: $('#cc_name').val(),
      number: $('#cc_number').val(),
      cvc: $('#cc_code').val(),
      exp_month: $('#cc_exp_m').val(),
      exp_year: $('#cc_exp_y').val()
    }, stripeResponseHandler);

    // Prevent the form from submitting with the default action
    return false;
  });
});      

function stripeResponseHandler(status, response) {
  if (response.error) {
    // Show the errors on the form
    $('.errors').text(response.error.message);
    $('.submit-button').prop('disabled', false);
  } else {
    var \$form = $('#payment-form');
    // token contains id, last4, and card type
    var token = response.id;
    // Insert the token into the form so it gets submitted to the server
    \$form.append($('<input type="hidden" name="client_fields[stripe_token]" />').val(token));
    // and submit
    \$form[0].submit();
  }
}

</script>
stuff;

        if (!defined('GATEWAY_API_KEY')) {
            define('GATEWAY_API_KEY', $this->get_field('publishable_key'));
        }

        $this->fields = array(
            'api_key' => "Stripe Secret Key",
            'publishable_key' => "Stripe Publishable Key",
        );

        $this->client_fields = array(
			'cc_name' => array(
                'label' => __('gateways:cc_cardholder')
            ),
            'cc_number' => array(
                'label' => __('gateways:cc_number')
            ),
            'cc_code' => array(
                'label' => __('gateways:cc_code')
            ),
            'cc_exp' => array(
                'type' => 'mmyyyy',
                'label' => __('gateways:cc_exp')
            ),
        );
    }

    public function generate_payment_form($unique_id, $item_name, $amount, $success, $cancel, $notify, $currency_code, $invoice_number) {               
        
        $CI = &get_instance();
	$CI->load->model('invoices/partial_payments_m', 'ppm');
	
        $original_amount = $amount;
        

		/* 
			I believe this is left over from when Stripe was only available in the US, now that it is available in Canada it is no longer relevant.
		
		*/
		
        # Convert $amount to USD if $currency_code is NOT USD
		// if ($currency_code != 'USD') {
		//    $amount = $CI->ppm->getUsdAmountByAmountAndUniqueId($amount, $unique_id);
		// }
        
        # Let's round the amount.
        $amount = number_format($amount, 2, '.', '');
        
        $token = $this->get_client_field('stripe_token');
        
        include APPPATH.'sparks/codeigniter-payments/0.1.4/src/php-payments/vendor/stripe/lib/Stripe.php';
        
		
		// Formatting total for Stripe
	
		$total = (float)$amount;
		$formatted_total = round($total,2)*100;
		
		$invoice_number = 
		
        Stripe::setApiKey($this->get_field('api_key'));
        try {
            $details = Stripe_Charge::create(array("amount" => $formatted_total,
                "currency" => $currency_code,
                "card" => $token, 
				"description" => $item_name));
            
            return array(
                'txn_id' => $details->id, // the gateway transaction ID
                'payment_gross' => round($original_amount, 2), // the amount paid, rounded to 2 decimal places
                'transaction_fee' => 0, // the fee charged by the gateway, rounded to 2 decimal places
                'payment_date' => $details->created, // a UNIX timestamp for the payment date
                'payment_status' => 'Completed', // One of: Completed/Pending/Refunded/Unpaid
                'item_name' => $item_name, // the item name (passed to the gateway in generate_payment_form())
                'is_paid' => true, // true or false, depending on whether payment was successful or not
            );
        } catch (Exception $e) {
            $error = $e->getMessage();
            $this->error($error);
            return false;
        }
    }

    public function process_cancel($unique_id) {
        
    }

    public function process_success($unique_id, $amount) {
        
    }

    public function process_notification($unique_id) {
        
    }

}