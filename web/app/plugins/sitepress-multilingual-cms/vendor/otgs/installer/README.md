# OTGS WP Installer

OTGS WP Installer is a library that allows you to install and upgrade plugins and themes developed by OnTheGoSystems.

## Installation

First, add OTGS WP Installer as a dependency with [Composer](http://getcomposer.org):

```bash
composer require otgs/installer
```

Make sure that your bootstrap file is loading the composer autoloader:

```php
require_once 'vendor/autoload.php';
```

Then, load the OTGS WP Installer bootstrap. Before the `plugins_loaded` action add:

```php
include 'vendor/otgs/installer/loader.php';
```

Optionally, you can specify parameters to configure showing a dedicated UI under `Plugins -> Install New` or to load specific repositories.
By default, all repositories configrede in `repositories.xml` will be loaded:
* wpml - [WPML.org](http://wpml.org)
* toolset - [WP-Types.com](http://wp-types.com)

### Repository configuration (`repositories.xml`)

Each `<repository>` element says where the product information comes from:

* `<products>` — the components document: what a site can install and why (names, descriptions,
  recommendations, sections, packages and prices). Evergreen; it changes when a component is added
  or retired, not when a version ships.
* `<releases>` — optional. The releases document: what versions exist, per plugin
  (`version`, `date`, `url`, `changelog`, `tested`, `channels`). Installer reads it in the same
  refresh and merges it onto `downloads.plugins.<slug>`, so everything downstream sees one
  combined shape. A repository without this element serves one combined products document, the
  way every repository did before WPML 5.0.
* `<apiurl>` — the API that hands a registered site its bucket. For a repository with a
  `<releases>` element only the releases document is bucketed: the components document is the
  same for every site.
* `<default_products>` — what the Commercial tab falls back to when the components document
  cannot be reached at all. It must carry an empty value for every top-level key Installer reads
  without an `isset` guard.

Both URLs can be overridden per repository with a constant, which wins over everything else and
is how a development site is pointed at a local feed:

```php
define( 'OTGS_INSTALLER_WPML_PRODUCTS', 'https://wpml.loc/feeds/wpml-components.json' );
define( 'OTGS_INSTALLER_WPML_RELEASES', 'https://wpml.loc/feeds/wpml-releases.json' );
```

Set them as a pair. A site that overrides only the components URL reads production releases.

A site can also keep a copy of the feed documents on disk and read them from there, which is what
a development site does while the feeds are not published yet:

```php
define( 'OTGS_INSTALLER_WPML_FEEDS_DIR', '/path/to/wpml-core/build/fixtures/wpml-org-feeds' );
```

The directory holds the documents under the file names the repository is configured to read, which
are the basenames of its `<products>` and `<releases>` URLs: for `wpml` that is
`wpml-components.json` and `wpml-releases.json`. The path must be absolute and readable by PHP; a
trailing separator is ignored. The directory covers both documents at once, and a repository
without a `<releases>` element takes only its components document from it.

The order per document is: the `OTGS_INSTALLER_<REPO>_PRODUCTS` or `OTGS_INSTALLER_<REPO>_RELEASES`
constant, then `OTGS_INSTALLER_<REPO>_FEEDS_DIR`, then the bucket the API hands a registered site,
then the URL in `repositories.xml`. Those two URL constants may also carry a path on disk instead
of a URL, for a site that wants to place one document and leave the other one alone: anything that
does not start with `http://` or `https://` is read as a file.

A path that cannot be read fails the way an unreachable server fails. Installer shows its usual
"cannot contact our updates server" message and writes the path it tried into the Installer log,
under WPML or Toolset support debug information.

```php
WP_Installer_Setup( $wp_installer_instance,  
    array(
        'plugins_install_tab'   => '1',   // optional, default value: 0
        'repositories_include'  => array( 'wpml' ) // optional, default to empty (show all)
    )
); 
```

After `init`, configure display the OTGS WP Installer UI like in the example below:

```php 
WP_Installer_Show_Products( 
    array( 
        'template'         => 'compact', //required
        'product_name'     => 'WPML', 
        'box_title'        => 'Multilingual Avada', 
        'name'             => 'Avada', //name of theme/plugin
        'box_description'  => 'Avada theme is fully compatible with WPML - the WordPress Multilingual plugin. WPML lets 
                                      you add languages to your existing sites and includes advanced translation management.', 
        'repository'       => 'wpml', // required
        'package'          => 'multilingual-cms', // required
        'product'          => 'multilingual-cms' // required
    ) 
);
```

* `template` two options available: default and compact. Default will be the same GUI as on the Plugins -> Install new page while compact is a smaller version that can be fit in a different already existing screen
* `repository` only one product of a specific product package from a specific repository can be shown
* `package` only one product of a specific product package from a specific repository can be shown
* `product` only one product of a specific product package from a specific repository can be shown

