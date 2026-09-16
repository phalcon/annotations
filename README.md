# Phalcon Annotations

Docblock annotations parser for the Phalcon Framework, in pure PHP.

## What this is

Phalcon v6 reads annotations as [native PHP attributes][php-attributes] through
reflection, so the framework no longer has an annotation language. This package
is for the code that has not moved yet. It parses the classic
`/** @Route("/users") */` docblock syntax, so a v4/v5 application can run on v6
without rewriting every annotation into an attribute first.

It is a behavioural port of the annotations parser in
[cphalcon](https://github.com/phalcon/cphalcon), which is written in C with a
re2c scanner and a lemon grammar. The output is identical: the same nested-array
AST, the same integer type codes, the same syntax-error strings.

There is no C extension to build, and no runtime dependency beyond
[`phalcon/traits`](https://github.com/phalcon/traits).

## Requirements

* PHP `>= 8.1 < 9.0`
* `ext-mbstring`
* `ext-apcu`, only for the APCu adapter

## Installation

```bash
composer require phalcon/annotations
```

## Usage

### Parsing a docblock directly

```php
use Phalcon\Annotations\Docblock\Reader;

$parsed = Reader::parseDocBlock('/** @Route("/users", methods={"GET"}) */');
```

`parseDocBlock()` returns the raw AST, or `false` when the docblock contains no
annotations at all.

### Reading a class

```php
use Phalcon\Annotations\Docblock\Adapter\Memory;

$annotations = new Memory();

$reflection = $annotations->get(MyController::class);

$route = $annotations->getMethod(MyController::class, 'indexAction')->get('Route');

$route->getArgument(0);                 // "/users"
$route->getNamedArgument('methods');    // ["GET"]
```

The adapter caches the parsed `Reflection` per class, so a class is parsed once
regardless of how many annotations are read from it. `Memory`, `Stream` and
`Apcu` adapters are provided; they share the base class, so the backend is
interchangeable.

## Namespace

Classes live under `Phalcon\Annotations\Docblock\`, not `Phalcon\Annotations\`.
Phalcon v6 already occupies the latter with its attribute-based component, and
both packages are installed side by side during a migration.

This package is also self-contained rather than plugging into v6's annotations
service, because that service builds its collections from `ReflectionAttribute`
objects, which a docblock parser does not have. Use
`Phalcon\Annotations\Docblock\Adapter\*` where a v5 application used
`Phalcon\Annotations\Adapter\*`.

## Compatibility notes

The parser reproduces cphalcon's behaviour, including the surprising parts:

* **Type codes are the output format.** `300` annotation, `301` integer, `302`
  double, `303` string, `304` null, `305` false, `306` true, `307` identifier,
  `308` array.
* **Numbers stay strings.** `@Foo(1)` resolves to `"1"`, not `1`.
* **Strings are not unescaped.** `"a\"b"` keeps the backslash; only the
  surrounding quotes are removed.
* **Optional AST keys are omitted, never null.** An annotation with no arguments
  has no `arguments` key at all.
* **`null`, `true` and `false` are case-insensitive**, but `nullable` is still an
  identifier.
* **`{...}` and `[...]` both build an array**, and neither has an empty form:
  `@Foo({})` is a syntax error.
* **Nesting fails closed** past roughly 100 levels, matching lemon's fixed stack.
* **`false`, not an empty array**, comes back from a docblock with nothing in it.

## Development

```bash
composer install
composer test-unit     # PHPUnit
composer analyze       # PHPStan, level max, no baseline
composer cs            # PHP_CodeSniffer, PSR-12
composer cs-fixer-fix  # php-cs-fixer
```

### Verifying against the C parser

`tests/differential.php` runs a corpus of docblocks through both this package and
`Phalcon\Annotations\Reader` from ext-phalcon 5.x, and reports any difference in
the AST or in the thrown message. It needs the extension loaded, and exits
quietly when it is absent:

```bash
php tests/differential.php
```

`tests/generate-golden.php` records the expected AST for every fixture in
`tests/fixtures`. Run it with ext-phalcon loaded and the goldens come from the C
parser, which is what makes `FixturesTest` a cross-implementation check rather
than a snapshot of this package's own behaviour.

## Why a hand-written parser

`volt` and `phql` were ported out of cphalcon by transliterating the generated
artefacts: the re2c DFA became a PHP state machine, the lemon tables became PHP
arrays. That is the right trade for their grammars, which are 159 and 171
productions with full operator-precedence expression rules.

The annotations grammar is 25 productions over 17 terminals with **no operator
precedence at all**: `expr` is a flat nine-way alternation, and the only
recursion is structural nesting. It is LL(1) apart from one token of lookahead in
`argument_item`. Parse tables exist to resolve ambiguity and precedence, and
there is neither here. lemon's driver template alone would be more code than the
whole parser, so the 25 productions are written out directly, one method per
nonterminal, like [Zephir's][zephir] pure-PHP parser.

[php-attributes]: https://www.php.net/manual/en/language.attributes.php
[zephir]: https://github.com/zephir-lang/zephir
