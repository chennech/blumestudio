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
 * @since		Version 2.2
 */
// ------------------------------------------------------------------------

/**
 * The Reports Model
 *
 * @subpackage	Models
 * @category	Reports
 */
class Reports_m extends Pancake_Model {

    public $reports;
    public $cached_payments_per_method = array();

    function __construct() {
        parent::__construct();

        $this->reports = array(
            'unpaid_invoices' => __('invoices:unpaid'),
            'payments' => __('reports:payments'),
            'overdue_invoices' => __('invoices:overdue'),
            'invoices' => __('global:invoices'),
            'invoices_per_status_pie' => __('global:invoices'),
            'expenses' => __('expenses:expenses'),
            'payments_per_method' => __('reports:payments'),
        );
    }

    function getOverviews($from = 0, $to = 0, $client_id = NULL) {
        $return = array();
        foreach (array_keys($this->reports) as $report) {
            $return[$report] = $this->load->view('reports/overview', $this->get($report, $from, $to, $client_id), true);
        }
        return $return;
    }

    function generateReportString($from = 0, $to = 0, $client = 0) {
        if ($client == NULL) {
            $client = 0;
        }
        return "from:$from-to:$to-client:$client";
    }

    function processReportString($string) {
        $string = explode('-', $string);
        $data = array(
            'from' => str_ireplace('from:', '', $string[0]),
            'to' => str_ireplace('to:', '', $string[1]),
            'client' => str_ireplace('client:', '', $string[2]),
        );

        if ($data['from'] == 0) {
            $data['from'] = Settings::fiscal_year_start();
        }

        if ($data['to'] == 0) {
            # Get the end of the day.
            $data['to'] = mktime(23, 59, 59, date("n"), date("j"), date("Y"));
        }

        return $data;
    }

    function getDefaultFrom($from) {
        return ($from > 0 ? $from : Settings::fiscal_year_start());
    }

    function getDefaultTo($to) {
        return ($to > 0 ? $to : time());
    }

    function _process_due_date($input) {
        return format_date($input);
    }

    function _process_amount($input) {
        return Currency::format($input);
    }

    function _process_billable_amount($input) {
        return Currency::format($input);
    }

    function _process_money_amount($input) {
        return Currency::format($input);
    }

    function _process_unpaid_amount($input) {
        return Currency::format($input);
    }

    function _process_paid_amount($input) {
        return Currency::format($input);
    }

    function _process_tax_collected($input) {
        return Currency::format($input);
    }

    private function _expense_report($from, $to, $client_id, $is_full = true) {

        $fields = array(
            "name" => __("global:name"),
            "category" => __("expenses:category"),
            "supplier" => __("expenses:supplier"),
            "client" => __("global:client"),
            "project" => __('global:project'),
            "due_date" => __("projects:expense_date"),
            "is_billed" => __("global:is_billed"),
            "unbilled_amount" => __("global:unbilled_amount"),
            "billed_amount" => __("global:billed_amount"),
            "amount" => __("expenses:amount"),
        );

        $amount = 0;
        $unbilled_amount = 0;
        $billed_amount = 0;

        $this->load->model("projects/project_expense_m");
        $records = $this->project_expense_m->get_for_report($from, $to, $client_id);

        foreach ($records as $record) {
            $amount += Currency::convert($record['amount'], Currency::code($record['currency_id']), Settings::get('currency'));
            $unbilled_amount += Currency::convert($record['unbilled_amount'], Currency::code($record['currency_id']), Settings::get('currency'));
            $billed_amount += Currency::convert($record['billed_amount'], Currency::code($record['currency_id']), Settings::get('currency'));
        }

        $totals = array(
            "amount" => $amount,
            "unbilled_amount" => $unbilled_amount,
            "billed_amount" => $billed_amount,
        );

        $reportString = $this->generateReportString($from, $to, $client_id);

        $data = array(
            'title' => $this->reports["expenses"],
            'report' => "expenses",
            'from' => $from,
            'to' => $to,
            'formatted_from' => format_date($from),
            'formatted_to' => format_date($to),
            'report_url' => site_url("reports/expenses/view/$reportString"),
            'report_url_pdf' => site_url("reports/expenses/pdf/$reportString"),
            'report_url_csv' => site_url("reports/expenses/csv/$reportString"),
            'fields' => $fields,
            'records' => $records,
            'chart_totals' => array(),
        );

        if ($is_full) {
            $data['taxes'] = array();
            $data['totals'] = $totals;
        } else {

            $unformatted_total = reset($totals);
            foreach ($totals as $field => $amount) {
                $totals[$field] = Currency::format($amount);
            }

            $data["report_total"] = $unformatted_total;
            $data['field_totals'] = $totals;
            $data['verb'] = __("reports:verb_created");
            $data["formatted_total"] = reset($data['field_totals']);

            $clients = get_dropdown('clients', 'id', "client_name");

            $client_totals = array();
            $client_totals_fields = array_keys($data['field_totals']);
            foreach ($records as $row) {
                foreach ($client_totals_fields as $field) {
                    if (!isset($clients[$row['client_id']])) {
                        # Expense belongs to a project that no longer exists.
                        $client = __("global:na");
                    } else {
                        $client = $clients[$row['client_id']];
                    }

                    if (!isset($client_totals[$field])) {
                        $client_totals[$field] = array();
                    }

                    if (!isset($client_totals[$field][$client])) {
                        $client_totals[$field][$client] = 0;
                    }

                    $client_totals[$field][$client] += $row[$field];
                }
            }

            $data['client_totals'] = $client_totals;
            $data['chart_type'] = 'pie';
            $data['per'] = __('reports:perclient');
            $data['chart_totals'] = reset($client_totals);
        }

        return $data;
    }

