<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\DTO;

use DateTimeInterface;
use Illuminate\Support\Collection;

class TaskResult
{
    public string $host;
    public Collection $parsedPages;
    public Collection $media;
    public DateTimeInterface $startedAt;
    public DateTimeInterface $finishedAt;

    public function __construct(
        string $host,
        Collection $parsedPages,
        Collection $media,
        DateTimeInterface $startedAt,
        DateTimeInterface $finishedAt,
    ) {
        $this->host = $host;
        $this->parsedPages = $parsedPages;
        $this->media = $media;
        $this->startedAt = $startedAt;
        $this->finishedAt = $finishedAt;
    }
}
