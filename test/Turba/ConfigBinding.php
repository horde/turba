<?php

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (ASL). If you
 * did not receive this file, see http://www.horde.org/licenses/apache.
 *
 * @author    Torben Dannhauer <torben@dannhauer.de>
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/apache ASL
 * @package   Turba
 */

/**
 * Binds Turba and Horde configuration for tests.
 *
 * Horde-wide keys are served by ConfigLoader::load('horde'). Turba keys are
 * served by ConfigLoader::load('turba') and by a TurbaConfig instance.
 */
trait Turba_Test_ConfigBinding
{
    /**
     * @param Horde_Injector|Horde\Injector\Injector $injector
     * @param array<string, array<string, mixed>>   $overlays App name => config tree.
     */
    protected static function bindConfig($injector, array $overlays): void
    {
        $previous = $injector->has(\Horde\Core\Config\ConfigLoader::class)
            ? $injector->getInstance(\Horde\Core\Config\ConfigLoader::class)
            : null;

        $loader = new class($previous, $overlays) {
            /**
             * @param object|null                              $previous
             * @param array<string, array<string, mixed>>      $overlays
             */
            public function __construct(private $previous, private array $overlays) {}

            public function load(string $app, string $file = 'conf.php', bool $withMetadata = false): \Horde\Core\Config\State
            {
                $base = [];
                if (is_object($this->previous) && method_exists($this->previous, 'load')) {
                    $base = $this->previous->load($app, $file, $withMetadata)->toArray();
                }
                if (isset($this->overlays[$app]) && is_array($this->overlays[$app])) {
                    $base = array_replace_recursive($base, $this->overlays[$app]);
                }
                if ($app === 'turba') {
                    return new \Horde\Turba\TurbaConfig($base);
                }

                return new \Horde\Core\Config\State($base);
            }
        };
        $injector->setInstance(\Horde\Core\Config\ConfigLoader::class, $loader);

        if (isset($overlays['turba']) && is_array($overlays['turba'])) {
            $injector->setInstance(
                \Horde\Turba\TurbaConfig::class,
                $loader->load('turba')
            );
        }
    }
}
