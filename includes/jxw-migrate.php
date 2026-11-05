<?php
/**
 * Idempotent migration of legacy CPT meta into jxw_* keys.
 *
 * Default is dry-run. Old keys are never deleted or overwritten.
 *
 * @package Dianalund_CPT
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'JXW_MIGRATE_BATCH' ) ) {
	define( 'JXW_MIGRATE_BATCH', 50 );
}

/**
 * Field keys copied for every supported CPT (hours handled separately).
 *
 * @return string[]
 */
function jxw_migrate_contact_keys() {
	return array(
		'jxw_name',
		'jxw_street',
		'jxw_postal',
		'jxw_city',
		'jxw_phone',
		'jxw_email',
		'jxw_web',
		'jxw_facebook',
		'jxw_instagram',
		'jxw_paid',
	);
}

/**
 * Builds one report row.
 *
 * @param int    $post_id Post ID.
 * @param string $field   Field key.
 * @param string $old     Old value.
 * @param string $new     New value.
 * @param string $status  kopieret|sprunget over|fejl|ikke angivet.
 * @return array<string, string>
 */
function jxw_migrate_row( $post_id, $field, $old, $new, $status ) {
	return array(
		'post_id' => (string) (int) $post_id,
		'field'   => $field,
		'old'     => $old,
		'new'     => $new,
		'status'  => $status,
	);
}

/**
 * Sanitizes a value with the field's callback.
 *
 * @param string $field_key Field key.
 * @param mixed  $value     Raw value.
 * @return string
 */
function jxw_migrate_sanitize_value( $field_key, $value ) {
	$definitions = jxw_field_definitions();
	if ( ! isset( $definitions[ $field_key ]['sanitize'] ) ) {
		return is_scalar( $value ) ? (string) $value : '';
	}

	$callback = $definitions[ $field_key ]['sanitize'];
	if ( ! is_callable( $callback ) ) {
		return is_scalar( $value ) ? (string) $value : '';
	}

	$result = call_user_func( $callback, $value );

	return is_scalar( $result ) ? (string) $result : '';
}

/**
 * Migrates one post. Never overwrites a filled new key. Never touches old keys.
 *
 * @param int  $post_id Post ID.
 * @param bool $dry_run Whether to skip writes.
 * @return array<int, array<string, string>>
 */
function jxw_migrate_one_post( $post_id, $dry_run ) {
	$post_id   = (int) $post_id;
	$post_type = get_post_type( $post_id );
	$rows      = array();

	if ( ! jxw_is_supported_post_type( $post_type ) ) {
		$rows[] = jxw_migrate_row( $post_id, '', '', '', 'fejl' );

		return $rows;
	}

	foreach ( jxw_migrate_contact_keys() as $field_key ) {
		$legacy_key = jxw_legacy_meta_key( $post_type, $field_key );
		if ( '' === $legacy_key ) {
			continue;
		}

		$old_raw = get_post_meta( $post_id, $legacy_key, true );
		$old     = is_scalar( $old_raw ) ? (string) $old_raw : '';
		$current = get_post_meta( $post_id, $field_key, true );

		if ( jxw_field_has_value( $current ) ) {
			$rows[] = jxw_migrate_row( $post_id, $field_key, $old, (string) $current, 'sprunget over' );
			continue;
		}

		if ( ! jxw_field_has_value( $old ) ) {
			continue;
		}

		$sanitized = jxw_migrate_sanitize_value( $field_key, $old );
		if ( 'jxw_paid' === $field_key ) {
			$sanitized = jxw_normalize_paid_value( $old );
		}

		if ( '' === $sanitized && 'jxw_paid' !== $field_key ) {
			$rows[] = jxw_migrate_row( $post_id, $field_key, $old, '', 'fejl' );
			continue;
		}

		if ( ! $dry_run ) {
			update_post_meta( $post_id, $field_key, $sanitized );
		}

		$rows[] = jxw_migrate_row( $post_id, $field_key, $old, $sanitized, 'kopieret' );
	}

	if ( 'butiksside' === $post_type ) {
		$rows = array_merge( $rows, jxw_migrate_hours_for_post( $post_id, $dry_run ) );
	}

	if ( ! $dry_run ) {
		update_post_meta( $post_id, '_jxw_fields_migrated', JXW_FIELDS_VERSION );
	}

	return $rows;
}

