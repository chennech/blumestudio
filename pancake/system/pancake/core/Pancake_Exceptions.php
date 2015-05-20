<?php

defined('BASEPATH') OR exit('No direct script access allowed');

// To disable UhOh! simply change IN_PRODUCTION to TRUE.
if (!defined('IN_PRODUCTION')) {
    define('IN_PRODUCTION', FALSE);
}

/**
 * CodeIgniter UhOh!
 *
 * This is an extension on CI_Extensions that provides awesome error messages
 * with full backtraces and a view of the line with the error.  It is based
 * on Kohana v3 Error Handling.
 *
 * @package		CodeIgniter
 * @author		Dan Horrigan <http://dhorrigan.com>
 * @license		Apache License v2.0
 * @version		1.0
 */
/**
 * This file contains some functions originally from Kohana.  They have been modified
 * to work with CodeIgniter.  Here is the obligatory Kohana license info:
 *
 * @copyright  (c) 2008-2009 Kohana Team
 * @license	   http://kohanaphp.com/license
 */

/**
 * Pancake_Exceptions
 *
 * @subpackage	Exceptions
 */
class Pancake_Exceptions extends CI_Exceptions {

    /**
     * Some nice names for the error types
     */
    public static $php_errors = array(
        E_ERROR => 'Fatal Error',
        E_USER_ERROR => 'User Error',
        E_PARSE => 'Parse Error',
        E_WARNING => 'Warning',
        E_USER_WARNING => 'User Warning',
        E_STRICT => 'Strict',
        E_NOTICE => 'Notice',
        E_RECOVERABLE_ERROR => 'Recoverable Error',
    );

    /**
     * List of available error levels
     *
     * @var array
     * @access public
     */
    public static $static_levels = array(
        E_ERROR => 'Error',
        E_WARNING => 'Warning',
        E_PARSE => 'Parsing Error',
        E_NOTICE => 'Notice',
        E_CORE_ERROR => 'Core Error',
        E_CORE_WARNING => 'Core Warning',
        E_COMPILE_ERROR => 'Compile Error',
        E_COMPILE_WARNING => 'Compile Warning',
        E_USER_ERROR => 'User Error',
        E_USER_WARNING => 'User Warning',
        E_USER_NOTICE => 'User Notice',
        E_STRICT => 'Runtime Notice',
    );

    /**
     * List of non-reportable error levels
     *
     * @var array
     * @access public
     */
    public static $non_reportable_levels = array(
        E_NOTICE,
        E_USER_NOTICE,
        E_STRICT,
    );

    /**
     * The Shutdown errors to show (all others will be ignored).
     */
    public static $shutdown_errors = array(E_PARSE, E_ERROR, E_USER_ERROR, E_COMPILE_ERROR);

    /**
     * Construct
     *
     * Sets the error handlers.
     *
     * @access	public
     * @return	void
     */
    public function __construct() {
        parent::__construct();

        // If we are in production, then lets dump out now.
        if (IN_PRODUCTION) {
            return;
        }

        //Set the Exception Handler
        set_exception_handler(array('Pancake_Exceptions', 'exception_handler'));

        // Set the Error Handler
        set_error_handler(array('Pancake_Exceptions', 'error_handler'));

        // Set the handler for shutdown to catch Parse errors
        register_shutdown_function(array('Pancake_Exceptions', 'shutdown_handler'));

        // This is a hack to set the default timezone if it isn't set. Not setting it causes issues.
        date_default_timezone_set(date_default_timezone_get());
    }

    /**
     * Debug Path
     *
     * This makes nicer looking paths for the error output.
     *
     * @access	public
     * @param	string	$file
     * @return	string
     */
    public static function debug_path($file) {

        if (!defined('SYSDIR')) {
            define('SYSDIR', trim(strrchr(trim(BASEPATH, '/'), '/'), '/'));
        }

        if (strpos($file, APPPATH) === 0) {
            $file = 'APPPATH/' . substr($file, strlen(APPPATH));
        } elseif (strpos($file, SYSDIR) === 0) {
            $file = 'SYSDIR/' . substr($file, strlen(SYSDIR));
        } elseif (strpos($file, FCPATH) === 0) {
            $file = 'FCPATH/' . substr($file, strlen(FCPATH));
        }

        return $file;
    }

