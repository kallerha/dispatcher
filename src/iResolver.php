<?php

declare(strict_types=1);

namespace FluencePrototype\Dispatcher;

/**
 * Interface iResolver
 * @package FluencePrototype\Dispatcher
 */
interface iResolver
{

    /**
     * @return array
     */
    public function getDependencies(): array;

}