<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

/*
  |--------------------------------------------------------------------------
  | File and Directory Modes
  |--------------------------------------------------------------------------
  |
  | These prefs are used when checking and setting modes when working
  | with the file system.  The defaults are fine on servers with proper
  | security, but you may wish (or even need) to change the values in
  | certain environments (Apache running a separate process for each
  | user, PHP under CGI with Apache suEXEC, etc.).  Octal values should
  | always be used to set the mode correctly.
  |
 */
define('FILE_READ_MODE', 0644);
define('FILE_WRITE_MODE', 0666);
define('DIR_READ_MODE', 0755);
define('DIR_WRITE_MODE', 0777);

/*
  |--------------------------------------------------------------------------
  | File Stream Modes
  |--------------------------------------------------------------------------
  |
  | These modes are used when working with fopen()/popen()
  |
 */

define('FOPEN_READ', 'rb');
define('FOPEN_READ_WRITE', 'r+b');
define('FOPEN_WRITE_CREATE_DESTRUCTIVE', 'wb'); // truncates existing file data, use with care
define('FOPEN_READ_WRITE_CREATE_DESTRUCTIVE', 'w+b'); // truncates existing file data, use with care
define('FOPEN_WRITE_CREATE', 'ab');
define('FOPEN_READ_WRITE_CREATE', 'a+b');
define('FOPEN_WRITE_CREATE_STRICT', 'xb');
define('FOPEN_READ_WRITE_CREATE_STRICT', 'x+b');

# Fix servers where the query string is not passed correctly.
if (isset($_SERVER['QUERY_STRING']) and isset($_SERVER['REQUEST_URI']) and strstr($_SERVER['REQUEST_URI'], "?") !== false and substr(strstr($_SERVER['REQUEST_URI'], "?"), 1) != $_SERVER['QUERY_STRING']) {
    $_SERVER['QUERY_STRING'] = substr(strstr($_SERVER['REQUEST_URI'], "?"), 1);
}

# Remove the query string from the REQUEST_URI if it's there.
$removed_from_request_uri = '';
if (isset($_SERVER['REQUEST_URI']) and isset($_SERVER['QUERY_STRING']) and stripos($_SERVER['REQUEST_URI'], '?' . $_SERVER['QUERY_STRING']) !== false) {
    $_SERVER['REQUEST_URI'] = substr($_SERVER['REQUEST_URI'], 0, stripos($_SERVER['REQUEST_URI'], '?' . $_SERVER['QUERY_STRING']));
    $removed_from_request_uri = '?' . $_SERVER['QUERY_STRING'];
}

