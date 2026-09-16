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

use Phalcon\Annotations\Docblock\Collection;
use Phalcon\Annotations\Docblock\ReaderInterface;
use Phalcon\Annotations\Docblock\Reflection;

/**
 * Port of `phalcon/Annotations/Adapter/AdapterInterface.zep`.
 *
 * Two signatures are stated more precisely here than in cphalcon:
 *
 * - `get()` is declared `string` there but the implementation has always
 *   accepted an object too, and callers rely on it.
 * - `read()` and `write()` are absent there and reached through Zephir's dynamic
 *   `this->{"read"}()`. They are part of what an adapter must provide, so they
 *   are declared. `write()` reports success uniformly; cphalcon returned `bool`
 *   from the APCu adapter and `void` from the other two.
 */
interface AdapterInterface
{
    /**
     * Parses or retrieves all the annotations found in a class
     */
    public function get(object | string $className): Reflection;

    /**
     * Returns the annotations found in a specific constant
     */
    public function getConstant(string $className, string $constantName): Collection;

    /**
     * Returns the annotations found in all the class' constants
     *
     * @return array<string, Collection>
     */
    public function getConstants(string $className): array;

    /**
     * Returns the annotations found in a specific method
     */
    public function getMethod(string $className, string $methodName): Collection;

    /**
     * Returns the annotations found in all the class' methods
     *
     * @return array<string, Collection>
     */
    public function getMethods(string $className): array;

    /**
     * Returns the annotations found in all the class' properties
     *
     * @return array<string, Collection>
     */
    public function getProperties(string $className): array;

    /**
     * Returns the annotations found in a specific property
     */
    public function getProperty(string $className, string $propertyName): Collection;

    /**
     * Returns the annotation reader
     */
    public function getReader(): ReaderInterface;

    /**
     * Reads parsed annotations from the backend, or false when absent
     */
    public function read(string $key): bool | Reflection;

    /**
     * Sets the annotations parser
     */
    public function setReader(ReaderInterface $reader): void;

    /**
     * Writes parsed annotations to the backend
     */
    public function write(string $key, Reflection $data): bool;
}
