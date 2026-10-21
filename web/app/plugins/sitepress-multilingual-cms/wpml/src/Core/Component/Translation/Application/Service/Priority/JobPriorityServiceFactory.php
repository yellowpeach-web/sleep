<?php

namespace WPML\Core\Component\Translation\Application\Service\Priority;

use WPML\Core\Component\Translation\Application\Query\Priority\PostDataQueryInterface;
use WPML\Core\Component\Translation\Application\Query\Priority\StringDataQueryInterface;
use WPML\Core\Component\Translation\Application\Query\Priority\TermDataQueryInterface;

class JobPriorityServiceFactory {


  public static function create(
        $postDataQuery = null,
        $stringDataQuery = null,
        $termDataQuery = null,
        array $stringDomainPriorities = [],
        array $stringContextPriorities = [],
        array $cptPriorities = []
    ): JobPriorityService {
      $sorter         = new JobPrioritySorter();
      $contextBuilder = new ClassificationContextBuilder(
        null,
        $stringDomainPriorities,
        $stringContextPriorities,
        $cptPriorities
      );
      $itemBuilder    = new PrioritizableItemBuilder();
      $payloadBuilder = new OrderingPayloadBuilder();

      return new JobPriorityService(
        $sorter,
        $contextBuilder,
        $itemBuilder,
        $payloadBuilder,
        $postDataQuery,
        $stringDataQuery,
        $termDataQuery
      );
  }


  public static function createDefault(): JobPriorityService {
      return self::create();
  }


}
