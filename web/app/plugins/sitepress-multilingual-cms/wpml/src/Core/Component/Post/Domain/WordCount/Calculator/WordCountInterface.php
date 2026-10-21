<?php

namespace WPML\Core\Component\Post\Domain\WordCount\Calculator;

interface WordCountInterface {


  public function words( string $content ): int;


}
