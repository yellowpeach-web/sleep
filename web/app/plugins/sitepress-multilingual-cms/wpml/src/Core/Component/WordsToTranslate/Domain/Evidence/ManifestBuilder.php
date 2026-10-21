<?php

namespace WPML\Core\Component\WordsToTranslate\Domain\Evidence;

use WPML\Core\Component\WordsToTranslate\Domain\Config;
use WPML\Core\Component\WordsToTranslate\Domain\Item;
use WPML\Core\Component\WordsToTranslate\Domain\Job\Job;
use WPML\Core\Component\WordsToTranslate\Domain\LastTranslation;
use WPML\Core\Component\WordsToTranslate\Domain\Post\FieldKey;
use WPML\Core\Component\WordsToTranslate\Domain\Post\Post;

class ManifestBuilder {

  const BODY_FIELD_KEY = 'body';

  const CUSTOM_FIELD_PREFIX = FieldKey::CUSTOM_FIELD_PREFIX;

  const TERMS_FIELD_KEY = 'terms';

  const DEGRADATION_PRICING_GATE = 'pricing-gate';

  private $hunkBuilder;

  private $tierSelector;

  private $budgetEnforcer;

  private $fingerprint;

  private $proofSize;

  private $displayText;


  public function __construct(
    HunkBuilder $hunkBuilder,
    TierSelector $tierSelector,
    BudgetEnforcer $budgetEnforcer,
    Fingerprint $fingerprint,
    ProofSize $proofSize,
    DisplayText $displayText
  ) {
    $this->hunkBuilder = $hunkBuilder;
    $this->tierSelector = $tierSelector;
    $this->budgetEnforcer = $budgetEnforcer;
    $this->fingerprint = $fingerprint;
    $this->proofSize = $proofSize;
    $this->displayText = $displayText;
  }


  public function buildForJob( Job $job ) {
    return $this->buildForItemLang( $job->getItem(), $job->getTargetLang() );
  }


  public function buildForItemLang( Item $item, string $targetLang ) {
    $lastTranslations = $item->getLastTranslations();
    if ( ! isset( $lastTranslations[ $targetLang ] ) ) {
      return null;
    }

    $lastTranslation = $lastTranslations[ $targetLang ];
    $fieldCounts = $lastTranslation->getFieldCounts();
    if ( $fieldCounts === null ) {
      return null;
    }

    $fieldContents = $item->getFieldContents() ?? [];
    $sourceLang = strtolower( $item->getSourceLang() );
    $ideogramFactor = Config::LANGS[ $sourceLang ][ Config::KEY_WORDS_PER_IDEOGRAM ] ?? null;
    $isFresh = ( $lastTranslation->getOriginalContent() ?? '' ) === '';

    $terms = $this->collectTerms( $item, $targetLang );
    $fields = $this->collectFieldRows( $fieldCounts );

    $evidenceCharged = $fields['charged'] + $terms['charged'];
    $totalWords = $fields['total'] + $terms['total'];
    $billed = $item->getWordsToTranslate( $targetLang );

    $tier = $this->tierSelector->select(
      $evidenceCharged,
      $isFresh,
      $fields['chargedCustomFields'],
      $fields['charged'] === 0 && $terms['charged'] > 0
    );

    $proof = $this->assembleProof(
      $fields,
      $terms,
      $lastTranslation,
      $fieldContents,
      $ideogramFactor,
      $sourceLang,
      $tier
    );

    $enforced = $this->budgetEnforcer->enforce( $proof, $tier );
    $proof = $enforced['proof'];
    $degradations = $enforced['degradations'];

    $words = [
      'charged' => $billed,
      'total'   => $totalWords,
      'free'    => max( 0, $totalWords - $evidenceCharged ),
    ];

    if ( $billed !== $evidenceCharged ) {
      $words['gated_from'] = $evidenceCharged;
      $degradations[] = self::DEGRADATION_PRICING_GATE;
    }

    return new Manifest(
      $isFresh ? Manifest::KIND_NEW : Manifest::KIND_UPDATE,
      $words,
      $tier,
      $degradations,
      $this->fingerprint->forContents( $fieldContents, $terms['texts'] ),
      $proof,
      [
        'raw'        => $this->proofSize->rawBytes( $proof ),
        'compressed' => $this->proofSize->compressedBytes( $proof ),
        'cap'        => BudgetEnforcer::PROOF_CAP_BYTES,
      ]
    );
  }


