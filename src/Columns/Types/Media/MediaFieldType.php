<?php

namespace Corinthian\Columns\Types\Media;

use Corinthian\Columns\Config;
use Corinthian\Columns\Sortable;
use Corinthian\Screens\Screen;
use Corinthian\Columns\Filterable;
use Corinthian\Columns\Types\Users\FilterSql;
use Corinthian\Columns\Types\Users\FiltersField;

/**
 * Attachment columns that sort in SQL: file name, MIME type, alt text, parent and uploader.
 */
class MediaFieldType extends AbstractMediaType implements Sortable, Filterable {

	use FiltersField;

	public function label(): string {
		return match ( $this->field ) {
			'file_name'   => __( 'File name', 'corinthian-admin-column-manager' ),
			'mime_type'   => __( 'MIME type', 'corinthian-admin-column-manager' ),
			'alt'         => __( 'Alt text', 'corinthian-admin-column-manager' ),
			'attached_to' => __( 'Attached to', 'corinthian-admin-column-manager' ),
			default       => __( 'Uploaded by', 'corinthian-admin-column-manager' ),
		};
	}

	public function group(): string {
		return in_array( $this->field, array( 'file_name', 'mime_type' ), true ) ? __( 'File', 'corinthian-admin-column-manager' ) : parent::group();
	}

	public function value( int $object_id, Config $config, Screen $screen ): mixed {
		$post = get_post( $object_id );
		if ( ! $post ) {
			return null;
		}
		switch ( $this->field ) {
			case 'file_name':
				$file = wp_basename( (string) get_post_meta( $object_id, '_wp_attached_file', true ) );
				return '' !== $file ? $file : null;
			case 'mime_type':
				return $post->post_mime_type;
			case 'alt':
				return (string) get_post_meta( $object_id, '_wp_attachment_image_alt', true );
			case 'attached_to':
				if ( ! $post->post_parent || ! get_post( $post->post_parent ) ) {
					return null;
				}
				$title = get_the_title( $post->post_parent );
				return '' !== $title ? $title : '#' . $post->post_parent;
			default:
				$author = (int) $post->post_author ? get_userdata( (int) $post->post_author ) : false;
				return $author ? $author->display_name : null;
		}
	}

	protected function format( mixed $value, int $object_id, Config $config, Screen $screen ): string {
		$text = is_scalar( $value ) ? (string) $value : '';
		$post = get_post( $object_id );
		if ( 'attached_to' === $this->field && $post ) {
			$url = get_edit_post_link( $post->post_parent, 'raw' );
			return $url ? $this->link( $url, $text ) : esc_html( $text );
		}
		if ( 'uploaded_by' === $this->field && $post ) {
			return $this->link( get_edit_user_link( (int) $post->post_author ), $text );
		}
		return esc_html( $text );
	}

	public function sort_clauses( array $clauses, Config $config, string $order, Screen $screen ): array {
		global $wpdb;
		switch ( $this->field ) {
			case 'file_name':
				$sub  = $this->meta_subselect( $wpdb->postmeta, 'post_id', "{$wpdb->posts}.ID", '_wp_attached_file' );
				$expr = "SUBSTRING_INDEX({$sub}, '/', -1)";
				return $this->order_by( $clauses, $expr, $order, self::blank( $expr ) );
			case 'mime_type':
				$expr = "{$wpdb->posts}.post_mime_type";
				return $this->order_by( $clauses, $expr, $order, self::blank( $expr ) );
			case 'alt':
				$expr = $this->meta_subselect( $wpdb->postmeta, 'post_id', "{$wpdb->posts}.ID", '_wp_attachment_image_alt' );
				return $this->order_by( $clauses, $expr, $order, self::blank( $expr ) );
			case 'attached_to':
				$expr = "(SELECT par.post_title FROM {$wpdb->posts} par WHERE par.ID = {$wpdb->posts}.post_parent AND {$wpdb->posts}.post_parent > 0)";
				return $this->order_by( $clauses, $expr, $order, self::blank( $expr ) );
			default:
				$expr = "(SELECT usr.display_name FROM {$wpdb->users} usr WHERE usr.ID = {$wpdb->posts}.post_author)";
				return $this->order_by( $clauses, $expr, $order, self::blank( $expr ) );
		}
	}

	protected function filter_spec( Config $config, Screen $screen ): ?array {
		global $wpdb;
		switch ( $this->field ) {
			case 'file_name':
				return array(
					'kind' => FilterSql::TEXT,
					'meta' => array(
						'table'     => $wpdb->postmeta,
						'id_column' => 'post_id',
						'outer_id'  => "{$wpdb->posts}.ID",
						'key'       => '_wp_attached_file',
						'template'  => "SUBSTRING_INDEX(__V__, '/', -1)",
					),
				);
			case 'alt':
				return array(
					'kind' => FilterSql::TEXT,
					'meta' => array(
						'table'     => $wpdb->postmeta,
						'id_column' => 'post_id',
						'outer_id'  => "{$wpdb->posts}.ID",
						'key'       => '_wp_attachment_image_alt',
					),
				);
			case 'mime_type':
				$options = array();
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				foreach ( $wpdb->get_col( "SELECT DISTINCT post_mime_type FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type <> '' ORDER BY post_mime_type LIMIT 500" ) as $mime ) {
					$options[ (string) $mime ] = (string) $mime;
				}
				return array(
					'kind'    => FilterSql::ENUM,
					'expr'    => "{$wpdb->posts}.post_mime_type",
					'options' => $options,
				);
			case 'attached_to':
				return array(
					'kind' => FilterSql::TEXT,
					'expr' => "(SELECT par.post_title FROM {$wpdb->posts} par WHERE par.ID = {$wpdb->posts}.post_parent AND {$wpdb->posts}.post_parent > 0)",
				);
			default:
				return array(
					'kind' => FilterSql::TEXT,
					'expr' => "(SELECT usr.display_name FROM {$wpdb->users} usr WHERE usr.ID = {$wpdb->posts}.post_author)",
				);
		}
	}
}
