<?php
/**
 * XML Sitemap Generator
 *
 * Generates XML sitemaps for all enabled post types and taxonomies.
 * Supports sitemap index, paginated post type sub-sitemaps, and taxonomy sub-sitemaps.
 *
 * @package Shynh_SEO
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Shynh_SEO_Sitemap
 *
 * Handles XML sitemap generation with support for:
 * - Sitemap index listing all sub-sitemaps
 * - Post type sub-sitemaps with pagination
 * - Taxonomy sub-sitemaps
 * - Noindex exclusion via post meta
 *
 * @since 1.0.0
 */
class Shynh_SEO_Sitemap {

	/**
	 * Singleton instance.
	 *
	 * @var Shynh_SEO_Sitemap|null
	 */
	private static $instance = null;

	/**
	 * Plugin settings.
	 *
	 * @var array
	 */
	private $settings = array();

	/**
	 * Get singleton instance.
	 *
	 * @since  1.0.0
	 * @return Shynh_SEO_Sitemap
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor. Registers hooks.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->settings = Shynh_SEO_Core::get_settings();

		add_action( 'init', array( $this, 'register_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_filter( 'redirect_canonical', array( $this, 'disable_sitemap_canonical_redirect' ), 10, 2 );
		add_action( 'template_redirect', array( $this, 'intercept_sitemap_request' ) );

		// Disable WordPress default sitemaps.
		add_filter( 'wp_sitemaps_enabled', '__return_false' );
	}

	/**
	 * Register rewrite rules for sitemap URLs.
	 *
	 * @since 1.0.0
	 */
	public function register_rewrite_rules() {
		add_rewrite_rule(
			'([a-z]{2}(?:-[a-z0-9]+)?)/sitemap\.xml/?$',
			'index.php?shynh_sitemap=index&shynh_sitemap_lang=$matches[1]',
			'top'
		);

		add_rewrite_rule(
			'([a-z]{2}(?:-[a-z0-9]+)?)/sitemap-([a-z0-9_-]+)\.xml/?$',
			'index.php?shynh_sitemap=$matches[2]&shynh_sitemap_lang=$matches[1]',
			'top'
		);

		add_rewrite_rule(
			'sitemap\.xml/?$',
			'index.php?shynh_sitemap=index',
			'top'
		);

		add_rewrite_rule(
			'sitemap\.xsl/?$',
			'index.php?shynh_sitemap_xsl=1',
			'top'
		);

		add_rewrite_rule(
			'sitemap-([a-z0-9_-]+)\.xml/?$',
			'index.php?shynh_sitemap=$matches[1]',
			'top'
		);
	}

	/**
	 * Add custom query variable for sitemap detection.
	 *
	 * @since  1.0.0
	 * @param  array $vars Existing query variables.
	 * @return array Modified query variables.
	 */
	public function add_query_vars( $vars ) {
		$vars[] = 'shynh_sitemap';
		$vars[] = 'shynh_sitemap_lang';
		$vars[] = 'shynh_sitemap_xsl';
		return $vars;
	}

	/**
	 * Prevent WordPress from redirecting sitemap files to trailing-slash URLs.
	 *
	 * @since  1.0.0
	 * @param  string|false $redirect_url  The canonical redirect URL.
	 * @param  string       $requested_url The originally requested URL.
	 * @return string|false
	 */
	public function disable_sitemap_canonical_redirect( $redirect_url, $requested_url ) {
		$path = wp_parse_url( $requested_url, PHP_URL_PATH );

		if ( is_string( $path ) && $this->is_sitemap_request_path( $path ) ) {
			return false;
		}

		return $redirect_url;
	}

	/**
	 * Intercept sitemap requests and render the appropriate XML.
	 *
	 * @since 1.0.0
	 */
	public function intercept_sitemap_request() {
		if ( get_query_var( 'shynh_sitemap_xsl' ) || $this->is_sitemap_xsl_request() ) {
			$this->render_sitemap_stylesheet();
			exit;
		}

		$sitemap = get_query_var( 'shynh_sitemap' );

		if ( empty( $sitemap ) ) {
			$sitemap = $this->detect_sitemap_from_request_uri();
		}

		if ( empty( $sitemap ) ) {
			return;
		}

		$language = sanitize_key( (string) get_query_var( 'shynh_sitemap_lang' ) );
		if ( empty( $language ) ) {
			$language = $this->detect_sitemap_language_from_request_uri();
		}

		// Refresh settings on each request.
		$this->settings = Shynh_SEO_Core::get_settings();

		status_header( 200 );
		header( 'Content-Type: application/xml; charset=UTF-8' );
		header( 'X-Robots-Tag: noindex, follow' );

		// Prevent caching of sitemaps.
		nocache_headers();

		if ( 'index' === $sitemap ) {
			if ( $language ) {
				$this->render_language_branch_index( $language );
			} else {
				$this->render_sitemap_index();
			}
		} else {
			$this->render_sub_sitemap( $sitemap, $language );
		}

		exit;
	}

	/**
	 * Detect sitemap URLs even before WordPress rewrite rules are flushed.
	 *
	 * @since 1.0.0
	 * @return string Sitemap slug or empty string.
	 */
	private function detect_sitemap_from_request_uri() {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path        = wp_parse_url( $request_uri, PHP_URL_PATH );

		if ( ! is_string( $path ) ) {
			return '';
		}

		$path = trim( $path, '/' );

		if ( 'sitemap.xml' === $path ) {
			return 'index';
		}

		if ( preg_match( '/^[a-z0-9_-]+\/sitemap\.xml$/i', $path ) ) {
			return 'index';
		}

		if ( preg_match( '/^sitemap-([a-z0-9_-]+)\.xml$/i', $path, $matches ) ) {
			return sanitize_key( $matches[1] );
		}

		if ( preg_match( '/^[a-z0-9_-]+\/sitemap-([a-z0-9_-]+)\.xml$/i', $path, $matches ) ) {
			return sanitize_key( $matches[1] );
		}

		return '';
	}

