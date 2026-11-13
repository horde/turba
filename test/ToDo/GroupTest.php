<?php

require_once __DIR__ . '/TestBase.php';

/**
 * @author  Jason M. Felice <jason.m.felice@gmail.com>
 * @package Turba
 * @subpackage UnitTests
 * @coversNothing
 */
class Turba_ToDo_GroupTest extends Turba_TestBase
{
    public $group;

    public function setUp()
    {
        $this->markTestIncomplete('Convert to use Horde_Test.');
        parent::setUp();
        $this->setUpDatabase();

        $driver = $this->getDriver();
        $this->group = $driver->getObject('fff');
        $this->assertOk($this->group);
    }

    public function test_listMembers_returns_objects_sorted_according_to_parameters()
    {
        $this->assertSortsList([$this->group, 'listMembers']);
    }

}
