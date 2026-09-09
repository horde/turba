<?php

declare(strict_types=1);

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

namespace Horde\Turba\Import;

use Horde_Icalendar_Vcard;
use Horde_Log_Logger;
use Horde_Mail_Exception;
use Horde_Mail_Rfc822;
use Turba_Driver;
use Turba_Exception;
use Turba_Object_Group;

/**
 * Chunked address-book import: duplicate check, add, then contact groups.
 */
class ContactImporter
{
    public const STEP_PROGRESS = 'import_progress';
    public const STORAGE_KEY = 'import_job';
    public const CHUNK_SIZE = 25;

    private ?string $lastError = null;

    /**
     * @param array<string, array> $attributes Turba attribute definitions.
     */
    public function __construct(
        private readonly Turba_Driver $driver,
        private readonly Horde_Log_Logger $logger,
        private readonly Horde_Mail_Rfc822 $rfc822,
        private readonly array $attributes,
    ) {}

    /**
     * Split parsed rows into serializable contact hashes and deferred groups.
     *
     * @param array<int, mixed> $rows
     *
     * @return array<string, mixed>
     */
    public function prepare(array $rows): array
    {
        $contacts = [];
        $groups = [];

        foreach ($rows as $row) {
            if ($row instanceof Horde_Icalendar_Vcard) {
                $contacts[] = $this->driver->toHash($row);
                continue;
            }
            if (!empty($row['__members'])) {
                $groups[] = $row;
                continue;
            }
            $contacts[] = $row;
        }

        return [
            'contacts' => $contacts,
            'groups' => $groups,
            'offset' => 0,
            'imported' => 0,
            'skipped' => 0,
            'groups_imported' => 0,
            'error' => null,
            'done' => count($contacts) === 0 && count($groups) === 0,
        ];
    }

    /**
     * Import up to $limit contacts (then remaining groups once contacts are done).
     *
     * @param array<string, mixed> $job
     *
     * @return array<string, mixed>
     */
    public function processChunk(array $job, int $limit = self::CHUNK_SIZE): array
    {
        if (!empty($job['done']) || !empty($job['error'])) {
            return $job;
        }

        $processed = 0;
        $contacts = $job['contacts'];
        $totalContacts = count($contacts);

        while ($processed < $limit && $job['offset'] < $totalContacts) {
            $result = $this->importContact($contacts[$job['offset']]);
            if ($result === 'error') {
                $job['error'] = $this->lastError;
                return $job;
            }
            $job['offset']++;
            $processed++;
            if ($result === 'skipped') {
                $job['skipped']++;
            } else {
                $job['imported']++;
            }
        }

        if ($job['offset'] < $totalContacts) {
            return $job;
        }

        $groups = $job['groups'];
        $totalGroups = count($groups);
        while ($processed < $limit && $job['groups_imported'] < $totalGroups) {
            if (!$this->importGroup($groups[$job['groups_imported']])) {
                $job['error'] = $this->lastError;
                return $job;
            }
            $job['groups_imported']++;
            $processed++;
        }

        if ($job['groups_imported'] >= $totalGroups) {
            $job['done'] = true;
        }

        return $job;
    }

    /**
     * @param array<string, mixed> $job
     *
     * @return array{processed: int, total: int, percent: int, imported: int, skipped: int, groups_imported: int, done: bool, error: ?string}
     */
    public function progress(array $job): array
    {
        $total = count($job['contacts']) + count($job['groups']);
        $processed = (int) $job['offset'] + (int) $job['groups_imported'];
        if ($total < 1) {
            $percent = !empty($job['done']) ? 100 : 0;
        } else {
            $percent = (int) round(($processed / $total) * 100);
            if ($percent > 100) {
                $percent = 100;
            }
        }

        return [
            'processed' => $processed,
            'total' => $total,
            'percent' => $percent,
            'imported' => (int) $job['imported'],
            'skipped' => (int) $job['skipped'],
            'groups_imported' => (int) $job['groups_imported'],
            'done' => !empty($job['done']),
            'error' => $job['error'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return 'imported'|'skipped'|'error'
     */
    private function importContact(array $row): string
    {
        try {
            $result = $this->driver->search(
                array_filter($row, [self::class, 'isNonEmptyAttribute'])
            );
        } catch (Turba_Exception $e) {
            $this->logger->err($e);
            $this->lastError = $e->getMessage();
            return 'error';
        }

        if (count($result)) {
            return 'skipped';
        }

        foreach (array_keys($row) as $field) {
            if (($this->attributes[$field]['type'] ?? null) !== 'email') {
                continue;
            }
            $allow_multi = is_array($this->attributes[$field]['params'] ?? null)
                && !empty($this->attributes[$field]['params']['allow_multi']);
            try {
                $row[$field] = strval($this->rfc822->parseAddressList($row[$field], [
                    'limit' => $allow_multi ? 0 : 1,
                ]));
            } catch (Horde_Mail_Exception $e) {
                $row[$field] = '';
            }
        }
        $row['__owner'] = $this->driver->getContactOwner();

        try {
            $this->driver->add($row);
        } catch (Turba_Exception $e) {
            $this->logger->err($e);
            $this->lastError = $e->getMessage();
            return 'error';
        }

        return 'imported';
    }

    /**
     * @param array<string, mixed> $group
     */
    private function importGroup(array $group): bool
    {
        $attributes = $group;
        unset($attributes['__members']);
        if (!isset($attributes['__key'])) {
            $attributes['__key'] = '';
        }
        $group_obj = new Turba_Object_Group($this->driver, $attributes);
        foreach (explode(',', (string) $group['__members']) as $uid) {
            $uid = trim($uid);
            if ($uid === '') {
                continue;
            }
            try {
                $results = $this->driver->search(['__uid' => $uid]);
            } catch (Turba_Exception $e) {
                $this->logger->err($e);
                $this->lastError = $e->getMessage();
                return false;
            }
            if (count($results->objects)) {
                $object = array_pop($results->objects);
                $group_obj->addMember($object->getValue('__key'), $object->getSource());
            }
        }
        $attributes['__members'] = $group_obj->getValue('__members');
        $attributes['__type'] = 'group';

        try {
            $this->driver->add($attributes);
        } catch (Turba_Exception $e) {
            $this->logger->err($e);
            $this->lastError = $e->getMessage();
            return false;
        }

        return true;
    }

    /**
     * @param mixed $var
     */
    public static function isNonEmptyAttribute($var): bool
    {
        if (!is_array($var)) {
            return $var != '';
        }

        foreach ($var as $v) {
            if ($v == '') {
                return false;
            }
        }

        return true;
    }
}
