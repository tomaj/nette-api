<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Input\Fixtures;

use Tomaj\NetteApi\Attribute\Get;
use Tomaj\NetteApi\Attribute\Post;
use Tomaj\NetteApi\Handlers\TypedHandler;
use Tomaj\NetteApi\Input\Body;
use Tomaj\NetteApi\Input\Cast;
use Tomaj\NetteApi\Input\DateFormat;
use Tomaj\NetteApi\Input\File;
use Tomaj\NetteApi\Input\FileUpload;
use Tomaj\NetteApi\Input\Header;
use Tomaj\NetteApi\Input\Path;
use Tomaj\NetteApi\Input\Query;
use Tomaj\NetteApi\Input\Split;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;
use Tomaj\NetteApi\Validation\ConstraintContext;
use Tomaj\NetteApi\Validation\ConstraintInterface;
use Tomaj\NetteApi\Validation\Email;
use Tomaj\NetteApi\Validation\Length;
use Tomaj\NetteApi\Validation\Pattern;
use Tomaj\NetteApi\Validation\Range;

enum UserStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';
}

enum Priority: int
{
    case Low = 1;
    case High = 2;
}

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class LaterThan implements ConstraintInterface
{
    public function __construct(private string $otherProperty)
    {
    }

    public function validate(mixed $value, ConstraintContext $context): ?string
    {
        $other = $context->value($this->otherProperty);
        if ($value instanceof \DateTimeImmutable && $other instanceof \DateTimeImmutable && $value <= $other) {
            return sprintf('Must be later than %s', $this->otherProperty);
        }

        return null;
    }
}

final readonly class AddressInput
{
    public function __construct(
        #[Body]
        #[Length(min: 1, max: 120)]
        public string $street,
        #[Body]
        #[Pattern('/^[0-9]{5}$/')]
        public string $postalCode,
    ) {
    }
}

final readonly class ItemInput
{
    public function __construct(
        #[Body]
        public string $sku,
        #[Body]
        #[Range(min: 1)]
        public int $quantity,
    ) {
    }
}

final readonly class CreateOrderInput
{
    /** @param list<ItemInput> $items */
    public function __construct(
        #[Body]
        #[Email]
        public string $customerEmail,
        #[Body]
        public AddressInput $address,
        #[Body]
        #[Length(min: 1, max: 50)]
        public array $items,
        #[Body]
        public ?\DateTimeImmutable $deliveryAt = null,
    ) {
    }
}

#[Post(1, 'orders')]
final class CreateOrderHandler extends TypedHandler
{
    public function handleDto(CreateOrderInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, [
            'email' => $input->customerEmail,
            'street' => $input->address->street,
            'items' => count($input->items),
            'quantity' => $input->items[0]->quantity,
            'deliveryAt' => $input->deliveryAt?->format(\DateTimeInterface::ATOM),
        ]);
    }
}

final readonly class ListUsersInput
{
    public function __construct(
        #[Query(description: 'Page number')]
        #[Range(min: 1)]
        public int $page = 1,
        #[Query]
        #[Range(min: 1, max: 100)]
        public int $limit = 20,
        #[Query]
        public ?UserStatus $status = null,
        #[Query]
        public ?Priority $priority = null,
        #[Query]
        public bool $remember = false,
    ) {
    }
}

#[Get(1, 'users', 'list')]
final class ListUsersHandler extends TypedHandler
{
    public function handleDto(ListUsersInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, [
            'page' => $input->page,
            'limit' => $input->limit,
            'status' => $input->status?->value,
            'priority' => $input->priority?->value,
            'remember' => $input->remember,
        ]);
    }
}

final readonly class UserDetailInput
{
    public function __construct(
        #[Path]
        #[Range(min: 1)]
        public int $id,
        #[Query]
        #[Range(min: 1, max: 100)]
        public int $limit = 20,
        #[Query]
        public ?UserStatus $status = null,
        #[Header('X-Request-Id')]
        public ?string $requestId = null,
    ) {
    }
}

#[Get(1, 'users', '{id}/detail')]
final class UserDetailHandler extends TypedHandler
{
    public function handleDto(UserDetailInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, [
            'id' => $input->id,
            'limit' => $input->limit,
            'status' => $input->status?->value,
            'requestId' => $input->requestId,
        ]);
    }
}

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

#[Post(1, 'avatar')]
final class AvatarHandler extends TypedHandler
{
    public function handleDto(AvatarInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, [
            'name' => $input->displayName,
            'file' => $input->avatar->originalName,
            'size' => $input->avatar->size,
            'error' => $input->avatar->error,
            'type' => $input->avatar->clientContentType,
            'path' => $input->avatar->temporaryPath,
        ]);
    }
}

final readonly class IssueInput
{
    public function __construct(
        #[Body]
        #[Cast(DateFormat::class, 'd.m.Y')]
        public \DateTimeImmutable $issuedOn,
    ) {
    }
}

final class IssueHandler extends TypedHandler
{
    public function handleDto(IssueInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, ['issuedOn' => $input->issuedOn->format('Y-m-d')]);
    }
}

final readonly class ReportInput
{
    /** @param list<string> $columns */
    public function __construct(
        #[Query]
        #[Cast(Split::class, ',')]
        public array $columns,
    ) {
    }
}

final class ReportHandler extends TypedHandler
{
    public function handleDto(ReportInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, ['columns' => $input->columns]);
    }
}

final readonly class ScheduleInput
{
    public function __construct(
        #[Body]
        public \DateTimeImmutable $startsAt,
        #[Body]
        #[LaterThan('startsAt')]
        public \DateTimeImmutable $endsAt,
    ) {
    }
}

final class ScheduleHandler extends TypedHandler
{
    public function handleDto(ScheduleInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, ['ok' => true]);
    }
}

final readonly class FilesInput
{
    /** @param list<FileUpload> $files */
    public function __construct(
        #[File]
        public array $files,
    ) {
    }
}

final class FilesHandler extends TypedHandler
{
    public function handleDto(FilesInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, ['count' => count($input->files), 'name' => $input->files[0]->originalName]);
    }
}
