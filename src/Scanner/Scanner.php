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

use Phalcon\Annotations\Docblock\Exception;

use function preg_match;
use function sprintf;
use function strlen;
use function strtolower;
use function substr;

/**
 * Tokenizer for the annotations language.
 *
 * Behavioural port of `phannot_get_token()` in cphalcon's
 * `ext/phalcon/annotations/scanner.re`. The C version is a re2c DFA; this is a
 * hand-written dispatcher on the first character. The grammar's token set is
 * small and unambiguous enough that a transliterated state machine buys nothing,
 * but the *observable* token stream is identical, including the quirks noted
 * below.
 *
 * re2c resolves overlapping rules by longest match, falling back to rule order
 * on a tie. Two places where that matters:
 *
 * - `1.1` is a DOUBLE, not INTEGER `1` followed by `.1`, because DOUBLE matches
 *   longer. `-10` is a single INTEGER; a bare `-` matches no rule and is an error.
 * - `null` ties between the keyword rule and IDENTIFIER, and the keyword rule is
 *   declared first, so it wins. `nullable` is longer as an IDENTIFIER, so it
 *   stays an identifier. Matching IDENTIFIER first and then re-checking the
 *   lexeme against the keywords gives the same result.
 *
 * Tokens are produced in one batch rather than pulled one at a time. The C
 * driver pumps tokens into a lemon parser that needs no lookahead; a
 * recursive-descent parser wants one token of lookahead, and a token list gives
 * it that for free.
 */
final class Scanner
{
    private const DOUBLE = '/-?[0-9]+\.[0-9]+/A';

    /**
     * `\?[a-zA-Z_][a-zA-Z0-9_]*(\[a-zA-Z_][a-zA-Z0-9_]*)*`. A namespaced name
     * such as `\Foo\Bar` is a single token.
     */
    private const IDENTIFIER = '/\\\\?[a-zA-Z_][a-zA-Z0-9_]*(?:\\\\[a-zA-Z_][a-zA-Z0-9_]*)*/A';

    private const INTEGER = '/-?[0-9]+/A';

    private const KEYWORDS = [
        'null'  => Opcode::NULL,
        'false' => Opcode::FALSE,
        'true'  => Opcode::TRUE,
    ];

    private const PUNCTUATION = [
        '(' => Opcode::PARENTHESES_OPEN,
        ')' => Opcode::PARENTHESES_CLOSE,
        '{' => Opcode::BRACKET_OPEN,
        '}' => Opcode::BRACKET_CLOSE,
        '[' => Opcode::SBRACKET_OPEN,
        ']' => Opcode::SBRACKET_CLOSE,
        '@' => Opcode::AT,
        '=' => Opcode::EQUALS,
        ':' => Opcode::COLON,
        ',' => Opcode::COMMA,
    ];

    /**
     * A string is: an escape (backslash plus anything but a newline or NUL), or
     * any byte that is not NUL, a backslash or the closing quote. Note that an
     * unescaped newline *is* allowed inside a string, but a backslash
     * immediately before one is not.
     */
    private const STRING_DOUBLE = '/"(?:\\\\[^\n\x00]|[^\x00\\\\"])*"/A';

    private const STRING_SINGLE = '/\'(?:\\\\[^\n\x00]|[^\x00\\\\\'])*\'/A';

    /**
     * Mirrors `state->start_length`, which `base.c` recomputes after every
     * token, including the ignored ones. The scanner error message reads it. On
     * a scanning error the C code never refreshes it, so the value left over
     * from the previous token is the one that formats the message. Keeping the
     * same update points preserves that.
     */
    private int $startLength = 0;

    public function __construct(private readonly State $state)
    {
    }

