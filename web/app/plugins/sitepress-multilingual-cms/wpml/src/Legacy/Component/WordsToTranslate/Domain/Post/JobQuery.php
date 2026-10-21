<?php

namespace WPML\Legacy\Component\WordsToTranslate\Domain\Post;

use WPML\Core\Component\WordsToTranslate\Domain\Post\FieldKey;
use WPML\Core\Component\WordsToTranslate\Domain\Post\JobDto;
use WPML\Core\Component\WordsToTranslate\Domain\Post\Post;
use WPML\Core\Component\WordsToTranslate\Domain\Post\Query\JobQueryInterface;
use WPML\Core\Component\WordsToTranslate\Domain\Post\Term\Term;
use WPML\Core\Component\WordsToTranslate\Domain\Post\Term\TermContent;
use WPML\FP\Type;
use WPML\Legacy\Component\WordsToTranslate\Domain\JobPackageTrait;

class JobQuery implements JobQueryInterface {
  use JobPackageTrait;

  private $jobPackages = [];


  public function getContentToTranslateForLang( Post $post, string $lang ) {
    $jobPackage = $this->getJobPackage( $post, $lang );
    $translatableFields = $this->getTranslatableFields( $jobPackage );

    $fieldContents = $this->collectFieldContentsToTranslate( $translatableFields );

    return new JobDto(
      $this->flattenFieldContents( $fieldContents ),
      array_keys( $translatableFields ),
      $fieldContents
    );
  }


  private function collectFieldContentsToTranslate( $translatableFields ) {
    $fieldContents = [];

    foreach ( $translatableFields as $type => $data ) {
      $isTerm = $this->getTermIdAndType( $type );
      if ( $isTerm ) {
        continue;
      }

      if ( Type::isJson( $data ) ) {
        continue;
      }

      $fieldKey = FieldKey::groupKey( $type );

      $fieldContents[ $fieldKey ] = isset( $fieldContents[ $fieldKey ] )
        ? $this->appendContent( $fieldContents[ $fieldKey ], $data )
        : $data;
    }

    return $fieldContents;
  }


  private function flattenFieldContents( $fieldContents ) {
    $content = '';

    foreach ( $fieldContents as $data ) {
      $content = $this->appendContent( $content, $data );
    }

    return $content;
  }


  private function appendContent( string $content, string $data ): string {
    return $content ? $content . ' ' . $data : $data;
  }


  public function getTerms( Post $post ) {
    $jobPackage = $this->getJobPackage( $post );
    $translatableFields = $this->getTranslatableFields( $jobPackage );
    $terms = [];

    foreach ( $translatableFields as $type => $data ) {
      $termIdAndType = $this->getTermIdAndType( $type );
      if ( ! $termIdAndType ) {
        continue;
      }

      $termId = $termIdAndType[0];
      $termType = $termIdAndType[1];

      $termContent = new TermContent( $termId, $termType, $post->getSourceLang() );
      $termContent->setContent( $data );

      $term = isset( $terms[ $termId ] )
        ? $terms[ $termId ]
        : new Term( $termId );

      $term->addContent( $termContent );

      if ( ! isset( $terms[ $termId ] ) ) {
        $terms[ $termId ] = $term;
      }
    }

    return $terms;
  }


  private function getTermIdAndType( $type ) {
    if ( strpos( $type, 't_' ) === 0 ) {
      return [(int) substr( $type, 2 ), 'name'];
    } elseif ( strpos( $type, 'tdesc_' ) === 0 ) {
      return [(int) substr( $type, 6 ), 'description'];
    } elseif ( strpos( $type, 'tfield-' ) === 0 && preg_match( '/tfield-(.*?)-(\d{1,})($|\_)/', $type, $matches ) ) {
      return [(int) $matches[2], $matches[1]];
    }

    return null;
  }


  private function getJobPackage( Post $post, $lang = null ) {
    $package = isset( $this->jobPackages[ $post->getId() ] )
      ? $this->jobPackages[ $post->getId() ]
      : false;

    if ( ! $package ) {
      $this->wpmlElementTranslationPackage()
          ->do_action_before_creating_translation_package(
            \get_post( $post->getId() )
          );

      $package = $this->wpmlElementTranslationPackage()
          ->create_translation_package( $post->getId(), true ) ?: false;

      $package = $this->filterCustomFields( $package, $post );

      $this->jobPackages[ $post->getId() ] = $package;
    }

    $package = $lang
      ? $this->wpmlElementTranslationPackage()
        ->filter_translation_package_for_lang(
          $package,
          \get_post( $post->getId() ),
          $lang
        )
      : $package;

    return $package ?: [];
  }


  public function useThisContentForItem( $idItem, $content ) {
    $data = [ 'contents' => [] ];

    foreach ( $content as $part ) {
      $data['contents'][ $part->getType() ] = [
        'translate' => 1,
        'data'   => $part->getContent(),
        'format' => $part->getFormat(),
      ];
    }

    $this->jobPackages[ $idItem ] = $data;
  }


  private function filterCustomFields( $package, Post $post ) {
    $package['contents'] = isset( $package['contents'] ) && is_array( $package['contents'] )
      ? $package['contents']
      : [];

    $customFields = [];

    foreach ( array_keys( $package['contents'] ) as $type ) {
      if ( strpos( $type, 'field-' ) !== 0 ) {
        continue;
      }

      if ( preg_match( '/^field-(.*?)-\d+/', $type, $match ) ) {
          $customFields[] = $match[1];
      }
    }

    if ( $customFields ) {
      $allowedCustomFields = apply_filters(
        'wpml_words_count_custom_fields_to_count',
        array_unique( $customFields ),
        $post->getId()
      );

      $package['contents'] = array_filter(
        $package['contents'],
        function ( $type ) use ( $allowedCustomFields ) {
          if ( strpos( $type, 'field-' ) !== 0 ) {
            return true;
          }

          foreach ( $allowedCustomFields as $allowedCustomField ) {
            if ( strpos( $type, 'field-' . $allowedCustomField ) === 0 ) {
              return true;
            }
          }

          return false;
        },
        ARRAY_FILTER_USE_KEY
      );
    }

    return $package;
  }


}
