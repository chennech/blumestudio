<?php

defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Pancake
 *
 * A simple, fast, self-hosted invoicing application
 *
 * @package		Pancake
 * @author		Pancake Dev Team
 * @copyright           Copyright (c) 2011, Pancake Payments
 * @license		http://pancakeapp.com/license
 * @link		http://pancakeapp.com
 * @since		Version 2.1.0
 */
// ------------------------------------------------------------------------

/**
 * All public controllers should extend this library
 *
 * @subpackage	Libraries
 * @category	Libraries
 * @author		Sean Drumm
 */
class Email_Template {

    private $_theme_path;

    /**
     * Constructor, actually not needed in this case, maybe future
     *
     * @return void
     */
    function __construct() {
        if (isset($this->template)) {
            $this->_theme_path = $this->template->get_theme_path();
        }
    }

    /**
     * Parse template text (variables)
     * @param string Email tempalte
     * @param array Replacement variables
     * @return type	parsed text
     */
    private static function parse_text($input, $array) {
        $search = preg_match_all('/{.*?}/', $input, $matches);
        for ($i = 0; $i < $search; $i++) {
            $matches[0][$i] = str_replace(array('{', '}'), null, $matches[0][$i]);
        }

        foreach ($matches[0] as $value) {
            //$replace = str_replace(array("\n","\t","\r")," ", $array[$value]);
            //die(print_r($array));
            if (isset($array[$value])) {
                $input = str_replace('{' . $value . '}', $array[$value], $input);
            }
        }

        return $input;
    }

    /**
     * Build (return) the email template, will return false if the file is
     * not found
     *
     * @param string $view
     * @return string|bool Email template text or false when file is not found
     */
    public static function build($view, $content, $logo = null, $subject = '') {
        $CI = &get_instance();

        if ($logo === null) {
            # Logo was not passed. I have no idea what's going on here.
            # Looking into other ways the logo was used yields no explanation that I can use.
            # Can we not make the logo here be fetched automatically?
            # I mean, in invoice_m, it's fetched from a call to logo(),
            # Which SHOULD be done HERE unless not possible, so that it's not necessary to update
            # all references to ::build(). And if it isn't possible, then PLEASE update all references to ::build.
            # Whoever did this so recklessly broke payment gateways.
            #
                # I'm using a simple fix to make the errors go away so I can finish Stripe, but this is NOT fixed.
            #
                # - Bruno
            $logo = array('content' => '');
        }

        # Change the theme to the frontend theme to get the email template contents.
        switch_theme(false);
        $CI->_theme_path = $CI->template->get_theme_path();
        $file_location = $CI->_theme_path . 'views/emails/' . $view . '.php';

        if (!file_exists($file_location)) {
            $file_location = FCPATH . 'third_party/themes/pancake/views/emails/' . $view . '.php';
        }

        if (file_exists($file_location)) {
            $email_content = file_get_contents($file_location);
            $email_content = Email_Template::parse_text($email_content, array('content' => $content, 'logo' => $logo['content'], 'subject' => $subject));
            # Change the theme back to the admin theme to keep things running smoothly.
            switch_theme(true);
            return $email_content;
        } else {
            show_error('Could not send the email. ' . $file_location . ' does not exist.');
            return false;
        }
    }

}