/**
 * Migrates shop opening hours.
 *
 * @param int  $post_id Post ID.
 * @param bool $dry_run Dry run.
 * @return array<int, array<string, string>>
 */
function jxw_migrate_hours_for_post( $post_id, $dry_run ) {
	$rows    = array();
	$current = get_post_meta( $post_id, 'jxw_hours', true );

	if ( jxw_field_has_value( $current ) ) {
		$rows[] = jxw_migrate_row( $post_id, 'jxw_hours', '', (string) $current, 'sprunget over' );

		return $rows;
	}

	$built   = jxw_hours_from_legacy_post( $post_id );
	$payload = $built['payload'];
	$source  = $built['source'];

	if ( '' === $source ) {
		return $rows;
	}

	$json = jxw_sanitize_hours_payload( $payload );
	if ( '' === $json ) {
		$rows[] = jxw_migrate_row( $post_id, 'jxw_hours', $source, '', 'fejl' );

		return $rows;
	}

	if ( ! $dry_run ) {
		update_post_meta( $post_id, 'jxw_hours', $json );
	}

	$rows[] = jxw_migrate_row( $post_id, 'jxw_hours', $source, $json, 'kopieret' );

	foreach ( $built['inherited_days'] as $day ) {
		$rows[] = jxw_migrate_row( $post_id, 'jxw_hours:' . $day, 'inherit', '', 'ikke angivet' );
	}

	foreach ( jxw_weekday_keys() as $day ) {
		$status = isset( $payload['days'][ $day ]['status'] ) ? $payload['days'][ $day ]['status'] : '';
		$from   = isset( $payload['days'][ $day ]['from'] ) ? $payload['days'][ $day ]['from'] : '';
		$to     = isset( $payload['days'][ $day ]['to'] ) ? $payload['days'][ $day ]['to'] : '';
		if ( '' === $status || ( 'open' === $status && '' === $from && '' === $to ) ) {
			if ( ! in_array( $day, $built['inherited_days'], true ) ) {
				$rows[] = jxw_migrate_row( $post_id, 'jxw_hours:' . $day, $source, '', 'ikke angivet' );
			}
		}
	}

	return $rows;
}

/**
 * Runs migration over a query of posts.
 *
 * @param array<string, mixed> $args {
 *     @type bool          $dry_run   Default true.
 *     @type string|array  $post_type Post type or list.
 *     @type int           $limit     Max posts, 0 = all.
 * }
 * @return array{rows: array, dry_run: bool, scanned: int, copied: int, skipped: int, errors: int, unset_days: int}
 */
