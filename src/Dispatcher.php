<?php

declare(strict_types=1);

namespace FluencePrototype\Dispatcher;

use FluencePrototype\Auth\AcceptRoles;
use FluencePrototype\Auth\AuthenticationService;
use FluencePrototype\Broadcast\BroadcastService;
use FluencePrototype\Http\Messages\iRequest;
use FluencePrototype\Http\Messages\iResponse;
use FluencePrototype\Http\Messages\MethodNotAllowedException;
use FluencePrototype\Http\Messages\Request\FormService;
use FluencePrototype\Http\Messages\Request\QueryParametersService;
use FluencePrototype\Http\Messages\Request\RestDataService;
use FluencePrototype\Http\Methods\iDelete;
use FluencePrototype\Http\Methods\iGet;
use FluencePrototype\Http\Methods\iPatch;
use FluencePrototype\Http\Methods\iPost;
use FluencePrototype\Http\Methods\iPut;
use FluencePrototype\Http\PathService;
use FluencePrototype\Http\TimeLimit;
use FluencePrototype\Router\iRouteInformation;
use FluencePrototype\Security\PasswordService;
use FluencePrototype\Session\SessionService;
use FluencePrototype\Validation\ValidationService;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use UnhandledMatchError;

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
     * @throws ReflectionException
     */
    private function resolveDependencies(ReflectionClass $reflectionControllerClass): iGet|iPost|iPut|iDelete
    {
        $dependencies = [];

        try {
            if ($controllerConstructor = $reflectionControllerClass->getConstructor()) {
                $dependencyInjectionParameters = $controllerConstructor->getParameters();

                foreach ($dependencyInjectionParameters as $dependencyInjectionParameter) {
                    $dependencyInjectionClassName = $dependencyInjectionParameter->getType()->getName();
                    $reflectionDependencyInjectionClass = new ReflectionClass(objectOrClass: $dependencyInjectionClassName);

                    if ($dependencyInjectionClassName === ParametersService::class) {
                        $dependencies[] = $reflectionDependencyInjectionClass->newInstance($this->request, $this->routeInformation);

                        continue;
                    }

                    if ($dependencyInjectionClassName === QueryParametersService::class) {
                        $dependencies[] = $reflectionDependencyInjectionClass->newInstance($this->request->getQueryParameters());

                        continue;
                    }

                    if ($reflectionDependencyInjectionClass->isInstantiable()) {
                        $dependencies[] = $reflectionDependencyInjectionClass->newInstance();
                    }
                }
            }
        } catch (UnhandledMatchError) {
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

    /**
     * @param ReflectionAttribute[] $attributes
     */
    private function resolveAttributes(array $attributes): void
    {
        foreach ($attributes as $attribute) {
            if (dirname(path: $attribute->getName(), levels: 2) === 'JetBrains') {
                continue;
            }

            $attribute->newInstance();
        }
    }

    /**
     * @inheritDoc
     * @throws ReflectionException
     */
    public function dispatch(): iResponse
    {
        $reflectionControllerClass = new ReflectionClass(objectOrClass: $this->routeInformation->getResource());

        if ($attributes = $reflectionControllerClass->getAttributes(name: AcceptRoles::class)) {
            $acceptRolesAttribute = array_pop(array: $attributes);
            $acceptRolesAttribute->newInstance();
        }

        if ($attributes = $reflectionControllerClass->getAttributes(name: TimeLimit::class)) {
            $timeLimitAttribute = array_pop(array: $attributes);
            $timeLimitAttribute->newInstance();
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