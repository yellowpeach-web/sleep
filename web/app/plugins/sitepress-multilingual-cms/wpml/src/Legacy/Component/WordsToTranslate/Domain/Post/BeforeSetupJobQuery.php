<?php

namespace WPML\Legacy\Component\WordsToTranslate\Domain\Post;

use WPML\Core\Component\WordsToTranslate\Domain\Post\Post;
use WPML\Core\Component\WordsToTranslate\Domain\Post\Query\JobQueryInterface;

class BeforeSetupJobQuery implements JobQueryInterface {

  private $jobQuery;


  public function __construct( JobQueryInterface $jobQuery ) {
    $this->jobQuery = $jobQuery;
  }


  public function getContentToTranslateForLang( Post $post, string $lang ) {
    $this->registerContributors();

    return $this->jobQuery->getContentToTranslateForLang( $post, $lang );
  }


  public function getTerms( Post $post ) {
    $this->registerContributors();

    return $this->jobQuery->getTerms( $post );
  }


  public function useThisContentForItem( $idItem, $content ) {
    $this->jobQuery->useThisContentForItem( $idItem, $content );
  }


  protected function registerContributors() {
    BeforeSetupPackageContributors::register();
  }


}
