# Inventar – Dianalund CPT-plugin

Fase 0-skan af pluginet pr. 5. november 2026. Ingen kodeændringer i denne fase.

Plugin-header: `dianalund-cpt.php` version 2.0.0. Text domain: `dianalund_txtdomain`. Ingen `Requires PHP` i headeren. Eksisterende kode bruger PHP 7.0-syntaks (type hints, `[]`).

CPT'er: `butiksside` (slug `butik`), `erhvervsside` (slug `erhverv`), `foreningsside` (slug `forening`), `landingsside` (slug `landingsside`, permalink-slug fjernes). Alle tre virksomhedstyper har `show_in_rest` og taxonomy `category`. `landingsside` har ikke de nye felter og røres ikke ud over denne konstatering.

---

## 1. Meta-nøgler pr. CPT

Ingen ACF. Ingen `register_post_meta()`. Felter gemmes via metaboxe + `update_post_meta` / `add_post_meta` / `delete_post_meta`. Tomme værdier slettes. REST eksponerer et udvalg via `register_rest_field` i `dianalund-cpt.php`.

### 1.1 `butiksside`

| Nøgle | Type | Læses | Skrives |
| --- | --- | --- | --- |
| `butik_payed_name` | tekst | `includes/functions-cpt-butikker.php:79` (metabox), `dianalund-cpt.php:99` (Yoast-beskrivelse) | `includes/functions-cpt-butikker.php:164–182` (`save_butiks_meta`); CVR `includes/functions-cvr-sync.php:742,782` |
| `butik_payed_adress` | tekst | `includes/functions-cpt-butikker.php:80`; `dianalund-cpt.php:100`; `includes/functions-branch-shortcode.php:51` | `save_butiks_meta` L165; CVR L743 |
| `butik_payed_postal` | tekst | L81; `dianalund-cpt.php:101`; shortcode L52 | `save_butiks_meta` L166; CVR L744 |
| `butik_payed_city` | tekst | L82; `dianalund-cpt.php:102`; shortcode L52 | `save_butiks_meta` L167; CVR L745 |
| `butik_payed_phone` | tekst | L83; `dianalund-cpt.php:103` | `save_butiks_meta` L168; CVR L746 |
| `butik_payed_mail` | tekst/e-mail | L84; REST `dianalund-cpt.php:235–239`; blok `blocks/cpt-display/render.php:62` | `save_butiks_meta` L169; CVR L747 |
| `butik_payed_web` | URL-tekst | L85; REST L235; blok L61 | `save_butiks_meta` L170; CVR L748 |
| `butik_payed_fb` | URL-tekst | L86; REST L235; blok L59 | `save_butiks_meta` L171 |
| `butik_payed_insta` | URL-tekst | L87; REST L235; blok L60 | `save_butiks_meta` L172 |
| `butik_payed_payedbutik` | enum-streng: `Intet`, `Sølv`, `Guld`, `Platin`, `Anden aftale` | L88 (metabox); L221 (admin-kolonne); `dianalund-cpt.php:97` (Yoast); shortcode L39 | `save_butiks_meta` L173; quick edit L307–309 |
| `butik_aabentider` | HTML (TinyMCE-tabel) | `includes/functions-cpt-butikker.php:59` | `save_butiks_meta` L163 |
| `allround-cpt_logo_id` | attachment-id (streng) | L128; REST `dianalund-cpt.php:223–225`; blok L28 | `save_butiks_meta` L174 |
| `dianalund_cvr_nummer` | tekst | CVR-admin | CVR-sync |
| `dianalund_cvr_synced_at` | datetime-streng | CVR-admin | CVR-sync |

**Ikke fundet:** `butik_aabentider_v2`. Åbningstider findes kun som HTML i `butik_aabentider`.

### 1.2 `erhvervsside` (`bizz_*`)

| Nøgle | Type | Læses | Skrives |
| --- | --- | --- | --- |
| `bizz_payed_name` | tekst | `includes/functions-cpt-erhverv.php:90`; `dianalund-cpt.php:109` | `save_erhvervs_meta` L175; CVR L733 |
| `bizz_payed_adress` | tekst | L91; `dianalund-cpt.php:110` | L176; CVR L734 |
| `bizz_payed_postal` | tekst | L92; `dianalund-cpt.php:111` | L177; CVR L735 |
| `bizz_payed_city` | tekst | L93; `dianalund-cpt.php:112` | L178; CVR L736 |
| `bizz_payed_phone` | tekst | L94; `dianalund-cpt.php:113` | L179; CVR L737 |
| `bizz_payed_mail` | tekst | L95; REST L250; blok L68 | L180; CVR L738 |
| `bizz_payed_web` | URL-tekst | L96; REST L250; blok L67 | L181; CVR L739 |
| `bizz_payed_fb` | URL-tekst | L97; REST L250; blok L65 | L182 |
| `bizz_payed_insta` | URL-tekst | L98; REST L250; blok L66 | L183 |
| `bizz_payed_payedbizz` | enum-streng (samme som butik) | L99; admin-kolonne L231 | L184; quick edit L316–318 |
| `bizz_aabning_check` | `skalvises` eller tom | L60 | L185 |
| `erhverv_aabentider` | HTML | L69 | L174 |
| `allround-cpt_logo_id` | attachment-id | L139 | L186 |
| `dianalund_cvr_nummer` / `dianalund_cvr_synced_at` | se butik | CVR | CVR |

