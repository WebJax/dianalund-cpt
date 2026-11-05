<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'DIANALUND_CVR_SYNC_OPTION_KEY' ) ) {
    define( 'DIANALUND_CVR_SYNC_OPTION_KEY', 'dianalund_cvr_sync_settings' );
}

/**
 * Returns default plugin settings for CVR sync.
 *
 * @return array<string, mixed>
 */
function dianalund_cvr_sync_default_settings() {
    return array(
        'username'     => '',
        'password_enc' => '',
        'endpoint'     => 'https://distribution.virk.dk/cvr-permanent/virksomhed/_search',
        'timeout'      => 20,
        'batch_size'   => 50,
    );
}

/**
 * Gets stored CVR settings merged with defaults.
 *
 * @return array<string, mixed>
 */
function dianalund_cvr_sync_get_settings() {
    $saved = get_option( DIANALUND_CVR_SYNC_OPTION_KEY, array() );
    if ( ! is_array( $saved ) ) {
        $saved = array();
    }

    return wp_parse_args( $saved, dianalund_cvr_sync_default_settings() );
}

/**
 * Builds a deterministic encryption key from WordPress salts.
 *
 * @return string
 */
function dianalund_cvr_sync_encryption_key() {
    return hash( 'sha256', wp_salt( 'auth' ) . '|' . wp_salt( 'secure_auth' ), true );
}

/**
 * Builds a deterministic IV from WordPress salts.
 *
 * @return string
 */
function dianalund_cvr_sync_encryption_iv() {
    return substr( hash( 'sha256', wp_salt( 'nonce' ), true ), 0, 16 );
}

/**
 * Encrypts plaintext for storage.
 *
 * @param string $plaintext The value to encrypt.
 *
 * @return string
 */
function dianalund_cvr_sync_encrypt( $plaintext ) {
    if ( '' === $plaintext || ! function_exists( 'openssl_encrypt' ) ) {
        return $plaintext;
    }

    $encrypted = openssl_encrypt(
        $plaintext,
        'AES-256-CBC',
        dianalund_cvr_sync_encryption_key(),
        0,
        dianalund_cvr_sync_encryption_iv()
    );

    if ( false === $encrypted ) {
        return '';
    }

    return 'enc:' . base64_encode( $encrypted );
}

/**
 * Decrypts stored value.
 *
 * @param string $value Stored value.
 *
 * @return string
 */
function dianalund_cvr_sync_decrypt( $value ) {
    if ( '' === $value ) {
        return '';
    }

    if ( 0 !== strpos( $value, 'enc:' ) ) {
        return $value;
    }

    if ( ! function_exists( 'openssl_decrypt' ) ) {
        return '';
    }

    $encoded = substr( $value, 4 );
    $raw     = base64_decode( $encoded, true );
    if ( false === $raw ) {
        return '';
    }

    $decrypted = openssl_decrypt(
        $raw,
        'AES-256-CBC',
        dianalund_cvr_sync_encryption_key(),
        0,
        dianalund_cvr_sync_encryption_iv()
    );

    if ( false === $decrypted ) {
        return '';
    }

    return $decrypted;
}

/**
 * Returns a sanitized credentials array.
 *
 * @return array<string, string>
 */
function dianalund_cvr_sync_get_credentials() {
    $settings = dianalund_cvr_sync_get_settings();

    return array(
        'username' => isset( $settings['username'] ) ? (string) $settings['username'] : '',
        'password' => isset( $settings['password_enc'] ) ? dianalund_cvr_sync_decrypt( (string) $settings['password_enc'] ) : '',
    );
}

/**
 * Builds an Elasticsearch query for active companies.
 *
 * @param string $search      Search term.
 * @param int    $size        Page size.
 * @param string $postnr      Postal code filter.
 * @param bool   $only_active Whether to filter to active companies only.
 * @param int    $page        Result page (1-based).
 *
 * @return array<string, mixed>
 */
function dianalund_cvr_sync_build_active_company_query( $search = '', $size = 50, $postnr = '', $only_active = true, $page = 1 ) {
    $size = max( 1, absint( $size ) );
    $page = max( 1, absint( $page ) );

    $query = array(
        '_source' => array(
            'Vrvirksomhed.cvrNummer',
            'Vrvirksomhed.virksomhedMetadata.nyesteNavn.navn',
            'Vrvirksomhed.virksomhedMetadata.nyesteBeliggenhedsadresse',
            'Vrvirksomhed.virksomhedMetadata.sammensatStatus',
            'Vrvirksomhed.virksomhedMetadata.nyesteVirksomhedsform',
            'Vrvirksomhed.hovedbranche',
            'Vrvirksomhed.telefonNummer',
            'Vrvirksomhed.elektroniskPost',
            'Vrvirksomhed.hjemmeside',
            'Vrvirksomhed.aarsbeskaeftigelse',
        ),
        'size'    => $size,
        'from'    => ( $page - 1 ) * $size,
        'query'   => array(
            'bool' => array(
                'must'   => array(),
                'filter' => array(),
            ),
        ),
    );

    if ( $only_active ) {
        $query['query']['bool']['filter'][] = array(
            'nested' => array(
                'path'  => 'Vrvirksomhed.livsforloeb',
                'query' => array(
                    'bool' => array(
                        'must_not' => array(
                            array(
                                'exists' => array(
                                    'field' => 'Vrvirksomhed.livsforloeb.periode.gyldigTil',
                                ),
                            ),
                        ),
                    ),
                ),
            ),
        );
    }

    $search = trim( (string) $search );
    if ( '' !== $search ) {
        $query['query']['bool']['must'][] = array(
            'match' => array(
                'Vrvirksomhed.virksomhedMetadata.nyesteNavn.navn' => array(
                    'query'    => $search,
                    'operator' => 'and',
                ),
            ),
        );
    }

    $postnr = preg_replace( '/\D+/', '', (string) $postnr );
    if ( '' !== $postnr ) {
        $query['query']['bool']['filter'][] = array(
            'term' => array(
                'Vrvirksomhed.virksomhedMetadata.nyesteBeliggenhedsadresse.postnummer' => $postnr,
            ),
        );
    }

    return $query;
}

/**
 * Builds a lightweight Elasticsearch query to verify active-company access.
 *
 * @return array<string, mixed>
 */
function dianalund_cvr_sync_build_active_company_test_query() {
    return array(
        '_source'         => array( 'Vrvirksomhed.cvrNummer' ),
        'size'            => 1,
        'track_total_hits'=> false,
        'sort'            => array( '_doc' ),
        'query'           => array(
            'bool' => array(
                'filter' => array(
                    array(
                        'nested' => array(
                            'path'  => 'Vrvirksomhed.livsforloeb',
                            'query' => array(
                                'bool' => array(
                                    'must_not' => array(
                                        array(
                                            'exists' => array(
                                                'field' => 'Vrvirksomhed.livsforloeb.periode.gyldigTil',
                                            ),
                                        ),
                                    ),
                                ),
                            ),
                        ),
                    ),
                ),
            ),
        ),
    );
}

/**
 * Performs a CVR Elasticsearch search request.
 *
 * @param array<string, mixed> $body Query body.
 * @param array<string, mixed> $args Optional request arguments.
 *
 * @return array<string, mixed>|WP_Error
 */
