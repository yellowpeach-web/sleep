# Language switcher templates

Each folder here is one switcher template. `template.php` renders with the
model `WPML_LS_Model_Build::get()` builds for the switcher's slot (the footer,
a menu, a widget area, the shortcode, the post translations): the model's keys
are its variables. `template.twig` is its Twig twin, kept until Twig is removed
(it renders if the PHP file throws, and for a theme that swaps the folder for a
Twig-only one through `wpml_ls_template_paths`). The two print the same bytes -
`Test_WPML_LS_Core_Templates_Parity` renders both for a matrix of models - so a
change to one is a change to the other. `flag.php`, `names.php` and
`flag-names.php` are the parts the PHP templates share (`flag.twig` for Twig);
values are escaped with `wpml_ls_esc()`, which is Twig's escaping.

## Custom templates and the uploads folder

A custom template is a folder with `config.json` and `template.php` or `template.twig` in the theme, the child theme
or a `wpml_ls_directories_to_scan` folder (`template.php` wins). A folder in the uploads folder
(`uploads/wpml/templates/language-switchers`) is **not used any more** (wpmldev-5098): the scan still finds it, so the
admin notice and Support > Debug information can name it as "not used any more: move it into your theme", but it is not
registered. The settings screen does not offer it, nothing of it is rendered in either engine, and a switcher that names
it (a saved switcher, the shortcode's or the action's `template` argument) shows

- the template of the same folder name once that folder is in the theme (`WPML_LS_Templates::get_moved_from_uploads()`:
  the uploads slug `uploads-<name>` becomes `<theme>-<name>` or `<name>`), else
- the core template of its type (the existing fallback; `wpml_ls_fallback_to_core_template` keeps it blank).

The same holds for a folder that `wpml_ls_template_paths` puts in a template's paths: a folder in the uploads folder is
skipped entirely (`WPML_LS_Template::skip_uploads_folders()`), before WPML's own folder or after it. Its `template.php`
does not run, and its `template.twig` and `flag.twig` override nothing, so it can neither override a template nor be
rendered. Such a folder with a template file is logged once per request. A core template whose every folder was
swapped for uploads ones keeps its own folder.

## The landmark's accessible name

Every wrapper template (in `template.php` and `template.twig`) prints `role="navigation"` with
`aria-label="{{ ls_aria_label }}"` (`$ls_aria_label` in PHP). Landmarks of one type on a page need unique
accessible names (WCAG), so the name is decided where the switcher PRINTS, from
one per-request list in render order
(`WPML\LanguageSwitcher\LandmarkLabels`):

- the first printed landmark keeps the plain label, `Language Switcher`;
- each later one names its slot: `Language switcher (footer)`,
  `Language switcher (menu)`, `Language switcher (widget)`,
  `Language switcher (shortcode)`;
- two switchers of one kind get an instance index on top:
  `Language switcher (widget) 2`, `Language switcher (widget) 3`, ...

Only a switcher that prints its landmark takes a name: a menu switcher (its
items join the theme's own nav), the post translations switcher (its span
carries no aria-label), a switcher hidden because there is nothing to switch
to and an empty one take none, so they never move another switcher's label.
The Language Switcher block takes its name from the same list (it does not
know its placement, so its later instances get the index), and a block whose
saved markup already carries a label keeps it — and that name is taken too.

### `wpml_ls_aria_label`

A site sets its own name per slot with the `wpml_ls_aria_label` filter:

```php
add_filter( 'wpml_ls_aria_label', function ( $label, $slot, $instance ) {
	if ( $slot && $slot->is_footer() ) {
		return 'Footer language switcher';
	}

	return $label;
}, 10, 3 );
```

- `$label` (string) — the name WPML computed (above).
- `$slot` (`WPML_LS_Slot|null`) — the switcher being rendered; `null` for the
  Language Switcher block.
- `$instance` (int) — the landmark's position among the page's printed
  switcher landmarks, in render order (1 is the first). A switcher that
  prints no landmark takes no number.
