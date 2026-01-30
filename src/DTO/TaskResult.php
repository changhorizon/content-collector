<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\DTO;

use DateTime;
use Illuminate\Support\Collection;

class TaskResult
{
    public string $host;
    public Collection $parsedPages;
    public Collection $media;
    public DateTime $startedAt;
    public DateTime $finishedAt;

    public function __construct(
        string $host,
        Collection $parsedPages,
        Collection $media,
        DateTime $startedAt,
        DateTime $finishedAt,
    ) {
        $this->host = $host;
        $this->parsedPages = $parsedPages;
        $this->media = $media;
        $this->startedAt = $startedAt;
        $this->finishedAt = $finishedAt;
    }
}
