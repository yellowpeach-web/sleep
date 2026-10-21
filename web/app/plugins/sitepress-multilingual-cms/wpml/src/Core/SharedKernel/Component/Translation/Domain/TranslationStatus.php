<?php

namespace WPML\Core\SharedKernel\Component\Translation\Domain;

class TranslationStatus {

  const NOT_TRANSLATED = 0;
  const WAITING_FOR_TRANSLATOR = 1;
  const IN_PROGRESS = 2;
  const NEEDS_UPDATE = 3;
  const READY_TO_DOWNLOAD = 4;
  const DUPLICATE = 9;
  const COMPLETE = 10;
  const NEEDS_REVIEW = 30;
  const ATE_NEEDS_RETRY = 40;
  const ATE_UNSOLVABLE = 41;
  const ATE_CANCELED = 42;

  const STATUSES_RESOLVED_BY_THE_TRANSLATION = [ self::NOT_TRANSLATED, self::COMPLETE, self::ATE_CANCELED ];

  private $value;


  public function __construct( int $value ) {
    if ( in_array( $value, self::getAll(), true ) ) {
      $this->value = $value;
    } else {
      $this->value = self::NOT_TRANSLATED;
    }
  }


  public function getPublic() {
    return [
      self::NOT_TRANSLATED,
      self::NEEDS_UPDATE,
      self::IN_PROGRESS,
      self::COMPLETE,
    ];
  }


  public static function getAll() {
    return [
      self::NOT_TRANSLATED,
      self::WAITING_FOR_TRANSLATOR,
      self::IN_PROGRESS,
      self::NEEDS_UPDATE,
      self::READY_TO_DOWNLOAD,
      self::DUPLICATE,
      self::COMPLETE,
      self::NEEDS_REVIEW,
      self::ATE_NEEDS_RETRY,
      self::ATE_UNSOLVABLE,
      self::ATE_CANCELED
    ];
  }


  public function get(): int {
    return $this->value;
  }


  public static function getPostDisplayStatus( array $data ): self {
    $statusValue = (int) $data['status'];

    if ( in_array( $statusValue, self::STATUSES_RESOLVED_BY_THE_TRANSLATION, true ) ) {
      $statusValue = $data['element_id'] ? self::COMPLETE : self::NOT_TRANSLATED;
    }

    if ( $data['needs_update'] ) {
      $statusValue = TranslationStatus::NEEDS_UPDATE;
    }

    if ( $data['review_status'] === 'NEEDS_REVIEW' ) {
      $statusValue = TranslationStatus::NEEDS_REVIEW;
    }

    return new self( $statusValue );
  }


}