    /**
     * Error Handler
     *
     * Converts all errors into ErrorExceptions.
     *
     * @access	public
     * @throws	ErrorException
     * @return	bool
     */
    public static function error_handler($code, $error, $file = NULL, $line = NULL) {

        # If error_reporting() is 0, then the @ sign was used to suppress an error.
        # We know this because error_reporting() is never 0 otherwise.
        # So we'll obey the developer's wishes.
        if (error_reporting() == 0) {
            return true;
        }

        # For some reason, using @ on set_time_limit() doesn't result in the behavior described above, so we're handling it manually.
        if ($error == "set_time_limit(): Cannot set time limit due to system policy") {
            return true;
        }

        $severity = (!isset(self::$static_levels[$code])) ? $code : self::$static_levels[$code];
        $display_error = (!in_array($code, self::$non_reportable_levels) || IS_DEBUGGING);
        self::exception_handler(new ErrorException($error, $code, $code, $file, $line), $display_error);
        // Do not execute the PHP error handler
        return TRUE;
    }

    /**
     * Exception Handler
     *
     * Displays the error message, source of the exception, and the stack trace of the error.
     *
     * @access	public
     * @param	object	 exception object
     * @return	boolean
     */
    public static function exception_handler(Exception $e, $display_error = true) {
        global $_CLEAN_SERVER;

        try {
            if (!headers_sent()) {
                header_remove('Content-Security-Policy');
                header_remove('X-Content-Security-Policy');
                header_remove('X-WebKit-CSP');
            }

            // Get the exception information
            $type = get_class($e);
            $code = $e->getCode();
            $file = $e->getFile();
            $line = $e->getLine();
            $message = $e->getMessage();

            // Create a text version of the exception
            $error = self::exception_text($e);

            // Get the exception backtrace
            $trace = $e->getTrace();

            if ($e instanceof ErrorException) {
                if (isset(self::$php_errors[$code])) {
                    // Use the human-readable error name
                    $code = self::$php_errors[$code];
                }

                if (version_compare(PHP_VERSION, '5.3', '<')) {
                    // Workaround for a bug in ErrorException::getTrace() that exists in
                    // all PHP 5.2 versions. @see http://bugs.php.net/bug.php?id=45895
                    for ($i = count($trace) - 1; $i > 0; --$i) {
                        if (isset($trace[$i - 1]['args'])) {
                            // Re-position the args
                            $trace[$i]['args'] = $trace[$i - 1]['args'];

                            // Remove the args
                            unset($trace[$i - 1]['args']);
                        }
                    }
                }
            }

            $subject = $message;

            foreach ($trace as $key => $value) {

                $arg0 = isset($value['args']) ? (isset($value['args'][0]) ? $value['args'][0] : '') : '';
                $arg0 = is_string($arg0) ? $arg0 : '';

                if (!isset($value['file']) or stristr($value['file'], 'core/CodeIgniter.php') or stristr($value['file'], 'Pancake_Exceptions.php') or stristr($value['file'], 'core/Loader.php') or stristr($value['file'], 'core/Common.php') or ( stristr($arg0, 'core/CodeIgniter.php'))) {
                    #unset($trace[$key]);
                }

                $force_logging = isset($trace[$key]['function']) and in_array($trace[$key]['function'], array("log_without_error"));

                if (!$force_logging) {
                    unset($trace[$key]['args']);
                }
            }

            $reset_trace = reset($trace);

            $file = isset($reset_trace['file']) ? $reset_trace['file'] : $file;
            $line = isset($reset_trace['line']) ? $reset_trace['line'] : $line;

            $error_id = $message.$file.$line;

            $error_id = str_ireplace(APPPATH, "APPPATH/", $error_id);
            $error_id = str_ireplace(FCPATH, "FCPATH/", $error_id);

            $error_id = sha1($error_id);

            // Start an output buffer
            ob_start();

            // This will include the custom error file.
            require APPPATH . 'errors/error_php_custom.php';

            // Get the contents of the output buffer
            $contents = ob_get_clean();

            $contents = str_ireplace(APPPATH, "APPPATH/", $contents);
            $contents = str_ireplace(FCPATH, "FCPATH/", $contents);

            $subject = str_ireplace(APPPATH, "APPPATH/", $subject);
            $subject = str_ireplace(FCPATH, "FCPATH/", $subject);
            $subject = str_ireplace("/", " / ", $subject);

            $reportable = true;

            $__ = "__";

            if (!function_exists("__")) {
                $__ = function($line, $vars = array()) {
                    $lang = array();
                    require APPPATH."language/english/pancake_lang.php";

                    $line = $lang[$line];

                    for ($i = 0; $i < count($vars); $i++) {
                        $line = str_replace(':' . ($i + 1), $vars[$i], $line);
                    }

                    return $line;
                };
            }

            if (stristr($subject, "Got error 28 from storage engine") !== false) {
                $hostname = $__("global:na");
                if (file_exists(APPPATH . 'config/database.php')) {
                    require APPPATH . 'config/database.php';
                    $hostname = $db['default']['hostname'];
                }
                $original_error = $subject;
                $title = $__("error:not_enough_disk_space");
                $subtitle = $__("error:not_enough_disk_space_explanation", array($hostname));
                $enduser_message = "<p class='known-error'> " . $__("error:you_cannot_report_this_error") . " <br /><br /> '$original_error' <br /><br /> " . __("error:not_enough_disk_space_solutions") . "</p>";
                $subject = $subtitle . " " . strip_tags($enduser_message);
                $enduser_message .= "<p>" . $__("error:logged_in_intro") . "</p>";
                $reportable = false;
            }

            if (stristr($subject, "Unable to open a socket to Sendmail. Please check settings.") !== false) {
                $original_error = $subject;
                $title = $__("error:cant_send_email");
                $subtitle = $__("error:email_settings_not_valid");
                $enduser_message = "<p class='known-error'> " . $__("error:cant_send_email_explanation", array(Settings::get("mailpath"))) . " <br /><br /> " . $__("error:you_cannot_report_this_error") . " <br /><br /> '$original_error' <br /><br /> " . $__("error:cant_send_email_solutions") . "</p>";
                $subject = $subtitle . " " . strip_tags($enduser_message);
                $enduser_message .= "<p>" . $__("error:logged_in_intro") . "</p>";
                $reportable = false;
            }

            if (stristr($subject, "Unable to send email using PHP mail(). Your server might not be configured to send mail using this method.") !== false) {
                $original_error = $subject;
                $title = $__("error:cant_send_email");
                $subtitle = $__("error:email_settings_not_valid");
                $enduser_message = "<p class='known-error'> " . $__("error:cant_php_mail_explanation", array(Settings::get("mailpath"))) . " <br /><br /> " . $__("error:you_cannot_report_this_error") . " <br /><br /> '$original_error'</p>";
                $subject = $subtitle . " " . strip_tags($enduser_message);
                $enduser_message .= "<p>" . $__("error:logged_in_intro") . "</p>";
                $reportable = false;
            }

            if (stristr($subject, "XCache: Cannot init") !== false) {
                $original_error = $subject;
                $title = $__("error:server_error");
                $subtitle = $__("error:xcache_extension_broken");
                $enduser_message = "<p class='known-error'> " . $__("error:xcache_extension_broken_explanation") . " <br /><br /> " . $__("error:you_cannot_report_this_error") . " <br /><br />'$original_error'</p>";
                $subject = $subtitle . " " . strip_tags($enduser_message);
                $enduser_message .= "<p>" . $__("error:logged_in_intro") . "</p>";
                $reportable = false;
            }

            if (stristr($subject, "tempnam(): open_basedir restriction in effect.") !== false and stristr($subject, "is not within the allowed path") !== false) {
                $original_error = $subject;
                $title = $__("error:server_error");
                $subtitle = $__("error:permissions_not_valid");
                $enduser_message = "<p class='known-error'> " . $__("error:no_tmp_dir_permissions_explanation", array(sys_get_temp_dir())) . " <br /><br /> " . $__("error:you_cannot_report_this_error") . " <br /><br /> '$original_error' <br /><br /> " . $__("error:no_tmp_dir_permissions_solutions", array(sys_get_temp_dir())) . "</p>";
                $subject = $subtitle . " " . strip_tags($enduser_message);
                $enduser_message .= "<p>" . $__("error:logged_in_intro") . "</p>";
                $reportable = false;
            }

            if (stristr($subject, "The page you requested was not found.") !== false) {

                if (function_exists("uri_string")) {
                    $uri_string = uri_string();
                } else {
                    $uri_string = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : "n/a";
                }

                $original_error = $subject;
                $title = $__("error:page_not_found");
                $subtitle = $__("error:page_does_not_exist");
                $enduser_message = "<p class='known-error'> " . $__("error:page_does_not_exist_explanation", array("<code style='white-space: initial;'>" . str_ireplace("/", " / ", $uri_string) . "</code>")) . "</p>";
                $subject = $subtitle . " " . strip_tags($enduser_message);
                $enduser_message .= "<p>" . $__("error:logged_in_intro") . "</p>";
                $reportable = true;
            }

            $error = self::store_error_in_db($subject, $contents, $reportable, $error_id);

            if ($display_error) {
                # Get rid of everything on the page,
                # so the error is the only thing displayed.
                while (ob_get_contents() !== false) {
                    # Just getting rid of all output.
                    ob_end_clean();
                }

                if (isset($title)) {
                    require APPPATH . 'errors/error_php_enduser.php';
                } else {
                    if (function_exists("logged_in") and ! IS_DEBUGGING) {
                        $title = $__("error:title");
                        $subtitle = $__("error:subtitle");

                        $db = @get_instance()->db;
                        if ($db) {
                            $logged_in = logged_in();
                        } else {
                            # Echo and die because the DB is not working.
                            echo $contents;
                            die;
                        }

                        if ($logged_in and $reportable) {
                            $url = site_url("send_error_report/{$error['id']}");
                            $enduser_message = "<p>" . $__("error:logged_in_intro") . "</p><p><a href='$url' class='btn " . ($error['is_reported'] ? "success wide-success" : "") . "'>" . $__($error['is_reported'] ? "error:already_reported" : "error:logged_in_extra") . "</a></p>";
                        } else {
                            $enduser_message = "<p>" . $__("error:not_logged_in_intro") . "</p><p>" . $__("error:not_logged_in_extra", array(Business::getNotifyEmail())) . "</p>";
                        }
                        require APPPATH . 'errors/error_php_enduser.php';
                    } else {
                        echo $contents;
                    }
                }

                # Make sure to end the execution of the script now.
                exit(1);
            } else {
                return true;
            }
        } catch (Exception $e) {
            // Clean the output buffer if one exists
            ob_get_level() and ob_clean();

            // Display the exception text
            echo self::exception_text($e), "\n";

            // Exit with an error status
            exit(1);
        }
    }

