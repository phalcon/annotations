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

use function apcu_fetch;
use function apcu_store;
use function strtolower;

/**
 * Stores parsed annotations in APCu.
 *
 * Port of `phalcon/Annotations/Adapter/Apcu.zep`. Requires `ext-apcu`.
 *
 *```php
 * use Phalcon\Annotations\Docblock\Adapter\Apcu;
 *
 * $annotations = new Apcu(
 *     [
 *         'prefix'   => 'my-prefix',
 *         'lifetime' => 3600,
 *     ]
 * );
 *```
 */
class Apcu extends AbstractAdapter
{
    protected string $prefix = '';

    protected int $ttl = 172800;

    /**
     * @param array{prefix?: string, lifetime?: int} $options
     */
    public function __construct(array $options = [])
    {
        if (isset($options['prefix'])) {
            $this->prefix = $options['prefix'];
        }

        if (isset($options['lifetime'])) {
            $this->ttl = $options['lifetime'];
        }
    }

    /**
     * Reads parsed annotations from APCu
     */
    public function read(string $key): Reflection | bool
    {
        $data = apcu_fetch($this->buildKey($key));

        return $data instanceof Reflection ? $data : false;
    }

    /**
     * Writes parsed annotations to APCu
     */
    public function write(string $key, Reflection $data): bool
    {
        return (bool) apcu_store($this->buildKey($key), $data, $this->ttl);
    }

    private function buildKey(string $key): string
    {
        return strtolower('_PHAN' . $this->prefix . $key);
    }
}
