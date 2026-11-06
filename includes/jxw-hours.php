<?php
/**
 * Opening hours: parse, normalize, schema and frontend table.
 *
 * @package Dianalund_CPT
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Weekday keys in Monday-first order.
 *
 * @return string[]
 */
function jxw_weekday_keys() {
	return array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );
}

/**
 * Localized weekday labels.
 *
 * @return array<string, string>
 */
function jxw_weekday_labels() {
	return array(
		'monday'    => __( 'Mandag', 'dianalund_txtdomain' ),
		'tuesday'   => __( 'Tirsdag', 'dianalund_txtdomain' ),
		'wednesday' => __( 'Onsdag', 'dianalund_txtdomain' ),
		'thursday'  => __( 'Torsdag', 'dianalund_txtdomain' ),
		'friday'    => __( 'Fredag', 'dianalund_txtdomain' ),
		'saturday'  => __( 'Lørdag', 'dianalund_txtdomain' ),
		'sunday'    => __( 'Søndag', 'dianalund_txtdomain' ),
	);
}

/**
 * Schema.org day names.
 *
 * @return array<string, string>
 */
function jxw_schema_day_names() {
	return array(
		'monday'    => 'Monday',
		'tuesday'   => 'Tuesday',
		'wednesday' => 'Wednesday',
		'thursday'  => 'Thursday',
		'friday'    => 'Friday',
		'saturday'  => 'Saturday',
		'sunday'    => 'Sunday',
	);
}

/**
 * Empty day payload.
 *
 * @return array<string, string>
 */
function jxw_empty_day() {
	return array(
		'status' => '',
		'from'   => '',
		'to'     => '',
	);
}

/**
 * Empty days map.
 *
 * @return array<string, array<string, string>>
 */
function jxw_empty_days() {
	$days = array();
	foreach ( jxw_weekday_keys() as $key ) {
		$days[ $key ] = jxw_empty_day();
	}

	return $days;
}

/**
 * Allowed week-pattern rules.
 *
 * @return string[]
 */
function jxw_week_applies_values() {
	return array( 'always', 'odd', 'even' );
}

/**
 * Localized label for a week rule.
 *
 * @param string $applies always|odd|even.
 * @return string
 */
function jxw_week_label( $applies ) {
	if ( 'odd' === $applies ) {
		return __( 'Ulige uger', 'dianalund_txtdomain' );
	}
	if ( 'even' === $applies ) {
		return __( 'Lige uger', 'dianalund_txtdomain' );
	}

	return __( 'Alle uger', 'dianalund_txtdomain' );
}

/**
 * Empty week pattern.
 *
 * @param string                         $applies always|odd|even.
 * @param array<string, array>|null      $days    Optional day map.
 * @return array{id:string,label:string,applies:string,days:array}
 */
function jxw_empty_week( $applies = 'always', $days = null ) {
	if ( ! in_array( $applies, jxw_week_applies_values(), true ) ) {
		$applies = 'always';
	}

	return array(
		'id'      => $applies,
		'label'   => jxw_week_label( $applies ),
		'applies' => $applies,
		'days'    => is_array( $days ) ? $days : jxw_empty_days(),
	);
}

/**
 * ISO-8601 week parity for a date. Odd = week 1, 3, 5…
 *
 * @param string $date YYYY-MM-DD.
 * @return string odd|even
 */
function jxw_iso_week_parity( $date ) {
	if ( ! jxw_valid_date( $date ) ) {
		$date = jxw_today();
	}

	$week = jxw_iso_week_number( $date );

	return ( 0 === ( $week % 2 ) ) ? 'even' : 'odd';
}

/**
 * ISO-8601 week number of a civil date.
 *
 * Noon UTC stays on that calendar day in every PHP timezone, including
 * dates that jxw_today() already resolved in the site timezone.
 *
 * @param string $date YYYY-MM-DD.
 * @return int
 */
function jxw_iso_week_number( $date ) {
	$when = date_create_immutable( $date . ' 12:00:00', new DateTimeZone( 'UTC' ) );
	if ( ! $when instanceof DateTimeImmutable ) {
		return (int) gmdate( 'W' );
	}

	return (int) $when->format( 'W' );
}

/**
 * Sanitizes one week's days map.
 *
 * @param mixed $source Raw days.
 * @return array<string, array<string, string>>
 */
function jxw_sanitize_week_days( $source ) {
	$days = jxw_empty_days();
	if ( ! is_array( $source ) ) {
		return $days;
	}

	foreach ( jxw_weekday_keys() as $key ) {
		if ( isset( $source[ $key ] ) ) {
			$days[ $key ] = jxw_sanitize_day_row( $source[ $key ] );
			unset( $days[ $key ]['inherited'] );
		}
	}

	return $days;
}

/**
 * Canonical week list: either one always-week or odd+even.
 *
 * @param mixed $raw_weeks     Raw weeks array.
 * @param array $fallback_days Days used when weeks are missing.
 * @return array<int, array{id:string,label:string,applies:string,days:array}>
 */
function jxw_sanitize_weeks( $raw_weeks, $fallback_days ) {
	$by_applies = array();

	if ( is_array( $raw_weeks ) ) {
		foreach ( $raw_weeks as $week ) {
			if ( ! is_array( $week ) ) {
				continue;
			}
			$applies = isset( $week['applies'] ) ? sanitize_text_field( (string) $week['applies'] ) : '';
			if ( ! in_array( $applies, jxw_week_applies_values(), true ) ) {
				$applies = 'always';
			}
			$source = isset( $week['days'] ) && is_array( $week['days'] ) ? $week['days'] : $week;
			$by_applies[ $applies ] = jxw_empty_week( $applies, jxw_sanitize_week_days( $source ) );
		}
	}

	if ( isset( $by_applies['odd'] ) && isset( $by_applies['even'] ) ) {
		return array( $by_applies['odd'], $by_applies['even'] );
	}

	if ( isset( $by_applies['always'] ) ) {
		return array( $by_applies['always'] );
	}

	if ( isset( $by_applies['odd'] ) ) {
		return array( jxw_empty_week( 'always', $by_applies['odd']['days'] ) );
	}

	if ( isset( $by_applies['even'] ) ) {
		return array( jxw_empty_week( 'always', $by_applies['even']['days'] ) );
	}

	return array( jxw_empty_week( 'always', $fallback_days ) );
}

/**
 * Picks the week that applies on a date.
 *
 * @param array  $payload Coerced payload with weeks.
 * @param string $date    YYYY-MM-DD.
 * @return array{id:string,label:string,applies:string,days:array}
 */
