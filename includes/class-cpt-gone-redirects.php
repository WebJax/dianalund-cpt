<?php
/**
 * Redirect (or answer 410) for slettede indlæg i udvalgte CPT'er – ét mål pr. CPT.
 *
 * Ingen tabel, ingen egen CPT, ingen admin-UI. Klassen kører kun, når WordPress
 * allerede har afgjort, at requesten er en 404, og URL'en ligger under CPT'ens
 * rewrite-slug.
 *
 * Brug (fx i dit eksisterende plugins hovedfil):
 *
 *     require_once plugin_dir_path( __FILE__ ) . 'includes/class-cpt-gone-redirects.php';
 *
 *     function mit_plugin_boot_gone_redirects() {
 *         $redirects = new Cpt_Gone_Redirects(
 *             array(
 *                 'butiksside'    => array( 'target' => 12, 'code' => 301 ),
 *                 'erhvervsside'  => array( 'target' => 34, 'code' => 301 ),
 *                 'foreningsside' => array( 'target' => '/foreninger/', 'code' => 302 ),
 *             )
 *         );
 *         $redirects->register_hooks();
 *     }
 *     add_action( 'plugins_loaded', 'mit_plugin_boot_gone_redirects' );
 *
 * 'target': heltal = ID på en udgivet side/indlæg, streng = sti på sitet.
 * 'code':   301, 302 eller 410 (410 kræver intet mål og viser temaets 404-skabelon).
 *
 * @package Cpt_Gone_Redirects
 */

defined( 'ABSPATH' ) || exit;

/**
 * Catch-all redirect pr. custom post type.
 */
class Cpt_Gone_Redirects {

	/**
	 * Regler pr. post type.
	 *
	 * @var array
	 */
	private $rules;

	/**
	 * Constructor.
	 *
	 * @param array $rules post_type => array( 'target' => int|string, 'code' => int ).
	 */
	public function __construct( array $rules ) {
		$this->rules = $rules;
	}

	/**
	 * Hook into WordPress.
	 */
	public function register_hooks() {
		// Prioritet 1: før redirect_canonical og gæt af gamle slugs (prioritet 10).
		add_action( 'template_redirect', array( $this, 'maybe_redirect' ), 1 );
	}

	/**
	 * Viderestil, hvis en 404 ligger under en af CPT'ernes URL-baser.
	 */
	public function maybe_redirect() {
		if ( ! is_404() ) {
			return;
		}

		$path = $this->get_request_path();
		if ( '' === $path ) {
			return;
		}

		foreach ( $this->rules as $post_type => $rule ) {
			$base = $this->get_base( $post_type );

			// Kun URL'er under basen (ikke selve /butiksside/).
			if ( '' === $base || 0 !== strpos( $path, '/' . $base . '/' ) ) {
				continue;
			}

			// Kladder, private og planlagte indlæg giver også 404 for besøgende – dem rører vi ikke.
			if ( $this->has_unpublished_post( $post_type, $path ) ) {
				return;
			}

			$this->respond( $rule, $path );
			return;
		}
	}

	/**
	 * Send redirect eller 410.
	 *
	 * @param array  $rule Regel for post typen.
	 * @param string $path Normaliseret request-sti.
	 */
	private function respond( array $rule, $path ) {
		$code = isset( $rule['code'] ) ? (int) $rule['code'] : 301;

		if ( 410 === $code ) {
			status_header( 410 );
			return;
		}

		$url = $this->get_target_url( $rule );

		// Målet er ikke længere udgivet: 410 er mere ærligt end et brudt redirect.
		if ( '' === $url ) {
			status_header( 410 );
			return;
		}

		// Loop-beskyttelse.
		if ( untrailingslashit( $url ) === untrailingslashit( home_url( $path ) ) ) {
			return;
		}

		wp_safe_redirect( $url, 302 === $code ? 302 : 301, 'Cpt Gone Redirects' );
		exit;
	}

	/**
	 * Mål-URL for en regel.
	 *
	 * @param array $rule Regel.
	 * @return string Tom streng, hvis der ikke er et gyldigt mål.
	 */
	private function get_target_url( array $rule ) {
		$target = isset( $rule['target'] ) ? $rule['target'] : '';

		if ( is_int( $target ) ) {
			$post = get_post( $target );

			return ( $post && 'publish' === $post->post_status ) ? (string) get_permalink( $post ) : '';
		}

		$target = trim( (string) $target );

		return '' !== $target ? home_url( '/' . ltrim( $target, '/' ) ) : '';
	}

	/**
	 * URL-base for en post type (rewrite-slug, ellers post type-navnet).
	 *
	 * @param string $post_type Post type.
	 * @return string Uden skråstreger, små bogstaver. Tom, hvis post typen ikke findes.
	 */
	private function get_base( $post_type ) {
		$object = get_post_type_object( $post_type );
		if ( ! $object ) {
			return '';
		}

		$slug = ( is_array( $object->rewrite ) && ! empty( $object->rewrite['slug'] ) )
			? $object->rewrite['slug']
			: $post_type;

		return strtolower( trim( $slug, '/' ) );
	}

	/**
	 * Findes der et ikke-udgivet indlæg med den slug, som URL'en slutter på?
	 *
	 * @param string $post_type Post type.
	 * @param string $path      Normaliseret request-sti.
	 * @return bool
	 */
	private function has_unpublished_post( $post_type, $path ) {
		$ids = get_posts(
			array(
				'post_type'   => $post_type,
				'name'        => sanitize_title( basename( $path ) ),
				'post_status' => array( 'draft', 'pending', 'private', 'future' ),
				'numberposts' => 1,
				'fields'      => 'ids',
			)
		);

		return ! empty( $ids );
	}

	/**
	 * Normaliseret sti for den aktuelle request: relativ til home, lower-case, uden slutskråstreg.
	 *
	 * @return string
	 */
	private function get_request_path() {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Kun parset og sammenlignet.
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path = wp_parse_url( $uri, PHP_URL_PATH );
		if ( ! is_string( $path ) || '' === $path ) {
			return '';
		}

		$home_path = untrailingslashit( (string) wp_parse_url( home_url(), PHP_URL_PATH ) );
		if ( '' !== $home_path && 0 === strpos( $path, $home_path . '/' ) ) {
			$path = substr( $path, strlen( $home_path ) );
		}

		return '/' . trim( strtolower( rawurldecode( $path ) ), '/' );
	}
}