if (isset($_SERVER['HTTP_HOST'])) {
    $base_url = SCHEME;
    $base_url .= '://' . $_SERVER['HTTP_HOST'] . '/';

    if (substr($_SERVER['SCRIPT_NAME'], 0, 2) == '/~' and substr($_SERVER['REQUEST_URI'], 0, 2) != '/~' and substr($_SERVER['SCRIPT_FILENAME'], 0, 6) == '/home/') {
        # Correct an issue with ~ dirs and RewriteBase.
        $script_name = explode('/', $_SERVER['SCRIPT_NAME']);
        unset($script_name[0]);
        unset($script_name[1]);
        unset($script_name[2]);
        $_SERVER['SCRIPT_NAME'] = "/" . implode('/', $script_name);
    }

    $path = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : (isset($_SERVER['ORIG_PATH_INFO']) ? $_SERVER['ORIG_PATH_INFO'] : (basename($_SERVER['SCRIPT_NAME'])));

    # On some hosts, $path = [path-to-index.php]/index.php/path
    # If absolute path to index.php ends with [path-to-index.php]/index.php, then remove it from $path.
    if (stristr($path, '/index.php') !== false) {
        $buffer = explode('/index.php', $path);
        if (substr($_SERVER['SCRIPT_FILENAME'], -strlen($buffer[0] . '/index.php'))) {
            # Remove [path-to-index.php] from $path.
            $path = str_ireplace($buffer[0], '', $path);
        }
    }

    if (stristr($_SERVER['SCRIPT_NAME'], 'index.php') !== false) {

        # This fixes an issue where for some reason some servers said the script was called "Index.php",
        # even though it was really called "index.php". Crazy, right?
        $_SERVER['SCRIPT_NAME'] = str_ireplace('Index.php', 'index.php', $_SERVER['SCRIPT_NAME']);

        $script_name = explode('index.php', $_SERVER['SCRIPT_NAME']);
        $path = $script_name[0] . str_replace(array($path), '', $script_name[1]);
    } else {
        $path = str_replace(array($path, 'index.php'), '', $_SERVER['SCRIPT_NAME']);
    }

    if (substr($path, 0, 1) == '/') {
        $path = substr($path, 1, strlen($path) - 1);
    }
    $base_url .= $path;


    if (isset($_SERVER['SCRIPT_URI']) and !empty($_SERVER['SCRIPT_URI'])) {
        $base_url = $_SERVER['SCRIPT_URI'];
        if (!empty($_SERVER['PATH_INFO'])) {
            if (substr($_SERVER['PATH_INFO'], -10) == '/index.php') {
                # This path info ends with index.php, it doesn't include request data.
                if (isset($_SERVER['QUERY_STRING'])) {
                    if (substr($base_url, -strlen($_SERVER['QUERY_STRING'])) == $_SERVER['QUERY_STRING']) {
                        $base_url = substr($base_url, 0, -strlen($_SERVER['QUERY_STRING']));
                    }
                }
            } else {
                # Remove path info from base url.
                if (substr($base_url, -strlen($_SERVER['PATH_INFO'])) == $_SERVER['PATH_INFO']) {
                    $base_url = substr($base_url, 0, -strlen($_SERVER['PATH_INFO']));
                }

                # Remove index.php from the end of the base URL, if necessary.
                if (substr($base_url, -10) == '/index.php') {
                    $base_url = substr($base_url, 0, -10);
                }
            }
        } elseif (!empty($_SERVER['REQUEST_URI'])) {
            $buffer = (substr($_SERVER['REQUEST_URI'], -1, 1) == '/') ? $_SERVER['REQUEST_URI'] : $_SERVER['REQUEST_URI'] . '/';
            $base_url = (substr($base_url, -1, 1) == '/') ? $base_url : $base_url . '/';

            # Sometimes, the buffer might include the folder to which the app belongs.
            # So. We'll find the script name, remove the index.php from it, that'll leave the path to the script.
            # Then, we remove the path to the script from the start of $buffer, that means that $buffer will only be the -proper- REQUEST_URI.
            $script_name_buffer = $_SERVER['SCRIPT_NAME'];
            if (substr($script_name_buffer, -9) == 'index.php') {
                $script_name_buffer = substr($script_name_buffer, 0, -9);
            }

            if (substr($buffer, 0, strlen($script_name_buffer)) == $script_name_buffer) {
                $buffer = substr($buffer, strlen($script_name_buffer)) . '';
            }

            if (substr($base_url, -strlen($buffer)) == $buffer) {
                $base_url = substr($base_url, 0, -strlen($buffer));
            }
        }
    }

    # Add the forward slash, always.
    $base_url = (substr($base_url, -1, 1) == '/') ? $base_url : $base_url . '/';
} else {
    $base_url = 'http://localhost/';
}

# Fix an issue with the REQUEST_URI in some server configurations.
if (isset($_SERVER["SCRIPT_FILENAME"]) and isset($_SERVER["REQUEST_URI"])) {
    if (substr($_SERVER["SCRIPT_FILENAME"], -9) == "index.php") {
        $script_filename = substr($_SERVER["SCRIPT_FILENAME"], 0, -9);
        $pieces = explode("/", $_SERVER["REQUEST_URI"]);
        while (count($pieces) > 1) {
            array_pop($pieces);
            $possible_uri_string = implode("/", $pieces) . "/";
            $length = strlen($possible_uri_string);
            if (substr(strtolower($script_filename), -$length) == strtolower($possible_uri_string)) {
                if (strlen($_SERVER["REQUEST_URI"]) != $length) {
                    $_SERVER["REQUEST_URI"] = substr($_SERVER["REQUEST_URI"], $length);
                } else {
                    $_SERVER["REQUEST_URI"] = "";
                }
                break;
            }
            $pieces--;
        }
    }
}

# Fix an issue with the REQUEST_URI in some server configurations.
if (isset($_SERVER["SCRIPT_NAME"]) and isset($_SERVER["REQUEST_URI"])) {
    if (substr($_SERVER["SCRIPT_NAME"], -9) == "index.php") {
        $script_filename = substr($_SERVER["SCRIPT_NAME"], 0, -9);

        # Guarantee that the SCRIPT_NAME always has a forward slash at the start.
        $script_filename = "/" . ltrim($script_filename, "/");

        $pieces = explode("/", $_SERVER["REQUEST_URI"]);
        while (count($pieces) > 1) {
            array_pop($pieces);
            $possible_uri_string = implode("/", $pieces) . "/";
            $length = strlen($possible_uri_string);
            if (substr(strtolower($script_filename), -($length + 1)) == '/' . strtolower($possible_uri_string)) {
                if (strlen($_SERVER["REQUEST_URI"]) != $length) {
                    $_SERVER["REQUEST_URI"] = substr($_SERVER["REQUEST_URI"], $length);
                } else {
                    $_SERVER["REQUEST_URI"] = "";
                }
                break;
            }
            $pieces--;
        }
    }
}

if ($_SERVER["REQUEST_URI"] == "index.php") {
    $_SERVER["REQUEST_URI"] = "";
}

