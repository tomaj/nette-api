<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Input;

use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Validation\ConstraintContext;
use Tomaj\NetteApi\Validation\Email;
use Tomaj\NetteApi\Validation\Length;
use Tomaj\NetteApi\Validation\Pattern;
use Tomaj\NetteApi\Validation\Range;

final class ConstraintTest extends TestCase
{
    private ConstraintContext $context;

    protected function setUp(): void
    {
        $this->context = new ConstraintContext('page', 'Dto', ['page', 'name'], ['page' => 1, 'name' => 'ada']);
    }

    public function testRangeBounds(): void
    {
        $both = new Range(min: 1, max: 10);
        $this->assertNull($both->validate(1, $this->context));
        $this->assertNull($both->validate(10, $this->context));
        $this->assertSame('Value must be between 1 and 10', $both->validate(0, $this->context));
        $this->assertSame('Value must be between 1 and 10', $both->validate(11, $this->context));
        $this->assertSame('Value must be at least 1', new Range(min: 1)->validate(0, $this->context));
        $this->assertSame('Value must be at most 10', new Range(max: 10)->validate(11, $this->context));
        $this->assertSame('Range applies to an integer or a float', $both->validate('1', $this->context));
        $this->assertNull(new Range(min: 1.5, max: 2.5)->validate(1.5, $this->context));
        $this->assertSame('Value must be between 1.5 and 2.5', new Range(min: 1.5, max: 2.5)->validate(3, $this->context));
    }

    public function testRangeConstructorRejectsEmptyAndInvertedBounds(): void
    {
        try {
            new Range();
            $this->fail('empty range');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('min, max or both', $exception->getMessage());
        }
        $this->expectException(\InvalidArgumentException::class);
        new Range(min: 5, max: 1);
    }

    public function testRangeConstructorRejectsInfiniteAndNanBounds(): void
    {
        foreach ([['min' => INF], ['max' => INF], ['min' => -INF], ['max' => NAN], ['min' => 1, 'max' => NAN]] as $bounds) {
            try {
                new Range(...$bounds);
                $this->fail('non finite bound ' . var_export($bounds, true));
            } catch (\LogicException $exception) {
                $this->assertStringContainsString('must be a finite number', $exception->getMessage());
            }
        }
    }

    public function testLengthCountsCharactersAndItems(): void
    {
        $text = new Length(min: 1, max: 3);
        $this->assertNull($text->validate('áé', $this->context));
        $this->assertSame('Value must have between 1 and 3 characters', $text->validate('', $this->context));
        $this->assertSame('Value must have between 1 and 3 characters', $text->validate('abcd', $this->context));
        $this->assertSame('Value must have at least 2 characters', new Length(min: 2)->validate('a', $this->context));
        $this->assertSame('Value must have at most 2 items', new Length(max: 2)->validate([1, 2, 3], $this->context));
        $this->assertNull(new Length(min: 1, max: 2)->validate([1], $this->context));
        $this->assertSame('Value must have between 1 and 2 items', new Length(min: 1, max: 2)->validate([], $this->context));
        $this->assertSame('Length applies to a string or a list', $text->validate(1, $this->context));
    }

    public function testLengthConstructorRejectsBadBounds(): void
    {
        try {
            new Length();
            $this->fail('empty');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('min, max or both', $exception->getMessage());
        }
        try {
            new Length(min: -1);
            $this->fail('negative');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('zero or greater', $exception->getMessage());
        }
        $this->expectException(\InvalidArgumentException::class);
        new Length(min: 4, max: 1);
    }

    public function testPatternAndEmail(): void
    {
        $pattern = new Pattern('/^[0-9]{5}$/');
        $this->assertSame('^[0-9]{5}$', $pattern->expression());
        $this->assertNull($pattern->validate('81101', $this->context));
        $this->assertSame('Value must match the pattern /^[0-9]{5}$/', $pattern->validate('abc', $this->context));
        $this->assertSame('Pattern applies to a string', $pattern->validate(81101, $this->context));

        $email = new Email();
        $this->assertNull($email->validate('ada@example.com', $this->context));
        $this->assertSame('Email must be a valid email address', $email->validate('nope', $this->context));
        $this->assertSame('Email must be a valid email address', $email->validate(1, $this->context));
    }

