# JXW feltsæt

Fælles post-meta for `butiksside`, `erhvervsside` og `foreningsside`. Registreret med `register_post_meta()` (`single` true, `show_in_rest` true, `auth_callback` = `edit_post`). `landingsside` har **ikke** disse felter.

Læs via `jxw_get_field( $post_id, $field )`. `$field` kan være `name` eller `jxw_name`. Helperen returnerer den nye nøgle og falder tilbage til den gamle CPT-præfiksede nøgle, hvis den nye er tom. Betaling: `jxw_is_paid( $post_id )`.

Text domain: `dianalund_txtdomain`. Prefiks: `jxw_`.

## Post-meta

| Nøgle | Type | Sanitering | Legacy (butik / erhverv / forening) | REST | Bemærkning |
| --- | --- | --- | --- | --- | --- |
| `jxw_name` | string | `sanitize_text_field` | `butik_payed_name` / `bizz_payed_name` / `fritid_payed_name` | ja | Offentligt navn. Schema falder tilbage til indlægstitlen. |
| `jxw_street` | string | `sanitize_text_field` | `*_payed_adress` | ja | Gade og husnummer. |
| `jxw_postal` | string | 4 cifre | `*_payed_postal` | ja | Første gruppe af 4 cifre. |
| `jxw_city` | string | `sanitize_text_field` | `*_payed_city` | ja | |
| `jxw_phone` | string | tal, mellemrum, `+`, `-` | `*_payed_phone` | ja | |
| `jxw_email` | string | `sanitize_email` | `*_payed_mail` | ja | Tom hvis ugyldig. |
| `jxw_web` | string | `esc_url_raw` | `*_payed_web` | ja | `https://` tilføjes hvis protokol mangler. |
| `jxw_facebook` | string | `esc_url_raw` | `*_payed_fb` | ja | |
| `jxw_instagram` | string | `esc_url_raw` | `*_payed_insta` / `*_payed_insta` / — | ja | Foreninger havde intet Instagram-felt. |
| `jxw_hours` | string (JSON) | `jxw_sanitize_hours_payload` | `butik_aabentider_v2` (mangler i data) → HTML `butik_aabentider` | ja | Kun butik migreres og redigeres. Se strukturen nedenfor. |
| `jxw_paid` | string | `1` eller tom | `butik_payed_payedbutik` / `bizz_payed_payedbizz` / — | ja | `1` når gammel værdi ikke er tom/`Intet`. Foreninger har intet legacy-felt. |
| `jxw_holidays_closed` | string | `1` eller tom | — | ja | Lukket på danske helligdage. |
| `jxw_lat` | string | decimal −90…90 | — | ja | Tom tilladt. |
| `jxw_lng` | string | decimal −180…180 | — | ja | Tom tilladt. |

Intern markeringsnøgle (ikke REST, ikke i feltsættet til jxw-seo): `_jxw_fields_migrated` = versionsnummer (`1`).

## Åbningstider (`jxw_hours`)

JSON-objekt. Forbrugere skal kalde `jxw_normalize_hours( $raw, $date = null )` — den returnerer altid en liste af tidsrum pr. dag for den uge, der gælder på `$date` (i dag hvis udeladt).

To tilstande:

- Én uge med `applies: "always"` (standard, også ældre `{ "days": … }` uden `weeks`).
- To uger med `applies: "odd"` og `"even"` — ISO-8601 ugenummer (uge 1, 3, 5… er ulige).

