---
title: params() and DTO side by side
description: 'The same endpoints declared with params() and with a typed DTO in Nette API: seven pairs that show how to rewrite a handler from one style to the other.'
---

# params() and DTO side by side

`params()` with `handle(array $params)` and `TypedHandler` with a DTO are two equal ways to write handlers in one application. Neither disables the other. Registration, authorization, outputs, rate limits, OpenAPI and the console are shared. [Typed input (DTO)](/guide/typed-input) compares the two styles, and [Input params](/guide/params) documents the first.

## Rewriting between styles

The following pairs show the same request contract in both styles, so you can rewrite a handler from either style to the other when it suits that endpoint. Framework imports are omitted where they are unchanged; application constructor dependencies and response creation remain in the handler. You can rewrite one endpoint, leave every other handler unchanged and rewrite the next one when it is convenient. There is no application-wide switch.

### 1. Simple GET: optional/defaulted query params and an enum

With `params()`, the handler declares each query parameter, casts the values and maps the status string to an enum. `UserStatus::options()` stands for an application helper that returns the allowed values:

```php
final class ListUsersHandler extends BaseHandler
{
    public function __construct(private UserRepository $users)
    {
        parent::__construct();
    }

    public function params(): array
    {
        return [
            (new GetInputParam('page', InputType::INTEGER))
                ->setDefault(1)
                ->setDescription('Page number'),
            (new GetInputParam('limit', InputType::INTEGER))->setDefault(20),
            (new GetInputParam('status', InputType::STRING))
                ->setAvailableValues(UserStatus::options()),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        $page = (int) $params['page'];
        $limit = (int) $params['limit'];
        $status = $params['status'] === null
            ? null
            : UserStatus::from($params['status']);

        if ($page < 1 || $limit < 1 || $limit > 100) {
            return new JsonApiResponse(400, ['status' => 'error']);
        }

        return new JsonApiResponse(200, $this->users->findPage($page, $limit, $status));
    }
}
```

With a DTO, defaults, optionality and allowed enum values live in the class. `Range` replaces the manual bounds check and `handleDto()` receives values ready to use:

```php
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
    ) {
    }
}

final class ListUsersHandler extends TypedHandler
{
    public function __construct(private UserRepository $users)
    {
        parent::__construct();
    }

    public function handleDto(ListUsersInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, $this->users->findPage(
            $input->page,
            $input->limit,
            $input->status,
        ));
    }
}
```

This also demonstrates the general rule for optional and defaulted values: `?UserStatus $status = null` is optional and becomes `null`; `int $limit = 20` is optional and becomes `20` when omitted. A required value would be non-nullable and have no default, for example `#[Query] public string $search`.

### 2. GET: a path id and a request header

With `params()`, the endpoint URL contains `users/{id}/detail` and `params()` declares `PathInputParam` and `HeaderInputParam`:

```php
final class UserDetailHandler extends BaseHandler
{
    public function params(): array
    {
        return [
            (new PathInputParam('id', InputType::INTEGER))->setRequired(),
            new HeaderInputParam('X-Request-Id', InputType::STRING),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        $id = (int) $params['id'];
        $requestId = $params['X-Request-Id'];

        return $this->userDetail($id, $requestId);
    }
}
```

With a DTO, `Path` and `Header` keep the external names while the handler uses meaningful typed properties:

```php
final readonly class UserDetailInput
{
    public function __construct(
        #[Path]
        #[Range(min: 1)]
        public int $id,

        #[Header('X-Request-Id')]
        public ?string $requestId = null,
    ) {
    }
}

final class UserDetailHandler extends TypedHandler
{
    public function handleDto(UserDetailInput $input): ResponseInterface
    {
        return $this->userDetail($input->id, $input->requestId);
    }
}
```

The endpoint registration and URL do not change. The DTO declaration still emits `id` as a required path parameter and `X-Request-Id` as an optional header in OpenAPI.

### 3. POST JSON: a nested object and an array

With `params()`, a `JsonInputParam` contains a handwritten schema. The handler reads nested arrays and constructs its own values:

