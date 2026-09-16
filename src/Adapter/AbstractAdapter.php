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
use Phalcon\Annotations\Docblock\Reader;
use Phalcon\Annotations\Docblock\ReaderInterface;
use Phalcon\Annotations\Docblock\Reflection;

use function count;
use function get_class;
use function is_object;
use function strcasecmp;

/**
 * This is the base class for Phalcon\Annotations\Docblock adapters.
 *
 * Port of `phalcon/Annotations/Adapter/AbstractAdapter.zep`. This layer is what
 * makes a PHP parser practical at request time: a class is parsed once and the
 * resulting Reflection is cached in memory and in whatever backend the concrete
 * adapter provides.
 */
abstract class AbstractAdapter implements AdapterInterface
{
    /**
     * @var array<string, Reflection>
     */
    protected array $annotations = [];

    /**
     * Maximum number of class annotation entries retained in the in-memory
     * cache. 0 (default) keeps the original unbounded behavior; a positive value
     * clears the cache when adding a new class would exceed it.
     */
    protected int $annotationsLimit = 0;

    protected ReaderInterface | null $reader = null;

    /**
     * Parses or retrieves all the annotations found in a class
     */
    public function get(object | string $className): Reflection
    {
        /**
         * Get the class name if it's an object
         */
        $realClassName = is_object($className) ? get_class($className) : $className;

        if (isset($this->annotations[$realClassName])) {
            return $this->annotations[$realClassName];
        }

        /**
         * Try to read the annotations from the adapter
         */
        $classAnnotations = $this->read($realClassName);

        if ($classAnnotations instanceof Reflection) {
            return $classAnnotations;
        }

        $parsedAnnotations = $this->getReader()->parse($realClassName);

        if (
            $this->annotationsLimit > 0
            && count($this->annotations) >= $this->annotationsLimit
        ) {
            $this->annotations = [];
        }

        $classAnnotations                   = new Reflection($parsedAnnotations);
        $this->annotations[$realClassName]  = $classAnnotations;

        $this->write($realClassName, $classAnnotations);

        return $classAnnotations;
    }

    /**
     * Returns the configured annotations-cache cap (0 = unlimited).
     *
     * @see self::setAnnotationsLimit()
     */
    public function getAnnotationsLimit(): int
    {
        return $this->annotationsLimit;
    }

    /**
     * Returns the annotations found in a specific constant
     */
    public function getConstant(string $className, string $constantName): Collection
    {
        $constants = $this->getConstants($className);

        /**
         * Returns a collection anyways
         */
        return $constants[$constantName] ?? new Collection();
    }

    /**
     * Returns the annotations found in all the class' constants
     *
     * @return array<string, Collection>
     */
    public function getConstants(string $className): array
    {
        return $this->get($className)->getConstantsAnnotations();
    }

    /**
     * Returns the annotations found in a specific method.
     *
     * The name is matched case-insensitively on the second pass, because PHP
     * method names are themselves case-insensitive.
     */
    public function getMethod(string $className, string $methodName): Collection
    {
        $methods = $this->get($className)->getMethodsAnnotations();

        if (isset($methods[$methodName])) {
            return $methods[$methodName];
        }

        foreach ($methods as $methodKey => $method) {
            if (strcasecmp($methodKey, $methodName) === 0) {
                return $method;
            }
        }

        /**
         * Returns a collection anyway
         */
        return new Collection();
    }

    /**
     * Returns the annotations found in all the class' methods
     *
     * @return array<string, Collection>
     */
    public function getMethods(string $className): array
    {
        return $this->get($className)->getMethodsAnnotations();
    }

    /**
     * Returns the annotations found in all the class' properties
     *
     * @return array<string, Collection>
     */
    public function getProperties(string $className): array
    {
        return $this->get($className)->getPropertiesAnnotations();
    }

    /**
     * Returns the annotations found in a specific property
     */
    public function getProperty(string $className, string $propertyName): Collection
    {
        $properties = $this->get($className)->getPropertiesAnnotations();

        /**
         * Returns a collection anyways
         */
        return $properties[$propertyName] ?? new Collection();
    }

    /**
     * Returns the annotation reader
     */
    public function getReader(): ReaderInterface
    {
        if ($this->reader === null) {
            $this->reader = new Reader();
        }

        return $this->reader;
    }

    /**
     * Caps the number of class entries retained in the annotations cache. 0
     * disables the cap (the default; preserves the original unbounded
     * behavior). When the cap is exceeded, the cache is cleared and repopulated
     * on subsequent reads.
     */
    public function setAnnotationsLimit(int $annotationsLimit): void
    {
        $this->annotationsLimit = $annotationsLimit;
    }

    /**
     * Sets the annotations parser
     */
    public function setReader(ReaderInterface $reader): void
    {
        $this->reader = $reader;
    }
}
