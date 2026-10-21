<?php

namespace WPML\LanguageEditor\Save;

class SaveTaskRepository {

	const OPTION_NAME = 'wpml_lang_save_task';

	const ACTIVE_FLAG_OPTION = 'wpml_lang_save_task_active';

	private $wpdb;

	public function __construct( ?\wpdb $wpdb = null ) {
		$this->wpdb = $wpdb;
	}

	public function insert( SaveTask $task ) {
		if ( ! $task->getStartingDate() ) {
			$task->setStartingDate( current_time( 'mysql' ) );
		}
		$task->setTaskId( $this->nextId() );
		$this->store( $task );
		return $task;
	}

	public function update( SaveTask $task ) {
		if ( ! $task->getTaskId() ) {
			return;
		}
		$this->store( $task );
	}

	public function findById( $taskId ) {
		$task = $this->read();
		if ( $task && (int) $task->getTaskId() === (int) $taskId ) {
			return $task;
		}
		return null;
	}

	public function findActive() {
		$flag = get_option( self::ACTIVE_FLAG_OPTION, null );
		if ( null !== $flag && ! $flag ) {
			return null;
		}

		$task   = $this->read();
		$active = ( $task && ! $task->isTerminal() ) ? $task : null;

		if ( null === $flag || ! $active ) {
			update_option( self::ACTIVE_FLAG_OPTION, $active ? '1' : '0', true );
		}

		return $active;
	}

	public function delete( $taskId ) {
		$task = $this->read();
		if ( $task && (int) $task->getTaskId() === (int) $taskId ) {
			delete_option( self::OPTION_NAME );
			update_option( self::ACTIVE_FLAG_OPTION, '0', true );
		}
	}

	private function store( SaveTask $task ) {
		$value = $task->serialize();
		$value['task_id'] = $task->getTaskId();

		if ( ! $task->isTerminal() ) {
			update_option( self::ACTIVE_FLAG_OPTION, '1', true );
		}

		if ( false === add_option( self::OPTION_NAME, $value, '', 'no' ) ) {
			update_option( self::OPTION_NAME, $value, false );
		}

		if ( $task->isTerminal() ) {
			update_option( self::ACTIVE_FLAG_OPTION, '0', true );
		}
	}

	private function read() {
		$row = get_option( self::OPTION_NAME, null );
		return $this->hydrate( is_array( $row ) ? $row : null );
	}

	private function nextId() {
		$task = $this->read();
		return $task && $task->getTaskId() ? (int) $task->getTaskId() + 1 : 1;
	}

	private function hydrate( $row ) {
		if ( ! is_array( $row ) ) {
			return null;
		}
		$task = new SaveTask();
		$task->setTaskId( isset( $row['task_id'] ) ? $row['task_id'] : null );
		$task->setStatus( isset( $row['task_status'] ) ? $row['task_status'] : SaveTask::STATUS_PENDING );
		$task->setTotalCount( isset( $row['total_count'] ) ? $row['total_count'] : 0 );
		$task->setCompletedCount( isset( $row['completed_count'] ) ? $row['completed_count'] : 0 );
		$task->setRetryCount( isset( $row['retry_count'] ) ? $row['retry_count'] : 0 );
		$task->setStartingDate( isset( $row['starting_date'] ) ? $row['starting_date'] : null );
		$payload = isset( $row['payload'] ) ? maybe_unserialize( $row['payload'] ) : [];
		$task->setPayload( is_array( $payload ) ? $payload : [] );
		return $task;
	}
}
