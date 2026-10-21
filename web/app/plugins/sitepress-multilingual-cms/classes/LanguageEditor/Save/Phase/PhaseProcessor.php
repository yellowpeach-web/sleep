<?php

namespace WPML\LanguageEditor\Save\Phase;

interface PhaseProcessor {

	public function getId();

	public function applies( array $change );

	public function getTotal( array $change );

	public function getChunkSize();

	public function isSkippable();

	public function processChunk( array $change, $offset );
}