    /**
     * Scans the whole buffer.
     *
     * Ignored tokens (whitespace, and everything before the first annotation) are
     * dropped here rather than returned, matching the `case PHANNOT_T_IGNORE:
     * break;` arm in the C driver that declines to feed them to the parser.
     *
     * @return list<Token>
     * @throws Exception   on an unscannable character
     */
    public function tokenize(): array
    {
        $state  = $this->state;
        $raw    = $state->getRaw();
        $length = $state->getLength();
        $tokens = [];

        while (true) {
            if ($state->mode === Mode::RAW) {
                if ($this->scanRaw() === ScannerStatus::EOF) {
                    break;
                }

                continue;
            }

            $char = $state->charAt($state->cursor);

            if ($char === "\0") {
                break;
            }

            $start  = $state->cursor;
            $opcode = null;
            $value  = null;

            if (($char >= '0' && $char <= '9') || $char === '-') {
                if (preg_match(self::DOUBLE, $raw, $matches, 0, $start) === 1) {
                    $opcode = Opcode::DOUBLE;
                    $value  = $matches[0];
                } elseif (preg_match(self::INTEGER, $raw, $matches, 0, $start) === 1) {
                    $opcode = Opcode::INTEGER;
                    $value  = $matches[0];
                }

                if ($opcode !== null) {
                    $state->cursor = $start + strlen($matches[0]);
                }
            } elseif ($char === '"' || $char === '\'') {
                $pattern = $char === '"' ? self::STRING_DOUBLE : self::STRING_SINGLE;

                if (preg_match($pattern, $raw, $matches, 0, $start) === 1) {
                    $opcode = Opcode::STRING;

                    /**
                     * The quotes are stripped but nothing inside them is
                     * unescaped, so `\"` stays two characters. cphalcon reuses
                     * the re2c YYMARKER, which sits just past the opening quote,
                     * as the value pointer and takes `length - 1` bytes from it.
                     */
                    $value         = substr($matches[0], 1, -1);
                    $state->cursor = $start + strlen($matches[0]);
                }
            } elseif (
                ($char >= 'a' && $char <= 'z')
                || ($char >= 'A' && $char <= 'Z')
                || $char === '_'
                || $char === '\\'
            ) {
                if (preg_match(self::IDENTIFIER, $raw, $matches, 0, $start) === 1) {
                    $keyword = self::KEYWORDS[strtolower($matches[0])] ?? null;

                    if ($keyword !== null) {
                        $opcode = $keyword;
                    } else {
                        $opcode = Opcode::IDENTIFIER;
                        $value  = $matches[0];
                    }

                    $state->cursor = $start + strlen($matches[0]);
                }
            } elseif (isset(self::PUNCTUATION[$char])) {
                $opcode = self::PUNCTUATION[$char];
                $state->cursor++;
            } elseif ($char === ' ' || $char === "\t" || $char === "\r") {
                while (
                    $state->cursor < $length
                    && (
                        $raw[$state->cursor] === ' '
                        || $raw[$state->cursor] === "\t"
                        || $raw[$state->cursor] === "\r"
                    )
                ) {
                    $state->cursor++;
                }

                $this->startLength = $state->remainingLength($state->cursor);

                continue;
            } elseif ($char === "\n") {
                $state->cursor++;
                $state->activeLine++;
                $this->startLength = $state->remainingLength($state->cursor);

                continue;
            }

            if ($opcode === null) {
                throw new Exception($this->scannerErrorMessage($start + 1));
            }

            $this->startLength = $state->remainingLength($state->cursor);

            $tokens[] = new Token(
                $opcode,
                $value,
                $state->activeLine,
                $state->cursor
            );
        }

        return $tokens;
    }

    /**
     * Port of `phannot_scanner_error_msg()` in `base.c`.
     */
    private function scannerErrorMessage(int $cursor): string
    {
        $state = $this->state;
        $near  = $state->remainder($cursor);

        if ($this->startLength > 16) {
            return sprintf(
                "Scanning error before '%s...' in %s on line %d",
                substr($near, 0, 16),
                $state->activeFile,
                $state->activeLine
            );
        }

        return sprintf(
            "Scanning error before '%s' in %s on line %d",
            $near,
            $state->activeFile,
            $state->activeLine
        );
    }

    /**
     * The hand-written RAW-mode loop from `scanner.re` (lines 26-49).
     *
     * It walks one byte at a time emitting ignored tokens until it sees an `@`
     * followed by a letter, then latches into ANNOTATION mode *without*
     * consuming the `@`, leaving it for the main scanner. Nothing switches the
     * mode back; that is safe only because the stripper already removed every
     * non-annotation character.
     */
    private function scanRaw(): ScannerStatus
    {
        $state = $this->state;

        while (true) {
            $char = $state->charAt($state->cursor);

            if ($char === "\0") {
                return ScannerStatus::EOF;
            }

            if ($char === "\n") {
                $state->activeLine++;
            }

            $next = $state->charAt($state->cursor + 1);

            if (
                $char === '@'
                && (
                    ($next >= 'A' && $next <= 'Z')
                    || ($next >= 'a' && $next <= 'z')
                )
            ) {
                $state->mode = Mode::ANNOTATION;

                return ScannerStatus::OK;
            }

            $state->cursor++;
            $this->startLength = $state->remainingLength($state->cursor);
        }
    }
}
