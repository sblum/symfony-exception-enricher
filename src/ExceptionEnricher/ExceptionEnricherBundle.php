<?php

declare(strict_types=1);

namespace ExceptionEnricher;

use ExceptionEnricher\DependencyInjection\ExceptionEnricherExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class ExceptionEnricherBundle extends Bundle
{
    public function getContainerExtension(): ExceptionEnricherExtension
    {
        return new ExceptionEnricherExtension();
    }
}
