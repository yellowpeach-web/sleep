<?php

namespace WPML\Core\Component\Translation\Domain\Priority\Classifier;

use WPML\Core\Component\Translation\Domain\Priority\JobPriority;
use WPML\Core\Component\Translation\Domain\Priority\PrioritizableItem;
use WPML\Core\Component\Translation\Domain\Priority\Rank;
use WPML\Core\Component\Translation\Domain\Priority\Tier;

class TermClassifier implements TierClassifierInterface {


  public function canClassify( PrioritizableItem $item, ClassificationContext $context ): bool {
      return $item->getType()->isTerm();
  }


  public function classify( PrioritizableItem $item, ClassificationContext $context ): JobPriority {
      return new JobPriority(
        $item->getId(),
        Tier::taxonomyTerms(),
        new Rank( [ $item->getTermDepth(), $item->getId() ] )
      );
  }


}
