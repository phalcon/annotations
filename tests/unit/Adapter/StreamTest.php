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

use Phalcon\Annotations\Docblock\Adapter\Stream;
use Phalcon\Annotations\Docblock\Exceptions\AnnotationsDirectoryNotWritable;
use Phalcon\Annotations\Docblock\Reflection;
use Phalcon\Annotations\Docblock\Tests\AbstractUnitTestCase;

use function file_put_contents;
use function glob;
use function is_dir;
use function mkdir;
use function restore_error_handler;
use function rmdir;
use function set_error_handler;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

use const E_WARNING;

final class StreamTest extends AbstractUnitTestCase
{
    private string $dir = '';

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/phalcon-annotations-' . uniqid() . '/';

        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0o777, true);
        }
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '*') ?: [] as $file) {
            unlink($file);
        }

        if (is_dir($this->dir)) {
            rmdir($this->dir);
        }
    }

    public function testCorruptCacheFileIsReportedNotReturned(): void
    {
        $adapter = new Stream(['annotationsDir' => $this->dir]);

        $adapter->write('Corrupt', new Reflection(['class' => []]));

        /**
         * Overwrite the cache file with something that is not valid serialized
         * data. unserialize() emits E_WARNING, which the adapter turns into an
         * explicit failure rather than silently handing back false data.
         */
        file_put_contents($this->dir . 'corrupt.php', 'not-serialized-at-all');

        $this->expectException(\Phalcon\Annotations\Docblock\Exceptions\CannotReadAnnotationData::class);

        $adapter->read('Corrupt');
    }

    public function testNamespacedKeysDoNotCollide(): void
    {
        $adapter = new Stream(['annotationsDir' => $this->dir]);

        $backslash  = new Reflection(['class' => [['type' => 300, 'name' => 'FromBackslash']]]);
        $underscore = new Reflection(['class' => [['type' => 300, 'name' => 'FromUnderscore']]]);

        $adapter->write('A\\B', $backslash);
        $adapter->write('A_B', $underscore);

        $readBackslash  = $adapter->read('A\\B');
        $readUnderscore = $adapter->read('A_B');

        $this->assertInstanceOf(Reflection::class, $readBackslash);
        $this->assertInstanceOf(Reflection::class, $readUnderscore);

        /**
         * "A\B" and "A_B" both flatten to "a_b", so the key containing an
         * underscore gets a hash suffix to keep the two files apart.
         */
        $this->assertSame(
            'FromBackslash',
            $readBackslash->getReflectionData()['class'][0]['name']
        );
        $this->assertSame(
            'FromUnderscore',
            $readUnderscore->getReflectionData()['class'][0]['name']
        );
    }

    public function testReadReturnsFalseWhenFileIsAbsent(): void
    {
        $adapter = new Stream(['annotationsDir' => $this->dir]);

        $this->assertFalse($adapter->read('NeverWritten'));
    }

    public function testUnwritableDirectoryThrows(): void
    {
        $adapter = new Stream(['annotationsDir' => '/this/path/does/not/exist/']);

        /**
         * file_put_contents() emits an E_WARNING of its own on the way to
         * returning false. That warning is the point of the test, not a problem
         * with it, so it is swallowed rather than left to surface as suite noise.
         */
        set_error_handler(static fn (): bool => true, E_WARNING);

        try {
            $this->expectException(AnnotationsDirectoryNotWritable::class);
            $this->expectExceptionMessage('Annotations directory cannot be written');

            $adapter->write('Whatever', new Reflection());
        } finally {
            restore_error_handler();
        }
    }

    public function testWriteThenReadRoundTrips(): void
    {
        $adapter    = new Stream(['annotationsDir' => $this->dir]);
        $reflection = new Reflection(['class' => [['type' => 300, 'name' => 'Simple']]]);

        $this->assertTrue($adapter->write('SomeClass', $reflection));

        $restored = $adapter->read('SomeClass');

        $this->assertInstanceOf(Reflection::class, $restored);
        $this->assertSame(
            $reflection->getReflectionData(),
            $restored->getReflectionData()
        );
    }
}
