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
 * The Plugins Model
 *
 * @subpackage	Models
 * @category	Plugins
 */
class Plugins_m extends Pancake_Model
{	
	/**
	 * @var string	The name of the settings table
	 */
	protected $table = 'plugins';

	/**
	 * @var string	The primary key
	 */
	protected $primary_key = 'slug';

	/**
	 * @var bool	Tells the model to skip auto validation
	 */
	protected $skip_validation = TRUE;

	/**
	 * Return stored values for plugins.
	 * @param string $key Associative key by which to retreive settings.
	 */
	public function get_plugin_setting($key){
		return Plugins::get($key);
	}

	/**
	 * Key/Value store for plugin settings.
	 * @param string $key Associative key by which to retreive settings.
	 * @param mixed $value Value to store.
	 */
	public function set_plugin_setting($key,$value){
		return Plugins::set($key,$value);
	}

	/**
	 * Return a list of all found plugins.
	 */
	public function get_all_present(){
		$directories = $this->config->item('plugin_directories');
		
		$plug_ins = array();

		foreach ($directories as $directory){
			if(is_dir($directory)){
				$plugins = scandir($directory);
				
				foreach($plugins as $k=>$plugin){
					$plugin_dir = $directory.$plugin;
					
					$path = $plugin_dir.'/plugin.php';

					if(is_dir($plugin_dir) && file_exists($path)){
						$plug_ins[$plugin]=$path;
					}
				}
			}
		}
 
		return $plug_ins;
	}

	public function get_all_with_details(){
		$plugins = $this->get_all_present();
		
		$plugin_details = array();

		foreach($plugins as $plugin=>$path){
			$class_name = $class_name = 'Plugin_'.ucfirst($plugin);
			if(class_exists($class_name)){
				$plugin_details[$plugin] = new stdClass;

				$instance = new $class_name;

				$plugin_details[$plugin]->author = property_exists($instance, 'author') ? $instance->author : 'undefined';
				
				$plugin_details[$plugin]->url = property_exists($instance, 'url') ? $instance->url : 'undefined';

				$plugin_details[$plugin]->alias = property_exists($instance, 'alias') ? $instance->alias : 'undefined';
				
				$plugin_details[$plugin]->installed = $this->get_plugin_setting($plugin_details[$plugin]->alias.'_installed');

				$plugin_details[$plugin]->name = property_exists($instance, 'name') ? $instance->name['en'] : 'undefined';

				$plugin_details[$plugin]->description = property_exists($instance, 'description') ? $instance->description['en'] : 'undefined';

				$plugin_details[$plugin]->fields = property_exists($instance, 'config') ? $instance->config['fields'] : array();

				if(count($plugin_details[$plugin]->fields)<1)
					continue;

				foreach($plugin_details[$plugin]->fields as &$field){
					$val = $this->get_plugin_setting($field['name']);
					$field['value'] = isset($val) ? $val : $field['default'];
				}

			}
		}

		return $plugin_details;
	}

	private function _get_plugin_details(){

	}
}