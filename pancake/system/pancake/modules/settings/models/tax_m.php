<?php defined('BASEPATH') OR exit('No direct script access allowed');
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
 * The Tax Model
 *
 * @subpackage	Models
 * @category	Settings
 */
class Tax_m extends Pancake_Model
{

	public function update_taxes($names, $values, $regs, $compounds)
	{
		$this->db->trans_begin();

		foreach ($names as $id => $name)
		{
			$this->db->where('id', $id);
			
			// Delete if the name is removed
			if ( ! $name)
			{
				$this->db->delete($this->table);
				continue;
			}
			
			$this->db->update($this->table, array(
				'name' => $name,
				'value' => $values[$id],
				'reg' => $regs[$id],
                                'is_compound' => isset($compounds[$id]),
			));

		}

		if ($this->db->trans_status() === FALSE)
		{
			$this->db->trans_rollback();
			return FALSE;
		}

		$this->db->trans_commit();
		return TRUE;
	}
        
        public function create_if_not_exists($percentage, $name = null) {
            $row = $this->db->where('value', $percentage)->get('taxes')->row_array();
            if (isset($row['name'])) {
                return $row['id'];
            } else {
                $percentage = round($percentage, 10);
                $this->db->insert('taxes', array(
                    'name' => $name === null ? "$percentage% Tax" : ($name . " ($percentage%)"),
                    'value' => $percentage
                ));
                
                return $this->db->insert_id();
            }
        }
        
        public function search($query) {
            $records = $this->db->select('id, name')->get('taxes')->result_array();

            $buffer = array();
            $details = array();
            $query = strtolower($query);

            foreach ($records as $row) {
                $subbuffer = array();
                $subbuffer[] = levenshtein($query, strtolower($row['name']), 1, 20, 20);

                sort($subbuffer);

                $buffer[$row['id']] = reset($subbuffer);
                $details[$row['id']] = $row['name'];
            }

            asort($buffer);
            $return = array();

            foreach (array_slice($buffer, 0, 3, true) as $id => $levenshtein) {
                $return[] = array(
                    'levenshtein' => $levenshtein,
                    'name' => $details[$id],
                    'id' => $id
                );
            }

            return $return;
        }

	public function insert_taxes($names, $values, $regs, $compounds)
	{
		$this->db->trans_begin();

		foreach ($names as $id => $name)
		{
			if ($name)
			{
				$this->db->insert($this->table, array(
					'name' => $name,
					'value' => $values[$id],
					'reg' => $regs[$id],
                                        'is_compound' => isset($compounds[$id]),
				));
			}
		}

		if ($this->db->trans_status() === FALSE)
		{
			$this->db->trans_rollback();
			return FALSE;
		}

		$this->db->trans_commit();
		return TRUE;
	}
}

/* End of file: tax_m.php */