    public static function store_error_in_db($subject, $contents, $reportable, $error_id) {
        if (class_exists("CI_Controller")) {
            $db = @get_instance()->db;

            if ($db) {
                $db->ar_set = array();
                $db->ar_from = array();
                $db->ar_where = array();
                $db->ar_like = array();
                $db->ar_orderby = array();
                $db->ar_keys = array();
                $db->ar_limit = array();
                $db->ar_order = array();
                $db->ar_select = array();
                $db->ar_join = array();
                $db->ar_groupby = array();
                $db->ar_having = array();
                $db->ar_wherein = array();
                $db->ar_aliased_tables = array();
                $db->ar_no_escape = array();
                $db->ar_distinct = array();
                $db->ar_limit = array();
                $db->ar_offset = array();

                $db->query("CREATE TABLE IF NOT EXISTS " . $db->dbprefix('error_logs') . " (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `subject` varchar(1024) NOT NULL DEFAULT '',
  `occurrences` int(11) NOT NULL DEFAULT '1',
  `first_occurrence` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `latest_occurrence` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `contents` longtext NOT NULL,
  `is_reported` tinyint(1) NOT NULL DEFAULT '0',
  `is_reportable` tinyint(1) NOT NULL DEFAULT '0',
  `notification_email` varchar(1024) NOT NULL DEFAULT '',
  `error_id` varchar(1024) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;");

                self::add_column("error_logs", "is_reportable", "boolean", null, 1, false);
                self::add_column("error_logs", "error_id", "varchar", 255, "", false);

                # An error might contain invalid UTF-8 characters, which cause an additional error when trying to store them in the DB.
                # This strips them.
                $regex = <<<'END'
/
  (
    (?: [\x00-\x7F]                 # single-byte sequences   0xxxxxxx
    |   [\xC0-\xDF][\x80-\xBF]      # double-byte sequences   110xxxxx 10xxxxxx
    |   [\xE0-\xEF][\x80-\xBF]{2}   # triple-byte sequences   1110xxxx 10xxxxxx * 2
    |   [\xF0-\xF7][\x80-\xBF]{3}   # quadruple-byte sequence 11110xxx 10xxxxxx * 3
    ){1,100}                        # ...one or more times
  )
| .                                 # anything else
/x
END;
                $contents = preg_replace($regex, '$1', $contents);

                $data = array(
                    "error_id" => $error_id,
                    "subject" => $subject,
                    "contents" => $contents,
                    "is_reportable" => $reportable,
                    "latest_occurrence" => gmdate("Y-m-d H:i:s")
                );
                $existing = $db->where("error_id", $error_id)->get("error_logs")->row_array();
                if (isset($existing['subject'])) {
                    $data['occurrences'] = $existing['occurrences'] + 1;
                    $db->where("error_id", $error_id)->update("error_logs", $data);
                    return $existing;
                } else {
                    $db->insert("error_logs", $data);
                    $data['is_reported'] = 0;
                    $data['id'] = $db->insert_id();
                    return $data;
                }
            }
        }
    }

    public static function add_column($table, $name, $type, $constraint = null, $default = '', $null = FALSE, $after_field = '') {
        $CI = &get_instance();

        if ($type == 'decimal') {
            if ($CI->db->dbdriver == "mysqli" and is_array($constraint)) {
                $constraint = implode(",", $constraint);
            } elseif ($CI->db->dbdriver == "mysql" and is_string($constraint)) {
                $constraint = explode(",", $constraint);
            }
        }

        $result = $CI->db->query("SHOW COLUMNS FROM " . $CI->db->dbprefix($table) . " LIKE '{$name}'")->row_array();

        if (!isset($result['Field']) or $result['Field'] != $name) {
            $properties = array(
                'type' => $type,
                'null' => $null,
            );

            if ($null === FALSE) {
                $properties['default'] = $default;
            }

            if ($constraint !== NULL) {
                $properties['constraint'] = $constraint;
            }

            return $CI->dbforge->add_column($table, array(
                        $name => $properties,
                            ), $after_field);
        }
    }

    /**
     * Shutdown Handler
     *
     * Catches errors that are not caught by the error handler, such as E_PARSE.
     *
     * @access	public
     * @return	void
     */
    public static function shutdown_handler() {
        $error = error_get_last();
        if ($error = error_get_last() AND in_array($error['type'], self::$shutdown_errors)) {
            // Clean the output buffer
            ob_get_level() and ob_clean();

            // Fake an exception for nice debugging
            self::exception_handler(new ErrorException($error['message']." (".$error['file'].":".$error['line'].")", $error['type'], 0, $error['file'], $error['line']));

            // Shutdown now to avoid a "death loop"
            exit(1);
        }
    }

    /**
     * Exception Text
     *
     * Makes a nicer looking, 1 line extension.
     *
     * @access	public
     * @param	object	Exception
     * @return	string
     */
    public static function exception_text(Exception $e) {
        return sprintf('%s [ %s ]: %s ~ %s [ %d ]', get_class($e), $e->getCode(), strip_tags($e->getMessage()), $e->getFile(), $e->getLine());
    }

    /**
     * Debug Source
     *
     * Returns an HTML string, highlighting a specific line of a file, with some
     * number of lines padded above and below.
     *
     * @access	public
     * @param	string	 file to open
     * @param	integer	 line number to highlight
     * @param	integer	 number of padding lines
     * @return	string	 source of file
     * @return	FALSE	 file is unreadable
     */
    public static function debug_source($file, $line_number, $padding = 5) {
        if ($file == "(Shutdown Error / Unknown File)") {
            # Not a real file.
            return false;
        }

        if (!$file OR ! is_readable($file)) {
            // Continuing will cause errors
            return FALSE;
        }

        // Open the file and set the line position
        $file = fopen($file, 'r');
        $line = 0;

        // Set the reading range
        $range = array('start' => $line_number - $padding, 'end' => $line_number + $padding);

        // Set the zero-padding amount for line numbers
        $format = '% ' . strlen($range['end']) . 'd';

        $source = '';
        while (($row = fgets($file)) !== FALSE) {
            // Increment the line number
            if (++$line > $range['end'])
                break;

            if ($line >= $range['start']) {
                // Make the row safe for output
                $row = htmlspecialchars($row, ENT_NOQUOTES);

                // Trim whitespace and sanitize the row
                $row = '<span class="number">' . sprintf($format, $line) . '</span> ' . $row;

                if ($line === $line_number) {
                    // Apply highlighting to this row
                    $row = '<span class="line highlight">' . $row . '</span>';
                } else {
                    $row = '<span class="line">' . $row . '</span>';
                }

                // Add to the captured source
                $source .= $row;
            }
        }

        // Close the file
        fclose($file);

        return '<pre class="source"><code>' . $source . '</code></pre>';
    }

    /**
     * Trace
     *
     * Returns an array of HTML strings that represent each step in the backtrace.
     *
     * @access	public
     * @param	string	path to debug
     * @return	string
     */
    public static function trace(array $trace = NULL) {
        if ($trace === NULL) {
            // Start a new trace
            $trace = debug_backtrace();
        }

        // Non-standard function calls
        $statements = array('include', 'include_once', 'require', 'require_once');

        $output = array();
        foreach ($trace as $step) {
            if (!isset($step['function'])) {
                // Invalid trace step
                continue;
            }

            if (isset($step['file']) AND isset($step['line'])) {
                // Include the source of this step
                $source = self::debug_source($step['file'], $step['line']);
            }

            if (isset($step['file'])) {
                $file = $step['file'];

                if (isset($step['line'])) {
                    $line = $step['line'];
                }
            }

            // function()
            $function = $step['function'];

            if (in_array($step['function'], $statements)) {
                if (empty($step['args'])) {
                    // No arguments
                    $args = array();
                } else {
                    // Sanitize the file path
                    $args = array($step['args'][0]);
                }
            } elseif (isset($step['args'])) {
                if (strpos($step['function'], '{closure}') !== FALSE) {
                    // Introspection on closures in a stack trace is impossible
                    $params = NULL;
                } else {
                    try {
                        if (isset($step['class'])) {
                            if (method_exists($step['class'], $step['function'])) {
                                $reflection = new ReflectionMethod($step['class'], $step['function']);
                            } else {
                                $reflection = new ReflectionMethod($step['class'], '__call');
                            }
                        } else {
                            $reflection = new ReflectionFunction($step['function']);
                        }

                        // Get the function parameters
                        $params = $reflection->getParameters();
                    } catch (ReflectionException $e) {
                        # Something went wrong while reflecting the function.
                        $params = null;
                    }
                }

                $args = array();

                foreach ($step['args'] as $i => $arg) {

                    if (is_array($arg)) {
                        self::array_cleanup($arg);
                    }

                    if (isset($params[$i])) {
                        // Assign the argument by the parameter name
                        $args[$params[$i]->name] = $arg;
                    } else {
                        // Assign the argument by number
                        $args[$i] = $arg;
                    }
                }
            }

            if (isset($step['class'])) {
                // Class->method() or Class::method()
                $function = $step['class'] . $step['type'] . $step['function'];
            }

            $output[] = array(
                'function' => $function,
                'args' => isset($args) ? $args : NULL,
                'file' => isset($file) ? $file : NULL,
                'line' => isset($line) ? $line : NULL,
                'source' => isset($source) ? $source : NULL,
            );

            unset($function, $args, $file, $line, $source);
        }

        return $output;
    }

    public static function array_cleanup(&$arg) {
        foreach (array_keys($arg) as $arg_key) {
            if ($arg[$arg_key] instanceof CI_loader) {
                unset($arg[$arg_key]);
            } elseif (substr($arg_key, 0, 3) == '_ci' or in_array($arg_key, array('file_exists', 'cascade', 'view_file'))) {
                unset($arg[$arg_key]);
            }
        }
    }

    /**
     * General Error Page
     *
     * This function takes an error message as input
     * (either as a string or an array) and displays
     * it using the specified template.
     *
     * @access	private
     * @param	string	the heading
     * @param	string	the message
     * @param	string	the template name
     * @return	string
     */
    function show_error($heading, $message, $template = 'error_general', $status_code = 500) {

        if ($template == 'error_db') {
            $error_code = explode(":", $message[0]);
            $error_code = (int) end($error_code);

            if ($error_code == 0) {
                if (isset($message[1]) and $message[1] == "Filename: libraries/MX/Loader.php" or stristr($message[0], "Unable to select the specified database") !== false) {
                    $title = "Database Error";
                    $subtitle = $message[0];
                    $enduser_message = "<p>Please check your database connection settings.</p>";
                    require APPPATH . 'errors/error_php_enduser.php';
                    die;
                }
            }

            if (in_array($error_code, array(1451, 1452))) {
                throw new ForeignKeyConstraintException($message[1], $error_code);
            } else {
                throw new QueryException($message[1], $error_code);
            }
        }

        if ($template == "error_404") {
            $message .= " (" . (function_exists("uri_string") ? uri_string() : "") . ")";
        }

        // If we are in production, then lets dump out now.
        if (IN_PRODUCTION) {
            return parent::show_error($heading, $message, $template, $status_code);
        }

        $trace = debug_backtrace();
        $file = NULL;
        $line = NULL;

        $is_from_app = FALSE;
        if (isset($trace[1]['file']) AND strpos($trace[1]['file'], APPPATH) === 0) {
            $is_from_app = !self::is_extension($trace[1]['file']);
        }

        // If the application called show_error, don't output a backtrace, just the error
        if ($is_from_app) {
            $message = '<p>' . implode('</p><p>', (!is_array($message)) ? array($message) : $message) . '</p>';

            if (ob_get_level() > $this->ob_level + 1) {
                ob_end_flush();
            }
            ob_start();
            include(APPPATH . 'errors/' . $template . EXT);
            $buffer = ob_get_contents();
            ob_end_clean();
            return $buffer;
        }

        $message = implode(' / ', (!is_array($message)) ? array($message) : $message);

        // If the system called show_error, so lets find the actual file and line in application/ that caused it.
        foreach ($trace as $call) {
            if (isset($call['file']) AND strpos($call['file'], APPPATH) === 0 AND ! self::is_extension($call['file'])) {
                $file = $call['file'];
                $line = $call['line'];
                break;
            }
        }
        unset($trace);

        self::exception_handler(new ErrorException($message, E_ERROR, 0, $file, $line));
        return;
    }

    /**
     * Native PHP error handler
     *
     * @access	private
     * @param	string	the error severity
     * @param	string	the error string
     * @param	string	the error filepath
     * @param	string	the error line number
     * @return	string
     */
    function show_php_error($severity, $message, $filepath, $line) {
        self::error_handler($severity, $message, $filepath, $line);
    }

    /**
     * Is Extension
     *
     * This checks to see if the file path is to a core extension.
     *
     * @access	private
     * @param	string	$file
     * @return	bool
     */
    private static function is_extension($file) {
        foreach (array('libraries/', 'core/') as $folder) {
            if (strpos($file, APPPATH . $folder . config_item('subclass_prefix')) === 0) {
                return TRUE;
            }
        }
        return FALSE;
    }

}

if (!function_exists("throw_exception")) {

    /**
     * Throws an exception (which is logged and emailed), displaying an error to the user and terminating script execution.
     *
     * Pass any additional arguments you want to this function,
     * they will be included in the email because of the stack trace.
     *
     * @param string $message
     */
    function throw_exception($message, $data = array()) {
        throw new Exception($message);
    }

}

if (!function_exists("log_exception")) {

    /**
     * Logs an exception without displaying an error to the user
     * or terminating script execution. Great for notes on the execution of code.
     *
     * @param Exception $e
     */
    function log_exception(Exception $e) {
        Pancake_Exceptions::exception_handler($e, false);
    }

}

class QueryException extends Exception {
    
}

class ForeignKeyConstraintException extends QueryException {
    
}

/**
 * TVarDumper class file
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @link http://www.pradosoft.com/
 * @copyright Copyright &copy; 2005-2014 PradoSoft
 * @license http://www.pradosoft.com/license/
 * @package System.Util
 */

/**
 * TVarDumper class.
 *
 * TVarDumper is intended to replace the buggy PHP function var_dump and print_r.
 * It can correctly identify the recursively referenced objects in a complex
 * object structure. It also has a recursive depth control to avoid indefinite
 * recursive display of some peculiar variables.
 *
 * TVarDumper can be used as follows,
 * <code>
 *   echo TVarDumper::dump($var);
 * </code>
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @package System.Util
 * @since 3.0
 */
class TVarDumper {

