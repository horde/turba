<?php

/**
 * Turba data.php.
 *
 * Copyright 2001-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (ASL).  If you
 * did not receive this file, see http://www.horde.org/licenses/apache.
 *
 * @author Jan Schneider <jan@horde.org>
 */

require_once __DIR__ . '/lib/Application.php';

use Horde\Turba\Import\ColumnMapper;
use Horde\Turba\Import\ContactImporter;
use Horde\Turba\Import\ImportWizard;
$app_ob = Horde_Registry::appInit('turba');

if (!$conf['menu']['import_export']) {
    require TURBA_BASE . '/index.php';
    exit;
}

/* If there are absolutely no valid sources, abort. */
if (!$cfgSources) {
    $notification->push(_("No Address Books are currently available. Import and Export is disabled."), 'horde.error');
    $page_output->header();
    $notification->notify(['listeners' => 'status']);
    $page_output->footer();
    exit;
}

/* Importable file types. */
$file_types = [
    'csv'      => _("CSV"),
    'tsv'      => _("TSV"),
    'vcard'    => _("vCard"),
    'mulberry' => _("Mulberry Address Book"),
    'pine'     => _("Pine Address Book"),
    'ldif'     => _("LDIF Address Book"),
];

/* Templates for the different import steps. */
$templates = [
    Horde_Data::IMPORT_FILE => [TURBA_TEMPLATES . '/data/export.inc'],
    Horde_Data::IMPORT_CSV => [$registry->get('templates', 'horde') . '/data/csvinfo.inc'],
    Horde_Data::IMPORT_TSV => [$registry->get('templates', 'horde') . '/data/tsvinfo.inc'],
    Horde_Data::IMPORT_MAPPED => [$registry->get('templates', 'horde') . '/data/csvmap.inc'],
    Horde_Data::IMPORT_DATETIME => [$registry->get('templates', 'horde') . '/data/datemap.inc'],
    ContactImporter::STEP_PROGRESS => [TURBA_TEMPLATES . '/data/import_progress.inc'],
];

/* Initial values. */
$vars = $injector->getInstance('Horde_Variables');
$import_step = $vars->get('import_step', 0) + 1;
$next_step = Horde_Data::IMPORT_FILE;
$app_fields = $bad_charset = $time_fields = [];
$error = false;
$import_mapping = [
    'e-mail' => 'email',
    'homeaddress' => 'homeAddress',
    'businessaddress' => 'workAddress',
    'homephone' => 'homePhone',
    'businessphone' => 'workPhone',
    'mobilephone' => 'cellPhone',
    'businessfax' => 'fax',
    'jobtitle' => 'title',
    'internetfreebusy' => 'freebusyUrl',

    // Turba groups
    'uid' => '__uid',
    'members' => '__members',

    // Entourage on MacOS
    'Dept' => 'department',
    'Work Street Address' => 'workStreet',
    'Work City' => 'workCity',
    'Work State' => 'workProvince',
    'Work Zip' => 'workPostalCode',
    'Work Country/Region' => 'workCountry',
    'Home Street Address' => 'homeStreet',
    'Home City' => 'homeCity',
    'Home State' => 'homeProvince',
    'Home Zip' => 'homePostalCode',
    'Home Country/Region' => 'homeCountry',
    'Work Fax' => 'workFax',
    'Work Phone 1' => 'workPhone',
    'Home Phone 1' => 'homePhone',
    'Instant Messaging 1' => 'imaddress',

    // Thunderbird
    'Primary Email' => 'email',
    'Fax Number' => 'fax',
    'Pager Number' => 'pager',
    'Mobile Number' => 'Mobile Phone',
    'Home Address' => 'homeStreet',
    'Home ZipCode' => 'homePostalCode',
    'Work Address' => 'workStreet',
    'Work ZipCode' => 'workPostalCode',
    'Work Country' => 'workCountry',
    'Work Phone' => 'workPhone',
    'Organization' => 'company',
    'Web Page 1' => 'website',
];
$param = [
    'time_fields' => $time_fields,
    'file_types'  => $file_types,
    'import_mapping' => array_merge(
        $app_ob->getOutlookMapping(),
        $import_mapping
    ),
];
if (in_array($vars->import_format, ['mulberry', 'pine'])) {
    $vars->import_format = 'tsv';
}
if ($vars->actionID != 'select') {
    array_unshift($templates[Horde_Data::IMPORT_FILE], TURBA_TEMPLATES . '/data/import.inc');
}

