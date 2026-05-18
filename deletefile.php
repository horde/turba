<?php

use Horde\Util\Util;

/**
 * Turba deletefile.php.
 *
 * Copyright 2000-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (ASL).  If you
 * did not receive this file, see http://www.horde.org/licenses/apache.
 *
 * @author Chuck Hagenbuch <chuck@horde.org>
 */

require_once __DIR__ . '/lib/Application.php';
Horde_Registry::appInit('turba');

$source = Util::getPost('source');
if ($source === null || !isset($cfgSources[$source])) {
    $notification->push(_("Not found"), 'horde.error');
    Horde::url($prefs->getValue('initial_page'), true)->redirect();
}

$driver = $injector->getInstance('Turba_Factory_Driver')->create($source);

try {
    $contact = $driver->getObject(Util::getPost('key'));
} catch (Horde_Exception $e) {
    $notification->push($e);
    Horde::url($prefs->getValue('initial_page'), true)->redirect();
}

if (!$contact->isEditable()) {
    $notification->push(_("Permission denied"), 'horde.error');
    Horde::url($prefs->getValue('initial_page'), true)->redirect();
}

$file = Util::getPost('file');

try {
    $contact->deleteFile($file);
    $notification->push(sprintf(_("The file \"%s\" has been deleted."), $file), 'horde.success');
} catch (Turba_Exception $e) {
    $notification->push($e, 'horde.error');
}

$contact->url('Contact', true)->redirect();
