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

namespace Phalcon\Annotations\Docblock\Parser;

use Phalcon\Annotations\Docblock\Scanner\Opcode;

/**
 * Builders for the nodes the parser emits.
 *
 * Port of the `phannot_ret_*` helpers in cphalcon's
 * `ext/phalcon/annotations/parser.php.inc.h`. The AST is plain nested arrays,
 * which is what the C parser produced too, so there is nothing to marshal.
 *
 * Two properties of these arrays are part of the output format:
 *
 * - **Key order** is the order the C code calls `add_assoc_*` in, reproduced
 *   below. Consumers using `assertSame()` on a whole AST depend on it.
 * - **Optional keys are omitted, never null.** An annotation with no arguments
 *   has no `arguments` key at all; `null`, `true` and `false` literals carry no
 *   `value` key; a positional argument has no `name` key.
 */
final class Ast
{
    /**
     * `phannot_ret_annotation()`. Key order: type, name, arguments, file, line.
     *
     * @param list<array<string, mixed>>|null $arguments
     *
     * @return array<string, mixed>
     */
    public static function annotation(
        string $name,
        array | null $arguments,
        string $file,
        int $line
    ): array {
        $node = [
            'type' => Opcode::ANNOTATION->value,
            'name' => $name,
        ];

        if ($arguments !== null) {
            $node['arguments'] = $arguments;
        }

        $node['file'] = $file;
        $node['line'] = $line;

        return $node;
    }

    /**
     * `phannot_ret_array()`. Both `{...}` and `[...]` produce this same node.
     *
     * @param list<array<string, mixed>>|null $items
     *
     * @return array<string, mixed>
     */
    public static function array(array | null $items): array
    {
        $node = ['type' => Opcode::ARRAY->value];

        if ($items !== null) {
            $node['items'] = $items;
        }

        return $node;
    }

    /**
     * `phannot_ret_literal_zval()`. `value` is present only for the token types
     * that carry one.
     *
     * @return array<string, mixed>
     */
    public static function literal(Opcode $type, string | null $value): array
    {
        $node = ['type' => $type->value];

        if ($value !== null) {
            $node['value'] = $value;
        }

        return $node;
    }

    /**
     * `phannot_ret_named_item()`. Key order: expr, name.
     *
     * @param array<string, mixed> $expr
     *
     * @return array<string, mixed>
     */
    public static function namedItem(array $expr, string | null $name): array
    {
        $node = ['expr' => $expr];

        if ($name !== null) {
            $node['name'] = $name;
        }

        return $node;
    }
}
