<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Currency Library
 */
class Currency {

    private static $_current = 'USD';
    protected static $_convert_cache = null;

    # A default set of currencies. To add more use the config file, or as a currency in Settings > Currencies
    private static $_currencies;

    public function __construct($params = array()) {
        if (isset($params['currencies'])) {
            foreach ($params['currencies'] as $code => $array) {
                $params['currencies'][$code]['name'] = $array['name'];
            }
            self::$_currencies = $params['currencies'];
        }
        
        $db = get_instance()->db;
        $currencies = $db->get('currencies')->result_array();
        foreach ($currencies as $currency) {
            if (!isset(self::$_currencies[$currency['code']])) {
                self::$_currencies[$currency['code']] = array(
                    "symbol" => $currency['code'],
                    "name" => $currency['name']
                );
            }
        }
        
        asort(self::$_currencies);
    }

    public static function set($currency) {
        self::$_current = $currency;
    }

    public static function get() {
        return self::$_currencies[self::$_current];
    }

    public static function currencies() {
        return self::$_currencies;
    }

    public static function symbol($code = null) {
        $code or $code = self::$_current;
        if (is_array($code) and isset($code['code'])) {
            $code = $code['code'];
        }

        // Only use the symbol if we know how to show it, otherwise show code
        if (isset(self::$_currencies[$code]['symbol'])) {
            return self::$_currencies[$code]['symbol'];
        } else {
            return $code;
        }
    }

    public static function code($currency_id = 0) {
        if ($currency_id == 0) {
            return self::$_current;
        }

        # A currency from the DB.
        $CI = &get_instance();
        $currency = $CI->db->get_where('currencies', array('id' => $currency_id))->row();
        return !empty($currency) ? $currency->code : self::$_current;
    }

    public static function format($amount, $code = null, $maintain_precision = false) {
        // If an ID is passed then find the code, and use that
        if (is_numeric($code) and $code > 0) {
            $code = self::code($code);
        }

        if ($code) {
            if (isset(self::$_currencies[$code])) {
                $code = self::$_currencies[$code]['symbol'];
            } else {
                $code = $code . " ";
            }
        }

        // Format whatever code/symbol we have
        $formatted = $code ? $code : self::$_currencies[self::$_current]['symbol'];

        if (strncmp($amount, '-', 1) === 0) {
            $amount = substr($amount, 1);
            $formatted = '-' . $formatted;
        }
        $formatted .= pancake_number_format($amount, $maintain_precision);

        return $formatted;
    }

    public static function switch_default($new_default_currency_code) {

        $old_default_currency_code = self::code();

        if ($new_default_currency_code == $old_default_currency_code) {
            # No need to change anything.
            return;
        }

        if (!isset(self::$_currencies[$old_default_currency_code]) or ! isset(self::$_currencies[$new_default_currency_code])) {
            throw new Exception("It is not possible to switch Pancake's default currency to an unsupported currency.");
        }

        $CI = get_instance();
        $buffer = $CI->db->get('currencies')->result_array();
        $currencies = array();
        $batch_updates = array();
        foreach ($buffer as $row) {
            $currencies[$row['code']] = $row;
            $batch_updates[] = array(
                'code' => $row['code'],
                'rate' => self::convert(1, $new_default_currency_code, $row['code'], true)
            );
        }

        if (count($batch_updates) > 0) {
            $CI->db->update_batch('currencies', $batch_updates, 'code');
        }

        self::_reset_convert_cache();

        $old_exchange_rate = self::convert(1, $new_default_currency_code, $old_default_currency_code);

        if (!isset($currencies[$old_default_currency_code])) {
            $CI->currency_m->insert_currencies(self::$_currencies[$old_default_currency_code]['name'], $old_default_currency_code, $old_exchange_rate);
        }

        if (!isset($currencies[$new_default_currency_code])) {
            $CI->currency_m->insert_currencies(self::$_currencies[$new_default_currency_code]['name'], $new_default_currency_code, 1);
        }

        $new_currency_id = $CI->db->select('id')->where('code', $new_default_currency_code)->get('currencies')->row_array();
        $new_currency_id = $new_currency_id['id'];

        $old_currency_id = $CI->db->select('id')->where('code', $old_default_currency_code)->get('currencies')->row_array();
        $old_currency_id = $old_currency_id['id'];

        $tables = array('invoices', 'projects', 'project_templates');
        foreach ($tables as $table) {
            $CI->db->where('currency_id', 0)->update($table, array(
                'currency_id' => $old_currency_id,
                'exchange_rate' => $old_exchange_rate
            ));

            $CI->db->where('currency_id', $new_currency_id)->update($table, array(
                'currency_id' => 0,
                'exchange_rate' => 1
            ));

            foreach ($currencies as $code => $currency) {
                if ($code != $old_default_currency_code and $code != $new_default_currency_code) {
                    $CI->db->where('currency_id', $currency['id'])->update($table, array(
                        'exchange_rate' => self::convert(1, $new_default_currency_code, $code)
                    ));
                }
            }
        }
    }

