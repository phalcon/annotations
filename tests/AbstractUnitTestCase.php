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

namespace Phalcon\Annotations\Docblock\Tests;

use PHPUnit\Framework\TestCase;

abstract class AbstractUnitTestCase extends TestCase
{
    /**
     * Path to a fixture under `tests/support/`.
     */
    protected function supportPath(string $path): string
    {
        return __DIR__ . '/support/' . $path;
    }
}
