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
class Paypal_m extends Gateway {

    public $title = 'PayPal';
    public $version = '0.2';
    public $autosubmit = true;
    public $fee = null;

    public function __construct() {
        parent::__construct(__CLASS__);

        $this->fields = array(
            'paypal_email' => lang('paypal:email'),
            'paypal_fee' => lang('paypal:fee'),
        );

        $this->fee = $this->get_field('paypal_fee');

        $this->load->library('paypal_lib');
    }

    public function generate_payment_form($unique_id, $item_name, $amount, $success, $cancel, $notify, $currency_code, $invoice_number) {
        # Let's round the amount.
        $amount = round($amount, 2);

        // Add the paypal fee to the amount
        $fee = $this->get_field('paypal_fee') ? ($amount * round($this->get_field('paypal_fee') / 100, 2)) : 0;

        // Setup the paypal request
        $this->paypal_lib->add_field('business', $this->get_field('paypal_email'));
        $this->paypal_lib->add_field('bn', "NullApps_SP");
        $this->paypal_lib->add_field('return', $success);
        $this->paypal_lib->add_field('cancel_return', $cancel);
        $this->paypal_lib->add_field('notify_url', $notify);
        $this->paypal_lib->add_field('item_name', $item_name);
        $this->paypal_lib->add_field('amount', round($amount + $fee, 2));
        $this->paypal_lib->add_field('currency_code', $currency_code);
        $this->paypal_lib->add_field('custom', $unique_id);
        $this->paypal_lib->button(lang('paypal:clickhere'));

        return $this->paypal_lib->paypal_form();
    }

    public function process_notification($unique_id) {

        if ($this->paypal_lib->validate_ipn()) {
            $subtotal = 0;
            $discount = 0;
            $total = 0;

            $errors = array();
            $ipn = $this->paypal_lib->ipn_data;

            switch ($ipn['payment_status']) {
                case 'Completed':
                    $gross = $this->paypal_lib->ipn_data['mc_gross'];


                    // If there is a surcharge for this gateway, work it out
                    if ($this->get_field('paypal_fee')) {
                        // 206 / ((3 / 100) + 1) = 200
                        if ((($this->get_field('paypal_fee') / 100) + 1) > 0) {
                            $gross_minus_fee = $gross / (($this->get_field('paypal_fee') / 100) + 1);
                        } else {
                            $gross_minus_fee = $gross;
                        }

                        // 206 - 200 = 6
                        $surcharge = $gross - $gross_minus_fee;
                    } else {
                        // We charged no surcharge
                        $gross_minus_fee = $gross;
                        $surcharge = 0;
                    }

                    return array(
                        'txn_id' => $this->paypal_lib->ipn_data['txn_id'],
                        'payment_gross' => round($gross_minus_fee, 2),
                        'transaction_fee' => $this->paypal_lib->ipn_data['mc_fee'],
                        'gateway_surcharge' => round($surcharge, 2),
                        'payment_date' => strtotime($this->paypal_lib->ipn_data['payment_date']),
                        'payment_type' => $this->paypal_lib->ipn_data['payment_type'],
                        'payer_status' => $this->paypal_lib->ipn_data['payer_status'],
                        'payment_status' => $this->paypal_lib->ipn_data['payment_status'],
                        'item_name' => $this->paypal_lib->ipn_data['item_name'],
                        'is_paid' => 1,
                    );
                    break;

                case 'Pending':
                case 'Processed':
                case 'Created':
                    # Pending.
                    break;

                case 'Expired':
                case 'Failed':
                case 'Denied':
                case 'Voided':
                    # Cancelled.
                    break;

                case 'Refunded':
                case 'Reversed':
                # Refunded
                case 'Canceled_Reversal':
                # Money's back in your pockets.
            }
        }
        
        # Got to this part without an error message being specified, but success didn't occur either, so it must've been an unknown error.
        if (!$this->has_errors()) {
            $this->error("An unknown error occurred while processing your transaction. Please try again.");
            return false;
        }
    }

}