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

	public function handoff( array $messages, string $name = '', string $email = '', string $pdf_url = '' ): array {
		$blog    = get_bloginfo( 'name' );
		$results = array();
		try {
			$text    = $this->pdf->export_text( $messages );
			$summary = 'Support Request: ' . $blog
				. "\nName: {$name}\nEmail: " . ( $email ?: 'N/A' )
				. "\nTime: " . gmdate( 'Y-m-d H:i T' )
				. "\nURL: " . get_site_url()
				. "\n\n--- Messages ---\n" . $text;

			// Try each configured channel independently
			$channels = array(
				'email'    => function () use ( $messages, $email, $name ) {
					return $this->try_email( $messages, $email, $name );
				},
				'telegram' => function () use ( $summary ) {
					return $this->try_telegram( $summary );},
				'twilio'   => function () use ( $summary, $pdf_url ) {
					return $this->try_twilio( $summary, $pdf_url );},
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

	/** @param array<int,array<string,mixed>> $messages */
	private function try_email( array $messages, string $email, string $name ): array {
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
		$contact_html  = '';
		if ( '' !== $name || '' !== $email ) {
			$contact_html = '<p><strong>' . esc_html__( 'Visitor', 'ai-site-assistant-autopilot' ) . ':</strong> ' . esc_html( $name ?: __( 'Not provided', 'ai-site-assistant-autopilot' ) ) . '<br><strong>' . esc_html__( 'Email', 'ai-site-assistant-autopilot' ) . ':</strong> ' . esc_html( $email ?: __( 'Not provided', 'ai-site-assistant-autopilot' ) ) . '</p>';
		}
		$full_html = $h->build_template( $contact_html . $messages_html, $blog );
		return $h->send( $h->get_recipient(), "Support Request: {$blog}", $full_html );
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

	private function channel_enabled( string $key ): bool {
		$widget = Settings::get( 'widget', array() );
		return is_array( $widget ) && ! empty( $widget[ $key ] );
	}
}
