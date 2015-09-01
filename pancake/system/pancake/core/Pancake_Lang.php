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
 * @license		http://pancakeapp.com/license
 * @link		http://pancakeapp.com
 * @since		Version 4.3.6
 */
// ------------------------------------------------------------------------

/**
 * The lang controller, extended to add support for our : notation.
 *
 * @subpackage Controllers
 */
class Pancake_Lang extends MX_Lang {

    public $current_language;
    protected static $available_languages;
    protected $loaded_files = array();
    public $english_cache = array();

    /**
     * HTTP Request Instance
     * @var HTTP_Request
     */
    protected $http;

    public function load($langfile, $lang = '', $return = FALSE, $_module = NULL) {
        $result = parent::load($langfile, $lang, $return, $_module);
        if ($result) {
            if ($lang == '') {
                $lang = 'english';
            }
            if ($lang == 'english') {
                $this->english_cache = $this->language;
            }
            $this->loaded_files[] = $langfile;
            $this->loaded_files = array_unique($this->loaded_files);
        }
        return $result;
    }

    public function download_language($language) {
        try {
            $buffer = $this->get_github_contents("/$language");

            # @todo, list all files and download all files, storing them with the help of the update system.

        } catch (Exception $e) {
            deal_with_no_internet(true, "https://api.github.com");
        }
    }

    public function get_unofficial_languages() {
        try {
            $buffer = $this->get_github_contents();
            $languages = array();
            foreach ($buffer as $item) {
                if ($item['type'] == "dir" and $item['name'] !== "english") {
                    $languages[$item['name']] = $item['sha'];
                }
            }

            return $languages;
        } catch (Exception $e) {
            return array();
        }
    }

    protected function get_github_contents($path = "/") {
        if ($this->http === null) {
            include_once APPPATH . 'libraries/HTTP_Request.php';
            $this->http = new HTTP_Request();
        }

        $url = "https://api.github.com/repos/pancakeapp/translations/contents{$path}";
        $contents = $this->http->request($url);
        return json_decode($contents, true);
    }

    public function switch_language($new_language) {
        if (self::$available_languages === null) {
            self::$available_languages = get_instance()->settings_m->get_languages();
        }

        if (empty($new_language) or $this->current_language == $new_language) {
            # No need to change anything.
            return;
        }

        if (!in_array($new_language, array_keys(self::$available_languages))) {
            # Cannot change to this language; it does not exist.
            return;
        }

        $loaded_files_buffer = $this->loaded_files;

        # Fixes an issue where the default pancake file wouldn't be loaded automatically.
        $loaded_files_buffer[] = "pancake";

        # Loads the custom language file.
        if (file_exists(APPPATH."language/{$new_language}/custom_lang.php")) {
            $loaded_files_buffer[] = "custom";
        }

        $loaded_files_buffer = array_unique($loaded_files_buffer);
        unset($loaded_files_buffer[array_search("migration", $loaded_files_buffer)]);

        # Reset the currently-loaded language info.
        $this->language = array();
        $this->is_loaded = array();

        # Reload all the language files.
        foreach ($loaded_files_buffer as $file) {
            $this->load($file, $new_language);
        }
        $this->current_language = $new_language;
    }

}
