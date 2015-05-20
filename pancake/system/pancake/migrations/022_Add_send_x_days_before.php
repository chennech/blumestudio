<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_add_send_x_days_before extends CI_Migration {
    function up() {
        add_column('invoices', 'send_x_days_before', 'int', 11, 7);
        
        $result = $this->db->query("SHOW COLUMNS FROM " . $this->db->dbprefix('invoices') . " LIKE 'has_sent_notification'")->row_array();
        if (!isset($result['Field']) or $result['Field'] != 'has_sent_notification') {
            $this->dbforge->add_column('invoices', array(
                'has_sent_notification' => array(
                    'type' => 'int',
                    'constraint' => 1,
                    'null' => FALSE,
                    'default' => 0,
                ),
            ));
        }
        
        if ($this->db->where('slug', 'send_x_days_before')->count_all_results('settings') == 0) {
            $this->db->insert('settings', array('value' => 7, 'slug' => 'send_x_days_before'));
        }
    }
    
    function down() {
        
    }
}