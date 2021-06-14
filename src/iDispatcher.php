<?php

declare(strict_types=1);

namespace FluencePrototype\Dispatcher;

use FluencePrototype\Http\Messages\iRequest;
use FluencePrototype\Http\Messages\iResponse;
use FluencePrototype\Http\Messages\MethodNotAllowedException;
use FluencePrototype\Http\Messages\NotFoundException;
use FluencePrototype\Router\iRouteInformation;

/**
 * Interface iDispatcher
 * @package FluencePrototype\Dispatcher
 */
interface iDispatcher
{

    /**
     * iDispatcher constructor.
     * @param iRequest $request
     * @param iRouteInformation $routeInformation
     */
    public function __construct(iRequest $request, iRouteInformation $routeInformation);

    /**
     * @return iResponse
     * @throws MethodNotAllowedException|NotFoundException
     */
    public function dispatch(): iResponse;

}