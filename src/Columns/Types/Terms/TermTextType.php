<?php

namespace Corinthian\Columns\Types\Terms;

use Corinthian\Columns\Config;
use Corinthian\Screens\Screen;

/**
 * Term columns that are not sortable: description, link and depth.
 */
class TermTextType extends AbstractTermType {

	public function label(): string {
		return match ( $this->field ) {
			'description' => __( 'Description', 'corinthian-admin-column-manager' ),
			'link'        => __( 'Term link', 'corinthian-admin-column-manager' ),
			default       => __( 'Depth', 'corinthian-admin-column-manager' ),
		};
	}

	public function value( int $object_id, Config $config, Screen $screen ): mixed {
		$term = $this->term( $object_id, $screen );
		if ( ! $term ) {
			return null;
		}
		switch ( $this->field ) {
			case 'description':
				return wp_trim_words( wp_strip_all_tags( $term->description ), 30 );
			case 'link':
				$link = get_term_link( $term );
				return is_wp_error( $link ) ? null : $link;
			default:
				return count( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) );
		}
	}

	protected function format( mixed $value, int $object_id, Config $config, Screen $screen ): string {
		$text = is_scalar( $value ) ? (string) $value : '';
		return 'link' === $this->field ? $this->link( $text, $text, true ) : esc_html( $text );
	}
}
