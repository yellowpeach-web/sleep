<?php

namespace WPML\Core\Component\WordsToTranslate\Domain\Post;

class JobPostContentLoader extends PostContentLoader {


  protected function needsNoWords( Post $post, $lang ) {
    return $this->getTranslationQuery()->isAlreadyTranslated( $post, $lang );
  }


}
