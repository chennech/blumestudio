<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| Base Site URL
|--------------------------------------------------------------------------
|
| URL to your CodeIgniter root. Typically this will be your base URL,
| WITH a trailing slash:
|
|	http://example.com/
|
*/
$config['base_url']	= BASE_URL;

/*
|--------------------------------------------------------------------------
| Index File
|--------------------------------------------------------------------------
|
| Typically this will be your index.php file, unless you've renamed it to
| something else. If you are using mod_rewrite to remove the page set this
| variable so that it is blank.
|
*/

$htaccess_servers = array(
    "Apache",
    "LiteSpeed",
    "WebServerX",
    "1984"
);

# Always use index.php by default.
$config['index_page'] = 'index.php';

foreach ($htaccess_servers as $server) {
    if (strpos($_SERVER["SERVER_SOFTWARE"], $server) !== false) {
        # Remove index.php if the server understands .htaccess files.
        $config['index_page'] = is_file(FCPATH.'.htaccess') ? '' : 'index.php';
    }
}

# This section is here so that the installer can override index_page detection if it's incorrect 
# (e.g. .htaccess exists but URL rewriting is not on, so index.php should still be added to URLs) 
# Don't touch it.
$config['index_page'] = $config['index_page'];

$config['timezones'] = array(
    'Etc/GMT+12' => '(GMT-12:00) International Date Line West',
    'Pacific/Apia' => '(GMT-11:00) Midway Island, Samoa',
    'Pacific/Honolulu' => '(GMT-10:00) Hawaii',
    'America/Anchorage' => '(GMT-09:00) Alaska',
    'America/Los_Angeles' => '(GMT-08:00) Pacific Time (US and Canada); Tijuana',
    'America/Phoenix' => '(GMT-07:00) Arizona',
    'America/Denver' => '(GMT-07:00) Mountain Time (US and Canada)',
    'America/Chihuahua' => '(GMT-07:00) Chihuahua, La Paz, Mazatlan',
    'America/Managua' => '(GMT-06:00) Central America',
    'America/Regina' => '(GMT-06:00) Saskatchewan',
    'America/Mexico_City' => '(GMT-06:00) Guadalajara, Mexico City, Monterrey',
    'America/Chicago' => '(GMT-06:00) Central Time (US and Canada)',
    'America/Indianapolis' => '(GMT-05:00) Indiana (East)',
    'America/Bogota' => '(GMT-05:00) Bogota, Lima, Quito',
    'America/New_York' => '(GMT-05:00) Eastern Time (US and Canada)',
    'America/Caracas' => '(GMT-04:00) Caracas, La Paz',
    'America/Santiago' => '(GMT-04:00) Santiago',
    'America/Halifax' => '(GMT-04:00) Atlantic Time (Canada)',
    'America/St_Johns' => '(GMT-03:30) Newfoundland',
    'America/Buenos_Aires' => '(GMT-03:00) Buenos Aires, Georgetown',
    'America/Godthab' => '(GMT-03:00) Greenland',
    'America/Sao_Paulo' => '(GMT-03:00) Brasilia',
    'America/Noronha' => '(GMT-02:00) Mid-Atlantic',
    'Atlantic/Cape_Verde' => '(GMT-01:00) Cape Verde Is.',
    'Atlantic/Azores' => '(GMT-01:00) Azores',
    'Africa/Casablanca' => '(GMT) Casablanca, Monrovia',
    'Europe/London' => '(GMT) Greenwich Mean Time : Dublin, Edinburgh, Lisbon, London',
    'Africa/Lagos' => '(GMT+01:00) West Central Africa',
    'Europe/Berlin' => '(GMT+01:00) Amsterdam, Berlin, Bern, Rome, Stockholm, Vienna',
    'Europe/Paris' => '(GMT+01:00) Brussels, Copenhagen, Madrid, Paris',
    'Europe/Sarajevo' => '(GMT+01:00) Sarajevo, Skopje, Warsaw, Zagreb',
    'Europe/Belgrade' => '(GMT+01:00) Belgrade, Bratislava, Budapest, Ljubljana, Prague',
    'Africa/Johannesburg' => '(GMT+02:00) Harare, Pretoria',
    'Asia/Jerusalem' => '(GMT+02:00) Jerusalem',
    'Europe/Istanbul' => '(GMT+02:00) Athens, Istanbul, Minsk',
    'Europe/Helsinki' => '(GMT+02:00) Helsinki, Kyiv, Riga, Sofia, Tallinn, Vilnius',
    'Africa/Cairo' => '(GMT+02:00) Cairo',
    'Europe/Bucharest' => '(GMT+02:00) Bucharest',
    'Africa/Nairobi' => '(GMT+03:00) Nairobi',
    'Asia/Riyadh' => '(GMT+03:00) Kuwait, Riyadh',
    'Europe/Moscow' => '(GMT+03:00) Moscow, St. Petersburg, Volgograd',
    'Asia/Baghdad' => '(GMT+03:00) Baghdad',
    'Asia/Tehran' => '(GMT+03:30) Tehran',
    'Asia/Muscat' => '(GMT+04:00) Abu Dhabi, Muscat',
    'Asia/Tbilisi' => '(GMT+04:00) Baku, Tbilisi, Yerevan',
    'Asia/Kabul' => '(GMT+04:30) Kabul',
    'Asia/Karachi' => '(GMT+05:00) Islamabad, Karachi, Tashkent',
    'Asia/Yekaterinburg' => '(GMT+05:00) Ekaterinburg',
    'Asia/Calcutta' => '(GMT+05:30) Chennai, Kolkata, Mumbai, New Delhi',
    'Asia/Katmandu' => '(GMT+05:45) Kathmandu',
    'Asia/Colombo' => '(GMT+06:00) Sri Jayawardenepura',
    'Asia/Dhaka' => '(GMT+06:00) Astana, Dhaka',
    'Asia/Novosibirsk' => '(GMT+06:00) Almaty, Novosibirsk',
    'Asia/Rangoon' => '(GMT+06:30) Rangoon',
    'Asia/Bangkok' => '(GMT+07:00) Bangkok, Hanoi, Jakarta',
    'Asia/Krasnoyarsk' => '(GMT+07:00) Krasnoyarsk',
    'Australia/Perth' => '(GMT+08:00) Perth',
    'Asia/Taipei' => '(GMT+08:00) Taipei',
    'Asia/Singapore' => '(GMT+08:00) Kuala Lumpur, Singapore',
    'Asia/Hong_Kong' => '(GMT+08:00) Beijing, Chongqing, Hong Kong, Urumqi',
    'Asia/Irkutsk' => '(GMT+08:00) Irkutsk, Ulaan Bataar',
    'Asia/Tokyo' => '(GMT+09:00) Osaka, Sapporo, Tokyo',
    'Asia/Seoul' => '(GMT+09:00) Seoul',
    'Asia/Yakutsk' => '(GMT+09:00) Yakutsk',
    'Australia/Darwin' => '(GMT+09:30) Darwin',
    'Australia/Adelaide' => '(GMT+09:30) Adelaide',
    'Pacific/Guam' => '(GMT+10:00) Guam, Port Moresby',
    'Australia/Brisbane' => '(GMT+10:00) Brisbane',
    'Asia/Vladivostok' => '(GMT+10:00) Vladivostok',
    'Australia/Hobart' => '(GMT+10:00) Hobart',
    'Australia/Sydney' => '(GMT+10:00) Canberra, Melbourne, Sydney',
    'Asia/Magadan' => '(GMT+11:00) Magadan, Solomon Is., New Caledonia',
    'Pacific/Fiji' => '(GMT+12:00) Fiji, Kamchatka, Marshall Is.',
    'Pacific/Auckland' => '(GMT+12:00) Auckland, Wellington',
    'Pacific/Tongatapu' => '(GMT+13:00) Nuku\'alofa',
);

