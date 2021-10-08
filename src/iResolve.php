<?php

declare(strict_types=1);

namespace FluencePrototype\Dispatcher;

use FluencePrototype\Http\Messages\iResponse;

/**
 * Interface iResolve
 * @package FluencePrototype\Dispatcher
 */
interface iResolve
{

    /**
     * @return iResponse|null
     */
    public function resolve(): null|iResponse;

}