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

	/**
	 * Capabilities a connector may declare. `portal.link` is reserved for the Easy Form Builder customer portal
	 * connector (a separate plugin built on EFB's F20-core contract, `capabilities.portal_core`): it may only link a
	 * visitor or the admin to the portal page, never read entries or handle sign-in codes.
	 */
	public const CAPABILITIES = array( 'form.create', 'form.validate', 'portal.link' );

	/** Action fired once the built-in connectors are in, so another plugin can register its own. */
	public const REGISTER_ACTION = 'aisaautopilot_register_connectors';

	/** @var array<string,ConnectorBase> */
	private array $connectors = array();

	public function register( ConnectorBase $connector ): void {
		$id = $connector->get_id();
		// A built-in connector is never replaced by a later registration under the same id.
		if ( '' === $id || isset( $this->connectors[ $id ] ) ) {
			return;
		}
		$this->connectors[ $id ] = $connector;
	}

	/**
	 * The connectors that create things after approval (forms).
	 *
	 * @return ConnectorInterface[]
	 */
	public function all(): array {
		return array_values( array_filter( $this->connectors, static fn ( ConnectorBase $c ): bool => $c instanceof ConnectorInterface ) );
	}

	/**
	 * Every registered connector, including ones that only read or link.
	 *
	 * @return ConnectorBase[]
	 */
	public function all_connectors(): array {
		return array_values( $this->connectors );
	}

	/** A connector that creates things after approval, by id. */
	public function get( string $id ): ?ConnectorInterface {
		$connector = $this->connectors[ $id ] ?? null;

		return $connector instanceof ConnectorInterface ? $connector : null;
	}

	/** Any registered connector by id. */
	public function connector( string $id ): ?ConnectorBase {
		return $this->connectors[ $id ] ?? null;
	}

	/**
	 * Available connectors serving a capability, in registration order.
	 *
	 * @return ConnectorBase[]
	 */
	public function with_capability( string $capability ): array {
		return array_values(
			array_filter(
				$this->connectors,
				static fn ( ConnectorBase $c ): bool => in_array( $capability, $c->get_capabilities(), true ) && $c->is_available()
			)
		);
	}

	/**
	 * Resolve which connector should serve a capability on this site.
	 *
	 * The built-in connector is the deterministic default; a feature that wants
	 * Easy Form Builder must select its `easy_form_builder` ID explicitly.
	 */
	public function resolve( string $capability ): ?ConnectorInterface {
		$candidates = array();
		foreach ( $this->all() as $connector ) {
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
