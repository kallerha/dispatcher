<?php

declare(strict_types=1);

namespace FluencePrototype\Dispatcher;

use FluencePrototype\Http\Messages\iRequest;
use FluencePrototype\Router\iRouteInformation;

/**
 * Class ParametersService
 * @package FluencePrototype\Dispatcher
 */
class ParametersService
{

    private array $parameters = [];

    /**
     * ParametersService constructor.
     * @param iRequest $request
     * @param iRouteInformation $routeInformation
     */
    public function __construct(iRequest $request, iRouteInformation $routeInformation)
    {
        $requestPathArray = explode(separator: '/', string: $request->getPath());
        $routeCandidatePathArray = explode(separator: '/', string: $routeInformation->getPath());

        for ($i = 0; $i < count(value: $routeCandidatePathArray); $i++) {
            $routeCandidatePathItem = $routeCandidatePathArray[$i];

            if (str_starts_with(haystack: $routeCandidatePathItem, needle: ':')) {
                $this->parameters[substr($routeCandidatePathItem, offset: 1)] = filter_var(value: $requestPathArray[$i], filter: FILTER_SANITIZE_STRING);
            }
        }
    }

    /**
     * @param string $key
     * @return string|null
     */
    public function getParameter(string $key): null|string
    {
        if (isset($this->parameters[$key])) {
            return $this->parameters[$key];
        }

        return null;
    }

}