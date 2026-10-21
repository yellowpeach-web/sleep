<?php
namespace WPML\Infrastructure\WordPress\Component\WordsToTranslate\Domain\Post;

use WPML\Core\Component\WordsToTranslate\Domain\Post\Post;
use WPML\Core\Component\WordsToTranslate\Domain\Post\Query\PostQueryInterface;
use WPML\PHP\Exception\InvalidItemIdException;

class PostBeforeSetupQuery implements PostQueryInterface {

  private $posts = [];


  public function getById( $id ) {
    if ( isset( $this->posts[ $id ] ) ) {
      return $this->posts[ $id ];
    }

    global $wpdb;

    $sql = $wpdb->prepare(
      "
      SELECT
        p.ID,
        p.post_type,
        p.post_date,
        p.post_modified
      FROM {$wpdb->prefix}posts AS p
      WHERE p.ID = %d
      AND p.post_status = 'publish'
    ",
      $id
    );

    $result = $wpdb->get_row( $sql, ARRAY_A );

    if ( ! $result ) {
      throw new InvalidItemIdException( sprintf( 'Post with ID %d not found.', $id ) );
    }

    $post = new Post(
      $result['ID'],
      $result['post_type'],
      '',
      strtotime( $result['post_modified'] ) ?: 0
    );
    $post->setDateModified(
      substr( $result['post_date'], 0, 10 )
    );

    $this->posts[ $post->getId() ] = $post;
    return $post;
  }




}