function jxw_run_field_migration( $args = array() ) {
	$dry_run   = ! isset( $args['dry_run'] ) || (bool) $args['dry_run'];
	$post_type = isset( $args['post_type'] ) && '' !== $args['post_type'] ? $args['post_type'] : jxw_supported_post_types();
	$limit     = isset( $args['limit'] ) ? max( 0, (int) $args['limit'] ) : 0;

	if ( is_string( $post_type ) ) {
		$post_type = array( $post_type );
	}
	$post_type = array_values( array_intersect( $post_type, jxw_supported_post_types() ) );
	if ( empty( $post_type ) ) {
		$post_type = jxw_supported_post_types();
	}

	$rows     = array();
	$scanned  = 0;
	$paged    = 1;
	$per_page = JXW_MIGRATE_BATCH;

	do {
		$remaining = ( $limit > 0 ) ? $limit - $scanned : $per_page;
		if ( $limit > 0 && $remaining <= 0 ) {
			break;
		}

		$query = new WP_Query(
			array(
				'post_type'              => $post_type,
				'post_status'            => 'any',
				'posts_per_page'         => ( $limit > 0 ) ? min( $per_page, $remaining ) : $per_page,
				'paged'                  => $paged,
				'fields'                 => 'ids',
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => false,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
			)
		);

		if ( empty( $query->posts ) ) {
			break;
		}

		foreach ( $query->posts as $post_id ) {
			$rows = array_merge( $rows, jxw_migrate_one_post( (int) $post_id, $dry_run ) );
			$scanned++;
			if ( $limit > 0 && $scanned >= $limit ) {
				break;
			}
		}

		$paged++;
		$found = (int) $query->max_num_pages;
		wp_reset_postdata();
	} while ( $paged <= $found && ( 0 === $limit || $scanned < $limit ) );

	$copied  = 0;
	$skipped = 0;
	$errors  = 0;
	$unset   = 0;
	foreach ( $rows as $row ) {
		if ( 'kopieret' === $row['status'] ) {
			$copied++;
		} elseif ( 'sprunget over' === $row['status'] ) {
			$skipped++;
		} elseif ( 'fejl' === $row['status'] ) {
			$errors++;
		} elseif ( 'ikke angivet' === $row['status'] ) {
			$unset++;
		}
	}

	$summary = array(
		'rows'       => $rows,
		'dry_run'    => $dry_run,
		'scanned'    => $scanned,
		'copied'     => $copied,
		'skipped'    => $skipped,
		'errors'     => $errors,
		'unset_days' => $unset,
		'ran_at'     => function_exists( 'current_time' ) ? current_time( 'mysql' ) : gmdate( 'Y-m-d H:i:s' ),
	);

	update_option( 'jxw_migrate_last_report', $summary, false );

	return $summary;
}

/**
 * CSV string for a report.
 *
 * @param array<int, array<string, string>> $rows Report rows.
 * @return string
 */
function jxw_migrate_report_csv( $rows ) {
	$fh = fopen( 'php://temp', 'r+' );
	fputcsv( $fh, array( 'post_id', 'field', 'old', 'new', 'status' ) );
	foreach ( $rows as $row ) {
		fputcsv(
			$fh,
			array(
				$row['post_id'],
				$row['field'],
				$row['old'],
				$row['new'],
				$row['status'],
			)
		);
	}
	rewind( $fh );
	$csv = stream_get_contents( $fh );
	fclose( $fh );

	return is_string( $csv ) ? $csv : '';
}

/**
 * Registers the Tools admin page.
 *
 * @return void
 */
function jxw_migrate_register_tools_page() {
	add_management_page(
		__( 'JXW feltmigrering', 'dianalund_txtdomain' ),
		__( 'JXW feltmigrering', 'dianalund_txtdomain' ),
		'manage_options',
		'jxw-migrate-fields',
		'jxw_migrate_render_tools_page'
	);
}
add_action( 'admin_menu', 'jxw_migrate_register_tools_page' );

/**
 * Handles admin form posts.
 *
 * @return void
 */
