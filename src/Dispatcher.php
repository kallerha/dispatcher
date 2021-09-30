<?php

declare(strict_types=1);

namespace FluencePrototype\Dispatcher;

use FluencePrototype\Auth\AcceptRoles;
use FluencePrototype\Auth\AuthenticationService;
use FluencePrototype\Broadcast\BroadcastService;
use FluencePrototype\Http\Messages\iRequest;
use FluencePrototype\Http\Messages\iResponse;
use FluencePrototype\Http\Messages\MethodNotAllowedException;
use FluencePrototype\Http\Messages\NotFoundException;
use FluencePrototype\Http\Messages\Request\FormService;
use FluencePrototype\Http\Messages\Request\QueryParametersService;
use FluencePrototype\Http\Methods\iGet;
use FluencePrototype\Http\Methods\iPost;
use FluencePrototype\Http\Methods\iPut;
use FluencePrototype\Http\Methods\iPatch;
use FluencePrototype\Http\Methods\iDelete;
use FluencePrototype\Http\PathService;
use FluencePrototype\Router\iRouteInformation;
use FluencePrototype\Security\PasswordService;
use FluencePrototype\Session\SessionService;
use FluencePrototype\Validation\ValidationService;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

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
                    case AuthenticationService::class:
                    case BroadcastService::class:
                    case FormService::class:
                    case PasswordService::class:
                    case PathService::class:
                    case SessionService::class:
                    case ValidationService::class:
                        $dependencies[] = $reflectionDependencyInjectionClass->newInstance();

                        break;
                    case ParametersService::class:
                        $dependencies[] = $reflectionDependencyInjectionClass->newInstance($this->request, $this->routeInformation);

                        break;
                    case QueryParametersService::class:
                        $dependencies[] = $reflectionDependencyInjectionClass->newInstance($this->request->getQueryParameters());

                        break;
                }
            }

            if ($attributes = $reflectionControllerClass->getAttributes(name: Resolver::class)) {
                foreach ($attributes as $attribute) {
                    /** @var Resolver $resolver */
                    $resolver = $attribute->newInstance();
                    $dependencies = array_merge($dependencies, $resolver->getResolver()->getDependencies());
                }
            }

            return $reflectionControllerClass->newInstanceArgs(args: $dependencies);
        }

        return null;
    }


    /**
     * @param ReflectionAttribute[] $attributes
     */
    private function resolveAttributes(array $attributes): void
    {
        foreach ($attributes as $attribute) {
            $attribute->newInstance();
        }
    }

    /**
     * @inheritDoc
     * @throws ReflectionException|InvalidDependencyException
     */
    public function dispatch(): iResponse
    {
        $reflectionControllerClass = new ReflectionClass(objectOrClass: $this->routeInformation->getResource());

        if ($attributes = $reflectionControllerClass->getAttributes(name: AcceptRoles::class)) {
            $acceptRolesAttribute = array_pop(array: $attributes);
            $acceptRolesAttribute->newInstance();
        }

        if (!$controller = $this->resolveDependencies(reflectionControllerClass: $reflectionControllerClass)) {
            $controller = $reflectionControllerClass->newInstance();
        }

        if ($controller instanceof iResolve) {
            if ($controller->resolve() !== null) {
                return $controller->resolve();
            }
        }

        if ($this->request->getMethod() === 'get' && $controller instanceof iGet) {
            $this->resolveAttributes(attributes: (new ReflectionMethod(objectOrMethod: $this->routeInformation->getResource(), method: 'get'))->getAttributes());

            return $controller->get();
        }

        if ($this->request->getMethod() === 'post' && $controller instanceof iPost) {
            $this->resolveAttributes(attributes: (new ReflectionMethod(objectOrMethod: $this->routeInformation->getResource(), method: 'post'))->getAttributes());

            return $controller->post();
        }

        if ($this->request->getMethod() === 'put' && $controller instanceof iPut) {
            $this->resolveAttributes(attributes: (new ReflectionMethod(objectOrMethod: $this->routeInformation->getResource(), method: 'put'))->getAttributes());

            return $controller->put();
        }

        if ($this->request->getMethod() === 'patch' && $controller instanceof iPatch) {
            $this->resolveAttributes(attributes: (new ReflectionMethod(objectOrMethod: $this->routeInformation->getResource(), method: 'patch'))->getAttributes());

            return $controller->patch();
        }

        if ($this->request->getMethod() === 'delete' && $controller instanceof iDelete) {
            $this->resolveAttributes(attributes: (new ReflectionMethod(objectOrMethod: $this->routeInformation->getResource(), method: 'delete'))->getAttributes());

            return $controller->delete();
        }

        throw new MethodNotAllowedException();
    }

}