<?php

namespace WPML\Legacy\Component\Item\Application\Query;

use WPML\Core\SharedKernel\Component\Item\Application\Query\ConfigExcludedPostTypesQueryInterface;

class ConfigExcludedPostTypesQuery implements ConfigExcludedPostTypesQueryInterface {

  private $memo = null;

  public function get(): array {
    if ( null !== $this->memo ) {
      return $this->memo;
    }

    $this->memo = [];

    if (
      ! class_exists( 'WPML_Config' )
      || ! class_exists( 'WPML_XML_Config_Read_File' )
      || ! class_exists( 'WPML_Config_Display_As_Translated' )
      || ! defined( 'WPML_CONTENT_TYPE_TRANSLATE' )
    ) {
      return $this->memo;
    }

    $excluded = [];

    foreach ( $this->configFiles() as $file ) {
      foreach ( $this->declarations( $file ) as $declaration ) {
        $name = $this->declaredName( $declaration );
        $mode = $this->declaredMode( $declaration );

        if ( '' !== $name && (int) WPML_CONTENT_TYPE_TRANSLATE !== $mode ) {
          $excluded[] = $name;
        }
      }
    }

    $this->memo = array_values( array_unique( $excluded ) );

    return $this->memo;
  }

  private function declaredName( $declaration ): string {
    if ( ! is_array( $declaration ) || ! isset( $declaration['value'] ) ) {
      return '';
    }

    $value = $declaration['value'];

    return is_string( $value ) ? trim( $value ) : '';
  }


  private function declaredMode( $declaration ): int {
    if ( ! is_array( $declaration ) || ! isset( $declaration['attr'] ) ) {
      return (int) WPML_CONTENT_TYPE_TRANSLATE;
    }

    $attr = $declaration['attr'];

    if ( ! is_array( $attr ) || ! isset( $attr['translate'] ) ) {
      return (int) WPML_CONTENT_TYPE_TRANSLATE;
    }

    $translate = $attr['translate'];

    return is_scalar( $translate ) ? (int) $translate : (int) WPML_CONTENT_TYPE_TRANSLATE;
  }


  private function configFiles(): array {
    $files = array_merge(
      \WPML_Config::load_plugins_wpml_config(),
      \WPML_Config::load_theme_wpml_config()
    );

    return array_values( array_unique( array_filter( $files, 'is_string' ) ) );
  }

  private function declarations( string $file ): array {
    $config = ( new \WPML_XML_Config_Read_File(
      $file,
      new \WPML_XML_Config_Validate(),
      new \WPML_XML2Array()
    ) )->get();

    $declarations = $this->customTypeEntries( $config );

    if ( ! $declarations ) {
      return [];
    }

    if ( ! is_numeric( key( $declarations ) ) ) {
      $declarations = [ $declarations ];
    }

    $folded = \WPML_Config_Display_As_Translated::merge_to_translate_mode(
      [ 'wpml-config' => [ 'custom-types' => [ 'custom-type' => $declarations ] ] ]
    );

    $foldedEntries = $this->customTypeEntries( $folded );

    return array_values( $foldedEntries ?: $declarations );
  }


  private function customTypeEntries( $config ): array {
    if ( ! is_array( $config ) || ! isset( $config['wpml-config'] ) ) {
      return [];
    }

    $root = $config['wpml-config'];

    if ( ! is_array( $root ) || ! isset( $root['custom-types'] ) ) {
      return [];
    }

    $customTypes = $root['custom-types'];

    if ( ! is_array( $customTypes ) || ! isset( $customTypes['custom-type'] ) ) {
      return [];
    }

    $entries = $customTypes['custom-type'];

    return is_array( $entries ) ? $entries : [];
  }
}