function jxw_week_for_date( $payload, $date ) {
	$weeks = isset( $payload['weeks'] ) && is_array( $payload['weeks'] ) ? $payload['weeks'] : array();
	if ( empty( $weeks ) ) {
		$days = isset( $payload['days'] ) && is_array( $payload['days'] ) ? $payload['days'] : jxw_empty_days();

		return jxw_empty_week( 'always', $days );
	}

	$parity = jxw_iso_week_parity( $date );
	$always = null;
	$match  = null;

	foreach ( $weeks as $week ) {
		if ( ! is_array( $week ) ) {
			continue;
		}
		$applies = isset( $week['applies'] ) ? $week['applies'] : '';
		if ( 'always' === $applies ) {
			$always = $week;
		}
		if ( $parity === $applies ) {
			$match = $week;
		}
	}

	if ( is_array( $match ) ) {
		return $match;
	}
	if ( is_array( $always ) ) {
		return $always;
	}

	return $weeks[0];
}

/**
 * Compact summary of a week for the collapsed metabox header.
 *
 * @param array<string, array<string, string>> $days Day map.
 * @return string
 */
function jxw_week_summary_text( $days ) {
	$short = array(
		'monday'    => 'Man',
		'tuesday'   => 'Tir',
		'wednesday' => 'Ons',
		'thursday'  => 'Tor',
		'friday'    => 'Fre',
		'saturday'  => 'Lør',
		'sunday'    => 'Søn',
	);
	$slots = array();
	foreach ( jxw_weekday_keys() as $key ) {
		$day = isset( $days[ $key ] ) ? $days[ $key ] : jxw_empty_day();
		if ( 'closed' === $day['status'] ) {
			$text = __( 'Lukket', 'dianalund_txtdomain' );
		} elseif ( 'open' === $day['status'] && jxw_valid_time( $day['from'] ) && jxw_valid_time( $day['to'] ) ) {
			$text = $day['from'] . '–' . $day['to'];
		} else {
			$text = '–';
		}
		$slots[] = array(
			'key'  => $key,
			'text' => $text,
		);
	}

	$groups = array();
	foreach ( $slots as $slot ) {
		$last_index = count( $groups ) - 1;
		if ( $last_index >= 0 && $groups[ $last_index ]['text'] === $slot['text'] ) {
			$groups[ $last_index ]['end'] = $slot['key'];
			continue;
		}
		$groups[] = array(
			'start' => $slot['key'],
			'end'   => $slot['key'],
			'text'  => $slot['text'],
		);
	}

	$parts = array();
	foreach ( $groups as $group ) {
		$label = $short[ $group['start'] ];
		if ( $group['start'] !== $group['end'] ) {
			$label .= '–' . $short[ $group['end'] ];
		}
		$parts[] = $label . ' ' . $group['text'];
	}

	return implode( ', ', $parts );
}

/**
 * Whether a string is HH:MM.
 *
 * @param string $time Time.
 * @return bool
 */
function jxw_valid_time( $time ) {
	return (bool) preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', (string) $time );
}

/**
 * Whether a string is YYYY-MM-DD.
 *
 * @param string $date Date.
 * @return bool
 */
function jxw_valid_date( $date ) {
	if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', (string) $date, $m ) ) {
		return false;
	}

	return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] );
}

/**
 * Normalizes a time token to HH:MM or empty.
 *
 * @param string $hour Hour.
 * @param string $minute Minute.
 * @return string
 */
function jxw_format_time_parts( $hour, $minute ) {
	$h = (int) $hour;
	$i = ( '' === (string) $minute ) ? 0 : (int) $minute;
	if ( $h > 23 || $i > 59 ) {
		return '';
	}

	return sprintf( '%02d:%02d', $h, $i );
}

/**
 * One empty exception.
 *
 * @return array<string, string>
 */
function jxw_empty_exception() {
	return array(
		'from_date' => '',
		'to_date'   => '',
		'status'    => '',
		'label'     => '',
		'from'      => '',
		'to'        => '',
	);
}

/**
 * Sanitizes one day row. Strips inherit; inherited days become open with empty times.
 *
 * @param mixed $day Raw day.
 * @return array{status:string,from:string,to:string,inherited?:bool}
 */
function jxw_sanitize_day_row( $day ) {
	$clean = jxw_empty_day();
	$meta  = array();

	if ( ! is_array( $day ) ) {
		return $clean;
	}

	if ( ! empty( $day['inherit'] ) || ( isset( $day['status'] ) && 'inherit' === $day['status'] ) ) {
		$clean['status'] = 'open';
		$meta['inherited'] = true;

		return array_merge( $clean, $meta );
	}

	$status = isset( $day['status'] ) ? sanitize_text_field( (string) $day['status'] ) : '';
	if ( ! in_array( $status, array( 'open', 'closed', '' ), true ) ) {
		$status = '';
	}

	$from = isset( $day['from'] ) ? sanitize_text_field( (string) $day['from'] ) : '';
	$to   = isset( $day['to'] ) ? sanitize_text_field( (string) $day['to'] ) : '';
	$from = jxw_valid_time( $from ) ? $from : '';
	$to   = jxw_valid_time( $to ) ? $to : '';

	if ( 'closed' === $status ) {
		$from = '';
		$to   = '';
	}

	if ( 'open' === $status && ( '' === $from || '' === $to ) ) {
		// Open without times is stored as specified (migration inherit / ikke angivet).
		$from = '';
		$to   = '';
	}

	$clean['status'] = $status;
	$clean['from']   = $from;
	$clean['to']     = $to;

	return $clean;
}

/**
 * Sanitizes one exception row.
 *
 * @param mixed $row Raw exception.
 * @return array<string, string>|null Null when the row has no usable date.
 */
