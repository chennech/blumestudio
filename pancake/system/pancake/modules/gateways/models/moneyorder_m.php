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
 * The Cash Gateway
 *
 * @subpackage	Gateway
 * @category	Payments
 */
class Moneyorder_m extends Gateway {
    
    public $version = '1.0';
    public $has_payment_page = false;
    
    function __construct() {
        parent::__construct(__CLASS__);
        $this->title = lang('moneyorder:moneyorder');
        $this->notes = __('gateways:just_for_logging', array($this->title));
    }

    public function generate_payment_form($unique_id, $item_name, $amount, $success, $cancel, $notify, $currency_code, $invoice_number) {
        
    }

    public function process_cancel($unique_id) {
        
    }

    public function process_success($unique_id, $amount) {
        
    }

    public function process_notification($unique_id) {
        
    }

}