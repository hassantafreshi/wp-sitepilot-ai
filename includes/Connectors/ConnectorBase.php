<?php
declare(strict_types=1);

namespace AISAAutoPilot\Connectors;

/**
 * What every connector has, whatever it does: an id, a name, whether it can run here, the capabilities it serves and
 * its health. A connector that creates things after approval also implements ConnectorInterface; one that only reads
 * or links (the coming Easy Form Builder customer portal connector, `portal.link`) implements just this, so it can be
 * registered without changing the form connectors or their callers.
 */
interface ConnectorBase {

	public function get_id(): string;

	public function get_name(): string;

	/** Whether this connector can run on the current site right now. */
	public function is_available(): bool;

	/** @return string[] Capability strings, e.g. ['form.create'] or ['portal.link']. */
	public function get_capabilities(): array;

	/**
	 * @return array<string, mixed> At least available, version and notes.
	 */
	public function get_health(): array;
}
