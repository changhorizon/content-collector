<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Contracts;

interface TaskCounterInterface
{
    public function current(): int;

    public function increment(): int;
}