    private function _payments_report($from, $to, $client_id, $is_full = true) {

        $fields = array(
            "invoice_number" => __("global:invoice"),
            "client" => __("global:client"),
            "payment_date" => __('partial:paymentdate'),
            "payment_method" => __('partial:paymentmethod'),
            "total_without_tax" => __("reports:amount_paid"),
            'collected_taxes' => '{tax}',
            "transaction_fee" => __("reports:fees_paid"),
        );

        $total_without_tax = 0;
        $transaction_fee = 0;
        $taxes = array();

        $this->load->model("invoices/invoice_m");
        $this->load->model("invoices/partial_payments_m");
        $records = $this->partial_payments_m->get_for_report($from, $to, $client_id, $is_full);

        $totals = array(
            "total_without_tax" => 0,
            "transaction_fee" => 0,
            "collected_taxes" => array(),
        );

        foreach ($records as $record) {
            $total_without_tax += Currency::convert($record['total_without_tax'], Currency::code($record['currency_id']), Settings::get('currency'));
            $transaction_fee += Currency::convert($record['transaction_fee'], Currency::code($record['currency_id']), Settings::get('currency'));

            foreach ($record['taxes'] as $tax_id => $collected) {
                if (!isset($totals['collected_taxes'][$tax_id])) {
                    $totals['collected_taxes'][$tax_id] = array(
                        'uncollected' => "n/a",
                        'collected' => 0,
                        'total' => "n/a"
                    );

                    $taxes[$tax_id] = Settings::tax($tax_id);
                    $taxes[$tax_id] = $taxes[$tax_id]['name'];
                }

                $totals['collected_taxes'][$tax_id]["collected"] += $collected;
            }
        }

        $totals["total_without_tax"] = $total_without_tax;
        $totals["transaction_fee"] = $transaction_fee;

        $reportString = $this->generateReportString($from, $to, $client_id);

        $data = array(
            'title' => $this->reports["payments"],
            'report' => "payments",
            'from' => $from,
            'to' => $to,
            'formatted_from' => format_date($from),
            'formatted_to' => format_date($to),
            'report_url' => site_url("reports/payments/view/$reportString"),
            'report_url_pdf' => site_url("reports/payments/pdf/$reportString"),
            'report_url_csv' => site_url("reports/payments/csv/$reportString"),
            'fields' => $fields,
            'records' => $records,
            'chart_totals' => array(),
        );

        if ($is_full) {
            $data['taxes'] = $taxes;
            $data['totals'] = $totals;
        } else {
            unset($totals['collected_taxes']);

            $unformatted_total = reset($totals);
            foreach ($totals as $field => $amount) {
                $totals[$field] = Currency::format($amount);
            }

            $data["report_total"] = $unformatted_total;
            $data['field_totals'] = $totals;
            $data['verb'] = __("reports:verb_paid");
            $data["formatted_total"] = reset($data['field_totals']);

            $clients = get_dropdown('clients', 'id', "client_name");

            $client_totals = array();
            $payment_method_totals = array();
            
            $client_totals_fields = array_keys($data['field_totals']);
            require_once APPPATH . 'modules/gateways/gateway.php';
            $gateways = Gateway::get_gateways();
            
            foreach ($records as $row) {
                
                $payment_method = isset($gateways[$row["payment_method"]]) ? $gateways[$row["payment_method"]]['title'] : __('global:na');;
                
                if (!isset($payment_method_totals[$payment_method])) {
                    $payment_method_totals[$payment_method] = 0;
                }
                
                $payment_method_totals[$payment_method] += Currency::convert($row['total_without_tax'], Currency::code($row['currency_id']), Settings::get('currency'));
                
                foreach ($client_totals_fields as $field) {
                    if (!isset($clients[$row['client_id']])) {
                        # Payment belongs to a project that no longer exists.
                        $client = __("global:na");
                    } else {
                        $client = $clients[$row['client_id']];
                    }

                    if (!isset($client_totals[$field])) {
                        $client_totals[$field] = array();
                    }

                    if (!isset($client_totals[$field][$client])) {
                        $client_totals[$field][$client] = 0;
                    }

                    $client_totals[$field][$client] += $row[$field];
                }
            }

            $data['client_totals'] = $client_totals;
            $data['chart_type'] = 'pie';
            $data['per'] = __('reports:perclient');
            $data['chart_totals'] = reset($client_totals);
            
            $this->cached_payments_per_method = array(
                'title' => __("reports:payments"),
                'report' => 'payments_per_method',
                'from' => $data['from'],
                'to' => $data['to'],
                'formatted_from' => $data['formatted_from'],
                'formatted_to' => $data['formatted_to'],
                'report_url' => site_url("reports/payments_per_method/view/$reportString"),
                'report_url_pdf' => site_url("reports/payments_per_method/pdf/$reportString"),
                'report_url_csv' => site_url("reports/payments_per_method/csv/$reportString"),
                'fields' => array(),
                'records' => array(),
                'chart_totals' => $payment_method_totals,
                'report_total' => $data['report_total'],
                'field_totals' => $data['field_totals'],
                'verb' => $data['verb'],
                'formatted_total' => $data['formatted_total'],
                'client_totals' => $data['client_totals'],
                'chart_type' => 'pie',
                'per' => __("reports:per_payment_method"),
            );
        }

        return $data;
    }

