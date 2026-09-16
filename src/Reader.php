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

use Phalcon\Annotations\Docblock\Parser\Parser;
use ReflectionClass;
use ReflectionException;

use function array_keys;
use function is_array;
use function is_string;

/**
 * Parses docblocks returning an array with the found annotations.
 *
 * Port of `phalcon/Annotations/Reader.zep`. PHP's reflection API supplies the
 * raw comments; everything after that is this package's own parser, where
 * cphalcon called into its C extension.
 */
class Reader implements ReaderInterface
{
    /**
     * Parses a raw doc block returning the annotations found
     *
     * @return list<array<string, mixed>> | false
     * @throws Exception on a syntax or scanning error
     */
    public static function parseDocBlock(
        string $docBlock,
        string | bool | null $file = null,
        int | null $line = null
    ): array | false {
        if (!is_string($file)) {
            $file = 'eval code';
        }

        return (new Parser())->parse($docBlock, $file, $line);
    }

    /**
     * Reflection reports an unknown start line as `false`. cphalcon let that
     * reach the C parser, where it coerced to 0 and took the "no line given"
     * path; null means the same thing here.
     */
    private static function toLine(int | false $line): int | null
    {
        return $line === false ? null : $line;
    }

    /**
     * Reads annotations from the class docblocks, its constants, properties
     * and/or methods
     *
     * @return array<string, mixed>
     * @throws ReflectionException
     * @throws Exception
     */
    public function parse(string $className): array
    {
        $annotations = [];

        /**
         * A ReflectionClass is used to obtain the class docblock. A name that
         * does not resolve surfaces as a ReflectionException, which is the
         * documented failure mode rather than something to guard against here.
         *
         * @phpstan-ignore argument.type
         */
        $reflection = new ReflectionClass($className);
        $fileName   = $reflection->getFileName();

        $comment = $reflection->getDocComment();

        if ($comment !== false) {
            $classAnnotations = self::parseDocBlock(
                $comment,
                $fileName,
                self::toLine($reflection->getStartLine())
            );

            if (is_array($classAnnotations)) {
                $annotations['class'] = $classAnnotations;
            }
        }

        /**
         * Get class constants
         */
        $constants = $reflection->getConstants();

        if (!empty($constants)) {
            /**
             * Line declaration for constants isn't available
             */
            $annotationsConstants = [];

            foreach (array_keys($constants) as $constant) {
                $constantReflection = $reflection->getReflectionConstant((string) $constant);

                if ($constantReflection === false) {
                    continue;
                }

                $comment = $constantReflection->getDocComment();

                if ($comment !== false) {
                    $constantAnnotations = self::parseDocBlock($comment, $fileName, 1);

                    if (is_array($constantAnnotations)) {
                        $annotationsConstants[$constant] = $constantAnnotations;
                    }
                }
            }

            if (!empty($annotationsConstants)) {
                $annotations['constants'] = $annotationsConstants;
            }
        }

        /**
         * Get the class properties
         */
        $properties = $reflection->getProperties();

        if (!empty($properties)) {
            /**
             * Line declaration for properties isn't available
             */
            $annotationsProperties = [];

            foreach ($properties as $property) {
                $comment = $property->getDocComment();

                if ($comment !== false) {
                    $propertyAnnotations = self::parseDocBlock($comment, $fileName, 1);

                    if (is_array($propertyAnnotations)) {
                        $annotationsProperties[$property->name] = $propertyAnnotations;
                    }
                }
            }

            if (!empty($annotationsProperties)) {
                $annotations['properties'] = $annotationsProperties;
            }
        }

        /**
         * Get the class methods
         */
        $methods = $reflection->getMethods();

        if (!empty($methods)) {
            $annotationsMethods = [];

            foreach ($methods as $method) {
                $comment = $method->getDocComment();

                if ($comment !== false) {
                    $methodAnnotations = self::parseDocBlock(
                        $comment,
                        $method->getFileName(),
                        self::toLine($method->getStartLine())
                    );

                    if (is_array($methodAnnotations)) {
                        $annotationsMethods[$method->name] = $methodAnnotations;
                    }
                }
            }

            if (!empty($annotationsMethods)) {
                $annotations['methods'] = $annotationsMethods;
            }
        }

        return $annotations;
    }
}
