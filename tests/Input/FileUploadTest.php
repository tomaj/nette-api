<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Input;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Api;
use Tomaj\NetteApi\Authorization\NoAuthorization;
use Tomaj\NetteApi\EndpointIdentifier;
use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Input\FileUpload;
use Tomaj\NetteApi\Input\RequestInput;
use Tomaj\NetteApi\Input\UnsupportedMediaTypeException;
use Tomaj\NetteApi\OpenApi\PathBuilder;
use Tomaj\NetteApi\OpenApi\SchemaRegistry;
use Tomaj\NetteApi\Params\ParamsProcessor;
use Tomaj\NetteApi\Params\PathValues;
use Tomaj\NetteApi\Test\Input\Fixtures\AvatarHandler;
use Tomaj\NetteApi\Test\Input\Fixtures\DocumentHandler;
use Tomaj\NetteApi\Test\Input\Fixtures\FilesHandler;
use Tomaj\NetteApi\Test\Input\Fixtures\ScanFirstHandler;
use Tomaj\NetteApi\Test\Input\Fixtures\UserStatus;
use Tomaj\NetteApi\Input\SchemaBuilder;

final class FileUploadTest extends TestCase
{
    protected function setUp(): void
    {
        $_GET = [];
        $_POST = [];
        $_FILES = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['CONTENT_TYPE'] = 'multipart/form-data; boundary=x';
        RequestInput::reset();
        PathValues::reset();
    }

    public function testDefaultMediaTypesWithAFileAreJsonAndMultipart(): void
    {
        // a required file cannot be sent as JSON, so only multipart is advertised for it
        $this->assertSame(['multipart/form-data'], array_keys($this->requestBody(new AvatarHandler())['content']));
        $this->assertSame(['application/json', 'multipart/form-data'], array_keys($this->requestBody(new DocumentHandler())['content']));
    }

    public function testUrlEncodedFormIsRejectedWhenTheDtoHasAFile(): void
    {
        $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
        $_POST = ['displayName' => 'Ada'];
        try {
            $this->processor(new AvatarHandler())->isError();
            $this->fail('form was accepted');
        } catch (UnsupportedMediaTypeException $exception) {
            $this->assertSame("Content-Type 'application/x-www-form-urlencoded' is not supported for this endpoint. Use one of: application/json, multipart/form-data.", $exception->getMessage());
        }
    }

    public function testOtherContentTypeIsRejected(): void
    {
        $_SERVER['CONTENT_TYPE'] = 'text/plain';
        $this->expectException(UnsupportedMediaTypeException::class);
        $this->expectExceptionMessage("Content-Type 'text/plain' is not supported for this endpoint. Use one of: application/json, multipart/form-data.");
        $this->processor(new AvatarHandler())->isError();
    }

    public function testOpenApiListsFilesOnlyInMultipart(): void
    {
        $content = $this->requestBody(new AvatarHandler())['content'];
        $multipart = $content['multipart/form-data']['schema'];
        $this->assertSame(['type' => 'string', 'format' => 'binary', 'description' => 'PNG or JPEG image'], $multipart['properties']['avatar']);
        $this->assertSame(['displayName', 'avatar'], $multipart['required']);
        $this->assertSame('object', $multipart['type']);

        // optional files: JSON stays advertised, without them
        $json = $this->requestBody(new DocumentHandler())['content']['application/json']['schema'];
        $this->assertSame(['issuedOn', 'copies'], array_keys($json['properties']));
        $this->assertSame(['issuedOn', 'copies'], $json['required']);
    }

    public function testOpenApiFileListIsAnArrayOfBinaries(): void
    {
        $content = $this->requestBody(new FilesHandler())['content'];
        $this->assertSame(['type' => 'array', 'items' => ['type' => 'string', 'format' => 'binary']], $content['multipart/form-data']['schema']['properties']['files']);
        $this->assertSame(['files'], $content['multipart/form-data']['schema']['required']);
        $this->assertSame(['multipart/form-data'], array_keys($content));
    }

    public function testOpenApiOfARequiredFileListsRequiredFieldsInMultipartOnly(): void
    {
        $content = $this->requestBody(new ScanFirstHandler())['content'];
        $this->assertSame(['multipart/form-data'], array_keys($content));
        $this->assertSame(['scan', 'title'], $content['multipart/form-data']['schema']['required']);

        $_POST = ['title' => 'Invoice'];
        $_FILES = ['scan' => self::file('s.pdf', 3)];
        $this->assertSame(['title' => 'Invoice', 'scan' => 's.pdf'], $this->payload(new ScanFirstHandler()));
    }