/*
|--------------------------------------------------------------------------
| URI PROTOCOL
|--------------------------------------------------------------------------
|
| This item determines which server global should be used to retrieve the
| URI string.  The default setting of "AUTO" works for most servers.
| If your links do not seem to work, try one of the other delicious flavors:
|
| 'AUTO'			Default - auto detects
| 'PATH_INFO'		Uses the PATH_INFO
| 'QUERY_STRING'	Uses the QUERY_STRING
| 'REQUEST_URI'		Uses the REQUEST_URI
| 'ORIG_PATH_INFO'	Uses the ORIG_PATH_INFO
|
*/
$config['uri_protocol']	= "AUTO";

/*
|--------------------------------------------------------------------------
| URL suffix
|--------------------------------------------------------------------------
|
| This option allows you to add a suffix to all URLs generated by CodeIgniter.
| For more information please see the user guide:
|
| http://codeigniter.com/user_guide/general/urls.html
*/

$config['url_suffix'] = "";

/*
|--------------------------------------------------------------------------
| Default Language
|--------------------------------------------------------------------------
|
| This determines which set of language files should be used. Make sure
| there is an available translation if you intend to use something other
| than english.
|
*/
$config['language']	= "english";

/*
|--------------------------------------------------------------------------
| Default Character Set
|--------------------------------------------------------------------------
|
| This determines which character set is used by default in various methods
| that require a character set to be provided.
|
*/
$config['charset'] = "UTF-8";

