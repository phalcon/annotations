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

use Phalcon\Annotations\Docblock\Collection;
use Phalcon\Annotations\Docblock\Reader;
use Phalcon\Annotations\Docblock\Reflection;
use Phalcon\Annotations\Docblock\Tests\AbstractUnitTestCase;

final class ReflectionTest extends AbstractUnitTestCase
{
    public function testEmptyReflectionReturnsNullAndEmptyArrays(): void
    {
        $reflection = new Reflection();

        $this->assertNull($reflection->getClassAnnotations());
        $this->assertSame([], $reflection->getConstantsAnnotations());
        $this->assertSame([], $reflection->getMethodsAnnotations());
        $this->assertSame([], $reflection->getPropertiesAnnotations());
        $this->assertSame([], $reflection->getReflectionData());
    }

    public function testExposesEveryMemberGroupAsCollections(): void
    {
        $reflection = $this->reflection();

        $classAnnotations = $reflection->getClassAnnotations();

        $this->assertInstanceOf(Collection::class, $classAnnotations);
        $this->assertTrue($classAnnotations->has('Simple'));

        $constants = $reflection->getConstantsAnnotations();
        $this->assertArrayHasKey('TEST_CONST1', $constants);
        $this->assertInstanceOf(Collection::class, $constants['TEST_CONST1']);

        /**
         * TEST_CONST2 has no docblock, so it must not appear at all.
         */
        $this->assertArrayNotHasKey('TEST_CONST2', $constants);

        $properties = $reflection->getPropertiesAnnotations();
        $this->assertArrayHasKey('testProp1', $properties);
        $this->assertInstanceOf(Collection::class, $properties['testProp1']);

        $methods = $reflection->getMethodsAnnotations();
        $this->assertArrayHasKey('testMethod1', $methods);
        $this->assertInstanceOf(Collection::class, $methods['testMethod1']);
        $this->assertTrue($methods['testMethod1']->has('NamedMultipleParams'));
    }

    public function testKeepsTheRawParsedData(): void
    {
        $reflection = $this->reflection();

        $this->assertArrayHasKey('class', $reflection->getReflectionData());
    }

    private function reflection(): Reflection
    {
        require_once $this->supportPath('assets/Annotations/TestClass.php');

        return new Reflection((new Reader())->parse('TestClass'));
    }
}
