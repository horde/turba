<?php

declare(strict_types=1);
/**
 * Turba configuration class
 *
 * Provides access to the Turba configuration settings.
 *
 * Old pattern: globals $conf; $somethingDetail = $conf['something']['detail']; *
 * New pattern: $config = $injector->get(TurbaConfig::class); $somethingDetail = $config->get('something.detail');
 *
 * Prefer DI over instantiating $config in your code.
 */

namespace Horde\Turba;

use Horde\Core\Config\State;
use Horde\Injector\Attribute\Factory;

#[Factory(factory: TurbaConfigFactory::class, method: 'create')]
class TurbaConfig extends State {}