function jxw_migrate_handle_admin_post() {
	if ( ! isset( $_POST['jxw_migrate_nonce'] ) ) {
		return;
	}

	$posted = wp_unslash( $_POST );
	if ( ! wp_verify_nonce( isset( $posted['jxw_migrate_nonce'] ) ? $posted['jxw_migrate_nonce'] : '', 'jxw_migrate_fields' ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Du har ikke tilladelse til at køre migrering.', 'dianalund_txtdomain' ) );
	}

	$dry_run   = empty( $posted['jxw_migrate_live'] );
	$post_type = isset( $posted['jxw_post_type'] ) ? sanitize_key( $posted['jxw_post_type'] ) : '';
	$limit     = isset( $posted['jxw_limit'] ) ? absint( $posted['jxw_limit'] ) : 0;

	if ( isset( $posted['jxw_download_csv'] ) ) {
		$report = get_option( 'jxw_migrate_last_report', array() );
		$rows   = isset( $report['rows'] ) && is_array( $report['rows'] ) ? $report['rows'] : array();
		$csv    = jxw_migrate_report_csv( $rows );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=jxw-migrate-report.csv' );
		echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	jxw_run_field_migration(
		array(
			'dry_run'   => $dry_run,
			'post_type' => $post_type,
			'limit'     => $limit,
		)
	);

	wp_safe_redirect(
		add_query_arg(
			array(
				'page'        => 'jxw-migrate-fields',
				'jxw-ran'     => '1',
				'jxw-dry-run' => $dry_run ? '1' : '0',
			),
			admin_url( 'tools.php' )
		)
	);
	exit;
}
add_action( 'admin_init', 'jxw_migrate_handle_admin_post' );

/**
 * Renders the Tools page.
 *
 * @return void
 */
function jxw_migrate_render_tools_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$report = get_option( 'jxw_migrate_last_report', array() );
	$rows   = isset( $report['rows'] ) && is_array( $report['rows'] ) ? $report['rows'] : array();
	?>
	<div class="wrap">
		<h1><?php echo esc_html__( 'JXW feltmigrering', 'dianalund_txtdomain' ); ?></h1>
		<p><?php echo esc_html__( 'Kopierer gamle meta-nøgler til jxw_* hvis den nye nøgle er tom. Overskriver aldrig. Sletter aldrig gamle nøgler. Standard er tør kørsel.', 'dianalund_txtdomain' ); ?></p>

		<form method="post" action="<?php echo esc_url( admin_url( 'tools.php?page=jxw-migrate-fields' ) ); ?>">
			<?php wp_nonce_field( 'jxw_migrate_fields', 'jxw_migrate_nonce' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="jxw_post_type"><?php echo esc_html__( 'Indholdstype', 'dianalund_txtdomain' ); ?></label></th>
					<td>
						<select name="jxw_post_type" id="jxw_post_type">
							<option value=""><?php echo esc_html__( 'Alle tre (butik, erhverv, forening)', 'dianalund_txtdomain' ); ?></option>
							<?php foreach ( jxw_supported_post_types() as $type ) : ?>
								<option value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $type ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="jxw_limit"><?php echo esc_html__( 'Maks. antal poster (0 = alle)', 'dianalund_txtdomain' ); ?></label></th>
					<td><input type="number" min="0" name="jxw_limit" id="jxw_limit" value="0" class="small-text" /></td>
				</tr>
			</table>
			<?php submit_button( __( 'Tør kørsel', 'dianalund_txtdomain' ), 'secondary', 'jxw_migrate_dry' ); ?>
			<?php submit_button( __( 'Kør migrering', 'dianalund_txtdomain' ), 'primary', 'jxw_migrate_live' ); ?>
			<?php if ( ! empty( $rows ) ) : ?>
				<?php submit_button( __( 'Download CSV', 'dianalund_txtdomain' ), 'secondary', 'jxw_download_csv', false ); ?>
			<?php endif; ?>
		</form>

		<?php if ( ! empty( $report ) ) : ?>
			<h2><?php echo esc_html__( 'Seneste rapport', 'dianalund_txtdomain' ); ?></h2>
			<p>
				<?php
				printf(
					/* translators: 1: scanned, 2: copied, 3: skipped, 4: errors, 5: unset days, 6: dry/live, 7: timestamp */
					esc_html__( 'Gennemgået: %1$d. Kopieret: %2$d. Sprunget over: %3$d. Fejl: %4$d. Ikke angivet: %5$d. Tilstand: %6$s. Tid: %7$s.', 'dianalund_txtdomain' ),
					isset( $report['scanned'] ) ? (int) $report['scanned'] : 0,
					isset( $report['copied'] ) ? (int) $report['copied'] : 0,
					isset( $report['skipped'] ) ? (int) $report['skipped'] : 0,
					isset( $report['errors'] ) ? (int) $report['errors'] : 0,
					isset( $report['unset_days'] ) ? (int) $report['unset_days'] : 0,
					! empty( $report['dry_run'] ) ? esc_html__( 'tør kørsel', 'dianalund_txtdomain' ) : esc_html__( 'skrevet', 'dianalund_txtdomain' ),
					isset( $report['ran_at'] ) ? esc_html( $report['ran_at'] ) : ''
				);
				?>
			</p>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'Post-id', 'dianalund_txtdomain' ); ?></th>
						<th><?php echo esc_html__( 'Felt', 'dianalund_txtdomain' ); ?></th>
						<th><?php echo esc_html__( 'Gammel værdi', 'dianalund_txtdomain' ); ?></th>
						<th><?php echo esc_html__( 'Ny værdi', 'dianalund_txtdomain' ); ?></th>
						<th><?php echo esc_html__( 'Status', 'dianalund_txtdomain' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row['post_id'] ); ?></td>
							<td><?php echo esc_html( $row['field'] ); ?></td>
							<td><?php echo esc_html( wp_html_excerpt( $row['old'], 80, '…' ) ); ?></td>
							<td><?php echo esc_html( wp_html_excerpt( $row['new'], 80, '…' ) ); ?></td>
							<td><?php echo esc_html( $row['status'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * WP-CLI: wp jxw migrate-fields [--dry-run] [--live] [--post-type=] [--limit=]
	 */
	class JXW_CLI_Command {

		/**
		 * Copy legacy CPT meta into empty jxw_* keys.
		 *
		 * ## OPTIONS
		 *
		 * [--dry-run]
		 * : Preview without writing. Default when --live is omitted.
		 *
		 * [--live]
		 * : Write new keys. Old keys are never changed.
		 *
		 * [--post-type=<type>]
		 * : butiksside, erhvervsside or foreningsside.
		 *
		 * [--limit=<n>]
		 * : Max posts to process.
		 *
		 * ## EXAMPLES
		 *
		 *     wp jxw migrate-fields
		 *     wp jxw migrate-fields --live --post-type=butiksside --limit=20
		 *
		 * @when after_wp_load
		 *
		 * @param array $args       Positional.
		 * @param array $assoc_args Flags.
		 * @return void
		 */
		public function migrate_fields( $args, $assoc_args ) {
			unset( $args );

			$dry_run = true;
			if ( ! empty( $assoc_args['live'] ) ) {
				$dry_run = false;
			}
			if ( isset( $assoc_args['dry-run'] ) ) {
				$dry_run = true;
			}

			$post_type = isset( $assoc_args['post-type'] ) ? sanitize_key( $assoc_args['post-type'] ) : '';
			$limit     = isset( $assoc_args['limit'] ) ? absint( $assoc_args['limit'] ) : 0;

			$summary = jxw_run_field_migration(
				array(
					'dry_run'   => $dry_run,
					'post_type' => $post_type,
					'limit'     => $limit,
				)
			);

			WP_CLI::log(
				sprintf(
					'Scanned %d posts (%s). Copied %d, skipped %d, errors %d, unset days %d.',
					$summary['scanned'],
					$summary['dry_run'] ? 'dry-run' : 'live',
					$summary['copied'],
					$summary['skipped'],
					$summary['errors'],
					$summary['unset_days']
				)
			);

			if ( ! empty( $summary['rows'] ) && class_exists( 'WP_CLI\Utils' ) ) {
				WP_CLI\Utils\format_items( 'table', $summary['rows'], array( 'post_id', 'field', 'old', 'new', 'status' ) );
			}

			if ( $summary['errors'] > 0 ) {
				WP_CLI::warning( 'Completed with errors.' );
			} else {
				WP_CLI::success( 'Migration finished.' );
			}
		}
	}

	WP_CLI::add_command( 'jxw', 'JXW_CLI_Command' );
}
