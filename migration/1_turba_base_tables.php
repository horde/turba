<?php

/**
 * Create turba base tables
 *
 * Copyright 2010-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (ASL).  If you
 * did not receive this file, see http://www.horde.org/licenses/apache.
 *
 * @author   Michael J. Rubinsky <mrubinsk@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/apache ASL
 * @package  Turba
 */
class TurbaBaseTables extends Horde_Db_Migration_Base
{
    /**
     * Upgrade.
     */
    public function up()
    {
        $tableList = $this->tables();

        if (!in_array('turba_objects', $tableList)) {
            $t = $this->createTable('turba_objects', ['autoincrementKey' => false]);
            $t->column('object_id', 'string', ['limit' => 32, 'null' => false]);
            $t->column('owner_id', 'string', ['limit' => 255, 'null' => false]);
            $t->column('object_type', 'string', ['limit' => 255, 'default' => 'Object', 'null' => false]);
            $t->column('object_uid', 'string', ['limit' => 255]);
            $t->column('object_members', 'text');
            $t->column('object_firstname', 'string', ['limit' => 255]);
            $t->column('object_lastname', 'string', ['limit' => 255]);
            $t->column('object_middlenames', 'string', ['limit' => 255]);
            $t->column('object_nameprefix', 'string', ['limit' => 32]);
            $t->column('object_namesuffix', 'string', ['limit' => 32]);
            $t->column('object_alias', 'string', ['limit' => 32]);
            $t->column('object_photo', 'binary');
            $t->column('object_phototype', 'string', ['limit' => 10]);
            $t->column('object_bday', 'string', ['limit' => 10]);
            $t->column('object_homestreet', 'string', ['limit' => 255]);
            $t->column('object_homepob', 'string', ['limit' => 10]);
            $t->column('object_homecity', 'string', ['limit' => 255]);
            $t->column('object_homeprovince', 'string', ['limit' => 255]);
            $t->column('object_homepostalcode', 'string', ['limit' => 10]);
            $t->column('object_homecountry', 'string', ['limit' => 255]);
            $t->column('object_workstreet', 'string', ['limit' => 255]);
            $t->column('object_workpob', 'string', ['limit' => 10]);
            $t->column('object_workcity', 'string', ['limit' => 255]);
            $t->column('object_workprovince', 'string', ['limit' => 255]);
            $t->column('object_workpostalcode', 'string', ['limit' => 10]);
            $t->column('object_workcountry', 'string', ['limit' => 255]);
            $t->column('object_tz', 'string', ['limit' => 32]);
            $t->column('object_geo', 'string', ['limit' => 255]);
            $t->column('object_email', 'string', ['limit' => 255]);
            $t->column('object_homephone', 'string', ['limit' => 25]);
            $t->column('object_workphone', 'string', ['limit' => 25]);
            $t->column('object_cellphone', 'string', ['limit' => 25]);
            $t->column('object_fax', 'string', ['limit' => 25]);
            $t->column('object_pager', 'string', ['limit' => 25]);
            $t->column('object_title', 'string', ['limit' => 255]);
            $t->column('object_role', 'string', ['limit' => 255]);
            $t->column('object_logo', 'binary');
            $t->column('object_logotype', 'string', ['limit' => 10]);
            $t->column('object_company', 'string', ['limit' => 255]);
            $t->column('object_category', 'string', ['limit' => 80]);
            $t->column('object_notes', 'text');
            $t->column('object_url', 'string', ['limit' => 255]);
            $t->column('object_freebusyurl', 'string', ['limit' => 255]);
            $t->column('object_pgppublickey', 'text');
            $t->column('object_smimepublickey', 'text');
            $t->primaryKey(['object_id']);
            $t->end();


            $this->addIndex('turba_objects', ['owner_id']);
            $this->addIndex('turba_objects', ['object_email']);
            $this->addIndex('turba_objects', ['object_firstname']);
            $this->addIndex('turba_objects', ['object_lastname']);
        }

        if (!in_array('turba_shares', $tableList)) {
            $t = $this->createTable('turba_shares', ['autoincrementKey' => false]);
            $t->column('share_id', 'integer', ['null' => false]);
            $t->column('share_name', 'string', ['limit' => 255, 'null' => false]);
            $t->column('share_owner', 'string', ['limit' => 255, 'null' => false]);
            $t->column('share_flags', 'integer', ['default' => 0, 'null' => false]);
            $t->column('perm_creator', 'integer', ['default' => 0, 'null' => false]);
            $t->column('perm_default', 'integer', ['default' => 0, 'null' => false]);
            $t->column('perm_guest', 'integer', ['default' => 0, 'null' => false]);
            $t->column('attribute_name', 'string', ['limit' => 255, 'null' => false]);
            $t->column('attribute_desc', 'string', ['limit' => 255]);
            $t->column('attribute_params', 'text');
            $t->primaryKey(['share_id']);
            $t->end();

            $this->addIndex('turba_shares', ['share_name']);
            $this->addIndex('turba_shares', ['share_owner']);
            $this->addIndex('turba_shares', ['perm_creator']);
            $this->addIndex('turba_shares', ['perm_default']);
            $this->addIndex('turba_shares', ['perm_guest']);
        }

        if (!in_array('turba_shares_groups', $tableList)) {
            $t = $this->createTable('turba_shares_groups');
            $t->column('share_id', 'integer', ['null' => false]);
            $t->column('group_uid', 'string', ['limit' => 255, 'null' => false]);
            $t->column('perm', 'integer', ['null' => false]);
            $t->end();

            $this->addIndex('turba_shares_groups', ['share_id']);
            $this->addIndex('turba_shares_groups', ['group_uid']);
            $this->addIndex('turba_shares_groups', ['perm']);
        }

        if (!in_array('turba_shares_users', $tableList)) {
            $t = $this->createTable('turba_shares_users');

            $t->column('share_id', 'integer', ['null' => false]);
            $t->column('user_uid', 'string', ['limit' => 255, 'null' => false]);
            $t->column('perm', 'integer', ['null' => false]);
            $t->end();

            $this->addIndex('turba_shares_users', ['share_id']);
            $this->addIndex('turba_shares_users', ['user_uid']);
            $this->addIndex('turba_shares_users', ['perm']);
        }
    }

    /**
     * Downgrade to 0
     */
    public function down()
    {
        $this->dropTable('turba_objects');
        $this->dropTable('turba_shares');
        $this->dropTable('turba_shares_users');
        $this->dropTable('turba_shares_groups');
    }

}
