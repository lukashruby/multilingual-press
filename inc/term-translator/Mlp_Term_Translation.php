<?php # -*- coding: utf-8 -*-
/**
 * Fetch and set term translations
 *
 * @version 2015.01.21
 * @author  Inpsyde GmbH, toscho
 * @license GPL
 */

class Mlp_Term_Translation {

	/**
	 * @type wpdb
	 */
	private $wpdb;

	/**
	 * @type WP_Rewrite
	 */
	private $wp_rewrite;

	/**
	 * @param wpdb       $wpdb
	 * @param WP_Rewrite $wp_rewrite
	 */
	public function __construct( wpdb $wpdb, WP_Rewrite $wp_rewrite ) {

		$this->wpdb       = $wpdb;
		$this->wp_rewrite = $wp_rewrite;
	}

	/**
	 * Get term translation
	 *
	 * Runs in the context of the source site.
	 *
	 * @param int $term_taxonomy_id
	 * @param int $target_site_id
	 * @return array|bool
	 */
	public function get_translation( $term_taxonomy_id, $target_site_id ) {

		$cache = (array) wp_cache_get( 'mlp_term_translations', 'mlp' );

		if ( isset ( $cache[ $target_site_id ][ $term_taxonomy_id ] ) )
			return $cache[ $target_site_id ][ $term_taxonomy_id ];

		switch_to_blog( $target_site_id );

		$result = $this->get_translation_in_target_site( $term_taxonomy_id );

		restore_current_blog();

		$cache[ $target_site_id ][ $term_taxonomy_id ] = $result;
		wp_cache_set( 'mlp_term_translations', $cache, 'mlp' );

		return $result;
	}

	/**
	 * Get term translation
	 *
	 * Runs in a switched context.
	 *
	 * @param  int        $term_taxonomy_id
	 * @return array|bool
	 */
	private function get_translation_in_target_site( $term_taxonomy_id ) {

		$term = $this->get_term_by_tt_id( $term_taxonomy_id );

		if ( empty ( $term ) )
			return FALSE;

		if ( is_admin() )
			return $this->get_admin_translation( $term, $term[ 'taxonomy'] );

		$url = $this->get_public_url( (int) $term[ 'term_id'], $term[ 'taxonomy'] );

		return array (
			'target_url'   => new Mlp_Url( $url ),
			'target_title' => $term[ 'name' ]
		);
	}

	/**
	 * Get term in backend
	 *
	 * @param  array  $term
	 * @param  string $taxonomy
	 * @return array|bool
	 */
	private function get_admin_translation( Array $term, $taxonomy ) {

		if ( ! current_user_can( 'edit_terms', $taxonomy ) ) {
			return FALSE;
		}

		$url = get_edit_term_link( (int) $term[ 'term_id' ], $taxonomy );

		return array (
			'target_url'   => new Mlp_Url( $url ),
			'target_title' => $term[ 'name' ]
		);
	}

	/**
	 * Prepare the tax base before the URL is fetched
	 *
	 * @param  int    $term_id
	 * @param  string $taxonomy
	 * @return string
	 */
	private function get_public_url( $term_id, $taxonomy ) {

		$changed = $this->fix_term_base( $taxonomy );
		$url     = get_term_link( (int) $term_id, $taxonomy );

		if ( is_wp_error( $url ) )
			$url = '';

		if ( $changed )
			$this->set_permastruct( $taxonomy, $changed );

		return $url;
	}

	/**
	 * Updates the global wp_rewrite instance if it is wrong
	 *
	 * @param  string $taxonomy
	 * @return bool|string FALSE or the original string for later restore
	 */
	private function fix_term_base( $taxonomy ) {

		$expected = $this->get_expected_base( $taxonomy );
		$existing = $this->wp_rewrite->get_extra_permastruct( $taxonomy );

		if ( ! $this->update_required( $expected, $existing ) )
			return FALSE;

		$this->set_permastruct( $taxonomy, $expected );

		return $existing;
	}

	/**
	 * Compare tax bases
	 *
	 * @param  string|bool $expected
	 * @param  string|bool $existing
	 * @return bool TRUE if both are not FALSE and different
	 */
	private function update_required( $expected, $existing ) {

		if ( ! $expected )
			return FALSE;

		if ( ! $existing )
			return FALSE;

		return $existing !== $expected;
	}

	/**
	 * Find a custom taxonomy base
	 *
	 * @param  string $taxonomy
	 * @return bool|string FALSE or the prepared string
	 */
	private function get_expected_base( $taxonomy ) {

		$taxonomies = array (
			'category' => 'category_base',
			'post_tag' => 'tag_base'
		);
		if ( ! isset ( $taxonomies[ $taxonomy ] ) )
			return FALSE;

		$option = get_option( $taxonomies[ $taxonomy ] );

		if ( ! $option )
			return FALSE;

		return $option . '/%' . $taxonomy . '%';
	}

	/**
	 * Update global WP_Rewrite instance
	 *
	 * @param  string $taxonomy
	 * @param  string $struct
	 * @return void
	 */
	private function set_permastruct( $taxonomy, $struct ) {

		$this->wp_rewrite->extra_permastructs[ $taxonomy ]['struct'] = $struct;
	}

	/**
	 * Get a term by its term_taxonomy_id.
	 *
	 * @param  int $tt_id term_taxonomy_id
	 * @return array
	 */
	private function get_term_by_tt_id( $tt_id ) {

		$sql = "
SELECT terms.`term_id`, terms.`name`, tax.`taxonomy`
FROM {$this->wpdb->terms} terms
  INNER JOIN {$this->wpdb->term_taxonomy} tax
    ON tax.`term_taxonomy_id` = %d
WHERE tax.`term_id` = terms.`term_id`
LIMIT 1";

		$query  = $this->wpdb->prepare( $sql, $tt_id );
		$result = $this->wpdb->get_row( $query, ARRAY_A );

		// $result might be NULL, but we need a predictable return type.
		return empty ( $result ) ? array() : $result;
	}
}