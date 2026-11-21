<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\OpenApi\Fixtures;

use Tomaj\NetteApi\Input\Body;
use Tomaj\NetteApi\Input\File;
use Tomaj\NetteApi\Input\FileUpload;
use Tomaj\NetteApi\Input\Header;
use Tomaj\NetteApi\Input\Path;
use Tomaj\NetteApi\Input\Query;
use Tomaj\NetteApi\Test\Input\Fixtures\AddressInput;
use Tomaj\NetteApi\Test\Input\Fixtures\UserStatus;
use Tomaj\NetteApi\Validation\Range;

final readonly class TypedProfileInput
{
    /**
     * @param list<string> $fields
     * @param list<string> $tags
     */
    public function __construct(
        #[Path]
        #[Range(min: 1)]
        public int $id,
        #[Header('X-Request-Id')]
        public string $requestId,
        #[Query]
        public array $fields,
        #[Body]
        public string $name,
        #[Body]
        public ?UserStatus $status,
        #[Body]
        public array $tags,
        #[Body]
        public AddressInput $address,
        #[File]
        public ?FileUpload $avatar = null,
    ) {
    }
}
