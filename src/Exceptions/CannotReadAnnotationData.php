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

namespace Phalcon\Annotations\Docblock\Exceptions;

use RuntimeException;

/**
 * Note this extends RuntimeException rather than the component's own Exception,
 * matching cphalcon.
 */
class CannotReadAnnotationData extends RuntimeException
{
    public function __construct()
    {
        parent::__construct("Cannot read annotation data");
    }
}
