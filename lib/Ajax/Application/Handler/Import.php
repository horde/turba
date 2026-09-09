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
 */

use Horde\Turba\Import\ContactImporter;

/**
 * AJAX actions for chunked address-book import.
 */
class Turba_Ajax_Application_Handler_Import extends Horde_Core_Ajax_Application_Handler
{
    /**
     * Import the next slice of the in-progress address-book import.
     *
     * @return object  Progress fields: processed, total, percent, imported,
     *                 skipped, groups_imported, done, error, progress_text,
     *                 summary.
     */
    public function importContacts()
    {
        global $attributes, $injector, $notification;

        $returnOb = new stdClass();
        $returnOb->processed = 0;
        $returnOb->total = 0;
        $returnOb->percent = 0;
        $returnOb->imported = 0;
        $returnOb->skipped = 0;
        $returnOb->groups_imported = 0;
        $returnOb->done = false;
        $returnOb->error = null;
        $returnOb->progress_text = '';
        $returnOb->summary = '';

        $storage = new Horde_Core_Data_Storage();
        $job = $storage->get(ContactImporter::STORAGE_KEY);
        if (!is_array($job)) {
            $returnOb->error = _("No import is in progress.");
            $returnOb->progress_text = $returnOb->error;
            return $returnOb;
        }

        $format = $storage->get('format');
        $target = $storage->get('target');
        $file_types = [
            'csv'      => _("CSV"),
            'tsv'      => _("TSV"),
            'vcard'    => _("vCard"),
            'mulberry' => _("Mulberry Address Book"),
            'pine'     => _("Pine Address Book"),
            'ldif'     => _("LDIF Address Book"),
        ];

        try {
            $driver = $injector->getInstance('Turba_Factory_Driver')->create($target);
            $importer = new ContactImporter(
                $driver,
                $injector->getInstance('Horde_Log_Logger'),
                $injector->getInstance('Horde_Mail_Rfc822'),
                $attributes
            );
            $job = $importer->processChunk($job);
            $storage->set(ContactImporter::STORAGE_KEY, $job);
            $progress = $importer->progress($job);
        } catch (Horde_Exception $e) {
            $injector->getInstance('Horde_Log_Logger')->err($e);
            $returnOb->error = $e->getMessage();
            $returnOb->progress_text = sprintf(_("There was an error importing the data: %s"), $e->getMessage());
            $storage->clear();
            return $returnOb;
        }

        foreach ($progress as $key => $value) {
            $returnOb->$key = $value;
        }
        $returnOb->progress_text = sprintf(
            _("Importing contacts: %d / %d (%d%%)"),
            $progress['processed'],
            $progress['total'],
            $progress['percent']
        );

        if (!empty($job['error'])) {
            $returnOb->error = $job['error'];
            $returnOb->progress_text = sprintf(_("There was an error importing the data: %s"), $job['error']);
            $storage->clear();
            return $returnOb;
        }

        if ($progress['done']) {
            $returnOb->summary = $this->_summary($progress);
            if ($progress['imported'] || $progress['groups_imported']) {
                $label = $file_types[$format] ?? $format;
                $notification->push(sprintf(_("%s file successfully imported."), $label), 'horde.success');
            }
            $storage->clear();
        }

        return $returnOb;
    }

    /**
     * @param array{imported: int, skipped: int, groups_imported: int} $progress
     */
    private function _summary(array $progress): string
    {
        $parts = [];
        if ($progress['imported']) {
            $parts[] = sprintf(
                ngettext("Imported %d contact.", "Imported %d contacts.", $progress['imported']),
                $progress['imported']
            );
        }
        if ($progress['skipped']) {
            $parts[] = sprintf(
                ngettext("%d contact already existed and was skipped.", "%d contacts already existed and were skipped.", $progress['skipped']),
                $progress['skipped']
            );
        }
        if ($progress['groups_imported']) {
            $parts[] = sprintf(
                ngettext("Imported %d contact list.", "Imported %d contact lists.", $progress['groups_imported']),
                $progress['groups_imported']
            );
        }
        if (!$parts) {
            return _("Import complete.");
        }
        return implode(' ', $parts);
    }

}