function dianalund_cvr_sync_search( $body, $args = array() ) {
    $settings    = dianalund_cvr_sync_get_settings();
    $credentials = dianalund_cvr_sync_get_credentials();
    $endpoint    = isset( $settings['endpoint'] ) ? esc_url_raw( (string) $settings['endpoint'] ) : '';
    $timeout     = isset( $settings['timeout'] ) ? absint( $settings['timeout'] ) : 20;
    $req_timeout = isset( $args['timeout'] ) ? absint( $args['timeout'] ) : $timeout;

    if ( '' === $endpoint ) {
        return new WP_Error( 'dianalund_cvr_missing_endpoint', __( 'CVR endpoint mangler.', 'dianalund_txtdomain' ) );
    }

    if ( '' === $credentials['username'] || '' === $credentials['password'] ) {
        return new WP_Error( 'dianalund_cvr_missing_credentials', __( 'CVR brugernavn/adgangskode mangler.', 'dianalund_txtdomain' ) );
    }

    $response = wp_remote_post(
        $endpoint,
        array(
            'timeout' => max( 5, $req_timeout ),
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( $credentials['username'] . ':' . $credentials['password'] ),
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode( $body ),
        )
    );

    if ( is_wp_error( $response ) ) {
        $error_message = (string) $response->get_error_message();
        if ( false !== stripos( $error_message, 'cURL error 28' ) ) {
            return new WP_Error(
                'dianalund_cvr_timeout',
                __( 'Forbindelsen fik timeout mod CVR. Prøv at hæve Timeout i indstillinger (fx 30 sek.), og kontroller at serveren kan nå distribution.virk.dk.', 'dianalund_txtdomain' )
            );
        }
        return $response;
    }

    $status_code = wp_remote_retrieve_response_code( $response );
    $raw_body    = wp_remote_retrieve_body( $response );
    $decoded     = json_decode( $raw_body, true );

    if ( 200 !== $status_code ) {
        $error_message = __( 'CVR forespørgsel fejlede.', 'dianalund_txtdomain' );
        if ( is_array( $decoded ) && isset( $decoded['error'] ) ) {
            $error_message = sprintf(
                /* translators: %s: remote error payload */
                __( 'CVR forespørgsel fejlede: %s', 'dianalund_txtdomain' ),
                wp_json_encode( $decoded['error'] )
            );
        }

        return new WP_Error( 'dianalund_cvr_request_failed', $error_message, array( 'status_code' => $status_code ) );
    }

    if ( ! is_array( $decoded ) ) {
        return new WP_Error( 'dianalund_cvr_invalid_response', __( 'CVR returnerede ugyldigt JSON-svar.', 'dianalund_txtdomain' ) );
    }

    return $decoded;
}

/**
 * Registers CVR sync admin menu.
 */
function dianalund_cvr_sync_register_admin_menu() {
    add_menu_page(
        __( 'CVR Sync', 'dianalund_txtdomain' ),
        __( 'CVR Sync', 'dianalund_txtdomain' ),
        'manage_options',
        'dianalund-cvr-sync',
        'dianalund_cvr_sync_render_admin_page',
        'dashicons-id-alt',
        47
    );

    add_submenu_page(
        'dianalund-cvr-sync',
        __( 'Indstillinger', 'dianalund_txtdomain' ),
        __( 'Indstillinger', 'dianalund_txtdomain' ),
        'manage_options',
        'dianalund-cvr-sync',
        'dianalund_cvr_sync_render_admin_page'
    );

    add_submenu_page(
        'dianalund-cvr-sync',
        __( 'Sammenlign & Synk', 'dianalund_txtdomain' ),
        __( 'Sammenlign & Synk', 'dianalund_txtdomain' ),
        'manage_options',
        'dianalund-cvr-compare',
        'dianalund_cvr_sync_render_compare_page'
    );
}
add_action( 'admin_menu', 'dianalund_cvr_sync_register_admin_menu' );

/**
 * Saves CVR settings when form is submitted.
 */
function dianalund_cvr_sync_handle_admin_save() {
    if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
        return;
    }

    if ( ! isset( $_POST['dianalund_cvr_sync_action'] ) || 'save_settings' !== $_POST['dianalund_cvr_sync_action'] ) {
        return;
    }

    check_admin_referer( 'dianalund_cvr_sync_save_settings', 'dianalund_cvr_sync_nonce' );

    $existing = dianalund_cvr_sync_get_settings();

    $username = isset( $_POST['dianalund_cvr_username'] ) ? sanitize_text_field( wp_unslash( $_POST['dianalund_cvr_username'] ) ) : '';
    $endpoint = isset( $_POST['dianalund_cvr_endpoint'] ) ? esc_url_raw( wp_unslash( $_POST['dianalund_cvr_endpoint'] ) ) : '';
    $timeout  = isset( $_POST['dianalund_cvr_timeout'] ) ? absint( $_POST['dianalund_cvr_timeout'] ) : 20;
    $batch    = isset( $_POST['dianalund_cvr_batch_size'] ) ? absint( $_POST['dianalund_cvr_batch_size'] ) : 50;

    $settings = array(
        'username'     => $username,
        'password_enc' => isset( $existing['password_enc'] ) ? (string) $existing['password_enc'] : '',
        'endpoint'     => '' !== $endpoint ? $endpoint : dianalund_cvr_sync_default_settings()['endpoint'],
        'timeout'      => max( 5, min( 60, $timeout ) ),
        'batch_size'   => max( 1, min( 100, $batch ) ),
    );

    if ( isset( $_POST['dianalund_cvr_password'] ) ) {
        $password_input = trim( (string) wp_unslash( $_POST['dianalund_cvr_password'] ) );
        if ( '' !== $password_input ) {
            $settings['password_enc'] = dianalund_cvr_sync_encrypt( $password_input );
        }
    }

    update_option( DIANALUND_CVR_SYNC_OPTION_KEY, $settings, false );

    $redirect_url = add_query_arg(
        array(
            'page'    => 'dianalund-cvr-sync',
            'updated' => '1',
        ),
        admin_url( 'admin.php' )
    );

    wp_safe_redirect( $redirect_url );
    exit;
}
add_action( 'admin_init', 'dianalund_cvr_sync_handle_admin_save' );

/**
 * Renders CVR sync admin page.
 */
