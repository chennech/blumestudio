<?php

defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Pancake
 *
 * A simple, fast, self-hosted invoicing application
 *
 * @package        Pancake
 * @author        Pancake Dev Team
 * @copyright    Copyright (c) 2010, Pancake Payments
 * @license        http://pancakeapp.com/license
 * @link        http://pancakeapp.com
 * @since        Version 1.0
 */
// ------------------------------------------------------------------------

/**
 * The Settings Model
 *
 * @subpackage    Models
 * @category    Settings
 */
class Settings_m extends Pancake_Model {

    /**
     * @var string    The name of the settings table
     */
    protected $table = 'settings';

    public $inputs = array(
        "email_server",
        "smtp_host",
        "smtp_user",
        "smtp_pass",
        "smtp_port",
        "smtp_encryption",
        "secure_smtp_host",
        "secure_smtp_user",
        "secure_smtp_pass",
        "secure_smtp_port",
        "tls_smtp_host",
        "tls_smtp_user",
        "tls_smtp_pass",
        "tls_smtp_port",
        "mailpath",
        "gmail_user",
        "gmail_pass",
        "gapps_user",
        "gapps_pass",
    );

    /**
     * @var string    The primary key
     */
    protected $primary_key = 'slug';

    /**
     * @var bool    Tells the model to skip auto validation
     */
    protected $skip_validation = TRUE;

    public function update_settings($settings) {

        Currency::switch_default($settings['currency']);

        $this->db->trans_begin();

        foreach ($settings as $slug => $value) {
            // This ensures we are only updating what has changed
            if (PAN::setting($slug) != $value) {
                $this->db->where('slug', $slug)->update($this->table, array('value' => $value));
            }
        }

        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
            return FALSE;
        }