function jxw_sanitize_exception_row( $row ) {
	if ( ! is_array( $row ) ) {
		return null;
	}

	$clean = jxw_empty_exception();
	$from_date = isset( $row['from_date'] ) ? sanitize_text_field( (string) $row['from_date'] ) : '';
	$to_date   = isset( $row['to_date'] ) ? sanitize_text_field( (string) $row['to_date'] ) : '';

	if ( jxw_valid_date( $from_date ) ) {
		$clean['from_date'] = $from_date;
	}
	if ( jxw_valid_date( $to_date ) ) {
		$clean['to_date'] = $to_date;
	} elseif ( '' !== $clean['from_date'] ) {
		$clean['to_date'] = $clean['from_date'];
	}

	if ( '' === $clean['from_date'] ) {
		return null;
	}

	$status = isset( $row['status'] ) ? sanitize_text_field( (string) $row['status'] ) : '';
	if ( ! in_array( $status, array( 'open', 'closed', '' ), true ) ) {
		$status = '';
	}
	$clean['status'] = $status;
	$clean['label']  = isset( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : '';

	$from = isset( $row['from'] ) ? sanitize_text_field( (string) $row['from'] ) : '';
	$to   = isset( $row['to'] ) ? sanitize_text_field( (string) $row['to'] ) : '';
	$clean['from'] = jxw_valid_time( $from ) ? $from : '';
	$clean['to']   = jxw_valid_time( $to ) ? $to : '';

	if ( 'closed' === $status ) {
		$clean['from'] = '';
		$clean['to']   = '';
	}

	return $clean;
}

/**
 * Coerces stored JSON, array or HTML into a canonical hours payload.
 *
 * Legacy `{ days }` becomes one `always` week. Consumers should resolve a
 * date with jxw_week_for_date() / jxw_normalize_hours( $raw, $date ).
 *
 * @param mixed $raw Stored value.
 * @return array{weeks: array, days: array, exceptions: array}
 */
function jxw_coerce_hours_payload( $raw ) {
	$days       = jxw_empty_days();
	$exceptions = array();
	$raw_weeks  = null;

	if ( is_string( $raw ) ) {
		$trim = trim( $raw );
		if ( '' === $trim ) {
			$week = jxw_empty_week( 'always', $days );

			return array(
				'weeks'      => array( $week ),
				'days'       => $days,
				'exceptions' => $exceptions,
			);
		}

		$decoded = json_decode( $trim, true );
		if ( is_array( $decoded ) ) {
			$raw = $decoded;
		} else {
			$raw = jxw_parse_hours_html( $trim );
		}
	}

	if ( ! is_array( $raw ) ) {
		$week = jxw_empty_week( 'always', $days );

		return array(
			'weeks'      => array( $week ),
			'days'       => $days,
			'exceptions' => $exceptions,
		);
	}

	if ( isset( $raw['weeks'] ) && is_array( $raw['weeks'] ) ) {
		$raw_weeks = $raw['weeks'];
	}

	$source_days = array();
	if ( isset( $raw['days'] ) && is_array( $raw['days'] ) ) {
		$source_days = $raw['days'];
	} elseif ( null === $raw_weeks ) {
		$source_days = $raw;
	}

	foreach ( jxw_weekday_keys() as $key ) {
		if ( isset( $source_days[ $key ] ) ) {
			$days[ $key ] = jxw_sanitize_day_row( $source_days[ $key ] );
			unset( $days[ $key ]['inherited'] );
		}
	}

	$weeks     = jxw_sanitize_weeks( $raw_weeks, $days );
	$resolved  = jxw_week_for_date( array( 'weeks' => $weeks ), jxw_today() );

	if ( isset( $raw['exceptions'] ) && is_array( $raw['exceptions'] ) ) {
		foreach ( $raw['exceptions'] as $row ) {
			$clean = jxw_sanitize_exception_row( $row );
			if ( null !== $clean ) {
				$exceptions[] = $clean;
			}
		}
	}

	return array(
		'weeks'      => $weeks,
		'days'       => $resolved['days'],
		'exceptions' => $exceptions,
	);
}

/**
 * Full sanitizer used as meta sanitize_callback.
 *
 * @param mixed $value Raw JSON, array or HTML.
 * @return string JSON.
 */
function jxw_sanitize_hours_payload( $value ) {
	$payload = jxw_coerce_hours_payload( $value );
	foreach ( $payload['days'] as $key => $day ) {
		unset( $payload['days'][ $key ]['inherited'] );
	}
	foreach ( $payload['weeks'] as $i => $week ) {
		if ( ! isset( $week['days'] ) || ! is_array( $week['days'] ) ) {
			continue;
		}
		foreach ( $week['days'] as $key => $day ) {
			unset( $payload['weeks'][ $i ]['days'][ $key ]['inherited'] );
		}
	}

	$encoded = function_exists( 'wp_json_encode' ) ? wp_json_encode( $payload ) : json_encode( $payload );

	return is_string( $encoded ) ? $encoded : '';
}

/**
 * Maps a Danish or English day label to a weekday key.
 *
 * @param string $label Label.
 * @return string
 */
function jxw_match_day_name( $label ) {
	$label = strtolower( trim( $label ) );
	$label = str_replace( array( 'ø', 'å' ), array( 'o', 'a' ), $label );
	$map   = array(
		'mandag'    => 'monday',
		'monday'    => 'monday',
		'tirsdag'   => 'tuesday',
		'tuesday'   => 'tuesday',
		'onsdag'    => 'wednesday',
		'wednesday' => 'wednesday',
		'torsdag'   => 'thursday',
		'thursday'  => 'thursday',
		'fredag'    => 'friday',
		'friday'    => 'friday',
		'lordag'    => 'saturday',
		'saturday'  => 'saturday',
		'sondag'    => 'sunday',
		'sunday'    => 'sunday',
	);

	foreach ( $map as $needle => $key ) {
		if ( 0 === strpos( $label, $needle ) ) {
			return $key;
		}
	}

	return '';
}

/**
 * Parses a cell of times ("10:00 - 18:00", "Lukket", "–").
 *
 * @param string $text Cell text.
 * @return array<string, string>
 */
function jxw_parse_hours_cell( $text ) {
	$text = html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES, 'UTF-8' );
	$text = trim( $text );
	$text = str_replace( array( '–', '—', '/' ), '-', $text );

	if ( '' === $text || '-' === $text || '.' === $text ) {
		return jxw_empty_day();
	}

	if ( preg_match( '/lukket|closed|helt\s*lukket/i', $text ) ) {
		return array(
			'status' => 'closed',
			'from'   => '',
			'to'     => '',
		);
	}

	if ( preg_match( '/(\d{1,2})(?:[:.](\d{2}))?\s*-\s*(\d{1,2})(?:[:.](\d{2}))?/', $text, $m ) ) {
		$from = jxw_format_time_parts( $m[1], isset( $m[2] ) ? $m[2] : '00' );
		$to   = jxw_format_time_parts( $m[3], isset( $m[4] ) ? $m[4] : '00' );
		if ( '' !== $from && '' !== $to ) {
			return array(
				'status' => 'open',
				'from'   => $from,
				'to'     => $to,
			);
		}
	}

	return jxw_empty_day();
}