function dianalund_cvr_sync_render_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Du har ikke tilladelse til denne side.', 'dianalund_txtdomain' ) );
    }

    $settings = dianalund_cvr_sync_get_settings();
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__( 'CVR Sync', 'dianalund_txtdomain' ); ?></h1>
        <p><?php echo esc_html__( 'Konfigurer adgang til CVR Elasticsearch og basisindstillinger til kommende synkronisering.', 'dianalund_txtdomain' ); ?></p>

        <?php if ( isset( $_GET['updated'] ) ) : ?>
            <div class="notice notice-success is-dismissible">
                <p><?php echo esc_html__( 'Indstillinger gemt.', 'dianalund_txtdomain' ); ?></p>
            </div>
        <?php endif; ?>

        <form method="post" action="">
            <?php wp_nonce_field( 'dianalund_cvr_sync_save_settings', 'dianalund_cvr_sync_nonce' ); ?>
            <input type="hidden" name="dianalund_cvr_sync_action" value="save_settings" />

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="dianalund_cvr_username"><?php echo esc_html__( 'CVR brugernavn', 'dianalund_txtdomain' ); ?></label>
                    </th>
                    <td>
                        <input
                            type="text"
                            id="dianalund_cvr_username"
                            name="dianalund_cvr_username"
                            class="regular-text"
                            value="<?php echo esc_attr( (string) $settings['username'] ); ?>"
                            autocomplete="off"
                        />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="dianalund_cvr_password"><?php echo esc_html__( 'CVR adgangskode', 'dianalund_txtdomain' ); ?></label>
                    </th>
                    <td>
                        <input
                            type="password"
                            id="dianalund_cvr_password"
                            name="dianalund_cvr_password"
                            class="regular-text"
                            value=""
                            autocomplete="new-password"
                        />
                        <p class="description">
                            <?php echo esc_html__( 'Lad feltet være tomt for at beholde eksisterende adgangskode.', 'dianalund_txtdomain' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="dianalund_cvr_endpoint"><?php echo esc_html__( 'CVR endpoint', 'dianalund_txtdomain' ); ?></label>
                    </th>
                    <td>
                        <input
                            type="url"
                            id="dianalund_cvr_endpoint"
                            name="dianalund_cvr_endpoint"
                            class="large-text"
                            value="<?php echo esc_attr( (string) $settings['endpoint'] ); ?>"
                        />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="dianalund_cvr_timeout"><?php echo esc_html__( 'Timeout (sekunder)', 'dianalund_txtdomain' ); ?></label>
                    </th>
                    <td>
                        <input
                            type="number"
                            id="dianalund_cvr_timeout"
                            name="dianalund_cvr_timeout"
                            min="5"
                            max="60"
                            value="<?php echo esc_attr( (string) $settings['timeout'] ); ?>"
                        />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="dianalund_cvr_batch_size"><?php echo esc_html__( 'Batch size', 'dianalund_txtdomain' ); ?></label>
                    </th>
                    <td>
                        <input
                            type="number"
                            id="dianalund_cvr_batch_size"
                            name="dianalund_cvr_batch_size"
                            min="1"
                            max="100"
                            value="<?php echo esc_attr( (string) $settings['batch_size'] ); ?>"
                        />
                    </td>
                </tr>
            </table>

            <?php submit_button( __( 'Gem CVR-indstillinger', 'dianalund_txtdomain' ) ); ?>
        </form>

        <hr />
        <h2><?php echo esc_html__( 'Test forbindelse', 'dianalund_txtdomain' ); ?></h2>
        <p><?php echo esc_html__( 'Klik for at teste om CVR-adgangen virker med de gemte indstillinger.', 'dianalund_txtdomain' ); ?></p>
        <p>
            <button type="button" id="dianalund-cvr-test-btn" class="button button-secondary">
                <?php echo esc_html__( 'Test forbindelsen', 'dianalund_txtdomain' ); ?>
            </button>
            <span id="dianalund-cvr-test-spinner" class="spinner" style="float:none;visibility:hidden;vertical-align:middle;margin-left:8px;"></span>
        </p>
        <div id="dianalund-cvr-test-result" style="margin-top:8px;"></div>
    </div>
    <?php
}

// ============================================================
// ASSETS
// ============================================================

/**
 * Enqueues admin CSS and JS on CVR Sync pages.
 *
 * @param string $hook Current admin page hook.
 */
function dianalund_cvr_sync_enqueue_admin_assets( $hook ) {
    if ( 'toplevel_page_dianalund-cvr-sync' !== $hook &&
         false === strpos( $hook, 'dianalund-cvr-compare' ) ) {
        return;
    }

    $plugin_dir = plugin_dir_path( dirname( __FILE__ ) );
    $plugin_url = plugins_url( '/', dirname( __FILE__ ) );

    $css_path = $plugin_dir . 'css/cvr-sync-admin.css';
    $js_path  = $plugin_dir . 'js/cvr-sync-admin.js';

    wp_enqueue_style(
        'dianalund-cvr-sync-admin',
        $plugin_url . 'css/cvr-sync-admin.css',
        array(),
        file_exists( $css_path ) ? (string) filemtime( $css_path ) : '1'
    );

    wp_enqueue_script(
        'dianalund-cvr-sync-admin',
        $plugin_url . 'js/cvr-sync-admin.js',
        array( 'jquery' ),
        file_exists( $js_path ) ? (string) filemtime( $js_path ) : '1',
        true
    );

    wp_localize_script(
        'dianalund-cvr-sync-admin',
        'DianalundCVR',
        array(
            'ajax_url'    => admin_url( 'admin-ajax.php' ),
            'nonce'       => wp_create_nonce( 'dianalund_cvr_ajax' ),
            'current_cpt' => 'erhvervsside',
            'local_posts' => array(),
            'strings'     => array(
                'loading'        => __( 'Henter CVR-data...', 'dianalund_txtdomain' ),
                'confirm_update' => __( 'Vil du overskrive data fra CVR? Dette erstatter navn, adresse og kategorier.', 'dianalund_txtdomain' ),
                'confirm_create' => __( 'Opret ny post som kladde fra CVR-data?', 'dianalund_txtdomain' ),
                'confirm_unlink' => __( 'Fjern CVR-link fra denne post?', 'dianalund_txtdomain' ),
                'confirm_delete' => __( 'Flyt denne virksomhed til papirkurv?', 'dianalund_txtdomain' ),
                'confirm_auto_link' => __( 'Kør automatisk CVR-linkning for denne CPT? Der linkes kun ved entydigt match med status NORMAL.', 'dianalund_txtdomain' ),
                'no_cvr'         => __( 'Ingen CVR-resultater. Prøv et andet søgeord.', 'dianalund_txtdomain' ),
                'network_error'  => __( 'Netværksfejl – prøv igen.', 'dianalund_txtdomain' ),
            ),
        )
    );
}
add_action( 'admin_enqueue_scripts', 'dianalund_cvr_sync_enqueue_admin_assets' );

// ============================================================
// NORMALISERING
// ============================================================

/**
 * Returns the list of CVR _source fields to request.
 *
 * @return string[]
 */
function dianalund_cvr_sync_source_fields() {
    return array(
        'Vrvirksomhed.cvrNummer',
        'Vrvirksomhed.virksomhedMetadata.nyesteNavn.navn',
        'Vrvirksomhed.virksomhedMetadata.nyesteBeliggenhedsadresse',
        'Vrvirksomhed.virksomhedMetadata.sammensatStatus',
        'Vrvirksomhed.virksomhedMetadata.nyesteVirksomhedsform',
        'Vrvirksomhed.hovedbranche',
        'Vrvirksomhed.telefonNummer',
        'Vrvirksomhed.elektroniskPost',
        'Vrvirksomhed.hjemmeside',
        'Vrvirksomhed.aarsbeskaeftigelse',
    );
}

/**
 * Normalizes a raw CVR Elasticsearch hit into a flat internal structure.
 *
 * @param array<string, mixed> $hit Raw ES hit document.
 *
 * @return array<string, string>
 */
