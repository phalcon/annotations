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

namespace Phalcon\Annotations\Docblock\Tests\Unit\Parser;

use Phalcon\Annotations\Docblock\Reader;
use Phalcon\Annotations\Docblock\Tests\AbstractUnitTestCase;

use function basename;
use function file_get_contents;
use function glob;
use function json_decode;
use function substr;

/**
 * Round-trips every docblock in `tests/fixtures` against its recorded AST.
 *
 * `assertSame()` rather than `assertEquals()`: key order is part of the output
 * format, and only an identity comparison notices when it drifts.
 *
 * Regenerate the goldens with `php tests/generate-golden.php`. With ext-phalcon
 * loaded that script records what the C parser produces, which is what makes
 * these fixtures a cross-implementation reference instead of a snapshot of this
 * package's own behaviour.
 */
final class FixturesTest extends AbstractUnitTestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function fixtureProvider(): array
    {
        $cases = [];

        foreach (glob(__DIR__ . '/../../fixtures/*.txt') ?: [] as $path) {
            $cases[basename($path, '.txt')] = [$path, substr($path, 0, -4) . '.json'];
        }

        return $cases;
    }

    /**
     * @dataProvider fixtureProvider
     */
    public function testFixtureMatchesGoldenAst(string $docBlockPath, string $goldenPath): void
    {
        $this->assertFileExists($docBlockPath);
        $this->assertFileExists($goldenPath);

        $docBlock = file_get_contents($docBlockPath);
        $golden   = file_get_contents($goldenPath);

        $this->assertIsString($docBlock);
        $this->assertIsString($golden);

        $expected = json_decode($golden, true);
        $actual   = Reader::parseDocBlock($docBlock);

        $this->assertSame($expected, $actual);
    }
}
