<?php

declare(strict_types=1);
/**
 * Turba configuration class factory
 *
 * Creates instances of the TurbaConfig class.
 *
 * Old pattern: globals $conf; $somethingDetail = $conf['something']['detail']; *
 * New pattern: $config = $injector->get(TurbaConfig::class); $somethingDetail = $config->get('something.detail');
 *
 * Prefer DI over instantiating $config in your code.
 */

namespace Horde\Turba;

use Horde\Core\Config\ConfigLoader;
use Horde\Injector\Injector;

class TurbaConfigFactory
{
    public function __construct(private Injector $injector) {}

    public function create(): TurbaConfig
    {
        $state = $this->injector->get(ConfigLoader::class)->load('turba');
        return new TurbaConfig($state->toArray());
    }
}
