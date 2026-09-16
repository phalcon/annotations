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

namespace Phalcon\Annotations\Docblock\Adapter;

use Phalcon\Annotations\Docblock\Reflection;

use function strtolower;

/**
 * Stores parsed annotations in memory for the lifetime of the request.
 *
 * Port of `phalcon/Annotations/Adapter/Memory.zep`.
 *
 *```php
 * use Phalcon\Annotations\Docblock\Adapter\Memory;
 *
 * $annotations = new Memory();
 *```
 */
class Memory extends AbstractAdapter
{
    /**
     * @var array<string, Reflection>
     */
    protected array $data = [];

    /**
     * The options array is accepted and ignored, as in cphalcon. This adapter
     * has nothing to configure, but every adapter takes the same constructor so
     * they stay interchangeable.
     *
     * @param array<string, mixed> $options
     *
     * @phpstan-ignore constructor.unusedParameter
     */
    public function __construct(array $options = [])
    {
    }

    /**
     * Reads parsed annotations from memory
     */
    public function read(string $key): bool | Reflection
    {
        return $this->data[strtolower($key)] ?? false;
    }

    /**
     * Writes parsed annotations to memory
     */
    public function write(string $key, Reflection $data): bool
    {
        $this->data[strtolower($key)] = $data;

        return true;
    }
}
