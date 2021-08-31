<?php

declare(strict_types=1);

namespace ExceptionEnricher\Processor;

use DateTimeImmutable;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\HeaderBag;
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
    public function testEmptyProcessor()
    {
        $exceptionEnricherProcessor = new ExceptionEnricherProcessor(null, null);
        $record = $exceptionEnricherProcessor($this->createRecord());

        $this->assertArrayNotHasKey('request_uri', $record['extra']);
        $this->assertArrayNotHasKey('request_post_data', $record['extra']);
        $this->assertArrayNotHasKey('request_user_agent', $record['extra']);
        $this->assertArrayNotHasKey('request_ip', $record['extra']);
        $this->assertArrayNotHasKey('session_id', $record['extra']);
        $this->assertArrayNotHasKey('username', $record['extra']);
        $this->assertTrue(empty($record['extra']));
    }

    /**
     * @covers \ExceptionEnricherProcessor::__invoke
     */
    public function testFullProcessor()
    {
        $request = $this->prophesize(Request::class);
        $request->headers = new HeaderBag(['User-Agent' => 'Mozilla/5.0']);
        $request->getClientIp()->willReturn('127.0.0.1')->shouldBeCalled();
        $request->getMethod()->willReturn('GET')->shouldBeCalled();
        $request->getRequestUri()->willReturn('/testroute/')->shouldBeCalled();

        $session = $this->prophesize(SessionInterface::class);
        $session->getId()->willReturn('39d9f31fb12441428031e26d2f83ab6e')->shouldBeCalled();

        $requestStack = $this->prophesize(RequestStack::class);
        $requestStack->getCurrentRequest()->willReturn($request->reveal())->shouldBeCalled();
        $requestStack->getMainRequest()->willReturn($request->reveal())->shouldBeCalled();
        $requestStack->getSession()->willReturn($session)->shouldBeCalled();

        $token = $this->prophesize(TokenInterface::class);
        $token->getUserIdentifier()->willReturn('testuser')->shouldBeCalled();

        $tokenStorage = $this->prophesize(TokenStorageInterface::class);
        $tokenStorage->getToken()->willReturn($token->reveal())->shouldBeCalled();

        $exceptionEnricherProcessor = new ExceptionEnricherProcessor($requestStack->reveal(), $tokenStorage->reveal());
        $record = $exceptionEnricherProcessor($this->createRecord());

        $this->assertArrayHasKey('request_uri', $record['extra']);
        $this->assertArrayHasKey('request_user_agent', $record['extra']);
        $this->assertArrayHasKey('request_ip', $record['extra']);
        $this->assertArrayHasKey('session_id', $record['extra']);
        $this->assertArrayHasKey('username', $record['extra']);
        $this->assertArrayNotHasKey('request_post_data', $record['extra']);

        $this->assertSame('GET /testroute/', $record['extra']['request_uri']);
        $this->assertSame('Mozilla/5.0', $record['extra']['request_user_agent']);
        $this->assertSame('127.0.0.1', $record['extra']['request_ip']);
        $this->assertSame('testuser', $record['extra']['username']);
        $this->assertSame('39d9f31fb12441428031e26d2f83ab6e', $record['extra']['session_id']);
    }

    private function createRecord($level = Logger::ERROR, $message = 'An Exception has been encountered.'): array
    {
        return [
            'message' => $message,
            'level' => $level,
            'level_name' => Logger::getLevelName($level),
            'channel' => 'test',
            'datetime' => new DateTimeImmutable('now'),
            'extra' => [],
        ];
    }
}
