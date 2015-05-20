<?php

defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Pancake
 *
 * A simple, fast, self-hosted invoicing application
 *
 * @package		Pancake
 * @author		Pancake Dev Team
 * @copyright		Copyright (c) 2011, Pancake Payments
 * @license		http://pancakeapp.com/license
 * @link		http://pancakeapp.com
 * @since		Version 3.1
 */
// ------------------------------------------------------------------------

/**
 * The Pancake Update System
 *
 * @subpackage	Models
 * @category	Upgrade
 */
class Update_system_m extends Pancake_Model {

    public $write = false;
    public $ftp = false;
    public $ftp_conn = false;
    public $version_hashes = null;
    public $error;

    /**
     * The construct.
     * 
     * Verifies if Pancake can write to itself or FTP to itself.
     */
    function __construct() {

        parent::__construct();

        include_once APPPATH . 'libraries/HTTP_Request.php';

        if (function_exists('getmyuid') && function_exists('fileowner')) {
            $test_file = FCPATH . 'uploads/test-' . time();
            $test = @fopen($test_file, 'w');
            if ($test) {
                if (function_exists('posix_getuid')) {
                    $posix = posix_getuid() == @fileowner($test_file);
                } else {
                    $posix = false;
                }
                $this->write = ((getmyuid() == @fileowner($test_file)) || $posix);
                @fclose($test);
                @unlink($test_file);
            } else {
                $this->write = false;
            }
        } else {
            $this->write = false;
        }

        if (!$this->write) {
            $user = Settings::get('ftp_user');
            $this->ftp = !empty($user);
        }

        if (Settings::get('latest_version') == '0') {
            $this->get_latest_version(true);
        }

        # I'm doing this here and not in a migration because we sometimes give people update_system_m.php
        # And it needs to be self-contained so they can just update without any problems.
        if (!$this->db->table_exists('updates')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS `' . $this->db->dbprefix('updates') . '` (
            `version` VARCHAR( 255 ) NOT NULL ,
            `hashes` LONGTEXT NOT NULL ,
            `suzip` LONGTEXT NOT NULL ,
            `changed_files` LONGTEXT NOT NULL ,
            `processed_changelog` LONGTEXT NOT NULL ,
            PRIMARY KEY (  `version` )
            ) ENGINE = MYISAM ;');
        }

        if (!$this->db->table_exists('update_files')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS `' . $this->db->dbprefix('update_files') . '` (
            `id` INT( 255 ) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
            `version` VARCHAR( 255 ) NOT NULL ,
            `filename` TEXT NOT NULL ,
            `data` LONGTEXT NOT NULL
            ) ENGINE = MYISAM ;');
        }
    }

    function get_error() {
        return __('update:' . $this->error);
    }

    /**
     * Checks if a given FTP configuration works with Pancake.
     * 
     * @param string $host
     * @param string $user
     * @param string $pass
     * @param integer $port
     * @param string $path
     * @param boolean $passive
     * @return boolean 
     */
    function test_ftp($host, $user, $pass, $port, $path, $passive) {
        $passive = (bool) $passive;
        $port = (int) $port;
        $path = (substr($path, strlen($path) - 1, 1) == '/') ? $path : $path . '/';

        $connection = @ftp_connect($host, $port);

        if (!$connection) {
            $this->error = 'ftp_conn';
            return false;
        } else {

            if (!@ftp_login($connection, $user, $pass)) {
                $this->error = 'ftp_login';
                return false;
            }

            ftp_pasv($connection, $passive);

            if (!ftp_chdir($connection, $path)) {
                $this->error = 'ftp_chdir';
                return false;
            }
        }

        $tmpNam = tempnam(sys_get_temp_dir(), 'test');

        if (@ftp_get($connection, $tmpNam, 'index.php', FTP_ASCII)) {
            if (stristr(file_get_contents($tmpNam), "require_once 'system/pancake/pancake_index.php'") !== false) {
                $uploaded = ftp_put($connection, 'uploads/test.txt', $tmpNam, FTP_BINARY);
                if ($uploaded) {
                    @ftp_delete($connection, 'uploads/test.txt');
                    return true;
                } else {
                    $this->error = 'ftp_no_uploads';
                    return false;
                }
            } else {
                $this->error = 'ftp_indexwrong';
                return false;
            }
            fclose($tmpFile);
        } else {
            # Couldn't get the file. I assume it's because the file didn't exist.
            $this->error = 'ftp_indexnotfound';
            return false;
        }

        return true;
    }

