<?php
declare(strict_types=1);

namespace AISAAutoPilot\Skills;

/** Validates untrusted model output before a WordPress draft can be proposed. */
final class ContentPlanValidator {

	public function validate( array $raw ): ValidationResult {
		$content = isset( $raw['content'] ) && is_array( $raw['content'] ) ? $raw['content'] : $raw;
		$type    = sanitize_key( (string) ( $content['post_type'] ?? 'post' ) );
		$title   = $this->text( (string) ( $content['title'] ?? '' ), 180 );
		$body    = $this->html( (string) ( $content['body'] ?? '' ), 30000 );
		$errors  = array();

		if ( ! in_array( $type, ContentPlanSchema::POST_TYPES, true ) ) {
			$errors[] = 'Content type must be a post or page.';
		}
		if ( '' === $title ) {
			$errors[] = 'The draft needs a title.';
		}
		if ( '' === trim( wp_strip_all_tags( $body ) ) ) {
			$errors[] = 'The draft needs body content.';
		}
		if ( array() !== $errors ) {
			return ValidationResult::failure( $errors );
		}

		$risk = sanitize_key( (string) ( $raw['risk_level'] ?? 'medium' ) );
		if ( ! in_array( $risk, ContentPlanSchema::RISK_LEVELS, true ) ) {
			$risk = 'medium';
		}

		return ValidationResult::success(
			array(
				'intent'            => 'content_draft',
				'post_type'         => $type,
				'title'             => $title,
				'excerpt'           => $this->text( (string) ( $content['excerpt'] ?? '' ), 500 ),
				'body'              => $body,
				'meta_description'  => $this->text( (string) ( $content['meta_description'] ?? '' ), 320 ),
				'slug'              => sanitize_title( (string) ( $content['slug'] ?? $title ) ),
				'risk_level'        => $risk,
				'notes'             => $this->text( (string) ( $raw['notes'] ?? '' ), 500 ),
				'requires_approval' => true,
				'publish_permitted' => false,
			)
		);
	}

	private function text( string $value, int $limit ): string {
		$value = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $value ) ) ?? '' );
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $limit ) : substr( $value, 0, $limit );
	}

	private function html( string $value, int $limit ): string {
		$value = wp_kses_post( trim( $value ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $limit ) : substr( $value, 0, $limit );
	}
}
