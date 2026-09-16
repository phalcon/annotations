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

namespace Phalcon\Annotations\Docblock;

/**
 * Allows to manipulate the annotations reflection in an OO manner.
 *
 * Port of `phalcon/Annotations/Reflection.zep`.
 *
 *```php
 * use Phalcon\Annotations\Docblock\Reader;
 * use Phalcon\Annotations\Docblock\Reflection;
 *
 * // Parse the annotations in a class
 * $reader = new Reader();
 * $parsing = $reader->parse("MyComponent");
 *
 * // Create the reflection
 * $reflection = new Reflection($parsing);
 *
 * // Get the annotations in the class docblock
 * $classAnnotations = $reflection->getClassAnnotations();
 *```
 */
class Reflection
{
    protected Collection | null $classAnnotations = null;

    /**
     * @var array<string, Collection>
     */
    protected array $constantAnnotations = [];

    /**
     * @var array<string, Collection>
     */
    protected array $methodAnnotations = [];

    /**
     * @var array<string, Collection>
     */
    protected array $propertyAnnotations = [];

    /**
     * @param array<string, mixed> $reflectionData
     */
    public function __construct(
        protected array $reflectionData = []
    ) {
    }

    /**
     * Returns the annotations found in the class docblock
     */
    public function getClassAnnotations(): Collection | null
    {
        if ($this->classAnnotations === null && isset($this->reflectionData['class'])) {
            /** @var array<array-key, array<string, mixed>> $reflectionClass */
            $reflectionClass        = $this->reflectionData['class'];
            $this->classAnnotations = new Collection($reflectionClass);
        }

        return $this->classAnnotations;
    }

    /**
     * Returns the annotations found in the constants' docblocks
     *
     * @return array<string, Collection>
     */
    public function getConstantsAnnotations(): array
    {
        if (isset($this->reflectionData['constants'])) {
            /** @var array<string, array<array-key, array<string, mixed>>> $reflectionConstants */
            $reflectionConstants = $this->reflectionData['constants'];

            foreach ($reflectionConstants as $constant => $reflectionConstant) {
                $this->constantAnnotations[$constant] = new Collection($reflectionConstant);
            }
        }

        return $this->constantAnnotations;
    }

    /**
     * Returns the annotations found in the methods' docblocks
     *
     * @return array<string, Collection>
     */
    public function getMethodsAnnotations(): array
    {
        if (isset($this->reflectionData['methods'])) {
            /** @var array<string, array<array-key, array<string, mixed>>> $reflectionMethods */
            $reflectionMethods = $this->reflectionData['methods'];

            foreach ($reflectionMethods as $methodName => $reflectionMethod) {
                $this->methodAnnotations[$methodName] = new Collection($reflectionMethod);
            }
        }

        return $this->methodAnnotations;
    }

    /**
     * Returns the annotations found in the properties' docblocks
     *
     * @return array<string, Collection>
     */
    public function getPropertiesAnnotations(): array
    {
        if (isset($this->reflectionData['properties'])) {
            /** @var array<string, array<array-key, array<string, mixed>>> $reflectionProperties */
            $reflectionProperties = $this->reflectionData['properties'];

            foreach ($reflectionProperties as $property => $reflectionProperty) {
                $this->propertyAnnotations[$property] = new Collection($reflectionProperty);
            }
        }

        return $this->propertyAnnotations;
    }

    /**
     * Returns the raw parsing intermediate definitions used to construct the
     * reflection
     *
     * @return array<string, mixed>
     */
    public function getReflectionData(): array
    {
        return $this->reflectionData;
    }
}
