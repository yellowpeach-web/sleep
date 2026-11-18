<?php

namespace WPML\Infrastructure\WordPress\Component\Translation\Application\Event\Links;

use WPML\Core\Component\Translation\Application\Event\Links\ItemUpdateListener;
use WPML\Core\Component\Translation\Domain\Links\ContentLinks;
use WPML\Core\Component\Translation\Domain\Links\Item;
use WPML\Core\Component\Translation\Domain\Links\ResolvedLinksCacheInterface;
use WPML\Infrastructure\WordPress\Component\Translation\Domain\Links\Repository;
use WPML\Infrastructure\WordPress\SharedKernel\Post\Domain\PublicationStatusDefinitions;
use WPML\PHP\Exception\InvalidTypeException;
use WPML\WordPress\Term;
use WP_Post;

if ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
  require_once __DIR__ . '/../../../../../../../../../inc/upgrade-autoload-shim.php';
}

class ItemUpdateEventListenerAdapter {

  const LINKS_SIGNATURE_META = '_wpml_links_signature';

  private $itemUpdateListener;

  private $publicationStatusDefinitions;

  private $repository;

  private $resolvedLinksCache;

  private $updatedPosts = [];

  private $updatedTerms = [];


  public function __construct(
    ItemUpdateListener $itemUpdateListener,
    PublicationStatusDefinitions $publicationStatusDefinitions,
    Repository $repository,
    ?ResolvedLinksCacheInterface $resolvedLinksCache = null
  ) {
    $this->itemUpdateListener = $itemUpdateListener;
    $this->publicationStatusDefinitions = $publicationStatusDefinitions;
    $this->repository = $repository;
    $this->resolvedLinksCache = $resolvedLinksCache;
  }


  public function onPostUpdate( WP_Post $postBeforeSave ) {
    $postId = $postBeforeSave->ID;
    if ( array_key_exists( $postId, $this->updatedPosts ) ) {
      return;
    }
    $this->setDefaultsForPost( $postId );
    $this->updatedPosts[ $postId ]['statusBefore'] = $postBeforeSave->post_status;
    $this->updatedPosts[ $postId ]['nameBefore'] = $postBeforeSave->post_name;
    $this->updatedPosts[ $postId ]['linksBefore'] = ContentLinks::signature(
      $postBeforeSave->post_content . $postBeforeSave->post_excerpt
    );
  }


  public function onPostSave( int $postId, WP_Post $wpPost ) {
    $this->setDefaultsForPost( $postId );
    $this->updatedPosts[ $postId ]['post'] = $wpPost;
  }


  private function setDefaultsForPost( int $postId ) {
    if ( array_key_exists( $postId, $this->updatedPosts ) ) {
      return;
    }
    $this->updatedPosts[ $postId ] = [
      'post' => null,
      'statusBefore' => '',
      'nameBefore' => '',
      'linksBefore' => null,
    ];
  }


  public function onTermCreation( int $termId ) {
    if ( array_key_exists( $termId, $this->updatedTerms ) ) {
      return;
    }

    $this->updatedTerms[ $termId ] = '';
  }


  public function beforeTermUpdate( int $termId ) {
    if ( array_key_exists( $termId, $this->updatedTerms ) ) {
      return;
    }

    $term = Term::get( $termId, '', 'ARRAY_A' );
    if ( is_array( $term ) ) {
      $this->updatedTerms[ $termId ] = (string) $term['slug'];
    }
  }


  public function onShutdown() {
    if ( ! $this->updatedPosts && ! $this->updatedTerms ) {
      return;
    }

    if ( $this->resolvedLinksCache ) {
      $this->resolvedLinksCache->clear();
    }

    foreach ( $this->updatedPosts as $post ) {
      try {
        if ( $post['post'] === null ) {
          continue;
        }

        $item = $this->getItemByPost(
          $post['post'],
          $post['statusBefore'],
          $post['nameBefore']
        );

        $linksNow = ContentLinks::signature(
          $post['post']->post_content . $post['post']->post_excerpt
        );
        $unchanged = is_array( $post['linksBefore'] )
          && $post['linksBefore'] === $linksNow
          && get_post_meta( $post['post']->ID, self::LINKS_SIGNATURE_META, true ) === wp_json_encode( $linksNow );

        if ( $unchanged ) {
          $item->markContentLinksUnchanged();
        }

        $this->itemUpdateListener->onItemSave( $item );

        if ( ! $unchanged && $item->isOriginal() && $item->isPublishable() && $item->canLinkToOtherItems() ) {
          update_post_meta( $post['post']->ID, self::LINKS_SIGNATURE_META, wp_slash( wp_json_encode( $linksNow ) ) );
        }
      } catch ( InvalidTypeException $e ) {
        continue;
      }
    }

    foreach ( $this->updatedTerms as $termId => $slugBefore ) {
      try {
        $item = $this->getItemByTerm( $termId, $slugBefore );

        if ( $item === null ) {
          continue;
        }
        $this->itemUpdateListener->onItemSave( $item );
      } catch ( InvalidTypeException $e ) {
        continue;
      }
    }
  }


  private function getItemByPost(
    WP_Post $wpPost,
    string $statusBefore,
    string $nameBefore
  ): Item {
      $item = $this->repository->get( $wpPost->ID, Repository::TYPE_POST );
    if (
        $this->publicationStatusDefinitions->gotPublished(
          $wpPost->post_status,
          $statusBefore
        )
      ) {
      $item->markAsGotPublished();
    }

    if ( $wpPost->post_name !== $nameBefore ) {
      $item->markLinkAsChanged( $nameBefore );
    }

      return $item;
  }


  private function getItemByTerm( int $termId, string $slugBefore ) {
    $term = Term::get( $termId, '', 'ARRAY_A' );

    if ( ! is_array( $term ) ) {
        return null;
    }

    $item = $this->repository->get( $termId, Repository::TYPE_TERM );
    $item->markAsPublished();

    if ( $term['slug'] !== $slugBefore ) {
      $item->markLinkAsChanged( $slugBefore );
    }

    return $item;
  }


}
