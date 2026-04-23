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
 * @param string $search Search term.
 * @param int    $size   Page size.
 *
 * @return array<string, mixed>
 */
function dianalund_cvr_sync_build_active_company_query( $search = '', $size = 50 ) {
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
        'size'    => max( 1, absint( $size ) ),
        'query'   => array(
            'bool' => array(
                'must'   => array(),
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

    $search = trim( (string) $search );
    if ( '' !== $search ) {
        $query['query']['bool']['must'][] = array(
            'query_string' => array(
                'default_field' => 'Vrvirksomhed.virksomhedMetadata.nyesteNavn.navn',
                'query'         => $search,
            ),
        );
    }

    return $query;
}

/**
 * Performs a CVR Elasticsearch search request.
 *
 * @param array<string, mixed> $body Query body.
 *
 * @return array<string, mixed>|WP_Error
 */
function dianalund_cvr_sync_search( $body ) {
    $settings    = dianalund_cvr_sync_get_settings();
    $credentials = dianalund_cvr_sync_get_credentials();
    $endpoint    = isset( $settings['endpoint'] ) ? esc_url_raw( (string) $settings['endpoint'] ) : '';
    $timeout     = isset( $settings['timeout'] ) ? absint( $settings['timeout'] ) : 20;

    if ( '' === $endpoint ) {
        return new WP_Error( 'dianalund_cvr_missing_endpoint', __( 'CVR endpoint mangler.', 'dianalund_txtdomain' ) );
    }

    if ( '' === $credentials['username'] || '' === $credentials['password'] ) {
        return new WP_Error( 'dianalund_cvr_missing_credentials', __( 'CVR brugernavn/adgangskode mangler.', 'dianalund_txtdomain' ) );
    }

    $response = wp_remote_post(
        $endpoint,
        array(
            'timeout' => max( 5, $timeout ),
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( $credentials['username'] . ':' . $credentials['password'] ),
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode( $body ),
        )
    );

    if ( is_wp_error( $response ) ) {
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
    </div>
    <?php
}
