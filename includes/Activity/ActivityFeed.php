<?php
declare(strict_types=1);

namespace AISAAutoPilot\Activity;

use AISAAutoPilot\Logging\Logger;
use AISAAutoPilot\Security\Redactor;

/**
 * Read model for the single operational timeline. It intentionally consumes
 * only Logger entries: the log is redacted and survives the short-lived,
 * single-use ActionStore proposal transient.
 */
final class ActivityFeed {

	private Logger $logger;

	public function __construct( ?Logger $logger = null ) {
		$this->logger = $logger ?? new Logger();
	}

	/**
	 * @return array<int,array{time:string,level:string,event:string,message:string,trace_id:string,action_id:string,connector_id:string,post_id:int,product_id:int,form_id:int}>
	 */
	public function recent( int $limit = 100, string $level = '', string $search = '' ): array {
		$limit  = max( 1, min( 100, $limit ) );
		$level  = sanitize_key( $level );
		$search = mb_strtolower( sanitize_text_field( $search ) );
		$items  = array();

		foreach ( $this->logger->recent( 200 ) as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$entry_level = sanitize_key( $this->text( $entry['level'] ?? '' ) );
			// Events are dotted identifiers (e.g. "plan.created"), so keep the
			// period that sanitize_key() would otherwise strip.
			$event   = (string) preg_replace( '/[^a-z0-9_.\-]/', '', mb_strtolower( $this->text( $entry['event'] ?? '' ) ) );
			$message = $this->text( $entry['message'] ?? '' );
			$context = is_array( $entry['context'] ?? null ) ? $entry['context'] : array();
			if ( '' !== $level && $level !== $entry_level ) {
				continue;
			}
			$haystack = mb_strtolower( $message . ' ' . $event . ' ' . $this->text( $context['trace_id'] ?? '' ) );
			if ( '' !== $search && false === mb_strpos( $haystack, $search ) ) {
				continue;
			}
			$items[] = array(
				'time'         => $this->text( $entry['time'] ?? '' ),
				'level'        => $entry_level,
				'event'        => $event,
				'message'      => $message,
				'trace_id'     => $this->text( $context['trace_id'] ?? '' ),
				'action_id'    => $this->text( $context['action_id'] ?? '' ),
				'connector_id' => sanitize_key( $this->text( $context['connector_id'] ?? ( $context['connector'] ?? '' ) ) ),
				'post_id'      => $this->resource_id( $context['post_id'] ?? 0 ),
				'product_id'   => $this->resource_id( $context['product_id'] ?? 0 ),
				'form_id'      => $this->resource_id( $context['form_id'] ?? 0 ),
			);
			if ( count( $items ) >= $limit ) {
				break;
			}
		}

		return $items;
	}

	/** @param mixed $value Stored values can come from older plugin versions. */
	private function text( $value ): string {
		return is_scalar( $value ) ? sanitize_text_field( Redactor::text( (string) $value ) ) : '';
	}

	/** @param mixed $value Resource identifiers must be positive integers. */
	private function resource_id( $value ): int {
		if ( ! is_int( $value ) && ! is_string( $value ) ) {
			return 0;
		}
		return 1 === preg_match( '/^[1-9][0-9]*$/D', (string) $value ) ? max( 0, (int) $value ) : 0;
	}
}
