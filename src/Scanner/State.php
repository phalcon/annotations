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

use function strlen;
use function substr;

/**
 * Scanner state, mirroring `phannot_scanner_state` in cphalcon's `scanner.h`.
 *
 * In the C scanner `YYCURSOR` is `#define`d to `s->start`, so the cursor and the
 * "where did this token start" pointer are the same field. That is preserved
 * here: {@see self::$cursor} plays both roles.
 */
final class State
{
    public int $cursor = 0;

    public Mode $mode = Mode::RAW;

    private int $length;

    public function __construct(
        private readonly string $raw,
        public string $activeFile = 'eval code',
        public int $activeLine = 1
    ) {
        $this->length = strlen($raw);
    }

    public function charAt(int $offset): string
    {
        return $offset < $this->length ? $this->raw[$offset] : "\0";
    }

    public function getLength(): int
    {
        return $this->length;
    }

    public function getRaw(): string
    {
        return $this->raw;
    }

    /**
     * The remaining buffer from an offset, i.e. the "near to '...'" text of a
     * syntax error.
     */
    public function remainder(int $offset): string
    {
        return substr($this->raw, $offset);
    }

    /**
     * The number of bytes left after an offset, i.e. `start_length` in `base.c`.
     */
    public function remainingLength(int $offset): int
    {
        return $this->length - $offset;
    }
}
