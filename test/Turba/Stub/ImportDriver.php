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

class Turba_Stub_ImportDriver extends Turba_Driver
{
    public array $contacts = [];
    public array $added = [];
    public array $reservedUids = [];
    public bool $failNextAdd = false;

    public function __construct()
    {
        parent::__construct('test');
        $this->title = 'Test';
        $this->map = [
            'name' => 'name',
            'email' => 'email',
            '__uid' => '__uid',
            '__key' => '__key',
            '__owner' => '__owner',
            '__type' => '__type',
            '__members' => '__members',
        ];
        $this->setContactOwner('alice@example.com');
    }

    public function search(
        array $search_criteria,
        $sort_order = null,
        $search_type = 'AND',
        array $return_fields = [],
        array $custom_strict = [],
        $match_begin = false,
        $count_only = false
    ) {
        $list = new Turba_List();
        foreach ($this->contacts as $contact) {
            $match = true;
            foreach ($search_criteria as $key => $value) {
                if (($contact[$key] ?? null) != $value) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                $list->insert(new Turba_Object($this, $contact));
            }
        }

        if ($count_only) {
            return $list->count();
        }

        return $list;
    }

    public function add(array $attributes)
    {
        if ($this->failNextAdd) {
            $this->failNextAdd = false;
            throw new Turba_Exception('add failed');
        }

        $uid = $attributes['__uid'] ?? null;
        if (is_string($uid) && $uid !== '' && in_array($uid, $this->reservedUids, true)) {
            throw new Turba_Exception(_("Server error when adding data."));
        }

        if (!isset($attributes['__uid'])) {
            $attributes['__uid'] = 'uid-' . (count($this->added) + 1);
        }
        if (!isset($attributes['__key'])) {
            $attributes['__key'] = 'key-' . (count($this->added) + 1);
        }
        $this->added[] = $attributes;
        $this->contacts[] = $attributes;

        return $attributes['__key'];
    }

    public function getObject($objectId)
    {
        foreach ($this->contacts as $contact) {
            if (($contact['__key'] ?? '') === $objectId) {
                return new Turba_Object($this, $contact);
            }
        }
        throw new Horde_Exception_NotFound();
    }

}
