# Yellow Peach Starter Theme (Timber/Tailwindcss)

### Create and configure .env

Create a .env file in the root of the project. Copy .env.example to .env.

### Add the relevant DB credentials to the .env file.

```
DB_NAME='{name of the database}'
DB_USER='{database user}'
DB_PASSWORD='{database password}'
```

### Add WP variables

If setting up a local environment set WP_ENV to development for staging = staging and production = production

```
WP_ENV='development'
WP_HOME='http://{url to your localhost}'
```

### Add any required paid plugin keys

```
ACF_PRO_KEY='{ACF key}'
GRAVITY_FORMS_KEY='{Gravity forms key}'
```

## Dependencies

### Rename theme folder
Update the timber-starter theme folder to your new theme name.


### Update composer autoload path
Update the **autoload** path in the composer.json file: "web/app/themes/timber-starter/inc", replacing "timber-starter" with the new theme name.

Install PHP dependencies

```
composer install
```

Navigate to the theme folder

```
cd web/app/themes/{theme}
```

Run a search & replace inside the theme directory, to replace **timber-starter** with whatever the new theme domain
should be.

Swap to correct Node version

```
nvm use
```

If on Windows you can use

```
nvm use $(Get-Content .nvmrc).replace( 'v', '' )
```

Install JS dependencies and build

```
yarn install
yarn run dev
```

## You're done!

Do a victory dance.

## VS Code

If you're using VS Code, make sure you install the Tailwind CSS IntelliSense and Prettier extensions. Depending on how
VS code is configured, spaces can be added to CSS files. This causes all sorts of headaches when compiling assets.

## Twig / Timber

All twig files are at 'web/app/themes/timber-starter/views'

## Gutenberg | ACF Blocks

Block are found in web/app/themes/timber-starter/views/blocks and each have a directory named the block title, within
will be the twig file for the mark-up and a fields.php file for setting the acf fields using the ACF builder, [see below
for more info on the ACF builder](#acf-builder).
To create a new block just create a new directory inside the folder 'web/app/themes/timber-starter/views/blocks/' use
the example directory as starting point
-- filenames should only contain lowercase alphanumeric characters and dashes, and must begin with a letter.

## Option pages

The theme comes with a General settings options page already added and the general settings fields can be global access
within twig files using {{general_settings}}, this is set using the get_general_settings() function in the
helper-functions.php

## Images

In twig files use the responsive_img() function to create a responsive image, pass the image object, array or url and
the arg
array['size','alt','class] size is a required arg, function located in the helper-functions.php

## Theme CSS | Tailwindcss

### CSS

#### Body typography:

Body text and fonts can be set in the partial utilities/base.css setting all base font styling under

```
body.yp-frontend, .wp-block .acf-block-preview{}
```

Headings and other typography styling can be found utilities/typography.css

#### Editor | Admin styling

Block editor styles are enqueue separately from the frontend styling, to style the block editor use the partial
gutenberg/formatting.css

### Tailwindcss

#### Adding custom utilities:

Add custom utilities in the theme-configuration.json file and add the selector in the tailwind.config.js file

# Symfony var-dumper

The theme is bundled with Symfony var-dumper using the Laravel dd() d() functions. simply call the dd() or d() function
passing data or {{ dd() }} or {{ d() }} within twig templates

# ACF builder

#### This theme is using the StoutLogic ACF Builder, [useful Docs can be found below](#useful-docs). Functions and classes can be found in web/app/themes/timber-starter/inc/AcfBuilder/ThemeFieldBuilder.php

## General

Note: use setFields() at the end of the acf builder method chain to run addLocalFieldGroup()

## Blocks

Use the field.php file in the block directories to set the block fields, see example.php in the block/example directory
to see an example.

Note: You must use setLocation to assign to the block, and at the end of the method chain call setFields() to run
addLocalFieldGroup() function on the fields.

## Clone ACF fields

This theme comes packaged with a heading, repeater buttons and content ACF fields which can be cloned and use where
required saving on repetition.
Add the method getClone() in the ACf builder method chain with the required method call i.e. to clone the heading field
add getClone('heading').

Add new clone fields in the web/app/themes/timber-starter/inc/AcfBuilder/ThemFieldBuilder.php trait making sure the method
name and field names are unique.

## Useful docs

[StoutLogic wiki](https://github.com/StoutLogic/acf-builder/wiki)

[Log1x Cheatsheet](https://github.com/Log1x/acf-builder-cheatsheet)

[ACF Docs](https://www.advancedcustomfields.com/resources/)
