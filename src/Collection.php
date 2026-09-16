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

use Countable;
use Iterator;
use Phalcon\Annotations\Docblock\Exceptions\AnnotationNotFound;

use function count;

/**
 * Represents a collection of annotations. This class allows to traverse a group
 * of annotations easily.
 *
 * Port of `phalcon/Annotations/Collection.zep`.
 *
 *```php
 * // Traverse annotations
 * foreach ($classAnnotations as $annotation) {
 *     echo "Name=", $annotation->getName(), PHP_EOL;
 * }
 *
 * // Check if the annotations has a specific
 * var_dump($classAnnotations->has("Cacheable"));
 *
 * // Get an specific annotation in the collection
 * $annotation = $classAnnotations->get("Cacheable");
 *```
 *
 * @implements Iterator<int, Annotation>
 */
class Collection implements Iterator, Countable
{
    /**
     * @var list<Annotation>
     */
    protected array $annotations = [];

    protected int $position = 0;

    /**
     * @param array<array-key, array<string, mixed>> $reflectionData
     */
    public function __construct(array $reflectionData = [])
    {
        $annotations = [];

        foreach ($reflectionData as $annotationData) {
            $annotations[] = new Annotation($annotationData);
        }

        $this->annotations = $annotations;
    }

    /**
     * Returns the number of annotations in the collection
     */
    public function count(): int
    {
        return count($this->annotations);
    }

    /**
     * Returns the current annotation in the iterator.
     *
     * Past the end this returns `false`, not null, which is what cphalcon does.
     * The iterator is still declared as yielding Annotation, because `valid()`
     * gates every position `foreach` actually visits.
     */
    public function current(): mixed
    {
        /** @phpstan-ignore return.type */
        return $this->annotations[$this->position] ?? false;
    }

    /**
     * Returns the first annotation that matches a name
     *
     * @throws AnnotationNotFound
     */
    public function get(string $name): Annotation
    {
        foreach ($this->annotations as $annotation) {
            if ($name === $annotation->getName()) {
                return $annotation;
            }
        }

        throw new AnnotationNotFound($name);
    }

    /**
     * Returns all the annotations that match a name
     *
     * @return list<Annotation>
     */
    public function getAll(string $name): array
    {
        $found = [];

        foreach ($this->annotations as $annotation) {
            if ($name === $annotation->getName()) {
                $found[] = $annotation;
            }
        }

        return $found;
    }

    /**
     * Returns the internal annotations as an array
     *
     * @return list<Annotation>
     */
    public function getAnnotations(): array
    {
        return $this->annotations;
    }

    /**
     * Check if an annotation exists in a collection
     */
    public function has(string $name): bool
    {
        foreach ($this->annotations as $annotation) {
            if ($name === $annotation->getName()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the current position/key in the iterator
     */
    public function key(): int
    {
        return $this->position;
    }

    /**
     * Moves the internal iteration pointer to the next position
     */
    public function next(): void
    {
        $this->position++;
    }

    /**
     * Rewinds the internal iterator
     */
    public function rewind(): void
    {
        $this->position = 0;
    }

    /**
     * Check if the current annotation in the iterator is valid
     */
    public function valid(): bool
    {
        return isset($this->annotations[$this->position]);
    }
}
