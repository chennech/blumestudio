<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Pancake
 *
 * A simple, fast, self-hosted invoicing application
 *
 * @package		Pancake
 * @author		Pancake Dev Team
 * @copyright           Copyright (c) 2014, Pancake Payments
 * @license		https://pancakeapp.com/license
 * @link		https://pancakeapp.com
 * @since		Version 4.6.0
 */
// ------------------------------------------------------------------------

/**
 * The Clients Taxes Model
 *
 * @subpackage	Models
 * @category	Clients
 */
class Clients_taxes_m extends Pancake_Model {

    protected $table = 'clients_taxes';

    function store($client_id, $tax) {
        $data = array();
        foreach ($tax as $tax_id => $tax_number) {
            $data[] = array(
                "client_id" => $client_id,
                "tax_id" => $tax_id,
                "tax_registration_id" => $tax_number
            );
        }

        $this->db->where("client_id", $client_id)->delete($this->table);
        if (count($data) > 0) {
            return $this->db->insert_batch($this->table, $data);
        } else {
            return true;
        }
    }

    function fetch($client_id) {
        $return = array();
        $buffer = $this->db->select("tax_id, tax_registration_id")->where("client_id", $client_id)->get($this->table)->result_array();
        foreach ($buffer as $field) {
            $field['tax_registration_id'] = trim($field['tax_registration_id']);
            if (!empty($field['tax_registration_id'])) {
                $return[$field['tax_id']] = $field['tax_registration_id'];
            }
        }

        return $return;
    }

}
