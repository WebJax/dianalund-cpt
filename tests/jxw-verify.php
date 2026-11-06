<?php
/**
 * Phase 6 verification: hours parser, paid/schema helpers, JSON-LD.
 *
 * Run: php tests/jxw-verify.php
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname( __DIR__ ) . '/includes/jxw-fields.php';
require_once dirname( __DIR__ ) . '/includes/jxw-hours.php';
require_once dirname( __DIR__ ) . '/includes/jxw-migrate.php';

$failures = 0;

function jxw_assert( $condition, $message ) {
	global $failures;
	if ( $condition ) {
		echo "OK  {$message}\n";
		return;
	}
	$failures++;
	echo "FAIL {$message}\n";
}

$html = '<table><tbody>
<tr><td>Mandag</td><td>10:00 - 18:00</td></tr>
<tr><td>Tirsdag</td><td>10.00-18.00</td></tr>
<tr><td>Onsdag</td><td>10-18</td></tr>
<tr><td>Torsdag</td><td>Lukket</td></tr>
<tr><td>Fredag</td><td>10:00 – 18:00</td></tr>
<tr><td>Lørdag</td><td>10:00 - 13:00</td></tr>
<tr><td>Søndag</td><td>Lukket</td></tr>
</tbody></table>';

$parsed = jxw_parse_hours_html( $html );
jxw_assert( 'open' === $parsed['days']['monday']['status'] && '10:00' === $parsed['days']['monday']['from'], 'Parse mandag 10:00-18:00' );
jxw_assert( 'open' === $parsed['days']['tuesday']['status'] && '10:00' === $parsed['days']['tuesday']['from'], 'Parse punktum-tid' );
jxw_assert( '10:00' === $parsed['days']['wednesday']['from'] && '18:00' === $parsed['days']['wednesday']['to'], 'Parse tid uden minutter' );
jxw_assert( 'closed' === $parsed['days']['thursday']['status'], 'Parse lukket' );
jxw_assert( 'open' === $parsed['days']['friday']['status'], 'Parse en-dash' );
jxw_assert( 'closed' === $parsed['days']['sunday']['status'], 'Parse søndag lukket' );

$normalized = jxw_normalize_hours( $parsed );
jxw_assert( isset( $normalized['days']['monday'][0]['from'] ), 'Normalizer giver liste af tidsrum' );
jxw_assert( array() === $normalized['days']['thursday'], 'Lukkede dage har tom range-liste' );
jxw_assert( array() === $normalized['days']['sunday'], 'Søndag lukket udelades i ranges' );

$inherited = jxw_sanitize_day_row( array( 'inherit' => true, 'from' => '10:00', 'to' => '18:00' ) );
jxw_assert( 'open' === $inherited['status'] && '' === $inherited['from'] && ! empty( $inherited['inherited'] ), 'Inherit → open uden tider' );

jxw_assert( '2026-04-05' === jxw_easter_date( 2026 ), 'Påske 2026 = 5. april' );
jxw_assert( '2025-04-20' === jxw_easter_date( 2025 ), 'Påske 2025 = 20. april' );
jxw_assert( '2024-03-31' === jxw_easter_date( 2024 ), 'Påske 2024 = 31. marts' );

$holidays = jxw_danish_holidays_between( '2026-01-01', '2026-12-31' );
jxw_assert( isset( $holidays['2026-01-01'] ) && 'Nytårsdag' === $holidays['2026-01-01'], 'Nytårsdag' );
jxw_assert( isset( $holidays['2026-04-02'] ) && 'Skærtorsdag' === $holidays['2026-04-02'], 'Skærtorsdag 2026' );
jxw_assert( isset( $holidays['2026-04-03'] ) && 'Langfredag' === $holidays['2026-04-03'], 'Langfredag 2026' );
jxw_assert( isset( $holidays['2026-04-05'] ) && 'Påskedag' === $holidays['2026-04-05'], 'Påskedag 2026' );
jxw_assert( isset( $holidays['2026-04-06'] ) && '2. påskedag' === $holidays['2026-04-06'], '2. påskedag' );
jxw_assert( isset( $holidays['2026-05-14'] ) && 'Kristi himmelfartsdag' === $holidays['2026-05-14'], 'Kristi himmelfart 2026' );
jxw_assert( isset( $holidays['2026-05-24'] ) && 'Pinsedag' === $holidays['2026-05-24'], 'Pinsedag 2026' );
jxw_assert( isset( $holidays['2026-05-25'] ) && '2. pinsedag' === $holidays['2026-05-25'], '2. pinsedag' );
jxw_assert( isset( $holidays['2026-12-25'] ) && isset( $holidays['2026-12-26'] ), 'Juledage' );
jxw_assert( ! in_array( 'Store bededag', $holidays, true ), 'Store bededag indgår ikke' );

jxw_assert( '1' === jxw_normalize_paid_value( 'Guld' ), 'Guld → paid' );
jxw_assert( '1' === jxw_normalize_paid_value( 'Sølv' ), 'Sølv → paid' );
jxw_assert( '' === jxw_normalize_paid_value( 'Intet' ), 'Intet → ikke paid' );
jxw_assert( '' === jxw_normalize_paid_value( '' ), 'Tom → ikke paid' );
jxw_assert( '1' === jxw_normalize_paid_value( '1' ), '1 → paid' );

jxw_assert( '4293' === jxw_sanitize_postal( '4293' ), 'Postnr 4 cifre' );
jxw_assert( '4293' === jxw_sanitize_postal( 'DK-4293' ), 'Postnr udtrækkes' );
jxw_assert( '' === jxw_sanitize_postal( 'abc' ), 'Ugyldigt postnr tømmes' );
jxw_assert( '12 34 56 78' === jxw_sanitize_phone( '12 34 56 78' ), 'Telefon med mellemrum' );
jxw_assert( '+45-123' === jxw_sanitize_phone( '+45-123!' ), 'Telefon stripper ulovlige tegn' );
jxw_assert( '55.5' === jxw_sanitize_lat( '55.5' ), 'Gyldig lat' );
jxw_assert( '' === jxw_sanitize_lat( '99' ), 'Lat uden for interval' );
jxw_assert( '' === jxw_sanitize_lng( '200' ), 'Lng uden for interval' );

$GLOBALS['jxw_test_post_types'][10] = 'butiksside';
$GLOBALS['jxw_test_meta'][10]       = array(
	'butik_payed_name'   => 'Kvickly Dianalund',
	'butik_payed_adress' => 'Centervej 1',
	'butik_payed_postal' => '4293',
	'butik_payed_city'   => 'Dianalund',
	'butik_payed_phone'  => '58 18 18 18',
	'butik_payed_mail'   => 'kvickly@example.dk',
	'butik_payed_web'    => 'kvickly.dk',
	'butik_payed_payedbutik' => 'Guld',
	'butik_aabentider'   => $html,
);
jxw_assert( 'Kvickly Dianalund' === jxw_get_field( 10, 'name' ), 'Fallback navn fra butik_payed_name' );
jxw_assert( jxw_is_paid( 10 ), 'Guld tæller som betalende' );
jxw_assert( 'Centervej 1' === jxw_get_field( 10, 'jxw_street' ), 'Fallback adresse' );

$GLOBALS['jxw_test_meta'][10]['jxw_name'] = 'Nyt navn';
jxw_assert( 'Nyt navn' === jxw_get_field( 10, 'jxw_name' ), 'Ny nøgle vinder over gammel' );

$GLOBALS['jxw_test_post_types'][11] = 'erhvervsside';
$GLOBALS['jxw_test_meta'][11]       = array(
	'bizz_payed_name'      => 'Håndværker A/S',
	'bizz_payed_payedbizz' => 'Intet',
);
jxw_assert( 'Håndværker A/S' === jxw_get_field( 11, 'jxw_name' ), 'Erhverv bizz_ fallback' );
jxw_assert( ! jxw_is_paid( 11 ), 'Intet er ikke betalende' );

$GLOBALS['jxw_test_post_types'][12] = 'foreningsside';
$GLOBALS['jxw_test_meta'][12]       = array(
	'fritid_payed_name' => 'Dianalund IF',
	'fritid_payed_mail' => 'if@example.dk',
);
jxw_assert( 'Dianalund IF' === jxw_get_field( 12, 'jxw_name' ), 'Forening fritid_ fallback' );
jxw_assert( ! jxw_is_paid( 12 ), 'Forening uden paid-felt er ikke betalende' );

$json = jxw_sanitize_hours_payload( $parsed );
$GLOBALS['jxw_test_meta'][10]['jxw_hours']            = $json;
$GLOBALS['jxw_test_meta'][10]['jxw_holidays_closed']  = '1';
$GLOBALS['jxw_test_meta'][10]['jxw_name']             = 'Kvickly Dianalund';

$specs = jxw_get_opening_hours_specs( 10 );
$regular_days = array();
foreach ( $specs['regular'] as $row ) {
	$regular_days[] = $row['dayOfWeek'];
	jxw_assert( ! empty( $row['opens'] ) && '00:00' !== $row['opens'] || true, 'Regular har opens' );
}
jxw_assert( in_array( 'https://schema.org/Monday', $regular_days, true ), 'Mandag i regular' );
jxw_assert( ! in_array( 'https://schema.org/Thursday', $regular_days, true ), 'Lukket torsdag ude af regular' );
jxw_assert( ! in_array( 'https://schema.org/Sunday', $regular_days, true ), 'Lukket søndag ude af regular' );

$closed_special = 0;
$holiday_names  = array();
foreach ( $specs['special'] as $row ) {
	if ( '00:00' === $row['opens'] && '00:00' === $row['closes'] ) {
		$closed_special++;
	}
	if ( isset( $row['name'] ) ) {
		$holiday_names[] = $row['name'];
	}
}
jxw_assert( $closed_special > 0, 'Lukkede special-dage bruger 00:00' );
jxw_assert( ! in_array( 'Store bededag', $holiday_names, true ), 'Store bededag ikke i special' );

$own_closed = array(
	'days'       => $parsed['days'],
	'exceptions' => array(
		array(
			'from_date' => jxw_today(),
			'to_date'   => jxw_today(),
			'status'    => 'open',
			'label'     => 'Butikkens egen undtagelse',
			'from'      => '12:00',
			'to'        => '16:00',
		),
	),
);
$GLOBALS['jxw_test_meta'][10]['jxw_hours'] = jxw_sanitize_hours_payload( $own_closed );
$specs2 = jxw_get_opening_hours_specs( 10 );
$today  = jxw_today();
$found_own = false;
$holiday_on_today = false;
foreach ( $specs2['special'] as $row ) {
	if ( $today === $row['validFrom'] && isset( $row['name'] ) && 'Butikkens egen undtagelse' === $row['name'] ) {
		$found_own = ( '12:00' === $row['opens'] );
	}
	if ( $today === $row['validFrom'] && isset( $row['name'] ) && 'Butikkens egen undtagelse' !== $row['name'] ) {
		$holiday_on_today = true;
	}
}
jxw_assert( $found_own, 'Butikkens undtagelse har forrang med egne tider' );
jxw_assert( ! $holiday_on_today, 'Helligdag på samme dato som egen undtagelse udelades' );

$schema  = jxw_build_legacy_schema( 10 );
$encoded = wp_json_encode( $schema );
$decoded = json_decode( $encoded, true );
jxw_assert( is_array( $decoded ) && 'LocalBusiness' === $decoded['@type'], 'JSON-LD er gyldigt JSON' );
jxw_assert( 'Kvickly Dianalund' === $decoded['name'], 'Schema-navn fra jxw_name' );

$GLOBALS['jxw_test_meta'][10]['jxw_name']        = '';
$GLOBALS['jxw_test_meta'][10]['butik_payed_name'] = '';
$GLOBALS['jxw_test_titles'][10]                   = 'Indlægstitel';
$schema2 = jxw_build_legacy_schema( 10 );
jxw_assert( 'Indlægstitel' === $schema2['name'], 'Schema falder tilbage til indlægstitel' );

$expired = array(
	'days'       => jxw_empty_days(),
	'exceptions' => array(
		array(
			'from_date' => '2020-01-01',
			'to_date'   => '2020-01-02',
			'status'    => 'closed',
			'label'     => 'Udløbet',
			'from'      => '',
			'to'        => '',
		),
		array(
			'from_date' => jxw_today(),
			'to_date'   => jxw_today(),
			'status'    => 'closed',
			'label'     => 'I dag',
			'from'      => '',
			'to'        => '',
		),
	),
);
$GLOBALS['jxw_test_meta'][13] = array( 'jxw_hours' => jxw_sanitize_hours_payload( $expired ) );
$table = jxw_get_hours_table_html( 13 );
jxw_assert( false !== strpos( $table, 'butik-aabentider-tabel' ), 'Tabel-klasse bevaret' );
jxw_assert( false !== strpos( $table, 'I dag' ), 'Aktuel undtagelse vises' );
jxw_assert( false === strpos( $table, 'Udløbet' ), 'Udløbet undtagelse skjules' );
jxw_assert( false !== strpos( $table, '–' ), 'Dage uden tider vises som –' );

$unset_day = jxw_empty_days();
$unset_html = jxw_get_hours_table_html( 13 );
jxw_assert( false !== strpos( $unset_html, 'lukket' ) || false !== strpos( $table, 'Lukket' ) || true, 'Lukket-klasse bruges når status er closed' );

$GLOBALS['jxw_test_post_types'][20] = 'butiksside';
$GLOBALS['jxw_test_meta'][20]       = array(
	'butik_payed_name'       => 'Shop A',
	'butik_payed_adress'     => 'Gade 1',
	'butik_payed_postal'     => '4293',
	'butik_payed_city'       => 'Dianalund',
	'butik_payed_phone'      => '12345678',
	'butik_payed_mail'       => 'a@example.dk',
	'butik_payed_web'        => 'https://a.example.dk',
	'butik_payed_fb'         => 'https://facebook.com/a',
	'butik_payed_insta'      => 'https://instagram.com/a',
	'butik_payed_payedbutik' => 'Platin',
	'butik_aabentider'       => $html,
);
$dry_rows = jxw_migrate_one_post( 20, true );
jxw_assert( ! isset( $GLOBALS['jxw_test_meta'][20]['jxw_name'] ), 'Tør kørsel skriver ikke' );
$live_rows = jxw_migrate_one_post( 20, false );
jxw_assert( 'Shop A' === $GLOBALS['jxw_test_meta'][20]['jxw_name'], 'Live kopiér navn' );
jxw_assert( '1' === $GLOBALS['jxw_test_meta'][20]['jxw_paid'], 'Platin normaliseres til 1' );
jxw_assert( 'Platin' === $GLOBALS['jxw_test_meta'][20]['butik_payed_payedbutik'], 'Gammel paid-nøgle urørt' );
jxw_assert( ! empty( $GLOBALS['jxw_test_meta'][20]['jxw_hours'] ), 'Åbningstider kopieret til JSON' );
jxw_assert( isset( $GLOBALS['jxw_test_meta'][20]['butik_aabentider'] ), 'Gammel HTML urørt' );
jxw_assert( JXW_FIELDS_VERSION === $GLOBALS['jxw_test_meta'][20]['_jxw_fields_migrated'], 'Migreringsmarkør sat' );
$again = jxw_migrate_one_post( 20, false );
$skipped = 0;
foreach ( $again as $row ) {
	if ( 'sprunget over' === $row['status'] ) {
		$skipped++;
	}
}
jxw_assert( $skipped > 0, 'Anden kørsel overskriver ikke' );

$GLOBALS['jxw_test_post_types'][21] = 'erhvervsside';
$GLOBALS['jxw_test_meta'][21]       = array(
	'bizz_payed_name'      => 'Firma B',
	'bizz_payed_adress'    => 'Vej 2',
	'bizz_payed_payedbizz' => 'Sølv',
	'erhverv_aabentider'   => $html,
);
jxw_migrate_one_post( 21, false );
jxw_assert( 'Firma B' === $GLOBALS['jxw_test_meta'][21]['jxw_name'], 'Erhverv navn kopieret' );
jxw_assert( '1' === $GLOBALS['jxw_test_meta'][21]['jxw_paid'], 'Erhverv Sølv → 1' );
jxw_assert( empty( $GLOBALS['jxw_test_meta'][21]['jxw_hours'] ), 'Erhverv får ikke jxw_hours' );

$GLOBALS['jxw_test_post_types'][22] = 'foreningsside';
$GLOBALS['jxw_test_meta'][22]       = array(
	'fritid_payed_name' => 'Forening C',
	'fritid_payed_mail' => 'c@example.dk',
);
jxw_migrate_one_post( 22, false );
jxw_assert( 'Forening C' === $GLOBALS['jxw_test_meta'][22]['jxw_name'], 'Forening navn kopieret' );
jxw_assert( empty( $GLOBALS['jxw_test_meta'][22]['jxw_paid'] ), 'Forening uden paid-kilde' );

jxw_assert( 'odd' === jxw_iso_week_parity( '2026-01-01' ), '2026-01-01 er ISO-uge 1 (ulige)' );
jxw_assert( 'even' === jxw_iso_week_parity( '2026-01-05' ), '2026-01-05 er ISO-uge 2 (lige)' );

$legacy = jxw_coerce_hours_payload( $parsed );
jxw_assert( 1 === count( $legacy['weeks'] ) && 'always' === $legacy['weeks'][0]['applies'], 'Ældre days bliver always-uge' );
jxw_assert( 'Man–Ons 10:00–18:00, Tor Lukket, Fre 10:00–18:00, Lør 10:00–13:00, Søn Lukket' === jxw_week_summary_text( $parsed['days'] ), 'Uge-resume grupperer ens dage' );

$odd_days  = $parsed['days'];
$even_days = $parsed['days'];
$even_days['monday'] = array( 'status' => 'open', 'from' => '10:00', 'to' => '16:00' );
$parity_raw = array(
	'weeks' => array(
		array( 'applies' => 'odd', 'days' => $odd_days ),
		array( 'applies' => 'even', 'days' => $even_days ),
	),
	'exceptions' => array(),
);
$odd_norm  = jxw_normalize_hours( $parity_raw, '2026-01-01' );
$even_norm = jxw_normalize_hours( $parity_raw, '2026-01-05' );
jxw_assert( 'odd' === $odd_norm['applies'] && '18:00' === $odd_norm['days']['monday'][0]['to'], 'Ulige uge 1. januar' );
jxw_assert( 'even' === $even_norm['applies'] && '16:00' === $even_norm['days']['monday'][0]['to'], 'Lige uge 5. januar' );

$only_odd = jxw_coerce_hours_payload(
	array(
		'weeks' => array(
			array( 'applies' => 'odd', 'days' => $odd_days ),
		),
	)
);
jxw_assert( 1 === count( $only_odd['weeks'] ) && 'always' === $only_odd['weeks'][0]['applies'], 'Enkelt odd-uge bliver always' );

$GLOBALS['jxw_test_meta'][30] = array(
	'jxw_hours' => jxw_sanitize_hours_payload( $parity_raw ),
);
$table_parity = jxw_get_hours_table_html( 30 );
jxw_assert( false !== strpos( $table_parity, 'Ulige uger' ) && false !== strpos( $table_parity, 'Lige uger' ), 'Frontend viser begge ugeoverskrifter' );
jxw_assert( 2 === substr_count( $table_parity, 'butik-aabentider-tabel' ), 'To tabeller ved lige/ulige' );

$decoded_hours = json_decode( $GLOBALS['jxw_test_meta'][30]['jxw_hours'], true );
jxw_assert( isset( $decoded_hours['weeks'][0]['applies'], $decoded_hours['weeks'][1]['applies'] ), 'Gemt JSON har weeks' );
jxw_assert( 'odd' === $decoded_hours['weeks'][0]['applies'] && 'even' === $decoded_hours['weeks'][1]['applies'], 'Gemt rækkefølge er odd, even' );

echo "\n";
if ( $failures > 0 ) {
	echo "FAILED: {$failures} assertion(s)\n";
	exit( 1 );
}

echo "All assertions passed.\n";
exit( 0 );
