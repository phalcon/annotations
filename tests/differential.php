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
 * Compares this package's parser against the cphalcon C parser, docblock by
 * docblock, and reports any difference in the produced AST or in the thrown
 * exception message.
 *
 * Requires ext-phalcon 5.x to be loaded; it exits 0 with a notice otherwise so
 * that it is harmless to wire into a pipeline that does not always have the
 * extension.
 *
 *     php tests/differential.php
 *
 * The corpus is every file in `tests/fixtures/*.txt` plus the inline cases
 * below, which cover the edges the fixtures deliberately do not: inputs that are
 * supposed to fail, and inputs that are supposed to return false rather than an
 * array.
 */

require __DIR__ . '/../vendor/autoload.php';

use Phalcon\Annotations\Docblock\Reader as PhpReader;
use Phalcon\Annotations\Reader as CReader;

if (!extension_loaded('phalcon') || !class_exists(CReader::class)) {
    fwrite(STDERR, "ext-phalcon is not loaded; nothing to compare against.\n");

    exit(0);
}

/**
 * Cases that are not in the fixture corpus because they do not produce a
 * parseable AST.
 */
$inline = [
    'empty'                  => '',
    'one-char'               => '@',
    'at-bang'                => '@!',
    'open-docblock'          => '/** @',
    'trailing-text'          => '/** @Foo */ trailing text',
    'unterminated-parens'    => "/**\n * @Invalid(\n */",
    'unterminated-string'    => '/** @Foo("abc',
    'trailing-backslash'     => '/** @Foo("abc\\',
    'empty-braces'           => '/** @Foo({}) */',
    'stray-comma'            => '/** @Foo(,) */',
    'missing-close-brace'    => '/** @Foo({1) */',
    'bare-dash'              => '/** @Foo(-) */',
    'deep-nesting'           => "/**\n * @Foo(" . str_repeat('{', 300) . str_repeat('}', 300) . ")\n */",
    'escaped-quote'          => '/** @Foo("a\\"b") */',
    'paren-in-string'        => "/** @Foo(key='value(') */",
];

$corpus = $inline;

foreach (glob(__DIR__ . '/fixtures/*.txt') ?: [] as $path) {
    $contents = file_get_contents($path);

    if ($contents !== false) {
        $corpus['fixture:' . basename($path, '.txt')] = $contents;
    }
}

/**
 * Runs a parser and normalises either outcome, a value or a failure, into one
 * comparable shape.
 *
 * @return array{outcome: string, value: mixed}
 */
function run(callable $parse, string $docBlock): array
{
    try {
        return ['outcome' => 'value', 'value' => $parse($docBlock)];
    } catch (Throwable $e) {
        return ['outcome' => 'throw', 'value' => $e->getMessage()];
    }
}

$mismatches = 0;
$checked    = 0;

foreach ($corpus as $label => $docBlock) {
    $checked++;

    $c   = run(static fn(string $d): mixed => CReader::parseDocBlock($d), $docBlock);
    $php = run(static fn(string $d): mixed => PhpReader::parseDocBlock($d), $docBlock);

    if ($c === $php) {
        continue;
    }

    $mismatches++;

    echo "MISMATCH {$label}\n";
    echo '  input : ' . json_encode($docBlock) . "\n";
    echo '  C     : ' . $c['outcome'] . ' ' . json_encode($c['value']) . "\n";
    echo '  PHP   : ' . $php['outcome'] . ' ' . json_encode($php['value']) . "\n\n";
}

echo "Checked {$checked} docblock(s); {$mismatches} mismatch(es).\n";

exit($mismatches === 0 ? 0 : 1);
