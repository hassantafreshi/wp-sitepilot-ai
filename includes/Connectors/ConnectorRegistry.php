<?php
declare(strict_types=1);

namespace AISAAutoPilot\Connectors;

/**
 * Holds connectors and resolves a capability to the native, safe default.
 *
 * Advanced integrations must opt in through their own controllers. In
 * particular, a generic form request must never silently become an Easy Form
 * Builder write merely because EFB was activated after the request was made.
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
	 * The built-in connector is the deterministic default; a feature that wants
	 * Easy Form Builder must select its `easy_form_builder` ID explicitly.
	 */
	public function resolve( string $capability ): ?ConnectorInterface {
		$candidates = array();
		foreach ( $this->connectors as $connector ) {
			if ( in_array( $capability, $connector->get_capabilities(), true ) && $connector->is_available() ) {
				$candidates[] = $connector;
			}
		}

		if ( array() === $candidates ) {
			return null;
		}

		foreach ( $candidates as $connector ) {
			if ( 'native_form' === $connector->get_id() ) {
				return $connector;
			}
		}

		return $candidates[0];
	}
}
