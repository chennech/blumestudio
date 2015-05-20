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
 * The Files Model
 *
 * @subpackage	Models
 * @category	Files
 */
class Files_m extends Pancake_Model {

    /**
     * @var	string	The name of the files table
     */
    protected $table = 'files';

    public function get_by_unique_id($unique_id) {
		return $this->db->where('invoice_unique_id', $unique_id)->get($this->table)->result_array();
    }

    /**
     * Uploads the files.
     *
     * @access	public
     * @param	array	The $_FILES['input_name']
     * @param	string	The unique id
     * @return	void
     */
    public function upload($input, $unique_id) {

    	$type="invoice";

    	switch($unique_id){
    		case 'settings':
    			$type="settings";
    		break;
    		case 'tickets':
    			$type="tickets";
    		break;
    	}

		$return = pancake_upload($input, $unique_id, $type);

		if (!$return) {
		    return FALSE;
		}
		
		if ($return === NOT_ALLOWED) {
		    return NOT_ALLOWED;
		}

		switch($unique_id){
			case 'settings':
				return $return;
			break;
			case 'tickets':
				return $return;
			break;
			default:
				foreach ($return as $real_name => $file) {
					//hmm...
					$result = parent::insert(array(
						'invoice_unique_id' => $unique_id,
						'orig_filename' => $real_name,
						'real_filename' => $file['folder_name'] . $real_name
					));
				}
			return true;
			break;
		}
	}
    
    public function verify_uploads($input) {
		return pancake_upload($input, 'test', 'invoice', 0, true);
    }

    public function delete($file_id) {
		$file = parent::get($file_id);
		if (!empty($file)) {
		    parent::delete($file_id);

		    if (is_file('uploads/' . $file->real_filename)) {
			@unlink('uploads/' . $file->real_filename);
			$parts = explode('/', $file->real_filename);
			@rmdir($parts[0]);
		    }
		}
    }

}

/* End of file: settings_m.php */