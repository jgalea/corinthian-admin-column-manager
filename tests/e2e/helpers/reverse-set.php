<?php
// Usage: wp eval-file reverse-set.php <screen-key>
// Prints the screen's recorded native column keys (JSON), then saves a set with them reversed.
$key    = $args[0] ?? '';
$screen = \Corinthian\Plugin::instance()->screens->get( $key );
if ( ! $screen ) {
	WP_CLI::error( "Unknown screen $key" );
}
$native  = array_keys( $screen->native_columns() );
$columns = array();
foreach ( array_reverse( $native ) as $k ) {
	$columns[] = array( 'id' => sanitize_key( $k ) ?: 'c', 'type' => 'native', 'label' => '', 'settings' => array( 'key' => $k ) );
}
$repo = \Corinthian\Plugin::instance()->sets;
foreach ( $repo->for_screen( $key ) as $old ) {
	if ( 'Reversed' === $old->title ) {
		$repo->delete( $old->id ); // Replace, don't accumulate, across runs.
	}
}
$repo->save( array( 'screen' => $key, 'title' => 'Reversed', 'columns' => $columns ) );
echo wp_json_encode( $native );
