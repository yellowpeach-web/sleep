# String Translation's own views: Twig and their PHP twins

Each `<name>.twig` here has a `<name>.php` next to it (wpmldev-5098: WPML is removing Twig). The code that
renders a view keeps asking for `<name>.twig` with the same model; WPML's renderer
(`sitepress-multilingual-cms/classes/templates/PhpTwinRenderer.php`) prints the PHP file instead when it is there,
so a request that renders only converted views loads no Twig. The `.twig` files stay until Twig is removed: they are
the fallback when a twin fails.

| Folder | Views | Rendered by |
|---|---|---|
| `slug-translation/` | `slug-translation-ui` | `WPML_ST_Element_Slug_Translation_UI` |
| `string-tracking/` | `positions-in-page`, `positions-in-source` | `WPML_ST_String_Positions_In_Page`, `WPML_ST_String_Positions_In_Source` (through `WPML_ST_String_Tracking_AJAX_Factory`) |
| `theme-plugin-localization/` | `theme-plugin-localization-ui` (with `-category-header` and `-item`), `-table-header` | `WPML_Theme_Plugin_Localization_UI` |

A twin prints the bytes its Twig view prints, quirks included. When you change one, change the other, and run the
parity gate (`sitepress-multilingual-cms/tests/phpunit/tests/classes/string-translation-views/`), which renders both
for the same models. How a view is written and proven: `sitepress-multilingual-cms/templates/README.md`.
