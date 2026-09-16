<?php

/**
 * This file is part of the Phalcon Framework.
 *
 * (c) Phalcon Team <team@phalcon.io>
 *
 * For the full copyright and license information, please view the LICENSE.txt
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Phalcon\Annotations\Docblock\Tests\Unit\Parser;

use Phalcon\Annotations\Docblock\Exception;
use Phalcon\Annotations\Docblock\Reader;
use Phalcon\Annotations\Docblock\Tests\AbstractUnitTestCase;

/**
 * The syntax-error strings are part of the contract: cphalcon's own test suite
 * asserts on them, so anything using this package as a drop-in replacement can
 * too.
 */
final class ErrorsTest extends AbstractUnitTestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function messageProvider(): array
    {
        return [
            'unexpected token with a value' => [
                '/** @Foo(1 2) */',
                "Syntax error, unexpected token INTEGER(2), near to ') ' in eval code on line 1",
            ],
            'unexpected token without a value' => [
                '/** @Foo(1]) */',
                "Syntax error, unexpected token ], near to ') ' in eval code on line 1",
            ],
            'unexpected eof' => [
                "/**\n * @Invalid(\n */",
                'Syntax error, unexpected EOF in eval code',
            ],
            'empty braces are not an empty array' => [
                '/** @Foo({}) */',
                "Syntax error, unexpected token }, near to ') ' in eval code on line 1",
            ],
            'bare dash does not scan' => [
                '/** @Foo(-) */',
                "Scanning error before ') ' in eval code on line 1",
            ],
        ];
    }

    public function testDeepNestingFailsClosed(): void
    {
        $docBlock = '/** @Foo(' . str_repeat('{', 300) . str_repeat('}', 300) . ') */';

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('nested too deeply');

        Reader::parseDocBlock($docBlock);
    }

    /**
     * @dataProvider messageProvider
     */
    public function testProducesTheCphalconMessage(string $docBlock, string $expected): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage($expected);

        Reader::parseDocBlock($docBlock);
    }

    public function testTokenNamesMissingFromTheCTableReportUnknown(): void
    {
        /**
         * NULL, TRUE and FALSE have no entry in cphalcon's `phannot_tokens[]`,
         * so an error naming one of them says UNKNOWN.
         */
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('unexpected token UNKNOWN,');

        Reader::parseDocBlock('/** @Foo(1 null) */');
    }
}
