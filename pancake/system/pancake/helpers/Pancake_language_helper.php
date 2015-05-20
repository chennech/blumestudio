<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Translates the given key, optionally with variables.
 *
 * __('Hello :1! Today is :2.', array('Joe', 'Friday'));
 *
 * In this example, your language files would look like this:
 *
 * $lang['Hello :1! Today is :2.'] = 'Hello :1! Today is :2.';
 *
 * @access	public
 * @param	string	The language line to translate
 * @param	array	An optional array of variables
 * @return	string
 */
function __($line, $vars = array()) {
    $lang = PAN::$CI->lang;
    $translated = ($line == '' OR ! isset($lang->language[$line])) ? FALSE : $lang->language[$line];
    $current_language = $lang->current_language;

    if (!$translated and $current_language != "english") {
        $translated = ($line == '' OR ! isset($lang->english_cache[$line])) ? FALSE : $lang->english_cache[$line];
        if ($translated) {
            $line = $translated;
        }
    } elseif ($translated) {
        $line = $translated;
    }

    for ($i = 0; $i < count($vars); $i++) {
        $line = str_replace(':' . ($i + 1), $vars[$i], $line);
    }

    return $line;
}

function lang($line, $id = '') {
    $line = __($line);

    if ($id != '') {
        $line = '<label for="' . $id . '">' . $line . "</label>";
    }

    return $line;
}

/* End of file: Pancake_language_helper.php */