<?php

namespace WPML\Core\Component\WordsToTranslate\Domain\Evidence;

class Hunk {

  private $before;

  private $removed;

  private $added;

  private $after;

  private $addedWords;


  public function __construct(
    string $before,
    string $removed,
    string $added,
    string $after,
    int $addedWords
  ) {
    $this->before = $before;
    $this->removed = $removed;
    $this->added = $added;
    $this->after = $after;
    $this->addedWords = $addedWords;
  }


  public function getAddedWords(): int {
    return $this->addedWords;
  }


  public function toArray(): array {
    return [
      'before'      => $this->before,
      'removed'     => $this->removed,
      'added'       => $this->added,
      'after'       => $this->after,
      'added_words' => $this->addedWords,
    ];
  }


}
