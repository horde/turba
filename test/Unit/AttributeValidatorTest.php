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

use Horde\Exception\InvalidDataThrowable;
use Horde\Turba\AttributeValidator;
use PHPUnit\Framework\TestCase;

class Turba_Unit_AttributeValidatorTest extends TestCase
{
    private AttributeValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new AttributeValidator([
            'homePostalCode' => [
                'label' => 'Home Postal Code',
                'type' => 'text',
                'params' => ['regex' => '', 'size' => 10, 'maxlength' => 10],
            ],
            'homeCity' => [
                'label' => 'Home City',
                'type' => 'text',
                'params' => ['regex' => '', 'size' => 40, 'maxlength' => 255],
            ],
            'nickname' => [
                'label' => 'Nickname',
                'type' => 'text',
                'params' => ['regex' => '/^[a-z]+$/', 'size' => 10, 'maxlength' => 10],
            ],
            'notes' => [
                'label' => 'Notes',
                'type' => 'longtext',
                'params' => ['rows' => 3, 'cols' => 40],
            ],
            'photo' => [
                'label' => 'Photo',
                'type' => 'image',
                'params' => ['show_upload' => true],
            ],
        ]);
    }

    public function testValuesWithinConstraintsPass(): void
    {
        $this->validator->validate([
            'homePostalCode' => '12345-6789',
            'homeCity' => 'Example City',
            'nickname' => 'alice',
            'notes' => str_repeat('x', 5000),
            'photo' => ['load' => ['data' => str_repeat('b', 100)]],
            '__key' => str_repeat('k', 300),
            'unknownAttribute' => str_repeat('u', 300),
        ]);
        $this->addToAssertionCount(1);
    }

    public function testEmptyValuesPass(): void
    {
        $this->validator->validate([
            'homePostalCode' => '',
            'nickname' => '',
            'homeCity' => null,
        ]);
        $this->addToAssertionCount(1);
    }

    public function testOverlongValueIsRejectedNamingTheAttribute(): void
    {
        try {
            $this->validator->validate(['homePostalCode' => '12345 Example']);
            $this->fail('Expected Turba_Exception_InvalidAttribute');
        } catch (Turba_Exception_InvalidAttribute $e) {
            $this->assertSame('homePostalCode', $e->getAttribute());
            $this->assertStringContainsString('Home Postal Code', $e->getMessage());
            $this->assertStringContainsString('10', $e->getMessage());
            $this->assertInstanceOf(Turba_Exception::class, $e);
            $this->assertInstanceOf(InvalidDataThrowable::class, $e);
        }
    }

    public function testLengthIsCountedInCharactersNotBytes(): void
    {
        // Ten multibyte characters fit a maxlength of 10.
        $this->validator->validate(['homePostalCode' => str_repeat('ü', 10)]);
        $this->addToAssertionCount(1);

        $this->expectException(Turba_Exception_InvalidAttribute::class);
        $this->validator->validate(['homePostalCode' => str_repeat('ü', 11)]);
    }

    public function testRegexIsEnforced(): void
    {
        try {
            $this->validator->validate(['nickname' => 'Alice1']);
            $this->fail('Expected Turba_Exception_InvalidAttribute');
        } catch (Turba_Exception_InvalidAttribute $e) {
            $this->assertSame('nickname', $e->getAttribute());
            $this->assertStringContainsString('Nickname', $e->getMessage());
        }
    }

    public function testLabelFallsBackToAttributeName(): void
    {
        $validator = new AttributeValidator([
            'foo' => ['params' => ['maxlength' => 2]],
        ]);
        try {
            $validator->validate(['foo' => 'bar']);
            $this->fail('Expected Turba_Exception_InvalidAttribute');
        } catch (Turba_Exception_InvalidAttribute $e) {
            $this->assertStringStartsWith('foo: ', $e->getMessage());
        }
    }
}