  private function collectFieldRows( $fieldCounts ) {
    $fields = [
      'rows'                => [],
      'charged'             => 0,
      'total'               => 0,
      'chargedCustomFields' => 0,
      'body'                => null,
      'counts'              => [],
    ];

    foreach ( $fieldCounts as $fieldCount ) {
      $fields['charged'] += $fieldCount->getCharged();
      $fields['total'] += $fieldCount->getTotal();
      $fields['counts'][ $fieldCount->getKey() ] = $fieldCount;

      if ( $fieldCount->getKey() === self::BODY_FIELD_KEY ) {
        $fields['body'] = $fieldCount;
      }

      if ( $fieldCount->getCharged() === 0 ) {
        continue;
      }

      $fields['rows'][] = [
        'key'     => $fieldCount->getKey(),
        'label'   => $this->fieldLabel( $fieldCount->getKey() ),
        'total'   => $fieldCount->getTotal(),
        'charged' => $fieldCount->getCharged(),
      ];

      if ( strpos( $fieldCount->getKey(), self::CUSTOM_FIELD_PREFIX ) === 0 ) {
        $fields['chargedCustomFields']++;
      }
    }

    return $fields;
  }


  private function assembleProof( $fields, $terms, $lastTranslation, $fieldContents, $ideogramFactor, $sourceLang, $tier ) {
    $proof = [
      'fields' => $this->fieldRowsWithTerms( $fields['rows'], $terms ),
    ];

    if ( $terms['rows'] && $terms['charged'] > 0 ) {
      $proof['terms'] = $terms['rows'];
    }
    if ( $terms['skipped'] > 0 ) {
      $proof['terms_skipped'] = $terms['skipped'];
    }

    $hunks = $this->buildHunks( $fields['rows'], $fields['counts'], $lastTranslation, $fieldContents, $ideogramFactor );
    if ( $hunks ) {
      $proof['hunks'] = $hunks;
    }

    $proof = $this->addBodyReceipt( $proof, $fields['body'], $fieldContents, $ideogramFactor );

    if ( $tier === Tier::RECEIPT && ! isset( $proof['receipt'] ) ) {
      $proof = $this->addLargestFieldReceipt( $proof, $fields['counts'], $fieldContents, $ideogramFactor );
    }

    if ( $ideogramFactor !== null ) {
      $proof['ideogram'] = [
        'factor'   => $ideogramFactor,
        'language' => $sourceLang,
      ];
    }

    return $proof;
  }


  private function addBodyReceipt( $proof, $bodyFieldCount, $fieldContents, $ideogramFactor ) {
    if ( $bodyFieldCount === null || $bodyFieldCount->getCharged() === 0 ) {
      return $proof;
    }

    $bodyContent = $fieldContents[ self::BODY_FIELD_KEY ] ?? '';
    $proof['receipt'] = $this->buildReceipt( $bodyFieldCount, $bodyContent, $ideogramFactor );

    if ( $ideogramFactor === null ) {
      $boundaries = $this->displayText->boundaries( $bodyContent );
      if ( $boundaries !== null ) {
        $proof['boundaries'] = $boundaries;
      }
    }

    return $proof;
  }


  private function addLargestFieldReceipt( $proof, $fieldCounts, $fieldContents, $ideogramFactor ) {
    $largestKey = null;
    $largest = null;
    foreach ( $fieldCounts as $fieldKey => $fieldCount ) {
      if ( $fieldCount->getCharged() === 0 || ! isset( $fieldContents[ $fieldKey ] ) ) {
        continue;
      }
      if ( $largest === null || $fieldCount->getCharged() > $largest->getCharged() ) {
        $largestKey = $fieldKey;
        $largest = $fieldCount;
      }
    }

    if ( $largest === null || $largestKey === null ) {
      return $proof;
    }

    $content = $fieldContents[ $largestKey ];
    $proof['receipt'] = $this->buildReceipt( $largest, $content, $ideogramFactor );
    $proof['receipt']['field'] = $largestKey;

    if ( $ideogramFactor === null ) {
      $boundaries = $this->displayText->boundaries( $content );
      if ( $boundaries !== null ) {
        $proof['boundaries'] = $boundaries;
      }
    }

    return $proof;
  }


  private function fieldRowsWithTerms( $chargedFieldRows, $terms ) {
    if ( $terms['charged'] <= 0 ) {
      return $chargedFieldRows;
    }

    $termsRow = [
      'key'     => self::TERMS_FIELD_KEY,
      'label'   => sprintf( 'Taxonomy terms (%d)', count( $terms['rows'] ) ),
      'total'   => $terms['total'],
      'charged' => $terms['charged'],
    ];

    if ( $terms['skipped'] > 0 ) {
      $termsRow['skipped'] = $terms['skipped'];
    }

    $chargedFieldRows[] = $termsRow;

    return $chargedFieldRows;
  }


