<?php

declare(strict_types=1);

namespace Thingston\Http;

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Uri;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Log\LoggerInterface;
use Thingston\Http\Exception\Handler\ExceptionHandler;
use Thingston\Http\Exception\Handler\ExceptionHandlerInterface;
use Thingston\Http\Exception\Handler\ExceptionHandlerSettings;
use Thingston\Http\Exception\InternalServerErrorException;
use Thingston\Http\Response\ResponseEmitter;
use Thingston\Http\Response\ResponseEmitterInterface;
use Thingston\Http\Router\RequestHandlerResolver;
use Thingston\Http\Router\RequestHandlerResolverInterface;
use Thingston\Http\Router\RouteDispatchHandler;
use Thingston\Http\Router\Router;
use Thingston\Http\Router\RouterInterface;
use Thingston\Log\LogManager;
use Thingston\Settings\SettingsInterface;
use Throwable;

final class Application implements ApplicationInterface
{
    use ApplicationRouterTrait;

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
     * @param array<MiddlewareInterface> $middlewares
     * @param array<string, string>|null $server
     */
    public function __construct(
        private ?ContainerInterface $container = null,
        private ?SettingsInterface $settings = null,
        private ?RouterInterface $router = null,
        private ?ServerRequestFactoryInterface $serverRequestFactory = null,
        private ?RequestHandlerResolverInterface $requestHandlerResolevr = null,
        private ?ResponseEmitterInterface $responseEmitter = null,
        private ?ExceptionHandlerInterface $exceptionHandler = null,
        private ?LoggerInterface $logger = null,
        private array $middlewares = [],
        ?array $server = null
    ) {
        $this->container = $container;
        $this->router = $router;
        $this->requestHandlerResolevr = $requestHandlerResolevr;
        $this->responseEmitter = $responseEmitter;
        $this->exceptionHandler = $exceptionHandler;
        $this->serverRequestFactory = $serverRequestFactory;
        $this->middlewares = $middlewares;
        $this->server = $server ?? $_SERVER;

        $this->setDefaultTimezone();

        $this->assertServerGlobals('REQUEST_METHOD');
        $this->assertServerGlobals('HTTP_HOST');
        $this->assertServerGlobals('REQUEST_URI');
    }

    public function pipe(MiddlewareInterface $middleware): self
    {
        $this->middlewares[] = $middleware;

        return $this;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return (new RouteDispatchHandler(
            $this->getRequestHandlerResolver(),
            $this->getRouter()->match($request),
            $this->middlewares
        ))->handle($request);
    }

    public function run(?ServerRequestInterface $request = null): void
    {
        if (null === $request) {
            $request = $this->getServerRequest();
        }

        try {
            $response = $this->handle($request);
        } catch (Throwable $exception) {
            $response = $this->getExceptionHandler()->handle($request, $exception);
        }

        $this->getResponseEmitter()->emit($response);
    }

    private function setDefaultTimezone(): void
    {
        $settings = $this->getSettings();

        if (false === $settings->has(ApplicationSettings::TIMEZONE)) {
            date_default_timezone_set('UTC');
            return;
        }

        $timezone = $settings->get(ApplicationSettings::TIMEZONE);

        if (false === is_string($timezone)) {
            throw new InternalServerErrorException('Invalid timezone identifier type.');
        }

        if (false === @date_default_timezone_set($timezone)) {
            throw new InternalServerErrorException('Invalid timezone identifier: ' . $timezone);
        }
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

    private function getSettings(): SettingsInterface
    {
        $type = SettingsInterface::class;
        $instance = $this->resolveInstance('settings', $type);

        if (is_object($instance) && is_a($instance, $type)) {
            return $this->settings = $instance;
        }

        return $this->settings = new ApplicationSettings();
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

    private function getLogger(): LoggerInterface
    {
        $type = LoggerInterface::class;
        $instance = $this->resolveInstance('logger', $type);

        if (is_object($instance) && is_a($instance, $type)) {
            return $this->logger = $instance;
        }

        return $this->logger = new LogManager();
    }

    private function getExceptionHandlerSettings(): SettingsInterface
    {
        $settings = $this->getSettings();

        $hasEnv = $settings->has(ApplicationSettings::ENVIRONMENT);
        $isProd = $hasEnv && ApplicationSettings::ENV_PRODUCTION !== $settings->get(ApplicationSettings::ENVIRONMENT);

        $debug = $hasEnv && false === $isProd;

        return new ExceptionHandlerSettings([
            ExceptionHandlerSettings::DEBUG => $debug,
            ExceptionHandlerSettings::LOG_ERRORS => true,
            ExceptionHandlerSettings::LOG_DETAILS => true === $debug,
        ]);
    }

    private function getExceptionHandler(): ExceptionHandlerInterface
    {
        $type = ExceptionHandlerInterface::class;
        $instance = $this->resolveInstance('exceptionHandler', $type);

        if (is_object($instance) && is_a($instance, $type)) {
            return $this->exceptionHandler = $instance;
        }

        return $this->exceptionHandler = new ExceptionHandler(
            settings: $this->getExceptionHandlerSettings(),
            logger: $this->getLogger()
        );
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
}
