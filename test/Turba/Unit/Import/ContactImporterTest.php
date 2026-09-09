<?php

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (ASL). If you
 * did not receive this file, see http://www.horde.org/licenses/apache.
 *
 * @author Torben Dannhauer <torben@dannhauer.de>
 * @category Horde
 * @copyright 2026 The Horde Project
 * @license http://www.horde.org/licenses/apache ASL
 * @package Turba
 * @subpackage UnitTests
 */

use Horde\Turba\Import\ContactImporter;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../Stub/ImportDriver.php';

class Turba_Unit_Import_ContactImporterTest extends TestCase
{
    private Turba_Stub_ImportDriver $driver;
    private ContactImporter $importer;

    protected function setUp(): void
    {
        $hooks = new class {
            public function hookExists()
            {
                return false;
            }
        };
        $injector = new Horde_Injector(new Horde_Injector_TopLevel());
        $injector->setInstance('Horde_Core_Hooks', $hooks);
        $GLOBALS['injector'] = $injector;
        $GLOBALS['attributes'] = [
            'email' => ['type' => 'email', 'params' => []],
            'name' => ['type' => 'text', 'params' => []],
        ];

        $this->driver = new Turba_Stub_ImportDriver();
        $this->importer = new ContactImporter(
            $this->driver,
            new Horde_Log_Logger(new Horde_Log_Handler_Null()),
            new Horde_Mail_Rfc822(),
            [
                'email' => [
                    'type' => 'email',
                    'params' => [],
                ],
                'name' => [
                    'type' => 'text',
                    'params' => [],
                ],
            ]
        );
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['injector'], $GLOBALS['attributes']);
    }

    public function testPrepareSplitsGroupsAndCountsContacts()
    {
        $job = $this->importer->prepare([
            ['name' => 'Alice Example', 'email' => 'alice@example.com'],
            ['name' => 'Family', '__members' => 'uid-1'],
        ]);

        $this->assertCount(1, $job['contacts']);
        $this->assertCount(1, $job['groups']);
        $this->assertFalse($job['done']);
        $progress = $this->importer->progress($job);
        $this->assertSame(2, $progress['total']);
    }

    public function testEmptyPrepareIsDone()
    {
        $job = $this->importer->prepare([]);
        $this->assertTrue($job['done']);
        $this->assertSame(0, $this->importer->progress($job)['total']);
    }

    public function testChunkedImport()
    {
        $rows = [];
        for ($i = 1; $i <= 5; $i++) {
            $rows[] = [
                'name' => 'Contact ' . $i,
                'email' => 'user' . $i . '@example.com',
            ];
        }
        $job = $this->importer->prepare($rows);
        $job = $this->importer->processChunk($job, 2);
        $this->assertSame(2, $job['offset']);
        $this->assertFalse($job['done']);
        $job = $this->importer->processChunk($job, 2);
        $this->assertSame(4, $job['offset']);
        $job = $this->importer->processChunk($job, 2);
        $this->assertTrue($job['done']);
        $this->assertSame(5, $job['imported']);
        $this->assertCount(5, $this->driver->added);
        $progress = $this->importer->progress($job);
        $this->assertSame(100, $progress['percent']);
        $this->assertSame(5, $progress['processed']);
    }

    public function testSkipDuplicates()
    {
        $this->driver->contacts[] = [
            'name' => 'Alice Example',
            'email' => 'alice@example.com',
            '__key' => 'existing',
        ];
        $job = $this->importer->prepare([
            ['name' => 'Alice Example', 'email' => 'alice@example.com'],
            ['name' => 'Bob Example', 'email' => 'bob@example.com'],
        ]);
        $job = $this->importer->processChunk($job, 10);
        $this->assertTrue($job['done']);
        $this->assertSame(1, $job['skipped']);
        $this->assertSame(1, $job['imported']);
    }

    public function testGroupsImportedAfterContacts()
    {
        $job = $this->importer->prepare([
            ['name' => 'Alice Example', 'email' => 'alice@example.com', '__uid' => 'uid-alice'],
            ['name' => 'Family', '__members' => 'uid-alice'],
        ]);
        $job = $this->importer->processChunk($job, 1);
        $this->assertFalse($job['done']);
        $this->assertSame(1, $job['imported']);
        $this->assertSame(0, $job['groups_imported']);
        $job = $this->importer->processChunk($job, 1);
        $this->assertTrue($job['done']);
        $this->assertSame(1, $job['groups_imported']);
        $last = end($this->driver->added);
        $this->assertSame('group', $last['__type']);
    }

    public function testErrorStopsJobWithoutSkippingRow()
    {
        $this->driver->failNextAdd = true;
        $job = $this->importer->prepare([
            ['name' => 'Alice Example', 'email' => 'alice@example.com'],
            ['name' => 'Bob Example', 'email' => 'bob@example.com'],
        ]);
        $job = $this->importer->processChunk($job, 10);
        $this->assertSame('add failed', $job['error']);
        $this->assertSame(0, $job['offset']);
        $this->assertSame(0, $job['imported']);
        $this->assertFalse($job['done']);
    }

    public function testSkipDuplicateUidInSameAddressBook()
    {
        $this->driver->contacts[] = [
            'name' => 'Alice Example',
            'email' => 'alice@example.com',
            '__uid' => 'uid-alice',
            '__key' => 'existing',
        ];
        $job = $this->importer->prepare([
            ['name' => 'Alice Example', 'email' => 'other@example.com', '__uid' => 'uid-alice'],
        ]);
        $job = $this->importer->processChunk($job, 10);
        $this->assertTrue($job['done']);
        $this->assertSame(1, $job['skipped']);
        $this->assertSame(0, $job['imported']);
    }

    public function testCopyWhenUidExistsForOwnerInAnotherBook()
    {
        $this->driver->reservedUids[] = 'uid-alice';
        $job = $this->importer->prepare([
            ['name' => 'Alice Example', 'email' => 'alice@example.com', '__uid' => 'uid-alice'],
        ]);
        $job = $this->importer->processChunk($job, 10);
        $this->assertTrue($job['done']);
        $this->assertSame(1, $job['imported']);
        $this->assertNotSame('uid-alice', $this->driver->added[0]['__uid']);
    }

    public function testEmptyUidGetsNewIdentifier()
    {
        $job = $this->importer->prepare([
            ['name' => 'Alice Example', 'email' => 'alice@example.com', '__uid' => ''],
        ]);
        $job = $this->importer->processChunk($job, 10);
        $this->assertTrue($job['done']);
        $this->assertSame(1, $job['imported']);
        $this->assertNotSame('', $this->driver->added[0]['__uid']);
    }

    public function testIsNonEmptyAttribute()
    {
        $this->assertTrue(ContactImporter::isNonEmptyAttribute('Alice'));
        $this->assertFalse(ContactImporter::isNonEmptyAttribute(''));
        $this->assertFalse(ContactImporter::isNonEmptyAttribute(['']));
        $this->assertTrue(ContactImporter::isNonEmptyAttribute(['Alice']));
    }

}
