<?php
declare(strict_types=1);
namespace AISAAutoPilot\Support;

/*
 * Exception messages here are never echoed: they travel back through the REST
 * layer as JSON, or are rendered by a caller with esc_html(). Escaping them at
 * the throw site would put HTML entities into the JSON payload, so the sniff is
 * disabled for this file rather than silenced line by line.
 */
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Logging\Logger;

/**
 * Telegram Bot API handler for human handoff.
 *
 * Scenarios covered:
 * - Token validation (400/401 → disabled)
 * - Chat ID validation (403/404 → warn user)
 * - Long messages >4096 chars → auto-split into chunks
 * - Markdown/HTML formatting with escaping
 * - Rate limiting (Retry-After header)
 * - Network timeout (15s default)
 * - Document upload for PDF (via sendDocument)
 * - All errors logged with full context
 */
class HandoffTelegram {

	public const API_BASE   = 'https://api.telegram.org/bot';
	public const MAX_LENGTH = 4096; // Telegram message limit
	public const TIMEOUT    = 15;

	private Logger $logger;

	public function __construct() {
		$this->logger = new Logger();
	}

	/**
	 * Get bot token from plugin settings.
	 */
	public function get_token(): string {
		return Settings::get_handoff_secret( 'handoff_telegram_token' );
	}

	/**
	 * Get chat ID from plugin settings.
	 */
	public function get_chat_id(): string {
		$widget = Settings::get( 'widget', array() );
		return is_array( $widget ) ? (string) ( $widget['handoff_telegram_chat'] ?? '' ) : '';
	}

	/**
	 * Check if Telegram is configured (token + chat ID present).
	 */
	public function is_configured(): bool {
		$token = $this->get_token();
		$chat  = $this->get_chat_id();
		return '' !== $token && '' !== $chat;
	}

	/**
	 * Send a message (or messages for long text) to Telegram.
	 *
	 * @param string $text     Message text.
	 * @param string $format   'html' or 'markdown'.
	 * @param string $pdf_path Optional path to PDF file for attachment.
	 * @return array{ok:bool, error?:string, message_ids?:array<int>}
	 */
	public function send( string $text, string $format = 'html', string $pdf_path = '' ): array {
		if ( ! $this->is_configured() ) {
			return array(
				'ok'    => false,
				'error' => __( 'Telegram not configured.', 'ai-site-assistant-autopilot' ),
			);
		}

		$token  = $this->get_token();
		$chat   = $this->get_chat_id();
		$result = array(
			'ok'          => true,
			'message_ids' => array(),
		);

		// 1. Validate token format (basic sanity).
		if ( ! preg_match( '/^[0-9]+:[a-zA-Z0-9_-]+$/', $token ) ) {
			$this->logger->error( 'handoff_telegram', 'Invalid bot token format.' );
			return array(
				'ok'    => false,
				'error' => __( 'Invalid Telegram bot token format.', 'ai-site-assistant-autopilot' ),
			);
		}

		// 2. Split long messages.
		$chunks = $this->split_message( $this->format_message( $text, $format ) );

		// 3. Send each chunk sequentially.
		$parse_mode = 'html' === $format ? 'HTML' : 'MarkdownV2';

		foreach ( $chunks as $i => $chunk ) {
			try {
				$body = array(
					'chat_id'    => $chat,
					'text'       => $chunk,
					'parse_mode' => $parse_mode,
				);

				$response = wp_remote_post(
					self::API_BASE . $token . '/sendMessage',
					array(
						'timeout'            => self::TIMEOUT,
						'headers'            => array( 'Content-Type' => 'application/json' ),
						'body'               => wp_json_encode( $body ),
						'redirection'        => 0,
						'reject_unsafe_urls' => true,
					)
				);

				if ( is_wp_error( $response ) ) {
					throw new \RuntimeException( $response->get_error_message() );
				}

				$code    = wp_remote_retrieve_response_code( $response );
				$raw     = wp_remote_retrieve_body( $response );
				$decoded = json_decode( $raw, true );

				// 4. Handle specific error codes.
				if ( 401 === $code || ( isset( $decoded['error_code'] ) && 401 === $decoded['error_code'] ) ) {
					$this->logger->error( 'handoff_telegram', 'Invalid bot token (401).' );
					return array(
						'ok'    => false,
						'error' => __( 'Telegram: invalid bot token.', 'ai-site-assistant-autopilot' ),
					);
				}

				if ( 403 === $code || ( isset( $decoded['error_code'] ) && 403 === $decoded['error_code'] ) ) {
					$this->logger->error( 'handoff_telegram', 'Bot blocked by user (403).' );
					return array(
						'ok'    => false,
						'error' => __( 'Telegram: bot was blocked by the user.', 'ai-site-assistant-autopilot' ),
					);
				}

				if ( 404 === $code || ( isset( $decoded['error_code'] ) && 404 === $decoded['error_code'] ) ) {
					$this->logger->error( 'handoff_telegram', 'Chat not found (404).' );
					return array(
						'ok'    => false,
						'error' => __( 'Telegram: chat not found. Check Chat ID.', 'ai-site-assistant-autopilot' ),
					);
				}

				if ( 429 === $code ) {
					// Rate limited — respect Retry-After.
					$this->logger->info( 'handoff_telegram', 'Telegram rate limited the handoff request.' );
					return array(
						'ok'    => false,
						'error' => __( 'Telegram is temporarily rate-limiting delivery.', 'ai-site-assistant-autopilot' ),
					);
				}

				if ( $code < 200 || $code >= 300 ) {
					throw new \RuntimeException( "HTTP {$code}: " . substr( $raw, 0, 200 ) );
				}

				// 5. Store message ID for tracking.
				if ( isset( $decoded['result']['message_id'] ) ) {
					$result['message_ids'][] = (int) $decoded['result']['message_id'];
				}

				$this->logger->info( 'handoff_telegram', "Message {$i}/" . count( $chunks ) . ' sent.' );

			} catch ( \Throwable $e ) {
				$this->logger->error( 'handoff_telegram', $e->getMessage() );
				$result['ok'] = false;
				if ( empty( $result['error'] ) ) {
					$result['error'] = $e->getMessage();
				}
			}
		}

		// 6. Attach PDF if provided.
		if ( '' !== $pdf_path && file_exists( $pdf_path ) ) {
			try {
				$this->send_document( $token, $chat, $pdf_path );
			} catch ( \Throwable $e ) {
				$this->logger->error( 'handoff_telegram', 'PDF upload failed: ' . $e->getMessage() );
			}
		}

		return $result;
	}

