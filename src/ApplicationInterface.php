<?php

declare(strict_types=1);

namespace Thingston\Http;

use Psr\Http\Message\ServerRequestInterface;
use Thingston\Http\Router\RouterInterface;

interface ApplicationInterface extends RouterInterface
{
    public function run(?ServerRequestInterface $request = null): void;
}
