<?php

declare(strict_types=1);

use ExceptionEnricher\Processor\ExceptionEnricherProcessor;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('sblum.exception_enricher', ExceptionEnricherProcessor::class)
            ->args([service('request_stack'), service('security.token_storage')])
            ->tag('monolog.processor');
};
