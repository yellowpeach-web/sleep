<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Settings;

class AiSettingsSearchIndex implements \IWPML_Backend_Action {


  const AI_TRANSLATION_SECTION = 'section=ai-translation';


  public function add_hooks() {
    add_filter( 'wpml_settings_search_index', array( $this, 'addSubs' ) );
  }


  public function addSubs( $index ) {
    if ( ! is_array( $index ) || ! self::ateEnabled() ) {
      return $index;
    }

    foreach ( $index as $i => $section ) {
      if (
        isset( $section['href'] )
        && is_string( $section['href'] )
        && strpos( $section['href'], self::AI_TRANSLATION_SECTION ) !== false
      ) {
        $index[ $i ]['subs'] = self::subs();
        break;
      }
    }

    return $index;
  }


  private static function subs(): array {
    return array(
      /* translators: Name of the card on WPML → Settings that shows which service translates the site: its heading and its entry in the settings search list. */
      array( 'label' => __( 'Translation engine', 'wpml' ), 'anchor' => 'engine' ),
      array( 'label' => __( 'Context and Target Audience', 'wpml' ), 'anchor' => 'context' ),
      array( 'label' => __( 'The name of your product/service/website', 'wpml' ), 'anchor' => 'context' ),
      array( 'label' => __( 'Describe what your website is about', 'wpml' ), 'anchor' => 'context' ),
      array( 'label' => __( 'Target audience', 'wpml' ), 'anchor' => 'context' ),
      array( 'label' => __( 'Formality per language', 'wpml' ), 'anchor' => 'context' ),
      array( 'label' => __( 'Automatic translation behavior', 'wpml' ), 'anchor' => 'auto-translate' ),
      array( 'label' => __( 'Automatically translate content when the Translation Editor opens', 'wpml' ), 'anchor' => 'auto-translate' ),
      array(
        'label'  => __( 'Translate drafts automatically', 'wpml' ),
        'anchor' => 'auto-translate',
        'target' => 'translate-everything-drafts-checkbox',
      ),
      array( 'label' => __( 'After auto-translation', 'wpml' ), 'anchor' => 'after-translation' ),
      array(
        'label'  => __( 'Publish without review', 'wpml' ),
        'anchor' => 'after-translation',
        'target' => 'wpml-ai-review-mode',
      ),
      array(
        'label'  => __( 'Wait for review before publishing', 'wpml' ),
        'anchor' => 'after-translation',
        'target' => 'wpml-ai-review-mode',
      ),
      array(
        'label'  => __( 'Publish and mark for review', 'wpml' ),
        'anchor' => 'after-translation',
        'target' => 'wpml-ai-review-mode',
      ),
      array( 'label' => __( 'Who can use Automatic Translation', 'wpml' ), 'anchor' => 'who-can-use' ),
      /* translators: Entry in the settings search list of WPML → Settings, pointing at the Automation preferences block. */
      array( 'label' => __( 'Automation preferences', 'wpml' ), 'anchor' => 'automation-preferences' ),
      array( 'label' => __( 'Track my edits and suggest improvements', 'wpml' ), 'anchor' => 'automation-preferences' ),
      array( 'label' => __( 'Email me when new patterns are detected', 'wpml' ), 'anchor' => 'automation-preferences' ),
      array( 'label' => __( 'Apply automatically without asking', 'wpml' ), 'anchor' => 'automation-preferences' ),
      array( 'label' => __( 'Link translations', 'wpml' ), 'anchor' => 'automation-preferences' ),
      array( 'label' => __( 'Glossary terms', 'wpml' ), 'anchor' => 'automation-preferences' ),
      array( 'label' => __( 'Regional variant changes', 'wpml' ), 'anchor' => 'automation-preferences' ),
      array( 'label' => __( 'Formality changes', 'wpml' ), 'anchor' => 'automation-preferences' ),
    );
  }


  private static function ateEnabled(): bool {
    return \WPML_TM_ATE_Status::is_enabled();
  }


}