    public function testPatternDollarDoesNotAcceptATrailingNewline(): void
    {
        $pattern = new Pattern('/^[0-9]{5}$/');
        $this->assertSame('Value must match the pattern /^[0-9]{5}$/', $pattern->validate("81101\n", $this->context));
        $this->assertNull(new Pattern('/^[0-9]{5}$/D')->validate('81101', $this->context));
        $this->assertNull(new Pattern('/^a$/m')->validate("a\nb", $this->context), 'the m flag keeps its meaning');
    }

    public function testPatternConstructorRejectsInvalidRegex(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Pattern('/[');
    }

    public function testContextReadsSiblingsAndRejectsUnknownNames(): void
    {
        $this->assertSame('ada', $this->context->value('name'));
        $this->assertNull($this->context->value('page') === 1 ? null : 'x');
        $this->assertSame(1, $this->context->value('page'));
        $missing = new ConstraintContext('endsAt', 'Schedule', ['startsAt', 'endsAt'], ['endsAt' => 1]);
        $this->assertNull($missing->value('startsAt'));
        $this->assertSame('endsAt', $missing->path);
        $this->assertSame('Schedule', $missing->dtoClass);

        $this->expectException(\LogicException::class);
        $this->context->value('nope');
    }

    public function testBoundsAcceptTheInclusiveEdge(): void
    {
        $range = new Range(min: 1, max: 1);
        $this->assertNull($range->validate(1, new ConstraintContext('n', 'Dto', ['n'], ['n' => 1])));
        $this->assertSame('1.5', new Range(min: 1.5, max: 2.5)->validate(1.5, new ConstraintContext('n', 'Dto', ['n'], ['n' => 1.5])) === null ? '1.5' : 'rejected');
        new Length(min: 2, max: 2);
        new Length(max: 0);
        $length = new Length(min: 0, max: 2);
        $this->assertNull($length->validate('', new ConstraintContext('s', 'Dto', ['s'], ['s' => ''])));
        $this->assertNull($length->validate('ab', new ConstraintContext('s', 'Dto', ['s'], ['s' => 'ab'])));
        $this->assertSame('Value must have between 0 and 2 characters', $length->validate('abc', new ConstraintContext('s', 'Dto', ['s'], ['s' => 'abc'])));
        $this->assertSame('Value must have at most 2 characters', new Length(max: 2)->validate('abc', new ConstraintContext('s', 'Dto', ['s'], ['s' => 'abc'])));
        $this->assertSame('^[0-9]{5}$', new Pattern('/^[0-9]{5}$/')->expression());
        try {
            new Length(max: -1);
            $this->fail('negative');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('zero or greater', $exception->getMessage());
        }
        $this->assertSame('Value must be at least 1.5', new Range(min: 1.5)->validate(1, new ConstraintContext('n', 'Dto', ['n'], ['n' => 1])));

        try {
            new ConstraintContext('page', 'Dto', ['page'], [])->value('missing');
            $this->fail('unknown');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString("value('page')", $exception->getMessage());
        }
    }

    public function testLengthCountsUtf8CharactersWhateverTheInternalEncoding(): void
    {
        $encoding = mb_internal_encoding();
        mb_internal_encoding('ISO-8859-1');
        try {
            $this->assertNull(new Length(max: 3)->validate('čšž', new ConstraintContext('x', self::class, [], [])));
        } finally {
            mb_internal_encoding($encoding);
        }
    }

    public function testASmallRangeBoundIsNotPrintedAsZero(): void
    {
        $context = new ConstraintContext('x', self::class, [], []);
        $this->assertSame('Value must be at most 1.0E-12', new Range(max: 1.0E-12)->validate(1.0, $context));
        $this->assertSame('Value must be at least -2.0E-11', new Range(min: -2.0E-11)->validate(-1.0, $context));
        $this->assertSame('Value must be at most 0.5', new Range(max: 0.5)->validate(1.0, $context));
        $this->assertSame('Value must be at least 0', new Range(min: 0)->validate(-1, $context));
        $this->assertSame('Value must be at most 0', new Range(max: 0.0)->validate(1, $context));
        $this->assertSame('Value must be at most 100', new Range(max: 100.0)->validate(101, $context));
        $this->assertSame('Value must be at most 9223372036854775806', new Range(max: PHP_INT_MAX - 1)->validate(PHP_INT_MAX, $context));
    }

    public function testRangePrintsMinusZeroAsZero(): void
    {
        $context = new ConstraintContext('x', self::class, [], []);

        $this->assertSame('Value must be at most 0', new Range(max: -0.0)->validate(1, $context));
    }
}