```php
final class CreateOrderHandler extends BaseHandler
{
    public function params(): array
    {
        return [
            (new JsonInputParam('body', <<<'JSON'
                {
                  "type": "object",
                  "required": ["customerEmail", "address", "items"],
                  "properties": {
                    "customerEmail": {"type": "string", "format": "email"},
                    "address": {
                      "type": "object",
                      "required": ["street", "postalCode"],
                      "properties": {
                        "street": {"type": "string"},
                        "postalCode": {"type": "string", "pattern": "^[0-9]{5}$"}
                      }
                    },
                    "items": {
                      "type": "array",
                      "minItems": 1,
                      "items": {
                        "type": "object",
                        "required": ["sku", "quantity"],
                        "properties": {
                          "sku": {"type": "string"},
                          "quantity": {"type": "integer", "minimum": 1}
                        }
                      }
                    }
                  }
                }
                JSON))->setRequired(),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        $body = $params['body'];
        $items = array_map(
            fn (array $item) => new OrderItem($item['sku'], (int) $item['quantity']),
            $body['items'],
        );

        return $this->orders->create(
            $body['customerEmail'],
            new Address($body['address']['street'], $body['address']['postalCode']),
            $items,
        );
    }
}
```

With a DTO, the nested structure is expressed once as classes. No JSON schema is needed for runtime validation:

```php
final readonly class AddressInput
{
    public function __construct(
        #[Body] public string $street,
        #[Body] #[Pattern('/^[0-9]{5}$/')] public string $postalCode,
    ) {
    }
}

final readonly class OrderItemInput
{
    public function __construct(
        #[Body] public string $sku,
        #[Body] #[Range(min: 1)] public int $quantity,
    ) {
    }
}

final readonly class CreateOrderInput
{
    /** @param list<OrderItemInput> $items */
    public function __construct(
        #[Body] #[Email] public string $customerEmail,
        #[Body] public AddressInput $address,
        #[Body] #[Length(min: 1)] public array $items,
    ) {
    }
}

final class CreateOrderHandler extends TypedHandler
{
    public function handleDto(CreateOrderInput $input): ResponseInterface
    {
        $items = array_map(
            fn (OrderItemInput $item) => new OrderItem($item->sku, $item->quantity),
            $input->items,
        );

        return $this->orders->create(
            $input->customerEmail,
            new Address($input->address->street, $input->address->postalCode),
            $items,
        );
    }
}
```

`list<OrderItemInput>` is important: it tells both the mapper and OpenAPI what an array item contains. A failure in the second quantity is reported under `items[1].quantity`.

### 4. Form POST

With `params()`, `PostInputParam` objects describe a URL-encoded login form:

```php
final class LoginHandler extends BaseHandler
{
    public function params(): array
    {
        return [
            (new PostInputParam('email', InputType::STRING))->setRequired(),
            (new PostInputParam('password', InputType::STRING))->setRequired(),
            (new PostInputParam('remember', InputType::BOOLEAN))->setDefault(false),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        return $this->sessions->login(
            (string) $params['email'],
            (string) $params['password'],
            (bool) $params['remember'],
        );
    }
}
```

With a DTO, `Body` means a body field regardless of whether the request is JSON or form-encoded. The `max` bound is the only length rule here, because a minimum would start rejecting existing short passwords at login:

```php
final readonly class LoginInput
{
    public function __construct(
        #[Body] #[Email] public string $email,
        #[Body] #[Length(max: 200)] public string $password,
        #[Body] public bool $remember = false,
    ) {
    }
}

final class LoginHandler extends TypedHandler
{
    public function handleDto(LoginInput $input): ResponseInterface
    {
        return $this->sessions->login($input->email, $input->password, $input->remember);
    }
}
```

The form still sends `email=ada%40example.com&password=...&remember=1`. The DTO mapper rejects unknown boolean strings rather than treating every non-empty string as `true`.

### 5. File upload

With `params()`, the handler receives the raw upload entry and validates its upload error and size:

```php
final class AvatarUploadHandler extends BaseHandler
{
    public function params(): array
    {
        return [
            (new PostInputParam('displayName', InputType::STRING))->setRequired(),
            (new FileInputParam('avatar'))->setRequired(),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        $avatar = $params['avatar'];
        if ($avatar['error'] !== UPLOAD_ERR_OK || $avatar['size'] > 100_000) {
            return new JsonApiResponse(400, ['status' => 'error']);
        }

        return $this->avatars->store((string) $params['displayName'], $avatar['tmp_name']);
    }
}
```

With a DTO, `FileUpload` gives typed access to the same metadata. Application checks for real file type and storage remain explicit:

