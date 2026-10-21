<?php

abstract class WPML_TM_Xliff_Shared extends WPML_TM_Job_Factory_User {
	protected $error;

	private $validator = null;

	protected function replace_xliff_new_line_tag_with_new_line( $string ) {
		return WPML_TP_Xliff_Parser::restore_new_line( $string );
	}

	protected function identifier_from_xliff( $xliff ) {
		$file_attributes = $xliff->{'file'}->attributes();

		return (string) $file_attributes['original'];
	}

	public function get_job_for_xliff( SimpleXMLElement $xliff ) {
		$identifier           = $this->identifier_from_xliff( $xliff );
		$job_identifier_parts = explode( '-', $identifier );
		if ( count( $job_identifier_parts ) === 2 && is_numeric( $job_identifier_parts[0] ) ) {
			$job_id = $job_identifier_parts[0];
			$job_id = apply_filters( 'wpml_job_id', $job_id );
			$md5    = $job_identifier_parts[1];
			$job = $this->job_factory->get_translation_job( (int) $job_id, false, 1, false );
			if ( ! $job || $md5 !== md5( $job_id . $job->original_doc_id ) ) {
				$job = $this->does_not_belong_error();
			}
		} else {
			$job = $this->invalid_xliff_error();
		}

		return $job;
	}

	protected function get_xliff_node_target( $xliff_node ) {
		$target = '';
		if ( isset( $xliff_node->target->mrk ) ) {
			$target = (string) $xliff_node->target->mrk;
		} elseif ( isset( $xliff_node->target ) ) {
			$target = (string) $xliff_node->target;
		}

		return $target;
	}

	public function set_validator( $validator ) {
		$this->validator = $validator;
	}

	private function get_validator() {
		if ( null === $this->validator ) {
			$this->set_validator( new WPML_TM_Validate_HTML() );
		}

		return $this->validator;
	}

	protected function generate_job_data( SimpleXMLElement $xliff, $job ) {
		$data = [
			'job_id'   => $job->job_id,
			'rid'      => $job->rid,
			'fields'   => [],
			'complete' => 1,
		];

		$units = [];
		foreach ( $xliff->file->body->children() as $node ) {
			$attr = $node->attributes();

			if ( 'no' === strtolower( (string) $attr['translate'] ) ) {
				continue;
			}

			$type   = (string) $attr['id'];
			$target = $this->get_xliff_node_target( $node );
			if ( 'html' === (string) $attr['datatype'] ) {
				$target = $this->get_validator()->restore_html( $target );
			}

			if ( ! $this->is_valid_target( $target ) ) {
				$source = $this->get_xliff_node_source( $node );
				if ( 'html' === (string) $attr['datatype'] ) {
					$source = $this->get_validator()->restore_html( $source );
				}
				if ( $this->source_has_no_text( $source ) ) {
					$target = $source;
				} else {
					return $this->invalid_xliff_error( array( 'target' ) );
				}
			}

			$units[] = [
				'id'     => $type,
				'target' => $target,
			];
		}

		$units = $this->collapse_segmented_suggestion_units( $units, $job );

		foreach ( $units as $unit ) {
			$type   = $unit['id'];
			$target = $unit['target'];

			foreach ( $job->elements as $element ) {
				if ( $element->field_type === $type ) {
					if ( isset( $element->field_translate ) && 0 === (int) $element->field_translate ) {
						break;
					}

					$target              = $this->replace_xliff_new_line_tag_with_new_line( $target );
					$field               = array();
					$field['data']       = $target;
					$field['finished']   = 1;
					$field['tid']        = $element->tid;
					$field['field_type'] = $element->field_type;
					$field['format']     = $element->field_format;

					$data['fields'][ $element->field_type ] = $field;
					break;
				}
			}
		}

		return $data;
	}

	private function collapse_segmented_suggestion_units( array $units, $job ) {
		$grouped = [];
		foreach ( $units as $index => $unit ) {
			$split = \WPML\TM\XLIFF\SegmentedSuggestion::split_unit_id( $unit['id'] );
			if ( $split ) {
				$grouped[ $split[0] ][ $split[1] ] = $unit['target'];
				unset( $units[ $index ] );
			}
		}

		if ( ! $grouped ) {
			return $units;
		}

		foreach ( $units as $index => $unit ) {
			$base = $unit['id'];
			if ( ! isset( $grouped[ $base ] ) ) {
				continue;
			}

			$segments      = $grouped[ $base ];
			$segments[1]   = $unit['target'];
			ksort( $segments );
			if ( array_keys( $segments ) !== range( 1, count( $segments ) ) ) {
				continue;
			}

			$reassembled = $this->reassemble_segmented_field( array_values( $segments ), $job, $base );
			if ( null !== $reassembled ) {
				$units[ $index ]['target'] = $reassembled;
			}
		}

		return array_values( $units );
	}

