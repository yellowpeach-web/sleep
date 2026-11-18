<?php

namespace WPML\Media\TextScan;

class SettingControl {

	const LIKELY_FILTER = 'wpml_media_text_scan_likely_available';

	public static function values() {
		$values = [ Mode::ALL ];

		if ( (bool) apply_filters( self::LIKELY_FILTER, false ) ) {
			$values[] = Mode::LIKELY;
		}

		$values[] = Mode::OFF;

		return $values;
	}

	public static function label( $mode ) {
		if ( Mode::LIKELY === $mode ) {
			/* translators: A value of the setting "Find images with text" on the Media Translation page. */
			return __( 'Only images likely to contain text', 'wpml-media' );
		}

		if ( Mode::OFF === $mode ) {
			/* translators: A value of the setting "Find images with text" on the Media Translation page: images are not checked automatically. */
			return __( 'Off', 'wpml-media' );
		}

		/* translators: A value of the setting "Find images with text" on the Media Translation page, the recommended one. */
		return __( 'Scan all images in scope', 'wpml-media' );
	}

	public static function description( $mode ) {
		if ( Mode::LIKELY === $mode ) {
			/* translators: Explanation of the value "Only images likely to contain text" of the setting "Find images with text" on the Media Translation page. */
			return __( 'WPML looks at each image on your site first and sends only the images that probably contain text. Fewer images leave your site, but some images with text may be missed. You can still pick those yourself.', 'wpml-media' );
		}

		if ( Mode::OFF === $mode ) {
			/* translators: Explanation of the value "Off" of the setting "Find images with text" on the Media Translation page. */
			return __( 'WPML does not check images on its own. Images are translated only when you pick them on this page or in the Translation Editor.', 'wpml-media' );
		}

		/* translators: Explanation of the value "Scan all images in scope" of the setting "Find images with text" on the Media Translation page. "512 pixels" is the size of the copy that is sent. */
		return __( "Each image in scope is checked when you scan. To check an image, WPML sends a small copy of it (512 pixels) to WPML's translation service.", 'wpml-media' );
	}

	public static function panel( $current ) {
		$values = self::values();
		if ( Mode::LIKELY === $current && ! in_array( Mode::LIKELY, $values, true ) ) {
			array_splice( $values, 1, 0, [ Mode::LIKELY ] );
		}

		$options = [];
		foreach ( $values as $value ) {
			$options[] = [
				'value'       => $value,
				'id'          => 'wpml-media-text-scan-mode-' . $value,
				'label'       => self::label( $value ),
				'description' => self::description( $value ),
				'recommended' => Mode::ALL === $value,
				'checked'     => $current === $value,
			];
		}

		return [
			'options' => $options,
			'strings' => [
				/* translators: Heading of the setting on the Media Translation page that decides which images WPML checks for text to translate. */
				'legend'      => __( 'Find images with text', 'wpml-media' ),
				/* translators: Introduction of the setting "Find images with text" on the Media Translation page. */
				'lead'        => __( 'Before WPML translates the text on images automatically, it checks which images have text worth translating. Images without such text are skipped.', 'wpml-media' ),
				/* translators: Badge next to the recommended value of the setting "Find images with text" on the Media Translation page. */
				'recommended' => __( 'Recommended', 'wpml-media' ),
				/* translators: First sentence of a note under the setting "Find images with text" on the Media Translation page, shown in bold. */
				'camera_head' => __( 'Camera photos are skipped unless you pick them.', 'wpml-media' ),
				/* translators: Second sentence of a note under the setting "Find images with text" on the Media Translation page, after "Camera photos are skipped unless you pick them." */
				'camera_text' => __( 'Photos taken with a camera rarely have text worth translating, so WPML skips them in every mode.', 'wpml-media' ),
				/* translators: Note under the setting "Find images with text" on the Media Translation page: which image files are checked. */
				'scope'       => __( 'Images in scope: the original JPEG, PNG, WebP and static GIF files, at least 64 pixels on each side. Translated copies, SVG files and animated GIFs are never checked.', 'wpml-media' ),
				/* translators: Message on the Media Translation page after the setting "Find images with text" was saved. */
				'saved'       => __( 'Settings saved', 'wpml-media' ),
				/* translators: Message on the Media Translation page when the setting "Find images with text" could not be saved. */
				'failed'      => __( 'The setting could not be saved. Check your connection and try again.', 'wpml-media' ),
				/* translators: Button label that closes the media translation dialog without saving. Verb, imperative, not the noun "a cancellation". */
				'cancel'      => __( 'Cancel', 'wpml-media' ),
				/* translators: Button label that saves the setting "Find images with text" on the Media Translation page. Verb, imperative. */
				'save'        => __( 'Save', 'wpml-media' ),
			],
		];
	}
}