```json
{
  "weeks": [
    {
      "id": "odd",
      "label": "Ulige uger",
      "applies": "odd",
      "days": {
        "monday": { "status": "open", "from": "10:00", "to": "18:00" }
      }
    },
    {
      "id": "even",
      "label": "Lige uger",
      "applies": "even",
      "days": {
        "monday": { "status": "open", "from": "10:00", "to": "16:00" }
      }
    }
  ],
  "days": {
    "monday": { "status": "open", "from": "10:00", "to": "18:00" }
JSON-objekt. Forbrugere skal kalde `jxw_normalize_hours()` — den returnerer altid en liste af tidsrum pr. dag, også når der kun er ét.

```json
{
  "days": {
    "monday":    { "status": "open", "from": "10:00", "to": "18:00" },
    "tuesday":   { "status": "open", "from": "10:00", "to": "18:00" },
    "wednesday": { "status": "open", "from": "10:00", "to": "18:00" },
    "thursday":  { "status": "open", "from": "10:00", "to": "18:00" },
    "friday":    { "status": "open", "from": "10:00", "to": "18:00" },
    "saturday":  { "status": "open", "from": "10:00", "to": "13:00" },
    "sunday":    { "status": "closed", "from": "", "to": "" }
  },
  "exceptions": [
    {
      "from_date": "2026-12-24",
      "to_date": "2026-12-26",
      "status": "closed",
      "label": "Jul",
      "from": "",
      "to": ""
    }
  ]
}
```

`days` på topniveau er den uge, der gælder i dag (kompatibilitet). Kilde til sandhed er `weeks`. `applies`: `always` | `odd` | `even`. `status`: `open` | `closed` | tom. Tid: `HH:MM`. Dato: `YYYY-MM-DD`.
`status`: `open` | `closed` | tom (`""` = ikke angivet). Tid: `HH:MM`. Dato: `YYYY-MM-DD`. Dage uden tider vises som "–" i tabellen og udelades i schema `regular`.

Normalizer-output:

```php
array(
  'days' => array(
    'monday' => array(
      array( 'from' => '10:00', 'to' => '18:00' ),
    ),
  ),
  'exceptions' => array( /* samme nøgler som ovenfor */ ),
  'applies' => 'odd',
)
```

Schema `regular` er kun den uge, der gælder i dag — ikke begge mønstre som om de gjaldt hver uge. `jxw_get_opening_hours_specs( $post_id )` → `array( 'regular' => array(), 'special' => array() )`.

Frontend: `jxw_get_hours_table_html()` viser én tabel, eller to med overskrifterne “Ulige uger” / “Lige uger”. `idag` sidder kun på den aktuelle ISO-uge.
)
```

Schema-hjælper: `jxw_get_opening_hours_specs( $post_id )` → `array( 'regular' => array(), 'special' => array() )`.

Legacy JSON-LD i `wp_head` kan slås fra med:

```php
add_filter( 'jxw_output_legacy_schema', '__return_false' );
```

## Term-meta (`category`)

| Nøgle | Type | Sanitering | REST | Helper |
| --- | --- | --- | --- | --- |
| `jxw_term_intro` | string (HTML) | `wp_kses_post` | ja | `jxw_get_term_intro( $term_id )` |

SEO-felter for kategorier (title, description, robots) hører til jxw-seo og findes ikke her.

Pluginet har **ingen** kategoriarkiv-skabelon. Temaet viser introteksten, f.eks. i `category.php`:

```php
$intro = jxw_get_term_intro( get_queried_object_id() );
if ( '' !== $intro ) {
	echo '<div class="jxw-term-intro">' . apply_filters( 'the_content', $intro ) . '</div>';
}
```

## PHP API til jxw-seo

```php
jxw_get_field( int $post_id, string $field );          // string
jxw_is_paid( int $post_id );                           // bool
jxw_normalize_hours( $raw, $date = null );             // array{days, exceptions, applies}
jxw_week_for_date( $payload, $date );                  // uge der gælder på datoen
jxw_iso_week_parity( $date );                          // odd|even (ISO-8601)
jxw_normalize_hours( $raw );                           // array{days, exceptions}
jxw_get_opening_hours_specs( int $post_id );           // array{regular, special}
jxw_get_hours_table_html( int $post_id );              // string, klasser uændrede
jxw_get_term_intro( int $term_id );                    // string
jxw_supported_post_types();                            // string[]
```

Gamle nøgler slettes aldrig. Rollback er at lade helperen falde tilbage til dem (slet de nye nøgler, eller tøm dem).
