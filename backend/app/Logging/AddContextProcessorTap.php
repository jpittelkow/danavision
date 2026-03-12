<?php

namespace App\Logging;

use Illuminate\Log\Logger;

class AddContextProcessorTap
{
    /**
     * Customize the given logger instance by adding the context processor.
     */
    public function __invoke(Logger $logger): void
    {
        $logger->getLogger()->pushProcessor(new ContextProcessor());
    }
}
