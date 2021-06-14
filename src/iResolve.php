<?php

declare(strict_types=1);

namespace FluencePrototype\Dispatcher;

/**
 * Interface iResolve
 * @package FluencePrototype\Dispatcher
 */
interface iResolve
{

    /**
     * @return bool
     */
    public function resolve(): bool;

}