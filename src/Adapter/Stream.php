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

namespace Phalcon\Annotations\Docblock\Adapter;

use Phalcon\Annotations\Docblock\Annotation;
use Phalcon\Annotations\Docblock\Collection;
use Phalcon\Annotations\Docblock\Exceptions\AnnotationsDirectoryNotWritable;
use Phalcon\Annotations\Docblock\Exceptions\CannotReadAnnotationData;
use Phalcon\Annotations\Docblock\Reflection;
use Phalcon\Traits\Php\FileTrait;

use function restore_error_handler;
use function serialize;
use function set_error_handler;
use function sha1;
use function str_contains;
use function strlen;
use function strtolower;
use function unserialize;

use const E_NOTICE;
use const E_WARNING;

/**
 * Stores parsed annotations in files.
 *
 * Port of `phalcon/Annotations/Adapter/Stream.zep`.
 *
 *```php
 * use Phalcon\Annotations\Docblock\Adapter\Stream;
 *
 * $annotations = new Stream(
 *     [
 *         'annotationsDir' => 'app/cache/annotations/',
 *     ]
 * );
 *```
 */
class Stream extends AbstractAdapter
{
    use FileTrait;

    protected string $annotationsDir = './';

    /**
     * @param array{annotationsDir?: string} $options
     */
    public function __construct(array $options = [])
    {
        if (isset($options['annotationsDir'])) {
            $this->annotationsDir = $options['annotationsDir'];
        }
    }

    /**
     * Reads parsed annotations from files
     *
     * @throws CannotReadAnnotationData
     */
    public function read(string $key): bool | Reflection
    {
        /**
         * Paths must be normalized before be used as keys
         */
        $path = $this->getFilePath($key);

        if (!self::phpFileExists($path)) {
            return false;
        }

        $contents = self::phpFileGetContents($path);

        if (empty($contents)) {
            return false;
        }

        $failed = false;

        /**
         * PHP 8.3 reclassified unserialize()'s malformed-data diagnostic from
         * E_NOTICE to E_WARNING. cphalcon listens for E_WARNING alone, so on
         * PHP 8.1 and 8.2 a corrupt cache file there goes unnoticed and read()
         * quietly reports a miss. Both levels are trapped here so the adapter
         * behaves the same on every version this package supports.
         */
        set_error_handler(
            static function () use (&$failed): bool {
                $failed = true;

                return true;
            },
            E_WARNING | E_NOTICE
        );

        /**
         * Restrict object instantiation to the annotation classes this cache
         * ever stores, so a planted cache file cannot trigger PHP object
         * injection through arbitrary classes (CWE-502).
         */
        $data = unserialize(
            $contents,
            [
                'allowed_classes' => [
                    Reflection::class,
                    Collection::class,
                    Annotation::class,
                ],
            ]
        );

        restore_error_handler();

        if ($failed) {
            throw new CannotReadAnnotationData();
        }

        return $data instanceof Reflection ? $data : false;
    }

    /**
     * Writes parsed annotations to files
     *
     * @throws AnnotationsDirectoryNotWritable
     */
    public function write(string $key, Reflection $data): bool
    {
        /**
         * Paths must be normalized before be used as keys
         */
        $path = $this->getFilePath($key);
        $code = serialize($data);

        if (self::phpFilePutContents($path, $code) === false) {
            throw new AnnotationsDirectoryNotWritable();
        }

        return true;
    }

    /**
     * Builds the cache file path. Namespace separators become "_", so a name
     * that itself contains "_" gets a hash suffix; otherwise "A\B" and "A_B"
     * would share one file.
     */
    private function getFilePath(string $key): string
    {
        $name = $this->prepareVirtualPath($key);

        if (str_contains($key, '_')) {
            $name = $name . '_' . sha1($key);
        }

        return $this->annotationsDir . $name . '.php';
    }

    /**
     * Equivalent of cphalcon's `prepare_virtual_path()`: lowercase the key and
     * flatten the characters that would otherwise create directories.
     */
    private function prepareVirtualPath(string $key): string
    {
        $result = '';
        $length = strlen($key);

        for ($i = 0; $i < $length; $i++) {
            $char = $key[$i];

            $result .= match ($char) {
                '/', '\\', ':' => '_',
                default        => strtolower($char),
            };
        }

        return $result;
    }
}
