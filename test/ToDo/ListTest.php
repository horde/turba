<?php

require_once __DIR__ . '/TestBase.php';

/**
 * @author  Jason M. Felice <jason.m.felice@gmail.com>
 * @package Turba
 * @subpackage UnitTests
 * @coversNothing
 */
class Turba_ToDo_ListTest extends Turba_TestBase
{
    public function setUp()
    {
        $this->markTestIncomplete('Convert to use Horde_Test.');
        parent::setUp();
        $this->setUpDatabase();
    }

    public function test_sort_should_sort_according_to_passed_parameters()
    {
        $this->assertSortsList([$this, 'sortList']);
    }

    public function sortList($order)
    {
        $list = $this->getList();
        $list->sort($order);
        return $list;
    }

}
