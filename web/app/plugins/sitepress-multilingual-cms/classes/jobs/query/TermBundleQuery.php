<?php

namespace WPML\TM\Jobs\Query;

use WPML\TM\ATE\TranslateEverything\TermBundle;
use WPML_TM_Job_Entity;

class TermBundleQuery extends AbstractQuery {
	protected $title_column = 'translate_job.title';

	protected function add_resource_join( QueryBuilder $query_builder ) {
		$query_builder->add_AND_where_condition( "original_translations.element_type = '" . TermBundle::ELEMENT_TYPE . "'" );
	}

	protected function get_type() {
		return WPML_TM_Job_Entity::TAXONOMY_TYPE;
	}
}