	private function detect_sitemap_language_from_request_uri() {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path        = wp_parse_url( $request_uri, PHP_URL_PATH );

		if ( ! is_string( $path ) ) {
			return '';
		}

		$path = trim( $path, '/' );
		if ( ! preg_match( '/^([a-z0-9_-]+)\/sitemap(?:-[a-z0-9_-]+)?\.xml$/i', $path, $matches ) ) {
			return '';
		}

		$language = sanitize_key( $matches[1] );

		return in_array( $language, $this->get_sitemap_languages(), true ) ? $language : '';
	}

	/**
	 * Check whether a request path points to one of this plugin's sitemap files.
	 *
	 * @since  1.0.0
	 * @param  string $path Request path.
	 * @return bool
	 */
	private function is_sitemap_request_path( $path ) {
		$path = trim( $path, '/' );

		return (
			'sitemap.xml' === $path
			|| 'sitemap.xsl' === $path
			|| (bool) preg_match( '/^sitemap-[a-z0-9_-]+\.xml$/i', $path )
			|| (bool) preg_match( '/^[a-z0-9_-]+\/sitemap(?:-[a-z0-9_-]+)?\.xml$/i', $path )
		);
	}

	/**
	 * Check whether the current request is for the sitemap XSL stylesheet.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	private function is_sitemap_xsl_request() {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path        = wp_parse_url( $request_uri, PHP_URL_PATH );

		if ( ! is_string( $path ) ) {
			return false;
		}

		return 'sitemap.xsl' === trim( $path, '/' );
	}

	/**
	 * Render sitemap XSL through WordPress so browsers receive the correct MIME type.
	 *
	 * @since 1.0.0
	 */
	private function render_sitemap_stylesheet() {
		$xsl_path = SHYNH_SEO_PATH . 'assets/xsl/sitemap.xsl';

		if ( ! file_exists( $xsl_path ) || ! is_readable( $xsl_path ) ) {
			status_header( 404 );
			header( 'Content-Type: text/plain; charset=UTF-8' );
			echo 'Sitemap stylesheet not found.';
			return;
		}

		status_header( 200 );
		header( 'Content-Type: text/xsl; charset=UTF-8' );
		header( 'X-Robots-Tag: noindex, follow' );
		nocache_headers();

		readfile( $xsl_path );
	}

	/**
	 * Get enabled post types for sitemap.
	 *
	 * @since  1.0.0
	 * @return array Array of post type slugs.
	 */
	private function get_enabled_post_types() {
		if ( ! empty( $this->settings['sitewide_noindex'] ) ) {
			return array();
		}

		$enabled = isset( $this->settings['sitemap_post_types'] )
			? (array) $this->settings['sitemap_post_types']
			: array( 'post', 'page' );

		if ( ! empty( $this->settings['sitemap_auto_include_post_types'] ) ) {
			$enabled = array_merge( $enabled, $this->get_public_post_type_names() );
		}

		$enabled = array_values( array_unique( array_map( 'sanitize_key', $enabled ) ) );
		$enabled = apply_filters( 'shynh_seo_sitemap_post_types', $enabled );
		$excluded = isset( $this->settings['sitemap_excluded_post_types'] )
			? array_map( 'sanitize_key', (array) $this->settings['sitemap_excluded_post_types'] )
			: array();
		$enabled = array_values( array_diff( $enabled, $excluded ) );

		if ( ! empty( $this->settings['enable_media_seo_pages'] ) && ! empty( $this->settings['media_in_sitemap'] ) && empty( $this->settings['attachment_noindex'] ) ) {
			if ( ! in_array( 'attachment', $enabled, true ) ) {
				$enabled[] = 'attachment';
			}
		}

		return array_filter( $enabled, function ( $pt ) {
			return post_type_exists( $pt );
		} );
	}

	/**
	 * Get enabled taxonomies for sitemap.
	 *
	 * @since  1.0.0
	 * @return array Array of taxonomy slugs.
	 */
	private function get_enabled_taxonomies() {
		if ( ! empty( $this->settings['sitewide_noindex'] ) || ! empty( $this->settings['archive_noindex'] ) ) {
			return array();
		}

		$enabled = isset( $this->settings['sitemap_taxonomies'] )
			? (array) $this->settings['sitemap_taxonomies']
			: array( 'category', 'post_tag' );

		if ( ! empty( $this->settings['sitemap_auto_include_taxonomies'] ) ) {
			$enabled = array_merge( $enabled, $this->get_public_taxonomy_names() );
		}

		$enabled = array_values( array_unique( array_map( 'sanitize_key', $enabled ) ) );
		$excluded = isset( $this->settings['sitemap_excluded_taxonomies'] )
			? array_map( 'sanitize_key', (array) $this->settings['sitemap_excluded_taxonomies'] )
			: array();
		$excluded[] = 'post_tag';
		$excluded = array_values( array_unique( $excluded ) );
		$enabled = array_values( array_diff( $enabled, $excluded ) );

		return array_filter( $enabled, function ( $tax ) {
			return taxonomy_exists( $tax );
		} );
	}

	/**
	 * Get public post type names that can be safely exposed in sitemap.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	private function get_public_post_type_names() {
		return array_values( array_diff( get_post_types( array( 'public' => true ), 'names' ), array( 'attachment' ) ) );
	}

	/**
	 * Get public taxonomy names that can be safely exposed in sitemap.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	private function get_public_taxonomy_names() {
		return array_values( array_diff( get_taxonomies( array( 'public' => true ), 'names' ), array( 'post_format' ) ) );
	}

	/**
	 * Get the number of URLs per sitemap page.
	 *
	 * @since  1.0.0
	 * @return int
	 */
	private function get_per_page() {
		$per_page = isset( $this->settings['sitemap_per_page'] )
			? absint( $this->settings['sitemap_per_page'] )
			: 1000;

		return max( 1, min( $per_page, 50000 ) );
	}

