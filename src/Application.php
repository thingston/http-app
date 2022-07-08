<?php

declare(strict_types=1);

namespace Thingston\Http;

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Uri;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Thingston\Http\Exception\Handler\ExceptionHandler;
use Thingston\Http\Exception\Handler\ExceptionHandlerInterface;
use Thingston\Http\Exception\InternalServerErrorException;
use Thingston\Http\Response\ResponseEmitter;
use Thingston\Http\Response\ResponseEmitterInterface;
use Thingston\Http\Router\RequestHandlerResolver;
use Thingston\Http\Router\RequestHandlerResolverInterface;
use Thingston\Http\Router\RouteCollectionInterface;
use Thingston\Http\Router\RouteInterface;
use Thingston\Http\Router\Router;
use Thingston\Http\Router\RouterInterface;
use Throwable;

final class Application implements ApplicationInterface
{
    /**
     * @var array<string, string>
     */
    private array $server = [];

    /**
     * @param ContainerInterface|null $container
     * @param RouterInterface|null $router
     * @param ServerRequestFactoryInterface|null $serverRequestFactory
     * @param RequestHandlerResolverInterface|null $requestHandlerResolevr
     * @param ResponseEmitterInterface|null $responseEmitter
     * @param ExceptionHandlerInterface|null $exceptionHandler
     * @param array<string, string>|null $server
     */
    public function __construct(
        private ?ContainerInterface $container = null,
        private ?RouterInterface $router = null,
        private ?ServerRequestFactoryInterface $serverRequestFactory = null,
        private ?RequestHandlerResolverInterface $requestHandlerResolevr = null,
        private ?ResponseEmitterInterface $responseEmitter = null,
        private ?ExceptionHandlerInterface $exceptionHandler = null,
        ?array $server = null
    ) {
        $this->container = $container;
        $this->router = $router;
        $this->requestHandlerResolevr = $requestHandlerResolevr;
        $this->responseEmitter = $responseEmitter;
        $this->exceptionHandler = $exceptionHandler;
        $this->serverRequestFactory = $serverRequestFactory;
        $this->server = $server ?? $_SERVER;

        $this->assertServerGlobals('REQUEST_METHOD');
        $this->assertServerGlobals('HTTP_HOST');
        $this->assertServerGlobals('REQUEST_URI');
    }

    public function run(?ServerRequestInterface $request = null): void
    {
        if (null === $request) {
            $request = $this->getServerRequest();
        }

        try {
            $route = $this->getRouter()->match($request);
            $response = $this->getRequestHandlerResolver()->resolve($route)->handle($request);
        } catch (Throwable $exception) {
            $response = $this->getExceptionHandler()->handle($request, $exception);
        }

        $this->getResponseEmitter()->emit($response);
    }

    private function getServerRequest(): ServerRequestInterface
    {
        return $this->getServerRequestFactory()
            ->createServerRequest($this->getRequestMethod(), $this->getRequestUri(), $this->server);
    }

    private function getRequestMethod(): string
    {
        return $this->server['REQUEST_METHOD'];
    }

    private function resolveInstance(string $name, string $key): mixed
    {
        if (isset($this->$name)) {
            return $this->$name;
        }

        if (null === $this->container || false === $this->container->has($key)) {
            return null;
        }

        return $this->container->get($key);
    }

    private function getServerRequestFactory(): ServerRequestFactoryInterface
    {
        $type = ServerRequestFactoryInterface::class;
        $instance = $this->resolveInstance('serverRequestFactory', $type);

        if (is_object($instance) && is_a($instance, $type)) {
            return $this->serverRequestFactory = $instance;
        }

        return $this->serverRequestFactory = new HttpFactory();
    }

    private function getRequestUri(): UriInterface
    {
        return new Uri(sprintf(
            '%s://%s%s',
            isset($this->server['HTTPS']) && $this->server['HTTPS'] === 'on' ? 'https' : 'http',
            $this->server['HTTP_HOST'],
            $this->server['REQUEST_URI']
        ));
    }

    private function assertServerGlobals(string $element): void
    {
        if (false === isset($this->server[$element])) {
            throw new InternalServerErrorException(
                sprintf('Element %s of global variable $_SERVER is not defined.', $element)
            );
        }
    }

    private function getRequestHandlerResolver(): RequestHandlerResolverInterface
    {
        $type = RequestHandlerResolverInterface::class;
        $instance = $this->resolveInstance('requestHandlerResolevr', $type);

        if (is_object($instance) && is_a($instance, $type)) {
            return $this->requestHandlerResolevr = $instance;
        }

        return $this->requestHandlerResolevr = new RequestHandlerResolver($this->container);
    }

    private function getRouter(): RouterInterface
    {
        $type = RouterInterface::class;
        $instance = $this->resolveInstance('router', $type);

        if (is_object($instance) && is_a($instance, $type)) {
            return $this->router = $instance;
        }

        return $this->router = new Router();
    }

    private function getExceptionHandler(): ExceptionHandlerInterface
    {
        $type = ExceptionHandlerInterface::class;
        $instance = $this->resolveInstance('exceptionHandler', $type);

        if (is_object($instance) && is_a($instance, $type)) {
            return $this->exceptionHandler = $instance;
        }

        return $this->exceptionHandler = new ExceptionHandler();
    }

    private function getResponseEmitter(): ResponseEmitterInterface
    {
        $type = ResponseEmitterInterface::class;
        $instance = $this->resolveInstance('exceptionHandler', $type);

        if (is_object($instance) && is_a($instance, $type)) {
            return $this->responseEmitter = $instance;
        }

        return $this->responseEmitter = new ResponseEmitter();
    }

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
