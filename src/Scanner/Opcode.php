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

namespace Phalcon\Annotations\Docblock\Scanner;

/**
 * Token opcodes, mirroring `PHANNOT_T_*` in cphalcon's
 * `ext/phalcon/annotations/scanner.h`.
 *
 * The values 300-308 are not merely internal: they are written into the AST as
 * the `type` key and consumed by {@see \Phalcon\Annotations\Docblock\Annotation}.
 * They are part of the public output format and must not be renumbered.
 *
 * Punctuation uses the ASCII code of the character, exactly as the C header does.
 */
enum Opcode: int
{
    case ANNOTATION          = 300;
    case ARRAY               = 308;
    case AT                  = 64;
    case BRACKET_CLOSE       = 125;
    case BRACKET_OPEN        = 123;
    case COLON               = 58;
    case COMMA               = 44;
    case DOUBLE              = 302;
    case EQUALS              = 61;
    case FALSE               = 305;
    case IDENTIFIER          = 307;
    case IGNORE              = 297;
    case INTEGER             = 301;
    case NULL                = 304;
    case PARENTHESES_CLOSE   = 41;
    case PARENTHESES_OPEN    = 40;
    case SBRACKET_CLOSE      = 93;
    case SBRACKET_OPEN       = 91;
    case STRING              = 303;
    case TRUE                = 306;
}
