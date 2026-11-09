<?php

namespace Corinthian\Rest;

use Corinthian\Columns\ColumnType;
use Corinthian\Columns\Sortable;
use Corinthian\Plugin;
use Corinthian\Screens\Screen;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class SetsController {

	public const NAMESPACE = 'corinthian/v1';

	public function __construct( private Plugin $plugin ) {}

	public function register(): void {
		$auth = fn() => current_user_can( Plugin::capability() );

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
					'permission_callback' => $auth,
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
					'permission_callback' => $auth,
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_set' ),
					'permission_callback' => $auth,
				),
			)
		);
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
		$data           = (array) $request->get_json_params() + $existing->to_array();
		$data['screen'] = $existing->screen;
		return new WP_REST_Response( $this->plugin->sets->save( $data, $existing->id )->to_array() );
	}

	public function delete_set( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id = (int) $request['id'];
		if ( ! $this->plugin->sets->find( $id ) ) {
			return new WP_Error( 'corinthian_not_found', __( 'Column set not found.', 'corinthian-admin-column-manager' ), array( 'status' => 404 ) );
		}
		$this->plugin->sets->delete( $id );
		return new WP_REST_Response( array( 'deleted' => true ) );
	}
}
