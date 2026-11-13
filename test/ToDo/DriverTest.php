<?php

require_once __DIR__ . '/TestBase.php';

/**
 * Test cases for the Turba_Driver:: class
 *
 * @author  Jason M. Felice <jason.m.felice@gmail.com>
 * @package Turba
 * @subpackage UnitTests
 * @coversNothing
 */
class Turba_ToDo_DriverTest extends Turba_TestBase
{
    public function setUp()
    {
        $this->markTestIncomplete('Convert to use Horde_Test.');
        parent::setUp();
        $this->setUpDatabase();
    }

    public function test_search_results_should_be_sorted_according_to_supplied_sort_order()
    {
        $this->assertSortsList([$this, 'doSearch']);
    }

    public function doSearch($order)
    {
        $driver = $this->getDriver();
        $this->fakeAuth();
        return $driver->search(['__type' => 'Object'], $order, 'AND');
    }

}
