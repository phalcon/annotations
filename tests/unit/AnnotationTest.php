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

namespace Phalcon\Annotations\Docblock\Tests\Unit;

use Phalcon\Annotations\Docblock\Annotation;
use Phalcon\Annotations\Docblock\Exceptions\UnknownAnnotationExpression;
use Phalcon\Annotations\Docblock\Reader;
use Phalcon\Annotations\Docblock\Tests\AbstractUnitTestCase;

final class AnnotationTest extends AbstractUnitTestCase
{
    public function testResolvesArrayAndHashExpressions(): void
    {
        $annotation = $this->annotationFor('/** @Foo({1, 2}, {key: "v", other = 3}) */');

        $this->assertSame(['1', '2'], $annotation->getArgument(0));
        $this->assertSame(['key' => 'v', 'other' => '3'], $annotation->getArgument(1));
    }

    public function testResolvesLiteralsToPhpValues(): void
    {
        $annotation = $this->annotationFor(
            '/** @Foo("str", ident, 1, 1.1, -10, true, false, null) */'
        );

        $this->assertSame('Foo', $annotation->getName());
        $this->assertSame(8, $annotation->numberArguments());

        /**
         * Integers and doubles stay strings: the parser records the lexeme and
         * cphalcon never converted it.
         */
        $this->assertSame('str', $annotation->getArgument(0));
        $this->assertSame('ident', $annotation->getArgument(1));
        $this->assertSame('1', $annotation->getArgument(2));
        $this->assertSame('1.1', $annotation->getArgument(3));
        $this->assertSame('-10', $annotation->getArgument(4));
        $this->assertTrue($annotation->getArgument(5));
        $this->assertFalse($annotation->getArgument(6));
        $this->assertNull($annotation->getArgument(7));
    }

    public function testResolvesNamedArguments(): void
    {
        $annotation = $this->annotationFor('/** @Foo(first, second="other", third: 3) */');

        $this->assertTrue($annotation->hasArgument(0));
        $this->assertTrue($annotation->hasArgument('second'));
        $this->assertFalse($annotation->hasArgument('missing'));

        $this->assertSame('other', $annotation->getNamedArgument('second'));
        $this->assertSame('other', $annotation->getNamedParameter('second'));
        $this->assertSame('3', $annotation->getNamedArgument('third'));
        $this->assertNull($annotation->getNamedArgument('missing'));
    }

    public function testResolvesNestedAnnotationToAnnotation(): void
    {
        $annotation = $this->annotationFor('/** @Outer(@Inner(1)) */');
        $inner      = $annotation->getArgument(0);

        $this->assertInstanceOf(Annotation::class, $inner);
        $this->assertSame('Inner', $inner->getName());
        $this->assertSame('1', $inner->getArgument(0));
    }

    public function testThrowsOnUnknownExpressionType(): void
    {
        $annotation = $this->annotationFor('/** @Foo(1) */');

        $this->expectException(UnknownAnnotationExpression::class);
        $this->expectExceptionMessage('The expression 999 is unknown');

        $annotation->getExpression(['type' => 999]);
    }

    public function testUnresolvedArgumentsArePreserved(): void
    {
        $annotation = $this->annotationFor('/** @Foo(1) */');

        $this->assertSame(
            [['expr' => ['type' => 301, 'value' => '1']]],
            $annotation->getExprArguments()
        );
    }

    private function annotationFor(string $docBlock): Annotation
    {
        $parsed = Reader::parseDocBlock($docBlock);

        $this->assertIsArray($parsed);

        return new Annotation($parsed[0]);
    }
}
