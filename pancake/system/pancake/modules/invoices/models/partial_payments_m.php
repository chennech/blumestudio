<?php

defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Pancake
 *
 * A simple, fast, self-hosted invoicing application
 *
 * @package		Pancake
 * @author		Pancake Dev Team
 * @copyright           Copyright (c) 2010, Pancake Payments
 * @license		http://pancakeapp.com/license
 * @link		http://pancakeapp.com
 * @since		Version 2.2.0
 */
// ------------------------------------------------------------------------

/**
 * The partial payments model
 *
 * @subpackage	Models
 * @category	Payments
 */
class Partial_payments_m extends Pancake_Model {

    /**
     * @var string The payments table name
     */
    protected $table = 'partial_payments';
    protected $totals_cache = array();
    protected $tax_totals_cache = array();
    protected $totals_cache_exists = false;
    protected $getInvoicePartialPayments_cache = null;

    function get_tax_total($unique_id) {
        $this->cache_totals();
        return isset($this->tax_totals_cache[$unique_id]) ? $this->tax_totals_cache[$unique_id] : 0;
    }

    function cache_totals() {
        if (!$this->totals_cache_exists) {
            $this->load->model("invoices/invoice_m");

            $unique_ids = array();
            foreach ($this->db->select("unique_id, amount")->get("invoices")->result_array() as $row) {
                $this->totals_cache[$row['unique_id']] = (float) $row['amount'];
                $this->tax_totals_cache[$row['unique_id']] = 0;
                $unique_ids[] = $row['unique_id'];
            }

            $results = $this->invoice_m->rows_with_tax_total($unique_ids);
            foreach ($results as $unique_id => $rows) {
                foreach ($rows as $row) {
                    $this->totals_cache[$unique_id] += $row['tax_total'];
                    $this->tax_totals_cache[$unique_id] += $row['tax_total'];
                }
            }

            $this->totals_cache_exists = true;
        }
    }

