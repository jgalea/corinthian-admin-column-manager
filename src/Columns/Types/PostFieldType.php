<?php

namespace Corinthian\Columns\Types;

use Closure;
use Corinthian\Columns\Config;
use Corinthian\Screens\Screen;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * A column that shows one derived value from the post, with no settings of its own.
 */
class PostFieldType extends AbstractPostType {

	/**
	 * @param Closure():string                                $label
	 * @param Closure(WP_Post,Config,Screen):mixed            $reader
	 * @param (Closure(string):bool)|null                     $post_types
	 * @param (Closure(mixed,int,Config,Screen):string)|null  $formatter
	 */
	public function __construct(
		private string $key,
		private Closure $label,
		private string $group,
		private Closure $reader,
		private ?Closure $post_types = null,
		private ?Closure $formatter = null,
	) {}

	public function key(): string {
		return $this->key;
	}

	public function label(): string {
		return ( $this->label )();
	}

	protected function group_slug(): string {
		return $this->group;
	}

	protected function supports_post_type( string $post_type ): bool {
		return null === $this->post_types || ( $this->post_types )( $post_type );
	}

	protected function post_value( WP_Post $post, Config $config, Screen $screen ): mixed {
		return ( $this->reader )( $post, $config, $screen );
	}

	protected function format( mixed $value, int $object_id, Config $config, Screen $screen ): string {
		return $this->formatter ? ( $this->formatter )( $value, $object_id, $config, $screen ) : parent::format( $value, $object_id, $config, $screen );
	}
}
