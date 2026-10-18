<?php

declare(strict_types=1);

namespace ExceptionEnricher\Processor;

use Monolog\Level;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class ExceptionEnricherProcessor implements ProcessorInterface
{
    /** POST data is only added from this level on; it is what helps to reproduce an error. */
    private const POST_DATA_LEVEL = Level::Warning;

    /** Longer POST data (e.g. file uploads) is cut off, so a request can't fill the log. */
    private const MAX_POST_DATA_LENGTH = 2048;

    /** Values of keys containing one of these (case-insensitive) are logged as "***". */
    private const SECRET_KEY_PARTS = ['password', 'passwd', 'secret', 'token', 'jwt', 'authorization', 'apikey', 'api_key', 'credential'];

    private ?RequestStack $requestStack;
    private ?TokenStorageInterface $tokenStorage;

    public function __construct(?RequestStack $requestStack = null, ?TokenStorageInterface $tokenStorage = null)
    {
        $this->requestStack = $requestStack;
        $this->tokenStorage = $tokenStorage;
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        if ($this->requestStack) {
            if ($this->requestStack->getCurrentRequest()) {
                if ($this->requestStack->getCurrentRequest()->getRequestUri()) {
                    $record->extra['request_uri'] = \sprintf('%s %s', $this->requestStack->getCurrentRequest()->getMethod(), $this->requestStack->getCurrentRequest()->getRequestUri());
                }

                if ('POST' === $this->requestStack->getCurrentRequest()->getMethod() && self::POST_DATA_LEVEL->includes($record->level)) {
                    $postParams = $this->requestStack->getCurrentRequest()->request->all();

                    if (false === empty($postParams)) {
                        $record->extra['request_post_data'] = $this->truncate(\serialize($this->mask($postParams)));
                    } elseif (!empty($this->requestStack->getCurrentRequest()->getContent())) {
                        $record->extra['request_post_data'] = $this->truncate($this->maskJson($this->requestStack->getCurrentRequest()->getContent()));
                    }
                }

                if ($this->requestStack->getCurrentRequest()->headers) {
                    $record->extra['request_user_agent'] = $this->requestStack->getCurrentRequest()->headers->get('User-Agent');
                }

                if ($this->requestStack->getCurrentRequest()->hasSession() && $this->requestStack->getSession()->getId()) {
                    $record->extra['session_id'] = $this->requestStack->getSession()->getId();
                }
            }

            if ($this->requestStack->getMainRequest()) {
                $record->extra['request_ip'] = $this->requestStack->getMainRequest()->getClientIp();

                if ($this->tokenStorage && $this->tokenStorage->getToken()) {
                    $record->extra['username'] = $this->tokenStorage->getToken()->getUserIdentifier();
                }
            }
        }

        return $record;
    }

    private function mask(array $data): array
    {
        foreach ($data as $key => $value) {
            if (\is_array($value)) {
                $data[$key] = $this->mask($value);
            } elseif ($this->isSecret((string) $key)) {
                $data[$key] = '***';
            }
        }

        return $data;
    }

    private function isSecret(string $key): bool
    {
        $key = \mb_strtolower($key);
        foreach (self::SECRET_KEY_PARTS as $part) {
            if (\str_contains($key, $part)) {
                return true;
            }
        }

        return false;
    }

    /** A JSON body with secrets is re-encoded with them masked; any other body stays as it is. */
    private function maskJson(string $content): string
    {
        $data = \json_decode($content, true);
        if (!\is_array($data)) {
            return $content;
        }

        $masked = $this->mask($data);

        return $masked === $data ? $content : (string) \json_encode($masked, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
    }

    private function truncate(string $postData): string
    {
        $length = \mb_strlen($postData);
        if ($length <= self::MAX_POST_DATA_LENGTH) {
            return $postData;
        }

        return \sprintf('%s… [truncated, %d characters]', \mb_substr($postData, 0, self::MAX_POST_DATA_LENGTH), $length);
    }
}