    /**
     * Gets the list of changes between $to and $from, in processed Markdown HTML.
     * 
     * If $return_empty_if_error, it will return an empty string if there is a problem downloading the changelog.
     * This is used to stop people getting a blank update notification.
     * 
     * @param string $to
     * @param string $from
     * @param boolean $return_empty_if_error
     * @return string
     */
    function get_processed_changelog($to, $from = null, $return_empty_if_error = false) {

        if ($from === null) {
            $from = Settings::get('version');
        }

        if ($to == $from) {
            return '';
        }

        $download = $this->cache_update();
        if (!$download and $return_empty_if_error) {
            return '';
        }

        $changelog = '';

        $changelog_versions = $this->get_versions_between_inclusive($to, $from);
        $changelog_versions = array_reverse($changelog_versions);

        require_once APPPATH . 'libraries/Markdown.php';

        foreach ($changelog_versions as $version) {
            if ($version != $from) {
                $buffer = $this->db->select('processed_changelog')->where('version', $version)->count_all_results('updates');

                if ($buffer == 0) {
                    $this->download_version($version);
                }

                $buffer = $this->db->select('processed_changelog')->where('version', $version)->get('updates')->row_array();
                $buffer = Markdown($buffer['processed_changelog']);
                if (empty($changelog)) {
                    $changelog = $buffer;
                } else {
                    $changelog = $changelog . '<h3>' . $version . '</h3>' . $buffer;
                }
            }
        }

        return $changelog;
    }

    function get_versions_between_inclusive($to = null, $from = null) {
        $return = array();
        $list = Settings::get('version_list');

        if ($to === null) {
            $to = Settings::get('latest_version');
        }

        if ($from === null) {
            $from = Settings::get('version');
        }

        if (empty($list)) {
            $this->download_version(Settings::get('version'));
            $list = Settings::get('version_list');
        }

        $list = explode('/', $list);
        $recording = false;

        foreach ($list as $version) {
            if ($version === $from) {
                $recording = true;
            }

            if ($recording) {
                $return[] = $version;
            }

            if ($version === $to) {
                break;
            }
        }

        return $return;
    }

    function verify_integrity() {
        $hashes = $this->get_current_version_hashes();
        $failed_hashes = array();
        $deleted_files = 0;
        $modified_files = 0;

        foreach ($hashes as $file => $hash) {
            if (substr($file, -strlen("/index.html")) == "/index.html" || $file == ".htaccess" || $file == "example.htaccess") {
                # Ignore index.html files.
                continue;
            }

            if (file_exists(FCPATH.$file)) {
                $new_hash = md5_file(FCPATH.$file);
                if ($new_hash != $hash) {
                    $failed_hashes[$file] = "M";
                    $modified_files++;
                }
            } else {
                $failed_hashes[$file] = "D";
                $deleted_files++;
            }
        }

        return array(
            "success" => count($failed_hashes) == 0,
            "modified_files" => $modified_files,
            "deleted_files" => $deleted_files,
            "failed_hashes" => $failed_hashes
        );
    }

    function cache_hashes() {
        if ($this->version_hashes === null) {
            $http = new HTTP_Request();
            $url = MANAGE_PANCAKE_BASE_URL . 'is_outdated/' . Settings::get('license_key') . '/' . Settings::get('version') . '/' . time();
            $buffer = json_decode($http->request($url), true);

            if ($buffer) {
                $this->version_hashes = $buffer['version_hashes'];
            }
        }
    }

