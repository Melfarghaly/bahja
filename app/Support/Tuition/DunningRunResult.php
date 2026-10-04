<?php

namespace App\Support\Tuition;

final class DunningRunResult
{
    public function __construct(
        public readonly int $sent = 0,
        public readonly int $skipped = 0,
        public readonly int $failed = 0,
        public readonly int $escalated = 0,
    ) {}
}