**Afvigelse:** Åbningstider ligger i `erhverv_aabentider`, ikke `bizz_aabentider`. Visning styres af `bizz_aabning_check`.

### 1.3 `foreningsside` (`fritid_*`)

| Nøgle | Type | Læses | Skrives |
| --- | --- | --- | --- |
| `fritid_payed_name` | tekst | `includes/functions-cpt-fritid.php:234`; mail-side L165 | `save_forenings_meta` L304; CVR L751 |
| `fritid_payed_adress` | tekst | L235 | L305; CVR L752 |
| `fritid_payed_postal` | tekst (default-visning 4293) | L236 | L306; CVR L753 |
| `fritid_payed_city` | tekst (default-visning Dianalund) | L237 | L307; CVR L754 |
| `fritid_payed_phone` | tekst | L238 | L308; CVR L755 |
| `fritid_payed_mail` | tekst | L239; mail-side L163; admin-kolonne L358; REST L265; blok L74 | L309; CVR L756 |
| `fritid_payed_show_mail` | `yes` eller tom | L240 | L310 |
| `fritid_payed_web` | URL-tekst | L241; REST L265; blok L73 | L311; CVR L757 |
| `fritid_payed_fb` | URL-tekst | L242; REST L265; blok L71 | L312 |
| `fritid_payed_sidstkontaktet` | datotekst | L243; kolonne L359 | L313 |
| `fritid_payed_vendttilbage` | datotekst | L244; kolonne L361 | L314 |
| `fritid_aabning_check` | `skalvises` eller tom | L203 | L303 |
| `fritid_aabentider` | HTML | L212 | L302 |
| `allround-cpt_logo_id` | attachment-id | L267 | L315 |
| `dianalund_cvr_nummer` / `dianalund_cvr_synced_at` | se butik | CVR | CVR |

**Ingen betalende-nøgle.** Kommentar i filen siger at foreninger registreres som betalte, men der er intet `fritid_payed_payed*` felt. Ingen Instagram-nøgle.

### 1.4 `landingsside`

Ingen kontakt-/åbningstids-meta. Kun CPT-registrering og permalink-trick i `includes/functions-cpt-landingpages.php`.

### 1.5 Term-meta (`category`)

| Nøgle | Type | Læses | Skrives |
| --- | --- | --- | --- |
| `_thumbnail` | attachment-id | `includes/functions-categorythumbnail.php:5` | **skrives ikke** (save-handler læser `$_POST['cat_thumbnail']` som ikke findes i formularen) |
| `_pagetitle` | tekst | ikke læst | L26 via `saveCategoryFields` |

Formularen poster `allround-cpt_logo_id`, som aldrig gemmes som term-meta. Feltet er i praksis i stykker. Uden for denne ombygnings scope, noteret som åbent punkt.

---

## 2. `centershop_*`, `get_option( $day . '_aaben' )`, `_lukket`, `_heltlukket`, `inherit`

**Ingen forekomster** i pluginet. Ingen center-arv, ingen center-optioner, ingen inherit-flag. Admin har ikke kolonnerne "Arv fra center" eller "Centerets tider".

---

## 3. `wp_head` og JSON-LD

**Ingen** `wp_head`-hooks, **ingen** JSON-LD, **ingen** `application/ld+json` eller schema.org-udskrivning i pluginet.

Yoast: filteret `wpseo_metadesc` i `dianalund-cpt.php:91–121` overskriver metabeskrivelsen for ubetalte butikker/erhverv (`payed* == "Intet"`) med navn, adresse og telefon.

---

## 4. Faktiske nøgler vs. fase 1-mapping

Kontaktfelterne matcher mappingen med præfiks:

