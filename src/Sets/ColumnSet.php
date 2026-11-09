<?php

namespace Corinthian\Sets;

use Corinthian\Columns\Config;

final class ColumnSet {

	/**
	 * @param list<Config>        $columns
	 * @param array<string,mixed> $settings roles and users (role slugs and user ids; both empty = everyone), per_page, horizontal_scroll, sticky_header.
	 */
	public function __construct(
		public readonly int $id,
		public readonly string $screen,
		public readonly string $title,
		public readonly array $columns,
		public readonly array $settings = array(),
		public readonly int $position = 0,
	) {}

	public function column( string $id ): ?Config {
		foreach ( $this->columns as $config ) {
			if ( $config->id === $id ) {
				return $config;
			}
		}
		return null;
	}

	/**
	 * @return list<string>
	 */
	public function roles(): array {
		$roles = $this->settings['roles'] ?? array();
		return is_array( $roles ) ? array_values( array_map( 'strval', $roles ) ) : array();
	}

	/**
	 * @return list<int>
	 */
	public function users(): array {
		$users = $this->settings['users'] ?? array();
		return is_array( $users ) ? array_values( array_filter( array_map( 'intval', $users ) ) ) : array();
	}

	/**
	 * Visible when the set has no restrictions, or the user matches a listed role or user.
	 */
	public function visible_to( \WP_User $user ): bool {
		$roles = $this->roles();
		$users = $this->users();
		if ( array() === $roles && array() === $users ) {
			return true;
		}
		return in_array( (int) $user->ID, $users, true ) || array() !== array_intersect( $roles, (array) $user->roles );
	}

	/**
	 * @return array<string,mixed>
	 */
	public function to_array(): array {
		return array(
			'id'       => $this->id,
			'screen'   => $this->screen,
			'title'    => $this->title,
			'columns'  => array_map( static fn( Config $c ) => $c->to_array(), $this->columns ),
			'settings' => (object) $this->settings,
			'position' => $this->position,
		);
	}
}