    public function testListEntryWithoutErrorsIsPassedOn(): void
    {
        $_FILES = ['files' => ['name' => ['a.txt'], 'tmp_name' => ['/tmp/a']]];
        $this->assertSame(['count' => 1, 'name' => 'a.txt'], $this->payload(new FilesHandler()));

        $_FILES = ['files' => ['name' => ['b.txt'], 'error' => []]];
        RequestInput::reset();
        $this->assertSame(['count' => 1, 'name' => 'b.txt'], $this->payload(new FilesHandler()));
    }

    public function testListKeepsLaterKeysWhenOneKeyIsMissing(): void
    {
        $_POST = ['issuedOn' => '24.12.2026', 'copies' => '1'];
        $_FILES = ['attachments' => ['name' => ['a.txt'], 'error' => [UPLOAD_ERR_PARTIAL]]];
        $this->assertSame([['a.txt', UPLOAD_ERR_PARTIAL]], $this->payload(new DocumentHandler())['attachments']);
    }

    public function testWireConvertsNestedValues(): void
    {
        $this->assertSame(
            ['status' => 'active', 'dates' => ['at' => '2026-01-02T03:04:05+00:00'], 'n' => 1],
            SchemaBuilder::wire(['status' => UserStatus::Active, 'dates' => ['at' => new \DateTimeImmutable('2026-01-02T03:04:05Z')], 'n' => 1]),
        );
    }

    public function testOpenApiOptionalFilesAreNotRequired(): void
    {
        $content = $this->requestBody(new DocumentHandler())['content'];
        $this->assertSame(['issuedOn', 'copies'], $content['multipart/form-data']['schema']['required']);
        $this->assertSame(['issuedOn', 'copies', 'scan', 'attachments'], array_keys($content['multipart/form-data']['schema']['properties']));
        $this->assertSame(['issuedOn', 'copies'], array_keys($content['application/json']['schema']['properties']));
        $this->assertSame(['issuedOn', 'copies'], $content['application/json']['schema']['required']);
    }

    public function testSingleRequiredFileWithScalarField(): void
    {
        $_POST = ['displayName' => 'Ada'];
        $_FILES = ['avatar' => self::file('a.png', 12)];
        $payload = $this->payload(new AvatarHandler());
        $this->assertSame(['name' => 'Ada', 'file' => 'a.png', 'size' => 12, 'error' => UPLOAD_ERR_OK, 'type' => 'image/png', 'path' => '/tmp/php-a.png'], $payload);
    }

    public function testMissingRequiredFileIs400WithFieldPath(): void
    {
        $_POST = ['displayName' => 'Ada'];
        $this->assertSame(['avatar' => ['Field is required']], $this->errors(new AvatarHandler()));
    }

    public function testEmptyFileInputCountsAsMissing(): void
    {
        $_POST = ['displayName' => 'Ada'];
        $_FILES = ['avatar' => ['name' => '', 'type' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0]];
        $this->assertSame(['avatar' => ['Field is required']], $this->errors(new AvatarHandler()));
    }

    public function testRequiredFileAndInvalidSiblingReportBothPaths(): void
    {
        $_POST = ['displayName' => ''];
        $this->assertSame(['displayName' => ['Value must have between 1 and 80 characters'], 'avatar' => ['Field is required']], $this->errors(new AvatarHandler()));
    }

    public function testJsonRequestToADtoWithARequiredFileExplainsMultipart(): void
    {
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        RequestInput::setRawBody('{"displayName":"Ada","avatar":"aGVsbG8="}');
        $this->assertSame(
            ['avatar' => ['File is required and can only be uploaded as multipart/form-data, but the request is application/json']],
            $this->errors(new AvatarHandler()),
        );
    }

    public function testMissingContentTypeWithARequiredFileIs415(): void
    {
        unset($_SERVER['CONTENT_TYPE']);
        $this->expectException(UnsupportedMediaTypeException::class);
        $this->expectExceptionMessage('Content-Type is missing. Use one of: application/json, multipart/form-data.');
        $this->processor(new FilesHandler())->isError();
    }

