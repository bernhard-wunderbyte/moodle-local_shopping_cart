<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Shopping cart cash report.
 *
 * @package     local_shopping_cart
 * @copyright   2022 Wunderbyte GmbH <info@wunderbyte.at>
 * @author      Bernhard Fischer
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_shopping_cart\table\cash_report_table;

require_once(__DIR__ . '/../../config.php');

global $DB;

$download = optional_param('download', '', PARAM_ALPHA);

// No guest autologin.
require_login(0, false);

$context = context_system::instance();
$PAGE->set_context($context);

$baseurl = new moodle_url('/local/shopping_cart/report.php');
$PAGE->set_url($baseurl);

if (!has_capability('local/shopping_cart:cashier', $context)) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('accessdenied', 'local_shopping_cart'), 4);
    echo get_string('nopermissiontoaccesspage', 'local_shopping_cart');
    echo $OUTPUT->footer();
    die();
}

// File name and sheet name.
$fileandsheetname = "cash_report";

$cashreporttable = new cash_report_table('cash_report_table');

$cashreporttable->is_downloading($download, $fileandsheetname, $fileandsheetname);

$tablebaseurl = $baseurl;
$tablebaseurl->remove_params('page');
$cashreporttable->define_baseurl($tablebaseurl);
$cashreporttable->defaultdownloadformat = 'pdf';

if (!$cashreporttable->is_downloading()) {

    // Table will be shown normally.
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('cashreport', 'local_shopping_cart'));

    // Dismissible alert containing the description of the report.
    echo '<div class="alert alert-secondary alert-dismissible fade show" role="alert">' .
        get_string('cashreport_desc', 'local_shopping_cart') .
        '<button type="button" class="close" data-dismiss="alert" aria-label="Close">
        <span aria-hidden="true">&times;</span>
        </button>
    </div>';

    // Header.
    $cashreporttable->define_headers([
        get_string('identifier', 'local_shopping_cart'),
        get_string('timecreated', 'local_shopping_cart'),
        get_string('timemodified', 'local_shopping_cart'),
        get_string('price', 'local_shopping_cart'),
        get_string('currency', 'local_shopping_cart'),
        get_string('lastname', 'local_shopping_cart'),
        get_string('firstname', 'local_shopping_cart'),
        get_string('itemid', 'local_shopping_cart'),
        get_string('itemname', 'local_shopping_cart'),
        get_string('payment', 'local_shopping_cart'),
        get_string('paymentstatus', 'local_shopping_cart'),
        get_string('gateway', 'local_shopping_cart'),
        get_string('orderid', 'local_shopping_cart'),
        get_string('usermodified', 'local_shopping_cart')
    ]);

    // Columns.
    $cashreporttable->define_columns([
        'identifier',
        'timecreated',
        'timemodified',
        'price',
        'currency',
        'lastname',
        'firstname',
        'itemid',
        'itemname',
        'payment',
        'paymentstatus',
        'gateway',
        'orderid',
        'usermodified'
    ]);

    // Get payment account from settings.
    $accountid = get_config('local_shopping_cart', 'accountid');
    $account = null;
    if (!empty($accountid)) {
        $account = new \core_payment\account($accountid);
    }

    // Create selects for each payment gateway.
    $colselects = [];
    // Create an array of table names for the payment gateways.
    if (!empty($account)) {
        foreach ($account->get_gateways() as $gateway) {
            $gwname = $gateway->get('gateway');
            if ($gateway->get('enabled')) {
                $tablename = "paygw_" . $gwname;

                $cols = $DB->get_columns($tablename);

                // Do not add the table if it does not have exactly 3 columns.
                if (count($cols) != 3) {
                    continue;
                }

                // Generate a select for each table.
                foreach ($cols as $key => $value) {
                    if (strpos($key, 'orderid') !== false) {
                        $colselects[] =
                            "SELECT $gwname.paymentid, $gwname.$key orderid
                            FROM {paygw_$gwname} $gwname";
                    }
                }
            }
        }
    }

    $selectorderidpart = "";
    if (!empty($colselects)) {
        $selectorderidpart = ", pgw.orderid";
        $colselectsstring = implode(' UNION ', $colselects);
        $gatewayspart = "LEFT JOIN ($colselectsstring) pgw ON p.id = pgw.paymentid";
    }

    // SQL query. The subselect will fix the "Did you remember to make the first column something...
    // ...unique in your call to get_records?" bug.
    $fields = "sch.identifier, sch.price, sch.currency,
            u.lastname, u.firstname, sch.itemid, sch.itemname, sch.payment, sch.paymentstatus, " .
            $DB->sql_concat("um.firstname", "' '", "um.lastname") . " as usermodified, sch.timecreated, sch.timemodified,
            p.gateway$selectorderidpart";
    $from = "{local_shopping_cart_history} sch
            LEFT JOIN {user} u
            ON u.id = sch.userid
            LEFT JOIN {user} um
            ON um.id = sch.usermodified
            LEFT JOIN {payments} p
            ON p.itemid = sch.identifier
            $gatewayspart";
    $where = "1 = 1";
    $params = ['paymentstatus' => PAYMENT_SUCCESS];

    // Now build the table.
    $cashreporttable->set_sql($fields, $from, $where, $params);
    $cashreporttable->out(50, false);

    echo $OUTPUT->footer();

}
// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
/* else {

    // The table is being downloaded.

    // Header.
    $cashreporttable->define_headers([
        get_string('name'),
        get_string('optiondate', 'mod_booking'),
        get_string('teacher', 'mod_booking')
    ]);
    // Columns.
    $cashreporttable->define_columns([
        'optionname',
        'optiondate',
        'teacher'
    ]);

    // SQL query. The subselect will fix the "Did you remember to make the first column something...
    // ...unique in your call to get_records?" bug.
    $fields = "s.optiondateid, s.text, s.optionid, s.coursestarttime, s.courseendtime, s.teachers";
    $from = "(
        SELECT bod.id optiondateid, bo.text, bod.optionid, bod.coursestarttime, bod.courseendtime, " .
        $DB->sql_group_concat('u.id', ',', 'u.id') . " teachers
        FROM {booking_optiondates} bod
        LEFT JOIN {booking_optiondates_teachers} bodt
        ON bodt.optiondateid = bod.id
        LEFT JOIN {booking_options} bo
        ON bo.id = bod.optionid
        LEFT JOIN {user} u
        ON u.id = bodt.userid
        WHERE bod.optionid = :optionid
        GROUP BY bod.id, bod.optionid, bod.coursestarttime, bod.courseendtime
        ORDER BY bod.coursestarttime ASC
        ) s";
    $where = "1=1";
    $params = ['optionid' => $optionid];

    // Now build the table.
    $cashreporttable->set_sql($fields, $from, $where, $params);
    $cashreporttable->setup();
    $cashreporttable->query_db(TABLE_SHOW_ALL_PAGE_SIZE);
    $cashreporttable->build_table();
    $cashreporttable->finish_output();
}*/
