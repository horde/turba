<?php

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (ASL).  If you
 * did not receive this file, see http://www.horde.org/licenses/apache.
 *
 * @author   Torben Dannhauer <torben@dannhauer.de>
 * @category Horde
 * @license  http://www.horde.org/licenses/apache ASL
 * @package  Turba
 */

use Horde\Exception\InvalidDataThrowable;

/**
 * Exception thrown when a contact attribute value violates the constraints
 * configured in attributes.php (maxlength, regex).
 *
 * Raised by every write path (web form, API, ActiveSync, CardDAV, import),
 * so callers can report the offending field instead of a generic backend
 * error. Implements InvalidDataThrowable so protocol handlers can treat the
 * item as unacceptable rather than retrying it.
 *
 * @author   Torben Dannhauer <torben@dannhauer.de>
 * @category Horde
 * @license  http://www.horde.org/licenses/apache ASL
 * @package  Turba
 */
class Turba_Exception_InvalidAttribute extends Turba_Exception implements InvalidDataThrowable
{
    /**
     * @param string $attribute  Name of the offending attribute (e.g.
     *                           'homePostalCode').
     * @param string $message    Human readable description.
     */
    public function __construct(protected string $attribute, string $message)
    {
        parent::__construct($message);
    }

    /**
     * Returns the name of the attribute that failed validation.
     */
    public function getAttribute(): string
    {
        return $this->attribute;
    }
}
