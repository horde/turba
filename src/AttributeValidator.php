<?php

declare(strict_types=1);
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

namespace Horde\Turba;

use Horde\Util\HordeString;
use Horde_Form_Translation;
use Turba_Exception_InvalidAttribute;

/**
 * Validates contact attribute values against the constraints configured in
 * attributes.php.
 *
 * The web edit form enforces 'maxlength' and 'regex' through Horde_Form;
 * every other write path (registry API, ActiveSync, CardDAV, vCard/CSV
 * import) used to hand the raw values to the backend, where an oversized
 * value either failed with a database error (PostgreSQL, SQLSTATE 22001)
 * or was silently truncated (MySQL in non-strict mode). This class applies
 * the same two rules the form applies, so all paths behave alike.
 *
 * @author   Torben Dannhauer <torben@dannhauer.de>
 * @category Horde
 * @license  http://www.horde.org/licenses/apache ASL
 * @package  Turba
 */
final class AttributeValidator
{
    /**
     * @param array $definitions  The attribute definitions from
     *                            attributes.php, keyed by attribute name.
     */
    public function __construct(private readonly array $definitions)
    {
    }

    /**
     * Validate a set of attribute values.
     *
     * Only scalar string values of attributes that have a definition are
     * checked; empty values, internal '__*' keys, and attributes without
     * 'maxlength' / 'regex' params pass unchanged.
     *
     * @param array $values  Attribute values keyed by attribute name.
     *
     * @throws Turba_Exception_InvalidAttribute  On the first violation.
     */
    public function validate(array $values): void
    {
        foreach ($values as $name => $value) {
            if (!is_string($name)
                || str_starts_with($name, '__')
                || !isset($this->definitions[$name])
                || !is_string($value)
                || $value === '') {
                continue;
            }

            $params = $this->definitions[$name]['params'] ?? [];
            if (!is_array($params)) {
                continue;
            }

            $maxlength = (int) ($params['maxlength'] ?? 0);
            if ($maxlength > 0 && HordeString::length($value) > $maxlength) {
                throw new Turba_Exception_InvalidAttribute(
                    $name,
                    sprintf(
                        '%s: %s',
                        $this->label($name),
                        sprintf(
                            Horde_Form_Translation::t("Value is over the maximum length of %d."),
                            $maxlength
                        )
                    )
                );
            }

            $regex = $params['regex'] ?? '';
            if (is_string($regex) && $regex !== '' && !preg_match($regex, $value)) {
                throw new Turba_Exception_InvalidAttribute(
                    $name,
                    sprintf(
                        '%s: %s',
                        $this->label($name),
                        Horde_Form_Translation::t("You must enter a valid value.")
                    )
                );
            }
        }
    }

    /**
     * Human readable label of an attribute, falling back to its name.
     */
    private function label(string $name): string
    {
        $label = $this->definitions[$name]['label'] ?? '';
        return is_string($label) && $label !== '' ? $label : $name;
    }
}
