<?php
namespace WPML\BackgroundTask;

use WPML\BackgroundTask\BackgroundTaskLoader;
use WPML\BackgroundTask\BackgroundTaskViewModel;
use WPML\Collect\Support\Collection;
use WPML\Core\BackgroundTask\Exception\TaskIsNotRunnableException;
use WPML\Core\BackgroundTask\Model\TaskEndpointInterface;
use WPML\Core\BackgroundTask\Service\BackgroundTaskService;
use WPML\Core\BackgroundTask\Command\UpdateBackgroundTask;
use WPML\Core\BackgroundTask\Model\BackgroundTask;
use WPML\Ajax\Authorization\Authorized;
use WPML\Ajax\IHandler;
use WPML\FP\Either;
use WPML\LIB\WP\User;
use function WPML\Container\make;

abstract class AbstractTaskEndpoint implements TaskEndpointInterface, IHandler, Authorized {
	const LOCK_TIME = 2*60;
	const MAX_RETRIES = 0;

	protected $updateBackgroundTask;

	protected $backgroundTaskService;

	public function __construct( UpdateBackgroundTask $updateBackgroundTask, BackgroundTaskService $backgroundTaskService ) {
		$this->updateBackgroundTask     = $updateBackgroundTask;
		$this->backgroundTaskService = $backgroundTaskService;
	}

	public function isValidTask( $task_id ) {
		return true;
	}

	public function isDisplayed() {
		return true;
	}

	public function getLockTime() {
		return static::LOCK_TIME;
	}

	public function getMaxRetries() {
		return static::MAX_RETRIES;
	}

	public function getType() {
		return static::class;
	}

	abstract function runBackgroundTask( BackgroundTask $task );

	public function authorize( Collection $data ) {
		return User::canManageTranslations();
	}

	public function run(
		Collection $data
	) {
		$endpointLock = null;

		try {
			if ( ! isset( $data['taskId'] ) ) {
				throw new TaskIsNotRunnableException();
			}

			$endpointLock = $this->makeLock( $this->getType() );

			if ( ! $endpointLock->create( $this->getLockTime() ) ) {
				return Either::left( [ 'error' => 'locked' ] );
			}

			$taskId     = $data['taskId'];
			$task       = $this->backgroundTaskService->startByTaskId( $taskId );
			$task       = $this->runBackgroundTask( $task );

			$this->updateBackgroundTask->runUpdate( $task );

			return $this->getResponse( $task );
		} catch ( TaskIsNotRunnableException $e ) {
			if ( $endpointLock ) {
				$endpointLock->release();
			}

			return Either::left( [ 'error' => $e->getMessage() ] );
		}
	}

	private function makeLock( $taskType ) {
		return make( 'WPML\Utilities\Lock', [ ':name' => $taskType ] );
	}

	private function getResponse( BackgroundTask $backgroundTask ) {
		$this->makeLock( $backgroundTask->getTaskType() )->release();

		return Either::of(
			BackgroundTaskViewModel::get( $backgroundTask, true )
		);
	}
}