/**
 * Parses legacy TinyMCE HTML hours into the JSON payload.
 *
 * @param string $html HTML table or free text.
 * @return array{days: array, exceptions: array}
 */
function jxw_parse_hours_html( $html ) {
	$days = jxw_empty_days();
	$html = (string) $html;

	if ( preg_match_all( '/<tr[^>]*>(.*?)<\/tr>/is', $html, $rows ) ) {
		foreach ( $rows[1] as $row ) {
			if ( ! preg_match_all( '/<t[dh][^>]*>(.*?)<\/t[dh]>/is', $row, $cells ) ) {
				continue;
			}
			if ( count( $cells[1] ) < 2 ) {
				continue;
			}
			$day_key = jxw_match_day_name( wp_strip_all_tags( $cells[1][0] ) );
			if ( '' === $day_key ) {
				continue;
			}
			$days[ $day_key ] = jxw_parse_hours_cell( $cells[1][1] );
		}

		return array(
			'days'       => $days,
			'exceptions' => array(),
		);
	}

	$plain = preg_replace( '/<br\s*\/?>/i', "\n", $html );
	$plain = wp_strip_all_tags( $plain );
	$lines = preg_split( '/\r\n|\r|\n/', $plain );
	if ( is_array( $lines ) ) {
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			if ( preg_match( '/^(mandag|tirsdag|onsdag|torsdag|fredag|l[øo]rdag|s[øo]ndag|monday|tuesday|wednesday|thursday|friday|saturday|sunday)\s*[:.]?\s*(.*)$/iu', $line, $m ) ) {
				$day_key = jxw_match_day_name( $m[1] );
				if ( '' !== $day_key ) {
					$days[ $day_key ] = jxw_parse_hours_cell( $m[2] );
				}
			}
		}
	}

	return array(
		'days'       => $days,
		'exceptions' => array(),
	);
}

/**
 * Builds hours JSON from a post's legacy keys. Does not write.
 *
 * @param int $post_id Post ID.
 * @return array{payload: array, inherited_days: string[], source: string}
 */
function jxw_hours_from_legacy_post( $post_id ) {
	$inherited = array();
	$v2        = get_post_meta( $post_id, 'butik_aabentider_v2', true );

	if ( jxw_field_has_value( $v2 ) ) {
		$payload = jxw_coerce_hours_payload( $v2 );
		foreach ( $payload['days'] as $key => $day ) {
			if ( ! empty( $day['inherited'] ) ) {
				$inherited[] = $key;
				unset( $payload['days'][ $key ]['inherited'] );
			} elseif ( 'open' === $day['status'] && '' === $day['from'] && '' === $day['to'] ) {
				$inherited[] = $key;
			}
		}

		return array(
			'payload'         => $payload,
			'inherited_days'  => $inherited,
			'source'          => 'butik_aabentider_v2',
		);
	}

	$html = get_post_meta( $post_id, 'butik_aabentider', true );
	if ( ! jxw_field_has_value( $html ) ) {
		return array(
			'payload'         => array(
				'days'       => jxw_empty_days(),
				'exceptions' => array(),
			),
			'inherited_days'  => array(),
			'source'          => '',
		);
	}

	return array(
		'payload'         => jxw_parse_hours_html( (string) $html ),
		'inherited_days'  => array(),
		'source'          => 'butik_aabentider',
	);
}

/**
 * Always returns a list of time ranges per day, even when only one range exists.
 *
 * @param mixed       $raw  JSON, array or HTML.
 * @param string|null $date YYYY-MM-DD to resolve even/odd weeks. Default today.
 * @return array{days: array<string, array<int, array{from:string,to:string}>>, exceptions: array, applies: string}
 */

function jxw_normalize_hours( $raw, $date = null ) {
	$payload = jxw_coerce_hours_payload( $raw );
	$date    = is_string( $date ) && jxw_valid_date( $date ) ? $date : jxw_today();
	$week    = jxw_week_for_date( $payload, $date );
	$days    = array();

	foreach ( jxw_weekday_keys() as $key ) {
		$day    = isset( $week['days'][ $key ] ) ? $week['days'][ $key ] : jxw_empty_day();
		$ranges = array();
		if ( isset( $day['periods'] ) && is_array( $day['periods'] ) ) {
			foreach ( $day['periods'] as $period ) {
				if ( ! is_array( $period ) ) {
					continue;
				}
				$from = isset( $period['from'] ) ? $period['from'] : '';
				$to   = isset( $period['to'] ) ? $period['to'] : '';
				if ( jxw_valid_time( $from ) && jxw_valid_time( $to ) ) {
					$ranges[] = array(
						'from' => $from,
						'to'   => $to,
					);
				}
			}
		}
		if ( empty( $ranges ) && 'open' === $day['status'] && jxw_valid_time( $day['from'] ) && jxw_valid_time( $day['to'] ) ) {
			$ranges[] = array(
				'from' => $day['from'],
				'to'   => $day['to'],
			);
		}
		$days[ $key ] = $ranges;
	}

	return array(
		'days'       => $days,
		'exceptions' => $payload['exceptions'],
		'applies'    => isset( $week['applies'] ) ? $week['applies'] : 'always',
	);
}

/**
 * Easter Sunday (Gregorian) as YYYY-MM-DD. No calendar extension.
 *
 * @param int $year Year.
 * @return string
 */
function jxw_easter_date( $year ) {
	$year = (int) $year;
	$a    = $year % 19;
	$b    = intdiv( $year, 100 );
	$c    = $year % 100;
	$d    = intdiv( $b, 4 );
	$e    = $b % 4;
	$f    = intdiv( $b + 8, 25 );
	$g    = intdiv( $b - $f + 1, 3 );
	$h    = ( 19 * $a + $b - $d - $g + 15 ) % 30;
	$i    = intdiv( $c, 4 );
	$k    = $c % 4;
	$l    = ( 32 + 2 * $e + 2 * $i - $h - $k ) % 7;
	$m    = intdiv( $a + 11 * $h + 22 * $l, 451 );
	$month = intdiv( $h + $l - 7 * $m + 114, 31 );
	$day   = ( ( $h + $l - 7 * $m + 114 ) % 31 ) + 1;

	return sprintf( '%04d-%02d-%02d', $year, $month, $day );
}

/**
 * Danish public holidays in an inclusive date range. Store Bededag is omitted.
 *
 * @param string $from_date YYYY-MM-DD.
 * @param string $to_date   YYYY-MM-DD.
 * @return array<string, string> date => label
 */
