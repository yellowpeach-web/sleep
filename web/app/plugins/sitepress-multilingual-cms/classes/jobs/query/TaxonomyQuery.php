<?php

namespace WPML\TM\Jobs\Query;

use WPML_TM_Job_Entity;

class TaxonomyQuery extends AbstractQuery {
	protected $title_column = 'terms.name';

	protected $batch_join = 'LEFT JOIN';

	protected function add_resource_join( QueryBuilder $query_builder ) {
		$query_builder->add_join(
			"INNER JOIN {$this->wpdb->prefix}term_taxonomy term_taxonomy
			ON term_taxonomy.term_taxonomy_id = original_translations.element_id"
		);
		$query_builder->add_join(
			"INNER JOIN {$this->wpdb->prefix}terms terms
			ON terms.term_id = term_taxonomy.term_id"
		);

		$query_builder->add_AND_where_condition( "original_translations.element_type LIKE 'tax\\_%'" );
	}

	protected function get_type() {
		return WPML_TM_Job_Entity::TAXONOMY_TYPE;
	}
}
