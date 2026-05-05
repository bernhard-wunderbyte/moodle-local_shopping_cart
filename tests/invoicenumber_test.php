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
 * Unit tests for the invoicenumber class.
 *
 * @package    local_shopping_cart
 * @category   test
 * @copyright  2024 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart;

use advanced_testcase;
use core\event\base;
use local_shopping_cart\invoice\invoicenumber;

/**
 * Tests for invoicenumber::save_invoice_number and [[invoice_number]] placeholder replacement.
 *
 * @covers \local_shopping_cart\invoice\invoicenumber
 */
final class invoicenumber_test extends advanced_testcase {
    /**
     * Set up the test environment.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Creates a mock event with the given identifier in $event->get_data()['other']['identifier'].
     *
     * @param int $identifier
     * @return base
     */
    private function make_event(int $identifier): base {
        $event = $this->createMock(base::class);
        $event->method('get_data')->willReturn([
            'other' => ['identifier' => $identifier],
        ]);
        return $event;
    }

    /**
     * Test that the first invoice gets startinvoicenumber + 1 and subsequent
     * invoices increment correctly.
     *
     * startinvoicenumber: "TEST-2600000"
     *   => 1st invoice: "TEST-2600001"
     *   => 10th invoice: "TEST-2600010"
     */
    public function test_invoice_numbers_increment_with_prefix(): void {
        set_config('startinvoicenumber', 'TEST-2600000', 'local_shopping_cart');

        // Create 10 invoices with distinct identifiers.
        for ($i = 1; $i <= 10; $i++) {
            invoicenumber::save_invoice_number($this->make_event(2600000 + $i));
        }

        $first = invoicenumber::get_invoicenumber_by_identifier(2600001);
        $this->assertSame('TEST-2600001', $first, 'First invoice number should be TEST-2600001.');

        $tenth = invoicenumber::get_invoicenumber_by_identifier(2600010);
        $this->assertSame('TEST-2600010', $tenth, 'Tenth invoice number should be TEST-2600010.');
    }

    /**
     * Test that all 10 invoice numbers are unique and sequential.
     */
    public function test_all_invoice_numbers_are_sequential(): void {
        set_config('startinvoicenumber', 'TEST-2600000', 'local_shopping_cart');

        for ($i = 1; $i <= 10; $i++) {
            invoicenumber::save_invoice_number($this->make_event(2600000 + $i));
        }

        for ($i = 1; $i <= 10; $i++) {
            $result = invoicenumber::get_invoicenumber_by_identifier(2600000 + $i);
            $expected = 'TEST-' . (2600000 + $i);
            $this->assertSame($expected, $result, "Invoice $i should be $expected.");
        }
    }
}