function jxw_danish_holidays_between( $from_date, $to_date ) {
	if ( ! jxw_valid_date( $from_date ) || ! jxw_valid_date( $to_date ) ) {
		return array();
	}

	$start     = (int) substr( $from_date, 0, 4 );
	$end       = (int) substr( $to_date, 0, 4 );
	$holidays  = array();

	for ( $year = $start; $year <= $end; $year++ ) {
		$easter = jxw_easter_date( $year );
		$easter_ts = strtotime( $easter . ' UTC' );
		$named     = array(
			sprintf( '%04d-01-01', $year )                   => 'Nytårsdag',
			gmdate( 'Y-m-d', strtotime( '-3 days', $easter_ts ) )  => 'Skærtorsdag',
			gmdate( 'Y-m-d', strtotime( '-2 days', $easter_ts ) )  => 'Langfredag',
			$easter                                          => 'Påskedag',
			gmdate( 'Y-m-d', strtotime( '+1 day', $easter_ts ) )   => '2. påskedag',
			gmdate( 'Y-m-d', strtotime( '+39 days', $easter_ts ) ) => 'Kristi himmelfartsdag',
			gmdate( 'Y-m-d', strtotime( '+49 days', $easter_ts ) ) => 'Pinsedag',
			gmdate( 'Y-m-d', strtotime( '+50 days', $easter_ts ) ) => '2. pinsedag',
			sprintf( '%04d-12-25', $year )                   => '1. juledag',
			sprintf( '%04d-12-26', $year )                   => '2. juledag',
		);

		foreach ( $named as $date => $label ) {
			if ( $date >= $from_date && $date <= $to_date ) {
				$holidays[ $date ] = $label;
			}
		}
	}

	ksort( $holidays );

	return $holidays;
}

/**
 * Today's date in the site timezone.
 *
 * @return string YYYY-MM-DD
 */
function jxw_today() {
	if ( function_exists( 'current_time' ) ) {
		return current_time( 'Y-m-d' );
	}

	return gmdate( 'Y-m-d' );
}

/**
 * Weekday key for today in the site timezone.
 *
 * @return string monday…sunday
 */
