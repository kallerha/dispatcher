<?php

declare(strict_types=1);

namespace FluencePrototype\Dispatcher;

use FluencePrototype\Auth\AcceptRoles;
use FluencePrototype\Http\Messages\iRequest;
use FluencePrototype\Http\Messages\iResponse;
use FluencePrototype\Http\Messages\MethodNotAllowedException;
use FluencePrototype\Http\Messages\Request\QueryParametersService;
use FluencePrototype\Http\Methods\iDelete;
use FluencePrototype\Http\Methods\iGet;
use FluencePrototype\Http\Methods\iPatch;
use FluencePrototype\Http\Methods\iPost;
use FluencePrototype\Http\Methods\iPut;
use FluencePrototype\Http\TimeLimit;
use FluencePrototype\Router\iRouteInformation;
use Psalm\Node\Expr\VirtualAssignRef;
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
    private function resolveDependencies(ReflectionClass $reflectionControllerClass): iGet|iPost|iPut|iDelete|iPatch
    {
        $dependencies = [];
        $dependencyNames = [];

        if ($attributes = $reflectionControllerClass->getAttributes(name: Resolver::class)) {
            foreach ($attributes as $attribute) {
                /** @var Resolver $resolver */
                $resolver = $attribute->newInstance();
                $dependencyNames = array_merge($dependencyNames, array_keys($resolver->getResolver()->getDependencies()));
                $dependencies = array_merge($dependencies, $resolver->getResolver()->getDependencies());
            }
        }

        try {
            if ($controllerConstructor = $reflectionControllerClass->getConstructor()) {
                $dependencyInjectionParameters = $controllerConstructor->getParameters();

                foreach ($dependencyInjectionParameters as $dependencyInjectionParameter) {
                    $dependencyInjectionClassName = $dependencyInjectionParameter->getType()->getName();
                    $parameterName = $dependencyInjectionParameter->getName();

                    if (in_array($dependencyInjectionParameter->getName(), $dependencyNames, true)) {
                        continue;
                    }

                    $reflectionDependencyInjectionClass = new ReflectionClass(objectOrClass: $dependencyInjectionClassName);

                    if ($dependencyInjectionClassName === ParametersService::class) {
                        $dependencies[$parameterName] = $reflectionDependencyInjectionClass->newInstance($this->request, $this->routeInformation);

                        continue;
                    }

                    if ($dependencyInjectionClassName === QueryParametersService::class) {
                        $dependencies[$parameterName] = $reflectionDependencyInjectionClass->newInstance($this->request->getQueryParameters());

                        continue;
                    }

                    if ($reflectionDependencyInjectionClass->isInstantiable()) {
                        $dependencies[$parameterName] = $reflectionDependencyInjectionClass->newInstance();
                    }
                }
            }
        } catch (UnhandledMatchError) {
        }

        $finalDependencies = [];

        foreach ($reflectionControllerClass->getConstructor()->getParameters() as $parameter) {
            $finalDependencies[$parameter->getName()] = $dependencies[$parameter->getName()];
        }

        return $reflectionControllerClass->newInstanceArgs(args: array_values($finalDependencies));
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
