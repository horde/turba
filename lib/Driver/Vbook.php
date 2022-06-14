<?php
/**
 * Turba directory driver implementation for virtual address books.
 *
 * Copyright 2005-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (ASL).  If you
 * did not receive this file, see http://www.horde.org/licenses/apache.
 *
 * @author   Michael J. Rubinsky <mrubinsk@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/apache ASL
 * @package  Turba
 */
class Turba_Driver_Vbook extends Turba_Driver
{
    /**
     * Search type for this virtual address book.
     *
     * @var string
     */
    public $searchType;

    /**
     * The search criteria that defines this virtual address book.
     *
     * @var array
     */
    public $searchCriteria;

    /**
     * The composed driver.
     *
     * @var Turba_Driver
     */
    protected $_driver;

    /**
     * Constructs a new Turba_Driver object.
     *
     * @param string $name   Source name
     * @param array $params  Additional configuration parameters:
     *        - share: Horde_Share object representing this vbook.
     *        - source: The configuration array of the parent source or the
     *                  source name of parent source in the global cfgSources
     *                  array.
     */
    public function __construct($name = '', array $params = array())
    {
        parent::__construct($name, $params);

        /* Grab a reference to the share for this vbook. */
        $this->_share = $this->_params['share'];

        /* Load the underlying driver. */
        $this->_driver = $GLOBALS['injector']
            ->getInstance('Turba_Factory_Driver')
            ->createFromConfig($this->_params);

        $this->searchCriteria = empty($this->_params['criteria'])
            ? array()
            : $this->_params['criteria'];
        $this->searchType = (count($this->searchCriteria) > 1)
            ? 'advanced'
            : 'basic';
    }

    /**
     * Remove all data for a specific user.
     *
     * @param string $user  The user to remove all data for.
     */
    public function removeUserData($user)
    {
        // Make sure we are being called by an admin.
        if (!$GLOBALS['registry']->isAdmin()) {
            throw new Horde_Exception_PermissionDenied(_("Permission denied"));
        }

        $GLOBALS['injector']
            ->getInstance('Turba_Shares')
            ->removeShare($this->_share);
        unset($this->_share);
    }

    /**
     * Return the owner to use when searching or creating contacts in
     * this address book.
     *
     * @return string
     */
    protected function _getContactOwner()
    {
        return $this->_driver->getContactOwner();
    }

    /**
     * Return all entries matching the combined searches represented by
     * $criteria and the vitural address book's search criteria.
     *
     * @param array $criteria  Array containing the search criteria.
     * @param array $fields    List of fields to return
     * @param array $blobFileds  Array of fields that contain
     *
     * @return array  Hash containing the search results.
     * @throws Turba_Exception
     */
    protected function _search(
        array $criteria, array $fields, array $blobFields = array(), $count_only = false)
    {
        /* Add the passed in search criteria to the vbook criteria
         * (which need to be mapped from turba fields to
         * driver-specific fields). */
        $new_criteria = array();
        if (empty($criteria['AND'])) {
            $new_criteria['AND'] = array(
                $criteria,
                $this->makeSearch($this->searchCriteria, 'AND', array())
            );
        } else {
            $new_criteria = $criteria;
            $new_criteria['AND'][] = $this->makeSearch($this->searchCriteria, 'AND', array());
        }
        $results = $this->_driver->_search($new_criteria, $fields, $blobFields);
        return $count_only ? count($results) : $results;
    }

    /**
     * Returns a Turba_List object containing $objects filtered by $tags.
     *
     * @param  array $objects     A hash of objects, as returned by
     *                            self::_search.
     * @param  array $tags        An array of tags to filter by.
     * @param  Array $sort_order  The sort order to pass to Turba_List::sort.
     *                            (Unused).
     *
     * @return Turba_List  The filtered Turba_List object.
     */
    protected function _filterTags($objects, $tags, $sort_order = null)
    {
        global $injector;

        // Overridden in this class so we can grab the internally stored
        // tag criteria.
        if (isset($this->searchCriteria['tags'])) {
            $tags = array_merge(
                $injector->getInstance('Turba_Tagger')->split($this->searchCriteria['tags']),
                $tags
            );

        }

        return parent::_filterTags($objects, $tags);
    }

    /**
     * Reads the requested entries from the underlying source.
     *
     * @param string $key        The primary key field to use.
     * @param mixed $ids         The ids of the contacts to load.
     * @param string $owner      Only return contacts owned by this user.
     * @param array $fields      List of fields to return.
     * @param array $blobFields  Array of fields containing binary data.
     * @param array $dateFields  Array of fields containing date data.
     *                           @since 4.2.0
     *
     * @return array  Hash containing the search results.
     * @throws Turba_Exception
     */
    protected function _read($key, $ids, $owner, array $fields,
                             array $blobFields = array(),
                             array $dateFields = array())
    {
        return $this->_driver->_read($key, $ids, $owner, $fields, $blobFields, $dateFields);
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
        throw new Turba_Exception(_("You cannot add new contacts to a virtual address book"));
    }

    /**
     * Not supported for virtual address books.
     *
     * @see Turba_Driver::_delete
     * @throws Turba_Exception
     */
    protected function _delete($object_key, $object_id)
    {
        throw new Turba_Exception(_("You cannot delete contacts from a virtual address book"));
    }

    /**
     * @see Turba_Driver::_save
     */
    protected function _save(Turba_Object $object)
    {
        return $this->_driver->save($object);
    }

    /**
     * Check to see if the currently logged in user has requested permissions.
     *
     * @param integer $perm  The permissions to check against.
     *
     * @return boolean  True or False.
     */
    public function hasPermission($perm)
    {
        return $this->_driver->hasPermission($perm);
    }

}
