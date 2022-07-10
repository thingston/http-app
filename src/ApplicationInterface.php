<?php

declare(strict_types=1);

namespace Thingston\Http;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Thingston\Http\Router\RouterInterface;

interface ApplicationInterface extends RouterInterface, RequestHandlerInterface
{
    public function pipe(MiddlewareInterface $middleware): self;
    public function run(?ServerRequestInterface $request = null): void;
}