  private function buildHunks(
    $chargedFieldRows,
    $fieldCountsByKey,
    $lastTranslation,
    $fieldContents,
    $ideogramFactor
  ) {
    $fieldDiffs = $lastTranslation->getFieldDiffs() ?? [];
    $originalFieldContents = $lastTranslation->getOriginalFieldContents() ?? [];

    $hunks = [];

    foreach ( $chargedFieldRows as $row ) {
      $fieldKey = (string) $row['key'];
      $isFreshField = ( $originalFieldContents[ $fieldKey ] ?? '' ) === '';

      $fieldHunks = $this->hunksForField(
        $isFreshField ? ( $fieldContents[ $fieldKey ] ?? '' ) : null,
        (int) $row['charged'],
        $fieldDiffs[ $fieldKey ] ?? null,
        $ideogramFactor,
        $this->chargedTokensBefore( $fieldCountsByKey, $fieldKey )
      );

      if ( $fieldHunks ) {
        $hunks[ $fieldKey ] = $fieldHunks;
      }
    }

    return $hunks;
  }


  private function chargedTokensBefore( $fieldCountsByKey, $fieldKey ) {
    if ( ! isset( $fieldCountsByKey[ $fieldKey ] ) ) {
      return 0;
    }

    return $fieldCountsByKey[ $fieldKey ]->getChargedTokensBefore();
  }


  private function hunksForField( $freshContent, $charged, $fieldDiff, $ideogramFactor, $chargedTokensBefore ) {
    if ( $freshContent !== null ) {
      $verbatimHunk = $this->verbatimHunk( $freshContent, $charged );

      return $verbatimHunk !== null ? [ $verbatimHunk ] : null;
    }

    if ( $fieldDiff === null ) {
      return null;
    }

    $builtHunks = $this->hunkBuilder->build(
      $fieldDiff,
      $ideogramFactor ?? 1,
      $ideogramFactor === null ? ' ' : '',
      $chargedTokensBefore
    );

    if ( ! $builtHunks ) {
      return null;
    }

    return array_map(
      function ( Hunk $hunk ) {
        return $hunk->toArray();
      },
      $builtHunks
    );
  }


  private function verbatimHunk( $content, $charged ) {
    $displayTokens = $this->displayText->tokens( $content );

    if ( ! $displayTokens || count( $displayTokens ) > TierSelector::VERBATIM_FIELD_MAX_WORDS ) {
      return null;
    }

    return [
      'before'      => '',
      'removed'     => '',
      'added'       => implode( ' ', $displayTokens ),
      'after'       => '',
      'added_words' => $charged,
    ];
  }


  private function buildReceipt( $bodyFieldCount, $bodyContent, $ideogramFactor ) {
    if ( $ideogramFactor !== null ) {
      return [
        'characters' => $bodyFieldCount->getRawTokenCount(),
        'factor'     => $ideogramFactor,
      ];
    }

    $counted = $bodyFieldCount->getTotal();
    $shortcodeTags = $this->displayText->countShortcodeTokens( $bodyContent );
    $numbers = $this->displayText->countStandaloneNumbers( $bodyContent );

    return [
      'raw_tokens'     => $counted + $shortcodeTags + $numbers,
      'shortcode_tags' => $shortcodeTags,
      'numbers'        => $numbers,
      'counted'        => $counted,
    ];
  }


  private function collectTerms( $item, $targetLang ) {
    $terms = [
      'rows'    => [],
      'charged' => 0,
      'total'   => 0,
      'skipped' => 0,
      'texts'   => [],
    ];

    if ( ! $item instanceof Post ) {
      return $terms;
    }

    $postTerms = $item->getTerms();
    if ( ! $postTerms ) {
      return $terms;
    }

    foreach ( $postTerms as $term ) {
      $name = '';
      $description = '';
      $words = 0;
      $fullWords = 0;

      foreach ( $term->getContents() as $termContent ) {
        if ( $termContent->getType() === 'name' ) {
          $name = $termContent->getContent() ?? '';
        } elseif ( $termContent->getType() === 'description' ) {
          $description = $termContent->getContent() ?? '';
        }

        $charged = $termContent->getWordsToTranslate( $targetLang );
        $words += $charged;
        $fullWords += $termContent->getContentCount() ?? $charged;
      }

      $terms['total'] += $fullWords;

      if ( $words === 0 ) {
        $terms['skipped']++;
        continue;
      }

      $terms['rows'][] = [
        'name'        => $name,
        'description' => $description,
        'words'       => $words,
      ];
      $terms['charged'] += $words;
      $terms['texts'][] = $name . "\n" . $description;
    }

    return $terms;
  }


  private function fieldLabel( string $fieldKey ) {
    if ( $fieldKey === 'title' ) {
      return 'Title';
    }
    if ( $fieldKey === self::BODY_FIELD_KEY ) {
      return 'Body';
    }
    if ( $fieldKey === 'excerpt' ) {
      return 'Excerpt';
    }
    if ( $fieldKey === FieldKey::PACKAGE_STRINGS_GROUP_KEY ) {
      return 'Page builder content';
    }
    if ( strpos( $fieldKey, self::CUSTOM_FIELD_PREFIX ) === 0 ) {
      return substr( $fieldKey, strlen( self::CUSTOM_FIELD_PREFIX ) );
    }

    return $fieldKey;
  }


}
