<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\DI;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\DI\Hint;

final class HintTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>, string}>
     */
    public static function provideSuggestions(): iterable
    {
        // a short value accepts up to 2 edits
        yield 'short, 2 edits' => ['ab', ['cd'], " Did you mean 'cd'?"];
        yield 'short, 3 edits' => ['ab', ['xyz'], ''];
        // a long value accepts up to a third of its length
        yield 'long, 4 edits' => ['abcdefghijkl', ['abcdefghwxyz'], " Did you mean 'abcdefghwxyz'?"];
        yield 'long, 5 edits' => ['abcdefghijkl', ['abcdefgvwxyz'], ''];
        yield 'value case is ignored' => ['ATUO', ['auto'], " Did you mean 'auto'?"];
        yield 'option case is ignored' => ['atuo', ['AUTO'], " Did you mean 'AUTO'?"];
        yield 'first of equally close' => ['abc', ['abd', 'abe'], " Did you mean 'abd'?"];
        yield 'closest wins' => ['abcd', ['abxy', 'abcx'], " Did you mean 'abcx'?"];
        yield 'no options' => ['abc', [], ''];
    }

    /**
     * @param list<string> $options
     */
    #[DataProvider('provideSuggestions')]
    public function testDidYouMean(string $value, array $options, string $expected): void
    {
        $this->assertSame($expected, Hint::didYouMean($value, $options));
    }

    public function testQuoted(): void
    {
        $this->assertSame("'a', 'b'", Hint::quoted(['a', 'b']));
    }
}
