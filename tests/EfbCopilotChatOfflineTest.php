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
	 * QA5-003: the model claimed an SMS code check, Turnstile, a 50-answer limit, a closing date and save-and-resume.
	 * None of them is something the copilot sets on a new form, so the reply is replaced and each one is named.
	 */
	public function test_a_new_form_reply_never_promises_what_was_not_built(): void {
		$claim = 'I built your sign-up form: SMS code verification for the mobile number, protected by Cloudflare Turnstile, at most 50 entries, closes on 31 December, and visitors can save and continue later.';
		$reply = self::chat(
			array(
				'action' => 'create_form',
				'reply'  => $claim,
				'form'   => array(
					'title'        => 'Workshop sign-up',
					'captcha'      => 'turnstile',
					'entry_limit'  => 50,
					'close_date'   => '2026-12-31',
					'save_resume'  => true,
					'fields'       => array(
						array( 'type' => 'text', 'label' => 'Name', 'required' => true ),
						array( 'type' => 'mobile', 'label' => 'Mobile', 'settings' => array( 'sms_verification' => true, 'placeholder' => '0912…' ) ),
						array( 'type' => 'email', 'label' => 'Email', 'settings' => array( 'email_otp' => true ) ),
					),
				),
			),
			3
		);

		$this->assertSame( 'preview', $reply['type'] ?? null, (string) wp_json_encode( $reply ) );
		$this->assertNotSame( $claim, $reply['reply'] );
		$this->assertStringContainsString( 'not everything you asked for is in it', (string) $reply['reply'] );
		$this->assertTrue( $reply['partial'] );
		$errors = implode( "\n", (array) $reply['errors'] );
		foreach ( array( '“Mobile” with a one-time code sent by SMS', '“Email” with a one-time code sent by e-mail', 'a CAPTCHA service', 'a limit on the number of answers', 'opening and closing dates', 'save and continue later' ) as $named ) {
			$this->assertStringContainsString( $named, $errors );
		}
		$this->assertStringContainsString( 'Easy Form Builder editor', $errors );
	}

	public function test_a_new_form_that_is_built_as_asked_keeps_the_models_reply(): void {
		$reply = self::chat(
			array(
				'action' => 'create_form',
				'reply'  => 'Here is your contact form.',
				'form'   => array(
					'title'  => 'Contact',
					'fields' => array(
						array( 'type' => 'text', 'label' => 'Name', 'required' => true, 'settings' => array( 'placeholder' => 'Your name' ) ),
						array( 'type' => 'email', 'label' => 'Email', 'required' => true ),
					),
				),
			),
			3
		);
		$this->assertSame( 'Here is your contact form.', $reply['reply'] ?? null, (string) wp_json_encode( $reply ) );
		$this->assertFalse( $reply['partial'] );
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
