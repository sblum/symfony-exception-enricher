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
    /** A field name like "password", "user[password]" or "client.secret"; anything else is masked whole. */
    private const PLAIN_KEY = '/^[\w.\-\[\] ]{1,100}$/';

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
                        $masked = false;
                        $record->extra['request_post_data'] = $this->truncate(\serialize($this->mask($postParams, $masked)));
                    } elseif (!empty($this->requestStack->getCurrentRequest()->getContent())) {
                        $record->extra['request_post_data'] = $this->truncate($this->maskBody($this->requestStack->getCurrentRequest()->getContent()));
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
     * else. A key that isn't a plain field name but contains a secret word (e.g. a JSON document
     * that form parsing turned into a field name) is masked, too. Objects stay objects, so a JSON
     * body keeps its shape ({} stays {}). $masked is set when anything was replaced.
     */
    private function mask(mixed $data, bool &$masked): mixed
    {
        if (\is_array($data)) {
            $result = [];
            foreach ($data as $key => $value) {
                if (\is_string($key) && $this->isSecret($key)) {
                    $masked = true;
                    $result[1 === \preg_match(self::PLAIN_KEY, $key) ? $key : '***'] = '***';
                } else {
                    $result[$key] = $this->mask($value, $masked);
                }
            }

            return $result;
        }

        if ($data instanceof stdClass) {
            $result = new stdClass();
            foreach (\get_object_vars($data) as $key => $value) {
                if ($this->isSecret((string) $key)) {
                    $masked = true;
                    $result->{1 === \preg_match(self::PLAIN_KEY, (string) $key) ? $key : '***'} = '***';
                } else {
                    $result->{$key} = $this->mask($value, $masked);
                }
            }

            return $result;
        }

        return $data;
    }

    private function isSecret(string $text): bool
    {
        $text = \mb_strtolower($text);
        foreach (self::SECRET_KEY_PARTS as $part) {
            if (\str_contains($text, $part)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The request body with its secrets masked: JSON and urlencoded bodies are masked field by
     * field. Any other body (XML, text, …) can't be masked reliably, so it is left out when it
     * contains one of the secret words.
     */
    private function maskBody(string $content): string
    {
        // A byte order mark would hide a JSON body from the check below.
        $body = \str_starts_with($content, "\u{FEFF}") ? \mb_substr($content, 1) : $content;

        if (1 === \preg_match('/^\s*[{[]/', $body)) {
            return $this->maskJson($body, $content);
        }

        if (1 === \preg_match('/^[^\s=&]+=\S*$/', $body)) {
            \parse_str($body, $params);
            $masked = false;
            $params = $this->mask($params, $masked);

            return $masked ? \http_build_query($params) : $content;
        }

        if ($this->isSecret($body)) {
            return \sprintf('[Body omitted: it may contain secrets that can\'t be masked, %d characters]', \mb_strlen($content));
        }

        return $content;
    }

    /**
     * A JSON body that can't be decoded (broken, nested too deeply) or encoded again can't be
     * masked, so it is left out. Without secrets, the body is logged exactly as it was sent.
     */
    private function maskJson(string $json, string $content): string
    {
        $omitted = \sprintf('[JSON body omitted: it could not be decoded to mask secrets, %d characters]', \mb_strlen($content));

        try {
            $masked = false;
            $data = $this->mask(\json_decode($json, false, 512, \JSON_THROW_ON_ERROR), $masked);

            return $masked ? \json_encode($data, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR) : $content;
        } catch (JsonException) {
            return $omitted;
        }
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
