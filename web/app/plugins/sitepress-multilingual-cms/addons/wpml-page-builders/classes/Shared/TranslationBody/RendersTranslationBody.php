<?php

namespace WPML\PB\TranslationBody;

interface RendersTranslationBody {

	public function render_translation_body( $translated_post_id, \WP_Post $original_post );
}