function jxw_today_weekday_key() {
	$week_map = array( 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday' );
	if ( function_exists( 'current_time' ) ) {
		$index = (int) current_time( 'w' );
	} else {
		$index = (int) gmdate( 'w' );
	}

	return isset( $week_map[ $index ] ) ? $week_map[ $index ] : '';
}

/**
 * OpeningHoursSpecification lists for schema consumers.
 *
 * Closed days and days without times are omitted from regular.
 * special only includes exceptions whose validThrough is today or later
 * and at most 90 days ahead. Shop exceptions win over holidays on the same date.
 *
 * @param int $post_id Post ID.
 * @return array{regular: array, special: array}
 */
function jxw_get_opening_hours_specs( $post_id ) {
	$post_id     = (int) $post_id;
	$normalized  = jxw_normalize_hours( jxw_get_field( $post_id, 'jxw_hours' ) );
	$schema_days = jxw_schema_day_names();
	$regular     = array();

	foreach ( $normalized['days'] as $key => $ranges ) {
		if ( empty( $ranges ) ) {
			continue;
		}
		foreach ( $ranges as $range ) {
			$regular[] = array(
				'dayOfWeek' => 'https://schema.org/' . $schema_days[ $key ],
				'opens'     => $range['from'],
				'closes'    => $range['to'],
			);
		}
	}

	$today  = jxw_today();
	$limit  = gmdate( 'Y-m-d', strtotime( $today . ' +90 days UTC' ) );
	$special = array();
	$owned   = array();

	foreach ( $normalized['exceptions'] as $ex ) {
		$from = $ex['from_date'];
		$to   = '' !== $ex['to_date'] ? $ex['to_date'] : $from;
		if ( ! jxw_valid_date( $from ) ) {
			continue;
		}
		if ( $to < $today ) {
			continue;
		}
		if ( $from > $limit ) {
			continue;
		}

		$valid_from     = ( $from < $today ) ? $today : $from;
		$valid_through  = ( $to > $limit ) ? $limit : $to;
		$opens          = '00:00';
		$closes         = '00:00';
		if ( 'open' === $ex['status'] && jxw_valid_time( $ex['from'] ) && jxw_valid_time( $ex['to'] ) ) {
			$opens  = $ex['from'];
			$closes = $ex['to'];
		}

		$item = array(
			'opens'        => $opens,
			'closes'       => $closes,
			'validFrom'    => $valid_from,
			'validThrough' => $valid_through,
		);
		if ( '' !== $ex['label'] ) {
			$item['name'] = $ex['label'];
		}
		$special[] = $item;

		$cursor = strtotime( $valid_from . ' UTC' );
		$end    = strtotime( $valid_through . ' UTC' );
		while ( $cursor <= $end ) {
			$owned[ gmdate( 'Y-m-d', $cursor ) ] = true;
			$cursor += 86400;
		}
	}

	if ( '1' === jxw_get_field( $post_id, 'jxw_holidays_closed' ) ) {
		foreach ( jxw_danish_holidays_between( $today, $limit ) as $date => $label ) {
			if ( isset( $owned[ $date ] ) ) {
				continue;
			}
			$special[] = array(
				'opens'        => '00:00',
				'closes'       => '00:00',
				'validFrom'    => $date,
				'validThrough' => $date,
				'name'         => $label,
			);
		}
	}

	return array(
		'regular' => $regular,
		'special' => $special,
	);
}

/**
 * Renders one weekday table. CSS classes stay butik-aabentider-tabel, idag, lukket.
 *
 * @param array<string, array<string, string>> $days       Day map.
 * @param string                               $today_key  monday…sunday.
 * @param bool                                 $mark_today Whether to add idag on today.
 * @return string
 */
function jxw_render_hours_days_table( $days, $today_key, $mark_today ) {
	$labels = jxw_weekday_labels();
	$html   = '<table class="butik-aabentider-tabel"><tbody>';

	foreach ( jxw_weekday_keys() as $key ) {
		$day     = isset( $days[ $key ] ) ? $days[ $key ] : jxw_empty_day();
		$classes = array();
		if ( $mark_today && $key === $today_key ) {
			$classes[] = 'idag';
		}
		if ( 'closed' === $day['status'] ) {
			$classes[] = 'lukket';
			$time_html = esc_html( __( 'Lukket', 'dianalund_txtdomain' ) );
		} elseif ( 'open' === $day['status'] && jxw_valid_time( $day['from'] ) && jxw_valid_time( $day['to'] ) ) {
			$time_html = esc_html( $day['from'] . ' – ' . $day['to'] );
		} else {
			$time_html = '–';
		}

		$class_attr = empty( $classes ) ? '' : ' class="' . esc_attr( implode( ' ', $classes ) ) . '"';
		$html      .= '<tr' . $class_attr . '><td>' . esc_html( $labels[ $key ] ) . '</td><td>' . $time_html . '</td></tr>';
	}

	$html .= '</tbody></table>';

	return $html;
}

/**
 * Renders the hours table. CSS classes stay butik-aabentider-tabel, idag, lukket.
 *
 * Expired exceptions are omitted. Days without times show "–".
 * Even/odd shops get one table per week; idag is only on the current ISO week.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function jxw_get_hours_table_html( $post_id ) {
	$post_id   = (int) $post_id;
	$payload   = jxw_coerce_hours_payload( jxw_get_field( $post_id, 'jxw_hours' ) );
	$today     = jxw_today();
	$today_key = jxw_today_weekday_key();
	$parity    = jxw_iso_week_parity( $today );
	$weeks     = isset( $payload['weeks'] ) ? $payload['weeks'] : array( jxw_empty_week( 'always', $payload['days'] ) );
	$html      = '';

	foreach ( $weeks as $week ) {
		$applies    = isset( $week['applies'] ) ? $week['applies'] : 'always';
		$is_current = ( 'always' === $applies || $applies === $parity );
		if ( count( $weeks ) > 1 ) {
			$label = isset( $week['label'] ) ? $week['label'] : jxw_week_label( $applies );
			$html .= '<h3 class="butik-aabentider-uge">' . esc_html( $label ) . '</h3>';
		}
		$html .= jxw_render_hours_days_table( $week['days'], $today_key, $is_current );
	}

	$visible_exceptions = array();
	foreach ( $payload['exceptions'] as $ex ) {
		$to = '' !== $ex['to_date'] ? $ex['to_date'] : $ex['from_date'];
		if ( $to < $today ) {
			continue;
		}
		$visible_exceptions[] = $ex;
	}

	if ( ! empty( $visible_exceptions ) ) {
		$html .= '<ul class="butik-aabentider-undtagelser">';
		foreach ( $visible_exceptions as $ex ) {
			$label = '' !== $ex['label'] ? $ex['label'] : __( 'Undtagelse', 'dianalund_txtdomain' );
			$range = $ex['from_date'];
			if ( $ex['to_date'] !== $ex['from_date'] && '' !== $ex['to_date'] ) {
				$range .= ' – ' . $ex['to_date'];
			}
			if ( 'closed' === $ex['status'] ) {
				$when = __( 'Lukket', 'dianalund_txtdomain' );
			} elseif ( 'open' === $ex['status'] && jxw_valid_time( $ex['from'] ) && jxw_valid_time( $ex['to'] ) ) {
				$when = $ex['from'] . ' – ' . $ex['to'];
			} else {
				$when = '–';
			}
			$html .= '<li>' . esc_html( $label . ': ' . $range . ' (' . $when . ')' ) . '</li>';
		}
		$html .= '</ul>';
	}

	return $html;
}

/**
 * Builds the LocalBusiness JSON-LD array for a post.
 *
 * @param int $post_id Post ID.
 * @return array<string, mixed>
 */
function jxw_build_legacy_schema( $post_id ) {
	$post_id = (int) $post_id;
	$name    = jxw_get_field( $post_id, 'jxw_name' );
	if ( '' === $name ) {
		$name = get_the_title( $post_id );
	}

	$schema = array(
		'@context' => 'https://schema.org',
		'@type'    => 'LocalBusiness',
		'name'     => $name,
	);

	$street = jxw_get_field( $post_id, 'jxw_street' );
	$postal = jxw_get_field( $post_id, 'jxw_postal' );
	$city   = jxw_get_field( $post_id, 'jxw_city' );
	if ( '' !== $street || '' !== $postal || '' !== $city ) {
		$schema['address'] = array(
			'@type'           => 'PostalAddress',
			'streetAddress'   => $street,
			'postalCode'      => $postal,
			'addressLocality' => $city,
			'addressCountry'  => 'DK',
		);
	}

	$phone = jxw_get_field( $post_id, 'jxw_phone' );
	if ( '' !== $phone ) {
		$schema['telephone'] = $phone;
	}

	$web = jxw_get_field( $post_id, 'jxw_web' );
	if ( '' !== $web ) {
		$schema['url'] = $web;
	}

	$lat = jxw_get_field( $post_id, 'jxw_lat' );
	$lng = jxw_get_field( $post_id, 'jxw_lng' );
	if ( '' !== $lat && '' !== $lng ) {
		$schema['geo'] = array(
			'@type'     => 'GeoCoordinates',
			'latitude'  => $lat,
			'longitude' => $lng,
		);
	}

	$specs = jxw_get_opening_hours_specs( $post_id );
	$list  = array();
	foreach ( $specs['regular'] as $row ) {
		$list[] = array_merge(
			array( '@type' => 'OpeningHoursSpecification' ),
			$row
		);
	}
	foreach ( $specs['special'] as $row ) {
		$item = array( '@type' => 'OpeningHoursSpecification' );
		foreach ( $row as $k => $v ) {
			$item[ $k ] = $v;
		}
		$list[] = $item;
	}
	if ( ! empty( $list ) ) {
		$schema['openingHoursSpecification'] = $list;
	}

	return $schema;
}

/**
 * Prints legacy JSON-LD. jxw-seo can disable with jxw_output_legacy_schema = false.
 *
 * @return void
 */
function jxw_output_legacy_schema() {
	if ( ! is_singular( jxw_supported_post_types() ) ) {
		return;
	}

	if ( ! apply_filters( 'jxw_output_legacy_schema', true ) ) {
		return;
	}

	$schema  = jxw_build_legacy_schema( get_the_ID() );
	$encoded = wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	if ( ! is_string( $encoded ) ) {
		return;
	}

	echo '<script type="application/ld+json">' . $encoded . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * Enqueues hours admin assets on shop screens.
 *
 * @param string $hook Current admin hook.
 * @return void
 */
function jxw_enqueue_hours_admin( $hook ) {
	if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}

	$screen = get_current_screen();
	if ( ! $screen || 'butiksside' !== $screen->post_type ) {
		return;
	}

	wp_enqueue_style(
		'jxw-hours-admin',
		plugins_url( '../css/jxw-admin.css', __FILE__ ),
		array(),
		JXW_FIELDS_VERSION
	);
	wp_enqueue_script(
		'jxw-hours-admin',
		plugins_url( '../js/jxw-hours-admin.js', __FILE__ ),
		array( 'jquery' ),
		JXW_FIELDS_VERSION,
		true
	);
	wp_localize_script(
		'jxw-hours-admin',
		'jxwHoursAdmin',
		array(
			'alwaysLabel' => __( 'Alle uger', 'dianalund_txtdomain' ),
			'oddLabel'    => __( 'Ulige uger', 'dianalund_txtdomain' ),
			'evenLabel'   => __( 'Lige uger', 'dianalund_txtdomain' ),
			'deleteWeek'  => __( 'Slet uge', 'dianalund_txtdomain' ),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'jxw_enqueue_hours_admin' );

/**
 * Prints one exception row. $index null renders the JS template.
 *
 * @param array<string, string> $ex    Exception.
 * @param int|string            $index Index or {{index}}.
 * @return void
 */
function jxw_render_exception_row( $ex, $index ) {
	$name = 'jxw_hours_exceptions[' . $index . ']';
	?>
	<tr class="jxw-hours-exception">
		<td><input type="date" name="<?php echo esc_attr( $name ); ?>[from_date]" value="<?php echo esc_attr( $ex['from_date'] ); ?>" /></td>
		<td><input type="date" name="<?php echo esc_attr( $name ); ?>[to_date]" value="<?php echo esc_attr( $ex['to_date'] ); ?>" /></td>
		<td>
			<select name="<?php echo esc_attr( $name ); ?>[status]">
				<option value="" <?php selected( $ex['status'], '' ); ?>><?php echo esc_html__( 'Ikke angivet', 'dianalund_txtdomain' ); ?></option>
				<option value="open" <?php selected( $ex['status'], 'open' ); ?>><?php echo esc_html__( 'Åben', 'dianalund_txtdomain' ); ?></option>
				<option value="closed" <?php selected( $ex['status'], 'closed' ); ?>><?php echo esc_html__( 'Lukket', 'dianalund_txtdomain' ); ?></option>
			</select>
		</td>
		<td><input type="text" name="<?php echo esc_attr( $name ); ?>[label]" value="<?php echo esc_attr( $ex['label'] ); ?>" /></td>
		<td><input type="time" name="<?php echo esc_attr( $name ); ?>[from]" value="<?php echo esc_attr( $ex['from'] ); ?>" /></td>
		<td><input type="time" name="<?php echo esc_attr( $name ); ?>[to]" value="<?php echo esc_attr( $ex['to'] ); ?>" /></td>
		<td><button type="button" class="button jxw-hours-remove-exception"><?php echo esc_html__( 'Fjern', 'dianalund_txtdomain' ); ?></button></td>
	</tr>
	<?php
}

/**
 * Renders one collapsible week editor.
 *
 * @param array $week  Week payload.
 * @param int   $index Form index.
 * @param bool  $open  Whether the details element starts open.
 * @return void
 */
function jxw_render_week_editor( $week, $index, $open ) {
	$labels  = jxw_weekday_labels();
	$applies = isset( $week['applies'] ) ? $week['applies'] : 'always';
	$days    = isset( $week['days'] ) ? $week['days'] : jxw_empty_days();
	$label   = isset( $week['label'] ) ? $week['label'] : jxw_week_label( $applies );
	$prefix  = 'jxw_hours_weeks[' . (int) $index . ']';
	?>
	<details class="jxw-hours-week" data-applies="<?php echo esc_attr( $applies ); ?>" <?php echo $open ? 'open' : ''; ?>>
		<summary>
			<strong class="jxw-hours-week-title"><?php echo esc_html( $label ); ?></strong>
			<span class="jxw-hours-week-summary"><?php echo esc_html( jxw_week_summary_text( $days ) ); ?></span>
		</summary>
		<input type="hidden" class="jxw-hours-week-applies" name="<?php echo esc_attr( $prefix ); ?>[applies]" value="<?php echo esc_attr( $applies ); ?>" />
		<table class="widefat jxw-hours-days">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Dag', 'dianalund_txtdomain' ); ?></th>
					<th><?php echo esc_html__( 'Status', 'dianalund_txtdomain' ); ?></th>
					<th><?php echo esc_html__( 'Fra', 'dianalund_txtdomain' ); ?></th>
					<th><?php echo esc_html__( 'Til', 'dianalund_txtdomain' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( jxw_weekday_keys() as $key ) : ?>
				<?php $day = isset( $days[ $key ] ) ? $days[ $key ] : jxw_empty_day(); ?>
				<tr>
					<th scope="row"><?php echo esc_html( $labels[ $key ] ); ?></th>
					<td>
						<select name="<?php echo esc_attr( $prefix ); ?>[days][<?php echo esc_attr( $key ); ?>][status]">
							<option value="" <?php selected( $day['status'], '' ); ?>><?php echo esc_html__( 'Ikke angivet', 'dianalund_txtdomain' ); ?></option>
							<option value="open" <?php selected( $day['status'], 'open' ); ?>><?php echo esc_html__( 'Åben', 'dianalund_txtdomain' ); ?></option>
							<option value="closed" <?php selected( $day['status'], 'closed' ); ?>><?php echo esc_html__( 'Lukket', 'dianalund_txtdomain' ); ?></option>
						</select>
					</td>
					<td><input type="time" name="<?php echo esc_attr( $prefix ); ?>[days][<?php echo esc_attr( $key ); ?>][from]" value="<?php echo esc_attr( $day['from'] ); ?>" /></td>
					<td><input type="time" name="<?php echo esc_attr( $prefix ); ?>[days][<?php echo esc_attr( $key ); ?>][to]" value="<?php echo esc_attr( $day['to'] ); ?>" /></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<p class="jxw-hours-week-actions">
			<button type="button" class="button jxw-hours-delete-week"><?php echo esc_html__( 'Slet uge', 'dianalund_txtdomain' ); ?></button>
		</p>
	</details>
	<?php
}

/**
 * Shop hours metabox: structured days, exceptions, holidays, coordinates.
 * No "Arv fra center" column — the plugin never had center inheritance.
 *
 * @param WP_Post $post Post.
 * @return void
 */
function jxw_render_hours_metabox( $post ) {
	wp_nonce_field( 'jxw_save_hours_' . $post->ID, 'jxw_hours_nonce' );

	$payload  = jxw_coerce_hours_payload( jxw_get_field( $post->ID, 'jxw_hours' ) );
	$weeks    = isset( $payload['weeks'] ) ? $payload['weeks'] : array( jxw_empty_week( 'always', $payload['days'] ) );
	$parity   = count( $weeks ) > 1;
	$holidays = jxw_get_field( $post->ID, 'jxw_holidays_closed' );
	$lat      = jxw_get_field( $post->ID, 'jxw_lat' );
	$lng      = jxw_get_field( $post->ID, 'jxw_lng' );
	?>
	<p class="description"><?php echo esc_html__( 'Dage uden tider vises som "–" på forsiden. Lige/ulige uger følger ISO-ugenummeret.', 'dianalund_txtdomain' ); ?></p>
	<div id="jxw-hours-weeks" class="jxw-hours-weeks" data-parity="<?php echo $parity ? '1' : '0'; ?>">
		<?php
		foreach ( $weeks as $i => $week ) {
			$open = $parity ? ( 0 !== (int) $i ) : false;
			jxw_render_week_editor( $week, (int) $i, $open );
		}
		?>
	</div>
	<p>
		<button type="button" class="button" id="jxw-hours-enable-parity"<?php echo $parity ? ' hidden' : ''; ?>>
			<?php echo esc_html__( 'Forskellige tider i lige og ulige uger', 'dianalund_txtdomain' ); ?>
		</button>
	</p>

	<h3><?php echo esc_html__( 'Undtagelser', 'dianalund_txtdomain' ); ?></h3>
	<table class="widefat jxw-hours-exceptions" id="jxw-hours-exceptions">
		<thead>
			<tr>
				<th><?php echo esc_html__( 'Fra dato', 'dianalund_txtdomain' ); ?></th>
				<th><?php echo esc_html__( 'Til dato', 'dianalund_txtdomain' ); ?></th>
				<th><?php echo esc_html__( 'Status', 'dianalund_txtdomain' ); ?></th>
				<th><?php echo esc_html__( 'Tekst', 'dianalund_txtdomain' ); ?></th>
				<th><?php echo esc_html__( 'Fra', 'dianalund_txtdomain' ); ?></th>
				<th><?php echo esc_html__( 'Til', 'dianalund_txtdomain' ); ?></th>
				<th></th>
			</tr>
		</thead>
		<tbody>
			<?php
			if ( empty( $payload['exceptions'] ) ) {
				jxw_render_exception_row( jxw_empty_exception(), 0 );
			} else {
				foreach ( $payload['exceptions'] as $i => $ex ) {
					jxw_render_exception_row( $ex, (int) $i );
				}
			}
			?>
		</tbody>
	</table>
	<p><button type="button" class="button" id="jxw-hours-add-exception"><?php echo esc_html__( 'Tilføj undtagelse', 'dianalund_txtdomain' ); ?></button></p>
	<table class="form-table">
		<tr>
			<th scope="row"><?php echo esc_html__( 'Helligdage', 'dianalund_txtdomain' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="jxw_holidays_closed" value="1" <?php checked( '1', $holidays ); ?> />
					<?php echo esc_html__( 'Lukket på danske helligdage', 'dianalund_txtdomain' ); ?>
				</label>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="jxw_lat"><?php echo esc_html__( 'Breddegrad', 'dianalund_txtdomain' ); ?></label></th>
			<td><input type="number" step="any" min="-90" max="90" name="jxw_lat" id="jxw_lat" value="<?php echo esc_attr( $lat ); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="jxw_lng"><?php echo esc_html__( 'Længdegrad', 'dianalund_txtdomain' ); ?></label></th>
			<td><input type="number" step="any" min="-180" max="180" name="jxw_lng" id="jxw_lng" value="<?php echo esc_attr( $lng ); ?>" /></td>
		</tr>
	</table>
	<?php
}

add_action( 'wp_head', 'jxw_output_legacy_schema', 30 );

/**
 * Saves structured hours from the shop metabox.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function jxw_save_hours_from_request( $post_id ) {
	if ( ! isset( $_POST['jxw_hours_nonce'] ) ) {
		return;
	}

	$posted = wp_unslash( $_POST );
	$nonce  = isset( $posted['jxw_hours_nonce'] ) ? $posted['jxw_hours_nonce'] : '';
	if ( ! wp_verify_nonce( $nonce, 'jxw_save_hours_' . $post_id ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$weeks_in = isset( $posted['jxw_hours_weeks'] ) && is_array( $posted['jxw_hours_weeks'] ) ? $posted['jxw_hours_weeks'] : array();
	if ( empty( $weeks_in ) && isset( $posted['jxw_hours_days'] ) && is_array( $posted['jxw_hours_days'] ) ) {
		$weeks_in = array(
			array(
				'applies' => 'always',
				'days'    => $posted['jxw_hours_days'],
			),
		);
	}
	$days = jxw_empty_days();
	$raw_days = isset( $posted['jxw_hours_days'] ) && is_array( $posted['jxw_hours_days'] ) ? $posted['jxw_hours_days'] : array();
	foreach ( jxw_weekday_keys() as $key ) {
		if ( isset( $raw_days[ $key ] ) ) {
			$days[ $key ] = jxw_sanitize_day_row( $raw_days[ $key ] );
		}
	}

	$exceptions = array();
	$raw_ex     = isset( $posted['jxw_hours_exceptions'] ) && is_array( $posted['jxw_hours_exceptions'] ) ? $posted['jxw_hours_exceptions'] : array();
	foreach ( $raw_ex as $row ) {
		$clean = jxw_sanitize_exception_row( $row );
		if ( null !== $clean ) {
			$exceptions[] = $clean;
		}
	}

	update_post_meta(
		$post_id,
		'jxw_hours',
		jxw_sanitize_hours_payload(
			array(
				'weeks'      => $weeks_in,
				'days'       => $days,
				'exceptions' => $exceptions,
			)
		)
	);

	$holidays = ! empty( $posted['jxw_holidays_closed'] ) ? '1' : '';
	update_post_meta( $post_id, 'jxw_holidays_closed', $holidays );

	if ( isset( $posted['jxw_lat'] ) ) {
		update_post_meta( $post_id, 'jxw_lat', jxw_sanitize_lat( $posted['jxw_lat'] ) );
	}
	if ( isset( $posted['jxw_lng'] ) ) {
		update_post_meta( $post_id, 'jxw_lng', jxw_sanitize_lng( $posted['jxw_lng'] ) );
	}
}