    /**
     * Updates Pancake to the latest version.
     * 
     * If Pancake cannot write to itself via PHP, it will try to do so via FTP.
     * If it can't do it with either, it will return false.
     * 
     * @return boolean
     */
    function update_pancake($version, $refresh = true) {

        if (Settings::get('version') == $version) {
            # Already  done.
            return true;
        }

        # Gets missing versions, and updates each of them first, from oldest to newest.
        # That means that the first one it tries to update is the one where this array is empty and it can proceed.
        # And so on and so forth, until it finishes.
        $missing_versions = $this->get_versions_between_inclusive($version);

        foreach ($missing_versions as $version_to_update) {
            if (!empty($version_to_update)) {
                if ($version_to_update != $version) {
                    $this->update_pancake($version_to_update, false);
                }
            }
        }

        # Make sure this version is stored with the right hash.
        $this->download_version($version, true);

        if ($this->write or $this->ftp) {

            $update = $this->db->where('version', $version)->get('updates')->row_array();
            $changed_files = explode("\n", $update['changed_files']);

            $count = $this->db->where('version', $version)->count_all_results('update_files');

            if ($count == 0) {
                show_error("Problem with SUZIP. Please contact support@pancakeapp.com. Don't worry, your Pancake data is OK.");
            }

            foreach ($changed_files as $file) {
                $file = trim($file);
                if (!empty($file)) {

                    if (substr($file, 0, 5) == '[Modi') {
                        # Modified.
                        $file = substr($file, 11);
                        $data = $this->db->select('data')->where('version', $version)->where('filename', 'pancake/' . $file)->get('update_files')->row_array();
                        $base64 = base64_decode($data['data']);
                        $data = $base64 ? $base64 : $data['data'];
                        $this->set_file_contents(FCPATH . $file, $data);
                    } elseif (substr($file, 0, 5) == '[Adde') {
                        # Added.
                        $file = substr($file, 8);
                        $data = $this->db->select('data')->where('version', $version)->where('filename', 'pancake/' . $file)->get('update_files')->row_array();
                        $base64 = base64_decode($data['data']);
                        $data = $base64 ? $base64 : $data['data'];
                        $this->set_file_contents(FCPATH . $file, $data);
                    } else {
                        # Deleted.
                        $file = substr($file, 10);
                        $this->delete(FCPATH . $file);
                    }
                }
            }

            # Force migrations to run.
            get_url_contents(site_url('admin'), false);
        } else {
            $this->error = 'update_no_perms';
            return false;
        }
    }

    function get_current_version_hashes() {
        $result = $this->update->download_version(Settings::get('version'));

        if (!$result) {
            return null;
        } else {
            $current_version_hashes = $this->db->select('hashes')->where('version', Settings::get('version'))->get('updates')->row_array();
            if (!isset($current_version_hashes['hashes'])) {
                return null;
            } else {
                $current_version_hashes = explode("\n", $current_version_hashes['hashes']);
                $hashes = array();

                foreach ($current_version_hashes as $hash) {
                    $hash = trim($hash);
                    if (!empty($hash)) {
                        $hash = explode(' :.: ', $hash);
                        $file = $hash[0];
                        $hash = $hash[1];

                        $hashes[$file] = $hash;
                    }
                }
                return $hashes;
            }
        }
    }

