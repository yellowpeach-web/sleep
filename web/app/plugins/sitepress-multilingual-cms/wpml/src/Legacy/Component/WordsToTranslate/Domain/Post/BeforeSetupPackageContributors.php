<?php

namespace WPML\Legacy\Component\WordsToTranslate\Domain\Post;

class BeforeSetupPackageContributors {

  private static $registered = false;


  public static function register() {
    if ( self::$registered ) {
      return false;
    }
    self::$registered = true;

    self::registerMediaTexts();
    self::registerBlockStrings();

    return true;
  }


  public static function reset() {
    self::$registered = false;
  }


  private static function registerMediaTexts() {
    if ( ! class_exists( \WPML\MediaTranslation\AddMediaDataToTranslationPackageFactory::class ) ) {
      return;
    }

    $contributor = ( new \WPML\MediaTranslation\AddMediaDataToTranslationPackageFactory() )->create();

    if ( is_object( $contributor ) && method_exists( $contributor, 'addPackageFilter' ) ) {
      $contributor->addPackageFilter( true );
    }
  }


  private static function registerBlockStrings() {
    if (
      ! class_exists( '\WPML_Gutenberg_Integration' )
      || ! class_exists( '\WPML_Gutenberg_Integration_Factory' )
      || ! class_exists( '\WPML_Gutenberg_Config_Option' )
    ) {
      return;
    }

    self::serveWpmlsOwnBlockRules();

    add_filter( 'wpml_tm_translation_job_data', [ self::class, 'blockStringsInsteadOfTheBody' ], 20, 2 );
  }


  private static function serveWpmlsOwnBlockRules() {
    if (
      ! defined( 'WPML_PLUGIN_PATH' )
      || ! class_exists( '\WPML_XML_Config_Read_File' )
      || ! class_exists( '\WPML_XML_Config_Validate' )
      || ! class_exists( '\WPML_XML2Array' )
    ) {
      return;
    }

    $file = WPML_PLUGIN_PATH . '/wpml-config.xml';
    if ( ! is_readable( $file ) ) {
      return;
    }

    $reader = new \WPML_XML_Config_Read_File( $file, new \WPML_XML_Config_Validate(), new \WPML_XML2Array() );
    $config = $reader->get();

    if ( ! is_array( $config ) ) {
      return;
    }

    $options = [
      \WPML_Gutenberg_Config_Option::OPTION,
      \WPML_Gutenberg_Config_Option::OPTION_IDS_IN_BLOCKS,
      \WPML_Gutenberg_Config_Option::OPTION_MEDIA_IN_BLOCKS,
    ];

    $captured = [];

    $capture =
      function ( $value, $old, $option ) use ( &$captured ) {
        $captured[ $option ] = $value;

        return $old;
      };

    foreach ( $options as $option ) {
      add_filter( 'pre_update_option_' . $option, $capture, 10, 3 );
    }

    ( new \WPML_Gutenberg_Config_Option() )->update_from_config( $config );

    foreach ( $options as $option ) {
      remove_filter( 'pre_update_option_' . $option, $capture, 10 );
    }

    foreach ( $captured as $option => $value ) {
      if ( ! $value ) {
        continue;
      }

      add_filter(
        'pre_option_' . $option,
        function () use ( $value ) {
          return $value;
        }
      );
    }
  }


  public static function blockStringsInsteadOfTheBody( $package, $post ) {
    if (
      ! is_array( $package )
      || ! isset( $package['contents']['body'] )
      || ! is_object( $post )
      || ! isset( $post->post_content )
    ) {
      return $package;
    }

    if ( ! self::isBlockPage( (string) $post->post_content ) ) {
      return $package;
    }

    $strings = self::blockStringsOf( (string) $post->post_content );

    $index = 0;
    foreach ( $strings as $value ) {
      $package['contents'][ 'package-string-estimate-' . $index ] = [
        'translate' => 1,
        'data'      => base64_encode( $value ),
        'format'    => 'base64',
      ];
      $index++;
    }

    $package['contents']['body']['translate'] = 0;

    return $package;
  }


  private static function isBlockPage( $content ) {
    return strpos( $content, '<!-- wp:' ) !== false
      && class_exists( '\WPML_Gutenberg_Integration' )
      && class_exists( '\WPML_Gutenberg_Integration_Factory' )
      && class_exists( '\WPML_Gutenberg_Config_Option' );
  }


  private static function blockStringsOf( $content ) {
    if ( ! self::isBlockPage( $content ) ) {
      return [];
    }

    $finders = \WPML_Gutenberg_Integration_Factory::createStringsInBlock( new \WPML_Gutenberg_Config_Option() );
    $values  = [];

    self::collectBlockStrings( \WPML_Gutenberg_Integration::parse_blocks( $content ), $finders, $values );

    return $values;
  }


  private static function collectBlockStrings( $blocks, $finders, array &$values ) {
    if ( ! is_array( $blocks ) ) {
      return;
    }

    foreach ( $blocks as $block ) {
      $block = \WPML_Gutenberg_Integration::sanitize_block( $block );

      foreach ( $finders->find( $block ) as $string ) {
        if ( ! isset( $string->value ) || (string) $string->value === '' ) {
          continue;
        }

        $key = isset( $string->id ) ? (string) $string->id : count( $values );

        if ( ! isset( $values[ $key ] ) ) {
          $values[ $key ] = (string) $string->value;
        }
      }

      if ( ! empty( $block->innerBlocks ) ) {
        self::collectBlockStrings( $block->innerBlocks, $finders, $values );
      }
    }
  }


}
