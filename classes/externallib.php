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
 * Wunderbyte table external API
 *
 * @package local_shopping_cart
 * @category external
 * @copyright 2021 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_shopping_cart\shopping_cart;

defined('MOODLE_INTERNAL') || die();

require_once('shopping_cart.php');

/**
 * Class local_shopping_cart_external
 */
class local_shopping_cart_external extends external_api {
    /**
     * Webservice for shopping_cart class to fetch data.
     *
     * @return object
     */
    public static function add_item_to_cart($component, $itemid) {
        $params = external_api::validate_parameters(self::add_item_to_cart_parameters(), [
            'component' => $component,
            'itemid' => $itemid,
        ]);

        $cartitem = shopping_cart::get_cartitem($params['component'], $params['itemid']);
        // We need the cartitem as an array.
        $item = $cartitem->getitem();

        $shoppingcart = new shopping_cart();
        $shoppingcart->add_item_to_cart($item);

        return $item;
    }

    /**
     * Describes the paramters for load_data.
     * @return external_function_parameters
     */
    public static function add_item_to_cart_parameters() {
        return new external_function_parameters(array(
                        'component'  => new external_value(PARAM_RAW, 'component', VALUE_DEFAULT, ''),
                        'itemid'  => new external_value(PARAM_INT, 'itemid', VALUE_DEFAULT, ''),

                )
        );
    }

    /**
     * Describes the return values for add_item_to_cart.
     * @return external_multiple_structure
     */
    public static function add_item_to_cart_returns() {
        return new external_single_structure(array(
                    'itemid' => new external_value(PARAM_RAW, 'html content'),
                    'itemname' => new external_value(PARAM_RAW, 'html content'),
                    'expirationdate' => new external_value(PARAM_RAW, 'html content'),
                    'price' => new external_value(PARAM_RAW, 'html content'),
                    'currency' => new external_value(PARAM_RAW, 'currency'),
                    'componentname' => new external_value(PARAM_RAW, 'html content'),
                    'description' => new external_value(PARAM_RAW, 'html content'),
                )
        );
    }
    /**
     * Webservice for shopping_cart class to fetch data.
     *
     * @return object // This is the rendered table_sql generated by the ->out method in the form {"content": "tableasstring"}.
     */
    public static function delete_item_from_cart($itemid, $component) {

        $params = external_api::validate_parameters(self::delete_item_from_cart_parameters(), [
            'itemid' => $itemid,
            'component' => $component
        ]);

        shopping_cart::delete_item_from_cart($params['itemid'], $params['component']);
    }

    /**
     * Describes the paramters for delete_item_from_cart.
     * @return external_function_parameters
     */
    public static function delete_item_from_cart_parameters() {
        return new external_function_parameters(array(
                        'itemid'  => new external_value(PARAM_INT, 'id', VALUE_DEFAULT, '0'),
                        'component'  => new external_value(PARAM_RAW, 'component name like mod_booking', VALUE_DEFAULT, ''),
                )
        );
    }

    /**
     * Describes the return values for delete_item_from_cart.
     * @return external_multiple_structure
     */
    public static function delete_item_from_cart_returns() {
    }
}
