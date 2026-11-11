<?php

namespace Corinthian\Rest;

use Corinthian\Admin\TableUi;
use Corinthian\Plugin;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Per-user column widths set by dragging column edges on a list table.
 */
final class WidthsController {

	public function __construct( private Plugin $plugin ) {}

	public function register(): void {
		register_rest_route(
			SetsController::NAMESPACE,
			'/widths',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save' ),
					'permission_callback' => 'is_user_logged_in',
					'args'                => array(
						'screen' => array(
							'type'     => 'string',
							'required' => true,
						),
						'set'    => array(
							'type'     => 'integer',
							'required' => true,
						),
						'widths' => array(
							'type'     => 'object',
							'required' => true,
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'reset' ),
					'permission_callback' => 'is_user_logged_in',
					'args'                => array(
						'screen' => array(
							'type'     => 'string',
							'required' => true,
						),
						'set'    => array(
							'type'     => 'integer',
							'required' => true,
						),
					),
				),
			)
		);
	}

	public function save( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$set = $this->visible_set( $request );
		if ( is_wp_error( $set ) ) {
			return $set;
		}
		$allowed = array();
		foreach ( $set->columns as $config ) {
			$allowed[ $config->table_key() ] = true;
		}
		$widths = TableUi::user_widths( $set->screen, $set->id, get_current_user_id() );
		foreach ( (array) $request->get_param( 'widths' ) as $key => $px ) {
			$key = (string) $key;
			if ( isset( $allowed[ $key ] ) && is_numeric( $px ) ) {
				$widths[ $key ] = max( TableUi::MIN_RESIZE, min( TableUi::MAX_RESIZE, (int) $px ) );
			}
		}
		update_user_meta( get_current_user_id(), TableUi::widths_meta_key( $set->screen, $set->id ), $widths );
		return new WP_REST_Response( array( 'widths' => (object) $widths ) );
	}

	public function reset( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$set = $this->visible_set( $request );
		if ( is_wp_error( $set ) ) {
			return $set;
		}
		delete_user_meta( get_current_user_id(), TableUi::widths_meta_key( $set->screen, $set->id ) );
		return new WP_REST_Response( array( 'widths' => (object) array() ) );
	}

	private function visible_set( WP_REST_Request $request ): \Corinthian\Sets\ColumnSet|WP_Error {
		$set    = $this->plugin->sets->find( (int) $request->get_param( 'set' ) );
		$screen = $set ? $this->plugin->screens->get( $set->screen ) : null;
		if ( ! $set || ! $screen || $set->screen !== (string) $request->get_param( 'screen' ) || ! $set->visible_to( wp_get_current_user() ) || ! $screen->user_can_list() ) {
			return new WP_Error( 'corinthian_not_found', __( 'Column set not found.', 'corinthian-admin-column-manager' ), array( 'status' => 404 ) );
		}
		return $set;
	}
}
