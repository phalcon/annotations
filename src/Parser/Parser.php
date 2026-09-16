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

use Phalcon\Annotations\Docblock\Exception;
use Phalcon\Annotations\Docblock\Scanner\Opcode;
use Phalcon\Annotations\Docblock\Scanner\Scanner;
use Phalcon\Annotations\Docblock\Scanner\State;
use Phalcon\Annotations\Docblock\Scanner\Token;
use Phalcon\Annotations\Docblock\Tokens;

use function count;
use function sprintf;
use function strlen;

/**
 * Recursive-descent parser for the annotations language.
 *
 * Replaces the lemon LALR parser in cphalcon's
 * `ext/phalcon/annotations/parser.php.lemon`. That grammar is 25 productions
 * over 17 terminals with no operator-precedence rules at all: `expr` is a flat
 * nine-way alternation, and the only recursion is structural nesting through
 * `annotation` and `array`. It is LL(1) except for `argument_item`, which needs
 * one token of lookahead past an IDENTIFIER or STRING to spot the `=` or `:` of
 * a named argument. Generated parse tables buy nothing at that size, so the 25
 * productions are written out directly below, one method per nonterminal.
 *
 * The output matches the C parser: the same nested arrays, the same integer
 * type codes, the same syntax-error strings. See {@see Ast} for the node shapes.
 *
 * Two deliberate structural choices:
 *
 * - `annotation_list` and `argument_list` are **loops, not recursion**. They are
 *   left-recursive in the grammar, so lemon reduces them eagerly and its stack
 *   stays flat; a naive recursive translation would be quadratic and would blow
 *   the nesting budget on long argument lists. cphalcon has a test that parses
 *   40,000 arguments under three seconds.
 * - Nesting is capped explicitly. lemon has a fixed 100-slot stack and reports
 *   "nested too deeply" when it overflows. cphalcon tests for that, so it is
 *   kept rather than improved away.
 */
final class Parser
{
    /**
     * Stands in for lemon's `YYSTACKDEPTH 100`. The C parser overflows after
     * roughly one stack slot per nesting level; the exact boundary is not
     * observable through any test, but failing closed on deep nesting is.
     */
    private const MAX_NESTING_DEPTH = 100;

    private int $count = 0;

    private int $depth = 0;

    private int $finalLine = 1;

    private int $index = 0;

    private State $state;

    /**
     * @var list<Token>
     */
    private array $tokens = [];

    /**
     * Parses a raw docblock.
     *
     * Returns `false` rather than an empty array when there is nothing to parse.
     * That is cphalcon's contract, from three separate places in `base.c`: a
     * docblock shorter than two characters, one that strips to fewer than two
     * characters, and one that contains no annotation token at all (`@!`), which
     * never runs the `program` rule.
     *
     * @return list<array<string, mixed>> | false
     * @throws Exception on a syntax or scanning error
     */
    public function parse(
        string $docBlock,
        string $file = 'eval code',
        int | null $line = null
    ): array | false {
        if (strlen($docBlock) < 2) {
            return false;
        }

        [$processed, $startLines] = DocBlockStripper::strip($docBlock);

        if (strlen($processed) < 2) {
            return false;
        }

        /**
         * The stripper swallowed `$startLines` newlines on its way to the
         * annotation text. Rewinding by that many means the line the scanner
         * counts back up to is the line in the original file.
         */
        $activeLine = ($line !== null && $line !== 0) ? $line - $startLines : 1;

        $this->state  = new State($processed, $file, $activeLine);
        $this->tokens = (new Scanner($this->state))->tokenize();
        $this->count  = count($this->tokens);
        $this->index  = 0;
        $this->depth  = 0;

        /**
         * Every token has been scanned by now, so this is where the C scanner's
         * line counter would have ended up.
         */
        $this->finalLine = $this->state->activeLine;

        if ($this->count === 0) {
            return false;
        }

        $annotations = $this->parseAnnotationList();

        if ($this->index < $this->count) {
            $this->syntaxError($this->tokens[$this->index]);
        }

        return $annotations;
    }