    /**
     * Checks to see which files are different.
     * 
     * @param string $version 
     */
    function check_for_conflicts($ignore_fail = false, $retry = true) {

        $version = Settings::get('latest_version');

        # 1. check_for_conflicts is always called with latest version.
        # 2. So the conflict checking is meant to be from current version to latest version.
        # 3. So we get the hashes of current version, and then we look at the changed_files.txt 
        # of every version between version and latest_version, including latest_version.
        # 4. After doing this, we look at the merged array of all changed_files, and then we check if there's a conflict.
        # Download latest version if necessary, and all versions between version and latest_version.
        $result = $this->download_version($version);

        if (!$result and $ignore_fail) {
            return array();
        }

        # Cache all missing versions.
        $this->cache_update();

        $current_version_hashes = $this->db->select('hashes')->where('version', Settings::get('version'))->get('updates')->row_array();

        if (!isset($current_version_hashes['hashes'])) {
            if ($ignore_fail) {
                return array();
            }

            if ($retry) {
                $this->check_for_conflicts(false, false);
            } else {
                throw new Exception("An unknown error occurred while trying to download the update files.");
            }
        }

        $current_version_hashes = explode("\n", $current_version_hashes['hashes']);
        $hashes = array();
        $changed = array();
        $conflicted = array();

        foreach ($current_version_hashes as $hash) {
            $hash = trim($hash);
            if (!empty($hash)) {
                $hash = explode(' :.: ', $hash);
                $file = $hash[0];
                $hash = $hash[1];

                $hashes[$file] = $hash;
                if (file_exists(FCPATH . $file)) {
                    if (md5_file(FCPATH . $file) != $hashes[$file]) {
                        # File exists, but has been modified.
                        $changed[$file] = 'M';
                    }
                } else {
                    # File doesn't exist anymore.
                    $changed[$file] = 'D';
                }
            }
        }

        $missing_versions = explode('/', Settings::get('missing_versions'));
        foreach ($missing_versions as $version_to_check) {
            if (!empty($version_to_check)) {
                $changed_files = $this->db->where('version', $version_to_check)->get('updates')->row_array();
                $changed_files = explode("\n", $changed_files['changed_files']);

                foreach ($changed_files as $file) {
                    $file = trim($file);
                    if (!empty($file)) {
                        if (substr($file, 0, 5) == '[Modi') {
                            # Modified.
                            $file = substr($file, 11);
                        } elseif (substr($file, 0, 5) == '[Adde') {
                            # Added.
                            $file = substr($file, 8);
                        } else {
                            # Deleted.
                            $file = substr($file, 10);
                        }
                    }

                    if (isset($changed[$file])) {
                        $conflicted[$file] = $changed[$file];
                    }
                }
            }
        }

        return $conflicted;
    }

    function download_version($version, $check_hashes = false) {

        if (empty($version)) {
            return false;
        }

        if (function_exists("set_time_limit") == TRUE AND @ ini_get("safe_mode") == 0) {
            @set_time_limit(0);
        }

        $result = $this->db->where('version', $version)->get('updates')->row_array();

        if (isset($result['version']) and $result['version'] == $version) {
            if ($check_hashes) {
                # It's downloaded, so check the hash!
                $this->cache_hashes();

                if ($result['suzip'] != $this->version_hashes[$version]) {
                    # Redownload version!
                    $this->remove_version_files($version);
                    return $this->download_version($version);
                }
            }

            return true;
        }

        # Clear the DB before downloading.
        $this->remove_version_files($version);

        $http = new HTTP_Request();
        $url = MANAGE_PANCAKE_BASE_URL . 'packaged_version/' . Settings::get('license_key') . '/' . $version . '/' . Settings::get('version') . '/' . time();
        try {
            if (!isset($GLOBALS['HTTP_REQUESTS'])) {
                $GLOBALS['HTTP_REQUESTS'] = 0;
            }
            $GLOBALS['HTTP_REQUESTS'] ++;

            $buffer = json_decode($http->request($url), true);
            if ($buffer) {
                if ($buffer['allowed']) {
                    $hashes_content = $buffer['hashes'];
                    $suzip_hash = $buffer['suzip_hash'];
                    $suzip_parts = $buffer['suzip_parts'];
                    $changelog_content = $buffer['changelog'];
                    $changed_files_content = $buffer['changed_files'];
                } else {
                    echo "<div style='font-family:Helvetica, Arial, sans-serif;background:black;color:white;padding:1em;'>Your license key \"" . Settings::get('license_key') . "\" is not allowed to download Pancake $version.</div>";
                    $this->get_latest_version(true);
                    die;
                }
            } else {
                deal_with_no_internet(true, $url);
            }
        } catch (Exception $e) {
            # If this is being loaded manually (which it never should be), redirect.
            # Even if it's not loaded manually, it doesn't matter, because the result of this page is ignored.
            deal_with_no_internet(true, $url);
        }

        $GLOBALS['HTTP_REQUESTS'] ++;

        $current_part = 1;



        while ($current_part <= $suzip_parts) {
            $url = MANAGE_PANCAKE_BASE_URL . 'suzip/' . Settings::get('license_key') . '/' . $version . '/' . $current_part . '/' . Settings::get('version') . '/' . time();
            foreach (@unserialize(base64_decode((($http->request($url))))) as $file => $data) {
                $new_data = array(
                    'version' => $version,
                    'filename' => $file,
                );

                $base64 = base64_encode($data);

                if ($this->db->where($new_data)->count_all_results('update_files') == 0) {
                    $new_data['data'] = $base64;
                    $this->db->insert('update_files', $new_data);
                }
            }

            $current_part++;
        }

        # Only add this when the SUZIP is finished.
        # This makes it so that if the SUZIP download breaks half-way,
        # nothing bad happens and it just gets redownloaded.

        if ($this->db->where('version', $version)->count_all_results('updates') == 0) {
            $this->db->insert('updates', array(
                'version' => $version,
                'hashes' => $hashes_content,
                'suzip' => $suzip_hash,
                'changed_files' => $changed_files_content,
                'processed_changelog' => $changelog_content,
            ));
        }

        # Okay, it's been downloaded. Now, does the user want us to auto-update?
        if (Settings::get('auto_update')) {
            if ($this->write or $this->ftp) {
                if (count($this->check_for_conflicts()) == 0) {
                    # There are no conflicts, upgrade.
                    $this->update_pancake($version);
                }
            }
            return true;
        }

        return true;
    }

