<?php
// Usage: wp eval-file clone-open.php <set-id>. Saves an unrestricted copy at position -1 and prints its id.
$repo = \Corinthian\Plugin::instance()->sets;
$set  = $repo->find( (int) ( $args[0] ?? 0 ) );
if ( ! $set ) {
	WP_CLI::error( 'No such set' );
}
$data                      = $set->to_array();
$data['settings']          = (array) $data['settings'];
$data['settings']['users'] = array();
$data['settings']['roles'] = array();
unset( $data['settings']['imported_from'] );
$data['title']    = 'Imported (open copy)';
$data['position'] = -1;
echo $repo->save( $data )->id;
