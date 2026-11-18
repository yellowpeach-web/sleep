<?php

namespace WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\GetPosts;

use WPML\Core\Component\Post\Application\Query\Criteria\NoTargetLanguagesException;
use WPML\Core\Component\Post\Application\Query\Criteria\SearchCriteriaBuilder;
use WPML\Core\Component\Post\Application\Query\Dto\PostWithTranslationStatusDto;
use WPML\Core\Component\Post\Application\Query\Dto\TranslationStatusDto;
use WPML\Core\Component\Post\Application\Query\PermalinkQueryInterface;
use WPML\Core\Component\Post\Application\Query\SearchQueryInterface;
use WPML\Core\Port\Appearance\AppearanceReaderInterface;
use WPML\PHP\Exception\Exception;
use WPML\PHP\Exception\InvalidArgumentException;

class GetPostsController implements GetPostControllerInterface {

  private $findBySearchCriteriaQuery;

  private $permalinkQuery;

  private $filter;

  private $criteriaBuilder;

  private $appearanceReader;


  public function __construct(
    PermalinkQueryInterface $permalinkQuery,
    SearchQueryInterface $findBySearchCriteriaQueryInterface,
    PostsFilterInterface $filter,
    SearchCriteriaBuilder $criteriaBuilder,
    AppearanceReaderInterface $appearanceReader
  ) {
    $this->permalinkQuery            = $permalinkQuery;
    $this->findBySearchCriteriaQuery = $findBySearchCriteriaQueryInterface;
    $this->filter                    = $filter;
    $this->criteriaBuilder           = $criteriaBuilder;
    $this->appearanceReader          = $appearanceReader;
  }


  public function handle( $requestData = null ): array {
    $requestData  = $requestData ?: [];
    $languageCode = $requestData['sourceLanguageCode'] ?? '';

    try {
      $criteria = $this->criteriaBuilder->build( $requestData );
      $items    = $this->findBySearchCriteriaQuery->get( $criteria );
    } catch ( NoTargetLanguagesException $e ) {
      return $this->filter->filter( [], $requestData );
    } catch ( InvalidArgumentException $e ) {
      throw new InvalidArgumentException(
        'The request data for GetPosts is not valid.' . $e->getMessage()
      );
    }

    $linkedPostIds = [];
    foreach ( $items->getResults() as $post ) {
      if ( ! $this->hasRestrictedStatus( $post ) ) {
        $linkedPostIds[] = $post->getId();
      }
    }
    $this->permalinkQuery->prefetch( $linkedPostIds );

    $appeared = $this->appearedIds( $criteria->getType(), $items->getResults() );

    $result = array_map(
      function ( PostWithTranslationStatusDto $post ) use ( $languageCode, $appeared ) {
        if ( $this->hasRestrictedStatus( $post ) ) {
          $viewLink = '';
        } else {
          $viewLink = (string) $this->permalinkQuery->getPermalink( $post->getId() );
        }

        $translations = array_map(
          function ( TranslationStatusDto $translation ) {
            return $translation->toArray();
          },
          $post->getTranslationStatuses()
        );

        return [
          'id'                => $post->getId(),
          'title'             => $post->getTitle(),
          'status'            => $post->getStatus(),
          'createdAt'         => $post->getCreatedAt(),
          'translations'      => $translations,
          'translatorNote'    => $post->getTranslatorNote(),
          'viewLink'          => $this->filter->filterViewLink(
            $viewLink,
            $post->getId(),
            $post->getPostType(),
            $languageCode
          ),
          'editLink'          => $this->filter->filterEditLink(
            '',
            $post->getId(),
            $post->getPostType(),
            $languageCode
          ),
          'isBlocked'         => false,
          'blockedReason'     => null,
          'image'             => null,
          'usingNativeEditor' => $post->getUsingNativeEditor(),
          'appearance'        => $appeared === null
            ? null
            : ( isset( $appeared[ $post->getId() ] ) ? 'appears' : 'notShown' ),
        ];
      },
      $items->getResults()
    );

    return $this->filter->filter( $result, $requestData );
  }


  private function appearedIds( string $postType, array $posts ) {
    $type = 'post_' . $postType;
    if ( ! $posts || ! in_array( $type, $this->appearanceReader->heldTypes(), true ) ) {
      return null;
    }

    $ids = array_map(
      function ( PostWithTranslationStatusDto $post ) {
        return $post->getId();
      },
      $posts
    );

    $appeared = $this->appearanceReader->filterIds( $type, $ids );

    return $appeared === null ? null : array_fill_keys( $appeared, true );
  }


  private function hasRestrictedStatus( PostWithTranslationStatusDto $post ): bool {
    return in_array(
      $post->getStatus(),
      array( 'draft', 'private', 'trash' ),
      true
    );
  }


}