function dianalund_cvr_sync_normalize_hit( $hit ) {
    if ( ! is_array( $hit ) || ! isset( $hit['_source']['Vrvirksomhed'] ) ) {
        return array();
    }

    $v        = $hit['_source']['Vrvirksomhed'];
    $metadata = isset( $v['virksomhedMetadata'] ) ? $v['virksomhedMetadata'] : array();

    // Name.
    $navn = isset( $metadata['nyesteNavn']['navn'] ) ? (string) $metadata['nyesteNavn']['navn'] : '';

    // Address.
    $adr     = isset( $metadata['nyesteBeliggenhedsadresse'] ) ? $metadata['nyesteBeliggenhedsadresse'] : array();
    $vejnavn = isset( $adr['vejnavn'] ) ? (string) $adr['vejnavn'] : '';
    $husnr   = isset( $adr['husnummerFra'] ) ? (string) $adr['husnummerFra'] : '';
    $bogstav = isset( $adr['bogstavFra'] ) ? (string) $adr['bogstavFra'] : '';
    $adresse = trim( $vejnavn . ' ' . $husnr . $bogstav );
    $postnr  = isset( $adr['postnummer'] ) ? (string) $adr['postnummer'] : '';
    $by      = isset( $adr['postdistrikt'] ) ? (string) $adr['postdistrikt'] : '';

    // Status.
    $status = isset( $metadata['sammensatStatus'] ) ? (string) $metadata['sammensatStatus'] : '';

    // Company form.
    $virksomhedsform = isset( $metadata['nyesteVirksomhedsform']['langBeskrivelse'] )
        ? (string) $metadata['nyesteVirksomhedsform']['langBeskrivelse']
        : '';

    // Branch.
    $branche_kode  = '';
    $branche_tekst = '';
    if ( isset( $v['hovedbranche'] ) && is_array( $v['hovedbranche'] ) ) {
        $branche_kode  = isset( $v['hovedbranche']['branchekode'] ) ? (string) $v['hovedbranche']['branchekode'] : '';
        $branche_tekst = isset( $v['hovedbranche']['branchetekst'] ) ? (string) $v['hovedbranche']['branchetekst'] : '';
    }

    // Phone – first entry without a gyldigTil date.
    $telefon = '';
    if ( isset( $v['telefonNummer'] ) && is_array( $v['telefonNummer'] ) ) {
        foreach ( $v['telefonNummer'] as $p ) {
            if ( empty( $p['periode']['gyldigTil'] ) ) {
                $telefon = isset( $p['kontaktoplysning'] ) ? (string) $p['kontaktoplysning'] : '';
                break;
            }
        }
    }

    // Email – first active entry.
    $email = '';
    if ( isset( $v['elektroniskPost'] ) && is_array( $v['elektroniskPost'] ) ) {
        foreach ( $v['elektroniskPost'] as $e ) {
            if ( empty( $e['periode']['gyldigTil'] ) ) {
                $email = isset( $e['kontaktoplysning'] ) ? (string) $e['kontaktoplysning'] : '';
                break;
            }
        }
    }

    // Website – first active entry.
    $website = '';
    if ( isset( $v['hjemmeside'] ) && is_array( $v['hjemmeside'] ) ) {
        foreach ( $v['hjemmeside'] as $w ) {
            if ( empty( $w['periode']['gyldigTil'] ) ) {
                $website = isset( $w['kontaktoplysning'] ) ? (string) $w['kontaktoplysning'] : '';
                break;
            }
        }
    }

    // Employees – most recent year.
    $ansatte = '';
    if ( isset( $v['aarsbeskaeftigelse'] ) && is_array( $v['aarsbeskaeftigelse'] ) ) {
        $aarsbesk = $v['aarsbeskaeftigelse'];
        usort(
            $aarsbesk,
            function ( $a, $b ) {
                $ya = isset( $a['aar'] ) ? (int) $a['aar'] : 0;
                $yb = isset( $b['aar'] ) ? (int) $b['aar'] : 0;
                return $yb - $ya;
            }
        );
        $ansatte = isset( $aarsbesk[0]['antalAarsvaerk'] ) ? (string) $aarsbesk[0]['antalAarsvaerk'] : '';
    }

    return array(
        'cvr_nummer'      => isset( $v['cvrNummer'] ) ? (string) $v['cvrNummer'] : '',
        'navn'            => $navn,
        'adresse'         => $adresse,
        'postnr'          => $postnr,
        'by'              => $by,
        'telefon'         => $telefon,
        'email'           => $email,
        'website'         => $website,
        'status'          => $status,
        'virksomhedsform' => $virksomhedsform,
        'branche_kode'    => $branche_kode,
        'branche_tekst'   => $branche_tekst,
        'ansatte'         => $ansatte,
    );
}

/**
 * Returns CPT-specific mapping from normalized CVR fields to WP meta keys.
 *
 * @param string $post_type Post type slug.
 *
 * @return array<string, string>
 */
function dianalund_cvr_sync_get_field_map( $post_type ) {
    $maps = array(
        'erhvervsside'  => array(
            'navn'    => 'bizz_payed_name',
            'adresse' => 'bizz_payed_adress',
            'postnr'  => 'bizz_payed_postal',
            'by'      => 'bizz_payed_city',
            'telefon' => 'bizz_payed_phone',
            'email'   => 'bizz_payed_mail',
            'website' => 'bizz_payed_web',
        ),
        'butiksside'    => array(
            'navn'    => 'butik_payed_name',
            'adresse' => 'butik_payed_adress',
            'postnr'  => 'butik_payed_postal',
            'by'      => 'butik_payed_city',
            'telefon' => 'butik_payed_phone',
            'email'   => 'butik_payed_mail',
            'website' => 'butik_payed_web',
        ),
        'foreningsside' => array(
            'navn'    => 'fritid_payed_name',
            'adresse' => 'fritid_payed_adress',
            'postnr'  => 'fritid_payed_postal',
            'by'      => 'fritid_payed_city',
            'telefon' => 'fritid_payed_phone',
            'email'   => 'fritid_payed_mail',
            'website' => 'fritid_payed_web',
        ),
    );

    return isset( $maps[ $post_type ] ) ? $maps[ $post_type ] : array();
}

/**
 * Applies normalized CVR data to an existing or newly created WP post.
 *
 * Updates mapped meta fields, post title, sync timestamp, and replaces
 * categories with the CVR branch category (creating the term if needed).
 *
 * @param int                  $post_id    Post ID.
 * @param array<string, string> $normalized Normalized CVR data from dianalund_cvr_sync_normalize_hit().
 * @param string               $post_type  Post type slug.
 *
 * @return string[] List of updated meta keys.
 */
function dianalund_cvr_sync_apply_to_post( $post_id, $normalized, $post_type ) {
    $field_map = dianalund_cvr_sync_get_field_map( $post_type );
    $updated   = array();

    foreach ( $field_map as $cvr_key => $meta_key ) {
        if ( isset( $normalized[ $cvr_key ] ) && '' !== (string) $normalized[ $cvr_key ] ) {
            update_post_meta( $post_id, $meta_key, sanitize_text_field( (string) $normalized[ $cvr_key ] ) );
            $updated[] = $meta_key;
            $jxw_key = function_exists( 'jxw_cvr_to_field_key' ) ? jxw_cvr_to_field_key( $cvr_key ) : '';
            if ( '' !== $jxw_key ) {
                $callback = 'jxw_sanitize_text';
                if ( 'jxw_postal' === $jxw_key ) {
                    $callback = 'jxw_sanitize_postal';
                } elseif ( 'jxw_phone' === $jxw_key ) {
                    $callback = 'jxw_sanitize_phone';
                } elseif ( 'jxw_email' === $jxw_key ) {
                    $callback = 'jxw_sanitize_email_field';
                } elseif ( 'jxw_web' === $jxw_key ) {
                    $callback = 'jxw_sanitize_url_field';
                }
                if ( function_exists( $callback ) ) {
                    update_post_meta( $post_id, $jxw_key, call_user_func( $callback, $normalized[ $cvr_key ] ) );
                    $updated[] = $jxw_key;
                }
            }
        }
    }

    // Store CVR number and sync timestamp.
    update_post_meta( $post_id, 'dianalund_cvr_nummer', sanitize_text_field( (string) $normalized['cvr_nummer'] ) );
    update_post_meta( $post_id, 'dianalund_cvr_synced_at', gmdate( 'Y-m-d H:i:s' ) );

    // Update post title to CVR name.
    if ( ! empty( $normalized['navn'] ) ) {
        wp_update_post(
            array(
                'ID'         => $post_id,
                'post_title' => sanitize_text_field( $normalized['navn'] ),
            )
        );
    }

    // Replace categories with CVR branch term.
    if ( ! empty( $normalized['branche_tekst'] ) ) {
        $branch_name = sanitize_text_field( $normalized['branche_tekst'] );
        $term        = get_term_by( 'name', $branch_name, 'category' );

        if ( $term instanceof WP_Term ) {
            $term_id = $term->term_id;
        } else {
            $inserted = wp_insert_term( $branch_name, 'category' );
            $term_id  = ! is_wp_error( $inserted ) ? (int) $inserted['term_id'] : 0;
        }

        if ( $term_id ) {
            wp_set_post_terms( $post_id, array( $term_id ), 'category' );
        }
    }

    return $updated;
}

