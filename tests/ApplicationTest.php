<?php

declare(strict_types=1);

namespace Thingston\Tests\Http;

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Thingston\Http\Application;
use Thingston\Http\ApplicationSettings;
use Thingston\Http\Exception\Handler\ExceptionHandler;
use Thingston\Http\Exception\HttpExceptionInterface;
use Thingston\Http\Exception\InternalServerErrorException;
use Thingston\Http\Response\ResponseEmitter;
use Thingston\Http\Router\RequestHandlerResolver;
use Thingston\Http\Router\Route;
use Thingston\Http\Router\RouteCollectionInterface;
use Thingston\Http\Router\RouteInterface;
use Thingston\Http\Router\Router;
use Thingston\Http\Router\RouterInterface;
use Thingston\Log\LogManager;
use Thingston\Settings\Settings;

final class ApplicationTest extends TestCase
{
    public function testInvalidTimezoneType(): void
    {
        $this->expectException(HttpExceptionInterface::class);

        new Application(
            settings: new ApplicationSettings([
                ApplicationSettings::TIMEZONE => 123,
            ])
        );
    }

    public function testInvalidTimezoneIdentifier(): void
    {
        $this->expectException(HttpExceptionInterface::class);

        new Application(
            settings: new ApplicationSettings([
                ApplicationSettings::TIMEZONE => '123',
            ])
        );
    }

    public function testDefaultTimezone(): void
    {
        new Application(
            settings: new Settings(),
            server: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_HOST' => 'example.org',
                'REQUEST_URI' => '/',
            ]
        );

        $this->assertSame('UTC', date_default_timezone_get());
    }

    public function testRun(): void
    {
        $application = new Application(server: [
            'REQUEST_METHOD' => 'GET',
            'HTTP_HOST' => 'example.org',
            'REQUEST_URI' => '/',
        ]);

        $application->get('/', 'home', function () {
            return new Response(200, [], 'It works!');
        });

        ob_start();

        $application->run();
        $this->assertSame('It works!', ob_get_contents());

        ob_end_clean();
    }

    public function testPipe(): void
    {
        $application = new Application(server: [
            'REQUEST_METHOD' => 'GET',
            'HTTP_HOST' => 'example.org',
            'REQUEST_URI' => '/',
        ]);

        $application->get('/', 'home', function () {
            return new Response(200, [], 'It works');
        });

        $application->pipe(new TestMiddleware(' with Middleware!'));

        ob_start();

        $application->run();
        $this->assertSame('It works with Middleware!', ob_get_contents());

        ob_end_clean();
    }

    public function testContainerDependencies(): void
    {
        $application = new Application(
            container: new SimpleContainer(),
            server: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_HOST' => 'example.org',
                'REQUEST_URI' => '/',
            ]
        );

        $application->get('/', 'home', function () {
            return new Response(200, [], 'It works!');
        });

        ob_start();

        $application->run();
        $this->assertSame('It works!', ob_get_contents());

        ob_end_clean();
    }

    public function testConstructArguments(): void
    {
        $application = new Application(
            settings: new ApplicationSettings(),
            router: new Router(),
            serverRequestFactory: new HttpFactory(),
            requestHandlerResolevr: new RequestHandlerResolver(),
            responseEmitter: new ResponseEmitter(),
            server: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_HOST' => 'example.org',
                'REQUEST_URI' => '/',
            ]
        );

        $application->get('/', 'home', function () {
            return new Response(200, [], 'It works!');
        });

        ob_start();

        $application->run();
        $this->assertSame('It works!', ob_get_contents());

        ob_end_clean();
    }

    public function testInvalidServerGlobals(): void
    {
        $this->expectException(InternalServerErrorException::class);
        new Application(server: []);
    }

    public function testNotFound(): void
    {
        $application = new Application(server: [
            'REQUEST_METHOD' => 'GET',
            'HTTP_HOST' => 'example.org',
            'REQUEST_URI' => '/',
        ]);

        ob_start();

        $application->run();
        $this->assertStringContainsString('not found', (string) ob_get_contents());

        ob_end_clean();
    }

    public function testNotFoundWithExceptionHandler(): void
    {
        $application = new Application(
            exceptionHandler: new ExceptionHandler(),
            server: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_HOST' => 'example.org',
                'REQUEST_URI' => '/',
            ]
        );

        ob_start();

        $application->run();
        $this->assertStringContainsString('not found', (string) ob_get_contents());

        ob_end_clean();
    }

    public function testNotFoundWithLogger(): void
    {
        $application = new Application(
            logger: new LogManager(),
            server: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_HOST' => 'example.org',
                'REQUEST_URI' => '/',
            ]
        );

        ob_start();

        $application->run();
        $this->assertStringContainsString('not found', (string) ob_get_contents());

        ob_end_clean();
    }

    public function testNotFoundWithContainer(): void
    {
        $application = new Application(
            container: new SimpleContainer(),
            server: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_HOST' => 'example.org',
                'REQUEST_URI' => '/',
            ]
        );

        ob_start();

        $application->run();
        $this->assertStringContainsString('not found', (string) ob_get_contents());

        ob_end_clean();
    }

    public function testGivenExceptionHandler(): void
    {
        $application = new Application(
            exceptionHandler: new ExceptionHandler(),
            server: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_HOST' => 'example.org',
                'REQUEST_URI' => '/',
            ]
        );

        ob_start();

        $application->run();
        $this->assertStringContainsString('not found', (string) ob_get_contents());

        ob_end_clean();
    }

    public function testRouterInterface(): void
    {
        $application = new Application(server: [
            'REQUEST_METHOD' => 'GET',
            'HTTP_HOST' => 'example.org',
            'REQUEST_URI' => '/',
        ]);

        $this->assertInstanceOf(RouteInterface::class, $application->map(['GET'], '/', 'home', 'handler'));
        $this->assertInstanceOf(RouteInterface::class, $application->match(new ServerRequest('GET', '/')));
        $this->assertInstanceOf(RouteInterface::class, $application->get('/', 'home', 'handler'));
        $this->assertInstanceOf(RouteInterface::class, $application->post('/', 'home', 'handler'));
        $this->assertInstanceOf(RouteInterface::class, $application->put('/', 'home', 'handler'));
        $this->assertInstanceOf(RouteInterface::class, $application->patch('/', 'home', 'handler'));
        $this->assertInstanceOf(RouteInterface::class, $application->delete('/', 'home', 'handler'));
        $this->assertInstanceOf(RouteInterface::class, $application->head('/', 'home', 'handler'));
        $this->assertInstanceOf(RouteInterface::class, $application->options('/', 'home', 'handler'));
        $this->assertInstanceOf(
            RouterInterface::class,
            $application->addRoute(new Route('GET', '/', 'home', 'handler'))
        );
        $this->assertInstanceOf(RouteCollectionInterface::class, $application->getRoutes());
    }
}
