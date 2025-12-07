<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\DTO;

use JsonSerializable;

/**
 * Base class for analysis results with JSON serialization
 */
abstract class AnalysisResult implements JsonSerializable
{
    /**
     * @return array<string, scalar|array<string, scalar|array<string, scalar|list<scalar>>>>
     */
    abstract public function jsonSerialize(): array;
}
