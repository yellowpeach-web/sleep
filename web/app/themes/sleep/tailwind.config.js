const plugin = require("tailwindcss/plugin");
const configuration = require("./theme-configuration.json");
const defaultTheme = require("tailwindcss/defaultTheme");

module.exports = {
  mode: "jit",
  configuration,
  content: require("fast-glob").sync(["./**/*.php", "./**/*.twig", "./safelist.txt"]),
  theme: {
    screens: Object.fromEntries(Object.entries(defaultTheme.screens).filter(([key, value]) => key !== "2xl")),
    container: {
      ...configuration.container,
    },
    spacing: configuration.spacing,
    fontSize: configuration.fontSize,
    extend: {
      colors: configuration.colors,
      fontFamily: configuration.fontFamily,
      maxWidth: configuration.maxWidth,
      transitionProperty: configuration.transition,
      zIndex: configuration.zIndex,
      spacing: configuration.spacing,
      backgroundImage: configuration.backgroundImage,
      boxShadow: configuration.boxShadow,
      borderRadius: configuration.borderRadius,
      listStyleType: configuration.listStyleType,
    },
  },
  plugins: [
    require("@tailwindcss/forms")({
      strategy: "class",
    }),
  ],
};
