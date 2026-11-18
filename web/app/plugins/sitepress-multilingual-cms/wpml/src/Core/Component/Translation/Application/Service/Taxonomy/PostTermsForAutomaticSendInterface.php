<?php

namespace WPML\Core\Component\Translation\Application\Service\Taxonomy;

interface PostTermsForAutomaticSendInterface {

  public function uncoveredTermsOfPosts( array $postIds, array $targetLanguages ): array;


}
