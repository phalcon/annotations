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
 * Scanner return codes, mirroring `PHANNOT_SCANNER_RETCODE_*` in cphalcon's
 * `scanner.h`. OK corresponds to the C scanner returning 0.
 */
enum ScannerStatus
{
    case EOF;
    case ERR;
    case OK;
}
