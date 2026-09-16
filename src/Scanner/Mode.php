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
 * Scanner modes, mirroring `PHANNOT_MODE_*` in cphalcon's `scanner.h`.
 *
 * The scanner starts in RAW and switches to ANNOTATION on the first `@` that is
 * followed by a letter. Nothing switches it back. The C scanner does the same,
 * which is safe only because the stripper has already removed every
 * non-annotation character.
 */
enum Mode
{
    case ANNOTATION;
    case RAW;
}