    /** @phpstan-impure */
    private function advance(): Token
    {
        return $this->tokens[$this->index++];
    }

    /** @phpstan-impure */
    private function expect(Opcode $opcode): Token
    {
        $token = $this->peek();

        if ($token === null || $token->opcode !== $opcode) {
            $this->syntaxError($token);
        }

        return $this->advance();
    }

    /**
     * The scanner's line at the point lemon would look ahead: the line of the
     * next significant token, or, once the stream is exhausted, the line the
     * scanner finished on.
     *
     * @phpstan-impure
     */
    private function lineOfNextToken(): int
    {
        $token = $this->peek();

        return $token === null ? $this->finalLine : $token->line;
    }

    /**
     * `Parsing failed, the annotation is nested too deeply ...`
     */
    private function nestingError(): never
    {
        throw new Exception(
            sprintf(
                'Parsing failed, the annotation is nested too deeply in %s on line %d',
                $this->state->activeFile,
                $this->lineOfNextToken()
            )
        );
    }

    /**
     * ```
     * annotation ::= AT IDENTIFIER PARENTHESES_OPEN argument_list PARENTHESES_CLOSE
     * annotation ::= AT IDENTIFIER PARENTHESES_OPEN PARENTHESES_CLOSE
     * annotation ::= AT IDENTIFIER
     * ```
     *
     * @return array<string, mixed>
     * @phpstan-impure
     */
    private function parseAnnotation(): array
    {
        if ($this->depth >= self::MAX_NESTING_DEPTH) {
            $this->nestingError();
        }

        $this->depth++;

        $this->expect(Opcode::AT);

        $name      = (string) $this->expect(Opcode::IDENTIFIER)->value;
        $arguments = null;

        if ($this->peekIs(Opcode::PARENTHESES_OPEN)) {
            $this->advance();

            if ($this->peekIs(Opcode::PARENTHESES_CLOSE)) {
                $close = $this->advance();
            } else {
                $arguments = $this->parseArgumentList();
                $close     = $this->expect(Opcode::PARENTHESES_CLOSE);
            }

            /**
             * With the closing parenthesis shifted, lemon's state has a single
             * default action and reduces immediately, so the line it stamps is
             * the line of that parenthesis.
             */
            $line = $close->line;
        } else {
            /**
             * Without parentheses lemon cannot decide between shifting `(` and
             * reducing, so it reads one more token first. The line it stamps is
             * the line of *that* token, after any newlines in between.
             */
            $line = $this->lineOfNextToken();
        }

        $this->depth--;

        return Ast::annotation($name, $arguments, $this->state->activeFile, $line);
    }

    /**
     * ```
     * annotation_list ::= annotation_list annotation
     * annotation_list ::= annotation
     * ```
     *
     * @return list<array<string, mixed>>
     * @phpstan-impure
     */
    private function parseAnnotationList(): array
    {
        $list = [];

        while ($this->index < $this->count) {
            $list[] = $this->parseAnnotation();
        }

        return $list;
    }

    /**
     * ```
     * argument_item ::= expr
     * argument_item ::= STRING EQUALS expr
     * argument_item ::= STRING COLON expr
     * argument_item ::= IDENTIFIER EQUALS expr
     * argument_item ::= IDENTIFIER COLON expr
     * ```
     *
     * The one place the grammar is not LL(1): a leading IDENTIFIER or STRING is
     * a name only if `=` or `:` follows, and otherwise it is an expression in its
     * own right.
     *
     * @return array<string, mixed>
     * @phpstan-impure
     */
    private function parseArgumentItem(): array
    {
        $token = $this->peek();

        if (
            $token !== null
            && ($token->opcode === Opcode::STRING || $token->opcode === Opcode::IDENTIFIER)
        ) {
            $next = $this->peek(1);

            if (
                $next !== null
                && ($next->opcode === Opcode::EQUALS || $next->opcode === Opcode::COLON)
            ) {
                $this->advance();
                $this->advance();

                return Ast::namedItem($this->parseExpr(), (string) $token->value);
            }
        }

        return Ast::namedItem($this->parseExpr(), null);
    }