    private function _payments_per_method_report($from, $to, $client_id, $is_full = true) {
        if ($is_full) {
            $fields = array(
                "payment_method" => __('partial:paymentmethod'),
                "total_without_tax" => __("reports:amount_paid"),
                'collected_taxes' => '{tax}',
                "transaction_fee" => __("reports:fees_paid"),
            );

            $total_without_tax = 0;
            $transaction_fee = 0;
            $taxes = array();

            $this->load->model("invoices/invoice_m");
            $this->load->model("invoices/partial_payments_m");
            $records = $this->partial_payments_m->get_for_report($from, $to, $client_id, $is_full);

            $totals = array(
                "total_without_tax" => 0,
                "transaction_fee" => 0,
                "collected_taxes" => array(),
            );

            foreach ($records as $record) {
                $total_without_tax += Currency::convert($record['total_without_tax'], Currency::code($record['currency_id']), Settings::get('currency'));
                $transaction_fee += Currency::convert($record['transaction_fee'], Currency::code($record['currency_id']), Settings::get('currency'));

                foreach ($record['taxes'] as $tax_id => $collected) {
                    $collected = Currency::convert($collected, Currency::code($record['currency_id']), Settings::get('currency'));
                    if (!isset($totals['collected_taxes'][$tax_id])) {
                        $totals['collected_taxes'][$tax_id] = array(
                            'uncollected' => "n/a",
                            'collected' => 0,
                            'total' => "n/a"
                        );

                        $taxes[$tax_id] = Settings::tax($tax_id);
                        $taxes[$tax_id] = $taxes[$tax_id]['name'];
                    }

                    $totals['collected_taxes'][$tax_id]["collected"] += $collected;
                }
            }

            $totals["total_without_tax"] = $total_without_tax;
            $totals["transaction_fee"] = $transaction_fee;

            $new_records = array();
            foreach ($records as $record) {
                if (!isset($new_records[$record['payment_method']])) {
                    $new_records[$record['payment_method']] = array(
                        'payment_method' => $record['payment_method'],
                        'total_without_tax' => 0,
                        'collected_taxes' => array(),
                        'transaction_fee' => 0,
                        'currency_id' => Settings::get('currency'),
                    );
                }

                foreach ($record['taxes'] as $tax_id => $collected) {
                    $collected = Currency::convert($collected, Currency::code($record['currency_id']), Settings::get('currency'));

                    if (!isset($new_records[$record['payment_method']]['collected_taxes'][$tax_id])) {
                        $new_records[$record['payment_method']]['collected_taxes'][$tax_id] = 0;
                    }

                    $new_records[$record['payment_method']]['collected_taxes'][$tax_id] += $collected;
                }

                $total_without_tax = Currency::convert($record['total_without_tax'], Currency::code($record['currency_id']), Settings::get('currency'));
                $transaction_fee = Currency::convert($record['transaction_fee'], Currency::code($record['currency_id']), Settings::get('currency'));

                $new_records[$record['payment_method']]['taxes'] = $new_records[$record['payment_method']]['collected_taxes'];
                $new_records[$record['payment_method']]['total_without_tax'] += $total_without_tax;
                $new_records[$record['payment_method']]['transaction_fee'] += $transaction_fee;
            }

            $reportString = $this->generateReportString($from, $to, $client_id);

            $data = array(
                'title' => $this->reports["payments_per_method"],
                'report' => "payments_per_method",
                'from' => $from,
                'to' => $to,
                'formatted_from' => format_date($from),
                'formatted_to' => format_date($to),
                'report_url' => site_url("reports/payments_per_method/view/$reportString"),
                'report_url_pdf' => site_url("reports/payments_per_method/pdf/$reportString"),
                'report_url_csv' => site_url("reports/payments_per_method/csv/$reportString"),
                'fields' => $fields,
                'records' => $new_records,
                'chart_totals' => array(),
            );
            $data['taxes'] = $taxes;
            $data['totals'] = $totals;

            return $data;
        } else {
            return $this->cached_payments_per_method;
        }

        return $data;
    }

