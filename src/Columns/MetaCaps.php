<?php

namespace Corinthian\Columns;

/**
 * Meta capability checks for field plugins that write through their own API.
 *
 * ACF denies the generic add/edit/delete_post_meta capabilities on any meta key
 * that matches the name of an ACF field anywhere on the site, so raw writes
 * can't skip its validation. Columns that save through ACF, Meta Box or Pods
 * go through those plugins' own validated APIs, as their edit screens do, so
 * the guard is lifted for the capability check only and always restored.
 */
final class MetaCaps {

	public static function can( string $cap, int $object_id, string $key ): bool {
		$priority = has_filter( 'map_meta_cap', 'acf_map_post_meta_caps' );
		if ( false !== $priority ) {
			remove_filter( 'map_meta_cap', 'acf_map_post_meta_caps', (int) $priority );
		}
		try {
			return current_user_can( $cap, $object_id, $key );
		} finally {
			if ( false !== $priority ) {
				// Re-added by name: ACF defines the function, so it exists whenever the filter was there.
				\call_user_func( 'add_filter', 'map_meta_cap', 'acf_map_post_meta_caps', (int) $priority, 4 );
			}
		}
	}
}
