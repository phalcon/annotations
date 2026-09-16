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

/**
 * Regenerates the golden AST for every fixture in `tests/fixtures`.
 *
 * Run it from the project root:
 *
 *     php tests/generate-golden.php
 *
 * When ext-phalcon is loaded, the C parser produces the goldens instead of this
 * package, which makes the fixtures a true cross-implementation reference rather
 * than a snapshot of our own behaviour. `tests/differential.php` checks the two
 * against each other directly.
 */

require __DIR__ . '/../vendor/autoload.php';

use Phalcon\Annotations\Docblock\Reader;

$useC = extension_loaded('phalcon') && class_exists(\Phalcon\Annotations\Reader::class);

echo $useC
    ? "Generating from the cphalcon C parser.\n"
    : "Generating from the pure-PHP parser (ext-phalcon not loaded).\n";

$written = 0;

foreach (glob(__DIR__ . '/fixtures/*.txt') ?: [] as $path) {
    $docBlock = file_get_contents($path);

    if ($docBlock === false) {
        continue;
    }

    $parsed = $useC
        ? \Phalcon\Annotations\Reader::parseDocBlock($docBlock)
        : Reader::parseDocBlock($docBlock);

    $json = json_encode(
        $parsed,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );

    file_put_contents(substr($path, 0, -4) . '.json', $json . "\n");
    $written++;

    echo '  ' . basename($path) . "\n";
}

echo "Wrote {$written} golden file(s).\n";
