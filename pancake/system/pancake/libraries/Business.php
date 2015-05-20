<?php

defined("BASEPATH") OR exit("No direct script access allowed");
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
 * @since		Version 4.1.31
 */

/**
 * The Business library.
 *
 * Handles the display of different business settings.
 *
 * @category Business Identities
 */
class Business {

    const ANY_BUSINESS = 0;

    protected static $business_details;

    public static function setBusiness($id) {
        $CI = get_instance()->load->model("settings/business_identities_m");
        self::$business_details = $CI->business_identities_m->getBusinessDetails($id);
    }

    public static function setBusinessFromClient($client_id) {
        $CI = get_instance()->load->model("settings/business_identities_m");
        $CI->load->model("clients/clients_m");
        self::setBusiness($CI->clients_m->getBusinessIdentity($client_id));
    }

    protected static function getShowNameAlongWithLogo() {
        if (self::$business_details === null) {
            self::setBusiness(self::ANY_BUSINESS);
        }

        return self::$business_details['show_name_along_with_logo'];
    }

    public static function getLogo($img_only = false, $anchor = true, $h = 1, $settings = null) {
        $logo = Business::getLogoUrl();
        if (isset($settings['use_business_name'])) {
            $title = Business::getBusinessName();
        } else {
            $title = Business::getBrandName();
        }
        $style = '';

        if (is_array($settings)) {
            foreach ($settings as $key => $value) {
                if ($key != "ignore_show_name") {
                    if (is_numeric($value)) {
                        $value = "{$value}px";
                    }
                    $style .= "$key: {$value}; ";
                }
            }
        }

        if (!empty($style)) {
            $style = 'style="'.$style.'"';
        }

        if (empty($logo)) {
            $anchor = $anchor ? anchor('admin', $title) : $title;
            $return = $img_only ? '' : "<h" . $h . " class='logo'>" . $anchor . "</h" . $h . ">";
        } else {
            $include_brand_name = (self::getShowNameAlongWithLogo() and !isset($settings['ignore_show_name']));
            $logo = "<img src='$logo' class='header-logo ".($include_brand_name ? "with-side-text" : "")."' $style alt='$title' />";

            if ($include_brand_name) {
                $anchor = $anchor ? anchor('admin', "$logo <span>$title</span>") : "$logo <span>$title</span>";
            } else {
                $anchor = $anchor ? anchor('admin', "$logo") : "$logo";
            }

            if ($h > 0) {
                $return = "<h" . $h . " class='logo'>" . $anchor . "</h" . $h . ">";
            } else {
                $return = $anchor;
            }
        }

        return $return;
    }

    public static function getLogoUrl() {
        if (self::$business_details === null) {
            self::setBusiness(self::ANY_BUSINESS);
        }

        $logo = self::$business_details['logo_filename'];

        if (!empty($logo)) {
            # Wrap $logo around site_url() if it's a relative URL, otherwise leave as is.
            $logo = !preg_match('!^\w+://! i', $logo) ? site_url($logo) : $logo;
            $logo = str_ireplace("/index.php/", "/", $logo);
        }

        return $logo;
    }

    public static function getBrandName() {

        if (self::$business_details === null) {
            self::setBusiness(self::ANY_BUSINESS);
        }

        return self::$business_details['brand_name'];
    }

    public static function getBusiness() {
        if (self::$business_details === null) {
            self::setBusiness(self::ANY_BUSINESS);
        }

        return self::$business_details;
    }

    public static function getBusinessName() {

        if (self::$business_details === null) {
            self::setBusiness(self::ANY_BUSINESS);
        }

        return self::$business_details['site_name'];
    }

    public static function getAdminName() {

        if (self::$business_details === null) {
            self::setBusiness(self::ANY_BUSINESS);
        }

        return self::$business_details['admin_name'];
    }

    public static function getMailingAddress() {

        if (self::$business_details === null) {
            self::setBusiness(self::ANY_BUSINESS);
        }

        return self::$business_details['mailing_address'];
    }

    public static function getHtmlEscapedMailingAddress() {
        return escape(nl2br(Business::getMailingAddress()));
    }

    public static function getNotifyEmail() {

        if (self::$business_details === null) {
            self::setBusiness(self::ANY_BUSINESS);
        }

        return self::$business_details['notify_email'];
    }

    public static function getBillingEmail() {

        if (self::$business_details === null) {
            self::setBusiness(self::ANY_BUSINESS);
        }

        return self::$business_details['billing_email'];
    }

    public static function getNotifyEmailFrom() {

        if (self::$business_details === null) {
            self::setBusiness(self::ANY_BUSINESS);
        }

        return self::$business_details['notify_email_from'];
    }

    public static function getBillingEmailFrom() {

        if (self::$business_details === null) {
            self::setBusiness(self::ANY_BUSINESS);
        }

        return self::$business_details['billing_email_from'];
    }

}