/*
|--------------------------------------------------------------------------
| Enable/Disable System Hooks
|--------------------------------------------------------------------------
|
| If you would like to use the "hooks" feature you must enable it by
| setting this variable to TRUE (boolean).  See the user guide for details.
|
*/
$config['enable_hooks'] = false;


/*
|--------------------------------------------------------------------------
| Class Extension Prefix
|--------------------------------------------------------------------------
|
| This item allows you to set the filename/classname prefix when extending
| native libraries.  For more information please see the user guide:
|
| http://codeigniter.com/user_guide/general/core_classes.html
| http://codeigniter.com/user_guide/general/creating_libraries.html
|
*/
$config['subclass_prefix'] = 'Pancake_';


/*
|--------------------------------------------------------------------------
| Allowed URL Characters
|--------------------------------------------------------------------------
|
| This lets you specify with a regular expression which characters are permitted
| within your URLs.  When someone tries to submit a URL with disallowed
| characters they will get a warning message.
|
| As a security measure you are STRONGLY encouraged to restrict URLs to
| as few characters as possible.  By default only these are allowed: a-z 0-9~%.:_-
|
| Leave blank to allow all characters -- but only if you are insane.
|
| DO NOT CHANGE THIS UNLESS YOU FULLY UNDERSTAND THE REPERCUSSIONS!!
|
*/
//$config['permitted_uri_chars'] = 'a-z 0-9~%.:_\-=\[\]';
$config['permitted_uri_chars'] = '';

/*
|--------------------------------------------------------------------------
| Enable Query Strings
|--------------------------------------------------------------------------
|
| By default CodeIgniter uses search-engine friendly segment based URLs:
| example.com/who/what/where/
|
| You can optionally enable standard query string based URLs:
| example.com?who=me&what=something&where=here
|
| Options are: TRUE or FALSE (boolean)
|
| The other items let you set the query string "words" that will
| invoke your controllers and its functions:
| example.com/index.php?c=controller&m=function
|
| Please note that some of the helpers won't work as expected when
| this feature is enabled, since CodeIgniter is designed primarily to
| use segment based URLs.
|
*/

# Do NOT use "allow_get_array". Query strings break some Pancake installations.
# Do NOT EVER use $_GET for ANYTHING. Use URI segments instead.
$config['allow_get_array']		= false;
$config['enable_query_strings'] = FALSE;
$config['controller_trigger'] 	= 'c';
$config['function_trigger'] 	= 'm';
$config['directory_trigger'] 	= 'd'; // experimental not currently in use

/*
|--------------------------------------------------------------------------
| Error Logging Threshold
|--------------------------------------------------------------------------
|
| If you have enabled error logging, you can set an error threshold to
| determine what gets logged. Threshold options are:
| You can enable error logging by setting a threshold over zero. The
| threshold determines what gets logged. Threshold options are:
|
|	0 = Disables logging, Error logging TURNED OFF
|	1 = Error Messages (including PHP errors)
|	2 = Debug Messages
|	3 = Informational Messages
|	4 = All Messages
|
| For a live site you'll usually only enable Errors (1) to be logged otherwise
| your log files will fill up very fast.
|
*/
$config['log_threshold'] = 0;

/*
|--------------------------------------------------------------------------
| Error Logging Directory Path
|--------------------------------------------------------------------------
|
| Leave this BLANK unless you would like to set something other than the default
| system/logs/ folder.  Use a full server path with trailing slash.
|
*/
$config['log_path'] = APPPATH.'logs/';

/*
|--------------------------------------------------------------------------
| Date Format for Logs
|--------------------------------------------------------------------------
|
| Each item that is logged has an associated date. You can use PHP date
| codes to set your own date formatting
|
*/
$config['log_date_format'] = 'Y-m-d H:i:s';

/*
|--------------------------------------------------------------------------
| Cache Directory Path
|--------------------------------------------------------------------------
|
| Leave this BLANK unless you would like to set something other than the default
| system/cache/ folder.  Use a full server path with trailing slash.
|
*/
$config['cache_path'] = '';

/*
|--------------------------------------------------------------------------
| Encryption Key
|--------------------------------------------------------------------------
|
| If you use the Encryption class or the Sessions class with encryption
| enabled you MUST set an encryption key.  See the user guide for info.
|
*/
$config['encryption_key'] = "SET-THIS-KEY";

