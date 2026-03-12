<?php

namespace App\Services;

class RenderedEmail
{
    public function __construct(
        public readonly string $subject,
        public readonly string $html,
        public readonly string $text
    ) {}
}
