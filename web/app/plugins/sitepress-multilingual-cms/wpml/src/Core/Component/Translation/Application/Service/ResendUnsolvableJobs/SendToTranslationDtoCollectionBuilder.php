<?php

namespace WPML\Core\Component\Translation\Application\Service\ResendUnsolvableJobs;

use WPML\Core\Component\Translation\Application\Service\Dto\SendToTranslationDto;
use WPML\Core\Component\Translation\Application\Service\ResendUnsolvableJobs\DtoCollectionBuilder\BatchStringIdExtractor;
use WPML\Core\Component\Translation\Application\Service\ResendUnsolvableJobs\DtoCollectionBuilder\SendToTranslationDtoBuilder;
use WPML\Core\Component\Translation\Application\Service\ResendUnsolvableJobs\DtoCollectionBuilder\TranslationGrouper;
use WPML\Core\Component\Translation\Domain\Translation;

class SendToTranslationDtoCollectionBuilder {

  private $batchStringIdExtractor;

  private $translationGrouper;

  private $dtoBuilder;


  public function __construct(
    BatchStringIdExtractor $batchStringIdExtractor,
    TranslationGrouper $translationGrouper,
    SendToTranslationDtoBuilder $dtoBuilder
  ) {
    $this->batchStringIdExtractor = $batchStringIdExtractor;
    $this->translationGrouper     = $translationGrouper;
    $this->dtoBuilder             = $dtoBuilder;
  }


  public function buildGroups( string $batchName, array $translations ): array {
    $batchIdToStringIdsMap = $this->batchStringIdExtractor->extract( $translations );

    $groupedBySourceLanguage = $this->translationGrouper->groupBySourceLanguage( $translations );

    $groups = [];
    foreach ( $groupedBySourceLanguage as $sourceLanguage => $translationsGroup ) {
      $groupedByTargetLanguages = $this->translationGrouper->groupByTargetLanguages( $translationsGroup );

      foreach ( $groupedByTargetLanguages as $groupKey => $translationsSubgroup ) {
        $key            = count( $groupedByTargetLanguages ) > 1 ? $sourceLanguage . ':' . $groupKey : $sourceLanguage;
        $groups[ $key ] = [
          'dto'    => $this->dtoBuilder->build(
            $batchName,
            $sourceLanguage,
            $translationsSubgroup,
            $batchIdToStringIdsMap
          ),
          'jobIds' => $this->jobIdsOf( $translationsSubgroup ),
        ];
      }
    }

    return $groups;
  }


  private function jobIdsOf( array $translations ): array {
    $jobIds = [];
    foreach ( $translations as $translation ) {
      $job = $translation->getJob();
      if ( $job ) {
        $jobIds[ $job->getId() ] = $job->getId();
      }
    }

    return array_values( $jobIds );
  }


}
