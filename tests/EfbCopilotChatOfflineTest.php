<?php
declare(strict_types=1);

namespace AISAAutoPilot\Tests;

use PHPUnit\Framework\TestCase;

/**
 * EfbCopilotController::chat() end to end, with only the model's JSON scripted.
 *
 * Each case runs tests/efb_copilot_offline_server.php in its own PHP process, the
 * same server the browser checks use, so the reply is exactly what /efb/chat sends.
 */
final class EfbCopilotChatOfflineTest extends TestCase {

	/**
	 * Found with a real model: it adds the consent checkbox the prompt asks for,
	 * and the whole form was rejected as "unsupported type (trmcheckbox)".
	 */
	public function test_a_new_form_with_a_consent_checkbox_reaches_the_preview(): void {
		$reply = self::chat(
			array(
				'action' => 'create_form',
				'reply'  => 'Here is your contact form.',
				'form'   => array(
					'title'                 => 'Contact us',
					'notify_admin'          => true,
					'confirm_to_respondent' => true,
					'fields'                => array(
						array( 'type' => 'text', 'label' => 'Name', 'required' => true ),
						array( 'type' => 'email', 'label' => 'Email', 'required' => true ),
						array( 'type' => 'mobile', 'label' => 'Mobile' ),
						array( 'type' => 'trmCheckbox', 'label' => 'Consent', 'required' => true, 'options' => array( 'I agree that my details are stored.' ) ),
					),
				),
			),
			1
		);

		$this->assertSame( 'preview', $reply['type'] ?? null, (string) wp_json_encode( $reply ) );
		$this->assertContains( 'trmCheckbox', array_column( (array) $reply['preview']['fields'], 'type' ) );
	}

	public function test_a_rejected_new_form_does_not_repeat_the_model_claim(): void {
		$reply = self::chat(
			array(
				'action' => 'create_form',
				'reply'  => 'I have built your form.',
				'form'   => array(
					'title'  => 'Odd',
					'fields' => array( array( 'type' => 'hologram', 'label' => 'Projection' ) ),
				),
			),
			1
		);

		$this->assertSame( 'message', $reply['type'] ?? null );
		$this->assertStringNotContainsString( 'I have built your form', (string) $reply['reply'] );
		$this->assertNotEmpty( $reply['errors'] );
	}

	/**
	 * @param array<string,mixed> $ai
	 * @return array<string,mixed>
	 */
	private static function chat( array $ai, int $efb_plan ): array {
		$input   = (string) json_encode(
			array(
				'mode'     => 'chat',
				'efb_plan' => $efb_plan,
				'ai'       => $ai,
				'request'  => array(
					'message'  => 'Build the form.',
					'trace_id' => 'offline',
					'has_form' => false,
				),
			)
		);
		$process = proc_open(
			array( PHP_BINARY, __DIR__ . '/efb_copilot_offline_server.php' ),
			array(
				0 => array( 'pipe', 'r' ),
				1 => array( 'pipe', 'w' ),
				2 => array( 'pipe', 'w' ),
			),
			$pipes
		);
		self::assertIsResource( $process );
		fwrite( $pipes[0], $input );
		fclose( $pipes[0] );
		$out = (string) stream_get_contents( $pipes[1] );
		$err = (string) stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		proc_close( $process );

		$decoded = json_decode( $out, true );
		self::assertIsArray( $decoded, 'Offline server output: ' . $out . $err );

		return (array) $decoded['body'];
	}
}
