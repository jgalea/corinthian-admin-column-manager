<?php

namespace Corinthian\Rest;

use Corinthian\Columns\ColumnType;
use Corinthian\Columns\Sortable;
use Corinthian\Plugin;
use Corinthian\Screens\Screen;
use Corinthian\Sets\CodeSets;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class SetsController {

	public const NAMESPACE = 'corinthian/v1';

	public function __construct( private Plugin $plugin ) {}

	public function register(): void {
		$auth  = fn() => current_user_can( Plugin::capability() );
		$write = fn( WP_REST_Request $request ) => $this->can_write( $request );

		register_rest_route(
			self::NAMESPACE,
			'/screens',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'screens' ),
				'permission_callback' => $auth,
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/sets',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_sets' ),
					'permission_callback' => $auth,
					'args'                => array( 'screen' => array( 'type' => 'string' ) ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_set' ),
					'permission_callback' => $write,
				),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/sets/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_set' ),
					'permission_callback' => $auth,
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_set' ),
					'permission_callback' => $write,
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_set' ),
					'permission_callback' => $write,
				),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/sets/(?P<id>\d+)/duplicate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'duplicate_set' ),
				'permission_callback' => $write,
			)
		);
	}

	private function can_write( WP_REST_Request $request ): bool {
		if ( ! current_user_can( Plugin::capability() ) ) {
			return false;
		}
		$body    = (array) $request->get_json_params();
		$screens = array( (string) ( $body['screen'] ?? '' ) );
		if ( (int) $request['id'] > 0 ) {
			$screens[] = (string) $this->plugin->sets->find( (int) $request['id'] )?->screen;
		}
		foreach ( $screens as $screen ) {
			if ( str_starts_with( $screen, 'network-' ) && ! current_user_can( 'manage_network_options' ) ) {
				return false;
			}
		}
		return true;
	}

	public function screens(): WP_REST_Response {
		$out = array();
		foreach ( $this->plugin->screens->all() as $screen ) {
			$out[] = $this->describe_screen( $screen );
		}
		return new WP_REST_Response( $out );
	}

	/**
	 * @return array<string,mixed>
	 */
	public function describe_screen( Screen $screen ): array {
		$types = array();
		foreach ( $this->plugin->registry->for_screen( $screen ) as $type ) {
			if ( 'native' === $type->key() ) {
				continue;
			}
			$types[] = $this->describe_type( $type, $screen );
		}
		$native = array();
		foreach ( $screen->native_columns() as $key => $label ) {
			$native[] = array(
				'key'   => $key,
				'label' => $label,
			);
		}
		return array(
			'key'         => $screen->key(),
			'label'       => $screen->label(),
			'url'         => $screen->url(),
			'object_type' => $screen->object_type(),
			'native'      => $native,
			'types'       => $types,
			'sets'        => array_map( static fn( $s ) => $s->to_array(), $this->plugin->sets->for_screen( $screen->key() ) ),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function describe_type( ColumnType $type, Screen $screen ): array {
		$description = array(
			'key'      => $type->key(),
			'label'    => $type->label(),
			'group'    => $type->group(),
			'settings' => $type->settings_schema( $screen ),
			'sortable' => $type instanceof Sortable,
		);
		$filtered    = apply_filters( 'corinthian_type_description', $description, $type, $screen );
		return is_array( $filtered ) ? $filtered : $description;
	}

	public function list_sets( WP_REST_Request $request ): WP_REST_Response {
		$screen = (string) $request->get_param( 'screen' );
		$sets   = '' !== $screen ? $this->plugin->sets->for_screen( $screen ) : $this->plugin->sets->all();
		return new WP_REST_Response( array_map( static fn( $s ) => $s->to_array(), $sets ) );
	}

	public function get_set( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$set = $this->plugin->sets->find( (int) $request['id'] );
		return $set ? new WP_REST_Response( $set->to_array() ) : new WP_Error( 'corinthian_not_found', __( 'Column set not found.', 'corinthian-admin-column-manager' ), array( 'status' => 404 ) );
	}

	public function create_set( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$data = (array) $request->get_json_params();
		if ( ! $this->plugin->screens->get( (string) ( $data['screen'] ?? '' ) ) ) {
			return new WP_Error( 'corinthian_bad_screen', __( 'Unknown list screen.', 'corinthian-admin-column-manager' ), array( 'status' => 400 ) );
		}
		return new WP_REST_Response( $this->plugin->sets->save( $data )->to_array(), 201 );
	}

	public function update_set( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$existing = $this->plugin->sets->find( (int) $request['id'] );
		if ( ! $existing ) {
			return new WP_Error( 'corinthian_not_found', __( 'Column set not found.', 'corinthian-admin-column-manager' ), array( 'status' => 404 ) );
		}
		if ( CodeSets::is_code_id( $existing->id ) ) {
			return $this->readonly_error();
		}
		$data           = (array) $request->get_json_params() + $existing->to_array();
		$data['screen'] = $existing->screen;
		return new WP_REST_Response( $this->plugin->sets->save( $data, $existing->id )->to_array() );
	}

	public function delete_set( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id = (int) $request['id'];
		if ( ! $this->plugin->sets->find( $id ) ) {
			return new WP_Error( 'corinthian_not_found', __( 'Column set not found.', 'corinthian-admin-column-manager' ), array( 'status' => 404 ) );
		}
		if ( CodeSets::is_code_id( $id ) ) {
			return $this->readonly_error();
		}
		$this->plugin->sets->delete( $id );
		return new WP_REST_Response( array( 'deleted' => true ) );
	}

	public function duplicate_set( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$source = $this->plugin->sets->find( (int) $request['id'] );
		if ( ! $source ) {
			return new WP_Error( 'corinthian_not_found', __( 'Column set not found.', 'corinthian-admin-column-manager' ), array( 'status' => 404 ) );
		}
		$data       = (array) $request->get_json_params();
		$target_key = (string) ( $data['screen'] ?? $source->screen );
		$target     = $this->plugin->screens->get( $target_key );
		if ( ! $target ) {
			return new WP_Error( 'corinthian_bad_screen', __( 'Unknown list screen.', 'corinthian-admin-column-manager' ), array( 'status' => 400 ) );
		}

		$columns = array_map( static fn( $c ) => $c->to_array(), $source->columns );
		if ( $target_key !== $source->screen ) {
			// Column types and existing columns that don't exist on the other screen stay behind.
			$available = array_diff( array_map( static fn( ColumnType $t ) => $t->key(), $this->plugin->registry->for_screen( $target ) ), array( 'native' ) );
			$columns   = array_values( array_filter( $columns, static fn( $c ) => in_array( $c['type'], $available, true ) ) );
		}
		$settings = array_diff_key( $source->settings, array_flip( array( 'readonly', 'code_key', 'imported_from' ) ) );
		if ( $target_key !== $source->screen ) {
			// Formatting rules and shared segments that use a column left behind would point at nothing.
			$kept = array_column( $columns, 'id' );
			$uses = static function ( array $rules ) use ( $kept ): bool {
				return array() === array_filter( $rules, static fn( $rule ) => ! in_array( $rule['column'] ?? '', $kept, true ) );
			};
			if ( isset( $settings['formatting'] ) ) {
				$settings['formatting'] = array_values( array_filter( (array) $settings['formatting'], static fn( $rule ) => $uses( array( $rule ) ) ) );
			}
			if ( isset( $settings['segments'] ) ) {
				$settings['segments'] = array_values( array_filter( (array) $settings['segments'], static fn( $segment ) => $uses( (array) ( $segment['filter']['rules'] ?? array() ) ) ) );
			}
		}
		$title    = sanitize_text_field( (string) ( $data['title'] ?? '' ) );
		$last     = $this->plugin->sets->for_screen( $target_key );

		$copy = $this->plugin->sets->save(
			array(
				'screen'   => $target_key,
				'title'    => '' !== $title ? $title : sprintf(
					/* translators: %s: column set name */
					__( '%s (copy)', 'corinthian-admin-column-manager' ),
					$source->title
				),
				'columns'  => $columns,
				'settings' => $settings,
				'position' => $last ? max( array_map( static fn( $s ) => $s->position, $last ) ) + 1 : 0,
			)
		);
		return new WP_REST_Response( $copy->to_array(), 201 );
	}

	private function readonly_error(): WP_Error {
		return new WP_Error( 'corinthian_readonly', __( 'This column set is defined in code. Change it there, or duplicate it to get an editable copy.', 'corinthian-admin-column-manager' ), array( 'status' => 403 ) );
	}
}
