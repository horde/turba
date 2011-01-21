#!/usr/bin/env php
<?php
/**
 * This script migrates Turba's share data from the SQL Horde_Share
 * driver to the next-generation SQL Horde_Share driver.
 *
 * It is supposed to run at any time after migrating Turba to the latest DB
 * schema version. The schema migration already migrates the data once, but
 * this script can be used to migrate the data again, e.g. if starting to use
 * the NG driver at a later time.
 */

/* Set up the CLI environment */
require_once dirname(__FILE__) . '/../../lib/Application.php';
Horde_Registry::appInit('turba', array('cli' => true));

require_once dirname(__FILE__) . '/../../migration/3_turba_upgrade_sqlng.php';

$db = $injector->getInstance('Horde_Db_Adapter');
$migration = new TurbaUpgradeSqlng($db);

$delete = $cli->prompt('Delete existing shares from the NEW backend before migrating the OLD backend? This should be done to avoid duplicate entries or primary key collisions in the storage backend from earlier migrations.', array('y' => 'Yes', 'n' => 'No'), 'n');

if ($delete == 'y' || $delete == 'Y') {
    $db->delete('DELETE FROM turba_sharesng');
    $db->delete('DELETE FROM turba_sharesng_users');
    $db->delete('DELETE FROM turba_sharesng_groups');
}

$migration->dataUp();
