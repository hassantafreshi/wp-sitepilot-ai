<?php
declare(strict_types=1);

namespace AISAAutoPilot\Support;

use AISAAutoPilot\Chat\ConversationManager;
use AISAAutoPilot\Chat\VisitorConversationStore;
use AISAAutoPilot\Forms\FormOutbox;
use AISAAutoPilot\Forms\Turnstile;

/**
 * WordPress privacy tools (Tools > Export Personal Data / Erase Personal Data) and the
 * suggested privacy-policy text.
 *
 * A request names an email address. The plugin holds personal data in three places, matched
 * to that address as follows:
 * - handoff requests: the email address the visitor left;
 * - administrator console conversations: the WordPress user with that email address;
 * - visitor conversations (only when recording is on): visitors are anonymous, so only
 *   conversations in which that email address was typed are found.
 * Erasing deletes the matching rows: a handoff request or conversation without its person
 * has no use left.
 */
final class PrivacyTools {

	/** Rows per exporter page. */
	private const PAGE = 50;

	public function register(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'add_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'add_eraser' ) );
		add_action( 'admin_init', array( $this, 'add_policy_text' ) );
	}

	/**
	 * @param array<string, mixed> $exporters
	 * @return array<string, mixed>
	 */
	public function add_exporter( $exporters ): array {
		$exporters                  = is_array( $exporters ) ? $exporters : array();
		$exporters['aisaautopilot'] = array(
			'exporter_friendly_name' => __( 'AI Site Assistant AutoPilot', 'ai-site-assistant-autopilot' ),
			'callback'               => array( $this, 'export' ),
		);

		return $exporters;
	}

	/**
	 * @param array<string, mixed> $erasers
	 * @return array<string, mixed>
	 */
	public function add_eraser( $erasers ): array {
		$erasers                  = is_array( $erasers ) ? $erasers : array();
		$erasers['aisaautopilot'] = array(
			'eraser_friendly_name' => __( 'AI Site Assistant AutoPilot', 'ai-site-assistant-autopilot' ),
			'callback'             => array( $this, 'erase' ),
		);

		return $erasers;
	}

