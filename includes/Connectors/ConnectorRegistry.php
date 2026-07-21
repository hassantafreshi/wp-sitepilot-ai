<?php
declare(strict_types=1);

namespace SitePilot\Connectors;

use SitePilot\Core\Settings;

/**
 * Holds connectors and resolves a capability to the best available one.
 */
final class ConnectorRegistry {

	/** @var ConnectorInterface[] */
	private array $connectors = array();

	public function register( ConnectorInterface $connector ): void {
		$this->connectors[ $connector->get_id() ] = $connector;
	}

	/** @return ConnectorInterface[] */
	public function all(): array {
		return array_values( $this->connectors );
	}

	public function get( string $id ): ?ConnectorInterface {
		return $this->connectors[ $id ] ?? null;
	}

	/**
	 * Resolve which connector should serve a capability on this site.
	 *
	 * Preference order: Easy Form Builder (if available and preferred),
	 * then any other available connector, with the built-in one as the fallback.
	 */
	public function resolve( string $capability ): ?ConnectorInterface {
		$prefer_efb = (bool) Settings::get( 'prefer_efb', true );

		$candidates = array();
		foreach ( $this->connectors as $connector ) {
			if ( in_array( $capability, $connector->get_capabilities(), true ) && $connector->is_available() ) {
				$candidates[] = $connector;
			}
		}

		if ( array() === $candidates ) {
			return null;
		}

		if ( $prefer_efb ) {
			foreach ( $candidates as $connector ) {
				if ( 'easy_form_builder' === $connector->get_id() ) {
					return $connector;
				}
			}
		}

		// Prefer a real third-party connector over the built-in fallback.
		foreach ( $candidates as $connector ) {
			if ( 'native_form' !== $connector->get_id() ) {
				return $connector;
			}
		}

		return $candidates[0];
	}
}
