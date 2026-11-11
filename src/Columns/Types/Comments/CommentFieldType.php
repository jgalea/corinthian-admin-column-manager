<?php

namespace Corinthian\Columns\Types\Comments;

use Corinthian\Columns\Config;
use Corinthian\Columns\Sortable;
use Corinthian\Screens\Screen;
use Corinthian\Columns\Filterable;
use Corinthian\Columns\Types\Users\FilterSql;
use Corinthian\Columns\Types\Users\FiltersField;

defined( 'ABSPATH' ) || exit;

/**
 * Columns that live on the comments table.
 */
class CommentFieldType extends AbstractCommentType implements Sortable, Filterable {

	use FiltersField;

	private const COLUMNS = array(
		'id'           => 'comment_ID',
		'author'       => 'comment_author',
		'author_email' => 'comment_author_email',
		'author_ip'    => 'comment_author_IP',
		'status'       => 'comment_approved',
		'type'         => 'comment_type',
	);

	public function label(): string {
		return match ( $this->field ) {
			'id'           => __( 'Comment ID', 'corinthian-admin-column-manager' ),
			'author'       => __( 'Author name', 'corinthian-admin-column-manager' ),
			'author_email' => __( 'Author email', 'corinthian-admin-column-manager' ),
			'author_ip'    => __( 'Author IP', 'corinthian-admin-column-manager' ),
			'status'       => __( 'Approval status', 'corinthian-admin-column-manager' ),
			default        => __( 'Comment type', 'corinthian-admin-column-manager' ),
		};
	}

	public function value( int $object_id, Config $config, Screen $screen ): mixed {
		$comment = $this->comment( $object_id );
		if ( ! $comment ) {
			return null;
		}
		switch ( $this->field ) {
			case 'id':
				return (int) $comment->comment_ID;
			case 'status':
				$status = wp_get_comment_status( $comment );
				return $status ? $status : null;
			case 'type':
				return '' === $comment->comment_type ? 'comment' : $comment->comment_type;
			default:
				return (string) $comment->{self::COLUMNS[ $this->field ]};
		}
	}

	protected function format( mixed $value, int $object_id, Config $config, Screen $screen ): string {
		$text = is_scalar( $value ) ? (string) $value : '';
		switch ( $this->field ) {
			case 'author_email':
				return $this->link( 'mailto:' . $text, $text );
			case 'status':
				$labels = array(
					'approved'   => __( 'Approved', 'corinthian-admin-column-manager' ),
					'unapproved' => __( 'Pending', 'corinthian-admin-column-manager' ),
					'spam'       => __( 'Spam', 'corinthian-admin-column-manager' ),
					'trash'      => __( 'Trash', 'corinthian-admin-column-manager' ),
				);
				return esc_html( $labels[ $text ] ?? $text );
			case 'type':
				$labels = array(
					'comment'   => __( 'Comment', 'corinthian-admin-column-manager' ),
					'pingback'  => __( 'Pingback', 'corinthian-admin-column-manager' ),
					'trackback' => __( 'Trackback', 'corinthian-admin-column-manager' ),
				);
				return esc_html( $labels[ $text ] ?? $text );
			default:
				return esc_html( $text );
		}
	}

	public function sort_clauses( array $clauses, Config $config, string $order, Screen $screen ): array {
		global $wpdb;
		$column = "{$wpdb->comments}." . self::COLUMNS[ $this->field ];
		return $this->order_by( $clauses, $column, $order, in_array( $this->field, array( 'id', 'status' ), true ) ? null : self::blank( $column ) );
	}

	protected function filter_spec( Config $config, Screen $screen ): ?array {
		global $wpdb;
		$expr = "{$wpdb->comments}." . self::COLUMNS[ $this->field ];
		switch ( $this->field ) {
			case 'id':
				return array(
					'kind' => FilterSql::NUMBER,
					'expr' => $expr,
				);
			case 'status':
				return array(
					'kind'    => FilterSql::ENUM,
					'expr'    => $expr,
					'options' => array(
						'approved'   => __( 'Approved', 'corinthian-admin-column-manager' ),
						'unapproved' => __( 'Pending', 'corinthian-admin-column-manager' ),
						'spam'       => __( 'Spam', 'corinthian-admin-column-manager' ),
						'trash'      => __( 'Trash', 'corinthian-admin-column-manager' ),
					),
					'map'     => array(
						'approved'   => array( '1' ),
						'unapproved' => array( '0' ),
						'spam'       => array( 'spam' ),
						'trash'      => array( 'trash', 'post-trashed' ),
					),
				);
			case 'type':
				$options = array(
					'comment'   => __( 'Comment', 'corinthian-admin-column-manager' ),
					'pingback'  => __( 'Pingback', 'corinthian-admin-column-manager' ),
					'trackback' => __( 'Trackback', 'corinthian-admin-column-manager' ),
				);
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$present = $wpdb->get_col( "SELECT DISTINCT comment_type FROM {$wpdb->comments} WHERE comment_type NOT IN ('', 'comment', 'pingback', 'trackback') ORDER BY comment_type LIMIT 100" );
				foreach ( $present as $type ) {
					$options[ (string) $type ] = (string) $type;
				}
				return array(
					'kind'    => FilterSql::ENUM,
					'expr'    => $expr,
					'options' => $options,
					'map'     => array( 'comment' => array( 'comment', '' ) ),
				);
			default:
				return array(
					'kind' => FilterSql::TEXT,
					'expr' => $expr,
				);
		}
	}
}