// ============================================================
// AJAX HANDLERS
// ============================================================

/**
 * AJAX: Test CVR connection.
 */
function dianalund_cvr_ajax_test_connection() {
    check_ajax_referer( 'dianalund_cvr_ajax', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'Ingen adgang.', 'dianalund_txtdomain' ) ) );
    }

    $settings = dianalund_cvr_sync_get_settings();
    $timeout  = isset( $settings['timeout'] ) ? absint( $settings['timeout'] ) : 20;

    $query  = dianalund_cvr_sync_build_active_company_test_query();
    $result = dianalund_cvr_sync_search(
        $query,
        array(
            'timeout' => max( 20, $timeout ),
        )
    );

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( array( 'message' => $result->get_error_message() ) );
    }

    $sample_cvr = '';
    if ( isset( $result['hits']['hits'][0]['_source']['Vrvirksomhed']['cvrNummer'] ) ) {
        $sample_cvr = (string) $result['hits']['hits'][0]['_source']['Vrvirksomhed']['cvrNummer'];
    }

    wp_send_json_success(
        array(
            'message' => $sample_cvr
                ? sprintf(
                    /* translators: %s: sample CVR number */
                    __( 'Forbindelsen virker. Aktiv virksomhed fundet (eksempel-CVR: %s).', 'dianalund_txtdomain' ),
                    esc_html( $sample_cvr )
                )
                : __( 'Forbindelsen virker. Aktiv-query blev gennemført uden fejl.', 'dianalund_txtdomain' ),
        )
    );
}
add_action( 'wp_ajax_dianalund_cvr_test_connection', 'dianalund_cvr_ajax_test_connection' );

/**
 * AJAX: Search CVR and return normalized company list.
 */
function dianalund_cvr_ajax_search() {
    check_ajax_referer( 'dianalund_cvr_ajax', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'Ingen adgang.', 'dianalund_txtdomain' ) ) );
    }

    $search      = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
    $postnr      = isset( $_POST['postnr'] ) ? sanitize_text_field( wp_unslash( $_POST['postnr'] ) ) : '';
    $page        = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
    $only_active = true;
    if ( isset( $_POST['only_active'] ) ) {
        $only_active = '1' === (string) wp_unslash( $_POST['only_active'] );
    }

    $settings    = dianalund_cvr_sync_get_settings();
    $batch_size  = isset( $settings['batch_size'] ) ? absint( $settings['batch_size'] ) : 50;

    $query  = dianalund_cvr_sync_build_active_company_query( $search, $batch_size, $postnr, $only_active, $page );
    $result = dianalund_cvr_sync_search( $query );

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( array( 'message' => $result->get_error_message() ) );
    }

    $hits = isset( $result['hits']['hits'] ) ? $result['hits']['hits'] : array();

    $normalized = array_values(
        array_filter(
            array_map( 'dianalund_cvr_sync_normalize_hit', $hits ),
            function ( $item ) {
                return ! empty( $item ) && ! empty( $item['cvr_nummer'] );
            }
        )
    );

    usort(
        $normalized,
        function ( $a, $b ) {
            return strcmp( $a['navn'], $b['navn'] );
        }
    );

    $total_raw = isset( $result['hits']['total'] ) ? $result['hits']['total'] : 0;
    $total     = is_array( $total_raw ) && isset( $total_raw['value'] )
        ? (int) $total_raw['value']
        : (int) $total_raw;
    $pages     = $batch_size > 0 ? (int) ceil( $total / $batch_size ) : 1;

    wp_send_json_success(
        array(
            'total'     => $total,
            'shown'     => count( $normalized ),
            'companies' => $normalized,
            'page'      => max( 1, $page ),
            'pages'     => max( 1, $pages ),
            'has_prev'  => $page > 1,
            'has_next'  => $page < $pages,
            'only_active' => $only_active,
        )
    );
}
add_action( 'wp_ajax_dianalund_cvr_search', 'dianalund_cvr_ajax_search' );

/**
 * AJAX: Link a local post to a CVR number (without fetching data).
 */
function dianalund_cvr_ajax_link_post() {
    check_ajax_referer( 'dianalund_cvr_ajax', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'Ingen adgang.', 'dianalund_txtdomain' ) ) );
    }

    $post_id    = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
    $cvr_nummer = isset( $_POST['cvr_nummer'] ) ? sanitize_text_field( wp_unslash( $_POST['cvr_nummer'] ) ) : '';

    if ( ! $post_id || ! $cvr_nummer ) {
        wp_send_json_error( array( 'message' => __( 'Ugyldig post ID eller CVR-nummer.', 'dianalund_txtdomain' ) ) );
    }

    $post = get_post( $post_id );
    if ( ! $post instanceof WP_Post || ! in_array( $post->post_type, array( 'erhvervsside', 'butiksside', 'foreningsside' ), true ) ) {
        wp_send_json_error( array( 'message' => __( 'Ugyldig post.', 'dianalund_txtdomain' ) ) );
    }

    update_post_meta( $post_id, 'dianalund_cvr_nummer', $cvr_nummer );

    wp_send_json_success( array( 'message' => __( 'CVR-nummer gemt.', 'dianalund_txtdomain' ) ) );
}
add_action( 'wp_ajax_dianalund_cvr_link_post', 'dianalund_cvr_ajax_link_post' );

/**
 * AJAX: Remove CVR link from a local post.
 */
function dianalund_cvr_ajax_unlink_post() {
    check_ajax_referer( 'dianalund_cvr_ajax', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'Ingen adgang.', 'dianalund_txtdomain' ) ) );
    }

    $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
    if ( ! $post_id ) {
        wp_send_json_error( array( 'message' => __( 'Ugyldig post ID.', 'dianalund_txtdomain' ) ) );
    }

    $post = get_post( $post_id );
    if ( ! $post instanceof WP_Post || ! in_array( $post->post_type, array( 'erhvervsside', 'butiksside', 'foreningsside' ), true ) ) {
        wp_send_json_error( array( 'message' => __( 'Ugyldig post.', 'dianalund_txtdomain' ) ) );
    }

    delete_post_meta( $post_id, 'dianalund_cvr_nummer' );
    delete_post_meta( $post_id, 'dianalund_cvr_synced_at' );

    wp_send_json_success( array( 'message' => __( 'CVR-link fjernet.', 'dianalund_txtdomain' ) ) );
}
add_action( 'wp_ajax_dianalund_cvr_unlink_post', 'dianalund_cvr_ajax_unlink_post' );

/**
 * AJAX: Move a local CPT post to trash.
 */
function dianalund_cvr_ajax_trash_post() {
    check_ajax_referer( 'dianalund_cvr_ajax', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'Ingen adgang.', 'dianalund_txtdomain' ) ) );
    }

    $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
    if ( ! $post_id ) {
        wp_send_json_error( array( 'message' => __( 'Ugyldig post ID.', 'dianalund_txtdomain' ) ) );
    }

    $post = get_post( $post_id );
    if ( ! $post instanceof WP_Post || ! in_array( $post->post_type, array( 'erhvervsside', 'butiksside', 'foreningsside' ), true ) ) {
        wp_send_json_error( array( 'message' => __( 'Ugyldig post.', 'dianalund_txtdomain' ) ) );
    }

    $trashed = wp_trash_post( $post_id );
    if ( false === $trashed ) {
        wp_send_json_error( array( 'message' => __( 'Kunne ikke flytte posten til papirkurv.', 'dianalund_txtdomain' ) ) );
    }

    wp_send_json_success( array( 'message' => __( 'Post flyttet til papirkurv.', 'dianalund_txtdomain' ) ) );
}
add_action( 'wp_ajax_dianalund_cvr_trash_post', 'dianalund_cvr_ajax_trash_post' );