	/**
	 * Exporter callback. Every source is read on the first page; the sources are small and
	 * an export runs rarely, by an administrator.
	 *
	 * @return array{data: array<int, array<string, mixed>>, done: bool}
	 */
	public function export( string $email, int $page = 1 ): array {
		$email = $this->email( $email );
		if ( '' === $email ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		$items  = array();
		$offset = ( max( 1, $page ) - 1 ) * self::PAGE;
		$rows   = $this->handoff_rows( $email, $offset, self::PAGE );
		foreach ( $rows as $row ) {
			$items[] = array(
				'group_id'          => 'aisaautopilot-handoffs',
				'group_label'       => __( 'Requests to talk to a person', 'ai-site-assistant-autopilot' ),
				'group_description' => __( 'Requests a visitor sent from the site assistant to reach a member of the team.', 'ai-site-assistant-autopilot' ),
				'item_id'           => 'aisaautopilot-handoff-' . $row['id'],
				'data'              => array_values(
					array_filter(
						array(
							$this->pair( __( 'Name', 'ai-site-assistant-autopilot' ), (string) $row['visitor_name'] ),
							$this->pair( __( 'Email address', 'ai-site-assistant-autopilot' ), (string) $row['visitor_email'] ),
							$this->pair( __( 'Mobile number', 'ai-site-assistant-autopilot' ), (string) $row['visitor_phone'] ),
							$this->pair( __( 'Website address', 'ai-site-assistant-autopilot' ), (string) $row['visitor_website'] ),
							$this->pair( __( 'Status', 'ai-site-assistant-autopilot' ), (string) $row['status'] ),
							$this->pair( __( 'Received', 'ai-site-assistant-autopilot' ), (string) $row['created_at'] ),
							$this->pair( __( 'Conversation', 'ai-site-assistant-autopilot' ), $this->transcript( (string) $row['messages'] ) ),
						)
					)
				),
			);
		}

		// The other two sources are only read once, with the first page.
		if ( 1 === max( 1, $page ) ) {
			foreach ( $this->console_rows( $email ) as $row ) {
				$items[] = $this->conversation_item( 'aisaautopilot-console', __( 'Site assistant console conversations', 'ai-site-assistant-autopilot' ), __( 'Conversations this administrator had with the knowledge base console in wp-admin.', 'ai-site-assistant-autopilot' ), $row, (string) $row['title'] );
			}
			foreach ( $this->visitor_rows( $email ) as $row ) {
				$items[] = $this->conversation_item( 'aisaautopilot-visitor-chats', __( 'Site assistant conversations', 'ai-site-assistant-autopilot' ), __( 'Recorded visitor conversations in which this email address was written.', 'ai-site-assistant-autopilot' ), $row, '' );
			}
			foreach ( ( new FormOutbox() )->rows_for_email( $email ) as $row ) {
				$items[] = array(
					'group_id'          => 'aisaautopilot-form-outbox',
					'group_label'       => __( 'Undelivered form submissions', 'ai-site-assistant-autopilot' ),
					'group_description' => __( 'Form answers kept on this site because the email to the team could not be sent.', 'ai-site-assistant-autopilot' ),
					'item_id'           => 'aisaautopilot-form-outbox-' . $row['id'],
					'data'              => array_values(
						array_filter(
							array(
								$this->pair( __( 'Form', 'ai-site-assistant-autopilot' ), $row['form_title'] ),
								$this->pair( __( 'Received', 'ai-site-assistant-autopilot' ), $row['created_at'] ),
								$this->pair( __( 'Answers', 'ai-site-assistant-autopilot' ), $row['body'] ),
							)
						)
					),
				);
			}
		}

		return array(
			'data' => $items,
			'done' => count( $rows ) < self::PAGE,
		);
	}

	/**
	 * Eraser callback. Rows are deleted, so each call starts again from the first match.
	 *
	 * @return array{items_removed: bool, items_retained: bool, messages: array<int, string>, done: bool}
	 */
	public function erase( string $email, int $page = 1 ): array {
		unset( $page );
		$email   = $this->email( $email );
		$removed = 0;
		if ( '' !== $email ) {
			$db = $this->db();
			foreach ( $this->handoff_rows( $email, 0, self::PAGE ) as $row ) {
				$removed += (int) $db->delete( $db->prefix . HandoffInbox::TABLE, array( 'id' => (int) $row['id'] ), array( '%d' ) );
			}
			foreach ( $this->console_rows( $email ) as $row ) {
				$removed += (int) $db->delete( $db->prefix . ConversationManager::TABLE, array( 'id' => (int) $row['id'] ), array( '%d' ) );
			}
			foreach ( $this->visitor_rows( $email ) as $row ) {
				$removed += (int) $db->delete( $db->prefix . VisitorConversationStore::TABLE, array( 'id' => (int) $row['id'] ), array( '%d' ) );
			}
			$outbox = new FormOutbox();
			foreach ( $outbox->rows_for_email( $email ) as $row ) {
				$removed += $outbox->delete( $row['id'] ) ? 1 : 0;
			}
		}

		return array(
			'items_removed'  => $removed > 0,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => '' === $email || array() === $this->handoff_rows( $email, 0, 1 ),
		);
	}

	/**
	 * Offers the suggested text under Settings > Privacy. WordPress accepts it only in
	 * wp-admin, from admin_init on.
	 */
	public function add_policy_text(): void {
		if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
			wp_add_privacy_policy_content( __( 'AI Site Assistant AutoPilot', 'ai-site-assistant-autopilot' ), self::policy_text() );
		}
	}

	/** The suggested privacy-policy text, which the site owner copies into their policy. */
	public static function policy_text(): string {
		$paragraphs = array(
			__( 'This site uses an AI assistant to answer questions about its content. When you ask a question in the chat, the question, the previous few messages of the conversation and the matching passages of this site\'s public pages are sent to the AI service the site uses, to write the answer. Voice messages you choose to send are sent to that service to be transcribed.', 'ai-site-assistant-autopilot' ),
			__( 'Conversations are only kept on this site when the site owner has turned recording on; the chat then says so. Questions the assistant could not answer are kept without any information about who asked, so the site can add the missing content.', 'ai-site-assistant-autopilot' ),
			__( 'If you ask to talk to a person, the conversation and the contact details you enter (such as your name, email address, mobile number or website) are stored on this site and sent to the team through the channels the site uses, which may include email, Telegram, WhatsApp, SMS, Slack or Signal.', 'ai-site-assistant-autopilot' ),
			self::retention_text(),
			FormOutbox::enabled()
				? __( 'Answers sent with a form on this site are emailed to the team. If that email cannot be sent, the answers are kept on this site until it is sent, and deleted automatically after the period above at the latest.', 'ai-site-assistant-autopilot' )
				: '',
			Turnstile::enabled()
				? __( 'Forms on this site use Cloudflare Turnstile to keep out spam. When you open or send a form, Cloudflare receives your IP address and information about your browser to check that you are a person; see Cloudflare\'s privacy policy.', 'ai-site-assistant-autopilot' )
				: '',
			__( 'You can ask for a copy of these requests and conversations, or for their deletion, by the email address you gave.', 'ai-site-assistant-autopilot' ),
		);

		return '<p>' . implode( '</p><p>', array_map( 'esc_html', array_filter( $paragraphs ) ) ) . '</p>';
	}

