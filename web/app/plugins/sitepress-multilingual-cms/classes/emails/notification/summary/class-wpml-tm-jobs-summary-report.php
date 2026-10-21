<?php

use WPML\Core\Component\WordsToTranslate\Domain\Calculator\WordsToTranslate;

class WPML_TM_Jobs_Summary_Report {

	const PACKAGE_TYPE_PREFIX = 'package_';

	private $jobs_collection;

	private $jobs = array();

	private $type;

	private $element_factory;

	private $count_field_words;

	public function __construct(
		WPML_Translation_Jobs_Collection $jobs_collection,
		$type,
		WPML_Translation_Element_Factory $element_factory,
		$count_field_words = null
	) {
		$this->jobs_collection   = $jobs_collection;
		$this->type              = $type;
		$this->element_factory   = $element_factory;
		$this->count_field_words = is_callable( $count_field_words ) ? $count_field_words : [ $this, 'count_words_with_tokenizer' ];
		$this->build_completed_jobs();
		$this->build_waiting_jobs();
	}

	private function build_completed_jobs() {
		$jobs = $this->jobs_collection->get_jobs( array(
				'any_translation_service' => true,
				'status'                  => ICL_TM_COMPLETE
			)
		);

		foreach ( $jobs as $job ) {
			$completed_date = $job instanceof WPML_Post_Translation_Job || $job instanceof WPML_Element_Translation_Job ? $job->get_completed_date() : '';

			if ( ! $completed_date ) {
				continue;
			}

			$out_of_period = strtotime( $completed_date ) < strtotime( '-' . WPML_TM_Jobs_Summary::WEEKLY_SCHEDULE );

			if ( WPML_TM_Jobs_Summary::DAILY_REPORT === $this->type ) {
				$out_of_period = strtotime( $completed_date ) < strtotime( '-' . WPML_TM_Jobs_Summary::DAILY_SCHEDULE );
			}

			if ( $out_of_period ) {
				continue;
			}

			if ( $this->is_package_job( $job ) ) {
				$original_page   = array(
					'title' => $job->get_title(),
					'url'   => $this->get_package_edit_link( $job->get_original_element_id() ),
				);
				$translated_page = array(
					'title' => $job->get_title() . ' (' . $job->get_language_code() . ')',
					'url'   => '',
				);
			} else {
				$original_element    = $this->element_factory->create( $job->get_original_element_id(), $job->get_type() );
				$translation_element = $original_element->get_translation( $job->get_language_code() );

				if ( is_null( $translation_element ) ) {
					continue;
				}

				$original_page   = array(
					'title' => $job->get_title(),
					'url'   => $job->get_url( true ),
				);
				$translated_page = array(
					'title' => get_the_title( $translation_element->get_element_id() ) . ' (' . $job->get_language_code() . ')',
					'url'   => get_the_permalink( $translation_element->get_element_id() ),
				);
			}

			$this->jobs[ $job->get_basic_data()->manager_id ][ WPML_TM_Jobs_Summary::JOBS_COMPLETED_KEY ][] = array(
				'completed_date'  => date_i18n( get_option( 'date_format', 'F d, Y' ), strtotime( $job->get_completed_date() ) ),
				'original_page'   => $original_page,
				'translated_page' => $translated_page,
				'translator'      => $this->get_translator_name( $job ),
				'deadline'        => $job->get_deadline_date() ?
					date_i18n( get_option( 'date_format', 'F d, Y' ), strtotime( $job->get_deadline_date() ) ) :
					'',
				'status'          => $job->get_status(),
				'overdue'         => $job->get_deadline_date() &&
				                     strtotime( $job->get_deadline_date() ) < strtotime( $job->get_completed_date() )
			);
		}
	}

