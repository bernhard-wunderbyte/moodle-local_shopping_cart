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

namespace local_shopping_cart\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("$CFG->libdir/formslib.php");

use context;
use context_system;
use core_form\dynamic_form;
use local_shopping_cart\shopping_cart;
use moodle_exception;
use moodle_url;
use stdClass;

/**
 * Dynamic optiondate form.
 * @copyright Wunderbyte GmbH <info@wunderbyte.at>
 * @author Bernhard Fischer
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class modal_add_discount_to_item extends dynamic_form {

    /**
     * {@inheritdoc}
     * @see moodleform::definition()
     */
    public function definition() {

        global $USER;

        $mform = $this->_form;

        $userid = $this->_ajaxformdata["userid"] == 0
            ? $USER->id : $this->_ajaxformdata["userid"];

        $mform->addElement('static', 'bodytext', '', get_string('adddiscounttoitem', 'local_shopping_cart'));

        $mform->addElement('hidden', 'itemid', $this->_ajaxformdata["itemid"]);
        $mform->addElement('hidden', 'userid', $userid);
        $mform->addElement('hidden', 'componentname', $this->_ajaxformdata["componentname"]);

        $mform->addElement('float', 'discountpercent', get_string('discountpercent', 'local_shopping_cart'));
        $mform->addHelpButton('discountpercent', 'discountpercent', 'local_shopping_cart');
        $mform->setType('discountpercent', PARAM_FLOAT);
        $mform->setDefault('discountpercent', 0);
        $mform->addRule('discountpercent', get_string('floatonly'), 'numeric', null , 'client');

        $mform->addElement('float', 'discountabsolut', get_string('discountabsolut', 'local_shopping_cart'));
        $mform->addHelpButton('discountabsolut', 'discountabsolut', 'local_shopping_cart');
        $mform->setType('discountabsolut', PARAM_FLOAT);
        $mform->setDefault('discountabsolut', 0);
        $mform->addRule('discountabsolut', get_string('floatonly'), 'numeric', null , 'client');
    }

    /**
     * Check access for dynamic submission.
     *
     * @return void
     */
    protected function check_access_for_dynamic_submission(): void {
        require_capability('local/shopping_cart:cashier', $this->get_context_for_dynamic_submission());
    }

    /**
     * Process the form submission, used if form was submitted via AJAX
     *
     * This method can return scalar values or arrays that can be json-encoded, they will be passed to the caller JS.
     *
     * Submission data can be accessed as: $this->get_data()
     *
     * @return mixed
     */
    public function process_dynamic_submission() {

        global $USER;

        $data = $this->get_data();

        $userid = empty($data->userid)
            ? $USER->id : $data->userid;

        shopping_cart::add_discount_to_item(
            $data->componentname,
            $data->itemid,
            $data->userid,
            $data->discountpercent,
            $data->discountabsolut);

        return $data;
    }


    /**
     * Load in existing data as form defaults
     *
     * Can be overridden to retrieve existing values from db by entity id and also
     * to preprocess editor and filemanager elements
     *
     * Example:
     *     $this->set_data(get_entity($this->_ajaxformdata['cmid']));
     */
    public function set_data_for_dynamic_submission(): void {

        global $USER;
        $data = new stdClass();

        $userid = $this->_ajaxformdata["userid"] == 0
            ? $USER->id : $this->_ajaxformdata["userid"];

        $itemid = $this->_ajaxformdata["itemid"];
        $component = $this->_ajaxformdata["componentname"];

        $cache = \cache::make('local_shopping_cart', 'cacheshopping');
        $cachekey = $userid . '_shopping_cart';

        $cachedrawdata = $cache->get($cachekey);
        $cacheitemkey = $component . '-' . $itemid;

        // Item has to be there.
        if (!isset($cachedrawdata['items'][$cacheitemkey])) {
            throw new moodle_exception('itemnotfound', 'local_shopping_cart');
        }

        $item = $cachedrawdata['items'][$cacheitemkey];

        $discount = $item['discount'] ?? 0;

        // We have to guess if the value comes from percentage or absolute.
        if (!empty($discount)
            && (0 === (($discount * 100) % $item['price']))) {
                // This seems to come from percentage, because we get a nice number.
                $data->discountpercent = ($discount * 100) / $item['price'];
        } else {
            $data->discountabsolut = $discount;
        }

        $this->set_data($data);
    }

    /**
     * Returns form context
     *
     * If context depends on the form data, it is available in $this->_ajaxformdata or
     * by calling $this->optional_param()
     *
     * @return context
     */
    protected function get_context_for_dynamic_submission(): context {

        return context_system::instance();
    }

    /**
     * Returns url to set in $PAGE->set_url() when form is being rendered or submitted via AJAX
     *
     * This is used in the form elements sensitive to the page url, such as Atto autosave in 'editor'
     *
     * If the form has arguments (such as 'id' of the element being edited), the URL should
     * also have respective argument.
     *
     * @return moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): moodle_url {

        // We don't need it, as we only use it in modal.
        return new moodle_url('/');
    }

    /**
     * Validate dates.
     *
     * {@inheritdoc}
     * @see moodleform::validation()
     */
    public function validation($data, $files) {

        $errors = array();

        if (!empty($data['discountpercent']) && !empty($data['discountabsolut'])) {
            $errors['discountpercent'] = get_string('onlyonevaluecanbeset', 'local_shopping_cart');
            $errors['discountabsolut'] = get_string('onlyonevaluecanbeset', 'local_shopping_cart');
        }

        return $errors;
    }

    /**
     * {@inheritDoc}
     * @see moodleform::get_data()
     */
    public function get_data() {
        $data = parent::get_data();
        return $data;
    }
}