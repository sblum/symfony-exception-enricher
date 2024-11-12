<?php

declare(strict_types=1);

namespace ExceptionEnricher\Processor;

use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class ExceptionEnricherProcessorTest extends TestCase
{
    /**
     * @covers \ExceptionEnricherProcessor::__invoke
     */
    public function testEmptyProcessor(): void
    {
        $exceptionEnricherProcessor = new ExceptionEnricherProcessor(null, null);
        $record = $exceptionEnricherProcessor($this->createRecord());

        $this->assertArrayNotHasKey('request_uri', $record->extra);
        $this->assertArrayNotHasKey('request_post_data', $record->extra);
        $this->assertArrayNotHasKey('request_user_agent', $record->extra);
        $this->assertArrayNotHasKey('request_ip', $record->extra);
        $this->assertArrayNotHasKey('session_id', $record->extra);
        $this->assertArrayNotHasKey('username', $record->extra);
        $this->assertEmpty($record->extra);
    }

    /**
     * @covers \ExceptionEnricherProcessor::__invoke
     */
    public function testFullProcessorWithGetRequest(): void
    {
        $request = $this->createMock(Request::class);
        $request->headers = new HeaderBag(['User-Agent' => 'Mozilla/5.0']);
        $request->method('getClientIp')->willReturn('127.0.0.1');
        $request->method('getMethod')->willReturn('GET');
        $request->method('getRequestUri')->willReturn('/testroute/');
        $request->method('hasSession')->willReturn(true);

        $session = $this->createMock(SessionInterface::class);
        $session->method('getId')->willReturn('39d9f31fb12441428031e26d2f83ab6e');

        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn($request);
        $requestStack->method('getMainRequest')->willReturn($request);
        $requestStack->method('getSession')->willReturn($session);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUserIdentifier')->willReturn('testuser');

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $exceptionEnricherProcessor = new ExceptionEnricherProcessor($requestStack, $tokenStorage);
        $record = $exceptionEnricherProcessor($this->createRecord());

        $this->assertArrayHasKey('request_uri', $record->extra);
        $this->assertArrayHasKey('request_user_agent', $record->extra);
        $this->assertArrayHasKey('request_ip', $record->extra);
        $this->assertArrayHasKey('session_id', $record->extra);
        $this->assertArrayHasKey('username', $record->extra);
        $this->assertArrayNotHasKey('request_post_data', $record->extra);

        $this->assertSame('GET /testroute/', $record->extra['request_uri']);
        $this->assertSame('Mozilla/5.0', $record->extra['request_user_agent']);
        $this->assertSame('127.0.0.1', $record->extra['request_ip']);
        $this->assertSame('testuser', $record->extra['username']);
        $this->assertSame('39d9f31fb12441428031e26d2f83ab6e', $record->extra['session_id']);
    }

    /**
     * @dataProvider providePostRequestData
     */
    public function testFullProcessorWithPostRequest(string $type, $data): void
    {
        $request = $this->createMock(Request::class);
        $request->headers = new HeaderBag(['User-Agent' => 'Mozilla/5.0']);
        $request->method('getClientIp')->willReturn('127.0.0.1');
        $request->method('getMethod')->willReturn('POST');
        $request->method('getRequestUri')->willReturn('/testroute/');
        $request->method('hasSession')->willReturn(true);

        if ($type === 'parameterBag') {
            $request->request = $data;
        } else {
            $request->request = new ParameterBag();
            $request->method('getContent')->willReturn($data);
        }

        $session = $this->createMock(SessionInterface::class);
        $session->method('getId')->willReturn('39d9f31fb12441428031e26d2f83ab6e');

        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn($request);
        $requestStack->method('getMainRequest')->willReturn($request);
        $requestStack->method('getSession')->willReturn($session);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUserIdentifier')->willReturn('testuser');

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $exceptionEnricherProcessor = new ExceptionEnricherProcessor($requestStack, $tokenStorage);
        $record = $exceptionEnricherProcessor($this->createRecord());

        $this->assertArrayHasKey('request_uri', $record->extra);
        $this->assertArrayHasKey('request_user_agent', $record->extra);
        $this->assertArrayHasKey('request_ip', $record->extra);
        $this->assertArrayHasKey('session_id', $record->extra);
        $this->assertArrayHasKey('username', $record->extra);

        $this->assertSame('POST /testroute/', $record->extra['request_uri']);
        $this->assertSame('Mozilla/5.0', $record->extra['request_user_agent']);
        $this->assertSame('127.0.0.1', $record->extra['request_ip']);
        $this->assertSame('testuser', $record->extra['username']);
        $this->assertSame('39d9f31fb12441428031e26d2f83ab6e', $record->extra['session_id']);
        if ($type === 'parameterBag') {
            $this->assertEquals('a:2:{s:12:"getVariable1";s:6:"value1";s:12:"getVariable2";s:6:"value2";}', $record->extra['request_post_data']);
        } else {
            $this->assertSame('{"getVariable1":"value1","getVariable2":"value2"}', $record->extra['request_post_data']);
        }
    }

    public function providePostRequestData(): \Iterator
    {
        yield ['parameterBag', new ParameterBag(['getVariable1' => 'value1', 'getVariable2' => 'value2'])];
        yield ['content', '{"getVariable1":"value1","getVariable2":"value2"}'];
    }


    private function createRecord(): LogRecord
    {
        return new LogRecord(new DateTimeImmutable('now'), 'test', Level::Error, 'An Exception has been encountered.');
    }
}