$data = null;
if ($vars->import_format) {
    switch ($vars->import_format) {
        case 'ldif':
            $driver = 'Turba_Data_Ldif';
            break;

        case 'csv':
            $param['check_charset'] = true;
            // Fall-through

            // no break
        default:
            $driver = $vars->import_format;
            break;
    }

    try {
        $data = $injector->getInstance('Horde_Core_Factory_Data')->create(
            $driver,
            [
                'cleanup' => [$app_ob, 'cleanupData'],
            ]
        );
    } catch (Horde_Exception $e) {
        $notification->push(_("This file format is not supported."), 'horde.error');
        $next_step = Horde_Data::IMPORT_FILE;
    }
}

/* Loop through the action handlers. */
switch ($vars->actionID) {
    case Horde_Data::IMPORT_FILE:
        try {
            $driver = $injector->getInstance('Turba_Factory_Driver')->create($vars->dest);
        } catch (Horde_Exception $e) {
            $notification->push($e, 'horde.error');
            $error = true;
            break;
        }

        if (Turba::hasMaxContacts($driver, true)) {
            $error = true;
        } else {
            $data->storage->set('target', $vars->dest);
            $data->storage->set('purge', $vars->purge);
        }
        break;

    case Horde_Data::IMPORT_MAPPED:
    case Horde_Data::IMPORT_DATETIME:
        $param['time_fields'] = ColumnMapper::timeFields(
            $cfgSources[$data->storage->get('target')]['map'],
            $attributes
        );
        break;
}

if (!$error && $data) {
    try {
        try {
            $next_step = $data->nextStep($vars->actionID, $param);
            $next_step = turba_auto_advance_import($data, $vars, $param, $cfgSources, $attributes, $next_step);

            /* Raise warnings if some exist. */
            if (method_exists($data, 'warnings')) {
                $warnings = $data->warnings();
                if (count($warnings)) {
                    foreach ($warnings as $warning) {
                        $notification->push($warning, 'horde.warning');
                    }
                    $notification->push(_("The import can be finished despite the warnings."), 'horde.message');
                }
            }
        } catch (Horde_Data_Exception_Charset $e) {
            if ($e->badCharset != 'UTF-8') {
                $bad_charset[] = $e->badCharset;
                throw $e;
            }

            $param['charset'] = 'windows-1252';
            try {
                $next_step = $data->nextStep($vars->actionID, $param);
                $next_step = turba_auto_advance_import($data, $vars, $param, $cfgSources, $attributes, $next_step);
            } catch (Horde_Data_Exception_Charset $e) {
                $bad_charset = ['UTF-8', 'windows-1252'];
                throw $e;
            }
        }
    } catch (Horde_Data_Exception $e) {
        $notification->push($e, 'horde.error');
        $next_step = $data->cleanup();
    }
}

/* We have a final result set. */
if (is_array($next_step)) {
    /* Create a Turba storage instance. */
    $dest = $data->storage->get('target');
    try {
        $driver = $injector->getInstance('Turba_Factory_Driver')->create($dest);
    } catch (Horde_Exception $e) {
        $notification->push($e, 'horde.error');
        $driver = null;
    }

    if (!count($next_step)) {
        $notification->push(sprintf(_("The %s file didn't contain any contacts."), $file_types[$data->storage->get('format')]), 'horde.error');
        $next_step = $data->cleanup();
    } elseif ($driver) {
        /* Purge old address book if requested. */
        if ($data->storage->get('purge')) {
            try {
                $driver->deleteAll();
                $notification->push(_("Address book successfully purged."), 'horde.success');
            } catch (Turba_Exception $e) {
                $notification->push(sprintf(_("The address book could not be purged: %s"), $e->getMessage()), 'horde.error');
            }
        }

        $importer = new ContactImporter(
            $driver,
            $injector->getInstance('Horde_Log_Logger'),
            $injector->getInstance('Horde_Mail_Rfc822'),
            $attributes
        );
        $job = $importer->prepare($next_step);
        $data->storage->set(ContactImporter::STORAGE_KEY, $job);
        $data->storage->set('data');
        $next_step = ContactImporter::STEP_PROGRESS;
    } else {
        $next_step = $data->cleanup();
    }
}

