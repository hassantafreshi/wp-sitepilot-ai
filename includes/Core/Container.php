<?php
declare(strict_types=1);

namespace SitePilot\Core;

/**
 * Tiny lazy service container. Not a full DI framework — just enough to share
 * singletons and keep classes decoupled.
 */
final class Container {

	/** @var array<string, object> */
	private array $instances = array();

	/** @var array<string, callable> */
	private array $factories = array();

	public function set( string $id, object $instance ): void {
		$this->instances[ $id ] = $instance;
	}

	public function factory( string $id, callable $factory ): void {
		$this->factories[ $id ] = $factory;
	}

	public function get( string $id ): object {
		if ( isset( $this->instances[ $id ] ) ) {
			return $this->instances[ $id ];
		}

		if ( isset( $this->factories[ $id ] ) ) {
			$this->instances[ $id ] = ( $this->factories[ $id ] )( $this );

			return $this->instances[ $id ];
		}

		// Default: construct with no args (all admin/api classes support this).
		$this->instances[ $id ] = new $id();

		return $this->instances[ $id ];
	}
}