    /**
     * Fetches the latest version IFF more than 1 hour has passed since the last fetch.
     * 
     * Downloads the latest version and caches it if it finds it.
     * The latest version downloader will create a notification for the new update, or execute
     * the update instantly, depending on the settings.
     */
    function get_latest_version($force = false) {
        $next_fetch = strtotime('+1 hours', Settings::get('latest_version_fetch'));
        if ($next_fetch < time() or $force) {
            $http = new HTTP_Request();
            try {
                Settings::set('latest_version_fetch', time());

                if (!isset($GLOBALS['HTTP_REQUESTS'])) {
                    $GLOBALS['HTTP_REQUESTS'] = 0;
                }
                $GLOBALS['HTTP_REQUESTS'] ++;
                $current = Settings::get('version');
                $url = MANAGE_PANCAKE_BASE_URL . 'is_outdated/' . Settings::get('license_key') . '/' . $current . '/' . base64_encode(BASE_URL) . '/' . time();
                $buffer = json_decode($http->request($url), true);
                $to_download = array();
                $latest = $current;

                if ($buffer) {
                    Settings::set("latest_blogpost", json_encode($buffer["latest_blogpost"]));

                    if ($buffer['outdated']) {
                        $latest = $buffer['latest_version'];
                        $to_download = $buffer['missing_versions'];
                        $version_list = $buffer['version_list'];

                        Settings::set('latest_version', trim($latest));
                        if (is_array($to_download)) {
                            Settings::set('missing_versions', implode('/', $to_download));
                        }
                        if (is_array($version_list)) {
                            Settings::set('version_list', implode('/', $version_list));
                        }

                        $this->db->truncate('updates');
                        $this->db->truncate('update_files');
                    } else {
                        Settings::set('latest_version', Settings::get('version'));
                    }
                }

                // Now check for plugin updates:
                $CI = get_instance();
                $CI->load->model('store/store_m');
                $CI->store_m->check_for_updates();


                return $latest;
            } catch (Exception $e) {
                deal_with_no_internet(false, MANAGE_PANCAKE_BASE_URL . 'VERSION?' . time());
            }
        }
    }

