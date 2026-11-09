<?php

namespace Corinthian\Screens;

use Corinthian\Columns\Config;
use Corinthian\Columns\Registry;
use Corinthian\Columns\Sortable;
use Corinthian\Sets\ColumnSet;
use Corinthian\Sets\SetRepository;

/**
 * One admin list screen (posts of a type, users, media, comments, terms...).
 *
 * Subclasses wire the WordPress hooks for their table; the shared logic here
 * turns the active set into a columns array, renders cells and records the
 * native columns WordPress and other plugins register.
 */
abstract class Screen {

	public const NATIVE_OPTION_PREFIX = 'corinthian_native_';

	protected ?ColumnSet $active = null;

	public function __construct(
		protected Registry $registry,
		protected SetRepository $sets,
	) {}

	/** @var array{join:string,where:string}|null */
	private ?array $filter_sql = null;

	public function registry(): Registry {
		return $this->registry;
	}

	/**
	 * SQL for the smart filter in the current request, compiled once.
	 *
	 * @return array{join:string,where:string}
	 */
	public function filter_sql(): array {
		if ( null === $this->filter_sql ) {
			/**
			 * Filters the SQL fragments the smart filter adds to this screen's query.
			 */
			$sql              = apply_filters(
				'corinthian_query_sql',
				array(
					'join'  => '',
					'where' => '',
				),
				$this
			);
			$this->filter_sql = ( is_array( $sql ) && is_string( $sql['join'] ?? null ) && is_string( $sql['where'] ?? null ) )
				? array(
					'join'  => $sql['join'],
					'where' => $sql['where'],
				)
				: array(
					'join'  => '',
					'where' => '',
				);
		}
		return $this->filter_sql;
	}

	/** Stable key stored with sets, e.g. "post:page", "users", "term:category". */
	abstract public function key(): string;

	abstract public function label(): string;

	/** The WP_Screen id this table appears on, e.g. "edit-page". */
	abstract public function screen_id(): string;

	/** Object kind the ids in this table refer to: post, user, comment, term, site. */
	abstract public function object_type(): string;

	/** URL of the list screen. */
	abstract public function url(): string;

	/** Register the table hooks. Called on every admin request; keep it cheap. */
	abstract public function register(): void;

	/** Post type, taxonomy, etc. that the screen lists, when it has one. */
	public function subtype(): string {
		return '';
	}

	public function active_set(): ?ColumnSet {
		if ( null === $this->active ) {
			$this->active = $this->sets->active_for( $this, get_current_user_id() );
		}
		return $this->active;
	}

	/**
	 * Filter callback for the table's columns array.
	 *
	 * @param array<string,string> $columns
	 * @return array<string,string>
	 */
	public function filter_columns( array $columns ): array {
		$this->remember_native( $columns );

		$set = $this->active_set();
		if ( ! $set ) {
			return $columns;
		}

		$out = array();
		if ( isset( $columns['cb'] ) ) {
			$out['cb'] = $columns['cb'];
		}
		foreach ( $set->columns as $config ) {
			if ( 'native' === $config->type ) {
				$key = $config->table_key();
				if ( ! isset( $columns[ $key ] ) ) {
					continue; // The plugin that added it is gone.
				}
				$out[ $key ] = '' !== $config->label ? esc_html( $config->label ) : $columns[ $key ];
				continue;
			}
			if ( ! $this->registry->get( $config->type ) ) {
				continue;
			}
			$out[ $config->table_key() ] = esc_html( $config->label );
		}

		// Keep the row's main column, which holds the link and row actions, even when a set lost it.
		$primary = $this->primary_column();
		if ( isset( $columns[ $primary ] ) && ! isset( $out[ $primary ] ) ) {
			$cb  = isset( $out['cb'] ) ? array( 'cb' => $out['cb'] ) : array();
			$out = $cb + array( $primary => $columns[ $primary ] ) + $out;
		}
		return $out;
	}

	/**
	 * The column WordPress uses for row links and row actions on this table.
	 */
	public function primary_column(): string {
		return 'title';
	}

	/**
	 * @param array<string,mixed> $sortable
	 * @return array<string,mixed>
	 */
	public function filter_sortable( array $sortable ): array {
		$set = $this->active_set();
		if ( ! $set ) {
			return $sortable;
		}
		foreach ( $set->columns as $config ) {
			if ( 'native' === $config->type || false === $config->setting( 'sortable', true ) ) {
				continue;
			}
			$type = $this->registry->get( $config->type );
			if ( $type instanceof Sortable && ( ! $type instanceof \Corinthian\Columns\ConditionallySortable || $type->sorts( $config, $this ) ) ) {
				$sortable[ $config->table_key() ] = array( $config->table_key(), false );
			}
		}
		return $sortable;
	}

	public function cell( string $column_key, int $object_id ): string {
		$config = $this->config_for_key( $column_key );
		if ( ! $config ) {
			return '';
		}
		$type = $this->registry->get( $config->type );
		if ( ! $type ) {
			return '';
		}
		/**
		 * Filters a rendered Corinthian cell.
		 */
		return (string) apply_filters( 'corinthian_cell', $type->render( $object_id, $config, $this ), $config, $object_id, $this );
	}

