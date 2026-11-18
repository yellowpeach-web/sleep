<?php

namespace WPML\PB\Gutenberg;

class ConfigLabels {

	private $config;

	private $blocks;

	private $namespaces;

	private $labels;

	public function __construct( $config = null ) {
		$this->config = $config;
	}

	public function getBlockLabel( $groupId ) {
		$this->load();

		foreach ( $this->getBlockCandidates( (string) $groupId ) as $dashed ) {
			if ( isset( $this->blocks[ $dashed ] ) ) {
				return $this->blocks[ $dashed ];
			}
		}

		foreach ( $this->getBlockCandidates( (string) $groupId ) as $dashed ) {
			if ( array_key_exists( $dashed, $this->blocks ) ) {
				return $this->getNamespaceLabel( $dashed );
			}
		}

		foreach ( $this->getBlockCandidates( (string) $groupId ) as $dashed ) {
			$label = $this->getNamespaceLabel( $dashed );
			if ( null !== $label ) {
				return $label;
			}
		}

		return null;
	}

	public function isConfigLabel( $title, array $groupIds ) {
		$this->load();

		if ( ! is_string( $title ) || ! isset( $this->labels[ $title ] ) ) {
			return false;
		}

		foreach ( $groupIds as $groupId ) {
			foreach ( $this->getBlockCandidates( (string) $groupId ) as $dashed ) {
				if ( array_key_exists( $dashed, $this->blocks ) || null !== $this->findNamespace( $dashed ) ) {
					return true;
				}
			}
		}

		return false;
	}

	private function getNamespaceLabel( $dashed ) {
		$namespace = $this->findNamespace( $dashed );

		return null === $namespace ? null : $this->namespaces[ $namespace ];
	}

	private function findNamespace( $dashed ) {
		foreach ( $this->namespaces as $namespace => $label ) {
			if ( 0 === strpos( $dashed, $namespace . '-' ) ) {
				return (string) $namespace;
			}
		}

		return null;
	}

	private function getBlockCandidates( $groupId ) {
		$candidates = [];
		$name       = $groupId;

		for ( $i = 0; $i < 2; $i++ ) {
			$stripped = preg_replace( '/-\d+$/', '', $name );
			if ( ! is_string( $stripped ) || $stripped === $name || '' === $stripped ) {
				break;
			}
			$candidates[] = $stripped;
			$name         = $stripped;
		}

		return $candidates;
	}

	private function load() {
		if ( null !== $this->labels ) {
			return;
		}

		if ( null === $this->config ) {
			$this->config = get_option( \WPML_Gutenberg_Config_Option::OPTION, [] );
		}

		$this->blocks     = [];
		$this->namespaces = [];
		$this->labels     = [];

		foreach ( is_array( $this->config ) ? $this->config : [] as $name => $blockConfig ) {
			$blockConfig = is_array( $blockConfig ) ? $blockConfig : [];
			$label       = isset( $blockConfig['label'] ) && $this->isLabel( $blockConfig['label'] ) ? $blockConfig['label'] : null;

			if ( false === strpos( (string) $name, '/' ) ) {
				$this->namespaces[ (string) $name ] = $label;
			} else {
				$this->blocks[ str_replace( '/', '-', (string) $name ) ] = $label;
			}

			if ( null !== $label ) {
				$this->labels[ $label ] = true;
			}

			if ( isset( $blockConfig['key'] ) && is_array( $blockConfig['key'] ) ) {
				$this->addKeyLabels( $blockConfig['key'] );
			}

			if ( isset( $blockConfig['xpath'] ) && is_array( $blockConfig['xpath'] ) ) {
				foreach ( $blockConfig['xpath'] as $xpath ) {
					if ( is_array( $xpath ) && isset( $xpath['label'] ) && $this->isLabel( $xpath['label'] ) ) {
						$this->labels[ $xpath['label'] ] = true;
					}
				}
			}
		}
	}

	private function addKeyLabels( array $keys ) {
		foreach ( $keys as $keyConfig ) {
			if ( ! is_array( $keyConfig ) ) {
				continue;
			}

			if ( isset( $keyConfig['label'] ) && $this->isLabel( $keyConfig['label'] ) ) {
				$this->labels[ $keyConfig['label'] ] = true;
			}

			if ( isset( $keyConfig['children'] ) && is_array( $keyConfig['children'] ) ) {
				$this->addKeyLabels( $keyConfig['children'] );
			}
		}
	}

	private function isLabel( $label ) {
		return is_string( $label ) && '' !== trim( $label );
	}
}
