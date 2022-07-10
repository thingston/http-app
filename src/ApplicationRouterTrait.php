<?php

declare(strict_types=1);

namespace Thingston\Http;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Thingston\Http\Router\RouteCollectionInterface;
use Thingston\Http\Router\RouteInterface;
use Thingston\Http\Router\RouterInterface;

trait ApplicationRouterTrait
{
    public function addRoute(RouteInterface $route): RouterInterface
    {
        return $this->getRouter()->addRoute($route);
    }

    public function delete(
        string $pattern,
        string $name,
        RequestHandlerInterface|callable|string $handler
    ): RouteInterface {
        return $this->getRouter()->delete($pattern, $name, $handler);
    }

    public function get(
        string $pattern,
        string $name,
        RequestHandlerInterface|callable|string $handler
    ): RouteInterface {
        return $this->getRouter()->get($pattern, $name, $handler);
    }

    public function getRoutes(): RouteCollectionInterface
    {
        return $this->getRouter()->getRoutes();
    }

    public function head(
        string $pattern,
        string $name,
        RequestHandlerInterface|callable|string $handler
    ): RouteInterface {
        return $this->getRouter()->head($pattern, $name, $handler);
    }

    /**
     * @param array<string> $methods
     * @param string $pattern
     * @param string $name
     * @param RequestHandlerInterface|callable|string $handler
     * @return RouteInterface
     */
    public function map(
        array $methods,
        string $pattern,
        string $name,
        RequestHandlerInterface|callable|string $handler
    ): RouteInterface {
        return $this->getRouter()->map($methods, $pattern, $name, $handler);
    }

    public function match(ServerRequestInterface $request): RouteInterface
    {
        return $this->getRouter()->match($request);
    }

    public function options(
        string $pattern,
        string $name,
        RequestHandlerInterface|callable|string $handler
    ): RouteInterface {
        return $this->getRouter()->options($pattern, $name, $handler);
    }

    public function patch(
        string $pattern,
        string $name,
        RequestHandlerInterface|callable|string $handler
    ): RouteInterface {
        return $this->getRouter()->patch($pattern, $name, $handler);
    }

    public function post(
        string $pattern,
        string $name,
        RequestHandlerInterface|callable|string $handler
    ): RouteInterface {
        return $this->getRouter()->post($pattern, $name, $handler);
    }

    public function put(
        string $pattern,
        string $name,
        RequestHandlerInterface|callable|string $handler
    ): RouteInterface {
        return $this->getRouter()->put($pattern, $name, $handler);
    }
}
