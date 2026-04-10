<?php
/**
 * HFE Analytics Events helper for one-time milestone tracking.
 *
 * Tracks events temporarily, sends them once via BSF Analytics,
 * then cleans up. Only a minimal dedup flag remains.
 *
 * @package header-footer-elementor
 * @since 2.8.6
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'HFE_Analytics_Events' ) ) {

	/**
	 * HFE Analytics Events Class.
	 *
	 * @since 2.8.6
	 */
	class HFE_Analytics_Events {

		/**
		 * Track a one-time event. Skips if already tracked or pending.
		 *
		 * @param string $event_name  Event identifier.
		 * @param string $event_value Primary value (version, mode, etc.).
		 * @param array  $properties  Additional context as key-value pairs.
		 * @since 2.8.6
		 * @return void
		 */
		public static function track( $event_name, $event_value = '', $properties = [] ) {
			// Check dedup flag — already sent in a previous cycle.
			$pushed = get_option( 'hfe_usage_events_pushed', [] );
			$pushed = is_array( $pushed ) ? $pushed : [];
			if ( in_array( $event_name, $pushed, true ) ) {
				return;
			}

			// Check if already queued in current cycle.
			$pending = get_option( 'hfe_usage_events_pending', [] );
			$pending = is_array( $pending ) ? $pending : [];
			if ( in_array( $event_name, array_column( $pending, 'event_name' ), true ) ) {
				return;
			}

			// Add to pending queue.
			$pending[] = [
				'event_name'  => sanitize_text_field( $event_name ),
				'event_value' => sanitize_text_field( (string) $event_value ),
				'properties'  => ! empty( $properties ) ? $properties : new \stdClass(),
				'date'        => current_time( 'mysql' ),
			];
			update_option( 'hfe_usage_events_pending', $pending, false );
		}

		/**
		 * Flush pending events: returns them for the payload, then cleans up.
		 *
		 * After this call:
		 * - hfe_usage_events_pending is EMPTY (full event data deleted).
		 * - hfe_usage_events_pushed has event_name strings added (minimal dedup).
		 *
		 * @since 2.8.6
		 * @return array Pending events to include in payload. Empty if none.
		 */
		public static function flush_pending() {
			$pending = get_option( 'hfe_usage_events_pending', [] );
			if ( empty( $pending ) || ! is_array( $pending ) ) {
				return [];
			}

			// Add event names to dedup flag (minimal — just strings).
			$pushed = get_option( 'hfe_usage_events_pushed', [] );
			$pushed = is_array( $pushed ) ? $pushed : [];
			$pushed = array_unique(
				array_merge( $pushed, array_column( $pending, 'event_name' ) )
			);
			update_option( 'hfe_usage_events_pushed', $pushed, false );

			// DELETE all temporary event data.
			update_option( 'hfe_usage_events_pending', [], false );

			return $pending;
		}

		/**
		 * Check if an event has already been tracked (sent or pending).
		 *
		 * @param string $event_name Event identifier.
		 * @since 2.8.6
		 * @return bool
		 */
		public static function is_tracked( $event_name ) {
			$pushed = get_option( 'hfe_usage_events_pushed', [] );
			$pushed = is_array( $pushed ) ? $pushed : [];
			if ( in_array( $event_name, $pushed, true ) ) {
				return true;
			}

			$pending = get_option( 'hfe_usage_events_pending', [] );
			$pending = is_array( $pending ) ? $pending : [];
			return in_array( $event_name, array_column( $pending, 'event_name' ), true );
		}
	}
}