    public function testJsonRequestWithOptionalFilesSucceeds(): void
    {
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        RequestInput::setRawBody('{"issuedOn":"24.12.2026","copies":2}');
        $this->assertSame(['issuedOn' => '2026-12-24', 'copies' => 2, 'scan' => null, 'attachments' => []], $this->payload(new DocumentHandler()));
    }

    public function testOptionalFileMissingAndCastAndConstraintOnSiblings(): void
    {
        $_POST = ['issuedOn' => '24.12.2026', 'copies' => '3'];
        $this->assertSame(['issuedOn' => '2026-12-24', 'copies' => 3, 'scan' => null, 'attachments' => []], $this->payload(new DocumentHandler()));

        $_POST = ['issuedOn' => '31.02.2026', 'copies' => '11'];
        $_FILES = ['scan' => self::file('s.pdf', 5)];
        RequestInput::reset();
        $this->assertSame([
            'issuedOn' => ['Date must match d.m.Y, for example 24.12.2026'],
            'copies' => ['Value must be between 1 and 10'],
        ], $this->errors(new DocumentHandler()));

        $_POST = ['issuedOn' => '24.12.2026', 'copies' => '1a'];
        RequestInput::reset();
        $this->assertSame(['copies' => ['Value must be an integer']], $this->errors(new DocumentHandler()));
    }

    public function testOptionalFileAndListTogether(): void
    {
        $_POST = ['issuedOn' => '24.12.2026', 'copies' => '1'];
        $_FILES = [
            'scan' => self::file('s.pdf', 5),
            'attachments' => self::files(['a.txt', 'b.txt'], [UPLOAD_ERR_OK, UPLOAD_ERR_OK]),
        ];
        $this->assertSame(
            ['issuedOn' => '2026-12-24', 'copies' => 1, 'scan' => 's.pdf', 'attachments' => [['a.txt', UPLOAD_ERR_OK], ['b.txt', UPLOAD_ERR_OK]]],
            $this->payload(new DocumentHandler()),
        );
    }

    public function testListWithOnlyEmptySlotsCountsAsMissing(): void
    {
        $_FILES = ['files' => self::files(['', ''], [UPLOAD_ERR_NO_FILE, UPLOAD_ERR_NO_FILE])];
        $this->assertSame(['files' => ['Field is required']], $this->errors(new FilesHandler()));

        $_POST = ['issuedOn' => '24.12.2026', 'copies' => '1'];
        $_FILES = ['attachments' => self::files([''], [UPLOAD_ERR_NO_FILE])];
        RequestInput::reset();
        $this->assertSame([], $this->payload(new DocumentHandler())['attachments']);
    }

    public function testListWithOneFileIsAList(): void
    {
        $_FILES = ['files' => self::file('only.txt', 1)];
        $this->assertSame(['count' => 1, 'name' => 'only.txt'], $this->payload(new FilesHandler()));
    }

