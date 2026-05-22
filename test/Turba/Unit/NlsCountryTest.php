<?php

/**
 * Tests the Nls API contract used by Turba_Driver for country/timezone lookups.
 *
 * Turba_Driver uses:
 * - Horde_Nls::getCountryISO($code) to look up country names by ISO code
 * - Horde_Nls_Loader::loadCountries() to get full country list for reverse lookup
 * - Horde_Nls::getTimezones() to get available timezones for vCard parsing
 * @coversNothing
 */
class Turba_Unit_NlsCountryTest extends PHPUnit\Framework\TestCase
{
    /**
     * Test that getCountryISO returns a country name for a valid code.
     * Used in _toVcard() to populate ADR country field.
     */
    public function testGetCountryIsoReturnsNameForValidCode()
    {
        $result = Horde_Nls::getCountryISO('US');

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    /**
     * Test that getCountryISO returns null for invalid code.
     */
    public function testGetCountryIsoReturnsNullForInvalidCode()
    {
        $result = Horde_Nls::getCountryISO('ZZ');

        $this->assertNull($result);
    }

    /**
     * Test that loadCountries returns array of code => name pairs.
     * Used in _fromVcard() for reverse lookup (name → code).
     */
    public function testLoadCountriesReturnsArray()
    {
        $countries = Horde_Nls_Loader::loadCountries();

        $this->assertIsArray($countries);
        $this->assertNotEmpty($countries);
        $this->assertArrayHasKey('US', $countries);
        $this->assertIsString($countries['US']);
    }

    /**
     * Test reverse lookup: array_search(name, countries) finds a code.
     * This is the actual pattern used in _fromVcard().
     */
    public function testCountryReverseLookupFindsCode()
    {
        $countries = Horde_Nls_Loader::loadCountries();
        $name = Horde_Nls::getCountryISO('DE');

        $code = array_search($name, $countries);

        $this->assertSame('DE', $code);
    }

    /**
     * Test that getTimezones returns array with timezone identifiers as keys.
     * Used in _fromVcard() to validate timezone from vCard TZ field.
     */
    public function testGetTimezonesReturnsArray()
    {
        $timezones = Horde_Nls::getTimezones();

        $this->assertIsArray($timezones);
        $this->assertNotEmpty($timezones);
        $this->assertArrayHasKey('America/New_York', $timezones);
    }
}
