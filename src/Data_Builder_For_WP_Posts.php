<?php

namespace HAMWORKS\WP\Simple_CSV_Exporter;

use Generator;
use WP_Post;
use WP_Query;
use WP_Taxonomy;

/**
 * Class CSV_Generator
 */
class Data_Builder_For_WP_Posts extends Data_Builder {

	/**
	 * Post type name.
	 *
	 * @var string
	 */
	private $post_type;

	/**
	 * Post field keys to be removed.
	 *
	 * @var string[]
	 */
	protected $drop_columns = array(
		'post_date_gmt',
		'ping_status',
		'to_ping',
		'pinged',
		'post_modified',
		'post_modified_gmt',
		'post_content_filtered',
		'guid',
		'post_mime_type',
		'comment_count',
		'filter',
		'tags_input',
		'page_template',
	);

	/**
	 * Meta keys.
	 *
	 * @var string[]
	 */
	private $meta_keys = array();

	/**
	 * Taxonomies
	 *
	 * @var WP_Taxonomy[]
	 */
	private $taxonomies;

	/**
	 * Posts query for export.
	 *
	 * @var WP_Query
	 */
	private $query;

	/**
	 * CSV_Generator constructor.
	 *
	 * @param string $post_type target post type.
	 */
	public function __construct( string $post_type ) {
		$this->post_type = $post_type;
	}

	/**
	 * @return string
	 */
	public function get_name(): string {
		return $this->post_type;
	}

	/**
	 * Get the taxonomies related with the post type.
	 *
	 * @return string[]|WP_Taxonomy[]
	 */
	private function fetch_taxonomies(): array {
		return get_taxonomies(
			post_type_exists( $this->post_type ) ? array( 'object_type' => array( $this->post_type ) ) : array(),
			'objects'
		);
	}

	/**
	 * Bulk setter for meta keys.
	 *
	 * @param string[] $keys
	 *
	 * @deprecated 1.0.0
	 */
	public function set_meta_keys( array $keys ) {
		$this->meta_keys = $keys;
	}

	/**
	 * Add custom field key for export.
	 *
	 * @param string $key
	 */
	public function append_meta_key( string $key ) {
		$this->meta_keys = array_merge( $this->meta_keys, array( $key ) );
	}

	/**
	 * Remove custom field key for export.
	 *
	 * @param string $key カラム名
	 */
	public function remove_meta_key( string $key ) {
		$this->meta_keys = array_values( array_diff( $this->meta_keys, array( $key ) ) );
	}

	/**
	 * Get term slug.
	 *
	 * @param WP_Post $post
	 * @param string $taxonomy
	 *
	 * @return string[]
	 */
	private function get_the_terms_slugs( WP_Post $post, string $taxonomy ): array {
		$terms = get_the_terms( $post, $taxonomy );
		if ( ! is_array( $terms ) ) {
			return array();
		}

		return array_map( 'urldecode', wp_list_pluck( $terms, 'slug' ) );
	}

	private function get_the_terms_field( WP_Post $post, string $taxonomy ): string {
		return join( ',', $this->get_the_terms_slugs( $post, $taxonomy ) );
	}

	/**
	 * @param WP_Post $post
	 *
	 * @return array
	 */
	private function get_post_meta_fields( WP_Post $post ): array {
		$fields = array_combine(
			$this->meta_keys,
			array_map(
				function ( $key ) use ( $post ) {
					return get_post_meta( $post->ID, $key, true );
				},
				$this->meta_keys
			)
		);

		/**
		 * @param array $fields meta key and value.
		 * @param WP_Post $post post object.
		 *
		 * @deprecated 1.0.0
		 */
		$fields = apply_filters( 'csv_exporter_data_builder_get_post_meta_fields', $fields, $post );

		/**
		 * @param array $fields meta key and value.
		 * @param WP_Post $post post object.
		 */
		return apply_filters( 'simple_csv_exporter_created_data_builder_for_wp_posts_get_post_meta_fields', $fields, $post );
	}


	/**
	 * @param WP_Post $post
	 *
	 * @return string[]
	 */
	private function get_taxonomy_fields( WP_Post $post ): array {
		$columns = array(
			'post_category' => null,
			'tags_input'    => null,
		);
		foreach ( $this->taxonomies as $taxonomy ) {
			switch ( $taxonomy->name ) {
				case 'category':
					$columns['post_category'] = $this->get_the_terms_field( $post, 'category' );
					break;
				case 'post_tag':
					$columns['post_tags'] = $this->get_the_terms_field( $post, 'post_tag' );
					break;
				default:
					$columns[ 'tax_' . $taxonomy->name ] = $this->get_the_terms_field( $post, $taxonomy->name );
			}
		}

		return $columns;
	}

	/**
	 * Build export data.
	 */
	private function build() {
		$this->taxonomies = $this->fetch_taxonomies();

		$query = new WP_Query();
		$query->set( 'nopaging', true );
		$query->set( 'post_status', 'any' );
		$query->set( 'post_type', $this->post_type );

		/**
		 * Fires after the query variable object is created, but before the actual query is run.
		 *
		 * @param WP_Query $query
		 */
		do_action( 'simple_csv_exporter_created_data_builder_for_wp_posts_pre_get_posts', $query );

		$query->get_posts();
		$this->query = $query;
	}

	/**
	 * Row generator.
	 *
	 * @return Generator
	 */
	public function rows(): Generator {
		if ( ! $this->post_type ) {
			return;
		}

		$this->build();

		while ( $this->query->have_posts() ) {
			$this->query->the_post();
			$post      = get_post();
			$post_data = array_merge(
				$post->to_array(),
				array(
					'post_thumbnail' => has_post_thumbnail( $post ) ? get_the_post_thumbnail_url( $post ) : '',
					'post_author'    => $post->post_author ? get_userdata( $post->post_author )->user_login : null,
				),
				$this->get_field_mask(),
				$this->get_post_meta_fields( $post ),
				$this->get_taxonomy_fields( $post )
			);

			// Note: 'foo' => null なものを、まとめて削除.
			yield array_filter(
				$post_data,
				function ( $fields ) {
					return is_string( $fields ) || is_numeric( $fields );
				}
			);
		}
	}
}
