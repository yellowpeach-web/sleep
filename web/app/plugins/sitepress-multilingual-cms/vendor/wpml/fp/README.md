# WPML Functional Programming Library

Many functional programming resources can be difficult for beginners to understand. Functional programming isn’t exactly a friendly paradigm for new programmers, and maintenance can be difficult during the coding phase. The switch to functional programming requires time, practice, and a change in the approach to writing code and solving problems.
Although we reviewed many libraries, several of which were excellent, we weren’t able to find what we needed to write expressive code in a functional style - so we decided to create our own.
Take a look at the Functional Programming Library built by our developers to see a collection of functions we’ve used in WPML projects.

## [Documentation](https://git.onthegosystems.com/wpml-packages/fp/-/blob/develop/docs/README.md)

## Calling convention (since wpmldev-12549)

The hot entry points are real static methods: `Obj::prop`, `propOr`, `path`, `pathOr`, `assoc`, `has`, `keys`, `values`,
`Str::startsWith`, `includes`, `Lst::includes`, `Relation::equals`, `propEq`. Called with all their arguments
(and no `Fns::__`) they run plain PHP in one frame: 10-20x cheaper than the curried dispatch, the caller at the top
of every backtrace, a real signature for PHPStan and "go to definition". Called with fewer arguments or a placeholder
they return the curried macro's partial application, exactly as before (`tests/FastPathTest.php` pins both forms to
the pre-12549 implementations).

For new and changed code in the products:

- call `wpml/fp` **fully applied**, or write plain PHP (the closure, the loop, the `if`);
- no new partial application (`Obj::prop( 'id' )` as a callback), `Fns::__`, `pipe()`, `compose()`, `spreadArgs()`,
  `curryN()` or `Either`/`Maybe` flow;
- wpml-core's `build/quality/fp-style-check.sh` is the full-tree ratchet that fails CI on a new one.

Adding a fast path to another entry point follows the pattern in `core/Obj.php`: a real static method with the macro's
name and argument order that hands over to `self::$macros[ $name ]` when not fully applied, a macro that delegates to
it, and a `FastPathTest` row set against a frozen copy of the previous implementation.