    function get_full($report, $from = 0, $to = 0, $client_id = NULL) {

        if ($report == 'expenses') {
            return $this->_expense_report($from, $to, $client_id);
        } elseif ($report == 'payments') {
            return $this->_payments_report($from, $to, $client_id);
        } elseif ($report == 'payments_per_method') {
            return $this->_payments_per_method_report($from, $to, $client_id);
        }

        $taxes = array();

        if ($client_id == 0) {
            $client_id = NULL;
        }

        $CI = &get_instance();
        $CI->load->model('invoices/invoice_m');
        $CI->load->model('invoices/partial_payments_m', 'ppm');

        $configs = array(
            'client_id' => $client_id,
            'from' => $from,
            'to' => $to,
            'include_totals' => true,
            'include_partials' => true,
            'return_object' => false
        );

        if ($report == 'unpaid_invoices') {
            $configs['paid'] = false;
        }

        $records = $CI->invoice_m->flexible_get_all($configs);

        $totals = array(
            'total_with_tax' => 0,
            'total_without_tax' => 0,
            'total_collected' => 0,
            'taxes' => array(),
            'fees' => 0
        );

        foreach ($records as $key => &$record) {

            switch ($report) {
                case 'overdue_invoices':
                    if (!$record['overdue']) {
                        unset($records[$key]);
                        continue 2;
                    }
                    break;
                case 'paid_invoices':
                    if ($record['paid_amount'] == 0) {
                        unset($records[$key]);
                        continue 2;
                    }
                    break;
                case 'tax_collected':
                    if ($record['tax_collected'] == 0) {
                        unset($records[$key]);
                        continue 2;
                    }
                    break;
                case 'unpaid_invoices':
                    if ($record['unpaid_amount'] == 0) {
                        unset($records[$key]);
                        continue 2;
                    }
                    break;
            }

            $exchange_rate = $record['exchange_rate'];

            $record = array(
                'unique_id' => $record['unique_id'],
                'invoice_number' => $record['invoice_number'],
                'currency_id' => $record['currency_id'],
                'due_date' => $record['due_date'],
                'payment_date' => $record['payment_date'],
                'client' => $record['client_name'] . (empty($record['company']) ? '' : ' - ') . $record['company'],
                'total_with_tax' => isset($record['total']) ? $record['total'] : $record['amount'],
                'total_without_tax' => $record['amount'],
                'total_collected' => $record['paid_amount'],
                'total_taxes' => isset($record['taxes']) ? $record['taxes'] : array(),
                'taxes' => isset($record['collected_taxes']) ? $record['collected_taxes'] : array(),
                'fees' => $record['total_transaction_fees']
            );

            $totals['total_with_tax'] += $record['total_with_tax'] / $exchange_rate;
            $totals['total_without_tax'] += $record['total_without_tax'] / $exchange_rate;
            $totals['total_collected'] += $record['total_collected'] / $exchange_rate;
            $totals['fees'] += $record['fees'] / $exchange_rate;
            foreach ($record['taxes'] as $tax_id => $collected_tax) {
                if (!isset($totals['taxes'][$tax_id])) {
                    $totals['taxes'][$tax_id] = array(
                        'uncollected' => 0,
                        'collected' => 0,
                        'total' => 0
                    );

                    $taxes[$tax_id] = Settings::tax($tax_id);
                    $taxes[$tax_id] = $taxes[$tax_id]['name'];
                }

                $totals['taxes'][$tax_id]['collected'] += $collected_tax / $exchange_rate;
                $totals['taxes'][$tax_id]['total'] += $record['total_taxes'][$tax_id] / $exchange_rate;
                $totals['taxes'][$tax_id]['uncollected'] += ($record['total_taxes'][$tax_id] - $collected_tax) / $exchange_rate;
            }
        }

        $fields = array(
            'invoice_number' => __('invoices:number'),
            'due_date' => __('projects:due_date'),
            'payment_date' => __('partial:paymentdate'),
            'client' => __('global:client'),
            'total_with_tax' => __('reports:total_with_tax'),
            'total_without_tax' => __('reports:total_without_tax'),
            'total_collected' => __('reports:total_collected'),
            'fees' => __('reports:fees_paid'),
            'taxes' => '{tax}',
        );

        $reportString = $this->generateReportString($from, $to, $client_id);

        return array(
            'title' => $this->reports[$report],
            'report' => $report,
            'from' => $from,
            'to' => $to,
            'taxes' => $taxes,
            'formatted_from' => format_date($from),
            'formatted_to' => format_date($to),
            'report_url' => site_url("reports/$report/view/$reportString"),
            'report_url_pdf' => site_url("reports/$report/pdf/$reportString"),
            'report_url_csv' => site_url("reports/$report/csv/$reportString"),
            'fields' => $fields,
            'records' => $records,
            'totals' => $totals
        );
    }