/**
 * Normalizes a company name for strict matching.
 *
 * @param string $name Company name.
 *
 * @return string
 */
function dianalund_cvr_sync_normalize_company_name( $name ) {
    $name = remove_accents( wp_strip_all_tags( (string) $name ) );
    $name = strtolower( $name );
    $name = preg_replace( '/[^a-z0-9]+/', '', $name );
    return (string) $name;
}

/**
 * Checks if normalized status represents a normal company.
 *
 * @param string $status Status text from CVR.
 *
 * @return bool
 */
function dianalund_cvr_sync_is_normal_status( $status ) {
    $status = strtoupper( trim( (string) $status ) );
    return 'NORMAL' === $status || false !== strpos( $status, 'NORMAL' );
}

/**
 * Picks a single reliable CVR candidate for a local post title.
 *
 * @param array<int, array<string, string>> $companies Normalized CVR companies.
 * @param string                             $post_title Local post title.
 *
 * @return array<string, string>
 */
function dianalund_cvr_sync_pick_single_normal_candidate( $companies, $post_title ) {
    $target_name = dianalund_cvr_sync_normalize_company_name( $post_title );
    if ( '' === $target_name ) {
        return array();
    }

    $matches = array();
    foreach ( $companies as $company ) {
        if ( empty( $company['cvr_nummer'] ) || empty( $company['navn'] ) ) {
            continue;
        }

        if ( ! dianalund_cvr_sync_is_normal_status( isset( $company['status'] ) ? $company['status'] : '' ) ) {
            continue;
        }

        $company_name = dianalund_cvr_sync_normalize_company_name( $company['navn'] );
        if ( $company_name === $target_name ) {
            $matches[ $company['cvr_nummer'] ] = $company;
        }
    }

    if ( 1 !== count( $matches ) ) {
        return array();
    }

    return (array) array_shift( $matches );
}

/**
 * AJAX: Auto-link CVR numbers for a selected CPT when status is NORMAL.
 */
function dianalund_cvr_ajax_auto_link_cpt() {
    check_ajax_referer( 'dianalund_cvr_ajax', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'Ingen adgang.', 'dianalund_txtdomain' ) ) );
    }

    $allowed_cpts = array( 'erhvervsside', 'butiksside', 'foreningsside' );
    $post_type    = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : '';
    if ( ! in_array( $post_type, $allowed_cpts, true ) ) {
        wp_send_json_error( array( 'message' => __( 'Ukendt post type.', 'dianalund_txtdomain' ) ) );
    }

    $field_map = dianalund_cvr_sync_get_field_map( $post_type );
    $postnr_key = isset( $field_map['postnr'] ) ? (string) $field_map['postnr'] : '';

    $posts = (array)get_posts(
        array(
            'post_type'      => $post_type,
            'post_status'    => array( 'publish', 'draft', 'private' ),
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        )
    );

    $stats = array(
        'total'          => count( $posts ),
        'already_linked' => 0,
        'linked'         => 0,
        'no_match'       => 0,
        'errors'         => 0,
    );

    $linked_rows = array();

    foreach ( $posts as $post ) {
        $existing_cvr = (string) get_post_meta( $post->ID, 'dianalund_cvr_nummer', true );
        if ( '' !== $existing_cvr ) {
            $stats['already_linked']++;
            continue;
        }

        $postnr = '';
        if ( '' !== $postnr_key ) {
            $postnr = (string) get_post_meta( $post->ID, $postnr_key, true );
        }

        $query  = dianalund_cvr_sync_build_active_company_query( (string)$post->post_title, 25, $postnr, false, 1 );
        $result = dianalund_cvr_sync_search( $query );

        if ( is_wp_error( $result ) ) {
            $stats['errors']++;
            continue;
        }

        $hits = isset( $result['hits']['hits'] ) && is_array( $result['hits']['hits'] ) ? $result['hits']['hits'] : array();
        $companies = array_values(
            array_filter(
                array_map( 'dianalund_cvr_sync_normalize_hit', $hits ),
                function ( $item ) {
                    return ! empty( $item ) && ! empty( $item['cvr_nummer'] );
                }
            )
        );

        $candidate = dianalund_cvr_sync_pick_single_normal_candidate( $companies, (string) $post->post_title );
        if ( empty( $candidate ) ) {
            $stats['no_match']++;
            continue;
        }

        update_post_meta( $post->ID, 'dianalund_cvr_nummer', sanitize_text_field( (string) $candidate['cvr_nummer'] ) );
        $stats['linked']++;
        $linked_rows[] = array(
            'post_id'    => (int) $post->ID,
            'cvr_nummer' => (string) $candidate['cvr_nummer'],
        );
    }

    wp_send_json_success(
        array(
            'message'    => sprintf(
                /* translators: 1: linked count, 2: total count */
                __( 'Auto-linkning færdig: %1$d af %2$d poster blev linket.', 'dianalund_txtdomain' ),
                (int) $stats['linked'],
                (int) $stats['total']
            ),
            'stats'      => $stats,
            'linked_rows'=> $linked_rows,
        )
    );
}
add_action( 'wp_ajax_dianalund_cvr_auto_link_cpt', 'dianalund_cvr_ajax_auto_link_cpt' );

/**
 * AJAX: Update an existing local post with fresh data from CVR.
 */
function dianalund_cvr_ajax_update_post() {
    check_ajax_referer( 'dianalund_cvr_ajax', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'Ingen adgang.', 'dianalund_txtdomain' ) ) );
    }

    $post_id    = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
    $cvr_nummer = isset( $_POST['cvr_nummer'] ) ? sanitize_text_field( wp_unslash( $_POST['cvr_nummer'] ) ) : '';

    if ( ! $post_id || ! $cvr_nummer ) {
        wp_send_json_error( array( 'message' => __( 'Ugyldig post ID eller CVR-nummer.', 'dianalund_txtdomain' ) ) );
    }

    $post = get_post( $post_id );
    if ( ! $post instanceof WP_Post || ! in_array( $post->post_type, array( 'erhvervsside', 'butiksside', 'foreningsside' ), true ) ) {
        wp_send_json_error( array( 'message' => __( 'Ugyldig post.', 'dianalund_txtdomain' ) ) );
    }

    $query = array(
        '_source' => dianalund_cvr_sync_source_fields(),
        'size'    => 1,
        'query'   => array(
            'term' => array( 'Vrvirksomhed.cvrNummer' => $cvr_nummer ),
        ),
    );

    $result = dianalund_cvr_sync_search( $query );
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( array( 'message' => $result->get_error_message() ) );
    }

    $hits = isset( $result['hits']['hits'] ) ? $result['hits']['hits'] : array();
    if ( empty( $hits ) ) {
        wp_send_json_error( array( 'message' => __( 'CVR-nummer ikke fundet i registret.', 'dianalund_txtdomain' ) ) );
    }

    $normalized = dianalund_cvr_sync_normalize_hit( $hits[0] );
    $updated    = dianalund_cvr_sync_apply_to_post( $post_id, $normalized, $post->post_type );

    wp_send_json_success(
        array(
            'message' => __( 'Post opdateret fra CVR.', 'dianalund_txtdomain' ),
            'updated' => $updated,
            'navn'    => $normalized['navn'],
        )
    );
}
add_action( 'wp_ajax_dianalund_cvr_update_post', 'dianalund_cvr_ajax_update_post' );

