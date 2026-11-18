<?php

namespace WPML\BackgroundTask;

use WPML\Core\BackgroundTask\Model\TaskEndpointInterface;
use WPML\Core\BackgroundTask\Model\BackgroundTask;
use function WPML\Container\make;

class BackgroundTaskViewModel {

	public static function get( $backgroundTask, $withLockReading = false, &$lockReadings = null ) {
		$className = $backgroundTask->getTaskType();

		if ( ! class_exists( $className ) ) {
			return;
		}

		$endpointInstance = make( $className );

		$hasLock = false;
		if ( $withLockReading && ! $backgroundTask->isStatusPaused() ) {
			if ( is_array( $lockReadings ) && array_key_exists( $className, $lockReadings ) ) {
				$hasLock = $lockReadings[ $className ];
			} else {
				$endpointLock = make( 'WPML\Utilities\Lock', [ ':name' => $className ] );
				$hasLock      = ! $endpointLock->isHeld( $endpointInstance->getLockTime() );
				if ( is_array( $lockReadings ) ) {
					$lockReadings[ $className ] = $hasLock;
				}
			}
		}

		return [
			'isPaused'      => $backgroundTask->isStatusPaused(),
			'progressTotal' => $backgroundTask->getTotalCount(),
			'progressDone'  => $backgroundTask->getCompletedCount(),
			'payload'       => $backgroundTask->getPayload(),
			'taskId'        => $backgroundTask->getTaskId(),
			'taskStatus'    => $backgroundTask->getStatusName(),
			'isCompleted'   => $backgroundTask->isStatusCompleted(),
			'description'   => $endpointInstance->getDescription( wpml_collect( $backgroundTask->getPayload() ) ),
			'taskType'      => $backgroundTask->getTaskType(),
			'isDisplayed'   => $endpointInstance->isDisplayed(),
			'hasLock'       => $hasLock,
		];
	}
}