| Mapping (ny) | Butik | Erhverv | Forening |
| --- | --- | --- | --- |
| `jxw_name` | `butik_payed_name` | `bizz_payed_name` | `fritid_payed_name` |
| `jxw_street` | `butik_payed_adress` | `bizz_payed_adress` | `fritid_payed_adress` |
| `jxw_postal` | `butik_payed_postal` | `bizz_payed_postal` | `fritid_payed_postal` |
| `jxw_city` | `butik_payed_city` | `bizz_payed_city` | `fritid_payed_city` |
| `jxw_phone` | `butik_payed_phone` | `bizz_payed_phone` | `fritid_payed_phone` |
| `jxw_email` | `butik_payed_mail` | `bizz_payed_mail` | `fritid_payed_mail` |
| `jxw_web` | `butik_payed_web` | `bizz_payed_web` | `fritid_payed_web` |
| `jxw_facebook` | `butik_payed_fb` | `bizz_payed_fb` | `fritid_payed_fb` |
| `jxw_instagram` | `butik_payed_insta` | `bizz_payed_insta` | — (findes ikke) |
| `jxw_hours` | `butik_aabentider` (HTML, **ikke** `_v2`) | `erhverv_aabentider` (HTML, ude af scope) | `fritid_aabentider` (HTML, ude af scope) |
| `jxw_paid` | `butik_payed_payedbutik` (enum, **ikke** boolean) | `bizz_payed_payedbizz` (enum) | **ingen nøgle** |

Abonnement er et produkttrin, ikke et boolean. Mappingen beder om `1` eller tom: `jxw_paid = 1` når gammel værdi er sat og ikke `Intet`.

---

## 5. Skabeloner, shortcodes, blokke, AJAX

### Skabeloner i pluginet (alle tomme)

`templates/single-butiksside.php`, `single-erhvervsside.php`, `single-foreningsside.php`, `content-butikheader.php`, `content-erhvervheader.php`, `visbutikslister.php`, `viserhvervslister.php`, `visforeninger.php`.

Pluginet registrerer **ikke** `template_include` og leverer ikke frontend-markup for åbningstider. Klassenavne som `butik-aabentider-tabel`, `idag`, `lukket` findes **ikke** i pluginet. Frontend antages at ligge i temaet.

Pluginet leverer **ikke** kategoriarkiv-skabelon.

### Shortcodes

`[diana-erhvervsliste diana_branche="{term_id}"]` i `includes/functions-branch-shortcode.php`. Læser `butik_payed_payedbutik`, `butik_payed_adress`, `butik_payed_postal`, `butik_payed_city`. CSS: `erhvervsliste`, `erhvervsliste-adresse`, `erhvervsliste-postnrby`.

### Blokke

`dianalund-cpt/cpt-display` (`blocks/cpt-display/`). Læser logo + sociale felter pr. CPT. Editoren henter REST-felter i `blocks/cpt-display/block.js`.

### AJAX

Kun CVR-sync (`wp_ajax_dianalund_cvr_*` i `includes/functions-cvr-sync.php`). Ingen AJAX for åbningstider.

---

## Afvigelser fra fase 1 (defaults brugt videre)

Prompten siger stop ved afvigelse. Nedenstående er verificerede afvigelser; de sikre defaults fra prompten bruges, så ombygningen kan gennemføres:

1. **`butik_aabentider_v2` findes ikke.** Migrér fra HTML i `butik_aabentider`. Der er ingen eksisterende parser — en HTML-parser tilføjes.
2. **Ingen center-arv.** Intet at fjerne. Dage uden tider vises som "–".
3. **Ingen JSON-LD i dag.** Tilføj `wp_head`-udskrivning bag `jxw_output_legacy_schema`, så jxw-seo kan slå den fra.
4. **`jxw_paid` er enum, ikke boolean.** Normaliser: alt andet end tom/`Intet` → `1`.
5. **Foreninger har intet betalende-felt.** `jxw_is_paid()` er falsk, medmindre `jxw_paid` sættes manuelt. Abonnement-select vises ikke for foreninger.
6. **Erhverv og foreninger får ikke `jxw_hours` i denne omgang.** Deres HTML-editor bevares på de gamle nøgler.
7. **Tomme plugin-skabeloner.** Frontend-HTML for åbningstider kan ikke sammenlignes i pluginet. Der tilføjes en render-helper med de specificerede CSS-klasser til temaet.
8. **Abonnementstrin (Sølv/Guld/Platin) har ingen ny nøgle.** Selecten beholdes og skriver stadig den gamle nøgle (driftsdata). `jxw_paid` sættes samtidig som boolean.
9. **CVR-sync skriver gamle nøgler.** Den bliver ved med det (ikke-destruktivt) og dual-writer også de nye nøgler.

Åbne punkter samles i `docs/CHANGELOG.md` efter fase 6.
