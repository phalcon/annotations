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

namespace Phalcon\Annotations\Docblock;

use Phalcon\Annotations\Docblock\Exceptions\UnknownAnnotationExpression;
use Phalcon\Annotations\Docblock\Scanner\Opcode;

use function count;

/**
 * Represents a single annotation in an annotations collection.
 *
 * Port of `phalcon/Annotations/Annotation.zep`. It turns the parser's array AST
 * into resolved PHP values: an integer literal becomes its string, `null`/`true`/
 * `false` become the real values, an array node becomes a PHP array, and a
 * nested annotation becomes another Annotation.
 */
class Annotation
{
    /**
     * Resolved arguments, keyed by name where the argument had one.
     *
     * @var array<array-key, mixed>
     */
    protected array $arguments = [];

    /**
     * The unresolved argument nodes, exactly as the parser emitted them.
     *
     * @var array<array-key, mixed>
     */
    protected array $exprArguments = [];

    protected string | null $name = null;

    /**
     * @param array<string, mixed> $reflectionData
     *
     * @throws UnknownAnnotationExpression
     */
    public function __construct(array $reflectionData)
    {
        if (isset($reflectionData['name'])) {
            /** @var string $name */
            $name        = $reflectionData['name'];
            $this->name  = $name;
        }

        /**
         * Process annotation arguments
         */
        if (isset($reflectionData['arguments'])) {
            /** @var array<array-key, array<string, mixed>> $exprArguments */
            $exprArguments = $reflectionData['arguments'];
            $arguments     = [];

            foreach ($exprArguments as $argument) {
                /** @var array<string, mixed> $expr */
                $expr             = $argument['expr'];
                $resolvedArgument = $this->getExpression($expr);

                if (isset($argument['name'])) {
                    /** @var array-key $key */
                    $key             = $argument['name'];
                    $arguments[$key] = $resolvedArgument;
                } else {
                    $arguments[] = $resolvedArgument;
                }
            }

            $this->arguments     = $arguments;
            $this->exprArguments = $exprArguments;
        }
    }

    /**
     * Returns an argument in a specific position
     */
    public function getArgument(int | string $position): mixed
    {
        return $this->arguments[$position] ?? null;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * Returns the expression arguments without resolving
     *
     * @return array<array-key, mixed>
     */
    public function getExprArguments(): array
    {
        return $this->exprArguments;
    }

    /**
     * Resolves an annotation expression
     *
     * @param array<string, mixed> $expr
     *
     * @throws UnknownAnnotationExpression
     */
    public function getExpression(array $expr): mixed
    {
        /** @var int $type */
        $type = $expr['type'];

        switch ($type) {
            case Opcode::INTEGER->value:
            case Opcode::DOUBLE->value:
            case Opcode::STRING->value:
            case Opcode::IDENTIFIER->value:
                return $expr['value'];

            case Opcode::NULL->value:
                return null;

            case Opcode::FALSE->value:
                return false;

            case Opcode::TRUE->value:
                return true;

            case Opcode::ARRAY->value:
                $arrayValue = [];

                /** @var array<array-key, array<string, mixed>> $items */
                $items = $expr['items'];

                foreach ($items as $item) {
                    /** @var array<string, mixed> $itemExpr */
                    $itemExpr     = $item['expr'];
                    $resolvedItem = $this->getExpression($itemExpr);

                    if (isset($item['name'])) {
                        /** @var array-key $key */
                        $key              = $item['name'];
                        $arrayValue[$key] = $resolvedItem;
                    } else {
                        $arrayValue[] = $resolvedItem;
                    }
                }

                return $arrayValue;

            case Opcode::ANNOTATION->value:
                return new Annotation($expr);

            default:
                throw new UnknownAnnotationExpression((string) $type);
        }
    }

    public function getName(): string | null
    {
        return $this->name;
    }

    /**
     * Returns a named argument
     */
    public function getNamedArgument(string $name): mixed
    {
        return $this->arguments[$name] ?? null;
    }

    /**
     * Returns a named parameter
     */
    public function getNamedParameter(string $name): mixed
    {
        return $this->getNamedArgument($name);
    }

    public function hasArgument(int | string $position): bool
    {
        return isset($this->arguments[$position]);
    }

    /**
     * Returns the number of arguments that the annotation has
     */
    public function numberArguments(): int
    {
        return count($this->arguments);
    }
}
