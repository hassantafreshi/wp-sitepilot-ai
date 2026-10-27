<?php
declare(strict_types=1);
namespace AISAAutoPilot\Support;

use AISAAutoPilot\Core\Settings;
use AISAAutoPilot\Logging\Logger;

class HandoffService {
	private PdfExporter $pdf;
	private Logger $logger;
	public function __construct() {
		$this->pdf    = new PdfExporter();
		$this->logger = new Logger();}

	/**
	 * Sends one visitor request to every enabled and configured channel.
	 *
	 * @param array<int,array<string,mixed>> $messages Plain-text transcript.
	 * @param array<string,string>           $contact  Validated details: name, email, phone, website (empty when not left).
	 */
	public function handoff( array $messages, array $contact = array(), string $pdf_url = '' ): array {
		$contact = $this->contact( $contact );
		$blog    = get_bloginfo( 'name' );
		$results = array();
		try {
			$text    = $this->pdf->export_text( $messages );
			$summary = 'Support Request: ' . $blog
				. "\n" . $this->contact_text( $contact )
				. "\nTime: " . gmdate( 'Y-m-d H:i T' )
				. "\nURL: " . get_site_url()
				. "\n\n--- Messages ---\n" . $text;

			// Try each configured channel independently
			$channels = array(
				'email'    => function () use ( $messages, $contact ) {
					return $this->try_email( $messages, $contact );
				},
				'telegram' => function () use ( $summary ) {
					return $this->try_telegram( $summary );},
				'twilio'   => function () use ( $summary, $pdf_url ) {
					return $this->try_twilio( $summary, $pdf_url );},
				'whatsapp' => function () use ( $summary ) {
					return $this->try_channel( 'whatsapp', new HandoffWhatsApp(), $summary );
				},
				'slack'    => function () use ( $summary ) {
					return $this->try_channel( 'slack', new HandoffSlack(), $summary );
				},
				'signal'   => function () use ( $summary ) {
					return $this->try_channel( 'signal', new HandoffSignal(), $summary );
				},
			);

			foreach ( $channels as $key => $fn ) {
				try {
					$results[ $key ] = $fn();} catch ( \Throwable $e ) {
					$results[ $key ] = array(
						'ok'    => false,
						'error' => $e->getMessage(),
					);}
			}
		} catch ( \Throwable $e ) {
			$this->logger->error( 'handoff_service', $e->getMessage() );
			return array(
				'ok'    => false,
				'error' => $e->getMessage(),
			);
		}
		$any_ok = ! empty( array_filter( $results, fn( $r )=> ! empty( $r['ok'] ) ) );
		return array(
			'ok'       => $any_ok,
			'channels' => $results,
		);
	}

	/**
	 * @param array<string,mixed> $contact
	 * @return array{name:string,email:string,phone:string,website:string}
	 */
	private function contact( array $contact ): array {
		return array(
			'name'    => (string) ( $contact['name'] ?? '' ),
			'email'   => (string) ( $contact['email'] ?? '' ),
			'phone'   => (string) ( $contact['phone'] ?? '' ),
			'website' => (string) ( $contact['website'] ?? '' ),
		);
	}

	/**
	 * The details a visitor left, one per line, for the chat and SMS channels. Each channel escapes for its own format.
	 *
	 * @param array{name:string,email:string,phone:string,website:string} $contact
	 */
	private function contact_text( array $contact ): string {
		$lines = array();
		foreach ( array(
			'name'    => 'Name',
			'email'   => 'Email',
			'phone'   => 'Mobile',
			'website' => 'Website',
		) as $key => $label ) {
			if ( '' !== $contact[ $key ] ) {
				$lines[] = $label . ': ' . $contact[ $key ];
			}
		}
		return array() !== $lines ? implode( "\n", $lines ) : 'Contact: not provided';
	}

	/**
	 * @param array<int,array<string,mixed>>                               $messages
	 * @param array{name:string,email:string,phone:string,website:string} $contact
	 */
	private function try_email( array $messages, array $contact ): array {
		if ( ! $this->channel_enabled( 'handoff_email_enabled' ) ) {
			return array(
				'ok'      => false,
				'skipped' => true,
				'reason'  => 'Email delivery disabled.',
			);
		}
		$h = new HandoffEmail();
		if ( ! $h->is_configured() ) {
			return array(
				'ok'      => false,
				'skipped' => true,
				'reason'  => 'Email not configured.',
			);
		}
		$blog          = get_bloginfo( 'name' );
		$messages_html = $h->build_messages_html( $messages );
		$full_html     = $h->build_template( $h->build_contact_html( $contact ) . $messages_html, $blog );
		// Replying to the notification reaches the visitor when they left an email address.
		return $h->send( $h->get_recipient(), "Support Request: {$blog}", $full_html, $contact['email'] );
	}
	private function try_telegram( string $text ): array {
		if ( ! $this->channel_enabled( 'handoff_telegram_enabled' ) ) {
			return array(
				'ok'      => false,
				'skipped' => true,
				'reason'  => 'Telegram delivery disabled.',
			);
		}
		$t = new HandoffTelegram();
		if ( ! $t->is_configured() ) {
			return array(
				'ok'      => false,
				'skipped' => true,
				'reason'  => 'Telegram not configured.',
			);}
		return $t->send( $text, 'html' );
	}

	private function try_twilio( string $text, string $pdf_url = '' ): array {
		if ( ! $this->channel_enabled( 'handoff_twilio_enabled' ) ) {
			return array(
				'ok'      => false,
				'skipped' => true,
				'reason'  => 'Twilio delivery disabled.',
			);
		}
		$tw = new HandoffTwilio();
		if ( ! $tw->is_configured() ) {
			return array(
				'ok'      => false,
				'skipped' => true,
				'reason'  => 'Twilio not configured.',
			);}
		return $tw->send( $text, $pdf_url );
	}

	/**
	 * A channel that takes the plain-text summary: skipped unless switched on and set up.
	 *
	 * @param HandoffWhatsApp|HandoffSlack|HandoffSignal $sender
	 */
	private function try_channel( string $channel, $sender, string $text ): array {
		if ( ! $this->channel_enabled( 'handoff_' . $channel . '_enabled' ) ) {
			return array(
				'ok'      => false,
				'skipped' => true,
				'reason'  => ucfirst( $channel ) . ' delivery disabled.',
			);
		}
		if ( ! $sender->is_configured() ) {
			return array(
				'ok'      => false,
				'skipped' => true,
				'reason'  => ucfirst( $channel ) . ' not configured.',
			);
		}

		return $sender->send( $text );
	}

	private function channel_enabled( string $key ): bool {
		$widget = Settings::get( 'widget', array() );
		return is_array( $widget ) && ! empty( $widget[ $key ] );
	}
}
