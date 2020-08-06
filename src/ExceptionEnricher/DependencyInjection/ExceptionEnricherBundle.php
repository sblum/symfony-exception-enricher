<?php

declare(strict_types=1);

namespace ExceptionEnricher\DependencyInjection;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class ExceptionEnricherBundle extends Bundle
{
    public function getContainerExtension()
    {
        return new ExceptionEnricherExtension();
    }
}
