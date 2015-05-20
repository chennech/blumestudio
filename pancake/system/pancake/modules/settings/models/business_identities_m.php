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
 * The Business Identities Model
 *
 * @subpackage	Models
 * @category	Settings
 */
class Business_identities_m extends Pancake_Model {

    public function processSettingsInput($existing_businesses, $new_businesses, $uploaded_existing_business_logos, $uploaded_new_business_logos) {
        $existing_ids = $this->getIds();

        if (count($new_businesses) > 0) {
            $buffer = $new_businesses;
            $new_businesses = array();
            foreach ($buffer['site_name'] as $key => $business_name) {
                $new_businesses[] = array(
                    'site_name' => $business_name,
                    'admin_name' => $buffer['admin_name'][$key],
                    'notify_email' => $buffer['notify_email'][$key],
                    'mailing_address' => $buffer['mailing_address'][$key],
                    'billing_email' => $buffer['billing_email'][$key],
                    'brand_name' => $buffer['brand_name'][$key],
                    'notify_email_from' => $buffer['notify_email_from'][$key],
                    'billing_email_from' => $buffer['billing_email_from'][$key],
                    'show_name_along_with_logo' => isset($buffer['show_name_along_with_logo'][$key]),
                );
            }

            $new_businesses = $this->processLogoFiles($uploaded_new_business_logos, $new_businesses);
        }

        $existing_businesses = $this->processLogoFiles($uploaded_existing_business_logos, $existing_businesses);


        # Update existing businesses first.
        foreach ($existing_businesses as $primary_key => $business) {
            if ($business['remove_logo_filename'] and ! isset($business['logo_filename'])) {
                # If the user clicked the "remove logo" button and didn't upload a logo after doing that, empty out the logo.
                $business['logo_filename'] = '';
            }
            
            $business['show_name_along_with_logo'] = isset($business['show_name_along_with_logo']);

            unset($business['remove_logo_filename']);
            unset($existing_ids[$primary_key]);
            $this->db->where("id", $primary_key)->update("business_identities", $business);
        }

        # Delete businesses deleted by the user.
        if (count($existing_ids) > 0) {
            $this->db->where_in("id", $existing_ids)->delete("business_identities");
        }

        if (count($new_businesses) > 0) {
            # Create new businesses added by the user.
            foreach ($new_businesses as $new_business) {
                unset($business['remove_logo_filename']);
                $this->db->insert("business_identities", $new_business);
            }
        }

        Business::setBusiness(Business::ANY_BUSINESS);
    }

    protected function processLogoFiles($logos, $businesses) {
        # Refresh logos for existing businesses.
        foreach (array_keys($logos["name"]) as $key) {
            foreach (array_keys($logos["name"][$key]) as $subkey) {
                if ($logos["error"][$key][$subkey] == UPLOAD_ERR_OK) {
                    # Prepare the input array for uploading.
                    $logo = array(
                        "name" => $logos["name"][$key][$subkey],
                        "type" => $logos["type"][$key][$subkey],
                        "tmp_name" => $logos["tmp_name"][$key][$subkey],
                        "error" => $logos["error"][$key][$subkey],
                        "size" => $logos["size"][$key][$subkey],
                    );

                    $this->load->model("files/files_m");
                    $buffer = $this->files_m->upload($logo, "settings");
                    if ($buffer) {
                        reset($buffer);
                        $logo = current($buffer);
                        $logo = $logo["url"];

                        # Look for the relative path to the logo (e.g. /uploads/branding/logofilename.png).
                        $matches = array();
                        preg_match("/^http(?:.*)\/uploads\/(.*)$/i", $logo, $matches);
                        if (isset($matches[1])) {
                            $relative_logo_url = "uploads/" . $matches[1];
                        } else {
                            # Couldn"t find the relative path, so store the absolute path just so that data isn"t lost.
                            $relative_logo_url = $logo;
                        }

                        $businesses[$key == "logo" ? $subkey : $key]["logo_filename"] = $relative_logo_url;
                    }
                }
            }
        }

        return $businesses;
    }

    /**
     * Gets a business's details.
     * 
     * If the business with that ID no longer exists,
     * fetches the first business in the DB to fall back on.
     * 
     * @param integer $id
     */
    public function getBusinessDetails($id) {
        
        $buffer = $this->getAllBusinesses();
        $record = isset($buffer[$id]) ? $buffer[$id] : reset($buffer);

        if (!isset($record['id'])) {
            # There are no businesses in this system; migrate old data.
            # Look for the relative path to the logo (e.g. /uploads/branding/logofilename.png).
            $matches = array();
            preg_match("/^http(?:.*)\/uploads\/(.*)$/i", Settings::get("logo_url"), $matches);
            if (isset($matches[1])) {
                $relative_logo_url = "uploads/" . $matches[1];
            } else {
                # Couldn't find the relative path, so store the absolute path just so that data isn't lost.
                $relative_logo_url = Settings::get("logo_url");
            }

            $this->db->insert("business_identities", array(
                "site_name" => Settings::get("site_name"),
                "admin_name" => Settings::get("admin_name"),
                "mailing_address" => Settings::get("mailing_address"),
                "notify_email" => Settings::get("notify_email"),
                "logo_filename" => $relative_logo_url,
            ));
        }

        return $record;
    }

    public function getIds() {
        $buffer = $this->getAllBusinesses();
        $results = array();
        foreach ($buffer as $row) {
            $results[$row["id"]] = $row["id"];
        }
        return $results;
    }

    public function getAllBusinesses() {
        static $cache = null;
        if ($cache === null) {
            $buffer = $this->db->get("business_identities")->result_array();
            $cache = array();
            foreach ($buffer as $row) {
                $cache[$row["id"]] = $row;
            }
        }
        return $cache;
    }

    public function getAllBusinessesDropdown() {
        $buffer = $this->getAllBusinesses();
        $results = array();
        foreach ($buffer as $row) {
            $results[$row["id"]] = $row['site_name'];
        }
        return $results;
    }

}
