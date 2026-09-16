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
use Phalcon\Annotations\Docblock\Collection;
use Phalcon\Annotations\Docblock\Exceptions\AnnotationNotFound;
use Phalcon\Annotations\Docblock\Reader;
use Phalcon\Annotations\Docblock\Tests\AbstractUnitTestCase;

use function count;
use function iterator_to_array;

final class CollectionTest extends AbstractUnitTestCase
{
    public function testCurrentReturnsFalsePastTheEnd(): void
    {
        $collection = new Collection();

        $this->assertFalse($collection->current());
        $this->assertFalse($collection->valid());
    }

    public function testGetAllReturnsEveryMatch(): void
    {
        $collection = $this->collection();

        $this->assertCount(2, $collection->getAll('Repeated'));
        $this->assertCount(0, $collection->getAll('Missing'));
    }

    public function testGetReturnsFirstMatch(): void
    {
        $collection = $this->collection();
        $annotation = $collection->get('Repeated');

        $this->assertSame('Repeated', $annotation->getName());
        $this->assertSame('first', $annotation->getArgument(0));
    }

    public function testGetThrowsWhenAbsent(): void
    {
        $this->expectException(AnnotationNotFound::class);
        $this->expectExceptionMessage("Collection does not have an annotation called 'Missing'");

        $this->collection()->get('Missing');
    }

    public function testHasChecksByName(): void
    {
        $collection = $this->collection();

        $this->assertTrue($collection->has('Simple'));
        $this->assertFalse($collection->has('Missing'));
    }

    public function testIsCountableAndIterable(): void
    {
        $collection = $this->collection();

        $this->assertCount(3, $collection);
        $this->assertSame(3, count($collection->getAnnotations()));

        $names = [];

        foreach ($collection as $key => $annotation) {
            $this->assertIsInt($key);
            $this->assertInstanceOf(Annotation::class, $annotation);

            $names[] = $annotation->getName();
        }

        $this->assertSame(['Simple', 'Repeated', 'Repeated'], $names);

        /**
         * Rewinding must let a second pass see everything again.
         */
        $this->assertCount(3, iterator_to_array($collection, false));
    }

    private function collection(): Collection
    {
        $parsed = Reader::parseDocBlock(
            '/** @Simple @Repeated(first) @Repeated(second) */'
        );

        $this->assertIsArray($parsed);

        return new Collection($parsed);
    }
}
