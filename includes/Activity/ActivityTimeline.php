<?php
declare(strict_types=1);

namespace AISAAutoPilot\Activity;

/**
 * Mockup-shaped, truthful read model for the Activity Log.
 *
 * Logger entries remain the source of truth. This class only derives local
 * display labels and exposes a row action when a generated WordPress resource
 * still exists and the current administrator can edit it.
 */
final class ActivityTimeline {

	private ActivityFeed $feed;

	public function __construct( ?ActivityFeed $feed = null ) {
		$this->feed = $feed ?? new ActivityFeed();
	}

	/**
	 * @return array<int,array{day_key:string,label:string,summary:string,rows:array<int,array{time_iso:string,when:string,feature:string,summary:string,state:string,tone:string,action:array{label:string,url:string}|null}>}>
	 */
	public function groups( int $limit = 100, ?int $now = null ): array {
		$limit  = max( 1, min( 100, $limit ) );
		$now    = null === $now ? time() : max( 0, $now );
		$groups = array();

		foreach ( $this->feed->recent( $limit ) as $item ) {
			$timestamp = $this->timestamp( (string) ( $item['time'] ?? '' ) );
			$day       = $this->day( $timestamp, $now );
			$key       = $day['key'];
			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = array(
					'day_key' => $key,
					'label'   => $day['label'],
					'summary' => '',
					'rows'    => array(),
				);
			}

			$groups[ $key ]['rows'][] = $this->row( $item, $timestamp );
		}

		foreach ( $groups as $key => $group ) {
			$count                     = count( $group['rows'] );
			$groups[ $key ]['summary'] = sprintf(
				/* translators: %s: number of logged events. */
				_n( '%s recorded event', '%s recorded events', $count, 'ai-site-assistant-autopilot' ),
				number_format_i18n( $count )
			);
		}

