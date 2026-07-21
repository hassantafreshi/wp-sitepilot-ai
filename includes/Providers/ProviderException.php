<?php
declare(strict_types=1);

namespace SitePilot\Providers;

/**
 * Provider error carrying a stable, safe code (never contains secrets).
 */
final class ProviderException extends \RuntimeException {

	private string $error_code;

	public function __construct( string $error_code, string $message ) {
		parent::__construct( $message );
		$this->error_code = $error_code;
	}

	public function error_code(): string {
		return $this->error_code;
	}
}
