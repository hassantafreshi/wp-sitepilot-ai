<?php
declare(strict_types=1);

namespace SitePilot\Providers;

/**
 * Common contract for AI providers. In standalone mode the plugin calls the
 * provider directly; the same interface will later sit behind the cloud
 * Provider Manager without changing callers.
 */
interface ProviderInterface {

	/**
	 * Ask the model for a JSON object matching the given instructions.
	 *
	 * @param string $system_prompt Trusted system rules.
	 * @param string $user_prompt   Untrusted user request.
	 * @return array<string, mixed> Decoded JSON object.
	 *
	 * @throws ProviderException On transport, auth, or parse failure.
	 */
	public function generate_structured_output( string $system_prompt, string $user_prompt ): array;

	/**
	 * Ask the model for a plain-text response (used for chat / summarisation).
	 *
	 * @param string $system_prompt Trusted system rules.
	 * @param string $user_prompt   Untrusted user request.
	 * @return array{content:string, usage:array{prompt_tokens:int, completion_tokens:int, total_tokens:int}}
	 *
	 * @throws ProviderException On transport, auth, or parse failure.
	 */
	public function generate_text( string $system_prompt, string $user_prompt ): array;

	/**
	 * Produce an embedding vector for a piece of text (semantic search).
	 *
	 * @param string $text Text to embed.
	 * @return array{embedding:array<int,float>, usage:array{prompt_tokens:int, total_tokens:int}}
	 *
	 * @throws ProviderException When embeddings are unsupported or the call fails.
	 */
	public function generate_embedding( string $text ): array;

	/**
	 * Cheap call to verify the key/base/model work.
	 *
	 * @return array{ok:bool, message:string}
	 */
	public function health_check(): array;
}