    /**
     * ```
     * argument_list ::= argument_list COMMA argument_item
     * argument_list ::= argument_item
     * ```
     *
     * @return list<array<string, mixed>>
     * @phpstan-impure
     */
    private function parseArgumentList(): array
    {
        $items = [$this->parseArgumentItem()];

        while ($this->peekIs(Opcode::COMMA)) {
            $this->advance();

            $items[] = $this->parseArgumentItem();
        }

        return $items;
    }

    /**
     * ```
     * array ::= BRACKET_OPEN argument_list BRACKET_CLOSE
     * array ::= SBRACKET_OPEN argument_list SBRACKET_CLOSE
     * ```
     *
     * There is no empty-array production in the grammar, so `{}` is a syntax
     * error in cphalcon and stays one here.
     *
     * @return array<string, mixed>
     * @phpstan-impure
     */
    private function parseArray(): array
    {
        if ($this->depth >= self::MAX_NESTING_DEPTH) {
            $this->nestingError();
        }

        $this->depth++;

        $open = $this->advance();

        $closing = $open->opcode === Opcode::BRACKET_OPEN
            ? Opcode::BRACKET_CLOSE
            : Opcode::SBRACKET_CLOSE;

        $items = $this->parseArgumentList();

        $this->expect($closing);

        $this->depth--;

        return Ast::array($items);
    }

    /**
     * ```
     * expr ::= annotation | array | IDENTIFIER | INTEGER | STRING | DOUBLE
     *        | NULL | FALSE | TRUE
     * ```
     *
     * @return array<string, mixed>
     * @phpstan-impure
     */
    private function parseExpr(): array
    {
        $token = $this->peek();

        if ($token === null) {
            $this->syntaxError(null);
        }

        return match ($token->opcode) {
            Opcode::AT                                   => $this->parseAnnotation(),
            Opcode::BRACKET_OPEN, Opcode::SBRACKET_OPEN  => $this->parseArray(),
            Opcode::IDENTIFIER,
            Opcode::INTEGER,
            Opcode::STRING,
            Opcode::DOUBLE                               => Ast::literal(
                $this->advance()->opcode,
                $token->value
            ),
            Opcode::NULL,
            Opcode::FALSE,
            Opcode::TRUE                                 => Ast::literal(
                $this->advance()->opcode,
                null
            ),
            default                                      => $this->syntaxError($token),
        };
    }

    private function peek(int $offset = 0): Token | null
    {
        $position = $this->index + $offset;

        return $position < $this->count ? $this->tokens[$position] : null;
    }

    /** @phpstan-impure */
    private function peekIs(Opcode $opcode): bool
    {
        return $this->peek()?->opcode === $opcode;
    }

    /**
     * Port of the `%syntax_error` block in `parser.php.lemon` (lines 22-65).
     *
     * `near to '...'` is the rest of the buffer *after* the offending token,
     * because `base.c` recomputes `state->start_length` once the token has been
     * scanned. When nothing follows, the C code takes its `start_length == 0`
     * branch and reports an unexpected EOF with no line number.
     */
    private function syntaxError(Token | null $token): never
    {
        $file = $this->state->activeFile;

        if ($token === null || $this->state->remainingLength($token->cursorAfter) <= 0) {
            throw new Exception(sprintf('Syntax error, unexpected EOF in %s', $file));
        }

        $name = Tokens::name($token->opcode);
        $near = $this->state->remainder($token->cursorAfter);

        if ($token->value !== null) {
            throw new Exception(
                sprintf(
                    "Syntax error, unexpected token %s(%s), near to '%s' in %s on line %d",
                    $name,
                    $token->value,
                    $near,
                    $file,
                    $token->line
                )
            );
        }

        throw new Exception(
            sprintf(
                "Syntax error, unexpected token %s, near to '%s' in %s on line %d",
                $name,
                $near,
                $file,
                $token->line
            )
        );
    }
}
