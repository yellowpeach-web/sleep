# WPML's own views: Twig and their PHP twins

WPML is removing Twig (wpmldev-5098; the removal ships in 6.0). A view is converted
one at a time: `<name>.php` is put **next to** `<name>.twig`, and nothing else
changes. The code that renders the view keeps asking for `<name>.twig` with the
same model, and uses the PHP file instead when it is there, so a request that
renders only converted views loads no Twig. The `.twig` file stays until Twig is
removed: WCML and the fallback below still read it.

(The language switcher templates in `language-switchers/` are rendered by
`WPML_LS_Template`, which has its own rule; see the README there.)

## How a view is rendered

| Caller | What it does | Twin used? |
|---|---|---|
| `WPML_Twig_Template_Loader::get_template()->show( $model, 'name.twig' )` | the common case: services get a `WPML_Twig_Template_Deferred` | yes, by `WPML\Templates\PhpTwinRenderer` |
| `WPML_Templates_Factory::get_view( 'name.twig', $model )` (and `show()`) | admin views that extend the factory | yes, unless the subclass returns false from `uses_php_twins()` |
| `get_sandbox_view()` | client-written Twig (language switcher templates) | no |

`PhpTwinRenderer` looks for the twin in the folders the Twig loader was given, in
their order, as Twig looks for the `.twig` file (the first folder that has
`name.php` or `name.twig` decides; `.php` wins when both are there). It includes
the file only if its **real path** is in the `templates/` folder of WPML, String
Translation or Media Translation; a `.php` next to anybody else's `.twig`
(WCML, a theme, uploads) is never included. If the twin throws, the failure is
logged once and Twig renders the view.

## Writing a twin

The model's keys are the variables of the file; `$model` is the whole model and
`$wpml_view` is the renderer. Start the file with `defined( 'ABSPATH' ) || exit;`.
Use the functions in `inc/functions-views.php`:

| Twig | PHP twin |
|---|---|
| `{{ a }}` | `echo wpml_view_esc( $a );` (Twig's escaping: `htmlspecialchars`, double-encodes, leaves URLs alone) |
| `{{ a.b }}` | `wpml_view_attr( $a, 'b' )` (an array key, a property, `b()`, `getB()`, `isB()`; missing is null) |
| `{{ a\|raw }}` | `echo wpml_view_raw( $a );` (HTML built by WPML or carried by a translation; never request data) |
| `{{ a\|default(x) }}` | `wpml_view_default( $a, $x )` |
| `{{ a\|e('html_attr') }}` | `wpml_view_esc( $a, 'html_attr' )` (Twig's attribute escaper: every character but letters, digits and `,.-_` becomes an entity) |
| `{{ a\|length }}` | `wpml_view_length( $a )` (Twig's rule: characters of a scalar, elements of an array, 0 for null) |
| `{{ a\|format(b) }}`, `{{ a\|merge(b) }}`, `{{ a\|json_encode }}` | `sprintf()`, `array_merge()`, `json_encode()` |
| `{% for x in xs %}` | `foreach ( isset( $xs ) && is_array( $xs ) ? $xs : array() as $x )` (an array; a `Traversable` is an empty sequence here, so use `instanceof Traversable` where the model can hold one) |
| `{% include 'p.twig' %}` | `$wpml_view->partial( 'p.twig', array( ... ) );` (needs a `p.php`; it gets only the variables you pass) |

Declare the model's variables in the file's docblock as `@var type|null $name` (never plain `@var type`): PHPStan then does not call the `isset( $name )` a twin needs "always true".

Mind what Twig does silently: an undefined variable or key is empty (use
`isset` / `wpml_view_attr`), Twig removes the newline after a `{% tag %}`, and
`{% spaceless %}` removes the whitespace between tags.

Two traps for the bytes (found converting the emails): PHP removes **one**
newline after `?>`, so a line that ends with `<?php echo ...; ?>` where Twig
had `{{ x }}` needs an empty line after it (Twig keeps the newline after
`}}`); and every `{% set %}` / `{% if %}` / `{% for %}` line leaves its
indentation (not its newline) in the output, so a twin keeps one PHP tag per
Twig tag, on a line with the same indentation, and keeps the blank lines.
A `{% set x %}...{% endset %}` capture is `ob_start()` and
`$x = ob_get_clean();`.

## Proving a twin: the parity test

Add a test that extends `WPML_View_Parity_TestCase`
(`tests/phpunit/utilities/`; example: `tests/phpunit/tests/classes/user-language/
test-wpml-user-language-switcher-view-parity.php`). It renders the Twig view and
its twin for every case you list and reports, per template and case, whether the
**parsed markup including text nodes** and the **bytes** are identical. List
cases that cover each branch of the template: empty, one and many items,
special characters (`& " ' < >`, entities such as `&amp;`), non-ASCII, encoded
URLs, every flag that switches a branch. `WPML_PARITY_REPORT=/path/file.tsv`
writes the results as a table.

## Editing a converted view

A view with a twin renders from the twin. The `.twig` next to it stays until Twig
is removed, but an edit to the `.twig` alone changes nothing on a page, in an
email or in the twin's tests: it is silently ignored. (It happened to the
slug-translation screen: rows added to `slug-translation-ui.twig` after its twin
was written were never printed.)

So change both files, and add the case to the view's parity test. The check
`build/quality/twig-twin-edit-check.sh` (in the `wpcs` job, selftest
`twig-twin-edit-selftest.sh`) enforces it on a merge request: it is red when a
change adds or edits a `.twig` that has a `.php` of the same name in the same
folder, and the change leaves that `.php` alone. It passes when both are changed,
when the `.twig` is deleted (the removal step), and for a `.twig` without a twin.
It reads the `templates/` folders of WPML (with its translation management),
String Translation and Media Translation, the folders twins are rendered from;
WooCommerce Multilingual is not in it.
