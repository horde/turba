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

use PHPUnit\Framework\TestCase;

/**
 * CardDAV round-trip for typed email and address fields.
 *
 * @author Torben Dannhauer <torben@dannhauer.de>
 * @category Horde
 * @copyright 2026 The Horde Project
 * @license http://www.horde.org/licenses/apache ASL
 * @package Turba
 * @subpackage UnitTests
 */
class Turba_Unit_CardDavSyncTest extends TestCase
{
    private $previousAttributes;

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

        $this->previousAttributes = $GLOBALS['attributes'] ?? null;
        $GLOBALS['attributes'] = [
            'email' => ['type' => 'email'],
            'homeEmail' => ['type' => 'email'],
            'workEmail' => ['type' => 'email'],
            'emails' => ['type' => 'email'],
        ];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['injector']);
        if ($this->previousAttributes === null) {
            unset($GLOBALS['attributes']);
        } else {
            $GLOBALS['attributes'] = $this->previousAttributes;
        }
    }

    public function testHomeAddressWithPrefStaysHome()
    {
        $hash = $this->toHash('BEGIN:VCARD
VERSION:3.0
FN:Alice Example
N:Example;Alice;;;
ADR;TYPE=HOME;TYPE=pref:;;1 Home Street;Town;State;12345;
END:VCARD
');

        $this->assertSame('1 Home Street', $hash['homeStreet']);
        $this->assertArrayNotHasKey('workStreet', $hash);
        $this->assertArrayNotHasKey('commonStreet', $hash);
        $this->assertArrayNotHasKey('workAddress', $hash);
        $this->assertArrayNotHasKey('commonAddress', $hash);

        $work = $this->toHash('BEGIN:VCARD
VERSION:3.0
FN:Alice Example
N:Example;Alice;;;
ADR;TYPE=WORK;TYPE=pref:;;2 Work Street;Town;State;12345;
END:VCARD
');
        $this->assertSame('2 Work Street', $work['workStreet']);
        $this->assertArrayNotHasKey('homeStreet', $work);
        $this->assertArrayNotHasKey('commonStreet', $work);

        $untyped = $this->toHash('BEGIN:VCARD
VERSION:3.0
FN:Alice Example
N:Example;Alice;;;
ADR:;;3 Other Street;Town;;;;
END:VCARD
');
        $this->assertSame('3 Other Street', $untyped['commonStreet']);
        $this->assertArrayNotHasKey('homeStreet', $untyped);
        $this->assertArrayNotHasKey('workStreet', $untyped);
    }

    public function testTypedEmailsDoNotFillEmptySlots()
    {
        $map = $this->emailMap();
        $object = $this->objectFromHash($this->toHash('BEGIN:VCARD
VERSION:3.0
FN:Alice Example
N:Example;Alice;;;
EMAIL;TYPE=INTERNET;TYPE=HOME;TYPE=pref:home@example.com
EMAIL;TYPE=INTERNET;TYPE=WORK:work@example.com
END:VCARD
', $map), $map);
        $object->ensureAttributes();

        $this->assertSame('home@example.com', $object->attributes['homeEmail']);
        $this->assertSame('work@example.com', $object->attributes['workEmail']);
        $this->assertArrayNotHasKey('email', $object->attributes);

        $homeOnly = $this->objectFromHash($this->toHash('BEGIN:VCARD
VERSION:3.0
FN:Alice Example
N:Example;Alice;;;
EMAIL;TYPE=HOME:home@example.com
END:VCARD
', $map), $map);
        $homeOnly->ensureAttributes();

        $this->assertSame('home@example.com', $homeOnly->attributes['homeEmail']);
        $this->assertArrayNotHasKey('email', $homeOnly->attributes);
        $this->assertArrayNotHasKey('workEmail', $homeOnly->attributes);
    }

    public function testExtraEmailFillsOneEmptySlot()
    {
        $map = $this->emailMap();
        $object = $this->objectFromHash($this->toHash('BEGIN:VCARD
VERSION:3.0
FN:Alice Example
N:Example;Alice;;;
EMAIL:one@example.com
EMAIL:two@example.com
END:VCARD
', $map), $map);
        $object->ensureAttributes();

        $this->assertSame('one@example.com', $object->attributes['email']);
        $this->assertSame('two@example.com', $object->attributes['homeEmail']);
        $this->assertArrayNotHasKey('workEmail', $object->attributes);
        $this->assertStringNotContainsString(',', $object->attributes['email']);
        $this->assertStringNotContainsString(',', $object->attributes['homeEmail']);
    }

    public function testNestedEmailTypesDoNotAbortImport()
    {
        $hash = $this->toHash('BEGIN:VCARD
VERSION:3.0
FN:Alice Example
N:Example;Alice;;;
EMAIL;TYPE=INTERNET,HOME;TYPE=WORK,pref:home@example.com
END:VCARD
', $this->emailMap());

        $this->assertSame('home@example.com', $hash['homeEmail']);
    }

    private function emailMap(): array
    {
        return [
            'email' => 'object_email',
            'homeEmail' => 'object_homeemail',
            'workEmail' => 'object_workemail',
        ];
    }

    private function toHash(string $vcard, array $map = []): array
    {
        $driver = new Turba_Driver();
        $driver->map = $map;
        $ical = new Horde_Icalendar();
        $ical->parsevCalendar($vcard);

        return $driver->toHash($ical->getComponent(0));
    }

    private function objectFromHash(array $hash, array $map): Turba_Object
    {
        $driver = new Turba_Driver();
        $driver->map = $map;
        $object = new Turba_Object($driver);
        foreach ($hash as $attribute => $value) {
            $object->setValue($attribute, $value);
        }

        return $object;
    }
}
