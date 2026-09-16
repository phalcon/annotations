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
 * A single scanned token, mirroring `phannot_scanner_token`.
 *
 * `cursorAfter` is the buffer offset immediately following this token. cphalcon
 * derives two things from the equivalent state in `base.c`:
 *
 *     state->start_length = processed_comment + processed_comment_len - state->start;
 *
 * the remaining byte count (`start_length`), and the "near to '...'" text in a
 * syntax error, which is the rest of the buffer from that offset. Both are
 * derived from this one offset rather than stored per token. Keeping the
 * substring on every token would use memory quadratic in the argument count.
 */
final class Token
{
    public function __construct(
        public readonly Opcode $opcode,
        public readonly string | null $value,
        public readonly int $line,
        public readonly int $cursorAfter
    ) {
    }
}