    /**
     * Converts $amount from a currency code to another.
     *
     * Use Settings::get('currency') to get the default currency.
     *
     * @staticvar array $currencies
     * @param float $amount
     * @param string $from
     * @param string $to
     * @param bool $force_refresh
     * @return float
     */
    public static function convert($amount, $from, $to = null, $force_refresh = false) {
        $base_currency_code = Settings::get('currency');

        if (empty($from)) {
            $from = Settings::get('currency');
        }

        if (empty($to)) {
            $to = Settings::get('currency');
        }

        if ($from == $to) {
            return $amount;
        }

        self::_cache_convert_currencies();

        if (!isset(self::$_convert_cache[$base_currency_code])) {
            self::$_convert_cache[$base_currency_code] = 1;
        }

        if (!isset(self::$_convert_cache[$from]) or $force_refresh) {
            $url = "http://finance.yahoo.com/d/quotes.csv?e=.csv&f=sl1d1t1&s={$base_currency_code}$from=X";
            $rate = get_url_contents($url);
            $rate = explode(",", $rate, 3);
            if (isset($rate[1])) {
                $rate = $rate[1];
                $rate = filter_var($rate, FILTER_VALIDATE_FLOAT, FILTER_FLAG_ALLOW_THOUSAND);

                if ($rate == 0) {
                    if (!isset(self::$_convert_cache[$from])) {
                        # Throw an error ONLY if it couldn't find a currency conversion AND none exists in the DB.
                        debug("Could not convert find a currency conversion from $base_currency_code to $from.");
                    }
                } else {
                    self::$_convert_cache[$from] = (float) $rate;
                }
            } else {
                # Throw an error ONLY if it couldn't find a currency conversion AND none exists in the DB.
                if (!isset(self::$_convert_cache[$from])) {
                    debug("Could not convert find a currency conversion from $base_currency_code to $from.");
                }
            }
        }

        if (!isset(self::$_convert_cache[$to]) or $force_refresh) {
            $url = "http://finance.yahoo.com/d/quotes.csv?e=.csv&f=sl1d1t1&s={$base_currency_code}$to=X";
            $rate = get_url_contents($url);
            $rate = explode(",", $rate, 3);
            if (isset($rate[1])) {
                $rate = $rate[1];
                $rate = filter_var($rate, FILTER_VALIDATE_FLOAT, FILTER_FLAG_ALLOW_THOUSAND);

                if ($rate == 0) {
                    if (!isset(self::$_convert_cache[$to])) {
                        # Throw an error ONLY if it couldn't find a currency conversion AND none exists in the DB.
                        debug("Could not convert find a currency conversion from $base_currency_code to $to.");
                    }
                } else {
                    self::$_convert_cache[$to] = (float) $rate;
                }
            } else {
                # Throw an error ONLY if it couldn't find a currency conversion AND none exists in the DB.
                if (!isset(self::$_convert_cache[$to])) {
                    debug("Could not convert find a currency conversion from $base_currency_code to $to.");
                }
            }
        }

        $eur = $amount / self::$_convert_cache[$from];
        return $eur * self::$_convert_cache[$to];
    }

    protected static function _cache_convert_currencies() {
        if (self::$_convert_cache === null) {
            $buffer = get_instance()->db->get('currencies')->result_array();
            foreach ($buffer as $row) {
                $row['rate'] = filter_var($row['rate'], FILTER_VALIDATE_FLOAT, FILTER_FLAG_ALLOW_THOUSAND);
                self::$_convert_cache[$row['code']] = $row['rate'];
            }
        }
    }

    protected static function _reset_convert_cache() {
        self::$_convert_cache = null;
    }

}