	/**
	 * Render the sitemap index listing all sub-sitemaps.
	 *
	 * @since 1.0.0
	 */
	private function render_sitemap_index() {
		$this->render_xml_header();
		echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		foreach ( $this->get_sitemap_languages() as $language ) {
			if ( ! $this->language_has_indexable_content( $language ) ) {
				continue;
			}

			$this->render_sitemap_entry(
				$this->get_language_sitemap_url( $language ),
				$this->get_language_lastmod( $language )
			);
		}

		echo '</sitemapindex>' . "\n";
	}

	/**
	 * Render a sub-sitemap based on the slug.
	 *
	 * Parses the slug to determine if it's a post type (optionally paginated)
	 * or a taxonomy sitemap.
	 *
	 * @since 1.0.0
	 * @param string $slug The sitemap slug from the URL.
	 */
	private function render_sub_sitemap( $slug, $forced_language = '' ) {
		$enabled_post_types = $this->get_enabled_post_types();
		$enabled_taxonomies = $this->get_enabled_taxonomies();
		$languages          = $this->get_sitemap_languages();
		$language           = sanitize_key( (string) $forced_language );

		if ( $language && ! in_array( $language, $languages, true ) ) {
			status_header( 404 );
			$this->render_xml_header();
			echo '<!-- Sitemap language not found -->' . "\n";
			return;
		}

		if ( ! $language && in_array( $slug, $languages, true ) ) {
			$this->render_language_branch_index( $slug );
			return;
		}

		if ( ! $language ) {
			foreach ( $languages as $candidate_language ) {
				$suffix = '-' . $candidate_language;
				if ( substr( $slug, -strlen( $suffix ) ) === $suffix ) {
					$language = $candidate_language;
					$slug     = substr( $slug, 0, -strlen( $suffix ) );
					break;
				}
			}
		}

		// Check for paginated post type sitemap: e.g. "post-2".
		if ( preg_match( '/^(.+)-(\d+)$/', $slug, $matches ) ) {
			$type = $matches[1];
			$page = (int) $matches[2];

			if ( in_array( $type, $enabled_post_types, true ) ) {
				$this->render_post_type_sitemap( $type, $page, $language );
				return;
			}
		}

		// Check for non-paginated post type sitemap.
		if ( in_array( $slug, $enabled_post_types, true ) ) {
			$this->render_post_type_sitemap( $slug, 1, $language );
			return;
		}

		// Check for taxonomy sitemap.
		if ( in_array( $slug, $enabled_taxonomies, true ) ) {
			$this->render_taxonomy_sitemap( $slug, $language );
			return;
		}

		// Sitemap not found.
		status_header( 404 );
		$this->render_xml_header();
		echo '<!-- Sitemap not found -->' . "\n";
	}

	/**
	 * Render a post type sub-sitemap.
	 *
	 * @since 1.0.0
	 * @param string $post_type The post type slug.
	 * @param int    $page      The page number (1-based).
	 * @param string $language  Optional language slug.
	 */
	private function render_post_type_sitemap( $post_type, $page = 1, $language = '' ) {
		$per_page = $this->get_per_page();

		$post_status = ( 'attachment' === $post_type ) ? 'inherit' : 'publish';

		$args = array(
			'post_type'      => $post_type,
			'post_status'    => $post_status,
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'meta_query'     => $this->get_indexable_post_meta_query(),
			'fields'              => 'ids',
			'no_found_rows'       => false,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => false,
		);

		if ( $language && $this->has_polylang() ) {
			$args['lang'] = $language;
		}

		$query = new WP_Query( $args );

		$this->render_xml_header();
		echo $language
			? '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n"
			: '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		if ( $query->have_posts() ) {
			foreach ( $query->posts as $post_id ) {
				if ( $language && ! $this->object_matches_language( 'post', $post_id, $language ) ) {
					continue;
				}

				$permalink = get_permalink( $post_id );
				$modified  = get_post_modified_time( 'Y-m-d\TH:i:sP', true, $post_id );
				$metadata  = $this->get_post_sitemap_metadata( $post_id, $post_type );

				$this->render_url_entry(
					$permalink,
					$modified,
					$metadata,
					$language ? $this->get_post_alternates( $post_id ) : array()
				);
			}
		}

		echo '</urlset>' . "\n";

