<?php

namespace WPML\TranslationPolicy;

interface CutoverDryRunReport {

	public function dryRunReport(): string;
}