	/** How long each kind is kept, from the periods saved in Advanced settings. */
	private static function retention_text(): string {
		$periods = array_map(
			static function ( int $days ): string {
				return $days > 0
					/* translators: %s: number of days. */
					? sprintf( _n( '%s day', '%s days', $days, 'ai-site-assistant-autopilot' ), number_format_i18n( $days ) )
					: __( 'until the site owner deletes them', 'ai-site-assistant-autopilot' );
			},
			Retention::periods()
		);

		$text = sprintf(
			/* translators: 1: recorded conversations, 2: requests to talk to a person, 3: unanswered questions; each "30 days" or "until the site owner deletes them". */
			__( 'Stored data is deleted automatically after: recorded conversations, %1$s; requests to talk to a person, %2$s; unanswered questions, %3$s.', 'ai-site-assistant-autopilot' ),
			$periods['conversations_days'],
			$periods['handoffs_days'],
			$periods['questions_days']
		);
		if ( FormOutbox::enabled() ) {
			$text .= ' ' . sprintf(
				/* translators: %s: "30 days" or "until the site owner deletes them". */
				__( 'Undelivered form submissions: %s.', 'ai-site-assistant-autopilot' ),
				$periods['undelivered_days']
			);
		}

		return $text;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function handoff_rows( string $email, int $offset, int $limit ): array {
		$db    = $this->db();
		$table = $db->prefix . HandoffInbox::TABLE;
		if ( ! $this->table_exists( $table ) ) {
			return array();
		}

		return (array) $db->get_results(
			$db->prepare(
				"SELECT id, status, visitor_name, visitor_email, visitor_phone, visitor_website, messages, created_at FROM {$table} WHERE LOWER(visitor_email) = %s ORDER BY id ASC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$email,
				$limit,
				$offset
			),
			ARRAY_A
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function console_rows( string $email ): array {
		$user  = get_user_by( 'email', $email );
		$db    = $this->db();
		$table = $db->prefix . ConversationManager::TABLE;
		if ( ! $user || ! $this->table_exists( $table ) ) {
			return array();
		}

		return (array) $db->get_results(
			$db->prepare( "SELECT id, title, messages, created_at FROM {$table} WHERE user_id = %d ORDER BY id ASC", (int) $user->ID ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function visitor_rows( string $email ): array {
		$db    = $this->db();
		$table = $db->prefix . VisitorConversationStore::TABLE;
		if ( ! $this->table_exists( $table ) ) {
			return array();
		}

		return (array) $db->get_results(
			$db->prepare( "SELECT id, messages, created_at FROM {$table} WHERE LOWER(messages) LIKE %s ORDER BY id ASC", '%' . $db->esc_like( $email ) . '%' ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	private function conversation_item( string $group, string $label, string $description, array $row, string $title ): array {
		return array(
			'group_id'          => $group,
			'group_label'       => $label,
			'group_description' => $description,
			'item_id'           => $group . '-' . (int) $row['id'],
			'data'              => array_values(
				array_filter(
					array(
						$this->pair( __( 'Title', 'ai-site-assistant-autopilot' ), $title ),
						$this->pair( __( 'Started', 'ai-site-assistant-autopilot' ), (string) $row['created_at'] ),
						$this->pair( __( 'Conversation', 'ai-site-assistant-autopilot' ), $this->transcript( (string) $row['messages'] ) ),
					)
				)
			),
		);
	}

	/** Stored messages as "Visitor: …" / "Assistant: …" lines. */
	private function transcript( string $json ): string {
		$messages = json_decode( $json, true );
		$lines    = array();
		foreach ( is_array( $messages ) ? $messages : array() as $message ) {
			if ( ! is_array( $message ) ) {
				continue;
			}
			$text = trim( (string) ( $message['content'] ?? $message['text'] ?? '' ) );
			if ( '' !== $text ) {
				$lines[] = ( 'assistant' === ( $message['role'] ?? '' ) ? __( 'Assistant', 'ai-site-assistant-autopilot' ) : __( 'Visitor', 'ai-site-assistant-autopilot' ) ) . ': ' . $text;
			}
		}

		return implode( "\n", $lines );
	}

	/** @return array{name:string, value:string}|null */
	private function pair( string $name, string $value ): ?array {
		return '' === trim( $value ) ? null : array(
			'name'  => $name,
			'value' => $value,
		);
	}

	private function email( string $email ): string {
		$email = strtolower( trim( sanitize_email( $email ) ) );
		return is_email( $email ) ? $email : '';
	}

	private function table_exists( string $table ): bool {
		$db = $this->db();
		return $table === $db->get_var( $db->prepare( 'SHOW TABLES LIKE %s', $db->esc_like( $table ) ) );
	}

	private function db(): \wpdb {
		return $GLOBALS['wpdb'];
	}
}