		wp_reset_postdata();
	}

	/**
	 * Render a taxonomy sub-sitemap.
	 *
	 * @since 1.0.0
	 * @param string $taxonomy The taxonomy slug.
	 * @param string $language Optional language slug.
	 */
	private function render_taxonomy_sitemap( $taxonomy, $language = '' ) {
		$args = array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => true,
			'orderby'    => 'count',
			'order'      => 'DESC',
		);

		if ( $language && $this->has_polylang() ) {
			$args['lang'] = $language;
		}

		$terms = get_terms( $args );

		$this->render_xml_header();
		echo $language
			? '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n"
			: '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				if ( 'noindex' === get_term_meta( $term->term_id, '_shynh_seo_robots_index', true ) ) {
					continue;
				}

				if ( get_term_meta( $term->term_id, '_shynh_seo_canonical', true ) ) {
					continue;
				}

				if ( $language && ! $this->object_matches_language( 'term', $term->term_id, $language ) ) {
					continue;
				}

				$term_link = get_term_link( $term );

				if ( is_wp_error( $term_link ) ) {
					continue;
				}

				$lastmod = $this->get_term_lastmod( $term );
				$metadata = $this->get_term_sitemap_metadata( $term, $taxonomy, $lastmod );

				$this->render_url_entry(
					$term_link,
					$lastmod,
					$metadata,
					$language ? $this->get_term_alternates( $term->term_id ) : array()
				);
			}
		}

		echo '</urlset>' . "\n";
	}

	/**
	 * Render one combined sitemap for a language.
	 *
	 * @since 1.1.0
	 * @param string $language Language slug, e.g. vi or en.
	 */
	private function render_language_sitemap( $language ) {
		$this->render_xml_header();
		echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

		foreach ( $this->get_enabled_post_types() as $post_type ) {
			$this->render_language_post_urls( $language, $post_type );
		}

		foreach ( $this->get_enabled_taxonomies() as $taxonomy ) {
			$this->render_language_term_urls( $language, $taxonomy );
		}

		echo '</urlset>' . "\n";
	}

	/**
	 * Render post URLs for one language.
	 *
	 * @since 1.1.0
	 * @param string $language  Language slug.
	 * @param string $post_type Post type slug.
	 */
	private function render_language_post_urls( $language, $post_type ) {
		$post_status = ( 'attachment' === $post_type ) ? 'inherit' : 'publish';
		$args        = array(
			'post_type'              => $post_type,
			'post_status'            => $post_status,
			'posts_per_page'         => 50000,
			'paged'                  => 1,
			'orderby'                => 'modified',
			'order'                  => 'DESC',
			'meta_query'             => $this->get_indexable_post_meta_query(),
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => false,
		);

		if ( $this->has_polylang() ) {
			$args['lang'] = $language;
		}

		$query = new WP_Query( $args );

		if ( ! $query->have_posts() ) {
			wp_reset_postdata();
			return;
		}

		foreach ( $query->posts as $post_id ) {
			if ( ! $this->object_matches_language( 'post', $post_id, $language ) ) {
				continue;
			}

			$permalink = get_permalink( $post_id );
			if ( ! $permalink ) {
				continue;
			}

			$modified   = get_post_modified_time( 'Y-m-d\TH:i:sP', true, $post_id );
			$metadata   = $this->get_post_sitemap_metadata( $post_id, $post_type );
			$alternates = $this->get_post_alternates( $post_id );

			$this->render_url_entry( $permalink, $modified, $metadata, $alternates );
		}

		wp_reset_postdata();
	}

	/**
	 * Render term URLs for one language.
	 *
	 * @since 1.1.0
	 * @param string $language Language slug.
	 * @param string $taxonomy Taxonomy slug.
	 */
	private function render_language_term_urls( $language, $taxonomy ) {
		$args = array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => true,
			'orderby'    => 'count',
			'order'      => 'DESC',
		);

		if ( $this->has_polylang() ) {
			$args['lang'] = $language;
		}

		$terms = get_terms( $args );

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return;
		}

		foreach ( $terms as $term ) {
			if ( 'noindex' === get_term_meta( $term->term_id, '_shynh_seo_robots_index', true ) ) {
				continue;
			}

			if ( get_term_meta( $term->term_id, '_shynh_seo_canonical', true ) ) {
				continue;
			}

			if ( ! $this->object_matches_language( 'term', $term->term_id, $language ) ) {
				continue;
			}

			$term_link = get_term_link( $term );
			if ( is_wp_error( $term_link ) ) {
				continue;
			}

			$lastmod    = $this->get_term_lastmod( $term );
			$metadata   = $this->get_term_sitemap_metadata( $term, $taxonomy, $lastmod );
			$alternates = $this->get_term_alternates( $term->term_id );

			$this->render_url_entry( $term_link, $lastmod, $metadata, $alternates );
		}
	}

	/**
	 * Render a single sitemap index entry.
	 *
	 * @since 1.0.0
	 * @param string      $loc     The URL of the sub-sitemap.
	 * @param string|null $lastmod The last modification date in W3C format.
	 */
	private function render_sitemap_entry( $loc, $lastmod = null ) {
		echo "\t<sitemap>\n";
		echo "\t\t<loc>" . $this->xml_escape( $loc ) . "</loc>\n";

		if ( $lastmod ) {
			echo "\t\t<lastmod>" . $this->xml_escape( $lastmod ) . "</lastmod>\n";
		}

		echo "\t</sitemap>\n";
	}

	/**
	 * Count indexable posts for a given post type.
	 *
	 * Excludes posts marked with noindex meta.
	 *
	 * @since  1.0.0
	 * @param  string $post_type The post type slug.
	 * @param  string $language  Optional language slug.
	 * @return int    The number of indexable posts.
	 */
	private function count_sitemap_posts( $post_type, $language = '' ) {
		$post_status = ( 'attachment' === $post_type ) ? 'inherit' : 'publish';
		$args        = array(
			'post_type'      => $post_type,
			'post_status'    => $post_status,
			'posts_per_page' => $language && ! $this->has_polylang() ? 50000 : 1,
			'meta_query'     => $this->get_indexable_post_meta_query(),
			'fields'        => 'ids',
			'no_found_rows' => false,
		);

		if ( $language && $this->has_polylang() ) {
			$args['lang'] = $language;
		}

		$query = new WP_Query( $args );

		if ( $language && ! $this->has_polylang() ) {
			$count = 0;
			foreach ( $query->posts as $post_id ) {
				if ( $this->object_matches_language( 'post', $post_id, $language ) ) {
					$count++;
				}
			}

			return $count;
		}

		return (int) $query->found_posts;
	}

	/**
	 * Get the shared meta query for indexable posts.
	 *
	 * @since 1.1.0
	 * @return array
	 */
	private function get_indexable_post_meta_query() {
		return array(
			'relation' => 'AND',
			array(
				'relation' => 'OR',
				array(
					'key'     => '_shynh_seo_robots_index',
					'value'   => 'noindex',
					'compare' => '!=',
				),
				array(
					'key'     => '_shynh_seo_robots_index',
					'compare' => 'NOT EXISTS',
				),
			),
			array(
				'relation' => 'OR',
				array(
					'key'     => '_shynh_seo_canonical',
					'value'   => '',
					'compare' => '=',
				),
				array(
					'key'     => '_shynh_seo_canonical',
					'compare' => 'NOT EXISTS',
				),
			),
		);
	}

	/**
	 * Get the last modification date for a post type.
	 *
	 * @since  1.0.0
	 * @param  string      $post_type The post type slug.
	 * @return string|null W3C datetime string or null.
	 */
	private function get_post_type_lastmod( $post_type ) {
		global $wpdb;

		$lastmod = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_modified_gmt
				 FROM {$wpdb->posts}
				 WHERE post_type = %s
				   AND post_status = 'publish'
				 ORDER BY post_modified_gmt DESC
				 LIMIT 1",
				$post_type
			)
		);

		if ( $lastmod ) {
			return mysql2date( 'Y-m-d\TH:i:s+00:00', $lastmod, false );
		}

		return null;
	}

	/**
	 * Get the last modification date for a taxonomy.
	 *
	 * Returns the most recent post modification date among all terms.
	 *
	 * @since  1.0.0
	 * @param  string      $taxonomy The taxonomy slug.
	 * @return string|null W3C datetime string or null.
	 */
	private function get_taxonomy_lastmod( $taxonomy ) {
		global $wpdb;

		$lastmod = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(p.post_modified_gmt)
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
				 INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
				 WHERE tt.taxonomy = %s
				   AND p.post_status = 'publish'",
				$taxonomy
			)
		);

		if ( $lastmod ) {
			return mysql2date( 'Y-m-d\TH:i:s+00:00', $lastmod, false );
		}

		return null;
	}

	/**
	 * Get the last modification date for a specific term.
	 *
	 * Returns the most recent post modification date within the term.
	 *
	 * @since  1.0.0
	 * @param  WP_Term     $term The term object.
	 * @return string|null W3C datetime string or null.
	 */
	private function get_term_lastmod( $term ) {
		global $wpdb;

		$lastmod = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(p.post_modified_gmt)
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
				 WHERE tr.term_taxonomy_id = %d
				   AND p.post_status = 'publish'",
				$term->term_taxonomy_id
			)
		);

		if ( $lastmod ) {
			return mysql2date( 'Y-m-d\TH:i:s+00:00', $lastmod, false );
		}

		return null;
	}

	/**
	 * Build smart sitemap metadata for a post URL.
	 *
	 * Uses recency, revision density, post type, front page and cornerstone state.
	 *
	 * @since 1.0.0
	 * @param int    $post_id   Post ID.
	 * @param string $post_type Post type slug.
	 * @return array Sitemap metadata.
	 */
	private function get_post_sitemap_metadata( $post_id, $post_type ) {
		$now               = current_time( 'timestamp', true );
		$published_ts      = (int) get_post_time( 'U', true, $post_id );
		$modified_ts       = (int) get_post_modified_time( 'U', true, $post_id );
		$age_days          = $published_ts ? max( 0, floor( ( $now - $published_ts ) / DAY_IN_SECONDS ) ) : 0;
		$days_since_edit   = $modified_ts ? max( 0, floor( ( $now - $modified_ts ) / DAY_IN_SECONDS ) ) : 999;
		$revision_count    = $this->count_post_revisions( $post_id );
		$edit_rate         = $age_days > 0 ? $revision_count / max( 1, $age_days / 30 ) : $revision_count;
		$is_front_page     = (int) get_option( 'page_on_front' ) === (int) $post_id;
		$is_cornerstone    = '1' === (string) get_post_meta( $post_id, '_shynh_seo_cornerstone', true );
		$base_priority     = $this->get_post_type_base_priority( $post_type );
		$changefreq        = $this->get_smart_changefreq( $days_since_edit, $edit_rate, $is_front_page );

		$priority = $base_priority;

		if ( $is_front_page ) {
			$priority = 1.0;
		} else {
			if ( $is_cornerstone ) {
				$priority += 0.12;
			}

			if ( $days_since_edit <= 7 ) {
				$priority += 0.08;
			} elseif ( $days_since_edit <= 30 ) {
				$priority += 0.04;
			} elseif ( $days_since_edit > 365 ) {
				$priority -= 0.12;
			} elseif ( $days_since_edit > 180 ) {
				$priority -= 0.06;
			}

			if ( $edit_rate >= 4 ) {
				$priority += 0.06;
			} elseif ( $edit_rate >= 1.5 ) {
				$priority += 0.03;
			}
		}

		$metadata = array(
			'changefreq'      => $changefreq,
			'priority'        => $this->normalize_priority( $priority ),
			'days_since_edit' => $days_since_edit,
			'age_days'        => $age_days,
			'revision_count'  => $revision_count,
			'edit_rate'       => $edit_rate,
			'is_front_page'   => $is_front_page,
			'is_cornerstone'  => $is_cornerstone,
		);

		$profile = $this->get_sitemap_profile( 'post_type', $post_type );
		$metadata = $this->apply_sitemap_profile( $metadata, $profile, 'post_type' );

		return apply_filters( 'shynh_seo_sitemap_post_metadata', $metadata, $post_id, $post_type, $this->settings );
	}

	/**
	 * Build smart sitemap metadata for a taxonomy term URL.
	 *
	 * @since 1.0.0
	 * @param WP_Term     $term     Term object.
	 * @param string      $taxonomy Taxonomy slug.
	 * @param string|null $lastmod  Term last modified date.
	 * @return array Sitemap metadata.
	 */
	private function get_term_sitemap_metadata( $term, $taxonomy, $lastmod ) {
		$now             = current_time( 'timestamp', true );
		$lastmod_ts      = $lastmod ? strtotime( $lastmod ) : 0;
		$days_since_edit = $lastmod_ts ? max( 0, floor( ( $now - $lastmod_ts ) / DAY_IN_SECONDS ) ) : 999;
		$count           = isset( $term->count ) ? absint( $term->count ) : 0;

		$priority = 0.45;
		if ( $count >= 50 ) {
			$priority += 0.18;
		} elseif ( $count >= 20 ) {
			$priority += 0.12;
		} elseif ( $count >= 5 ) {
			$priority += 0.06;
		}

		if ( $days_since_edit <= 14 ) {
			$priority += 0.08;
		} elseif ( $days_since_edit > 365 ) {
			$priority -= 0.08;
		}

		$metadata = array(
			'changefreq'      => $this->get_smart_changefreq( $days_since_edit, $count >= 20 ? 1.5 : 0, false ),
			'priority'        => $this->normalize_priority( $priority ),
			'days_since_edit' => $days_since_edit,
			'term_count'      => $count,
		);

		$profile = $this->get_sitemap_profile( 'taxonomy', $taxonomy );
		$metadata = $this->apply_sitemap_profile( $metadata, $profile, 'taxonomy' );

		return apply_filters( 'shynh_seo_sitemap_term_metadata', $metadata, $term, $taxonomy, $this->settings );
	}

	/**
	 * Get sitemap behavior profile for a post type or taxonomy.
	 *
	 * @since 1.0.0
	 * @param string $object_type post_type|taxonomy.
	 * @param string $object_key  Object slug.
	 * @return string Profile key.
	 */
	private function get_sitemap_profile( $object_type, $object_key ) {
		$profiles = array( 'auto', 'fresh', 'dynamic', 'stable', 'archive' );

		if ( 'taxonomy' === $object_type ) {
			$profile = isset( $this->settings['sitemap_taxonomy_profiles'][ $object_key ] )
				? sanitize_key( $this->settings['sitemap_taxonomy_profiles'][ $object_key ] )
				: sanitize_key( $this->settings['sitemap_default_taxonomy_profile'] ?? 'dynamic' );
		} else {
			$profile = isset( $this->settings['sitemap_post_type_profiles'][ $object_key ] )
				? sanitize_key( $this->settings['sitemap_post_type_profiles'][ $object_key ] )
				: sanitize_key( $this->settings['sitemap_default_post_type_profile'] ?? 'auto' );
		}

		return in_array( $profile, $profiles, true ) ? $profile : 'auto';
	}

	/**
	 * Apply sitemap profile guardrails after smart metadata is calculated.
	 *
	 * @since 1.0.0
	 * @param array  $metadata    Smart metadata.
	 * @param string $profile     Profile key.
	 * @param string $object_type post_type|taxonomy.
	 * @return array Metadata.
	 */
	private function apply_sitemap_profile( $metadata, $profile, $object_type ) {
		if ( ! empty( $metadata['is_front_page'] ) ) {
			$metadata['profile'] = $profile;
			$metadata['changefreq'] = 'daily';
			$metadata['priority'] = '1.0';
			return $metadata;
		}

		if ( 'auto' === $profile ) {
			$metadata['profile'] = 'auto';
			return $metadata;
		}

		$priority = isset( $metadata['priority'] ) ? (float) $metadata['priority'] : 0.5;
		$is_taxonomy = 'taxonomy' === $object_type;

		switch ( $profile ) {
			case 'fresh':
				$metadata['changefreq'] = 'daily';
				$priority = max( $priority, $is_taxonomy ? 0.75 : 0.85 );
				break;

			case 'dynamic':
				if ( in_array( $metadata['changefreq'], array( 'monthly', 'yearly', 'never' ), true ) ) {
					$metadata['changefreq'] = 'weekly';
				}
				$priority = max( $priority, $is_taxonomy ? 0.65 : 0.75 );
				break;

			case 'stable':
				if ( in_array( $metadata['changefreq'], array( 'daily', 'weekly' ), true ) ) {
					$metadata['changefreq'] = 'monthly';
				}
				$priority = min( $priority, $is_taxonomy ? 0.60 : 0.70 );
				break;

			case 'archive':
				$metadata['changefreq'] = 'yearly';
				$priority = min( $priority, $is_taxonomy ? 0.45 : 0.50 );
				break;
		}

		$metadata['profile']  = $profile;
		$metadata['priority'] = $this->normalize_priority( $priority );

		return $metadata;
	}

	/**
	 * Determine smart change frequency.
	 *
	 * @since 1.0.0
	 * @param int   $days_since_edit Days since last edit.
	 * @param float $edit_rate       Approximate edits per month.
	 * @param bool  $is_front_page   Whether URL is the front page.
	 * @return string Change frequency.
	 */
	private function get_smart_changefreq( $days_since_edit, $edit_rate = 0, $is_front_page = false ) {
		if ( $is_front_page ) {
			return 'daily';
		}

		if ( $days_since_edit <= 3 || $edit_rate >= 4 ) {
			return 'daily';
		}

		if ( $days_since_edit <= 30 || $edit_rate >= 1.5 ) {
			return 'weekly';
		}

		if ( $days_since_edit <= 180 ) {
			return 'monthly';
		}

		if ( $days_since_edit <= 365 ) {
			return 'yearly';
		}

		return 'never';
	}

	/**
	 * Get base priority by post type.
	 *
	 * @since 1.0.0
	 * @param string $post_type Post type slug.
	 * @return float Base priority.
	 */
	private function get_post_type_base_priority( $post_type ) {
		$base_priority = array(
			'post' => 0.75,
			'page' => 0.62,
		);

		$priority = isset( $base_priority[ $post_type ] ) ? $base_priority[ $post_type ] : 0.70;

		return (float) apply_filters( 'shynh_seo_sitemap_post_type_base_priority', $priority, $post_type, $this->settings );
	}

	/**
	 * Count revisions for edit-frequency detection.
	 *
	 * @since 1.0.0
	 * @param int $post_id Post ID.
	 * @return int Revision count.
	 */
	private function count_post_revisions( $post_id ) {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(ID)
				 FROM {$wpdb->posts}
				 WHERE post_parent = %d
				   AND post_type = 'revision'
				   AND post_status = 'inherit'",
				$post_id
			)
		);
	}

	/**
	 * Normalize sitemap priority to the 0.1-1.0 range.
	 *
	 * @since 1.0.0
	 * @param float $priority Raw priority.
	 * @return string Normalized priority.
	 */
	private function normalize_priority( $priority ) {
		$priority = max( 0.1, min( 1.0, (float) $priority ) );

		return number_format( $priority, 1, '.', '' );
	}

	/**
	 * Get sitemap language slugs.
	 *
	 * @since 1.1.0
	 * @return array
	 */
	private function get_sitemap_languages() {
		$languages = array();

		if ( function_exists( 'pll_languages_list' ) ) {
			$pll_languages = pll_languages_list( array( 'fields' => 'slug' ) );
			if ( is_array( $pll_languages ) ) {
				$languages = array_map( 'sanitize_key', $pll_languages );
			}
		}

		if ( empty( $languages ) ) {
			$languages = array( 'vi', 'en' );
		}

		$preferred = array( 'vi', 'en' );
		$ordered   = array_values( array_intersect( $preferred, $languages ) );

		return array_values( array_unique( ! empty( $ordered ) ? $ordered : $languages ) );
	}

	private function get_language_sitemap_url( $language, $slug = '' ) {
		$language = sanitize_key( (string) $language );
		$slug     = sanitize_key( (string) $slug );

		if ( '' === $slug ) {
			return home_url( '/' . $language . '/sitemap.xml' );
		}

		return home_url( '/' . $language . '/sitemap-' . $slug . '.xml' );
	}

	private function language_has_indexable_content( $language ) {
		foreach ( $this->get_enabled_post_types() as $post_type ) {
			if ( $this->count_sitemap_posts( $post_type, $language ) > 0 ) {
				return true;
			}
		}

		foreach ( $this->get_enabled_taxonomies() as $taxonomy ) {
			if ( $this->taxonomy_has_indexable_terms( $taxonomy, $language ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether Polylang language helpers are available.
	 *
	 * @since 1.1.0
	 * @return bool
	 */
	private function has_polylang() {
		return function_exists( 'pll_get_post_language' ) || function_exists( 'pll_get_term_language' );
	}

	/**
	 * Check if an object belongs to the requested sitemap language.
	 *
	 * @since 1.1.0
	 * @param string $object_type post|term.
	 * @param int    $object_id   Object ID.
	 * @param string $language    Language slug.
	 * @return bool
	 */
	private function object_matches_language( $object_type, $object_id, $language ) {
		$object_language = '';

		if ( 'post' === $object_type && function_exists( 'pll_get_post_language' ) ) {
			$object_language = (string) pll_get_post_language( $object_id );
		} elseif ( 'term' === $object_type && function_exists( 'pll_get_term_language' ) ) {
			$object_language = (string) pll_get_term_language( $object_id );
		}

		if ( '' !== $object_language ) {
			return $language === sanitize_key( $object_language );
		}

		$url = 'term' === $object_type ? get_term_link( $object_id ) : get_permalink( $object_id );
		if ( is_wp_error( $url ) || empty( $url ) ) {
			return false;
		}

		return $this->url_matches_language( $url, $language );
	}

	/**
	 * Infer language from URL when Polylang is unavailable.
	 *
	 * @since 1.1.0
	 * @param string $url      URL.
	 * @param string $language Language slug.
	 * @return bool
	 */
	private function url_matches_language( $url, $language ) {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$path = '/' . ltrim( $path, '/' );

		if ( 'en' === $language ) {
			return (bool) preg_match( '#^/en(?:/|$)#i', $path );
		}

		if ( 'vi' === $language ) {
			return ! preg_match( '#^/en(?:/|$)#i', $path );
		}

		return false;
	}

	/**
	 * Get last modification date for a language sitemap.
	 *
	 * @since 1.1.0
	 * @param string $language Language slug.
	 * @return string|null
	 */
	private function get_language_lastmod( $language, $post_type = '' ) {
		$lastmod = null;

		$post_types = $post_type ? array( $post_type ) : $this->get_enabled_post_types();

		foreach ( $post_types as $current_post_type ) {
			$post_type_lastmod = $this->get_post_type_lastmod( $current_post_type );
			if ( $post_type_lastmod && ( null === $lastmod || strtotime( $post_type_lastmod ) > strtotime( $lastmod ) ) ) {
				$lastmod = $post_type_lastmod;
			}
		}

		return apply_filters( 'shynh_seo_language_sitemap_lastmod', $lastmod, $language, $this->settings );
	}

	/**
	 * Render a branch index for one language when /sitemap-{lang}.xml is opened directly.
	 *
	 * @since 1.1.0
	 * @param string $language Language slug.
	 */
	private function render_language_branch_index( $language ) {
		$this->render_xml_header();
		echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		foreach ( $this->get_enabled_post_types() as $post_type ) {
			$total = $this->count_sitemap_posts( $post_type, $language );

			if ( $total <= 0 ) {
				continue;
			}

			$per_page    = $this->get_per_page();
			$total_pages = (int) ceil( $total / $per_page );
			$lastmod     = $this->get_language_lastmod( $language, $post_type );

			if ( $total_pages <= 1 ) {
				$this->render_sitemap_entry( $this->get_language_sitemap_url( $language, $post_type ), $lastmod );
			} else {
				for ( $page = 1; $page <= $total_pages; $page++ ) {
					$this->render_sitemap_entry( $this->get_language_sitemap_url( $language, $post_type . '-' . $page ), $lastmod );
				}
			}
		}

		foreach ( $this->get_enabled_taxonomies() as $taxonomy ) {
			if ( ! $this->taxonomy_has_indexable_terms( $taxonomy, $language ) ) {
				continue;
			}

			$this->render_sitemap_entry(
				$this->get_language_sitemap_url( $language, $taxonomy ),
				$this->get_taxonomy_lastmod( $taxonomy )
			);
		}

		echo '</sitemapindex>' . "\n";
	}

	/**
	 * Check whether a taxonomy has indexable terms for a language.
	 *
	 * @since 1.1.0
	 * @param string $taxonomy Taxonomy slug.
	 * @param string $language Language slug.
	 * @return bool
	 */
	private function taxonomy_has_indexable_terms( $taxonomy, $language = '' ) {
		$args = array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => true,
		);

		if ( $language && $this->has_polylang() ) {
			$args['lang'] = $language;
		}

		$terms = get_terms( $args );

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return false;
		}

		foreach ( $terms as $term ) {
			if ( 'noindex' === get_term_meta( $term->term_id, '_shynh_seo_robots_index', true ) ) {
				continue;
			}

			if ( get_term_meta( $term->term_id, '_shynh_seo_canonical', true ) ) {
				continue;
			}

			if ( $language && ! $this->object_matches_language( 'term', $term->term_id, $language ) ) {
				continue;
			}

			return true;
		}

		return false;
	}

	/**
	 * Get alternate URLs for a post.
	 *
	 * @since 1.1.0
	 * @param int $post_id Post ID.
	 * @return array
	 */
	private function get_post_alternates( $post_id ) {
		$alternates = array();

		if ( function_exists( 'pll_get_post_translations' ) ) {
			$translations = pll_get_post_translations( $post_id );
			if ( is_array( $translations ) ) {
				foreach ( $translations as $lang => $translated_id ) {
					$url = get_permalink( $translated_id );
					if ( $url ) {
						$alternates[ sanitize_key( $lang ) ] = $url;
					}
				}
			}
		}

		return $this->normalize_alternates( $alternates );
	}

	/**
	 * Get alternate URLs for a term.
	 *
	 * @since 1.1.0
	 * @param int $term_id Term ID.
	 * @return array
	 */
	private function get_term_alternates( $term_id ) {
		$alternates = array();

		if ( function_exists( 'pll_get_term_translations' ) ) {
			$translations = pll_get_term_translations( $term_id );
			if ( is_array( $translations ) ) {
				foreach ( $translations as $lang => $translated_id ) {
					$url = get_term_link( (int) $translated_id );
					if ( ! is_wp_error( $url ) && $url ) {
						$alternates[ sanitize_key( $lang ) ] = $url;
					}
				}
			}
		}

		return $this->normalize_alternates( $alternates );
	}

	/**
	 * Keep alternates focused on configured languages.
	 *
	 * @since 1.1.0
	 * @param array $alternates Language => URL.
	 * @return array
	 */
	private function normalize_alternates( $alternates ) {
		$languages = $this->get_sitemap_languages();
		$output    = array();

		foreach ( $languages as $language ) {
			if ( ! empty( $alternates[ $language ] ) ) {
				$output[ $language ] = $alternates[ $language ];
			}
		}

		return $output;
	}

	/**
	 * Render one URL entry.
	 *
	 * @since 1.1.0
	 * @param string      $loc        URL.
	 * @param string|null $lastmod    Last modified date.
	 * @param array       $metadata   Sitemap metadata.
	 * @param array       $alternates Hreflang alternates.
	 */
	private function render_url_entry( $loc, $lastmod, $metadata, $alternates = array() ) {
		echo "\t<url>\n";
		echo "\t\t<loc>" . $this->xml_escape( $loc ) . "</loc>\n";

		foreach ( $alternates as $lang => $href ) {
			echo "\t\t<xhtml:link rel=\"alternate\" hreflang=\"" . $this->xml_escape( $lang ) . "\" href=\"" . $this->xml_escape( $href ) . "\" />\n";
		}

		if ( count( $alternates ) > 1 ) {
			$default_href = reset( $alternates );
			echo "\t\t<xhtml:link rel=\"alternate\" hreflang=\"x-default\" href=\"" . $this->xml_escape( $default_href ) . "\" />\n";
		}

		if ( $lastmod ) {
			echo "\t\t<lastmod>" . $this->xml_escape( $lastmod ) . "</lastmod>\n";
		}

		if ( ! empty( $metadata['changefreq'] ) ) {
			echo "\t\t<changefreq>" . $this->xml_escape( $metadata['changefreq'] ) . "</changefreq>\n";
		}

		if ( ! empty( $metadata['priority'] ) ) {
			echo "\t\t<priority>" . $this->xml_escape( $metadata['priority'] ) . "</priority>\n";
		}

		echo "\t</url>\n";
	}

	/**
	 * Escape a string for safe use in XML output.
	 *
	 * @since  1.0.0
	 * @param  string $string The string to escape.
	 * @return string Escaped string safe for XML.
	 */
	private function xml_escape( $string ) {
		return esc_xml( (string) $string );
	}

	/**
	 * Render the XML declaration and browser stylesheet instruction.
	 *
	 * @since 1.0.0
	 */
	private function render_xml_header() {
		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo '<?xml-stylesheet type="text/xsl" href="' . $this->xml_escape( home_url( '/sitemap.xsl' ) ) . '"?>' . "\n";
	}
}
