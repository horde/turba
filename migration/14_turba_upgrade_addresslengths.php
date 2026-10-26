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

/**
 * Widens the post office box columns.
 *
 * "P.O. Box 12345" or "Postfach 10 11 12" do not fit into the original
 * VARCHAR(10). 255 matches the other address columns of turba_objects. The
 * postal code columns keep their width of 10; their limit is now enforced
 * on every write path by Horde\Turba\AttributeValidator instead of by the
 * database.
 *
 * @author   Torben Dannhauer <torben@dannhauer.de>
 * @category Horde
 * @license  http://www.horde.org/licenses/apache ASL
 * @package  Turba
 */
class TurbaUpgradeAddressLengths extends Horde_Db_Migration_Base
{
    /**
     * Columns to widen with their previous limit.
     *
     * @var array
     */
    protected $_columns = [
        'object_homepob' => 10,
        'object_workpob' => 10,
        'object_otherpob' => 10,
    ];

    /**
     * Upgrade.
     */
    public function up()
    {
        $cols = array_keys($this->_connection->table('turba_objects')->getColumns());
        foreach (array_keys($this->_columns) as $column) {
            if (in_array($column, $cols)) {
                $this->changeColumn('turba_objects', $column, 'string', ['limit' => 255]);
            }
        }
    }

    /**
     * Downgrade.
     */
    public function down()
    {
        $cols = array_keys($this->_connection->table('turba_objects')->getColumns());
        foreach ($this->_columns as $column => $limit) {
            if (in_array($column, $cols)) {
                $this->changeColumn('turba_objects', $column, 'string', ['limit' => $limit]);
            }
        }
    }
}