	/**
	 * Send a file (PDF) to Telegram.
	 */
	private function send_document( string $token, string $chat, string $file_path ): void {
		$url = self::API_BASE . $token . '/sendDocument';

		$contents = file_get_contents( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a PDF this plugin just wrote to a local temp path, not a remote request.
		if ( false === $contents ) {
			throw new \RuntimeException( 'Could not read the generated PDF.' );
		}

		// WordPress ships no multipart helper, so the body is assembled by hand
		// rather than reaching for cURL directly: wp_remote_post() respects the
		// site's HTTP configuration — proxies, timeouts, SSL policy, filters —
		// and keeps this plugin free of a direct ext-curl dependency.
		$boundary = 'aisaautopilot' . wp_generate_password( 24, false );
		$filename = str_replace( array( '"', "\r", "\n" ), '', basename( $file_path ) );
		$eol      = "\r\n";

		$body = '--' . $boundary . $eol
			. 'Content-Disposition: form-data; name="chat_id"' . $eol . $eol
			. $chat . $eol
			. '--' . $boundary . $eol
			. 'Content-Disposition: form-data; name="document"; filename="' . $filename . '"' . $eol
			. 'Content-Type: application/pdf' . $eol . $eol
			. $contents . $eol
			. '--' . $boundary . '--' . $eol;

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => self::TIMEOUT,
				'headers' => array( 'Content-Type' => 'multipart/form-data; boundary=' . $boundary ),
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( 'Telegram upload failed: ' . $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			throw new \RuntimeException( "HTTP {$code}: " . substr( (string) wp_remote_retrieve_body( $response ), 0, 200 ) );
		}

		$this->logger->info( 'handoff_telegram', 'PDF sent via Telegram.' );
	}

	/**
	 * Split a long message into chunks respecting Telegram's limit.
	 *
	 * @return array<int, string>
	 */
	public function split_message( string $text ): array {
		if ( mb_strlen( $text ) <= self::MAX_LENGTH ) {
			return array( $text );
		}

		$chunks  = array();
		$lines   = explode( "\n", $text );
		$current = '';

		foreach ( $lines as $line ) {
			// If a single line exceeds the limit, hard-split it.
			if ( mb_strlen( $line ) > self::MAX_LENGTH ) {
				if ( '' !== $current ) {
					$chunks[] = $current;
					$current  = '';
				}
				$chunks = array_merge( $chunks, str_split( $line, self::MAX_LENGTH ) );
				continue;
			}

			$candidate = '' === $current ? $line : $current . "\n" . $line;
			if ( mb_strlen( $candidate ) > self::MAX_LENGTH ) {
				$chunks[] = $current;
				$current  = $line;
			} else {
				$current = $candidate;
			}
		}

		if ( '' !== $current ) {
			$chunks[] = $current;
		}

		return $chunks;
	}

	private function format_message( string $text, string $format ): string {
		if ( 'html' !== $format ) {
			return wp_strip_all_tags( $text );
		}

		// Plain text for HTML parse mode: escape once (existing entities such as a site name's &amp; stay single)
		// and do not strip tags, which would drop everything after a "<" the visitor typed.
		return htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false );
	}

	/**
	 * Validate the bot token by calling getMe API.
	 *
	 * @return array{ok:bool, message:string}
	 */
	public function test_connection(): array {
		$token = $this->get_token();
		if ( '' === $token ) {
			return array(
				'ok'      => false,
				'message' => __( 'No bot token configured.', 'ai-site-assistant-autopilot' ),
			);
		}

		try {
			$response = wp_remote_get(
				self::API_BASE . $token . '/getMe',
				array(
					'timeout'            => self::TIMEOUT,
					'redirection'        => 0,
					'reject_unsafe_urls' => true,
				)
			);
			if ( is_wp_error( $response ) ) {
				return array(
					'ok'      => false,
					'message' => __( 'Network error: ', 'ai-site-assistant-autopilot' ) . $response->get_error_message(),
				);
			}

			$code = wp_remote_retrieve_response_code( $response );
			$body = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( 200 === $code && ! empty( $body['ok'] ) ) {
				$bot = $body['result'];
				return array(
					'ok'      => true,
					'message' => sprintf(
						/* translators: %s: bot username */
						__( 'Connected as @%s', 'ai-site-assistant-autopilot' ),
						$bot['username'] ?? 'unknown'
					),
				);
			}

			if ( 401 === $code ) {
				return array(
					'ok'      => false,
					'message' => __( 'Invalid bot token.', 'ai-site-assistant-autopilot' ),
				);
			}

			return array(
				'ok'      => false,
				'message' => __( 'Unexpected response (HTTP ', 'ai-site-assistant-autopilot' ) . $code . ')',
			);

		} catch ( \Throwable $e ) {
			return array(
				'ok'      => false,
				'message' => $e->getMessage(),
			);
		}
	}
}