    private static $_objects;
    private static $_output;
    private static $_depth;

    /**
     * Converts a variable into a string representation.
     * This method achieves the similar functionality as var_dump and print_r
     * but is more robust when handling complex objects such as PRADO controls.
     * @param mixed variable to be dumped
     * @param integer maximum depth that the dumper should go into the variable. Defaults to 10.
     * @return string the string representation of the variable
     */
    public static function dump($var, $depth = 4, $highlight = false) {
        self::$_output = '';
        self::$_objects = array();
        self::$_depth = $depth;
        self::dumpInternal($var, 0);
        if ($highlight) {
            $result = highlight_string("<?php\n" . self::$_output, true);
            return preg_replace('/&lt;\\?php<br \\/>/', '', $result, 1);
        } else
            return self::$_output;
    }

    private static function dumpInternal($var, $level) {
        switch (gettype($var)) {
            case 'boolean':
                self::$_output.=$var ? 'true' : 'false';
                break;
            case 'integer':
                self::$_output.="$var";
                break;
            case 'double':
                self::$_output.="$var";
                break;
            case 'string':
                if (IS_DEBUGGING) {
                    self::$_output.= htmlentities($var);
                } else {
                    self::$_output.= "string(" . strlen($var) . ")";
                }
                break;
            case 'resource':
                self::$_output.='{resource}';
                break;
            case 'NULL':
                self::$_output.="null";
                break;
            case 'unknown type':
                self::$_output.='{unknown}';
                break;
            case 'array':
                if (self::$_depth <= $level)
                    self::$_output.='array(...)';
                else if (empty($var))
                    self::$_output.='array()';
                else {
                    $keys = array_keys($var);
                    $spaces = str_repeat(' ', $level * 4);
                    self::$_output.="array\n" . $spaces . '(';
                    foreach ($keys as $key) {
                        self::$_output.="\n" . $spaces . "    [$key] => ";
                        self::$_output.=self::dumpInternal($var[$key], $level + 1);
                    }
                    self::$_output.="\n" . $spaces . ')';
                }
                break;
            case 'object':
                if (($id = array_search($var, self::$_objects, true)) !== false)
                    self::$_output.=get_class($var) . '#' . ($id + 1) . '(...)';
                else if (self::$_depth <= $level)
                    self::$_output.=get_class($var) . '(...)';
                else {
                    $id = array_push(self::$_objects, $var);
                    $className = get_class($var);
                    $members = (array) $var;
                    $keys = array_keys($members);
                    $spaces = str_repeat(' ', $level * 4);
                    self::$_output.="$className#$id\n" . $spaces . '(';
                    foreach ($keys as $key) {
                        $keyDisplay = strtr(trim($key), array("\0" => ':'));
                        self::$_output.="\n" . $spaces . "    [$keyDisplay] => ";
                        self::$_output.=self::dumpInternal($members[$key], $level + 1);
                    }
                    self::$_output.="\n" . $spaces . ')';
                }
                break;
        }
    }

}

/* End of file: Pancake_Exceptions.php */