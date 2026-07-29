<?php
/**
 * Shared test helper.
 *
 * @package WP-UserOnline
 */

/**
 * Reset the plugin's per-request static caches.
 *
 * These are request-scoped by design and correct in production, where each
 * request starts fresh and record() invalidates them. A PHPUnit run is one long
 * process, so they leak between tests unless cleared explicitly.
 */
trait WP_UserOnline_Reset_Statics {

	/**
	 * Clear every static cache the plugin keeps.
	 *
	 * @return void
	 */
	protected function reset_useronline_statics() {
		$targets = array(
			'WP_UserOnline_Recorder' => array( 'count' => null ),
			'WP_UserOnline_Template' => array(
				'cache'        => array(),
				'needs_script' => false,
			),
		);

		foreach ( $targets as $class => $properties ) {
			foreach ( $properties as $name => $value ) {
				$property = new ReflectionProperty( $class, $name );
				$property->setAccessible( true );
				$property->setValue( null, $value );
			}
		}
	}
}
