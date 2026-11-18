# lib/StringScanning

The JavaScript parser behind `WPML\ST\StringsScanning\JS\Scanner`
(`classes/strings-scanning/JS/`), which finds `__()`, `_x()`, `_n()` and
`_nx()` calls in the JavaScript files of a scanned theme or plugin.

- `peast/` is [mck89/peast](https://github.com/mck89/peast) **v1.17.4**
  (BSD-3-Clause, `peast/LICENSE`), with its namespace moved from `Peast\` to
  `WPML\ST\Vendor\Peast\`. Nothing else is changed.
- `autoload.php` loads those classes on demand.

WPML defines no global `Peast\`, `Gettext\` or `WP_CLI\` class. Until
wpmldev-12555 this directory was a nested Composer project that installed
WP-CLI's `i18n-command` with `gettext/gettext`, unprefixed.

## Updating peast

Unpack the upstream release and run, from `wpml-string-translation/`:

    python3 bin/prefix-peast.py <upstream peast dir> lib/StringScanning/peast

It replaces `peast/` with the upstream release's `lib/` and `LICENSE`, with
these four rewrites applied to every `.php` file (they are the whole
difference from upstream):

| Upstream | Here |
|---|---|
| `namespace Peast` (line start) | `namespace WPML\ST\Vendor\Peast` |
| `use Peast\` (line start) | `use WPML\ST\Vendor\Peast\` |
| `\Peast\` in code | `\WPML\ST\Vendor\Peast\` |
| `"Peast\\` and `"\\Peast\\` in strings | `"WPML\\ST\\Vendor\\Peast\\`, `"\\WPML\\ST\\Vendor\\Peast\\` |

`tests/phpunit/tests/classes/strings-scanning/JS/PrefixedParserTest.php` fails when a
file under `peast/` still declares or names a global `Peast\` class.
