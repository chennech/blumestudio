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
 * Settings library for easily accessing them
 *
 * @subpackage	Libraries
 * @category	Settings
 */
class Settings {

    /**
     * @var	object	The CI global object
     */
    private $_ci;

    /**
     * @var	array	Holds the settings from the db
     */
    private static $_settings = array();

    /**
     * @var	array	Holds the taxes from the db
     */
    private static $_taxes = array();

    /**
     * @var	array	Holds the taxes from the db
     */
    private static $_currencies = array();
    protected static $_sensitive_settings = array(
        "ftp_host",
        "ftp_pass",
        "ftp_pasv",
        "ftp_path",
        "ftp_port",
        "ftp_user",
        "latest_blogpost",
        "license_key",
        "main_warning",
        "mailpath",
        "rss_password",
        "smtp_host",
        "smtp_pass",
        "smtp_port",
        "smtp_encryption",
        "smtp_user",
        "smtp_use_tls",
        "store_auth_email",
        "store_auth_token",
        "tls_smtp_host",
        "tls_smtp_pass",
        "tls_smtp_port",
        "tls_smtp_user",
        "top_warning",
        "version_list",
    );

    // ------------------------------------------------------------------------

    /**
     * Loads in the CI global object and loads the settings module
     *
     * @access	public
     * @return	void
     */
    public function __construct() {
        $this->_ci = & get_instance();
        $this->_ci->load->model('settings/settings_m');
        $this->_ci->load->model('settings/tax_m');
        $this->_ci->load->model('settings/currency_m');

        $this->reload();
    }

    // ------------------------------------------------------------------------

    /**
     * This allows you to get the settings like this:
     *
     * $this->settings->setting_name
     *
     * @access	public
     * @param	string	The name of the setting
     * @return	string	The setting value
     */
    public function __get($name) {
        return Settings::get($name);
    }

    public static function setVersion($version) {
        return self::set('version', $version);
    }

    public static function get_tax_percentage($tax_id) {
        if (isset(Settings::$_taxes[$tax_id])) {
            return Settings::$_taxes[$tax_id]['value'];
        } else {
            return null;
        }
    }

    public static function get_tax($percentage, $name = '') {
        $CI = &get_instance();
        $taxes = $CI->db->where('value', $percentage)->get('taxes')->result_array();

        if (count($taxes) == 0) {
            $CI->db->insert('taxes', array(
                'name' => empty($name) ? 'Tax' : $name,
                'value' => $percentage
            ));

            $id = $CI->db->insert_id();
            $CI->settings->reload();

            return $id;
        } else {
            foreach ($taxes as $record) {
                if (empty($name)) {
                    return (int) $record['id'];
                } else {
                    if ($record['name'] == $name) {
                        return (int) $record['id'];
                    }
                }
            }

            if (!empty($name)) {
                # Name is not empty and no tax with the same name was yet found, so let's create one.
                $CI->db->insert('taxes', array(
                    'name' => empty($name) ? 'Tax' : $name,
                    'value' => $percentage
                ));
                $taxes = $CI->db->where('value', $percentage)->where('name', $name)->get('taxes')->row_array();
                $id = (int) $taxes['id'];

                $CI->settings->reload();
                return $id;
            }
        }
    }

    public static function set($name, $value) {
        if (array_key_exists($name, Settings::$_settings)) {
            $CI = &get_instance();
            $CI->db->where('slug', $name)->update('settings', array('value' => $value));
            $CI->settings->reload();
        } else {
            Settings::create($name, $value);
        }
    }

    public function __set($name, $value) {
        self::set($name, $value);
    }

    // ------------------------------------------------------------------------

    /**
     * This allows you to get the settings like this, which is ideal for
     * use in views:
     *
     * Settings::get('setting_name');
     *
     * @static
     * @access	public
     * @param	string	The name of the setting
     * @return	string	The setting value
     */
    public static function get($name) {
        if (!array_key_exists($name, Settings::$_settings)) {
            return FALSE;
        }
        return trim(Settings::$_settings[$name]);
    }

    public static function get_latest_blog_post() {
        $latest_blogpost = Settings::get("latest_blogpost");
        $return = false;
        if ($latest_blogpost) {
            $return = json_decode($latest_blogpost, true);
        }
        return $return;
    }

    public static function create($name, $value) {

        if (array_key_exists($name, Settings::$_settings)) {
            # Already exists.
            return true;
        }

        $CI = &get_instance();
        if ($CI->db->where('slug', $name)->count_all_results('settings') == 0) {
            $result = $CI->db->insert('settings', array('slug' => $name, 'value' => $value));
            $CI->settings->reload();
            return $result;
        } else {
            return true;
        }
    }

    public static function delete($name) {
        $CI = &get_instance();
        $result = $CI->db->where('slug', $name)->delete('settings');
        $CI->settings->reload();
        return $result;
    }

    // ------------------------------------------------------------------------

    /**
     * Returns all of the settings, excluding the sensitive settings like FTP, License, Email, etc.
     *
     * @access	public
     * @return	array	An array containing all the settings
     */
    public static function get_all() {
        static $return = null;

        if ($return === null) {
            $return = array();
            foreach (self::$_settings as $setting_name => $setting_value) {
                if (!in_array($setting_name, self::$_sensitive_settings)) {
                    $return[$setting_name] = $setting_value;
                }
            }
        }

        return $return;
    }
    
