<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Error;

use InvalidArgumentException;
use JsonSerializable;

/**
 * RFC 9457 problem details object, serialized as application/problem+json
 *
 * @see https://www.rfc-editor.org/rfc/rfc9457
 */
final readonly class ProblemDetails implements JsonSerializable
{
    public const string DEFAULT_TYPE = 'about:blank';

    private const array RESERVED_MEMBERS = ['type', 'status', 'title', 'detail', 'instance'];

    /**
     * @param int $status HTTP status code of the response
     * @param string $title short, human-readable summary of the problem type
     * @param string|null $detail human-readable explanation of this occurrence
     * @param string $type URI reference identifying the problem type
     * @param string|null $instance URI reference identifying this occurrence
     * @param array<string, mixed> $extensions extension members, e.g. ['errors' => [...]]
     * @throws InvalidArgumentException if status is not 4xx/5xx or an extension uses a reserved member name
     */
    public function __construct(
        public int $status,
        public string $title,
        public ?string $detail = null,
        public string $type = self::DEFAULT_TYPE,
        public ?string $instance = null,
        public array $extensions = [],
    ) {
        if ($status < 400 || $status > 599) {
            throw new InvalidArgumentException(sprintf('Problem details status must be 4xx or 5xx, %d given', $status));
        }

        foreach (array_keys($extensions) as $name) {
            if (in_array($name, self::RESERVED_MEMBERS, true)) {
                throw new InvalidArgumentException(sprintf('Extension member "%s" is reserved by RFC 9457', $name));
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'type' => $this->type,
            'status' => $this->status,
            'title' => $this->title,
        ];
        if ($this->detail !== null) {
            $data['detail'] = $this->detail;
        }

        if ($this->instance !== null) {
            $data['instance'] = $this->instance;
        }

        return $data + $this->extensions;
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
