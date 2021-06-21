<?php

declare(strict_types=1);

namespace FluencePrototype\Dispatcher;

use FluencePrototype\Http\Messages\iRequest;
use FluencePrototype\Http\Messages\iResponse;
use FluencePrototype\Http\Messages\Request\QueryParametersService;
use FluencePrototype\Http\Messages\Request\FormService;
use FluencePrototype\Http\Messages\MethodNotAllowedException;
use FluencePrototype\Http\Messages\NotFoundException;
use FluencePrototype\Http\Methods\iGet;
use FluencePrototype\Http\Methods\iPost;
use FluencePrototype\Http\PathService;
use FluencePrototype\Router\iRouteInformation;
use ReflectionClass;
use ReflectionException;

/**
 * Class Dispatcher
 * @package FluencePrototype\Dispatcher
 */
class Dispatcher implements iDispatcher
{

    private iRequest $request;
    private iRouteInformation $routeInformation;

    /**
     * @inheritDoc
     */
    public function __construct(iRequest $request, iRouteInformation $routeInformation)
    {
        $this->request = $request;
        $this->routeInformation = $routeInformation;
    }

    /**
     * @throws ReflectionException|InvalidDependencyException
     */
    private function resolveDependencies(ReflectionClass $reflectionControllerClass): iGet|iPost|null
    {
        if ($controllerConstructor = $reflectionControllerClass->getConstructor()) {
            $dependencyInjectionParameters = $controllerConstructor->getParameters();
            $dependencies = [];

            foreach ($dependencyInjectionParameters as $dependencyInjectionParameter) {
                $dependencyInjectionClassName = $dependencyInjectionParameter->getType()->getName();
                $reflectionDependencyInjectionClass = new ReflectionClass(objectOrClass: $dependencyInjectionClassName);

                switch ($dependencyInjectionClassName) {
                    case FormService::class:
                    case PathService::class:
                        $dependencies[] = $reflectionDependencyInjectionClass->newInstance();

                        break;
                    case ParametersService::class:
                        $dependencies[] = $reflectionDependencyInjectionClass->newInstance($this->request, $this->routeInformation);

                        break;
                    case QueryParametersService::class:
                        $dependencies[] = $reflectionDependencyInjectionClass->newInstance($this->request->getQueryParameters());

                        break;
                    default:
                        throw new InvalidDependencyException();
                }
            }

            return $reflectionControllerClass->newInstanceArgs(args: $dependencies);
        }

        return null;
    }

    /**
     * @inheritDoc
     * @throws ReflectionException|InvalidDependencyException
     */
    public function dispatch(): iResponse
    {
        $reflectionControllerClass = new ReflectionClass(objectOrClass: $this->routeInformation->getResource());

        if (!$controller = $this->resolveDependencies(reflectionControllerClass: $reflectionControllerClass)) {
            $controller = $reflectionControllerClass->newInstance();
        }

        if ($controller instanceof iResolve) {
            if (!$controller->resolve()) {
                throw new NotFoundException();
            }
        }

        if ($this->request->getMethod() === 'get' && $controller instanceof iGet) {
            return $controller->get();
        }

        if ($this->request->getMethod() === 'post' && $controller instanceof iPost) {
            return $controller->post();
        }

        throw new MethodNotAllowedException();
    }

}