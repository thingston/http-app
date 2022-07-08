<?php

declare(strict_types=1);

namespace Thingston\Tests\Http;

use GuzzleHttp\Psr7\HttpFactory;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Thingston\Http\Exception\Handler\ExceptionHandler;
use Thingston\Http\Exception\Handler\ExceptionHandlerInterface;
use Thingston\Http\Response\ResponseEmitter;
use Thingston\Http\Response\ResponseEmitterInterface;
use Thingston\Http\Router\RequestHandlerResolver;
use Thingston\Http\Router\RequestHandlerResolverInterface;
use Thingston\Http\Router\Router;
use Thingston\Http\Router\RouterInterface;

final class SimpleContainer implements ContainerInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $services = [];

    public function __construct()
    {
        $this->services[RouterInterface::class] = new Router();
        $this->services[ServerRequestFactoryInterface::class] = new HttpFactory();
        $this->services[RequestHandlerResolverInterface::class] = new RequestHandlerResolver($this);
        $this->services[ResponseEmitterInterface::class] = new ResponseEmitter();
        $this->services[ExceptionHandlerInterface::class] = new ExceptionHandler();
    }

    public function get(string $id): mixed
    {
        if (false === isset($this->services[$id])) {
            throw new InvalidArgumentException('Service not found: ' . $id);
        }

        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }
}
