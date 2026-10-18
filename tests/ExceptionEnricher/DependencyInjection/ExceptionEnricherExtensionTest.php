<?php

declare(strict_types=1);

namespace ExceptionEnricher\DependencyInjection;

use ExceptionEnricher\ExceptionEnricherBundle;
use ExceptionEnricher\Processor\ExceptionEnricherProcessor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ExceptionEnricherExtensionTest extends TestCase
{
    public function testBundleRegistersTheProcessorForMonolog(): void
    {
        $container = new ContainerBuilder();
        (new ExceptionEnricherBundle())->getContainerExtension()->load([], $container);

        $definition = $container->getDefinition('sblum.exception_enricher');
        $this->assertSame(ExceptionEnricherProcessor::class, $definition->getClass());
        $this->assertSame([[]], $definition->getTag('monolog.processor'));
    }
}
