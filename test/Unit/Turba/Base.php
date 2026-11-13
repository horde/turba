<?php

/**
 * Test the core Turba class with various backends.
 *
 * PHP version 5
 *
 * @category   Horde
 * @package    Turba
 * @subpackage UnitTests
 * @author     Gunnar Wrobel <wrobel@pardus.de>
 * @link       http://www.horde.org/apps/turba
 * @license    http://www.horde.org/licenses/apache Apache-like
 */

/**
 * Prepare the test setup.
 */

/**
 * Test the core Turba class with various backends.
 *
 * Copyright 2011-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (ASL). If you
 * did not receive this file, see http://www.horde.org/licenses/apache.
 *
 * @category   Horde
 * @package    Turba
 * @subpackage UnitTests
 * @author     Gunnar Wrobel <wrobel@pardus.de>
 * @link       http://www.horde.org/apps/turba
 * @license    http://www.horde.org/licenses/apache Apache-like
 * @coversNothing
 */

namespace Horde\Turba\Test\Unit;

use \Turba;
use Horde_Secret;
use Horde_Test_Setup;
use Horde_Perms;
use Horde_Share_Base;

class Turba_Unit_Turba_Base extends TestBase
{
    /**
     * The test setup.
     *
     * @var Horde_Test_Setup
     */
    public static $setup;

    /**
     * The default share name expected to be used.
     *
     * @var string
     */
    protected $default_name = 'Address book of test@example.com';

    public static function setUpBeforeClass(): void
    {
        self::createBasicTurbaSetup(self::$setup);
        parent::setUpBeforeClass();
    }

    public static function tearDownAfterClass(): void
    {
        self::tearDownBasicTurbaSetup();
        self::tearDownShares();
        parent::tearDownAfterClass();
    }

    public function setUp(): void
    {
        if (!class_exists('Horde_Secret_Cbc', false)) {
            class_alias('Horde_Secret', 'Horde_Secret_Cbc');
        }
        $error = self::$setup->getError();
        if (!empty($error)) {
            $this->markTestSkipped($error);
        }
    }

    public function tearDown(): void
    {
        $turba_shares = $GLOBALS['injector']->getInstance('Turba_Shares');
        foreach ($turba_shares->listShares('test@example.com') as $share) {
            $turba_shares->removeShare($share);
        }
        $GLOBALS['injector']->setInstance('Turba_Factory_Addressbooks', null);
        parent::tearDown();
    }

    public function testCreateDefaultShare()
    {
        $turba_shares = $GLOBALS['injector']->getInstance('Turba_Shares');
        $this->bindHordeConfig(['share' => ['auto_create' => true]]);
        Turba::getConfigFromShares(['test' => ['use_shares' => true]]);
        $this->assertEquals(
            1,
            count($turba_shares->listShares('test@example.com'))
        );
    }

    public function testDefaultShareName()
    {
        $turba_shares = $GLOBALS['injector']->getInstance('Turba_Shares');
        $this->bindHordeConfig(['share' => ['auto_create' => true]]);
        Turba::getConfigFromShares(['test' => ['use_shares' => true]]);
        $shares = $turba_shares->listShares('test@example.com');
        $default = array_pop($shares);
        $this->assertInstanceOf('Horde_Share_Object', $default);
        $this->assertEquals(
            $this->default_name,
            $default->get('name')
        );
    }

    public function testNoAutoCreate()
    {
        $turba_shares = $GLOBALS['injector']->getInstance('Turba_Shares');
        $this->bindHordeConfig(['share' => ['auto_create' => false]]);
        Turba::getConfigFromShares(['test' => ['use_shares' => true]]);
        $this->assertEquals(
            0,
            count($turba_shares->listShares('test@example.com'))
        );
    }

    public function testDefaultShareDeletePermission()
    {
        $turba_shares = $GLOBALS['injector']->getInstance('Turba_Shares');
        $this->bindHordeConfig(['share' => ['auto_create' => true]]);
        Turba::getConfigFromShares(['test' => ['use_shares' => true]]);
        $shares = $turba_shares->listShares('test@example.com');
        $default = array_pop($shares);
        $this->assertInstanceOf('Horde_Share_Object', $default);
        $this->assertTrue(
            $default->hasPermission(
                $GLOBALS['registry']->getAuth(),
                Horde_Perms::DELETE
            )
        );
    }

    /**
     * @param array<string, mixed> $overlay Values merged over the Horde config.
     */
    private function bindHordeConfig(array $overlay): void
    {
        self::bindConfig($GLOBALS['injector'], ['horde' => $overlay]);
    }

}
