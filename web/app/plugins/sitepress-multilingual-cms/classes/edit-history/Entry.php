<?php

namespace WPML\EditHistory;

class Entry {

	public $id;

	public $subject;

	public $language;

	public $time;

	public $source;

	public $editor;

	public $userId;

	public $lists;

	public function __construct( Subject $subject, string $language, int $time, string $source, string $editor, $userId, array $lists, $id = null ) {
		$this->subject  = $subject;
		$this->language = $language;
		$this->time     = $time;
		$this->source   = $source;
		$this->editor   = $editor;
		$this->userId   = $userId;
		$this->lists    = $lists;
		$this->id       = $id;
	}
}