$import_total = 0;
if ($next_step === ContactImporter::STEP_PROGRESS) {
    $job = $data->storage->get(ContactImporter::STORAGE_KEY);
    $import_total = count($job['contacts'] ?? []) + count($job['groups'] ?? []);
}

switch ($next_step) {
    case Horde_Data::IMPORT_MAPPED:
    case Horde_Data::IMPORT_DATETIME:
        foreach ($cfgSources[$data->storage->get('target')]['map'] as $field => $null) {
            if ((substr($field, 0, 2) != '__'  && !is_array($null)) || ($field == '__uid' || $field == '__members')) {
                if ($field == '__uid') {
                    $app_fields['__uid'] = _("UID");
                } elseif ($field == '__members') {
                    $app_fields['__members'] = _("Contact list members");
                } else {
                    $app_fields[$field] = $attributes[$field]['label'];
                }
            }
        }
        break;
}

$page_output->addScriptFile('import.js');
$page_output->addInlineJsVars([
    'TurbaImport.text' => [
        'preparing' => _("Preparing import — please wait."),
    ],
]);
if ($next_step === ContactImporter::STEP_PROGRESS) {
    $page_output->ajax = true;
}

$page_output->header([
    'title' => _("Import/Export Address Books"),
    'view' => Horde_Registry::VIEW_BASIC,
]);
$notification->notify(['listeners' => 'status']);

$default_source = $prefs->getValue('default_dir');
if ($next_step == Horde_Data::IMPORT_FILE) {
    /* Build the directory sources select widget. */
    $unique_source = '';
    $source_options = [];
    foreach (Turba::getAddressBooks() as $key => $entry) {
        if (!empty($entry['export'])) {
            $source_options[] = '<option value="' . htmlspecialchars($key) . '">'
                . htmlspecialchars($entry['title']) . "</option>\n";
            $unique_source = $key;
        }
    }

    /* Build the directory destination select widget. */
    $unique_dest = '';
    $dest_options = [];
    $hasWriteable = false;
    foreach (Turba::getAddressBooks(Horde_Perms::EDIT) as $key => $entry) {
        $selected = ($key == $default_source) ? ' selected="selected"' : '';
        $dest_options[] = '<option value="' . htmlspecialchars($key) . '" ' . $selected . '>'
            . htmlspecialchars($entry['title']) . "</option>\n";
        $unique_dest = $key;
        $hasWriteable = true;
    }

    if (!$hasWriteable) {
        array_shift($templates[$next_step]);
    }

    /* Build the charset options. */
    $charsets = [];

    if (!empty($bad_charset)) {
        $charsets = $registry->nlsconfig->encodings_sort;
        foreach ($registry->nlsconfig->charsets as $charset) {
            if (!isset($charsets[$charset])
                && !in_array($charset, $bad_charset)) {
                $charsets[$charset] = $charset;
            }
        }
        $my_charset = $GLOBALS['registry']->getLanguageCharset();
    }
}

// @todo: These variables are needed for stuff that is in *Horde*, not Turba.
// That's not correct.
if ($data) {
    $storage = $data->storage;
}

foreach ($templates[$next_step] as $template) {
    require $template;
}

$page_output->footer();

/**
 * Skip delimiter / mapping / ISO-date wizard pages for Turba-exported files.
 *
 * @param mixed $nextStep
 *
 * @return mixed
 */
function turba_auto_advance_import($data, $vars, array &$param, array $cfgSources, array $attributes, $nextStep)
{
    $sourceMap = [];
    $target = $data->storage->get('target');
    if ($target && isset($cfgSources[$target]['map'])) {
        $sourceMap = $cfgSources[$target]['map'];
    }

    return (new ImportWizard())->autoAdvance(
        $data,
        $vars,
        $param,
        $sourceMap,
        $attributes,
        $nextStep
    );
}
