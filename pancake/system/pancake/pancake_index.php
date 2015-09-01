<?php

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
 * @since		Version 4.0
 */
try {
    if (version_compare(PHP_VERSION, "5.3.0", "lt")) {
        echo "<!doctype HTML><html><head><style>body{font-family: sans-serif;margin: 4em;} li {margin-bottom: 1em;} code {padding: 4px; display:inline-block; border-radius: 4px; background: #333; color: white;}</style></head><body>";
        echo "<h1>The version of PHP that you are using is not supported by Pancake.</h1>";
        echo "<p>You are using PHP " . PHP_VERSION . ", which has been out of date for well over 5 years, and has not been supported by the PHP Group for at least 3 years.</p>";
        echo "<p>By staying with this version of PHP, you're missing out on a number of performance and security improvements, as well as a countless number of bugfixes.</p>";
        echo "<p>You're also not making the most of Pancake.</p>";
        echo "<p>You should upgrade your PHP version to at least 5.5.</p>";
        echo "<p>To do so, please talk to your server administrators and ask them to update PHP.</p>";
        echo "<h2>Notes</h2>";
        echo "<ol>";
        echo "<li>If you're on GoDaddy, you can <a href='http://redlinesolutions.co/configuring-php-5-3-correctly-on-godaddy/#version5_3'>change your PHP version</a> yourself.</li>";
        echo "<li>If you're running Pancake on an Apache server (if you don't know, you probably are), you might be able to change the PHP version by adding the following to the bottom of your <code>.htaccess</code> file: <code>AddHandler application/x-httpd-php54 .php</code>. It doesn't work for every server configuration, but it might do for you.</li>";
        echo "</ol>";
        echo "</body></html>";
        die;
    }

    define('SELF', pathinfo($index_file, PATHINFO_BASENAME));
    define('EXT', '.php');
    define('FCPATH', str_replace(SELF, '', $index_file));

    if (defined('STDIN')) {
        chdir(dirname($index_file));
    }

    define("REQUEST_TIME", microtime(true));

    # Having Pancake version as 2.1.0 is NOT a mistake, it is here for backward-compatibility.
    define('PANCAKE_VERSION', '2.1.0');

    # Define environment variables.
    clearstatcache();
    define('IS_AJAX', isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');
    define('IS_CLI', defined('STDIN'));
    define('IS_SSL', ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)));
    define('SCHEME', IS_SSL ? 'https' : 'http');
    define('IS_DEBUGGING', file_exists(FCPATH."DEBUGGING"));
    define('IS_PROFILING', file_exists(FCPATH."PROFILING"));
    define('IS_DEMO', (file_exists(FCPATH.'DEMO')));
    define('IS_HOSTED', (file_exists(FCPATH.'HOSTED')));

    $policy = "default-src 'self'; script-src 'self' 'unsafe-eval' code.jquery.com; style-src 'self' 'unsafe-inline' fonts.googleapis.com; img-src 'self' data: https://secure.gravatar.com http://www.gravatar.com; font-src *";
    # @todo this is still being worked on.
    # header("X-Content-Security-Policy: $policy;");
    # header("X-WebKit-CSP: $policy;");
    # header("Content-Security-Policy: $policy;");

    # This is here for backward-compatibility purposes.
    define('PANCAKE_DEMO', IS_DEMO);
    define('PANCAKE_HOSTED', IS_HOSTED);

    @ini_set('memory_limit', '256M');

    # This is here to fix an odd CI bug with special characters.
    $post_buffer = $_POST;

    # This can no longer be modified; please don't change it.
    define('ENVIRONMENT', 'development');

    # @ is used here to prevent errors with some of the stricter hosts who disable ini_set.
    @ini_set('display_errors', false);
    error_reporting(-1);

    $system_path = FCPATH . "system/codeigniter";
    $application_folder = FCPATH . "system/pancake";

    if (is_file($application_folder . '/config/database.php')) {
        file_get_contents($application_folder . '/config/database.php') or $application_folder = FCPATH . "installer";
    } else {
        $application_folder = FCPATH . "installer";
    }

    define('INSTALLING_PANCAKE', ($application_folder == FCPATH . "installer"));

    if (realpath($system_path) !== FALSE) {
        $system_path = realpath($system_path) . '/';
    }

    $system_path = rtrim($system_path, '/') . '/';

    if (!is_dir($system_path)) {
        pancake_system_folder_error($system_path);
    }

    define('BASEPATH', str_replace("\\", "/", $system_path));
    define('SYSDIR', trim(strrchr(trim(BASEPATH, '/'), '/'), '/'));

    if (is_dir($application_folder)) {
        define('APPPATH', $application_folder . '/');
    } else {
        if (!is_dir(BASEPATH . $application_folder . '/')) {
            pancake_application_folder_error($application_folder);
        }

        define('APPPATH', BASEPATH . $application_folder . '/');
    }

    # This file is not included because it contains 5.3+ code that would prevent us
    # from displaying the "unsupported PHP" error at the top of this file.
    require_once dirname(__FILE__) . '/../vendor/autoload.php';

    # Load CodeIgniter.
    require_once BASEPATH . 'core/CodeIgniter.php';
} catch (Exception $e) {
    require_once BASEPATH . '/core/Exceptions.php';
    require_once APPPATH . 'core/Pancake_Exceptions.php';
    Pancake_Exceptions::exception_handler($e);
}