```php
final readonly class AvatarUploadInput
{
    public function __construct(
        #[Body] #[Length(min: 1, max: 80)] public string $displayName,
        #[File] public FileUpload $avatar,
    ) {
    }
}

final class AvatarUploadHandler extends TypedHandler
{
    public function handleDto(AvatarUploadInput $input): ResponseInterface
    {
        $avatar = $input->avatar;
        if ($avatar->error !== UPLOAD_ERR_OK || $avatar->size > 100_000) {
            return new JsonApiResponse(400, ['status' => 'error']);
        }

        return $this->avatars->store($input->displayName, $avatar->temporaryPath);
    }
}
```

The multipart field names remain `displayName` and `avatar`. Neither style replaces application security checks or PHP upload limits.

### 6. Custom validation

With `params()`, validation that depends on several values lives in `handle()`:

```php
final class ScheduleHandler extends BaseHandler
{
    public function params(): array
    {
        return [
            (new JsonInputParam('body', $this->scheduleSchema()))->setRequired(),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        $startsAt = new DateTimeImmutable($params['body']['startsAt']);
        $endsAt = new DateTimeImmutable($params['body']['endsAt']);
        if ($endsAt <= $startsAt) {
            return new JsonApiResponse(400, [
                'status' => 'error',
                'message' => 'wrong input',
                'detail' => ['endsAt' => ['End must be later than start']],
            ]);
        }

        return $this->schedule->create($startsAt, $endsAt);
    }
}
```

With a DTO, simple conversion and RFC 3339 parsing are declarative. A custom constraint owns the cross-field rule, keeping the transport response consistent:

```php
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class LaterThan implements ConstraintInterface
{
    public function __construct(private string $otherProperty)
    {
    }

    public function validate(mixed $value, ConstraintContext $context): ?string
    {
        $other = $context->value($this->otherProperty);
        if ($value instanceof DateTimeImmutable && $other instanceof DateTimeImmutable && $value <= $other) {
            return sprintf('Must be later than %s', $this->otherProperty);
        }

        return null;
    }
}

final readonly class ScheduleInput
{
    public function __construct(
        #[Body] public DateTimeImmutable $startsAt,
        #[Body] #[LaterThan('startsAt')] public DateTimeImmutable $endsAt,
    ) {
    }
}

final class ScheduleHandler extends TypedHandler
{
    public function handleDto(ScheduleInput $input): ResponseInterface
    {
        return $this->schedule->create($input->startsAt, $input->endsAt);
    }
}
```

`ConstraintContext::value()` reads sibling values in the same DTO, so cross-property constraints do not have to inspect the HTTP request. Business rules that need a database still belong in application services; a constraint should remain a deterministic request-contract check.

### 7. Both styles in one application

An endpoint may depend on the looser `InputType::INTEGER` conversion of `params()`, on a custom `InputParam`, or on manual parsing of an unusual content type. `params()` is the right style for it:

```php
final class ReportHandler extends BaseHandler
{
    public function params(): array
    {
        return [new GetInputParam('offset', InputType::INTEGER)];
    }

    public function handle(array $params): ResponseInterface
    {
        // This endpoint intentionally uses the looser params() conversion.
        return $this->reports->generate($params['offset']);
    }
}
```

Its neighboring `ListReportsHandler` can extend `TypedHandler`. Mixing the styles is supported without any limit; the choice is about ergonomics for each endpoint.

### Checklist for rewriting a handler to a DTO

1. **Capture the current contract:** save endpoint tests and the current OpenAPI operation, including defaults and error behavior.
2. **Create a readonly DTO:** one promoted property per query, path, header, body or file input.
3. **Preserve external names:** use explicit attribute names such as `#[Header('X-Request-Id')]` where the property name differs.
4. **Match requiredness:** non-nullable without a default means required; nullable or defaulted means optional.
5. **Replace manual declarations:** use backed enums for allowed values and the built-in constraints where they match existing checks.
6. **Handle nested data:** create a nested DTO and add `list<ItemInput>` PHPDoc for DTO arrays.
7. **Move only contract validation:** keep database lookups and business rules in services or the handler; use custom constraints for deterministic input rules.
8. **Switch the base class:** replace `handle(array $params)` with `handleDto(Input $input)` and remove manual casts/array lookups.
9. **Compare behavior:** run success and failure requests, inspect field paths, compare OpenAPI and check the console form and upload behavior.
10. **Review compatibility:** call out the stricter conversion in your changelog. Keep `params()` for the endpoint if its current behavior must remain.

Rewriting a DTO to `params()` goes the same way in reverse: declare one `InputParam` per property with the same name, required flag, default and allowed values, move the constraints into the handler or a custom `InputParam`, and compare the OpenAPI operation again.

