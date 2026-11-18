const path = require( 'path' );
const crypto = require( 'crypto' );
const fs = require( 'fs' );
const MiniCssExtractPlugin = require( 'mini-css-extract-plugin' );

/**
 * One entry per library; each lands in dist/js/<name>.js (+ dist/css/<name>.css when
 * it imports styles) and in dist/assets.json, which the PHP loader reads: for every
 * entry point its `js` and `css` files, each with `?ver=<md5 of the file>` so a
 * rebuilt file busts the browser cache (the shape tippy's build wrote).
 */
const libraries = {
	otgsSwitcher: './src/js/otgsSwitcher.js',
	otgsPopoverTooltip: './src/js/otgsPopoverTooltip.js',
	otgsTableStickyHeader: './src/js/otgsTableStickyHeader.js',
};

class AssetsManifestPlugin {
	apply( compiler ) {
		compiler.hooks.done.tap( 'OtgsAssetsManifest', ( stats ) => {
			const out = compiler.options.output.path;
			const manifest = { entrypoints: {} };
			const flat = {};
			const version = ( file ) => crypto.createHash( 'md5' ).update( fs.readFileSync( path.join( out, file ) ) ).digest( 'hex' );
			stats.compilation.entrypoints.forEach( ( entry, name ) => {
				const files = entry.getFiles().filter( f => /\.(js|css)$/.test( f ) );
				manifest.entrypoints[ name ] = {};
				files.forEach( file => {
					const type = file.endsWith( '.css' ) ? 'css' : 'js';
					const versioned = file + '?ver=' + version( file );
					manifest.entrypoints[ name ][ type ] = ( manifest.entrypoints[ name ][ type ] || [] ).concat( versioned );
					flat[ path.basename( file ) ] = versioned;
				} );
			} );
			fs.writeFileSync( path.join( out, 'assets.json' ), JSON.stringify( Object.assign( manifest, flat ), null, 2 ) + '\n' );
		} );
	}
}

module.exports = ( env, argv ) => {
	const isProduction = 'production' === argv.mode;

	return {
		entry: libraries,
		output: {
			path: path.join( __dirname, 'dist' ),
			filename: 'js/[name].js',
			library: { name: [ 'OTGSUI', '[name]' ], type: 'var' },
			clean: true,
		},
		module: {
			rules: [
				{
					test: /\.js$/,
					exclude: /node_modules/,
					use: 'babel-loader',
				},
				{
					test: /\.s?css$/,
					use: [
						MiniCssExtractPlugin.loader,
						{ loader: 'css-loader', options: { sourceMap: ! isProduction } },
						{ loader: 'postcss-loader' },
						{ loader: 'sass-loader', options: { sourceMap: ! isProduction } },
					],
				},
			],
		},
		plugins: [
			new MiniCssExtractPlugin( { filename: 'css/[name].css' } ),
			new AssetsManifestPlugin(),
		],
		devtool: isProduction ? false : 'inline-source-map',
	};
};
