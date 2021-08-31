<?php

declare(strict_types=1);

namespace ExceptionEnricher\Processor;

use Monolog\Processor\ProcessorInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class ExceptionEnricherProcessor implements ProcessorInterface
{
    private ?RequestStack $requestStack;
    private ?TokenStorageInterface $tokenStorage;

    public function __construct(?RequestStack $requestStack = null, ?TokenStorageInterface $tokenStorage = null)
    {
        $this->requestStack = $requestStack;
        $this->tokenStorage = $tokenStorage;
    }

    public function __invoke(array $record): array
    {
        if ($this->requestStack) {
            if ($this->requestStack->getCurrentRequest()) {
                if ($this->requestStack->getCurrentRequest()->getRequestUri()) {
                    $record['extra']['request_uri'] = \sprintf('%s %s', $this->requestStack->getCurrentRequest()->getMethod(), $this->requestStack->getCurrentRequest()->getRequestUri());
                }

                if ('POST' === $this->requestStack->getCurrentRequest()->getMethod()) {
                    $postParams = $this->requestStack->getCurrentRequest()->request->all();

                    if (false === empty($postParams)) {
                        $postParams = \serialize($postParams);
                        $record['extra']['request_post_data'] = $postParams;
                    }
                }

                if ($this->requestStack->getCurrentRequest()->headers) {
                    $record['extra']['request_user_agent'] = $this->requestStack->getCurrentRequest()->headers->get('User-Agent');
                }

                if ($this->requestStack->getCurrentRequest()->hasSession() && $this->requestStack->getSession()->getId()) {
                    $record['extra']['session_id'] = $this->requestStack->getSession()->getId();
                }
            }

            if ($this->requestStack->getMainRequest()) {
                $record['extra']['request_ip'] = $this->requestStack->getMainRequest()->getClientIp();

                if ($this->tokenStorage && $this->tokenStorage->getToken()) {
                    $record['extra']['username'] = $this->tokenStorage->getToken()->getUserIdentifier();
                }
            }
        }

        return $record;
    }
}