/**
 * AJAX: Create a new draft post from CVR data.
 */
function dianalund_cvr_ajax_create_post() {
    check_ajax_referer( 'dianalund_cvr_ajax', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'Ingen adgang.', 'dianalund_txtdomain' ) ) );
    }

    $post_type  = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : '';
    $cvr_nummer = isset( $_POST['cvr_nummer'] ) ? sanitize_text_field( wp_unslash( $_POST['cvr_nummer'] ) ) : '';

    if ( ! in_array( $post_type, array( 'erhvervsside', 'butiksside', 'foreningsside' ), true ) ) {
        wp_send_json_error( array( 'message' => __( 'Ukendt post type.', 'dianalund_txtdomain' ) ) );
    }

    if ( ! $cvr_nummer ) {
        wp_send_json_error( array( 'message' => __( 'CVR-nummer mangler.', 'dianalund_txtdomain' ) ) );
    }

    $query = array(
        '_source' => dianalund_cvr_sync_source_fields(),
        'size'    => 1,
        'query'   => array(
            'term' => array( 'Vrvirksomhed.cvrNummer' => $cvr_nummer ),
        ),
    );

    $result = dianalund_cvr_sync_search( $query );
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( array( 'message' => $result->get_error_message() ) );
    }

    $hits = isset( $result['hits']['hits'] ) ? $result['hits']['hits'] : array();
    if ( empty( $hits ) ) {
        wp_send_json_error( array( 'message' => __( 'CVR-nummer ikke fundet i registret.', 'dianalund_txtdomain' ) ) );
    }

    $normalized = dianalund_cvr_sync_normalize_hit( $hits[0] );

    $post_id = wp_insert_post(
        array(
            'post_type'   => $post_type,
            'post_status' => 'draft',
            'post_title'  => sanitize_text_field( $normalized['navn'] ),
            'post_author' => get_current_user_id(),
        ),
        true
    );

    if ( is_wp_error( $post_id ) ) {
        wp_send_json_error( array( 'message' => $post_id->get_error_message() ) );
        return;
    }

    dianalund_cvr_sync_apply_to_post( $post_id, $normalized, $post_type );

    wp_send_json_success(
        array(
            'message'  => sprintf(
                /* translators: %s: company name */
                __( 'Ny kladde oprettet: %s', 'dianalund_txtdomain' ),
                esc_html( $normalized['navn'] )
            ),
            'post_id'  => $post_id,
            'edit_url' => get_edit_post_link( $post_id, 'raw' ),
            'navn'     => $normalized['navn'],
        )
    );
}
add_action( 'wp_ajax_dianalund_cvr_create_post', 'dianalund_cvr_ajax_create_post' );

// ============================================================
// SAMMENLIGN & SYNK PAGE
// ============================================================

/**
 * Renders the Compare & Sync admin page.
 */