	private function reassemble_segmented_field( array $segments, $job, $field_type ) {
		foreach ( $job->elements as $element ) {
			if ( $element->field_type !== $field_type ) {
				continue;
			}

			$previous = isset( $element->field_data_translated ) ? base64_decode( (string) $element->field_data_translated ) : '';
			$source   = isset( $element->field_data ) ? base64_decode( (string) $element->field_data ) : '';

			return \WPML\TM\XLIFF\SegmentedSuggestion::reassemble_into( $segments, $previous, $source );
		}

		return null;
	}

	protected function get_xliff_node_source( $xliff_node ) {
		$source = '';
		if ( isset( $xliff_node->source->mrk ) ) {
			$source = (string) $xliff_node->source->mrk;
		} elseif ( isset( $xliff_node->source ) ) {
			$source = (string) $xliff_node->source;
		}

		return $source;
	}

	private function source_has_no_text( $source ) {
		$text = html_entity_decode( strip_tags( $source ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		return '' === preg_replace( '/(*UCP)\s+/u', '', (string) $text );
	}

	private function is_valid_target( $target ) {
		return $target || '0' === $target;
	}

	protected function validate_file( $name, $content, $current_user ) {
		$xml = $this->check_xml_file( $name, $content );

		if ( ! $xml ) {
			return null;
		}

		$this->error = null;
		if ( is_wp_error( $xml ) ) {
			$this->error = $xml;

			return null;
		}

		$job = $this->get_job_for_xliff( $xml );
		if ( is_wp_error( $job ) ) {
			$this->error = $job;

			return null;
		}
		if ( ! $this->is_user_the_job_owner( $current_user, $job ) ) {
			$this->error = $this->not_the_job_owner_error( $job );

			return null;
		}
		$job_data = $this->generate_job_data( $xml, $job );
		if ( is_wp_error( $job_data ) ) {
			$this->error = $job_data;

			return null;
		}

		return array( $job, $job_data );
	}

	function validate_file_name( $filename ) {
		$ignored_files = apply_filters( 'wpml_xliff_ignored_files', array( '__MACOSX' ) );
		return ! ( '/' === substr( $filename, -1 ) || '/' === substr( $filename, 0, 1 ) || in_array( $filename, $ignored_files, false ) );
	}

	protected function is_user_the_job_owner( $current_user, $job ) {
		return (int) $current_user->ID === (int) $job->translator_id;
	}

	protected function not_the_job_owner_error( $job ) {
		/* translators: %s: translation job ID. */
		$message = sprintf( __( 'The translation job (%s) doesn\'t belong to you.', 'sitepress' ), $job->job_id );

		return new WP_Error( 'not_your_job', $message );
	}

	protected function check_xml_file( $name, $content ) {
		set_error_handler( array( $this, 'error_handler' ) );
		try {
			$xml = simplexml_load_string( $content );
		} catch ( Exception $e ) {
			$xml = false;
		}
		restore_error_handler();
		if ( ! $xml || ! isset( $xml->file ) ) {
			$xml = $this->not_xml_file_error( $name );
		}

		return $xml;
	}

	protected function error_handler( $errno, $errstr, $errfile, $errline ) {
		throw new ErrorException( $errstr, $errno, 1, $errfile, $errline );
	}

	protected function not_xml_file_error( $name ) {
		/* translators: %s: uploaded file name. */
		$message = sprintf( __( '"%s" is not a valid XLIFF file.', 'sitepress' ), $name );

		return new WP_Error( 'not_xml_file', $message );
	}

	protected function invalid_xliff_error( array $missing_data = array() ) {
		$message = __( 'The uploaded xliff file does not seem to be properly formed.', 'sitepress' );

		if ( $missing_data ) {
			$message .= '<br>' . __( 'Missing or wrong data:', 'sitepress' );
			if ( count( $missing_data ) > 1 ) {
				$message .= '<ol>';
				$message .= '<li><strong>' . implode( '</strong></li><li><strong>', $missing_data ) . '</strong></li>';
				$message .= '</ol>';
			} else {
				$message .= ' <strong>' . $missing_data[0] . '</strong>';
			}
		}

		return new WP_Error( 'xliff_invalid', $message );
	}

	protected function does_not_belong_error() {

		return new WP_Error( 'xliff_does_not_match', __( "The uploaded xliff file doesn't belong to this system.", 'sitepress' ) );
	}
}
