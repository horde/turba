<?php
/**
 * Turba directory driver implementation for Horde Preferences - very simple,
 * lightweight container.
 *
 * Copyright 2010-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (ASL).  If you did
 * did not receive this file, see http://www.horde.org/licenses/apache.
 *
 * @author   Chuck Hagenbuch <chuck@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/apache ASL
 * @package  Turba
 */
class Turba_Driver_Prefs extends Turba_Driver
{
    /**
     * Returns all entries - searching isn't implemented here for now. The
     * parameters are simply ignored.
     *
     * @param array $criteria    Array containing the search criteria.
     * @param array $fields      List of fields to return.
     * @param array $blobFields  Array of fields containing binary data.
     *
     * @return array  Hash containing the search results.
     */
    protected function _search(array $criteria, array $fields, array $blobFields = array(), $count_only = false)
    {
        return $count_only ? count($this->_getAddressBook()) : array_values($this->_getAddressBook());
    }

    /**
     * Reads the given data from the preferences and returns the result's
     * fields.
     *
     * @param string $key        The primary key field to use.
     * @param mixed $ids         The ids of the contacts to load.
     * @param string $owner      Only return contacts owned by this user.
     * @param array $fields      List of fields to return.
     * @param array $blobFields  Array of fields containing binary data.
     * @param array $dateFields  Array of fields containing date data.
     *                           @since 4.2.0
     *
     */
    protected function _read($key, $ids, $owner, array $fields,
                             array $blobFields = array(),
                             array $dateFields = array())
    {
        $book = $this->_getAddressBook();

        $results = array();
        if (!is_array($ids)) {
            $ids = array($ids);
        }

        foreach ($ids as $id) {
            if (isset($book[$id])) {
                $results[] = $book[$id];
            }
        }

        return $results;
    }

    /**
     * Adds the specified contact to the addressbook.
     *
     * @param array $attributes  The attribute values of the contact.
     * @param array $blob_fields  Fields that represent binary data.
     * @param array $date_fields  Fields that represent dates. @since 4.2.0
     *
     * @throws Turba_Exception
     */
    protected function _add(array $attributes, array $blob_fields = array(), array $date_fields = array())
    {
        $book = $this->_getAddressBook();
        $book[$attributes['id']] = $attributes;
        $this->_setAddressbook($book);
    }

    /**
     * TODO
     */
    protected function _canAdd()
    {
        return true;
    }

    /**
     * Deletes the specified object from the preferences.
     *
     * @param string $object_key TODO
     * @param string $object_id  TODO
     */
    protected function _delete($object_key, $object_id)
    {
        $book = $this->_getAddressBook();
        unset($book[$object_id]);
        $this->_setAddressbook($book);
    }

    /**
     * Saves the specified object in the preferences.
     *
     * @param Turba_Object $object TODO
     * 
     * @return string object id
     */
    function _save($object)
    {
        $object_keys = $this->toDriverKeys(array('__key' => $object->getValue('__key')));
        $object_id = reset($object_keys);
        $attributes = $this->toDriverKeys($object->getAttributes());

        $book = $this->_getAddressBook();
        $book[$object_id] = $attributes;
        $this->_setAddressBook($book);
        return (string) $object_id;
    }

    /**
     * TODO
     *
     * @return TODO
     */
    protected function _getAddressBook()
    {
        global $prefs;

        $val = $prefs->getValue('prefbooks');
        if (!empty($val)) {
            $prefbooks = unserialize($val);
            return $prefbooks[$this->_params['name']];
        }

        return array();
    }

    /**
     * TODO
     *
     * @param $addressbook TODO
     *
     */
    protected function _setAddressBook($addressbook): void
    {
        global $prefs;

        $val = $prefs->getValue('prefbooks');
        $prefbooks = empty($val)
            ? array()
            : unserialize($val);

        $prefbooks[$this->_params['name']] = $addressbook;
        $prefs->setValue('prefbooks', serialize($prefbooks));
        $prefs->store();
    }

}
