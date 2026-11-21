<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Input;

/**
 * One uploaded file. The client content type is not trusted and the file is not moved.
 */
final readonly class FileUpload
{
    public function __construct(
        public string $originalName,
        public string $clientContentType,
        public string $temporaryPath,
        public int $size,
        public int $error,
    ) {
    }

    /**
     * @param array<array-key, mixed> $file one $_FILES entry (name, type, tmp_name, error, size)
     */
    public static function fromFile(array $file): self
    {
        return new self(
            is_string($file['name'] ?? null) ? $file['name'] : '',
            is_string($file['type'] ?? null) ? $file['type'] : '',
            is_string($file['tmp_name'] ?? null) ? $file['tmp_name'] : '',
            is_int($file['size'] ?? null) ? $file['size'] : 0,
            is_int($file['error'] ?? null) ? $file['error'] : \UPLOAD_ERR_NO_FILE,
        );
    }
}