    public function testListKeepsAMixOfEmptyAndRealSlots(): void
    {
        $_POST = ['issuedOn' => '24.12.2026', 'copies' => '1'];
        $_FILES = ['attachments' => self::files(['', 'b.txt'], [UPLOAD_ERR_NO_FILE, UPLOAD_ERR_OK])];
        $this->assertSame([['', UPLOAD_ERR_NO_FILE], ['b.txt', UPLOAD_ERR_OK]], $this->payload(new DocumentHandler())['attachments']);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function uploadErrors(): iterable
    {
        yield 'ini size' => [UPLOAD_ERR_INI_SIZE];
        yield 'form size' => [UPLOAD_ERR_FORM_SIZE];
        yield 'partial' => [UPLOAD_ERR_PARTIAL];
        yield 'no tmp dir' => [UPLOAD_ERR_NO_TMP_DIR];
        yield 'cant write' => [UPLOAD_ERR_CANT_WRITE];
        yield 'extension' => [UPLOAD_ERR_EXTENSION];
    }

    #[DataProvider('uploadErrors')]
    public function testUploadErrorsReachTheHandler(int $error): void
    {
        $_POST = ['displayName' => 'Ada'];
        $_FILES = ['avatar' => ['name' => 'big.png', 'type' => 'image/png', 'tmp_name' => '', 'error' => $error, 'size' => 0]];
        $payload = $this->payload(new AvatarHandler());
        $this->assertSame($error, $payload['error']);
        $this->assertSame('big.png', $payload['file']);
    }

    public function testNoFileErrorWithANameIsPassedOn(): void
    {
        $_POST = ['displayName' => 'Ada'];
        $_FILES = ['avatar' => ['name' => 'x.png', 'error' => UPLOAD_ERR_NO_FILE]];
        $this->assertSame(UPLOAD_ERR_NO_FILE, $this->payload(new AvatarHandler())['error']);
    }

    public function testUploadErrorInsideAList(): void
    {
        $_FILES = ['files' => self::files(['a.txt', 'b.txt'], [UPLOAD_ERR_OK, UPLOAD_ERR_PARTIAL])];
        $this->assertSame(['count' => 2, 'name' => 'a.txt'], $this->payload(new FilesHandler()));
        $processor = $this->processor(new FilesHandler());
        $files = $processor->getValues()['files'];
        $this->assertIsArray($files);
        $this->assertInstanceOf(FileUpload::class, $files[1]);
        $this->assertSame(['b.txt', UPLOAD_ERR_PARTIAL], [$files[1]->originalName, $files[1]->error]);
        $this->assertSame(UPLOAD_ERR_OK, $files[0]->error);
    }

    public function testArrayOfFilesForASingleFileIsRejected(): void
    {
        $_POST = ['displayName' => 'Ada'];
        $_FILES = ['avatar' => self::files(['a.png', 'b.png'], [UPLOAD_ERR_OK, UPLOAD_ERR_OK])];
        $this->assertSame(['avatar' => ['Value must be a file upload']], $this->errors(new AvatarHandler()));
    }

    public function testMalformedFileEntriesAreMissing(): void
    {
        $_POST = ['displayName' => 'Ada'];
        $_FILES = ['avatar' => 'not-an-array'];
        $this->assertSame(['avatar' => ['Field is required']], $this->errors(new AvatarHandler()));
    }

    public function testFileUploadFromIncompleteEntryUsesSafeDefaults(): void
    {
        $upload = FileUpload::fromFile([]);
        $this->assertSame(['', '', '', 0, UPLOAD_ERR_NO_FILE], [$upload->originalName, $upload->clientContentType, $upload->temporaryPath, $upload->size, $upload->error]);

        $upload = FileUpload::fromFile(['name' => 1, 'type' => [], 'tmp_name' => null, 'size' => '5', 'error' => '0']);
        $this->assertSame(['', '', '', 0, UPLOAD_ERR_NO_FILE], [$upload->originalName, $upload->clientContentType, $upload->temporaryPath, $upload->size, $upload->error]);
    }

    public function testMediaTypeIsTrackedPerRequest(): void
    {
        $_POST = ['displayName' => 'Ada'];
        $_FILES = ['avatar' => self::file('a.png', 1)];
        $this->assertFalse($this->processor(new AvatarHandler())->isError());
        $this->assertSame('multipart/form-data', RequestInput::mediaType());

        RequestInput::reset();
        $this->assertNull(RequestInput::mediaType());
    }

    /**
     * @return array<string, mixed>
     */
    private static function file(string $name, int $size): array
    {
        return ['name' => $name, 'type' => 'image/png', 'tmp_name' => '/tmp/php-' . $name, 'error' => UPLOAD_ERR_OK, 'size' => $size];
    }

    /**
     * @param list<string> $names
     * @param list<int> $errors
     * @return array<string, list<mixed>>
     */
    private static function files(array $names, array $errors): array
    {
        return [
            'name' => $names,
            'type' => array_fill(0, count($names), 'text/plain'),
            'tmp_name' => array_map(static fn (string $name): string => $name === '' ? '' : '/tmp/php-' . $name, $names),
            'error' => $errors,
            'size' => array_fill(0, count($names), 1),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function requestBody(BaseHandler $handler): array
    {
        $operation = new PathBuilder()->buildOperation(
            new Api(new EndpointIdentifier('POST', 1, 'upload'), $handler, new NoAuthorization()),
            new SchemaRegistry(),
        );

        return $operation['requestBody'];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(BaseHandler $handler): array
    {
        $processor = $this->processor($handler);
        $this->assertFalse($processor->isError(), (string) json_encode($processor->getErrors()));

        return $handler->handle($processor->getValues())->getPayload();
    }

    /**
     * @return array<string, mixed>
     */
    private function errors(BaseHandler $handler): array
    {
        $processor = $this->processor($handler);
        $this->assertTrue($processor->isError());

        return $processor->getErrors();
    }

    private function processor(BaseHandler $handler): ParamsProcessor
    {
        return new ParamsProcessor($handler->params());
    }
}
