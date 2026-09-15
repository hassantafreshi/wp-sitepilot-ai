<?php
declare(strict_types=1);

namespace AISAAutoPilot\Tests;

use AISAAutoPilot\Efb\EfbChatSkill;
use AISAAutoPilot\Providers\ProviderInterface;
use PHPUnit\Framework\TestCase;

final class EfbChatSkillTest extends TestCase {

	public function test_selected_field_is_explicit_context_from_the_server_catalogue(): void {
		$provider = new CapturingEfbProvider();
		$skill    = new EfbChatSkill( $provider );
		$field    = array( 'id' => 'email', 'label' => 'Work email', 'type' => 'email' );

		$skill->respond( 'Make this required', array(), true, array( $field ), array(), array( 'tier' => 'free' ), $field );

		$this->assertStringContainsString( 'SELECTED_FIELD: id="email" label="Work email" type=email', $provider->user_prompt );
		$this->assertStringContainsString( 'CURRENT_FIELDS:', $provider->user_prompt );
	}

	public function test_selected_field_context_is_omitted_without_an_open_form(): void {
		$provider = new CapturingEfbProvider();
		$skill    = new EfbChatSkill( $provider );
		$field    = array( 'id' => 'email', 'label' => 'Email', 'type' => 'email' );

		$skill->respond( 'Build a form', array(), false, array(), array(), array(), $field );

		$this->assertStringNotContainsString( 'SELECTED_FIELD:', $provider->user_prompt );
	}
}

final class CapturingEfbProvider implements ProviderInterface {

	public string $user_prompt = '';

	public function generate_structured_output( string $system_prompt, string $user_prompt ): array {
		unset( $system_prompt );
		$this->user_prompt = $user_prompt;

		return array( 'action' => 'chat', 'reply' => 'OK' );
	}

	public function generate_text( string $system_prompt, string $user_prompt ): array {
		unset( $system_prompt, $user_prompt );

		return array(
			'content' => '',
			'usage'   => array( 'prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0 ),
		);
	}

	public function generate_embedding( string $text ): array {
		unset( $text );

		return array( 'embedding' => array(), 'usage' => array( 'prompt_tokens' => 0, 'total_tokens' => 0 ) );
	}

	public function health_check(): array {
		return array( 'ok' => true, 'message' => 'OK' );
	}
}