function is_php($version = '5.0.0') {
    static $_is_php = array();
    $version = (string) $version;

    if (!isset($_is_php[$version])) {
        $_is_php[$version] = (version_compare(PHP_VERSION, $version) < 0) ? FALSE : TRUE;
    }

    return $_is_php[$version];
}

function pancake_application_folder_error($application_folder) {
    exit("<h3>Pancake is having problems figuring out what the path to the application folder is.</h3>
		    It thinks the path is: $application_folder<br><br>
		    If this is incorrect, there is a line in the file index.php that has
		    <code># \$application_folder = '/your/path/here/system/pancake';</code>.
		    <br><br> Remove the hash sign and replace /your/path/here/system/pancake with the correct path to the system/pancake folder.
		    <br><br>Windows users, use forward slashes.
		    <br><br>NOTE: If you haven't installed Pancake yet, the application path should be /your/path/here/installer");
}

function pancake_system_folder_error($system_path) {
    exit("<h3>Pancake is having problems figuring out what the path to the system folder is.</h3> It thinks the path is: $system_path<br><br>If this is incorrect, there is a line in the file index.php that has <code># \$system_path = '/your/path/here/system/codeigniter';</code>.<br><br> Remove the hash sign and replace /your/path/here/system/codeigniter with the correct path to the system/codeigniter folder. <br><br>Windows users, use forward slashes.");
}

/**
 * If $server is a string, it assumes it's a base64_encoded serialized dump of $_SERVER
 * and alters $_SERVER to match it. Used for debugging installation errors in Pancake.
 *
 * If $server is an array, it'll return it in a debuggable format.
 *
 * @param string $server
 * @param boolean $process
 * @return string
 */
function debug_server($server) {

    # Destroy base_url.txt for testing purposes.
    @unlink(FCPATH . '/uploads/base_url.txt');

    if (is_array($server)) {
        return chunk_split(base64_encode(serialize($server)));
    } else {
        $server = trim($server);
        $server = base64_decode($server);
        $unserialize_server = @unserialize($server);
        if ($unserialize_server !== false) {
            $_SERVER = $unserialize_server;
        } else {
            if (substr($server, 0, 7) == "array (") {
                eval("\$server = " . $server . ";");
                $_SERVER = $server;
            } else {
                throw new Exception("Could not unserialize debug server.");
            }
        }
    }
}

function debug() {
    $i = 1;

    $raw_title = "<div style='font-family:Helvetica, Arial, sans-serif;background:black;color:white;padding:1em;'>%s</div>";
    $raw_pre = "<pre style='white-space: pre-wrap; white-space: -moz-pre-wrap; white-space: -pre-wrap;  white-space: -o-pre-wrap; word-wrap: break-word;'>%s</pre>";

    foreach (func_get_args() as $arg) {

        $dumped_arg = $arg;
        ob_start();
        var_dump($arg);
        $dumped_arg = ob_get_contents();
        ob_end_clean();
        @ob_end_clean();

        $type = gettype($arg);
        $just_echo = false;

        switch ($type) {
            case 'array':
                $details = "Array with " . count($arg) . " elements";

                if (array_values($arg) === $arg) {
                    $is_implodable = true;
                    foreach (array_values($arg) as $value) {
                        if (gettype($value) != 'string') {
                            $is_implodable = false;
                        }
                    }
                    if ($is_implodable) {
                        $arg = "array('" . implode("', '", $arg) . "')";
                        $just_echo = true;
                    }
                }

                echo sprintf($raw_title, "Argument #$i (" . $details . ")");
                echo "<div style='font-family:Helvetica, Arial, sans-serif;border:1px solid black;padding:1em 2em;margin-bottom: 1em;'><h2>Export</h2>";
                if ($just_echo) {
                    printf($raw_pre, $arg);
                } else {
                    printf($raw_pre, "\$arg = " . var_export($arg, true) . ";");
                }
                echo "<h2>Dump</h2>" . sprintf($raw_pre, $dumped_arg) . "</div>";

                break;
            case 'boolean':
                echo sprintf($raw_title, "Argument #$i (Boolean) - " . var_export($arg, true));
                break;
            case 'string':
                echo sprintf($raw_title, "Argument #$i (String) - " . var_export($arg, true));
                break;
            case 'integer':
                echo sprintf($raw_title, "Argument #$i (Integer) - " . var_export($arg, true));
                break;
            default:
                $details = ucwords($type);

                echo sprintf($raw_title, "Argument #$i (" . $details . ")");
                echo "<div style='font-family:Helvetica, Arial, sans-serif;border:1px solid black;padding:1em 2em;margin-bottom: 1em;'><h2>Export</h2>";
                if ($just_echo) {
                    printf($raw_pre, $arg);
                } else {
                    printf($raw_pre, "\$arg = " . var_export($arg, true) . ";");
                }
                echo "<h2>Dump</h2>" . sprintf($raw_pre, $dumped_arg) . "</div>";

                break;
        }

        $i++;
    }
    die;
}