/*
|--------------------------------------------------------------------------
| Session Variables
|--------------------------------------------------------------------------
|
| 'session_cookie_name' = the name you want for the cookie
| 'encrypt_sess_cookie' = TRUE/FALSE (boolean).  Whether to encrypt the cookie
| 'session_expiration'  = the number of SECONDS you want the session to last.
|  by default sessions last 28800 seconds (eight hours).  Set to zero for no expiration.
| 'time_to_update'		= how many seconds between CI refreshing Session Information
|
*/
$config['sess_cookie_name']		= 'ci_session';
$config['sess_expiration']		= 2880000;
$config['sess_encrypt_cookie']	= FALSE;
$config['sess_use_database']	= TRUE;
$config['sess_table_name']		= 'ci_sessions';
$config['sess_match_ip']		= FALSE;
$config['sess_match_useragent']	= TRUE;
$config['sess_time_to_update'] 	= 28800;

/*
|--------------------------------------------------------------------------
| Cookie Related Variables
|--------------------------------------------------------------------------
|
| 'cookie_prefix' = Set a prefix if you need to avoid collisions
| 'cookie_domain' = Set to .your-domain.com for site-wide cookies
| 'cookie_path'   =  Typically will be a forward slash
|
*/
$config['cookie_prefix']	= "";
$config['cookie_domain']	= "";
$config['cookie_path'] = isset($_SERVER['HTTP_HOST']) ? str_ireplace(array('http://'.$_SERVER['HTTP_HOST'], 'https://'.$_SERVER['HTTP_HOST']), '', BASE_URL) : "/";
$config['cookie_path'] = preg_replace('/\/+/', '/', $config['cookie_path']);

# Fixes an issue where the cookie path would include the server port.
$config['cookie_path'] = preg_replace('/^:\d+\//', '/', $config['cookie_path']);

/*
|--------------------------------------------------------------------------
| Global XSS Filtering
|--------------------------------------------------------------------------
|
| Determines whether the XSS filter is always active when GET, POST or
| COOKIE data is encountered
|
*/
$config['global_xss_filtering'] = FALSE;

/*
|--------------------------------------------------------------------------
| Cross Site Forgery Request
|--------------------------------------------------------------------------
| Enables a CSFR cookie token to be set. When set to TRUE, token will be
| checked on a submitted form. If you are accepting user data, it is strongly
| recommended CSRF protection be enabled.
*/
$config['csrf_protection'] = FALSE;


/*
|--------------------------------------------------------------------------
| Output Compression
|--------------------------------------------------------------------------
|
| Enables Gzip output compression for faster page loads.  When enabled,
| the output class will test whether your server supports Gzip.
| Even if it does, however, not all browsers support compression
| so enable only if you are reasonably sure your visitors can handle it.
|
| VERY IMPORTANT:  If you are getting a blank page when compression is enabled it
| means you are prematurely outputting something to your browser. It could
| even be a line of whitespace at the end of one of your scripts.  For
| compression to work, nothing can be sent before the output buffer is called
| by the output class.  Do not "echo" any values with compression enabled.
|
*/
$config['compress_output'] = false;

/*
|--------------------------------------------------------------------------
| Master Time Reference
|--------------------------------------------------------------------------
|
| Options are "local" or "gmt".  This pref tells the system whether to use
| your server's local time as the master "now" reference, or convert it to
| GMT.  See the "date helper" page of the user guide for information
| regarding date handling.
|
*/
$config['time_reference'] = 'local';


/*
|--------------------------------------------------------------------------
| Rewrite PHP Short Tags
|--------------------------------------------------------------------------
|
| If your PHP installation does not have short tag support enabled CI
| can rewrite the tags on-the-fly, enabling you to utilize that syntax
| in your view files.  Options are TRUE or FALSE (boolean)
|
*/
$config['rewrite_short_tags'] = FALSE;


/*
|--------------------------------------------------------------------------
| Reverse Proxy IPs
|--------------------------------------------------------------------------
|
| If your server is behind a reverse proxy, you must whitelist the proxy IP
| addresses from which CodeIgniter should trust the HTTP_X_FORWARDED_FOR
| header in order to properly identify the visitor's IP address.
| Comma-delimited, e.g. '10.0.1.200,10.0.1.201'
|
*/
$config['proxy_ips'] = '';


/*
|--------------------------------------------------------------------------
| Module Locations
|--------------------------------------------------------------------------
|
| Modular Extensions: Where are modules located?
|
*/
$config['modules_locations'] = array(
	APPPATH.'modules/' => '../modules/',
	FCPATH.'third_party/modules/' => '../../third_party/modules/'
);

/* End of file config.php */
/* Location: ./application/config/config.php */