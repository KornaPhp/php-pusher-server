<?php

namespace unit;

use GuzzleHttp;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\RejectedPromise;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Pusher\ApiErrorException;
use Pusher\Pusher;
use Pusher\PusherException;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use RuntimeException;
use stdClass;

class TerminateUserConnectionsUnitTest extends TestCase
{
    private $request_history = [];

    private function mockPusher(array $responses): Pusher
    {
        $mockHandler = new GuzzleHttp\Handler\MockHandler($responses);
        $history = GuzzleHttp\Middleware::history($this->request_history);
        $handlerStack = GuzzleHttp\HandlerStack::create($mockHandler);
        $handlerStack->push($history);
        $httpClient = new GuzzleHttp\Client(['handler' => $handlerStack]);
        return new Pusher("auth-key", "secret", "appid", ['cluster' => 'test1'], $httpClient);
    }

    public function testTerminateUserConections(): void
    {
        $pusher = $this->mockPusher([new Response(200, [], "{}")]);
        $result = $pusher->terminateUserConnections("123");
        self::assertEquals(new stdClass(), $result);
        self::assertEquals(1, count($this->request_history));
        $request = $this->request_history[0]['request'];
        self::assertEquals('api-test1.pusher.com', $request->GetUri()->GetHost());
        self::assertEquals('POST', $request->GetMethod());
        self::assertEquals('/apps/appid/users/123/terminate_connections', $request->GetUri()->GetPath());
    }

    public function testTerminateUserConectionsAsync(): void
    {
        $pusher = $this->mockPusher([new Response(200, [], "{}")]);
        $result = $pusher->terminateUserConnectionsAsync("123")->wait();
        self::assertEquals(new stdClass(), $result);
        self::assertEquals(1, count($this->request_history));
        $request = $this->request_history[0]['request'];
        self::assertEquals('api-test1.pusher.com', $request->GetUri()->GetHost());
        self::assertEquals('POST', $request->GetMethod());
        self::assertEquals('/apps/appid/users/123/terminate_connections', $request->GetUri()->GetPath());
    }

    public function testBadUserId(): void
    {
        $pusher = $this->mockPusher([]);
        $this->expectException(PusherException::class);
        $pusher->terminateUserConnections("");
    }

    public function testBadUserIdAsync(): void
    {
        $pusher = $this->mockPusher([]);
        $this->expectException(PusherException::class);
        $pusher->terminateUserConnectionsAsync("");
    }

    public function testTerminateUserConectionsError(): void
    {
        $pusher = $this->mockPusher([new Response(500, [], "{}")]);
        $this->expectException(ApiErrorException::class);
        $pusher->terminateUserConnections("123");
    }

    public function testTerminateUserConectionsAsyncError(): void
    {
        $pusher = $this->mockPusher([new Response(500, [], "{}")]);
        $this->expectException(ApiErrorException::class);
        $pusher->terminateUserConnectionsAsync("123")->wait();
    }

    public function testTerminateUserConnectionsUsesClientInterfaceRequest(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);

        $httpClient->expects(self::once())
            ->method('request')
            ->with(
                'POST',
                'apps/appid/users/123/terminate_connections',
                self::callback(function (array $options): bool {
                    return $options['body'] === '{}'
                        && $options['http_errors'] === false
                        && $options['base_uri'] === 'https://api-test1.pusher.com:443'
                        && $options['headers']['Content-Type'] === 'application/json';
                })
            )
            ->willReturn(new Response(200, [], '{}'));

        $pusher = new Pusher('auth-key', 'secret', 'appid', ['cluster' => 'test1'], $httpClient);

        self::assertEquals(new stdClass(), $pusher->terminateUserConnections('123'));
    }

    public function testTerminateUserConnectionsAsyncUsesClientInterfaceRequestAsync(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);

        $httpClient->expects(self::once())
            ->method('requestAsync')
            ->with(
                'POST',
                'apps/appid/users/123/terminate_connections',
                self::callback(function (array $options): bool {
                    return $options['body'] === '{}'
                        && $options['http_errors'] === false
                        && $options['base_uri'] === 'https://api-test1.pusher.com:443'
                        && $options['headers']['Content-Type'] === 'application/json';
                })
            )
            ->willReturn(new FulfilledPromise(new Response(200, [], '{}')));

        $pusher = new Pusher('auth-key', 'secret', 'appid', ['cluster' => 'test1'], $httpClient);

        self::assertEquals(new stdClass(), $pusher->terminateUserConnectionsAsync('123')->wait());
    }

    public function testTerminateUserConnectionsAsyncMapsNetworkExceptionToApiErrorException(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $networkException = new class ('connection failed', new Request('POST', 'https://example.com')) extends RuntimeException implements NetworkExceptionInterface {
            private $request;

            public function __construct(string $message, RequestInterface $request)
            {
                parent::__construct($message);
                $this->request = $request;
            }

            public function getRequest(): RequestInterface
            {
                return $this->request;
            }
        };

        $httpClient->method('requestAsync')
            ->willReturn(new RejectedPromise($networkException));

        $pusher = new Pusher('auth-key', 'secret', 'appid', ['cluster' => 'test1'], $httpClient);

        try {
            $pusher->terminateUserConnectionsAsync('123')->wait();
            $this->fail('An exception should have been thrown.');
        } catch (ApiErrorException $exception) {
            self::assertSame('connection failed', $exception->getMessage());
            self::assertSame($networkException, $exception->getPrevious());
        }
    }

    public function testTerminateUserConnectionsAsyncMapsConnectExceptionToApiErrorException(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $connectException = new ConnectException(
            'connection failed',
            new Request('POST', 'https://example.com')
        );

        $httpClient->method('requestAsync')
            ->willReturn(new RejectedPromise($connectException));

        $pusher = new Pusher('auth-key', 'secret', 'appid', ['cluster' => 'test1'], $httpClient);

        try {
            $pusher->terminateUserConnectionsAsync('123')->wait();
            $this->fail('An exception should have been thrown.');
        } catch (ApiErrorException $exception) {
            self::assertSame('connection failed', $exception->getMessage());
            self::assertSame($connectException, $exception->getPrevious());
        }
    }

    public function testTerminateUserConnectionsMapsConnectExceptionToApiErrorException(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $connectException = new ConnectException(
            'connection failed',
            new Request('POST', 'https://example.com')
        );

        $httpClient->method('request')
            ->willThrowException($connectException);

        $pusher = new Pusher('auth-key', 'secret', 'appid', ['cluster' => 'test1'], $httpClient);

        try {
            $pusher->terminateUserConnections('123');
            $this->fail('An exception should have been thrown.');
        } catch (ApiErrorException $exception) {
            self::assertSame('connection failed', $exception->getMessage());
            self::assertSame($connectException, $exception->getPrevious());
        }
    }

    public function testTerminateUserConnectionsAsyncRethrowsNonConnectThrowable(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);

        $httpClient->method('requestAsync')
            ->willReturn(new RejectedPromise(new RuntimeException('boom')));

        $pusher = new Pusher('auth-key', 'secret', 'appid', ['cluster' => 'test1'], $httpClient);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('boom');

        $pusher->terminateUserConnectionsAsync('123')->wait();
    }
}