    /**
     * Returns all of the settings, including the sensitive settings like FTP, License, Email, etc.
     *
     * @access	public
     * @return	array	An array containing all the settings
     */
    public static function get_all_including_sensitive() {
        return self::$_settings;
    }

    // ------------------------------------------------------------------------

    /**
     * Returns all of the taxes
     *
     * @access	public
     * @return	array	An array containing all the settings
     */
    public static function all_taxes() {
        return Settings::$_taxes;
    }

    // ------------------------------------------------------------------------

    /**
     * Returns all of the taxes
     *
     * @access	public
     * @return	array	An array containing all the currencies
     */
    public static function all_currencies() {
        return Settings::$_currencies;
    }

    // ------------------------------------------------------------------------

    /**
     * This allows you to get the currency like this, which is ideal for
     * use in views:
     *
     * Settings::currency(1);
     *
     * @static
     * @access	public
     * @param	string	The id of the tax
     * @return	string	The tax
     */
    public static function currency($id) {
        if (!array_key_exists($id, Settings::$_currencies)) {
            return FALSE;
        }
        return Settings::$_currencies[$id];
    }

    // ------------------------------------------------------------------------

    /**
     * This allows you to get the tax like this, which is ideal for
     * use in views:
     *
     * Settings::tax(1);
     *
     * @static
     * @access	public
     * @param	string	The id of the tax
     * @return	string	The tax
     */
    public static function tax($id) {
        if (!array_key_exists($id, Settings::$_taxes)) {
            return FALSE;
        }
        return Settings::$_taxes[$id];
    }

    // ------------------------------------------------------------------------

    /**
     * Gets the dropdown for all the taxes
     *
     * @static
     * @access	public
     * @return	array	The tax dropdown array
     */
    public static function tax_dropdown() {
        $return = array(0 => 'No Tax');

        foreach (Settings::$_taxes as $id => $tax) {
            $return[$id] = $tax['name'] . ' (' . $tax['value'] . '%)';
        }
        return $return;
    }

    public static function get_default_tax_ids() {
        $buffer = explode(",", Settings::get('default_tax_id'));
        $return = array();
        foreach ($buffer as $id) {
            $return[$id] = $id;
        }

        return $return;
    }

    // ------------------------------------------------------------------------

    /**
     * This reloads the settings in from the database.
     *
     * @access	public
     * @return	void
     */
    public function reload() {
        Settings::$_taxes = array();
        Settings::$_currencies = array();
        Settings::$_settings = array();

        foreach ($this->_ci->settings_m->get_all() as $setting) {
            Settings::$_settings[$setting->slug] = $setting->value;
        }

        $name = "version";
        $real_version = file_get_contents(APPPATH . 'VERSION');
        if (isset(Settings::$_settings[$name])) {
            if (Settings::$_settings[$name] != $real_version) {
                $this->_ci->db->where('slug', $name)->update('settings', array('value' => $real_version));
            }
        } else {
            $this->_ci->db->insert('settings', array('slug' => $name, 'value' => $real_version));
        }

        Settings::$_settings[$name] = $real_version;

        foreach ($this->_ci->tax_m->get_all() as $tax) {
            Settings::$_taxes[$tax->id] = array(
                'name' => $tax->name,
                'value' => $tax->value,
                'is_compound' => isset($tax->is_compound) ? $tax->is_compound : 0,
            );

            if (isset($tax->reg)) {
                Settings::$_taxes[$tax->id]['reg'] = $tax->reg;
            }
        }

        $versions_without_currencies = array('1.0', '1.1', '1.1.1', '1.1.2', '1.1.3', '1.1.4', '2.0', '2.0.1', '2.0.2');

        if (in_array(Settings::$_settings['version'], $versions_without_currencies)) {
            # This version does not have currencies, but needs to work till the upgrade is over.
            $currencies = array();
        } else {
            $currencies = @$this->_ci->currency_m->get_all();
        }

        foreach ($currencies as $currency) {
            Settings::$_currencies[$currency->id] = array(
                'name' => $currency->name,
                'code' => $currency->code,
                'rate' => $currency->rate,
            );
        }

        $tz = Settings::get('timezone');
        if (empty($tz)) {
            $tz = @date_default_timezone_get();
        }
        date_default_timezone_set($tz);
    }

    public static function fiscal_year_start() {
        $start_day = Settings::get("year_start_day");
        $start_month = Settings::get("year_start_month");

        $this_year = mktime(null, null, null, $start_month, $start_day);
        $last_year = mktime(null, null, null, $start_month, $start_day, date("Y") - 1);
        $now = time();

        if ($now >= $this_year) {
            # Fiscal Year started this year (e.g. It's September 2014 and it started on April 6th 2014
            return $this_year;
        } else {
            # Fiscal Year started last year (e.g. It's January 2014 and it started on April 6th 2013).
            return $last_year;
        }
    }

}

/* End of file: Settings.php */