	/**
	 * Whether the current user may open this list screen at all. REST and
	 * export entry points check this, since they bypass the admin page's own check.
	 */
	public function user_can_list(): bool {
		if ( $this->network_refused() ) {
			return false;
		}
		switch ( $this->object_type() ) {
			case 'post':
				$type = get_post_type_object( $this->subtype() );
				return $type && current_user_can( $type->cap->edit_posts );
			case 'user':
				return current_user_can( 'list_users' );
			case 'comment':
				return current_user_can( 'edit_posts' );
			case 'term':
				$tax = get_taxonomy( $this->subtype() );
				return $tax && current_user_can( $tax->cap->manage_terms );
			case 'site':
				return current_user_can( 'manage_sites' );
		}
		return false;
	}

	/**
	 * Capability a network admin screen demands on top of the object checks, or
	 * "" for screens that live on a single site.
	 */
	public function network_capability(): string {
		return '';
	}

	public function network_refused(): bool {
		$cap = $this->network_capability();
		return '' !== $cap && ! current_user_can( $cap );
	}

	/**
	 * Whether the current user may see this object's data on this screen.
	 */
	public function user_can_read( int $id ): bool {
		if ( $id <= 0 || $this->network_refused() ) {
			return false;
		}
		switch ( $this->object_type() ) {
			case 'post':
				$post = get_post( $id );
				return $post && ( '' === $this->subtype() || $post->post_type === $this->subtype() ) && current_user_can( 'read_post', $id ) && ( 'publish' === $post->post_status || current_user_can( 'edit_post', $id ) );
			case 'user':
				return (bool) get_userdata( $id ) && current_user_can( 'list_users' );
			case 'comment':
				return (bool) get_comment( $id ) && current_user_can( 'edit_comment', $id );
			case 'term':
				$term = get_term( $id );
				$tax  = get_taxonomy( $this->subtype() );
				return $term instanceof \WP_Term && $term->taxonomy === $this->subtype() && $tax && current_user_can( $tax->cap->manage_terms );
			case 'site':
				return get_site( $id ) instanceof \WP_Site && current_user_can( 'manage_sites' );
		}
		return false;
	}

	/**
	 * Extension points for screens whose objects aren't posts, users, comments,
	 * terms or sites (for example WooCommerce orders in their own tables). The
	 * core falls back to these for any object type it doesn't handle itself.
	 */
	public function can_edit_object( int $id ): bool {
		return false;
	}

	public function clean_object_cache( int $id ): void {}

	/**
	 * Ids matching compiled filter SQL, optionally limited to $within, or null
	 * when this screen can't run its own query.
	 *
	 * @param array{join:string,where:string} $sql
	 * @param list<int>|null                  $within
	 * @return list<int>|null
	 */
	public function query_ids( array $sql, ?array $within, int $limit ): ?array {
		return null;
	}

	/**
	 * Whether the current admin request is this screen.
	 */
	public function is_current(): bool {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $screen && $screen->id === $this->screen_id();
	}

	/**
	 * Resolve a requested orderby to a sortable Corinthian column.
	 *
	 * @return array{0:Config,1:Sortable}|null
	 */
	public function sort_target( mixed $orderby ): ?array {
		if ( ! is_string( $orderby ) || ! str_starts_with( $orderby, 'corinthian-' ) ) {
			return null;
		}
		$config = $this->config_for_key( $orderby );
		$type   = $config ? $this->registry->get( $config->type ) : null;
		if ( ! $config || ! $type instanceof Sortable || false === $config->setting( 'sortable', true ) ) {
			return null;
		}
		if ( $type instanceof \Corinthian\Columns\ConditionallySortable && ! $type->sorts( $config, $this ) ) {
			return null;
		}
		return array( $config, $type );
	}

	public static function normalize_order( mixed $order ): string {
		return strtoupper( (string) $order ) === 'ASC' ? 'ASC' : 'DESC';
	}

	public function config_for_key( string $column_key ): ?Config {
		$set = $this->active_set();
		if ( ! $set || ! str_starts_with( $column_key, 'corinthian-' ) ) {
			return null;
		}
		$id = substr( $column_key, strlen( 'corinthian-' ) );
		return $set->column( $id );
	}

	/**
	 * The columns WordPress and other plugins put on this table, as key => label.
	 *
	 * @return array<string,string>
	 */
	public function native_columns(): array {
		$stored = get_option( self::NATIVE_OPTION_PREFIX . md5( $this->key() ), array() );
		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * @param array<string,string> $columns
	 */
	protected function remember_native( array $columns ): void {
		unset( $columns['cb'] );
		$clean = array();
		foreach ( $columns as $key => $label ) {
			if ( str_starts_with( (string) $key, 'corinthian-' ) ) {
				continue;
			}
			$clean[ (string) $key ] = trim( wp_strip_all_tags( (string) $label ) );
		}
		if ( $clean !== $this->native_columns() ) {
			update_option( self::NATIVE_OPTION_PREFIX . md5( $this->key() ), $clean, false );
		}
	}
}