		return array_values( $groups );
	}

	/**
	 * @param array{time:string,level:string,event:string,message:string,trace_id:string,action_id:string,connector_id:string,post_id:int,product_id:int,form_id:int} $item
	 * @return array{time_iso:string,when:string,feature:string,summary:string,state:string,tone:string,action:array{label:string,url:string}|null}
	 */
	private function row( array $item, int $timestamp ): array {
		$event   = (string) ( $item['event'] ?? '' );
		$status  = $this->status( (string) ( $item['level'] ?? '' ) );
		$message = (string) ( $item['message'] ?? '' );

		return array(
			'time_iso' => $timestamp > 0 ? gmdate( 'c', $timestamp ) : '',
			'when'     => $timestamp > 0 ? wp_date( 'H:i', $timestamp ) : '—',
			'feature'  => $this->feature( $event, (string) $item['connector_id'] ),
			'summary'  => '' !== $message ? $message : __( 'Activity recorded.', 'ai-site-assistant-autopilot' ),
			'state'    => $status['label'],
			'tone'     => $status['tone'],
			'action'   => $this->action( $event, $item ),
		);
	}

	/** @return array{key:string,label:string} */
	private function day( int $timestamp, int $now ): array {
		if ( $timestamp <= 0 ) {
			return array(
				'key'   => 'unavailable',
				'label' => __( 'Date unavailable', 'ai-site-assistant-autopilot' ),
			);
		}

		$day       = ( new \DateTimeImmutable( '@' . $timestamp ) )->setTimezone( wp_timezone() )->format( 'Y-m-d' );
		$local_now = ( new \DateTimeImmutable( '@' . $now ) )->setTimezone( wp_timezone() );
		if ( $day === $local_now->format( 'Y-m-d' ) ) {
			return array(
				'key'   => $day,
				'label' => __( 'Today', 'ai-site-assistant-autopilot' ),
			);
		}
		if ( $day === $local_now->modify( '-1 day' )->format( 'Y-m-d' ) ) {
			return array(
				'key'   => $day,
				'label' => __( 'Yesterday', 'ai-site-assistant-autopilot' ),
			);
		}

		return array(
			'key'   => $day,
			'label' => wp_date( (string) get_option( 'date_format' ), $timestamp ),
		);
	}

	/** @return array{label:string,tone:string} */
	private function status( string $level ): array {
		switch ( sanitize_key( $level ) ) {
			case 'error':
				return array(
					'label' => __( 'Needs attention', 'ai-site-assistant-autopilot' ),
					'tone'  => 'bad',
				);
			case 'warning':
				return array(
					'label' => __( 'Review', 'ai-site-assistant-autopilot' ),
					'tone'  => 'warn',
				);
			default:
				return array(
					'label' => __( 'Recorded', 'ai-site-assistant-autopilot' ),
					'tone'  => 'off',
				);
		}
	}

	private function feature( string $event, string $connector ): string {
		$event = strtolower( $event );
		if ( 0 === strpos( $event, 'action.' ) ) {
			if ( 'content_draft' === $connector ) {
				return __( 'Content Studio', 'ai-site-assistant-autopilot' );
			}
			if ( 'woocommerce_product_draft' === $connector ) {
				return __( 'Store Copilot', 'ai-site-assistant-autopilot' );
			}
			if ( ! in_array( $connector, array( 'native_form', 'easy_form_builder' ), true ) ) {
				return __( 'AutoPilot', 'ai-site-assistant-autopilot' );
			}
		}
		if ( 0 === strpos( $event, 'content.' ) ) {
			return __( 'Content Studio', 'ai-site-assistant-autopilot' );
		}
		if ( 0 === strpos( $event, 'woo.' ) || 0 === strpos( $event, 'wc_' ) ) {
			return __( 'Store Copilot', 'ai-site-assistant-autopilot' );
		}
		if ( 0 === strpos( $event, 'kb_' ) || 0 === strpos( $event, 'kb.' ) || 0 === strpos( $event, 'vector_' ) ) {
			return __( 'Site Knowledge', 'ai-site-assistant-autopilot' );
		}
		if ( 0 === strpos( $event, 'chat_' ) || 0 === strpos( $event, 'handoff_' ) || 0 === strpos( $event, 'voice_' ) ) {
			return __( 'Answer Desk', 'ai-site-assistant-autopilot' );
		}
		if ( 0 === strpos( $event, 'form.' ) || 0 === strpos( $event, 'efb.' ) || 0 === strpos( $event, 'action.' ) || 0 === strpos( $event, 'plan.' ) ) {
			return __( 'Form Composer', 'ai-site-assistant-autopilot' );
		}
		if ( 0 === strpos( $event, 'usage.' ) ) {
			return __( 'Usage & Cost', 'ai-site-assistant-autopilot' );
		}
		if ( 0 === strpos( $event, 'health.' ) ) {
			return __( 'Health', 'ai-site-assistant-autopilot' );
		}

		return __( 'AutoPilot', 'ai-site-assistant-autopilot' );
	}

	/**
	 * @param array{time:string,level:string,event:string,message:string,trace_id:string,action_id:string,connector_id:string,post_id:int,product_id:int,form_id:int} $item
	 * @return array{label:string,url:string}|null
	 */
	private function action( string $event, array $item ): ?array {
		$post_id = 0;
		if ( 'content.draft_created' === $event ) {
			$post_id = absint( $item['post_id'] ?? 0 );
		} elseif ( 'woo.draft.created' === $event ) {
			$post_id = absint( $item['product_id'] ?? 0 );
		}

		if ( $post_id <= 0 || ! current_user_can( 'edit_post', $post_id ) ) {
			return null;
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || '1' !== (string) get_post_meta( $post->ID, '_aisaautopilot_generated', true ) ) {
			return null;
		}
		$types = 'woo.draft.created' === $event ? array( 'product' ) : array( 'post', 'page' );
		if ( ! in_array( $post->post_type, $types, true ) || ! in_array( $post->post_status, array( 'draft', 'pending', 'future', 'private', 'publish' ), true ) ) {
			return null;
		}

		$url = get_edit_post_link( $post->ID, 'raw' );
		if ( ! is_string( $url ) || '' === $url ) {
			return null;
		}

		return array(
			'label' => 'draft' === $post->post_status ? __( 'Open draft', 'ai-site-assistant-autopilot' ) : __( 'Open in WordPress', 'ai-site-assistant-autopilot' ),
			'url'   => $url,
		);
	}

	private function timestamp( string $value ): int {
		// Logger persists ISO 8601 timestamps, never relative or timezone-less dates.
		if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/D', $value ) ) {
			return 0;
		}
		$date   = \DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i:sP', $value );
		$errors = \DateTimeImmutable::getLastErrors();
		if ( false === $date || ( is_array( $errors ) && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) ) ) {
			return 0;
		}
		return max( 0, $date->getTimestamp() );
	}
}
