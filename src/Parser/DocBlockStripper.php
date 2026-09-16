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

namespace Phalcon\Annotations\Docblock\Parser;

use function ctype_alnum;
use function strlen;

/**
 * Reduces a raw docblock to just its annotation text.
 *
 * Direct port of `phannot_remove_comment_separators()` in cphalcon's
 * `ext/phalcon/annotations/base.c` (lines 132-256). It runs before the scanner
 * and throws away every character that is not part of an annotation, which is
 * why the scanner's RAW mode never has to switch back once it has latched.
 *
 * Three behaviours here matter and are easy to lose in a rewrite:
 *
 * - Annotation *names* are matched with a narrower rule than the scanner's
 *   IDENTIFIER (`alnum | _ | \` only). The stripper runs first, so its rule wins.
 * - A string literal inside an argument list is consumed whole, so parentheses
 *   and newlines inside it are never treated as structural.
 * - `startLines` counts the newlines swallowed here, so that the caller can
 *   rewind the reported line number and have errors point at the original file.
 */
final class DocBlockStripper
{
    private const SEPARATORS = [
        ' '    => true,
        '*'    => true,
        '/'    => true,
        "\t"   => true,
        "\v"   => true,
    ];

    /**
     * @return array{0: string, 1: int} the processed text and the number of
     *                                  newlines consumed
     */
    public static function strip(string $comment): array
    {
        $length     = strlen($comment);
        $processed  = '';
        $startLines = 0;
        $startMode  = true;
        $ch         = '';

        for ($i = 0; $i < $length; $i++) {
            $ch = $comment[$i];

            if ($startMode) {
                if (isset(self::SEPARATORS[$ch])) {
                    continue;
                }

                $startMode = false;
            }

            if ($ch === '@') {
                $processed .= $ch;
                $i++;

                $openParentheses = 0;

                for ($j = $i; $j < $length; $j++) {
                    $ch = $comment[$j];

                    if ($startMode) {
                        if (isset(self::SEPARATORS[$ch])) {
                            continue;
                        }

                        $startMode = false;
                    }

                    if ($openParentheses === 0) {
                        if (ctype_alnum($ch) || '_' === $ch || '\\' === $ch) {
                            $processed .= $ch;

                            continue;
                        }

                        if ($ch === '(') {
                            $processed .= $ch;
                            $openParentheses++;

                            continue;
                        }
                    } else {
                        $processed .= $ch;

                        if ($ch === '"' || $ch === '\'') {
                            $quote = $ch;

                            /**
                             * Consume the whole string literal so that any
                             * parentheses inside it are not counted as structural
                             */
                            for ($j++; $j < $length; $j++) {
                                $ch         = $comment[$j];
                                $processed .= $ch;

                                if ($ch === '\\') {
                                    $j++;
                                    if ($j < $length) {
                                        $processed .= $comment[$j];
                                    }

                                    continue;
                                }

                                if ($ch === $quote) {
                                    break;
                                }

                                if ($ch === "\n") {
                                    $startLines++;
                                }
                            }

                            continue;
                        }

                        if ($ch === '(') {
                            $openParentheses++;
                        } elseif ($ch === ')') {
                            $openParentheses--;
                        } elseif ($ch === "\n") {
                            $startLines++;
                            $startMode = true;
                        }

                        if ($openParentheses > 0) {
                            continue;
                        }
                    }

                    $i          = $j;
                    $processed .= ' ';

                    break;
                }
            }

            if ($ch === "\n") {
                $startLines++;
                $startMode = true;
            }
        }

        return [$processed, $startLines];
    }
}