    function get($report, $from = 0, $to = 0, $client_id = NULL) {

        if ($report == 'expenses') {
            return $this->_expense_report($from, $to, $client_id, false);
        } elseif ($report == 'payments') {
            return $this->_payments_report($from, $to, $client_id, false);
        } elseif ($report == 'payments_per_method') {
            return $this->_payments_per_method_report($from, $to, $client_id, false);
        }

        if ($client_id == 0) {
            $client_id = NULL;
        }

        $CI = &get_instance();
        $CI->load->model('invoices/invoice_m');
        $CI->load->model('invoices/partial_payments_m', 'ppm');

        $fields = array(
            'invoice_number' => __('invoices:number'),
            'client_name' => __('reports:client_name'),
            'company' => __('global:company'),
            'due_date' => __('projects:due_date'),
            'unpaid_amount' => __('reports:unpaid_amount'),
            'paid_amount' => __('reports:paid_amount'),
            'billable_amount' => __('reports:total_amount')
        );
        $configs = array(
            'client_id' => $client_id,
            'from' => $from,
            'to' => $to,
            'include_totals' => true,
            'include_partials' => true,
            'return_object' => false
        );

        switch ($report) {

            case 'invoices':
                $field_totals = array('billable_amount', 'unpaid_amount', 'paid_amount');
                $records = $CI->invoice_m->flexible_get_all($configs);
                $configs['all'] = true;
                break;

            case 'invoices_per_status':
                $field_totals = array('billable_amount', 'unpaid_amount', 'paid_amount');
                $records = $CI->invoice_m->flexible_get_all($configs);
                $configs['all'] = true;
                $chart_field = 'formatted_is_paid';
                $per = __('reports:paid_vs_unpaid_over_time');
                $chart_type = 'line';
                break;

            case 'invoices_per_client_line':
                $field_totals = array('amount', 'unpaid_amount', 'paid_amount');
                $chart_field = 'client_name';
                $per = __('reports:per_client');
                $chart_type = 'line';
                break;

            case 'invoices_per_status_pie':
                $field_totals = array('money_amount', 'billable_amount');
                $records = $CI->ppm->flexible_get_all($configs);
                $configs['convert_to_invoices'] = true;
                $chart_field = 'formatted_is_paid';
                $per = __('reports:paid_and_unpaid');
                break;

            case 'tax_collected':
                $fields['tax_collected'] = __('invoices:tax_collected');
                $field_totals = array('tax_collected', 'billable_amount', 'unpaid_amount', 'paid_amount');
                $configs['order'] = $records = $CI->invoice_m->flexible_get_all($configs);
                $configs['tax_collected'] = true;
                break;

            case 'overdue_invoices':
                $field_totals = array('unpaid_amount', 'billable_amount', 'paid_amount');
                $configs['paid'] = false;
                $configs['overdue'] = true;
                break;
            case 'paid_invoices':
                $field_totals = array('paid_amount', 'billable_amount');
                unset($fields['unpaid_amount']);
                unset($fields['paid_amount']);
                $records = $CI->invoice_m->flexible_get_all($configs);
                $configs['paid'] = true;
                break;
            case 'unpaid_invoices':
                $field_totals = array('unpaid_amount', 'billable_amount', 'paid_amount');
                $records = $CI->invoice_m->flexible_get_all($configs);
                $configs['paid'] = false;
                break;
            case 'expenses':
                $records = array();
                $per = '';
        }

        if (!isset($records)) {
            $records = $CI->invoice_m->flexible_get_all($configs);
        }

        $return_field_totals = array();
        $client_field_totals = array();
        $chart_field = isset($chart_field) ? $chart_field : 'client_name';

        $time_points = array();
        $created = array();
        $paid = array();

        foreach ($records as $record) {

            foreach ($field_totals as $total) {
                $record[$total] = $record[$total] / $record['exchange_rate'];

                if (!isset($return_field_totals[$total])) {
                    $return_field_totals[$total] = 0;
                }

                if (!isset($client_field_totals[$total][$record[$chart_field]])) {
                    $client_field_totals[$total][$record[$chart_field]] = 0;
                }

                $return_field_totals[$total] = $return_field_totals[$total] + $record[$total];
                $client_field_totals[$total][$record[$chart_field]] = $client_field_totals[$total][$record[$chart_field]] + $record[$total];
            }

            if (isset($chart_type) and $chart_type == 'line') {
                $created[$record['date_entered']] = $record['billable_amount'];

                foreach ($record['partial_payments'] as $part) {
                    if ($part['is_paid']) {
                        $paid[$part['payment_date']] = $part['billableAmount'];
                    }
                }
            }
        }

        ksort($created);
        ksort($paid);
        $times = array_merge(array_keys($created), array_keys($paid));
        asort($times);

        $time_points = array();
        $previous = 0;

        foreach ($times as $time) {
            $previous_paid = isset($time_points[$previous]) ? $time_points[$previous]['paid'] : 0;
            $previous_unpaid = isset($time_points[$previous]) ? $time_points[$previous]['unpaid'] : 0;

            $time_points[$time] = array(
                'paid' => $previous_paid + (isset($paid[$time]) ? $paid[$time] : 0),
                'unpaid' => $previous_unpaid + (isset($created[$time]) ? $created[$time] : 0) - (isset($paid[$time]) ? $paid[$time] : 0),
            );

            $previous = $time;
        }

        $buffer = $time_points;
        $time_points = array(
            'paid_amount' => array(
                __('global:unpaid') => array(),
                __('global:paid') => array()
            )
        );

        foreach ($buffer as $time => $paid_unpaid) {
            $time_points['paid_amount'][__('global:unpaid')][$time] = $paid_unpaid['unpaid'];
            $time_points['paid_amount'][__('global:paid')][$time] = $paid_unpaid['paid'];
        }

        $i = 0;
        $nonformatted_total = 0;
        foreach ($return_field_totals as $key => $value) {
            if ($i == 0) {
                $nonformatted_total = $value;
            }

            $method = '_process_' . $key;
            if (method_exists($this, $method)) {
                $return_field_totals[$key] = $this->$method($value);
            }
            $i++;
        }

        reset($return_field_totals);
        $formatted_total = current($return_field_totals);
        reset($return_field_totals);

        reset($client_field_totals);
        $chart_totals = current($client_field_totals);
        reset($client_field_totals);

        if (empty($chart_totals)) {
            $chart_totals = array();
        }

        reset($time_points);
        $chart_time_points = current($time_points);
        reset($time_points);

        if (empty($chart_time_points)) {
            $chart_time_points = array();
        }

        $reportString = $this->generateReportString($from, $to, $client_id);

        $newRecords = array();
        # Time to filter out the records that aren't meant to show up in the report.
        # I have to get them all for partial_payments' sake, but I can't just show them all!
        foreach ($records as $record) {
            if ((isset($configs['paid']) and $record['is_paid'] == $configs['paid']) OR ( isset($configs['tax_collected']) and $record['tax_collected'] > 0) OR ( isset($configs['all']) and $configs['all'])) {
                $newRecords[] = $record;
            } elseif (isset($configs['convert_to_invoices']) and $configs['convert_to_invoices']) {

                if (!isset($newRecords[$record['unique_id']])) {
                    $newRecords[$record['unique_id']] = $record;
                    $newRecords[$record['unique_id']]['invoice_number'] = 0;
                    $newRecords[$record['unique_id']]['billable_amount'] = 0;
                    $newRecords[$record['unique_id']]['amount'] = 0;
                    $newRecords[$record['unique_id']]['unpaid_amount'] = 0;
                    $newRecords[$record['unique_id']]['paid_amount'] = 0;
                }

                $newRecords[$record['unique_id']]['billable_amount'] = $newRecords[$record['unique_id']]['amount'] + $record['money_amount'];
                $newRecords[$record['unique_id']]['amount'] = $newRecords[$record['unique_id']]['amount'] + $record['money_amount'];
                $newRecords[$record['unique_id']]['unpaid_amount'] = $newRecords[$record['unique_id']]['unpaid_amount'] + ($record['is_paid'] ? 0 : $record['money_amount']);
                $newRecords[$record['unique_id']]['paid_amount'] = $newRecords[$record['unique_id']]['paid_amount'] + ($record['is_paid'] ? $record['money_amount'] : 0);
            }
        }

        if (isset($configs['convert_to_invoices']) and $configs['convert_to_invoices']) {
            $field_totals[] = 'unpaid_amount';
            $field_totals[] = 'paid_amount';
            $field_totals[] = 'amount';
        }

        $return_field_totals = array();
        foreach ($newRecords as &$record) {
            foreach ($field_totals as $total) {
                if (!isset($return_field_totals[$total])) {
                    $return_field_totals[$total] = 0;
                }

                $return_field_totals[$total] = $return_field_totals[$total] + $record[$total];
            }

            foreach ($record as $key => $value) {
                $method = '_process_' . $key;
                if (method_exists($this, $method)) {
                    $record[$key] = $this->$method($value);
                }
            }
        }

        foreach ($return_field_totals as $key => $value) {
            $method = '_process_' . $key;
            if (method_exists($this, $method)) {
                $return_field_totals[$key] = $this->$method($value);
            }
        }

        foreach ($chart_totals as $key => $value) {
            if ($value == 0) {
                unset($chart_totals[$key]);
            }
        }

        $return = array(
            'report' => $report,
            'title' => $this->reports[$report],
            'from' => $from,
            'to' => $to,
            'formatted_from' => format_date($from),
            'formatted_to' => format_date($to),
            'report_url' => site_url("reports/$report/view/$reportString"),
            'report_url_pdf' => site_url("reports/$report/pdf/$reportString"),
            'report_url_csv' => site_url("reports/$report/csv/$reportString"),
            'fields' => $fields,
            'verb' => __("reports:verb_created"),
            'field_totals' => $return_field_totals,
            'report_total' => $nonformatted_total,
            'formatted_total' => $formatted_total,
            'client_field_totals' => $client_field_totals,
            'chart_type' => isset($chart_type) ? $chart_type : 'pie',
            'per' => isset($per) ? $per : __('reports:perclient'),
            'chart_totals' => $chart_totals,
            'time_points' => $time_points,
            'chart_time_points' => $chart_time_points,
            'records' => $newRecords
        );

        return $return;
    }

}
