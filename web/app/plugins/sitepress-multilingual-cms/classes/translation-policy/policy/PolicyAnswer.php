<?php

namespace WPML\TranslationPolicy\Policy;

class PolicyAnswer {

	private $editor;

	private $method;

	private $editorScope;

	private $editorStrength;

	private $editorSource;

	private $methodStrength;

	private $rowEditor;

	public function __construct( string $editor, $method, string $editorScope = '', string $editorStrength = '', string $editorSource = '', string $methodStrength = '', string $rowEditor = '' ) {
		$this->editor         = $editor;
		$this->method         = $method;
		$this->editorScope    = $editorScope;
		$this->editorStrength = $editorStrength;
		$this->editorSource   = $editorSource;
		$this->methodStrength = $methodStrength;
		$this->rowEditor      = $rowEditor;
	}

	public function editor(): string {
		return $this->editor;
	}

	public function method() {
		return $this->method;
	}

	public function editorScope(): string {
		return $this->editorScope;
	}

	public function editorStrength(): string {
		return $this->editorStrength;
	}

	public function editorSource(): string {
		return $this->editorSource;
	}

	public function rowEditor(): string {
		return $this->rowEditor;
	}

	public function isEditorLocked(): bool {
		return PolicyTable::STRENGTH_LOCK === $this->editorStrength;
	}

	public function methodStrength(): string {
		return $this->methodStrength;
	}

	public function isPtcChosen(): bool {
		return PolicyTable::METHOD_PTC === $this->method && PolicyTable::STRENGTH_CHOICE === $this->methodStrength;
	}
}