function dianalund_cvr_sync_render_compare_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Du har ikke tilladelse til denne side.', 'dianalund_txtdomain' ) );
    }

    $allowed_cpts = array( 'erhvervsside', 'butiksside', 'foreningsside' );
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $cpt = isset( $_GET['cpt'] ) ? sanitize_key( wp_unslash( $_GET['cpt'] ) ) : 'erhvervsside';
    if ( ! in_array( $cpt, $allowed_cpts, true ) ) {
        $cpt = 'erhvervsside';
    }

    $cpt_labels = array(
        'erhvervsside'  => __( 'Erhverv', 'dianalund_txtdomain' ),
        'butiksside'    => __( 'Butikker', 'dianalund_txtdomain' ),
        'foreningsside' => __( 'Foreninger', 'dianalund_txtdomain' ),
    );

    $posts = (array)get_posts(
        array(
            'post_type'      => $cpt,
            'post_status'    => array( 'publish', 'draft', 'private' ),
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        )
    );

    // Build local posts data for JS matching.
    $local_posts_data = array();
    foreach ( $posts as $p ) {
        $cvr_nummer = (string) get_post_meta( $p->ID, 'dianalund_cvr_nummer', true );
        $synced_at  = (string) get_post_meta( $p->ID, 'dianalund_cvr_synced_at', true );
        $local_posts_data[] = array(
            'post_id'    => $p->ID,
            'title'      => $p->post_title,
            'cvr_nummer' => $cvr_nummer,
            'synced_at'  => $synced_at,
            'edit_url'   => get_edit_post_link( $p->ID, 'raw' ),
        );
    }

    // Pass data to JS before the script runs.
    wp_add_inline_script(
        'dianalund-cvr-sync-admin',
        'DianalundCVR.local_posts = ' . wp_json_encode( $local_posts_data ) . ';'
        . 'DianalundCVR.current_cpt = ' . wp_json_encode( $cpt ) . ';',
        'before'
    );

    $base_url = admin_url( 'admin.php?page=dianalund-cvr-compare' );
    ?>
    <div class="wrap" id="dianalund-cvr-compare-page">
        <h1><?php echo esc_html__( 'Sammenlign & Synk CVR', 'dianalund_txtdomain' ); ?></h1>
        <p>
            <?php
            echo esc_html__( 'Sammenlign dine lokale poster med virksomheder fra CVR-registret. Søg i CVR til højre og brug handlingerne til at opdatere eller oprette lokale poster.', 'dianalund_txtdomain' );
            ?>
        </p>

        <p>
            <button type="button" id="dianalund-cvr-auto-link-btn" class="button button-secondary">
                <?php echo esc_html__( 'Auto-link CVR (kun status NORMAL)', 'dianalund_txtdomain' ); ?>
            </button>
            <span id="dianalund-cvr-auto-link-spinner" class="spinner" style="float:none;visibility:hidden;vertical-align:middle;margin-left:8px;"></span>
        </p>
        <div id="dianalund-cvr-auto-link-result" style="margin-top:8px;"></div>

        <nav class="cvr-cpt-tabs" aria-label="<?php echo esc_attr__( 'Vælg indholdstype', 'dianalund_txtdomain' ); ?>">
            <?php foreach ( $allowed_cpts as $tab_cpt ) : ?>
                <a
                    href="<?php echo esc_url( add_query_arg( 'cpt', $tab_cpt, $base_url ) ); ?>"
                    class="<?php echo $tab_cpt === $cpt ? 'cvr-tab--active' : ''; ?>"
                    aria-current="<?php echo $tab_cpt === $cpt ? 'page' : 'false'; ?>"
                >
                    <?php echo esc_html( $cpt_labels[ $tab_cpt ] ); ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="cvr-compare-wrap">

            <!-- LOCAL POSTS COLUMN -->
            <div class="cvr-compare-col" id="cvr-col-local">
                <h2>
                    <?php
                    printf(
                        /* translators: %d: number of local posts */
                        esc_html__( 'Lokale poster (%d)', 'dianalund_txtdomain' ),
                        count( $posts )
                    );
                    ?>
                </h2>
                <div class="cvr-search-bar">
                    <label for="dianalund-local-filter" class="screen-reader-text">
                        <?php echo esc_html__( 'Filtrer lokale poster', 'dianalund_txtdomain' ); ?>
                    </label>
                    <input
                        type="text"
                        id="dianalund-local-filter"
                        placeholder="<?php echo esc_attr__( 'Filtrer lokale poster...', 'dianalund_txtdomain' ); ?>"
                        class="regular-text"
                    />
                </div>
                <table class="cvr-compare-table widefat striped">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__( 'Navn', 'dianalund_txtdomain' ); ?></th>
                            <th><?php echo esc_html__( 'CVR nr.', 'dianalund_txtdomain' ); ?></th>
                            <th><?php echo esc_html__( 'Status', 'dianalund_txtdomain' ); ?></th>
                            <th><?php echo esc_html__( 'Handlinger', 'dianalund_txtdomain' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="dianalund-local-list">
                        <?php if ( empty( $posts ) ) : ?>
                            <tr>
                                <td colspan="4">
                                    <?php echo esc_html__( 'Ingen lokale poster fundet.', 'dianalund_txtdomain' ); ?>
                                </td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ( $posts as $p ) :
                                $cvr_nummer = (string) get_post_meta( $p->ID, 'dianalund_cvr_nummer', true );
                                $synced_at  = (string) get_post_meta( $p->ID, 'dianalund_cvr_synced_at', true );

                                if ( $synced_at ) {
                                    $badge_key = 'synced';
                                    $badge_lbl = __( 'Synkroniseret', 'dianalund_txtdomain' );
                                } elseif ( $cvr_nummer ) {
                                    $badge_key = 'linked';
                                    $badge_lbl = __( 'Linket', 'dianalund_txtdomain' );
                                } else {
                                    $badge_key = 'unlinked';
                                    $badge_lbl = __( 'Ikke linket', 'dianalund_txtdomain' );
                                }
                            ?>
                            <tr
                                data-post-id="<?php echo esc_attr( (string) $p->ID ); ?>"
                                <?php if ( $cvr_nummer ) : ?>
                                    data-cvr="<?php echo esc_attr( $cvr_nummer ); ?>"
                                <?php endif; ?>
                            >
                                <td class="local-col-navn">
                                    <a href="<?php echo esc_url( (string) get_edit_post_link( $p->ID ) ); ?>" target="_blank">
                                        <?php echo esc_html( $p->post_title ); ?>
                                    </a>
                                </td>
                                <td class="local-col-cvr">
                                    <?php echo $cvr_nummer ? esc_html( $cvr_nummer ) : '<span class="cvr-empty">—</span>'; ?>
                                </td>
                                <td>
                                    <span class="cvr-badge cvr-badge--<?php echo esc_attr( $badge_key ); ?>">
                                        <?php echo esc_html( $badge_lbl ); ?>
                                    </span>
                                </td>
                                <td class="local-col-actions">
                                    <button
                                        type="button"
                                        class="button button-small local-action-find-cvr"
                                        data-post-id="<?php echo esc_attr( (string) $p->ID ); ?>"
                                        data-search="<?php echo esc_attr( $p->post_title ); ?>"
                                    >
                                        <?php echo esc_html__( 'Find i CVR', 'dianalund_txtdomain' ); ?>
                                    </button>
                                    <button
                                        type="button"
                                        class="button button-small button-link-delete local-action-delete-post"
                                        data-post-id="<?php echo esc_attr( (string) $p->ID ); ?>"
                                    >
                                        <?php echo esc_html__( 'Slet', 'dianalund_txtdomain' ); ?>
                                    </button>
                                    <?php if ( $cvr_nummer ) : ?>
                                        <button
                                            type="button"
                                            class="button button-small local-action-update"
                                            data-post-id="<?php echo esc_attr( (string) $p->ID ); ?>"
                                            data-cvr="<?php echo esc_attr( $cvr_nummer ); ?>"
                                        >
                                            <?php echo esc_html__( 'Opdatér fra CVR', 'dianalund_txtdomain' ); ?>
                                        </button>
                                        <button
                                            type="button"
                                            class="button button-small button-link-delete local-action-unlink"
                                            data-post-id="<?php echo esc_attr( (string) $p->ID ); ?>"
                                        >
                                            <?php echo esc_html__( 'Fjern link', 'dianalund_txtdomain' ); ?>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div><!-- #cvr-col-local -->

            <!-- CVR RESULTS COLUMN -->
            <div class="cvr-compare-col" id="cvr-col-remote">
                <h2><?php echo esc_html__( 'CVR virksomheder', 'dianalund_txtdomain' ); ?></h2>
                <form id="dianalund-cvr-search-form" class="cvr-search-bar" novalidate>
                    <label for="dianalund-cvr-search-input" class="screen-reader-text">
                        <?php echo esc_html__( 'Søg i CVR', 'dianalund_txtdomain' ); ?>
                    </label>
                    <input
                        type="text"
                        id="dianalund-cvr-search-input"
                        placeholder="<?php echo esc_attr__( 'Søg på navn (tomt = hent alle)...', 'dianalund_txtdomain' ); ?>"
                        class="regular-text"
                    />
                    <label for="dianalund-cvr-postnr-input" class="screen-reader-text">
                        <?php echo esc_html__( 'Filtrer på postnummer', 'dianalund_txtdomain' ); ?>
                    </label>
                    <input
                        type="text"
                        id="dianalund-cvr-postnr-input"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        maxlength="4"
                        placeholder="<?php echo esc_attr__( 'Postnr (fx 4293)', 'dianalund_txtdomain' ); ?>"
                        class="small-text"
                    />
                    <label for="dianalund-cvr-only-active" class="cvr-filter-check">
                        <input type="checkbox" id="dianalund-cvr-only-active" checked="checked" />
                        <?php echo esc_html__( 'Kun aktive virksomheder', 'dianalund_txtdomain' ); ?>
                    </label>
                    <button type="submit" class="button button-primary">
                        <?php echo esc_html__( 'Hent CVR-liste', 'dianalund_txtdomain' ); ?>
                    </button>
                    <span
                        id="dianalund-cvr-search-spinner"
                        class="spinner"
                        style="float:none;visibility:hidden;vertical-align:middle;margin-left:4px;"
                    ></span>
                </form>
                <p id="dianalund-cvr-count-wrap" style="display:none;font-size:12px;color:#555;margin:4px 0 8px;">
                    <?php echo esc_html__( 'Viser', 'dianalund_txtdomain' ); ?>
                    <strong id="dianalund-cvr-count-shown">0</strong>
                    <?php echo esc_html__( 'af', 'dianalund_txtdomain' ); ?>
                    <strong id="dianalund-cvr-count-total">0</strong>
                    <?php echo esc_html__( 'aktive virksomheder', 'dianalund_txtdomain' ); ?>
                </p>
                <div id="dianalund-cvr-pager" class="cvr-pager" style="display:none;">
                    <button type="button" id="dianalund-cvr-prev-page" class="button button-small">
                        <?php echo esc_html__( 'Forrige', 'dianalund_txtdomain' ); ?>
                    </button>
                    <span id="dianalund-cvr-page-meta" class="cvr-pager-meta"></span>
                    <button type="button" id="dianalund-cvr-next-page" class="button button-small">
                        <?php echo esc_html__( 'Næste', 'dianalund_txtdomain' ); ?>
                    </button>
                </div>
                <table class="cvr-compare-table widefat striped">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__( 'Navn / Branche', 'dianalund_txtdomain' ); ?></th>
                            <th><?php echo esc_html__( 'CVR nr.', 'dianalund_txtdomain' ); ?></th>
                            <th><?php echo esc_html__( 'By', 'dianalund_txtdomain' ); ?></th>
                            <th><?php echo esc_html__( 'Handlinger', 'dianalund_txtdomain' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="dianalund-cvr-list">
                        <tr>
                            <td colspan="4">
                                <?php echo esc_html__( 'Klik "Hent CVR-liste" for at hente virksomheder.', 'dianalund_txtdomain' ); ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div><!-- #cvr-col-remote -->

        </div><!-- .cvr-compare-wrap -->
    </div><!-- .wrap -->
    <?php
}
