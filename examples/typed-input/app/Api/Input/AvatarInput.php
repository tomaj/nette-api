<?php

declare(strict_types=1);

namespace App\Api\Input;

use Tomaj\NetteApi\Input\Body;
use Tomaj\NetteApi\Input\File;
use Tomaj\NetteApi\Input\FileUpload;
use Tomaj\NetteApi\Validation\Length;

final readonly class AvatarInput
{
    public function __construct(
        #[Body]
        #[Length(min: 1, max: 80)]
        public string $displayName,
        #[File(description: 'PNG or JPEG image')]
        public FileUpload $avatar,
    ) {
    }
}
