<?php
declare(strict_types=1);

namespace SitePilot\Skills;

/**
 * Result of validating an AI-produced form plan.
 */
final class ValidationResult {

	/** @param array<string, mixed> $plan @param string[] $errors */
	private function __construct(
		public readonly bool $ok,
		public readonly array $plan,
		public readonly array $errors
	) {}

	/** @param array<string, mixed> $plan */
	public static function success( array $plan ): self {
		return new self( true, $plan, array() );
	}

	/** @param string[] $errors */
	public static function failure( array $errors ): self {
		return new self( false, array(), $errors );
	}
}
