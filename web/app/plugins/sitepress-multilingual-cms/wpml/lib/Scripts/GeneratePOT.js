const { execFileSync } = require('child_process');
const { readFileSync, unlinkSync } = require('fs');
const path = require('path');
const webpackConfig = require('../../webpack.config');
const domain = "wpml";

const files = Object.keys( webpackConfig.entry );
const srcPath = path.resolve( __dirname, '../../src' );

// WordPress loads a script's catalogue as wpml-<locale>-<handle>.json, so each
// template must be named after the handle the bundle is enqueued with.
const configFiles = [
  'config-scripts.php',
  'config-admin-pages.php',
  'config-admin-notices.php',
];

// Bundles enqueued outside the configs above, with the handle they are
// enqueued under.
const handleExceptions = {
  'wpml-deleting-content.js': {
    handle: 'wpml-deleting-content',
    source: 'DeletingContentController.php',
  },
  'wc-language-editor.js': {
    handle: 'wpml-wc-language-editor',
    source: 'LanguageEditorEmbed.php, LanguageEditorWizard.php',
  },
  'connection-status.js': {
    handle: 'wpml-connection-status',
    source: 'ReconnectNotice.php',
  },
  'wc-deletion-dialog.js': {
    handle: 'wpml-wc-deletion-dialog',
    source: 'DialogHost.php',
  },
  'notice-warning-translation-edit.js': {
    handle: 'wpml_warning_translation_edit',
    source: 'WarningTranslationEditController.php',
  },
};

// The configs call __() and use WPML constants and classes, so they are
// required with __() stubbed, WPML's constants defined and the WPML
// namespace autoloaded from src/.
const loadConfigBootstrap = `
if ( ! function_exists( '__' ) ) {
  function __( $text ) {
    return $text;
  }
}
require_once $argv[2] . '/constants.php';
spl_autoload_register( function ( $class ) use ( $argv ) {
  if ( strpos( $class, 'WPML\\\\' ) !== 0 ) {
    return;
  }
  $file = $argv[2] . '/' . str_replace( '\\\\', '/', substr( $class, 5 ) ) . '.php';
  if ( is_file( $file ) ) {
    require_once $file;
  }
} );
echo json_encode( require $argv[1], JSON_THROW_ON_ERROR );
`;

const loadConfig = ( configFile ) => JSON.parse(
  execFileSync(
    'php',
    [
      '-r',
      loadConfigBootstrap,
      path.join( srcPath, configFile ),
      srcPath,
    ],
    { encoding: 'utf8' }
  )
);

const isObject = ( value ) => value !== null && typeof value === 'object';

// Collects every { id, src } entry of the nested `scripts` lists.
const collectNestedScripts = ( node, scripts = [] ) => {
  if ( ! isObject( node ) ) {
    return scripts;
  }

  Object.entries( node ).forEach( ( [ key, value ] ) => {
    if ( key === 'scripts' && isObject( value ) ) {
      Object.values( value ).forEach( ( script ) => {
        if ( isObject( script ) && script.id && script.src ) {
          scripts.push( { handle: script.id, src: script.src } );
        }
      } );
    } else {
      collectNestedScripts( value, scripts );
    }
  } );

  return scripts;
};

const scriptsOfConfig = ( configFile, config ) => {
  if ( configFile === 'config-scripts.php' ) {
    return Object.entries( config )
      .filter( ( [ , script ] ) => isObject( script ) && script.src )
      .map( ( [ handle, script ] ) => ( { handle, src: script.src } ) );
  }

  return collectNestedScripts( config );
};

// Bundle file name => { handle => [ where it is declared ] }.
const declarations = {};
const declare = ( fileName, handle, source ) => {
  declarations[ fileName ] = declarations[ fileName ] || {};
  declarations[ fileName ][ handle ] = declarations[ fileName ][ handle ] || [];
  if ( ! declarations[ fileName ][ handle ].includes( source ) ) {
    declarations[ fileName ][ handle ].push( source );
  }
};

configFiles.forEach( ( configFile ) => {
  scriptsOfConfig( configFile, loadConfig( configFile ) )
    .filter( ( { src } ) => src.endsWith( '.js' ) )
    .forEach( ( { handle, src } ) => declare( path.basename( src ), handle, configFile ) );
} );

Object.entries( handleExceptions ).forEach( ( [ fileName, { handle, source } ] ) => {
  declare( fileName, handle, source );
} );

let hasErrors = false;

const handlesByFileName = {};
Object.entries( declarations ).forEach( ( [ fileName, handles ] ) => {
  const declared = Object.entries( handles );
  if ( declared.length > 1 ) {
    hasErrors = true;
    console.error(
      `Bundle "${fileName}" is declared under more than one script handle: ` +
      declared.map( ( [ handle, sources ] ) => `"${handle}" (${sources.join( ', ' )})` ).join( ', ' ) +
      '. Its catalogue can only be named after one handle.'
    );
    return;
  }

  handlesByFileName[ fileName ] = declared[0][0];
} );

if ( hasErrors ) {
  process.exit( 1 );
}

const hasTranslatableEntries = ( filePath ) => {
  const pot = readFileSync( filePath, 'utf8' );

  // The first msgid is the POT header. Any additional msgid represents a
  // translatable string extracted from the JavaScript bundle.
  return ( pot.match( /^msgid\s/gm ) || [] ).length > 1;
};

const fallbackUses = [];

files.forEach((file) => {
  const fileName = file + '.js'
  const fallbackHandle = file.startsWith( 'wpml-' ) ? file : `wpml-${file}`;
  const declaredHandle = handlesByFileName[ fileName ];
  const handle = declaredHandle || fallbackHandle;
  const output = `languages/pot/${domain}-${handle}.pot`;

  if ( ! declaredHandle ) {
    fallbackUses.push( `${fileName} -> ${handle}` );
    console.log(`No declared script handle for ${fileName}, using the fallback name ${handle}`);
  }

  try {
    console.log(`Generating ${output} for the ${handle} script handle`);
    execFileSync(
      'wp',
      [
        'i18n',
        'make-pot',
        './public/js',
        output,
        `--include=${fileName}`,
        '--no-location',
        `--domain=${domain}`,
        '--skip-php',
      ],
      { stdio: 'inherit' }
    );

    if ( ! hasTranslatableEntries( output ) ) {
      unlinkSync( output );
      console.log(`Skipping empty POT file: ${output}`);
    } else if ( ! declaredHandle ) {
      unlinkSync( output );
      hasErrors = true;
      console.error(
        `Bundle "${fileName}" has translatable strings but no declared script handle in ` +
        'config-scripts.php, config-admin-pages.php or config-admin-notices.php.'
      );
    }
  } catch (error) {
    hasErrors = true;
    console.error(`Error executing command for ${fileName}:`, error);
  }
});

if ( fallbackUses.length ) {
  console.log(`Bundles that used the fallback name (${fallbackUses.length}):\n  ${fallbackUses.join( '\n  ' )}`);
}

if ( hasErrors ) {
  process.exitCode = 1;
}
