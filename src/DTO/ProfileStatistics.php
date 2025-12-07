<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\DTO;

/**
 * Statistics from parsing an Xdebug profile (Cachegrind) file
 */
final class ProfileStatistics
{
    public function __construct(
        public readonly string $filePath,
        public readonly int $fileSize,
        public readonly int $functionsCount,
        public readonly int $callsCount,
        public readonly string $targetFile,
        public readonly string $creator,
        public readonly string $command = '',
    ) {
    }

    public function getFileSizeFormatted(): string
    {
        if ($this->fileSize > 1024 * 1024) {
            return round($this->fileSize / 1024 / 1024, 1) . 'MB';
        }

        if ($this->fileSize > 1024) {
            return round($this->fileSize / 1024, 1) . 'KB';
        }

        return $this->fileSize . 'B';
    }
}
