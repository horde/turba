<?php
/**
 * Copyright 2020 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (ASL).  If you
 * did not receive this file, see http://www.horde.org/licenses/apache.
 *
 * @author   Jan Schneider <jan@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/apache ASL
 * @package  Turba
 */

/**
 * Fixes the type of the parents column.
 *
 * @author   Jan Schneider <jan@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/apache ASL
 * @package  Turba
 */
class TurbaUpgradePhototypes extends Horde_Db_Migration_Base
{
    /**
     * Upgrade.
     */
    public function up()
    {
        $this->changeColumn('turba_objects', 'object_phototype', 'string', array('limit' => 127));
        $this->changeColumn('turba_objects', 'object_logotype', 'string', array('limit' => 127));
    }

    /**
     * Downgrade
     */
    public function down()
    {
        $this->changeColumn('turba_objects', 'object_phototype', 'string', array('limit' => 10));
        $this->changeColumn('turba_objects', 'object_logotype', 'string', array('limit' => 10));
    }
}
