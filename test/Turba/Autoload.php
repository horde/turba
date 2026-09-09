<?php

/**
 * Setup autoloading for the tests.
 *
 * PHP version 5
 *
 * @category   Horde
 * @package    Turba
 * @subpackage UnitTests
 * @author     Gunnar Wrobel <wrobel@pardus.de>
 * @link       http://www.horde.org/apps/turba
 * @license    http://www.horde.org/licenses/apache Apache-like
 */

Horde_Test_Autoload::addPrefix('Turba', __DIR__ . '/../../lib');

/** Load the basic test definition */
require_once __DIR__ . '/TestCase.php';

/** Load stub definitions */
require_once __DIR__ . '/Stub/Tagger.php';
require_once __DIR__ . '/Stub/Types.php';
require_once __DIR__ . '/Stub/ImportDriver.php';

spl_autoload_register(function ($class) {
    $prefix = 'Horde\\Turba\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    $rel = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = __DIR__ . '/../../src/' . $rel . '.php';
    if (file_exists($file)) {
        require $file;
    }
});
