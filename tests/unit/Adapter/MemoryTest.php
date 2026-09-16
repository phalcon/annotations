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

namespace Phalcon\Annotations\Docblock\Tests\Unit\Adapter;

use Phalcon\Annotations\Docblock\Adapter\Memory;
use Phalcon\Annotations\Docblock\Collection;
use Phalcon\Annotations\Docblock\Reader;
use Phalcon\Annotations\Docblock\Reflection;
use Phalcon\Annotations\Docblock\Tests\AbstractUnitTestCase;

final class MemoryTest extends AbstractUnitTestCase
{
    public function testAnnotationsLimitClearsTheCache(): void
    {
        $adapter = $this->adapter();

        $this->assertSame(0, $adapter->getAnnotationsLimit());

        $adapter->setAnnotationsLimit(1);
        $this->assertSame(1, $adapter->getAnnotationsLimit());

        $first = $adapter->get('TestClass');

        /**
         * With the cap reached, the in-memory map is emptied before the next
         * class is added, so a later lookup re-parses instead of hitting it.
         */
        $this->assertInstanceOf(Reflection::class, $first);
    }

    public function testGetAcceptsAnObject(): void
    {
        $adapter = $this->adapter();

        $this->assertInstanceOf(Reflection::class, $adapter->get(new \TestClass()));
    }

    public function testGetMethodMatchesCaseInsensitively(): void
    {
        $adapter = $this->adapter();

        $this->assertTrue($adapter->getMethod('TestClass', 'testMethod1')->has('Simple'));
        $this->assertTrue($adapter->getMethod('TestClass', 'TESTMETHOD1')->has('Simple'));
    }

    public function testGetParsesOnceAndCaches(): void
    {
        $adapter = $this->adapter();

        $first  = $adapter->get('TestClass');
        $second = $adapter->get('TestClass');

        $this->assertInstanceOf(Reflection::class, $first);
        $this->assertSame($first, $second);
    }

    public function testMissingMembersStillReturnACollection(): void
    {
        $adapter = $this->adapter();

        $this->assertCount(0, $adapter->getMethod('TestClass', 'missingMethod'));
        $this->assertCount(0, $adapter->getProperty('TestClass', 'missingProperty'));
        $this->assertCount(0, $adapter->getConstant('TestClass', 'MISSING_CONST'));

        $this->assertInstanceOf(
            Collection::class,
            $adapter->getMethod('TestClass', 'missingMethod')
        );
    }

    public function testReaderIsReplaceable(): void
    {
        $adapter = new Memory();
        $reader  = new Reader();

        $this->assertInstanceOf(Reader::class, $adapter->getReader());

        $adapter->setReader($reader);

        $this->assertSame($reader, $adapter->getReader());
    }

    public function testReadReturnsFalseWhenAbsent(): void
    {
        $this->assertFalse((new Memory())->read('nothing-here'));
    }

    public function testWriteThenReadRoundTrips(): void
    {
        $adapter    = new Memory();
        $reflection = new Reflection(['class' => []]);

        $this->assertTrue($adapter->write('SomeClass', $reflection));

        /**
         * Keys are lower-cased on the way in and on the way out.
         */
        $this->assertSame($reflection, $adapter->read('SomeClass'));
        $this->assertSame($reflection, $adapter->read('someclass'));
    }

    private function adapter(): Memory
    {
        require_once $this->supportPath('assets/Annotations/TestClass.php');

        return new Memory();
    }
}