    function cache_update() {
        # Download current version, for HASHES.
        $result = $this->update->download_version(Settings::get('version'));

        if (!$result) {
            return false;
        }

        # Download all missing versions.
        $missing = explode('/', Settings::get('missing_versions'));
        foreach ($missing as $version) {
            if (!empty($version)) {
                if (!$this->update->download_version($version)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Creates a file with $data if it doesn't exist, or updates it with $data if it exists.
     * 
     * If Pancake cannot write to itself via PHP, it will try to do so via FTP.
     * If it can't do it with either, it will return false.
     * 
     * $filename is ABSOLUTE, and starts with FCPATH.
     * 
     * @param string $filename
     * @param string $data
     * @return boolean 
     */
    function set_file_contents($filename, $data) {
        if ($this->write) {

            $dir = dirname($filename);
            $dir = str_ireplace(rtrim(FCPATH, '/\\'), '', $dir);
            $dir = explode("/", $dir);
            $path = "";

            for ($i = 0; $i < count($dir); $i++) {
                $path.= $dir[$i] . '/';

                if ($path != '/' and ! file_exists(FCPATH . $path)) {
                    if (!@mkdir(FCPATH . $path)) {
                        show_error('A problem occurred while trying to create a folder when updating Pancake. (Extra error information: ' . FCPATH . ' - ' . $filename . ' - ' . $path . ') Please send an email to support@pancakeapp.com immediately, letting us know.');
                    } else {
                        # CHMOD recently-created update folder just in case.
                        if (stristr($path, 'pancake-update-system') !== false) {
                            @chmod(FCPATH . $path, 0777);
                        }
                    }
                }
            }

            $result = (bool) file_put_contents($filename, $data);
            @chmod($filename, 0755);
            return $result;
        } elseif ($this->ftp) {
            $filename = str_ireplace(FCPATH, '', $filename);
            $connection = $this->getFtpConnection();

            # Create the folder where the file is in, if it does not exist. Recursive.
            $dir = explode("/", dirname($filename));
            $path = "";

            for ($i = 0; $i < count($dir); $i++) {
                $path.= $dir[$i] . '/';

                $origin = ftp_pwd($connection);

                if (!@ftp_chdir($connection, $path)) {
                    if (!@ftp_mkdir($connection, $path)) {
                        return false;
                    } else {
                        # CHMOD recently-created update folder just in case.
                        if (stristr($path, 'pancake-update-system') !== false) {
                            @ftp_chmod($connection, 0777, $path);
                        }
                    }
                }

                ftp_chdir($connection, $origin);
            }
            $tmpNam = tempnam(sys_get_temp_dir(), 'test');
            file_put_contents($tmpNam, $data);
            @chmod($filename, 0755);
            return @ftp_put($connection, $filename, $tmpNam, FTP_BINARY);
        } else {
            return false;
        }

        return true;
    }

    /**
     * Starts an FTP connection if necessary.
     * If an FTP connection was already established, it returns it.
     * Called whenever setting file contents or deleting files.
     */
    function getFtpConnection() {

        $host = Settings::get('ftp_host');
        $path = Settings::get('ftp_path');
        $user = Settings::get('ftp_user');
        $pass = Settings::get('ftp_pass');
        $port = Settings::get('ftp_port');
        $passive = Settings::get('ftp_pasv');

        if (!($this->ftp_conn)) {

            $port = (int) $port;
            $path = (substr($path, strlen($path) - 1, 1) == '/') ? $path : $path . '/';

            $connection = @ftp_connect($host, $port);

            if (!$connection) {
                return false;
            } else {

                if (!@ftp_login($connection, $user, $pass)) {
                    return false;
                }

                ftp_pasv($connection, $passive);

                if (!ftp_chdir($connection, $path)) {
                    return false;
                }
            }

            $this->ftp_conn = $connection;

            return $this->ftp_conn;
        } else {
            return $this->ftp_conn;
        }
    }

    function remove_version_files($version) {
        $this->db->where('version', $version)->delete('update_files');
        $this->db->where('version', $version)->delete('updates');
    }

    function delete($filename) {
        if ($this->write) {
            return @unlink($filename);
        } elseif ($this->ftp) {
            $filename = str_ireplace(FCPATH, '', $filename);
            @ftp_delete($this->getFtpConnection(), $filename);
        } else {
            return false;
        }
    }

}
