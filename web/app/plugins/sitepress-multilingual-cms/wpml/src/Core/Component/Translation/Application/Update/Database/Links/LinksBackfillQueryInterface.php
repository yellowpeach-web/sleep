<?php

namespace WPML\Core\Component\Translation\Application\Update\Database\Links;

interface LinksBackfillQueryInterface {


  public function getOriginalPostIdsWithTranslations( int $afterId, int $limit );


  public function countOriginalPostsWithTranslations( $upToId = null );


  public function getPostTranslationIds( int $originalId );


  public function hasTranslation( int $id, string $type, string $languageCode );


}
