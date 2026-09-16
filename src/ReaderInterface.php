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
 * Parses docblocks returning an array with the found annotations
 */
interface ReaderInterface
{
    /**
     * Parses a raw docblock returning the annotations found.
     *
     * cphalcon declares this `-> array` in Zephir, but the underlying C function
     * genuinely returns `false` for a docblock with nothing to parse, and callers
     * there test for it with `typeof ... == "array"`. The declared type is
     * widened here so the contract matches the behaviour.
     *
     * @return list<array<string, mixed>> | false
     */
    public static function parseDocBlock(
        string $docBlock,
        string | bool | null $file = null,
        int | null $line = null
    ): array | false;
    /**
     * Reads annotations from the class docblocks, its constants, properties and
     * methods
     *
     * @return array<string, mixed>
     */
    public function parse(string $className): array;
}
