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

use Horde\Turba\Import\ColumnMapper;
use PHPUnit\Framework\TestCase;

class Turba_Unit_Import_ColumnMapperTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $sourceMap;

    protected function setUp(): void
    {
        $this->sourceMap = [
            '__key' => 'object_id',
            '__uid' => 'object_uid',
            '__members' => 'object_members',
            'firstname' => 'object_firstname',
            'lastname' => 'object_lastname',
            'email' => 'object_email',
            'birthday' => 'object_bday',
            'photo' => 'object_photo',
            'name' => [
                'fields' => ['firstname', 'lastname'],
                'format' => '%s %s',
            ],
            'homeAddress' => [
                'fields' => ['homeStreet'],
                'format' => '%s',
            ],
            'workAddress' => [
                'fields' => ['workStreet'],
                'format' => '%s',
            ],
            'otherAddress' => [
                'fields' => ['otherStreet'],
                'format' => '%s',
            ],
        ];
    }

    public function testDestFieldKeysSkipCompositesAndMostInternals()
    {
        $keys = ColumnMapper::destFieldKeys($this->sourceMap);
        $this->assertContains('firstname', $keys);
        $this->assertContains('__uid', $keys);
        $this->assertContains('photo', $keys);
        $this->assertNotContains('name', $keys);
        $this->assertNotContains('homeAddress', $keys);
        $this->assertNotContains('__key', $keys);
    }

    public function testTurbaRoundTripCanSkipMapping()
    {
        $headers = [
            'kind', 'name', 'homeAddress', 'workAddress', 'otherAddress',
            '__uid', '__members', 'firstname', 'lastname', 'email', 'birthday',
        ];
        $this->assertTrue(ColumnMapper::canSkipMapping(
            $headers,
            ColumnMapper::destFieldKeys($this->sourceMap),
            $this->sourceMap
        ));
    }

    public function testUidAliasAllowsTsvSkip()
    {
        $headers = [
            'kind', 'name', 'uid', 'members', 'firstname', 'lastname', 'email',
        ];
        $this->assertTrue(ColumnMapper::canSkipMapping(
            $headers,
            ColumnMapper::destFieldKeys($this->sourceMap),
            $this->sourceMap
        ));
        [$dataKeys, $appKeys] = ColumnMapper::identityPairs(
            $headers,
            ColumnMapper::destFieldKeys($this->sourceMap)
        );
        $this->assertContains('uid', $dataKeys);
        $this->assertContains('__uid', $appKeys);
        $this->assertContains('members', $dataKeys);
        $this->assertContains('__members', $appKeys);
    }

    public function testUnknownColumnDoesNotSkipWhenUnmapped()
    {
        $headers = ['firstname', 'lastname', 'Custom Column'];
        $this->assertFalse(ColumnMapper::canSkipMapping(
            $headers,
            ColumnMapper::destFieldKeys($this->sourceMap),
            $this->sourceMap
        ));
    }

    public function testOutlookHeadersDoNotLookNative()
    {
        $headers = ['Title', 'First Name', 'Last Name', 'E-mail Address', 'Company'];
        $this->assertFalse(ColumnMapper::looksLikeNativeHeader(
            $headers,
            ColumnMapper::knownExportHeaders($this->sourceMap)
        ));
    }

    public function testTurbaHeadersLookNative()
    {
        $headers = ColumnMapper::parseHeaderRow(
            '"kind","name","firstname","lastname","email","uid","members"' . "\n" . '"","","Alice","Example","alice@example.com","u1",""' . "\n"
        );
        $this->assertTrue(ColumnMapper::looksLikeNativeHeader(
            $headers,
            ColumnMapper::knownExportHeaders($this->sourceMap)
        ));
    }

    public function testKeepNonIsoTimeFieldsDropsIsoBirthdays()
    {
        $time = ['birthday' => 'date', 'anniversary' => 'date'];
        $kept = ColumnMapper::keepNonIsoTimeFields($time, [
            ['birthday' => '1990-05-01', 'anniversary' => ''],
            ['birthday' => '2001-12-31', 'anniversary' => ''],
        ]);
        $this->assertSame([], $kept);
    }

    public function testKeepNonIsoTimeFieldsKeepsSlashDates()
    {
        $time = ['birthday' => 'date'];
        $kept = ColumnMapper::keepNonIsoTimeFields($time, [
            ['birthday' => '05/01/1990'],
        ]);
        $this->assertSame(['birthday' => 'date'], $kept);
    }

    public function testAllDateSamplesAreIso()
    {
        $this->assertTrue(ColumnMapper::allDateSamplesAreIso([
            'birthday' => ['type' => 'date', 'values' => ['1990-05-01', '2001-12-31']],
        ]));
        $this->assertFalse(ColumnMapper::allDateSamplesAreIso([
            'birthday' => ['type' => 'date', 'values' => ['05/01/1990']],
        ]));
        $this->assertFalse(ColumnMapper::allDateSamplesAreIso([]));
    }

    public function testIdentityPairsSkipComposites()
    {
        [$dataKeys, $appKeys] = ColumnMapper::identityPairs(
            ['kind', 'name', 'firstname', 'email'],
            ColumnMapper::destFieldKeys($this->sourceMap)
        );
        $this->assertSame(['firstname', 'email'], $dataKeys);
        $this->assertSame(['firstname', 'email'], $appKeys);
    }
}
