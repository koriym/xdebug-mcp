<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\DTO;

/**
 * Statistics from parsing an Xdebug profile (Cachegrind) file
 */
final readonly class ProfileStatistics
{
    public function __construct(
        public string $filePath,
        public int $fileSize,
        public int $functionsCount,
        public int $callsCount,
        public string $targetFile,
        public string $creator,
        public string $command = '',
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