        $this->db->trans_commit();
        return TRUE;
    }

    function interpret_email_settings($settings = null) {
        if ($settings === null) {
            $settings = array(
                "email_type" => Settings::get('email_type'),
                "smtp_host" => Settings::get('smtp_host'),
                "smtp_user" => Settings::get('smtp_user'),
                "smtp_pass" => Settings::get('smtp_pass'),
                "smtp_port" => Settings::get('smtp_port'),
                "smtp_encryption" => Settings::get('smtp_encryption'),
                "email_secure" => Settings::get('email_secure'),
                "smtp_use_tls" => Settings::get('smtp_use_tls'),
            );
        }

        $type = $settings['email_type'];
        $host = str_ireplace('ssl://', '', $settings['smtp_host']);
        $user = $settings['smtp_user'];
        $pass = $settings['smtp_pass'];
        $port = $settings['smtp_port'];
        $encryption = isset($settings['smtp_encryption']) ? $settings['smtp_encryption'] : null;
        $secure = $settings['email_secure'];
        $gmail_user = '';
        $gmail_pass = '';

        $not_gmail_domain = (stristr($user, 'gmail.com') === false and stristr($user, 'googlemail.com') === false);

        if (stristr($host, 'gmail.com') !== false or stristr($host, 'googlemail.com') !== false) {
            $encryption = "tls";
            $port = "587";
            $type = 'gmail';

            if ($not_gmail_domain) {
                $gmail_pass = $pass;
                $gmail_user = $user;
            } else {
                $gmail_pass = $pass;
                $gmail_user = $user;
            }
        } elseif (stristr($host, 'ssl://') and $type == 'smtp') {
            $encryption = "ssl";
            $port = empty($port) ? 465 : $port;
        } elseif ($settings['smtp_use_tls'] == 1) {
            $encryption = "tls";
            $port = empty($port) ? 587 : $port;
        } elseif ($type != "smtp") {
            $type = "default";
        }

        $port = empty($port) ? 25 : $port;

        if ($secure) {
            $this->load->library('encrypt');
            $CI = &get_instance();
            $CI->load->model('settings/key_m');
            $email_encrypt = $CI->key_m->get_by(array("note" => 'email'));
            if (!empty($email_encrypt)) {
                $pass = $this->encrypt->decode($pass, $email_encrypt->key);
                $gmail_pass = $this->encrypt->decode($gmail_pass, $email_encrypt->key);
            }
        }

        return array(
            'type' => $type,
            'smtp_host' => $host,
            'smtp_user' => $user,
            'smtp_pass' => $pass,
            'smtp_port' => $port,
            'smtp_encryption' => $encryption,
            'gmail_user' => $gmail_user,
            'gmail_pass' => $gmail_pass,
        );
    }

    function random_key($length) {
        $random = '';
        for ($i = 0; $i < $length; $i++) {
            $random .= rand(0, 1) ? rand(0, 9) : chr(rand(ord('a'), ord('z')));
        }
        return $random;
    }

    function convert_input_to_settings($input) {
        $settings = array();
        $this->load->library('encrypt');
        $this->load->model('key_m');

        $email_encrypt = $this->key_m->get_by(array("note" => 'email'));

        if (empty($email_encrypt)) {
            $email_encrypt = new stdClass;
            $email_encrypt->key = $this->random_key(40);
            $this->key_m->insert_keys(array($email_encrypt->key), array('email'));
        }

        $type = $input['email_server'];
        $host = $input['smtp_host'];
        $user = $input['smtp_user'];
        $pass = $input['smtp_pass'];
        $pass = !empty($email_encrypt) ? $this->encrypt->encode($pass, $email_encrypt->key) : $pass;
        $port = $input['smtp_port'];
        $encryption = $input['smtp_encryption'];

        $gmail_user = $input['gmail_user'];
        $gmail_pass = $input['gmail_pass'];
        $gmail_pass = !empty($email_encrypt) ? $this->encrypt->encode($gmail_pass, $email_encrypt->key) : $gmail_pass;

        if (!empty($email_encrypt)) {
            $settings['email_secure'] = TRUE;
        } else {
            $settings['email_secure'] = FALSE;
        }

        if ($type == 'gmail') {
            $host = 'smtp.gmail.com';
            $user = $gmail_user;
            $pass = $gmail_pass;
            $type = 'smtp';
            $port = 587;
            $encryption = "tls";
        } elseif ($type == 'default') {
            $host = "";
            $user = "";
            $pass = "";
            $type = "default";
            $port = "";
            $encryption = "";
            $settings['email_secure'] = false;
        }

        # Reset the smtp_use_tls setting.
        # This is not used anymore, but if it was 1 then it'd be intepreted by interpret_email_settings()
        # as being the old "SMTP (TLS)" setting, so we reset it here when saving settings again.
        $settings['smtp_use_tls'] = 0;

        $settings['email_type'] = $type;
        $settings['smtp_host'] = $host;
        $settings['smtp_user'] = $user;
        $settings['smtp_pass'] = $pass;
        $settings['smtp_port'] = $port;
        $settings['smtp_encryption'] = $encryption;
        return $settings;
    }

    function save_email_settings($email) {
        foreach ($this->convert_input_to_settings($email) as $key => $value) {
            Settings::set($key, $value);
        }
        return true;
    }

    function get_languages() {
        $var = scandir(APPPATH . 'language/');
        $ret = array();
        foreach ($var as $row) {
            if ($row != '.' and $row != '..' and is_dir(APPPATH . 'language/' . $row)) {
                $ret[$row] = humanize($row);
            }
        }
        return $ret;
    }

    /**
     * Installs a new font family
     * This function maps a font-family name to a font.  It tries to locate the
     * bold, italic, and bold italic versions of the font as well.  Once the
     * files are located, ttf versions of the font are copied to the fonts
     * directory.  Changes to the font lookup table are saved to the cache.
     *
     * @param string $fontname the font-family name
     * @param string $normal the filename of the normal face font subtype
     * @param string $bold the filename of the bold face font subtype
     * @param string $italic the filename of the italic face font subtype
     * @param string $bold_italic the filename of the bold italic face font subtype
     *
     * @throws DOMPDF_Exception
     */
    function install_font_family($fontname, $normal, $bold = null, $italic = null, $bold_italic = null) {
        require_once APPPATH . 'libraries/dompdf/dompdf_config.custom.inc.php';
        require_once APPPATH . 'libraries/dompdf/dompdf_config.inc.php';

        Font_Metrics::init();

        // Check if the base filename is readable
        if (!is_readable($normal)) {
            throw new DOMPDF_Exception("Unable to read '$normal'.");
        }

        $dir = dirname($normal);
        $basename = basename($normal);
        $last_dot = strrpos($basename, '.');
        if ($last_dot !== false) {
            $file = substr($basename, 0, $last_dot);
            $ext = strtolower(substr($basename, $last_dot));
        } else {
            $file = $basename;
            $ext = '';
        }

        if (!in_array($ext, array(".ttf", ".otf"))) {
            throw new DOMPDF_Exception("Unable to process fonts of type '$ext'.");
        }

        // Try $file_Bold.$ext etc.
        $path = "$dir/$file";

        $patterns = array(
            "bold" => array("_Bold", "b", "B", "bd", "BD"),
            "italic" => array("_Italic", "i", "I"),
            "bold_italic" => array("_Bold_Italic", "bi", "BI", "ib", "IB"),
        );

        foreach ($patterns as $type => $_patterns) {
            if (!isset($$type) || !is_readable($$type)) {
                foreach ($_patterns as $_pattern) {
                    if (is_readable("$path$_pattern$ext")) {
                        $$type = "$path$_pattern$ext";
                        break;
                    }
                }

                if (is_null($$type)) {
                    echo("Unable to find $type face file.\n");
                }
            }
        }

        $fonts = compact("normal", "bold", "italic", "bold_italic");
        $entry = array();

        // Copy the files to the font directory.
        foreach ($fonts as $var => $src) {
            if (is_null($src)) {
                $entry[$var] = DOMPDF_FONT_DIR . mb_substr(basename($normal), 0, -4);
                continue;
            }

            // Verify that the fonts exist and are readable
            if (!is_readable($src)) {
                throw new DOMPDF_Exception("Requested font '$src' is not readable");
            }

            $dest = DOMPDF_FONT_DIR . basename($src);

            if (!is_writeable(dirname($dest))) {
                throw new DOMPDF_Exception("Unable to write to destination '$dest'.");
            }

            echo "Copying $src to $dest...\n";

            if (!copy($src, $dest)) {
                throw new DOMPDF_Exception("Unable to copy '$src' to '$dest'");
            }

            $entry_name = mb_substr($dest, 0, -4);

            echo "Generating Adobe Font Metrics for $entry_name...\n";

            $font_obj = Font::load($dest);
            $font_obj->saveAdobeFontMetrics("$entry_name.ufm");

            $entry[$var] = $entry_name;
        }

        // Store the fonts in the lookup table
        Font_Metrics::set_font_family($fontname, $entry);

        // Save the changes
        Font_Metrics::save_font_families();
    }

}

/* End of file: settings_m.php */