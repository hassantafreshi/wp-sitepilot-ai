<?php
declare(strict_types=1);

namespace AISAAutoPilot\Providers;

/**
 * A provider that can report which models the current key may use.
 *
 * Kept separate from ProviderInterface because generating text and publishing a
 * catalogue are different capabilities: a private endpoint may do the first
 * without the second.
 */
interface ModelCatalogInterface {

	/**
	 * @return string[] Model identifiers.
	 * @throws ProviderException On transport, auth, or parse failure.
	 */
	public function list_models(): array;
}