	private function build_waiting_jobs() {
		$jobs                       = $this->jobs_collection->get_jobs( array(
				'any_translation_service' => true,
				'status'                  => ICL_TM_WAITING_FOR_TRANSLATOR
			)
		);
		$counters                   = array();
		$number_of_strings          = 0;
		$number_of_words_in_strings = 0;

		foreach ( $jobs as $job ) {
			$manager_id = isset( $job->get_basic_data()->manager_id ) ? $job->get_basic_data()->manager_id : 0;
			$lang_pair  = $job->get_source_language_code() . '|' . $job->get_language_code();

			if ( ! isset( $counters[ $manager_id ][ $lang_pair ]['number_of_strings'], $counters[ $manager_id ][ $lang_pair ]['number_of_words'], $counters[ $manager_id ][ $lang_pair ]['number_of_pages'] ) ) {
				$counters[ $manager_id ][ $lang_pair ]['number_of_strings'] = 0;
				$counters[ $manager_id ][ $lang_pair ]['number_of_words']   = 0;
				$counters[ $manager_id ][ $lang_pair ]['number_of_pages']   = 0;
			}

			if ( 'String' === $job->get_type() ) {
				$number_of_strings ++;
				$number_of_words_in_strings += apply_filters( 'wpml_word_count_calculate_string', 0, $job->get_original_element_id() );
			} else {
				if ( $this->is_package_job( $job ) ) {
					$fields = $this->get_job_field_contents( $job );

					$counters[ $manager_id ][ $lang_pair ]['number_of_strings'] += count( $fields );
					$counters[ $manager_id ][ $lang_pair ]['number_of_words']   += (int) call_user_func( $this->count_field_words, $fields, $job->get_source_language_code() );
				} else {
					$counters[ $manager_id ][ $lang_pair ]['number_of_pages'] += 1;
					$counters[ $manager_id ][ $lang_pair ]['number_of_words'] += apply_filters( 'wpml_word_count_calculate_post', 0, $job->get_original_element_id() );
				}

				$this->jobs[ $manager_id ][ WPML_TM_Jobs_Summary::JOBS_WAITING_KEY ][ $lang_pair ] = array(
					'lang_pair'         => sprintf(
						/* translators: Language pair in the email WPML sends about translation work, e.g. "en to fr". %1$s: source language, %2$s: target language. */
						__( '%1$s to %2$s', 'sitepress' ),
						$job->get_source_language_code( true ),
						$job->get_language_code( true )
					),
					'number_of_strings' => $counters[ $manager_id ][ $lang_pair ]['number_of_strings'] + $number_of_strings,
					'number_of_words'   => $counters[ $manager_id ][ $lang_pair ]['number_of_words'] + $number_of_words_in_strings,
					'number_of_pages'   => $counters[ $manager_id ][ $lang_pair ]['number_of_pages'],
				);
			}
		}
	}

	private function is_package_job( $job ) {
		$basic_data = $job->get_basic_data();

		return is_object( $basic_data )
		       && isset( $basic_data->original_post_type )
		       && 0 === strpos( (string) $basic_data->original_post_type, self::PACKAGE_TYPE_PREFIX );
	}

	private function get_job_field_contents( $job ) {
		$basic_data = $job->get_basic_data();
		$elements   = is_object( $basic_data ) && isset( $basic_data->elements ) && is_array( $basic_data->elements )
			? $basic_data->elements
			: array();

		$contents = array();
		foreach ( $elements as $element ) {
			if ( ! isset( $element->field_data ) || ( isset( $element->field_translate ) && ! (int) $element->field_translate ) ) {
				continue;
			}
			$decoded    = base64_decode( (string) $element->field_data, true );
			$contents[] = false === $decoded ? (string) $element->field_data : $decoded;
		}

		return $contents;
	}

	public function count_words_with_tokenizer( array $fields, $lang ) {
		global $wpml_dic;

		if ( ! $fields || ! $wpml_dic ) {
			return 0;
		}

		return (int) $wpml_dic->make( WordsToTranslate::class )->forFieldContents( $fields, strtolower( (string) $lang ) );
	}

	private function get_package_edit_link( $package_id ) {
		if ( ! class_exists( 'WPML_Package' ) ) {
			return '';
		}

		$package = new WPML_Package( $package_id );

		return isset( $package->edit_link ) ? (string) $package->edit_link : '';
	}

	private function get_translator_name( WPML_Element_Translation_Job $job ) {
		$translation_service = $job->get_translation_service();

		if ( 'local' === $translation_service ) {
			return $this->get_local_translator_name( $job );
		}

		return $translation_service
			? TranslationProxy::get_service_name( $translation_service )
			: $job->get_translator_name();
	}

	private function get_local_translator_name( WPML_Element_Translation_Job $job ) {
		if ( $this->is_automatic( $job ) ) {
			/* translators: Shown in place of the name of a translator when the translation was made by a machine. */
			return __( 'Automatic translation', 'sitepress' );
		}

		$user = get_userdata( $job->get_translator_id() );

		if ( ! $user ) {
			/* translators: Shown in place of the name of a translator when it is not known who made the translation. */
			return __( 'Unknown translator', 'sitepress' );
		}

		return $user->display_name . ' (' . $user->user_login . ')';
	}

	private function is_automatic( WPML_Element_Translation_Job $job ) {
		$basic_data = $job->get_basic_data();

		return is_object( $basic_data )
		       && property_exists( $basic_data, 'automatic' )
		       && (int) $basic_data->automatic === 1;
	}

	public function get_jobs() {
		return $this->jobs;
	}
}