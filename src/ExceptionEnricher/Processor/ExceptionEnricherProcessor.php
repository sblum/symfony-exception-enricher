<?php

declare(strict_types=1);

namespace ExceptionEnricher\Processor;

use JsonException;
use Monolog\Level;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use stdClass;
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
    /** Whether the last mask() call replaced a value. */
    private bool $maskedSecret = false;
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

    /**
     * Masks the values of secret keys, whole (also arrays and objects), and recurses into everything
     * else. Objects stay objects, so a JSON body keeps its shape ({} stays {}).
     */
    private function mask(mixed $data): mixed
    {
        if (\is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = \is_string($key) && $this->isSecret($key) ? $this->maskedValue() : $this->mask($value);
            }
        } elseif ($data instanceof stdClass) {
            foreach (\get_object_vars($data) as $key => $value) {
                $data->{$key} = $this->isSecret((string) $key) ? $this->maskedValue() : $this->mask($value);
            }
        }

        return $data;
    }

    private function maskedValue(): string
    {
        $this->maskedSecret = true;

        return '***';
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

    /**
     * A JSON body is logged with its secrets masked. A body that looks like JSON but can't be
     * decoded (broken, or nested too deeply) can't be masked, so it is left out. Other bodies stay
     * as they are.
     */
    private function maskJson(string $content): string
    {
        // A regex, not mb_ltrim(): that one needs PHP 8.4 or a recent symfony/polyfill-mbstring.
        if (1 !== \preg_match('/^\s*[{[]/', $content)) {
            return $content;
        }

        try {
            $data = \json_decode($content, false, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return \sprintf('[JSON body omitted: it could not be decoded to mask secrets, %d characters]', \mb_strlen($content));
        }

        $this->maskedSecret = false;
        $masked = $this->mask($data);

        // Without secrets the body is logged exactly as it was sent.
        return $this->maskedSecret ? (string) \json_encode($masked, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE) : $content;
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
