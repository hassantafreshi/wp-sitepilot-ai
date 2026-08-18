<?php
declare(strict_types=1);

namespace AISAAutoPilot\Providers;

/**
 * The outbound HTTP rules every provider shares.
 *
 * Provider URLs are administrator-configured, but a compromised setting must
 * still not turn the plugin into an SSRF primitive — so the guard lives in one
 * place and every provider transport goes through it.
 */
trait SafeHttp {

	/**
	 * @param array<string, string> $headers
	 * @param array<string, mixed>|null $body JSON body for POST; null for GET.
	 * @return array<string, mixed>|\WP_Error
	 */
	protected function remote_json( string $method, string $endpoint, array $headers, ?array $body, int $timeout ) {
		if ( ! $this->is_safe_endpoint( $endpoint ) ) {
			return new \WP_Error( 'aisaautopilot_unsafe_endpoint', __( 'The provider URL is not allowed.', 'ai-site-assistant-autopilot' ) );
		}

		$args = array(
			'timeout'            => $timeout,
			'headers'            => $headers,
			'redirection'        => 0,
			'reject_unsafe_urls' => true,
		);

		if ( 'POST' === $method ) {
			$args['body'] = wp_json_encode( null === $body ? array() : $body );

			return wp_remote_post( $endpoint, $args );
		}

		return wp_remote_get( $endpoint, $args );
	}

	/**
	 * HTTPS only, and never a URL that smuggles credentials in the authority.
	 */
	protected function is_safe_endpoint( string $endpoint ): bool {
		$parts = wp_parse_url( $endpoint );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) || ! empty( $parts['user'] ) || ! empty( $parts['pass'] ) ) {
			return false;
		}

		$scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );
		if ( 'https' === $scheme ) {
			return true;
		}

		/**
		 * Allow a plain-HTTP provider endpoint.
		 *
		 * Off by default. Only useful for a self-hosted model on a trusted
		 * private network; anything reachable from the internet must stay HTTPS.
		 *
		 * @param bool   $allow    Whether to permit the request.
		 * @param string $endpoint The full endpoint URL.
		 */
		return (bool) apply_filters( 'aisaautopilot_allow_insecure_provider_url', false, $endpoint );
	}

	/**
	 * Join a base URL and a path, keeping any query string the base carries.
	 *
	 * Azure OpenAI base URLs may end in `?api-version=…`, which a naive
	 * concatenation would bury in the middle of the path.
	 */
	protected static function join_endpoint( string $base, string $path ): string {
		$base  = rtrim( trim( $base ), '/' );
		$query = '';

		$mark = strpos( $base, '?' );
		if ( false !== $mark ) {
			$query = substr( $base, $mark );
			$base  = rtrim( substr( $base, 0, $mark ), '/' );
		}

		return $base . $path . $query;
	}
}
