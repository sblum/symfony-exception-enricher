<?php

declare(strict_types=1);

namespace ExceptionEnricher\Processor;

use Monolog\Processor\ProcessorInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class ExceptionEnricherProcessor implements ProcessorInterface
{
    /** @var string $postParams */
    private $postParams = null;
    /** @var RequestStack $requestStack */
    private $requestStack;
    /** @var SessionInterface $session */
    private $session;
    /** @var TokenStorageInterface $tokenStorage */
    private $tokenStorage;

    public function __construct(?RequestStack $requestStack, ?SessionInterface $session, ?TokenStorageInterface $tokenStorage)
    {
        $this->requestStack = $requestStack;
        $this->session = $session;
        $this->tokenStorage = $tokenStorage;
    }

    public function __invoke(array $record)
    {
        if ($this->requestStack) {
            if ($this->requestStack->getCurrentRequest()->getRequestUri()) {
                $record['extra']['request_uri'] = \sprintf('%s %s', $this->requestStack->getCurrentRequest()->getMethod(), $this->requestStack->getCurrentRequest()->getRequestUri());
            }

            if ('POST' === $this->requestStack->getCurrentRequest()->getMethod()) {
                $postParams = $this->requestStack->getCurrentRequest()->request->all();

                if (false === empty($postParams)) {
                    $this->postParams = \serialize($postParams);
                    $record['extra']['request_post_data'] = $this->postParams;
                }
            }

            if ($this->requestStack->getCurrentRequest()->headers) {
                $record['extra']['request_user_agent'] = $this->requestStack->getCurrentRequest()->headers->get('User-Agent');
            }

            if ($this->requestStack->getMasterRequest()) {
                $record['extra']['request_ip'] = $this->requestStack->getMasterRequest()->getClientIp();
            }
        }

        if ($this->session) {
            $record['extra']['session_id'] = $this->session->getId();
        }

        if ($this->tokenStorage) {
            $record['extra']['username'] = $this->tokenStorage->getToken()->getUsername();
        }

        return $record;
    }
}
