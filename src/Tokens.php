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

use Phalcon\Annotations\Docblock\Scanner\Opcode;

/**
 * Display names used in syntax-error messages.
 *
 * Direct port of the `phannot_tokens[]` table in cphalcon's
 * `ext/phalcon/annotations/base.c`. That table has no entry for NULL, TRUE,
 * FALSE or IGNORE, and the C code falls back to "UNKNOWN" for anything missing.
 * The fallback shows up in error text, so the gaps are kept.
 */
final class Tokens
{
    /**
     * Returns the display name for an opcode, or "UNKNOWN" when the C table has
     * no entry for it.
     *
     * A `match` rather than a lookup table, because reading `->value` off an
     * enum case is not a valid constant expression on PHP 8.1.
     */
    public static function name(Opcode | null $opcode): string
    {
        return match ($opcode) {
            Opcode::INTEGER           => 'INTEGER',
            Opcode::DOUBLE            => 'DOUBLE',
            Opcode::STRING            => 'STRING',
            Opcode::IDENTIFIER        => 'IDENTIFIER',
            Opcode::AT                => '@',
            Opcode::COMMA             => ',',
            Opcode::EQUALS            => '=',
            Opcode::COLON             => ':',
            Opcode::PARENTHESES_OPEN  => '(',
            Opcode::PARENTHESES_CLOSE => ')',
            Opcode::BRACKET_OPEN      => '{',
            Opcode::BRACKET_CLOSE     => '}',
            Opcode::SBRACKET_OPEN     => '[',
            Opcode::SBRACKET_CLOSE    => ']',
            default                   => 'UNKNOWN',
        };
    }
}