$_SERVER["REQUEST_URI"] = str_ireplace("index.php/", "", $_SERVER["REQUEST_URI"]);

# Fixes an issue where .htaccess redirects requests for third_party/ files to Pancake.
# This just makes Pancake understand those requests and serve those files, if they exist.
if (substr($_SERVER["REQUEST_URI"], 0, strlen("third_party/")) == "third_party/") {
    # Fixes an issue where query strings are attached to the REQUEST_URI.
    $_SERVER["REQUEST_URI"] = explode("?", $_SERVER["REQUEST_URI"]);
    $_SERVER["REQUEST_URI"] = reset($_SERVER["REQUEST_URI"]);

    $third_party_path = realpath($_SERVER["REQUEST_URI"]);
    if (substr($third_party_path, 0, strlen(FCPATH . "third_party/")) == FCPATH . "third_party/") {
        if (file_exists($third_party_path) and is_file($third_party_path)) {

            // Grab the file extension
            $extension = explode('.', $third_party_path);
            $extension = end($extension);

            include(APPPATH . 'config/mimes.php');

            // Set a default mime if we can't find it
            if (!isset($mimes[$extension])) {
                $mime = 'application/octet-stream';
            } else {
                $mime = (is_array($mimes[$extension])) ? $mimes[$extension][0] : $mimes[$extension];
            }

            set_status_header(200);

            $data = file_get_contents($third_party_path);

            if (strpos($_SERVER['HTTP_USER_AGENT'], "MSIE") !== FALSE) {
                header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                header('Pragma: public');
            } else {
                header('Pragma: no-cache');
            }

            header('Content-Disposition: inline');
            header('Expires: 0');
            header("Content-Length: " . strlen($data));
            header('Content-Type: ' . $mime);
            echo $data;

            die;
        }
    }
}

# Add the query string back to REQUEST_URI if it was removed earlier on:
$_SERVER['REQUEST_URI'] .= $removed_from_request_uri;

// Define these values to be used later on

$guessed_base_url = strtolower((substr($base_url, -1) != '/') ? $base_url . '/' : $base_url);
$guessed_base_url = str_ireplace('/index.php/', '/', $guessed_base_url);

$base_url_file = FCPATH . 'uploads/base_url.txt';

# Fix an issue where https:// would be replaced by http:// incorrectly.
$scheme = (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on') ? 'https' : 'http';
$guessed_base_url = str_ireplace(array("http://", "https://"), "$scheme://", $guessed_base_url);



if (!INSTALLING_PANCAKE) {
    if (stristr($guessed_base_url, 'localhost') === false) {
        # Store the base URL. This is not localhost, so odds are, it's correct, and this way, people can move Pancake and it'll still work.
        # It also avoid the extra /admin/ problem that some people were facing.
        file_put_contents($base_url_file, $guessed_base_url);
        define('BASE_URL', $guessed_base_url);
    } else {
        # Base URL is being identified as localhost, so we will not store the base URL, because it's probably being called from a cron.
        # Instead, we will use the CURRENT stored URL, if one exists. Otherwise we'll use the guessed base URL.
        # If a current stored URL exists, we know this is a cron job. Otherwise, we know it's just an installation running on localhost.
        if (file_exists($base_url_file)) {
            $base = file_get_contents($base_url_file);
            $base = trim($base);
            define('BASE_URL', $base);
        } else {
            define('BASE_URL', $guessed_base_url);
        }
    }
} else {
    define('BASE_URL', $guessed_base_url);
}

# Setting this here to sort out a bug that crops up with using date('Y') before setting a timezone.
# This is overriden as Pancake is loading, so it's not a problem.
date_default_timezone_set('Europe/London');
define('COPYRIGHT_YEAR', date('Y'));

// We dont need these variables any more
unset($base_url);

# Store Plugin Types
define('STORE_TYPE_PLUGIN', 1);
define('STORE_TYPE_GATEWAY', 2);
define('STORE_TYPE_FRONTEND_THEME', 3);
define('STORE_TYPE_BACKEND_THEME', 4);
define('STORE_INVALID_AUTH', 1000);
define('STORE_ALREADY_PURCHASED', 1001);
define('STORE_FAILED_CREDIT_CARD', 1002);
define('STORE_INVALID_REQUEST_ERROR', 1003);
define('STORE_TEMPORARY_ERROR', 1004);
define('STORE_NO_WRITE_PERMISSIONS', 1005);

# Upload Errors
define('NOT_ALLOWED', 'NOT_ALLOWED');

# Testing Payments
define('USE_SANDBOX', false);

define("PANCAKEAPP_COM_BASE_URL", "https://www.pancakeapp.com/");
define("MANAGE_PANCAKE_BASE_URL", "http://manage.pancakeapp.com/");

define('MUSTACHE_EXT', '.mustache.html');

/* End of file constants.php */
/* Location: ./application/config/constants.php */
