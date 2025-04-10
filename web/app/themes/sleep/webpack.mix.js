let mix = require("laravel-mix");
let path = require("path");

mix.setPublicPath(path.resolve("./"));

mix.js("resources/js/app.js", "build/js");

mix.postCss("resources/css/app.css", "build/css");

mix.postCss("resources/css/gutenberg-style.css", "./build/css/admin", [
  require("postcss-prefix-selector")({
    prefix: ".editor-styles-wrapper",
    exclude: ["body.yp-frontend", ".wp-block.acf-block-preview"],
  }),
]);

mix.postCss("resources/css/editor-style.css", "./build/css/admin", [
  require("postcss-prefix-selector")({
    prefix: ".editor-styles-wrapper .acf-block-preview",
    exclude: ["body.yp-frontend", ".wp-block.acf-block-preview"],
  }),
]);

mix.options({
  postCss: [
    require("postcss-import"),
    require("tailwindcss/nesting"),
    require("tailwindcss"),
    require("postcss-cachebuster")({
      imagesPath: "/../../../",
      cssPath: "/../../../",
    }),
    require("autoprefixer"),
  ],
});

mix.browserSync({
  proxy: "http://timber-starter-theme.localhost",
  files: ["**/*.php", "**/**/*.php", "**/*.twig", "**/*.json", "./resources/", "**/*.css"],
});

mix.version();
// mix.combine([
// ], './build/css/vendor.css');
//

// mix.combine([], './build/js/vendor.js');
