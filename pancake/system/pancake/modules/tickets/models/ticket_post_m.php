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
 * The Item Model
 *
 * @subpackage	Models
 * @category	Items
 */
class Ticket_post_m extends Pancake_Model {

    protected $validate = array(
        array(
            'field' => 'message',
            'label' => 'lang:global:message',
            'rules' => 'required|xss_clean',
        ),
    );

    public function get_user(&$post) {
        $this->load->model('users/user_m');
        $post->user = $post->user_id != null ? $this->user_m->get($post->user_id) : null;
    }

    function insert($data, $skip_validation = FALSE) {

        if (!isset($data['orig_filename'])) {
            $data['orig_filename'] = '';
        }

        if (!isset($data['real_filename'])) {
            $data['real_filename'] = '';
        }

        if (isset($data['message'])) {
            $data['message'] = purify_html($data['message']);
        }

        return parent::insert($data, $skip_validation);
    }

}

/* End of file: item_m.php */