    function flexible_get_all($config) {

        $from = isset($config['from']) ? $config['from'] : 0;
        $to = isset($config['to']) ? $config['to'] : 0;
        $from_payment_date = isset($config['from_payment_date']) ? $config['from_payment_date'] : 0;
        $to_payment_date = isset($config['to_payment_date']) ? $config['to_payment_date'] : 0;
        $client_id = isset($config['client_id']) ? $config['client_id'] : NULL;
        $overdue = isset($config['overdue']) ? $config['overdue'] : NULL;
        $paid = isset($config['paid']) ? $config['paid'] : NULL;

        $data = array();
        if ($client_id) {
            $this->db->where('client_id', $client_id);
        }

        if ($from != 0) {
            $this->db->where('date_entered >=', $from);
        }

        if ($to != 0) {
            $this->db->where('date_entered <=', $to);
        }

        if ($from_payment_date != 0) {
            $this->db->where('partial_payments.payment_date >=', $from_payment_date);
        }

        if ($to_payment_date != 0) {
            $this->db->where('partial_payments.payment_date <=', $to_payment_date);
        }

        if ($overdue !== NULL) {
            if ($overdue) {
                $this->db->where(array('due_date <' => time()));
            } else {
                $this->db->where(array('due_date >' => time()));
            }
        }

        if ($paid !== NULL) {
            if ($paid) {
                $this->db->where(array('partial_payments.is_paid' => 1));
            } else {
                $this->db->where(array('partial_payments.is_paid' => 0));
            }
        }

        $table = $this->db->dbprefix('partial_payments');
        $results = $this->db->select($this->db->dbprefix('invoices') . ".exchange_rate, partial_payments.txn_id, partial_payments.is_percentage,partial_payments.amount, partial_payments.payment_date, partial_payments.is_paid, partial_payments.payment_method, invoices.unique_id, invoices.invoice_number, invoices.client_id, invoices.date_entered, partial_payments.due_date, clients.company,
                                         CONCAT(first_name, ' ', last_name) as client_name, invoices.id, transaction_fee, currency_id,
                                         IF(" . $this->db->dbprefix('partial_payments') . ".is_paid, '" . __('global:paid') . "', '" . __('global:unpaid') . "') as formatted_is_paid", false)
                        ->join('invoices', 'invoices.unique_id = partial_payments.unique_invoice_id')
                        ->join('clients', 'clients.id = invoices.client_id')
                        ->get($this->table)->result_array();
        $return = array();

        $allowed_invoices = get_assigned_ids('invoices', 'read');

        foreach ($results as $buffer) {
            if (isset($allowed_invoices[$buffer['id']])) {
                $buffer['money_amount'] = ($buffer['is_percentage']) ? ( ($buffer['amount'] / 100) * $this->getInvoiceTotalAmount($buffer['unique_id']) ) : $buffer['amount'];
                $buffer['billable_amount'] = $buffer['money_amount'];
                $return[] = $buffer;
            }
        }
        return $return;
    }

    /**
     * Gets the payment totals, converted into the default currency.
     *
     * $is_paid can be true/false/OVERDUE/SENT_BUT_UNPAID.
     *
     * OVERDUE gets the overdue totals.
     * SENT_BUT_UNPAID gets the totals of invoices sent but not yet paid.
     *
     * @param type $client_id
     * @param boolean $is_paid
     * @return type
     */
    function getTotals($client_id = null, $is_paid = false, $since = null, $is_viewable = null) {
        $CI = &get_instance();
        $CI->load->model('invoices/invoice_m');
        if ($is_paid === 'OVERDUE') {
            $type = 'OVERDUE';
            $is_paid = false;
        } elseif ($is_paid === 'SENT_BUT_UNPAID') {
            $type = 'SENT_BUT_UNPAID';
            $is_paid = false;
        } else {
            $type = '';
        }

        $invoice_totals = array();
        $partial_payments = $this->getAllPartialPayments($client_id, $is_paid, $since, $is_viewable);
        $total = 0;
        $count = $CI->invoice_m->count($client_id, $is_paid);

        $ignored_invoice_ids = array();

        # Let's find out what the totals of the invoices are.
        foreach ($partial_payments as $payment) {

            if ($type == 'OVERDUE' and ( $payment['is_paid'] or ! $payment['last_sent'] OR $payment['due_date'] > time())) {
                if (!in_array($payment['unique_invoice_id'], $ignored_invoice_ids)) {
                    $count--;
                    $ignored_invoice_ids[] = $payment['unique_invoice_id'];
                }
                continue;
            }

            if ($type === 'SENT_BUT_UNPAID' and $payment['last_sent'] == 0) {
                if (!in_array($payment['unique_invoice_id'], $ignored_invoice_ids)) {
                    $count--;
                    $ignored_invoice_ids[] = $payment['unique_invoice_id'];
                }
                continue;
            }

            if (!isset($invoice_totals[$payment['unique_invoice_id']])) {
                $invoice_totals[$payment['unique_invoice_id']] = $this->getInvoiceTotalAmount($payment['unique_invoice_id']);
            }

            $moneyAmount = ($payment['is_percentage']) ? ( ($payment['amount'] / 100) * $invoice_totals[$payment['unique_invoice_id']] ) : $payment['amount'];
            
            # If the exchange rate is set to 0, don't convert; leave the amount alone.
            $moneyAmount = $payment['exchange_rate'] > 0 ? $moneyAmount / $payment['exchange_rate'] : $moneyAmount;
            
            $total = $total + $moneyAmount;
        }

        return array('count' => $count, 'total' => round($total, 2));
    }

    /**
     * Gets the amount in USD of a partial payment.
     *
     * Used in certain payment methods, such as Authorize.net.
     *
     * @param float $amount
     * @param string $unique_id
     * @return float
     */
    public function getUsdAmountByAmountAndUniqueId($amount, $unique_id) {
        $buffer = $this->db->select('currency_id, exchange_rate')->join('invoices', 'partial_payments.unique_invoice_id = invoices.unique_id')->get_where('partial_payments', array('partial_payments.unique_id' => $unique_id))->row_array();

        if (isset($buffer['exchange_rate']) and $buffer['exchange_rate'] != 0) {
            if ($buffer['currency_id'] == 0) {
                $buffer['code'] = Currency::code();
            } else {
                $code = Currency::code($buffer['currency_id']);

                if (isset($code['code'])) {
                    $buffer['code'] = $code;
                } else {
                    $buffer['code'] = Currency::code();
                }
            }

            # Okay, so, the current currency is $buffer['code']. If that's USD, we can return right now.
            if ($buffer['code'] == 'USD') {
                return $amount;
            } else {
                # Let's see if the default currency is USD. If so, then we can convert to USD by dividing by the exchange rate.
                if (Currency::code() == 'USD') {
                    return $amount / $buffer['exchange_rate'];
                } else {
                    # Since the invoice isn't in USD and Pancake's default currency isn't USD, let's see if there's USD in the DB.
                    $currency = $this->db->select('rate')->where('code', 'USD')->get('currencies')->row_array();
                    if (isset($currency['rate'])) {
                        # It's in the DB, so we need to divide to the default rate, then multiply by the DB rate.
                        $amount = $amount / $buffer['exchange_rate'];
                        $amount = $amount * $currency['rate'];
                        return $amount;
                    } else {
                        # Okay, the invoice isn't in USD, Pancake's default currency isn't USD, there's no USD value in the DB.
                        # So let's go ask the ECB.

                        return Currency::convert($amount, $buffer['code'], "USD");
                    }
                }
            }
        } else {
            return 0;
        }
    }

    function get_balance_payments_total($client_id, $date = null) {
        
        if ($date === null) {
            $date = time();
        }
        
        $result = $this->db
                ->select("partial_payments.amount, partial_payments.is_percentage, partial_payments.unique_invoice_id, code as currency_code", false)
                ->where("payment_method", "credit-balance")
                ->where("partial_payments.is_paid", 1)
                ->where("client_id", $client_id)
                ->where("partial_payments.payment_date <=", $date)
                ->join("invoices", "invoices.unique_id = partial_payments.unique_invoice_id")
                ->join('currencies', 'invoices.currency_id = currencies.id', 'left')
                ->get("partial_payments")
                ->result_array();
        $ppm = $this;
        return array_reduce($result, function($carry, $value) use ($ppm) {
            $value['billableAmount'] = ($value['is_percentage']) ? ( ($value['amount'] / 100) * $ppm->getInvoiceTotalAmount($value['unique_invoice_id']) ) : $value['amount'];
            return $carry + Currency::convert($value['billableAmount'], $value['currency_code']);
        });
    }

    public function getClientHealth($client_id) {
        $unpaidTotals = $this->getTotals($client_id);
        $unpaidTotals = $unpaidTotals['total'];
        $paidTotals = $this->getTotals($client_id, true);
        $paidTotals = $paidTotals['total'];
        $overdueTotals = $this->getTotals($client_id, 'OVERDUE');
        $overdueTotals = $overdueTotals['total'];

        # 100 = all paid, no unpaid, no overdue.
        # 50 = 50% unpaid, 50% paid
        # 100 = 100 - unp

        $invoice_total = $unpaidTotals + $paidTotals;
        $unpaid_without_overdue = $unpaidTotals - $overdueTotals;
        $health = array();
        if ($invoice_total > 0) {
            $health['overdue'] = round(($overdueTotals / $invoice_total) * 100, 2);
            $health['paid'] = round(($paidTotals / $invoice_total) * 100, 2);
            $health['unpaid'] = round(($unpaidTotals / $invoice_total) * 100, 2);
            $health['overall'] = 100 - $health['unpaid'];
        } else {
            $health = array('overdue' => 0, 'paid' => 100, 'unpaid' => 0, 'overall' => 100);
        }
        return $health;
    }

    function getAllPartialPayments($client_id = null, $is_paid = false, $since = null, $is_viewable = null) {
        static $cache = null;
        if ($cache === null) {
            where_assigned('estimates_plus_invoices', 'read');
            $accounting_date = "date_entered";

            # Don't use WHERE here; this is cached for repeated use.
            # Instead, filter the rows you want inside the foreach().

            $this->db->select('partial_payments.*, invoices.last_sent, invoices.is_viewable, invoices.client_id, invoices.exchange_rate, ' . $accounting_date . ' as accounting_date', false);
            $this->db->join('invoices', 'partial_payments.unique_invoice_id = invoices.unique_id');
            $cache = $this->db->get($this->table)->result_array();
        }

        $return = array();
        foreach ($cache as $row) {
            if ($client_id !== null and $row['client_id'] != $client_id) {
                continue;
            }

            if ($is_paid and ! $row['is_paid']) {
                continue;
            }

            if (!$is_paid and $row['is_paid']) {
                continue;
            }

            if ($since !== null and $row['accounting_date'] <= $since) {
                continue;
            }

            if ($is_viewable !== null and $is_viewable != $row['is_viewable']) {
                continue;
            }

            $return[] = $row;
        }

        return $return;
    }

    function getPartialPaymentDetails($key, $unique_invoice_id, $create_if_not_exists = false) {
        where_assigned('invoices', 'read');
        $buffer = $this->db->select('partial_payments.*, currency_id')->join('invoices', 'partial_payments.unique_invoice_id = invoices.unique_id')->get_where($this->table, array('unique_invoice_id' => $unique_invoice_id, 'key' => $key))->row_array();
        if (!isset($buffer['unique_id']) and $create_if_not_exists) {
            # This part does not exist, so let's create it, for editing.
            $this->setPartialPayment($unique_invoice_id, $key, 0, 1, 0, '', true);
            return $this->getPartialPaymentDetails($key, $unique_invoice_id, false);
        }

        if (!isset($buffer['unique_id'])) {
            return array();
        }

        return array(
            'unique_id' => $buffer['unique_id'],
            'gateway' => $buffer['payment_method'],
            'date' => ($buffer['payment_date'] == '0') ? '' : format_date($buffer['payment_date']),
            'tid' => $buffer['txn_id'],
            'fee' => $buffer['transaction_fee'],
            'status' => $buffer['payment_status'],
            'amount' => $buffer['amount'],
            'is_percentage' => $buffer['is_percentage'],
            'currency' => Currency::symbol(Currency::code($buffer['currency_id']))
        );
    }

    function get_paid_total_this_fiscal_year() {
        $payments = $this->get_for_report(Settings::fiscal_year_start(), null, null, false);
        $total = 0;
        foreach ($payments as $payment) {
            $total += $payment['total_without_tax'];
        }
        return $total;
    }

    function get_for_report($from = null, $to = null, $client_id = null, $include_tax_data = true) {
        $config = array(
            "from_payment_date" => $from,
            "to_payment_date" => $to,
            "client_id" => $client_id,
            "paid" => true
        );

        $return = array();
        $invoice_ids = array();
        $i = 0;
        $this->db->order_by("partial_payments.payment_date", "asc");
        $this->db->where("partial_payments.payment_method !=", "credit-balance");
        foreach ($this->flexible_get_all($config) as $row) {
            if (!isset($invoice_ids[$row['unique_id']])) {
                $invoice_ids[$row['unique_id']] = array();
            }

            $invoice_total = $this->getInvoiceTotalAmount($row['unique_id']);
            if ($invoice_total != 0) {
                $payment_percentage = $row["billable_amount"] / $invoice_total;
            } else {
                $payment_percentage = 100;
            }
            $invoice_ids[$row['unique_id']][] = $i;

            $return[$i] = array(
                "invoice_number" => $row['invoice_number'],
                "client" => client_name($row['client_id']),
                "payment_date" => format_date($row['payment_date']),
                "payment_method" => $row['payment_method'],
                "txn_id" => $row['txn_id'],
                # Total Without Tax == Paid Amount
                "total_without_tax" => $row["billable_amount"],
                "transaction_fee" => $row['transaction_fee'],
                "currency_id" => $row['currency_id'],
                "unique_id" => $row['unique_id'],
                "taxes" => array(),
                "percentage" => $payment_percentage,
                "client_id" => $row['client_id'],
            );

            $i++;
        }

        if (count($invoice_ids) > 0 and $include_tax_data) {
            $CI = get_instance();
            $CI->load->model('invoices/invoice_m');
            $invoices = $CI->invoice_m->flexible_get_all(array('unique_id' => array_keys($invoice_ids), 'include_totals' => true, 'return_object' => false));
            foreach ($invoices as $invoice) {
                foreach ($invoice['taxes'] as $tax_id => $amount) {
                    foreach ($invoice_ids[$invoice['unique_id']] as $key) {
                        if (!isset($return[$key]["taxes"][$tax_id])) {
                            $return[$key]["taxes"][$tax_id] = $return[$key]["percentage"] * $amount;
                        }
                    }
                }
            }
        }

        return $return;
    }

    function getPartialPayment($unique_id) {
        $buffer = $this->db->get_where($this->table, array('unique_id' => $unique_id))->row_array();
        $CI = &get_instance();
        $CI->load->model('invoices/invoice_m');
        if (isset($buffer['unique_invoice_id'])) {
            $buffer['invoice'] = $CI->invoice_m->get($buffer['unique_invoice_id']);
            $total = isset($buffer['invoice']['total']) ? $buffer['invoice']['total'] : $buffer['invoice']['amount'];
            $buffer['billableAmount'] = ($buffer['is_percentage']) ? ( ($buffer['amount'] / 100) * $total ) : $buffer['amount'];
            $buffer['payment_url'] = site_url('transaction/process/' . $buffer['unique_id']);
        }
        return $buffer;
    }

    function updatePartialPayment($unique_id, $data) {
        return $this->db->where('unique_id', $unique_id)->update($this->table, $data);
    }

    /**
     * Generates the unique id for a partial payment
     *
     * @access	public
     * @return	string
     */
    public function _generate_unique_id() {

        static $unique_ids = null;

        if ($unique_ids === null) {
            $buffer = $this->db->select('unique_id')->get($this->table)->result_array();
            $unique_ids = array();

            foreach ($buffer as $row) {
                $unique_ids[$row['unique_id']] = $row['unique_id'];
            }
        }

        $this->load->helper('string');

        $valid = false;
        while ($valid === false) {
            $unique_id = random_string('alnum', 8);
            if (!isset($unique_ids[$unique_id])) {
                $valid = true;

                # Add this unique ID to list of IDs, because it'll be created.
                $unique_ids[$unique_id] = $unique_id;
            }
        }

        return $unique_id;
    }

    public function removePartialPayments($unique_invoice_id) {
        return $this->db->where('unique_invoice_id', $unique_invoice_id)->delete($this->table);
    }

    function addPayment($unique_invoice_id, $amount, $date, $gateway, $txn_id, $fee, $send_notification_email) {
        $original_amount = $amount;

        if ($amount == 0 or empty($gateway)) {
            return true;
        }

        if ($gateway == "credit-balance") {
            $CI = get_instance();
            $CI->load->model("clients/clients_m");
            $CI->load->model("invoices/invoice_m");
            $credit_balance_amount = $CI->clients_m->get_balance($CI->invoice_m->getClientIdByUniqueId($unique_invoice_id));
            if ($amount > $credit_balance_amount) {
                # Tried to set a payment larger than the amount available, so, instead of doing that, add as much as possible via addPayment().
                return $this->addPayment($unique_invoice_id, round($credit_balance_amount, 2), $date, $gateway, $txn_id, $fee, $send_notification_email);
            }
        }

        if (empty($date)) {
            $date = time();
        }

        $parts = $this->getInvoicePartialPayments($unique_invoice_id, $this->getInvoiceTotalAmount($unique_invoice_id));
        $invoice_total = 0;
        $unpaid_total = 0;
        $paid_parts = array();
        $unpaid_parts = array();
        foreach ($parts as $part) {
            $invoice_total = $invoice_total + $part['billableAmount'];
            if (!$part['is_paid']) {
                $unpaid_total = $unpaid_total + $part['billableAmount'];
                $unpaid_parts[] = $part;
            } else {
                $paid_parts[] = $part;
            }
        }

        if ($amount > $invoice_total) {
            # Mark all payments as paid.
            foreach ($parts as $part) {
                $this->setPartialPaymentDetails($unique_invoice_id, $part['key'], $date, $gateway, 'Completed', $txn_id, $fee);
            }
        } else {
            if ($amount > $unpaid_total) {
                # Mark all payments as paid.
                foreach ($parts as $part) {
                    $this->setPartialPaymentDetails($unique_invoice_id, $part['key'], $date, $gateway, 'Completed', $txn_id, $fee);
                }
            } else {
                # Re-structure payment plan.
                # 1. Remove all parts.
                $this->removePartialPayments($unique_invoice_id);
                # 2. Add paid parts.
                $key = 1;
                foreach ($paid_parts as $part) {
                    # setPartialPayment($unique_invoice_id, $key, $amount, $is_percentage = 0, $due_date = 0, $notes = '', $force = false)
                    $this->setPartialPayment($unique_invoice_id, $key, $part['amount'], $part['is_percentage'], $part['due_date'], $part['notes']);
                    $this->setPartialPaymentDetails($unique_invoice_id, $key, $part['payment_date'], $part['payment_method'], $part['payment_status'], $part['txn_id'], $part['transaction_fee']);
                    $key++;
                }
                # 3. Mark unpaid parts as paid, by order.
                $carried_forward = 0;
                $i = 0;

                foreach ($unpaid_parts as $part) {
                    $part_amount = $part['billableAmount'];

                    if ($amount == 0) {
                        # We've already added all the money to the payment plan, let's just re-add these parts without changing them.
                        $this->setPartialPayment($unique_invoice_id, $key, $part['amount'], $part['is_percentage'], $part['due_date'], $part['notes']);
                        $this->setPartialPaymentDetails($unique_invoice_id, $key, $part['payment_date'], $part['payment_method'], $part['payment_status'], $part['txn_id'], $part['transaction_fee']);
                        $key++;
                    } elseif (round($amount, 2) < round($part_amount, 2)) {
                        # Split this amount in two. $amount is paid, $part_amount - $amount is not.
                        # We add the carried forward, because, imagine that we were paying 300, and there were two payments for 250.
                        # The first time, Pancake would've carried forward 250, and reduced the amount to 50.
                        # The second time, it would have gotten here, and instead of splitting the 250 to 50 and 200,
                        # I want it to split the 250 to 300 AND 200.
                        $new_amount = $amount + $carried_forward;

                        $this->setPartialPayment($unique_invoice_id, $key, $new_amount, 0);
                        $this->setPartialPaymentDetails($unique_invoice_id, $key, $date, $gateway, 'Completed', $txn_id, $fee);
                        $key++;
                        $this->setPartialPayment($unique_invoice_id, $key, ($part_amount - $amount), 0, $part['due_date'], $part['notes']);
                        $this->setPartialPaymentDetails($unique_invoice_id, $key, $part['payment_date'], $part['payment_method'], $part['payment_status'], $part['txn_id'], $part['transaction_fee']);
                        $key++;
                        $fee = 0;
                        $amount = 0;
                    } elseif (round($amount, 2) == round($part_amount, 2)) {
                        # Mark this part as paid.
                        $new_amount = $amount + $carried_forward;

                        $this->setPartialPayment($unique_invoice_id, $key, $new_amount, 0, $part['due_date'], $part['notes']);
                        $this->setPartialPaymentDetails($unique_invoice_id, $key, $date, $gateway, 'Completed', $txn_id, $fee);
                        $key++;
                        $fee = 0;
                        $amount = 0;
                    } else {
                        # Ignore this part, add to the next part.
                        $carried_forward = $part_amount;
                        $amount = $amount - $part_amount;
                    }

                    $i++;
                }
            }
        }

        if ($send_notification_email) {
            $date = (!is_numeric($date)) ? strtotime($date) : $date;

            # Let's notify the client about this.
            $data = array(
                'txn_id' => $txn_id,
                'payment_gross' => $original_amount,
                'transaction_fee' => $fee,
                'gateway_surcharge' => 0,
                'payment_date' => $date,
                'payment_type' => 'instant',
                'payer_status' => 'verified',
                'payment_status' => 'Completed',
                'is_paid' => 1,
            );

            $part = reset($this->getInvoicePartialPayments($unique_invoice_id));
            $this->invoice_m->send_payment_receipt_emails($part['unique_id'], $gateway, $data);
        }
    }

    /**
     * Creates (or, if it already exists, updates) a partial payment for an invoice.
     *
     * $key is the index of the payment part, starting from 1.
     * $due_date is a UNIX timestamp.
     * The rest should be self-explanatory.
     *
     * Returns an integer (the ID of the payment part) if it is created successfully,
     * true if it is already existed and was updated,
     * and false if anything went wrong.
     *
     * @param string $unique_invoice_id
     * @param integer $key
     * @param double $amount
     * @param boolean $is_percentage
     * @param integer $due_date
     * @param string $notes
     * @param boolean $is_paid
     * @return integer|boolean
     */
    function setPartialPayment($unique_invoice_id, $key, $amount, $is_percentage = 0, $due_date = 0, $notes = '', $force = false) {

        $where = array(
            'unique_invoice_id' => $unique_invoice_id,
            'key' => $key
        );

        $exists = $this->db->select('unique_id')->where($where)->get($this->table)->row_array();
        $unique_id = (isset($exists['unique_id']) and ! empty($exists['unique_id'])) ? $exists['unique_id'] : $this->_generate_unique_id();
        $exists = (isset($exists['unique_id']) and ! empty($exists['unique_id']));

        $data = array(
            'unique_invoice_id' => $unique_invoice_id,
            'amount' => $amount,
            'key' => $key,
            'is_percentage' => $is_percentage,
            'due_date' => $due_date,
            'notes' => $notes,
            'unique_id' => $unique_id,
            'improved' => 1
        );

        if (!$exists) {

            # Fixes a MySQL Strict Error.
            $data = array_merge($data, array(
                'gateway_surcharge' => 0,
                'payment_gross' => 0,
                'item_name' => '',
                'is_paid' => 0,
                'payment_date' => 0,
                'payment_type' => '',
                'payer_status' => '',
                'payment_status' => '',
                'payment_method' => '',
                'transaction_fee' => 0,
            ));

            if ($amount > 0 or $force) {
                return $this->db->insert($this->table, $data);
            } else {
                # The amount is empty, this partial payment can go to hell.
                return true;
            }
        } else {
            if ($amount > 0 or $force) {
                return $this->db->where($where)->update($this->table, $data);
            } else {
                # The amount is empty, let's delete this partial payment.
                return $this->db->where($where)->delete($this->table);
            }
        }
    }

    function setPartialPaymentDetails($invoice_unique_id, $key, $payment_date, $gateway, $status, $txn_id, $fee = 0, $send_notification_email = false) {
        $fee = (float) $fee;

        $part = $this->getPartialPaymentDetails($key, $invoice_unique_id);
        $is_paid = (!empty($status) or ! empty($gateway));

        if ($is_paid) {
            # Date's gotta be bigger than 0.
            # If it is NOT a UNIX Timestamp, turn it into one.
            # Obvious, huh?

            $date = (!is_numeric($payment_date)) ? strtotime($payment_date) : $payment_date;
            $date = ($date == 0) ? time() : $date;
        } else {
            # Date's gotta be 0, because no payment was made yet.
            $date = 0;
        }

        $payment_status = (!empty($gateway) and empty($status)) ? 'Completed' : $status;

        $data = array(
            'is_paid' => $is_paid,
            'payment_status' => $payment_status,
            'payment_method' => $gateway,
            'payment_date' => $date,
            'txn_id' => $txn_id,
            'transaction_fee' => $fee
        );

        # We calculate the money amount. If it's a percentage, we calculate that against the invoice total.
        $moneyAmount = $part['is_percentage'] ? ( ($part['amount'] / 100) * $this->getInvoiceTotalAmount($invoice_unique_id) ) : $part['amount'];

        if ($gateway == "credit-balance") {
            $CI = get_instance();
            $CI->load->model("clients/clients_m");
            $CI->load->model("invoices/invoice_m");
            $amount = $CI->clients_m->get_balance($CI->invoice_m->getClientIdByUniqueId($invoice_unique_id));
            if ($moneyAmount > $amount) {
                # Tried to set a payment larger than the amount available, so, instead of doing that, add as much as possible via addPayment().
                $this->addPayment($invoice_unique_id, round($amount, 2), $date, $gateway, $txn_id, $fee, $send_notification_email);
                return;
            }
        }

        $this->updatePartialPayment($part['unique_id'], $data);

        # Clear cache after setting a payment and before fixing an invoice,
        # otherwise it won't take into account what's changed.
        $this->getInvoicePartialPayments_cache = null;
        $this->invoice_m->fixInvoiceRecord($invoice_unique_id);

        if ($send_notification_email and $is_paid) {
            # Let's notify the client about this.

            $data = array(
                'txn_id' => $txn_id,
                'payment_gross' => round($moneyAmount, 2),
                'transaction_fee' => $fee,
                'gateway_surcharge' => 0,
                'payment_date' => $date,
                'payment_type' => 'instant',
                'payer_status' => 'verified',
                'payment_status' => $payment_status,
                'is_paid' => 1,
            );

            $this->invoice_m->send_payment_receipt_emails($part['unique_id'], $gateway, $data);
        }
    }

    /**
     * This function resets the keys of the partial payments to avoid problems when parts are deleted.
     * @param string $unique_invoice_id
     */
    function organiseInvoicePartialPayments($unique_invoice_id) {
        $parts = $this->db->order_by('key', 'ASC')->get_where($this->table, array('unique_invoice_id' => $unique_invoice_id))->result_array();
        $i = 1;
        $remaining = count($parts);
        while ($remaining >= $i) {
            $this->db->where('id', $parts[$i - 1]['id'])->update($this->table, array('key' => $i));
            $i++;
        }
    }

    function deleteInvoicePartialPayments($unique_invoice_id, $exceptFirst = false) {
        if ($exceptFirst) {
            $this->db->where('key !=', 1);
        }
        return $this->db->where('unique_invoice_id', $unique_invoice_id)->delete($this->table);
    }

    function validate_partials($invoice_total_with_tax, $invoice_is_recurring, $partial_amounts, $partial_is_percentages, $invoice_unique_id = null) {
        $total_amount = 0;

        if (!is_array($partial_amounts)) {
            $partial_amounts = array();
        }

        if (count($partial_amounts) == 0) {
            # No partial payments needed validation.
            return true;
        }

        if ($invoice_unique_id !== null) {
            $CI = get_instance();
            $CI->load->model('invoices/invoice_m');
            $invoice = $this->invoice_m->get($invoice_unique_id);
            $is_partially_paid = $invoice['paid_amount'] != 0;
            $is_sent = $invoice['last_sent'] != 0;
        }

        foreach ($partial_amounts as $key => $amount) {

            if ($invoice_unique_id !== null) {
                if ($invoice_is_recurring and ! $is_partially_paid and ! $is_sent) {
                    # Don't use limitation if invoice is already partially paid or sent to the client.

                    if ($key > 1) {
                        # Only one part allowed, ignore all the rest!
                        break;
                    } else {
                        # First part, let's make sure the amount/percentage is correct.
                        # We could just change (sanitize) it, but that might be unexpected for the user.
                        if ($amount != 100 or $partial_is_percentages[$key] != 1) {
                            if ($amount == $invoice_total_with_tax and $partial_is_percentages[$key] == 0) {
                                # It's still okay, it equals 100% of the invoice.
                            } else {
                                return false;
                            }
                        }
                    }
                }
            }

            $is_percentage = ($partial_is_percentages[$key] == "1") ? true : false;

            # We calculate the money amount. If it's a percentage, we calculate that against the invoice total.
            $moneyAmount = $is_percentage ? ( ($amount / 100) * $invoice_total_with_tax ) : $amount;
            $total_amount = $total_amount + $moneyAmount;

            # We calculate if it's bigger than the invoice total, and we give it the ability to be off by 1 cent, because of rounding.
            if ($total_amount > ($invoice_total_with_tax + 0.01)) {
                # Okay, it's wrong. Let's quit.
                return false;
            }
        }

        $total_amount = (string) $total_amount;
        $invoice_total_with_tax = (string) $invoice_total_with_tax;

        # We calculate if it's bigger than the invoice total, and we give it the ability to be off by 1 cent, because of rounding.
        if ($total_amount < ($invoice_total_with_tax - 0.01)) {
            # It's wrong, too. Let's quit.
            return false;
        }

        return true;
    }

    /**
     * Processes POST input from the invoice form.
     *
     * Returns 'WRONG_TOTAL' if the total does not match 100% of the invoice's costs.
     * Returns true if everything went well.
     * Returns false if it failed at creating/updating one of the partial payments.
     *
     * In the case anything goes wrong, all partial payments are removed, to prevent orphans.
     *
     * @param string $unique_invoice_id
     * @param double $invoice_total
     * @param array $partial_amounts
     * @param array $partial_is_percentages
     * @param array $partial_due_dates
     * @param array $partial_notes
     * @param array $partial_is_paids
     */
    function processInput($unique_invoice_id, $partial_amounts, $partial_is_percentages, $partial_due_dates, $partial_notes, $partial_is_paids, $invoice_total_with_tax) {

        # Let's organise the data properly. I hate dealing with one array for each field.
        # We also process it for insertion in the DB.
        # And we also check that the amounts match 100%.
        # Oh, and we insert it in the DB, too.

        $invoice_total = $invoice_total_with_tax;

        $partials = array();
        $invoice_is_recurring = $this->invoice_m->getIsRecurringByUniqueId($unique_invoice_id);

        if ($unique_invoice_id !== null) {
            $CI = get_instance();
            $CI->load->model('invoices/invoice_m');
            $invoice = $this->invoice_m->get($unique_invoice_id);
            $is_partially_paid = $invoice['paid_amount'] != 0;
            $is_sent = $invoice['last_sent'] != 0;
        }

        $total_amount = 0;

        foreach ($partial_amounts as $key => $amount) {

            if ($unique_invoice_id !== null) {
                if ($invoice_is_recurring and ! $is_partially_paid and ! $is_sent) {
                    if ($key > 1) {
                        # Only one part allowed, ignore all the rest!
                        break;
                    } else {
                        # First part, let's make sure the amount/percentage is correct.
                        # We could just change (sanitize) it, but that might be unexpected for the user.
                        if ($amount != 100 or $partial_is_percentages[$key] != 1) {
                            if ($amount == $invoice_total and $partial_is_percentages[$key] == 0) {
                                # It's still okay, it equals 100% of the invoice.
                            } else {
                                return 'WRONG_TOTAL';
                            }
                        }
                    }
                }
            }

            $partials[$key] = array(
                'amount' => $amount,
                'is_percentage' => ($partial_is_percentages[$key] == "1") ? true : false,
                'due_date' => read_date_picker($partial_due_dates[$key]),
                'notes' => $partial_notes[$key]
            );

            # We calculate the money amount. If it's a percentage, we calculate that against the invoice total.
            $moneyAmount = ($partials[$key]['is_percentage']) ? ( ($amount / 100) * $invoice_total ) : $amount;
            $total_amount = $total_amount + $moneyAmount;

            # We calculate if it's bigger than the invoice total, and we give it the ability to be off by 1 cent, because of rounding.
            if ($total_amount > ($invoice_total + 0.01)) {
                # Okay, it's wrong. Let's quit.
                return 'WRONG_TOTAL';
            }
        }

        $total_amount = (string) $total_amount;
        $invoice_total = (string) $invoice_total;

        # We calculate if it's bigger than the invoice total, and we give it the ability to be off by 1 cent, because of rounding.
        if ($total_amount < ($invoice_total - 0.01)) {
            # It's wrong, too. Let's quit.
            return 'WRONG_TOTAL';
        }

        $current_partials = $this->getInvoicePartialPayments($unique_invoice_id);
        $this->getInvoicePartialPayments_cache = null;

        # Everything's processed, and the total is correct. Let's go and put it all in the DB.
        foreach ($partials as $key => $partial) {
            if (!$this->setPartialPayment($unique_invoice_id, $key, $partial['amount'], $partial['is_percentage'], $partial['due_date'], $partial['notes'])) {
                # Something wrong happened, so let's just stop this.
                $this->deleteInvoicePartialPayments($unique_invoice_id);
                return false;
            } else {
                if (isset($current_partials[$key])) {
                    unset($current_partials[$key]);
                }
            }
        }

        if (count($current_partials) > 0) {
            # These parts got deleted when editing, we need to delete them.
            foreach ($current_partials as $key => $part) {
                $this->setPartialPayment($unique_invoice_id, $key, 0, $part['is_percentage'], $part['due_date']);
            }
        }

        if ($invoice_is_recurring and ! $is_partially_paid and ! $is_sent) {
            # We also need to delete the rest of the parts, only part one is allowed to stay!
            $this->deleteInvoicePartialPayments($unique_invoice_id, true);
        }

        $this->organiseInvoicePartialPayments($unique_invoice_id);

        # Everything worked perfectly! Jolly good show, old chap.
        return true;
    }

    function getInvoicePaidAmount($unique_invoice_id) {
        $total_amount = 0;
        $invoice_total = $this->getInvoiceTotalAmount($unique_invoice_id);

        foreach ($this->getInvoicePartialPayments($unique_invoice_id) as $row) {
            if ($row['is_paid']) {
                # We calculate the money amount. If it's a percentage, we calculate that against the invoice total.
                $moneyAmount = (($row['is_percentage']) ? ( ($row['amount'] / 100) * $invoice_total ) : $row['amount']);
                $total_amount = $total_amount + $moneyAmount;
            }
        }

        return $total_amount;
    }

    function getInvoiceTotalAmount($unique_invoice_id) {
        $this->cache_totals();
        if (isset($this->totals_cache[$unique_invoice_id])) {
            return $this->totals_cache[$unique_invoice_id];
        }
        $CI = &get_instance();
        $CI->load->model('invoices/invoice_m');
        $invoice = $CI->invoice_m->flexible_get_all(array('unique_id' => $unique_invoice_id, 'include_items' => true, 'include_totals' => false, 'return_object' => false, 'get_single' => true, 'include_partials' => false));
        return $invoice['billable_amount'];
    }

    function getIdByUniqueId($unique_id) {
        $buffer = $this->select('id')->where('unique_id', $unique_id)->get($this->table);
        return (isset($buffer['id']) ? $buffer['id'] : false);
    }

    function getUniqueInvoiceIdByUniqueId($unique_id) {
        $buffer = $this->db->select('unique_invoice_id')->where('unique_id', $unique_id)->get($this->table)->row_array();
        return (isset($buffer['unique_invoice_id']) ? $buffer['unique_invoice_id'] : false);
    }

    function getInvoiceUnpaidAmount($unique_invoice_id) {
        $total_amount = 0;
        $invoice_total = $this->getInvoiceTotalAmount($unique_invoice_id);

        foreach ($this->getInvoicePartialPayments($unique_invoice_id) as $row) {
            if (!$row['is_paid']) {
                # We calculate the money amount. If it's a percentage, we calculate that against the invoice total.
                $moneyAmount = (($row['is_percentage']) ? ( ($row['amount'] / 100) * $invoice_total ) : $row['amount']);
                $total_amount = $total_amount + $moneyAmount;
            }
        }

        return $total_amount;
    }

    function getInvoiceIsPaid($unique_invoice_id) {
        foreach ($this->getInvoicePartialPayments($unique_invoice_id) as $row) {
            if (!$row['is_paid']) {
                return false;
            }
        }

        # All partial payments are paid, so the invoice is paid.
        return true;
    }

    /**
     * Get all parts of the payments for a given invoice.
     *
     * Appends a column to the results, called 'billableAmount',
     * which is the amount that the client is getting charged,
     * in cash. No percentages, no nothing. Tax included.
     *
     * It also appends payment_url, self-explanatory, and over_due, also self-explanatory.
     * It also appends due_date_input, which is the due date in a format suitable for
     * displaying in the create/edit invoice pages.
     *
     * @param string $unique_invoice_id
     * @param float $invoice_total
     * @return array
     */
    function getInvoicePartialPayments($unique_invoice_id, $invoice_total = 0, $ignore_cache = false) {
        if ($ignore_cache) {
            $this->getInvoicePartialPayments_cache = null;
        }

        if ($this->getInvoicePartialPayments_cache === null) {
            $buffer = $this->db->order_by($this->table . '.key', 'ASC')->get($this->table)->result_array();
            $this->getInvoicePartialPayments_cache = array();

            if (!$invoice_total) {
                $invoice_total = $this->getInvoiceTotalAmount($unique_invoice_id);
            }

            foreach ($buffer as $row) {
                $row['due_date_input'] = $row['due_date'] > 0 ? format_date($row['due_date']) : '';
                $row['billableAmount'] = ($row['is_percentage']) ? ( ($row['amount'] / 100) * $invoice_total ) : $row['amount'];
                $row['payment_url'] = site_url('transaction/process/' . $row['unique_id']);
                $row['over_due'] = $row['due_date'] < time();

                if (!isset($this->getInvoicePartialPayments_cache[$row['unique_invoice_id']])) {
                    $this->getInvoicePartialPayments_cache[$row['unique_invoice_id']] = array();
                }

                $this->getInvoicePartialPayments_cache[$row['unique_invoice_id']][$row['key']] = $row;
            }
        }

        return isset($this->getInvoicePartialPayments_cache[$unique_invoice_id]) ? $this->getInvoicePartialPayments_cache[$unique_invoice_id] : array();
    }

    /**
     * Gets the number of partial payments in an invoice.
     *
     * $count can be either "all", "unpaid" or "paid".
     *
     * @param string $unique_invoice_id
     * @param string $count
     * @return float
     */
    function get_counts_invoice_partial_payments($unique_invoice_id, $count) {
        static $cache = null;
        if ($cache === null) {
            $cache = array();
            $buffer = $this->db->select("unique_invoice_id, count(0) as 'all', sum(is_paid) as paid, sum(is_paid = 0) as unpaid", false)->group_by("unique_invoice_id")->get($this->table)->result_array();
            foreach ($buffer as $row) {
                $cache[$row['unique_invoice_id']] = $row;
            }
        }

        return isset($cache[$unique_invoice_id][$count]) ? $cache[$unique_invoice_id][$count] : 0;
    }

}
