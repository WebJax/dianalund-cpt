# Changelog – fælles jxw-feltsæt

Plugin-version 2.1.0. 5. november 2026.

## Ændringer

- Fælles post-meta `jxw_*` på `butiksside`, `erhvervsside` og `foreningsside`. `landingsside` er urørt.
- `jxw_get_field()` / `jxw_is_paid()` med fald-tilbage til gamle nøgler.
- Idempotent migrering: `wp jxw migrate-fields` (tør kørsel som standard, `--live` skriver) og Værktøjer → JXW feltmigrering.
- Butikkers åbningstider redigeres som JSON (`days` + `exceptions`). HTML i `butik_aabentider` migreres via parser. Ingen center-arv fandtes i pluginet.
- Legacy JSON-LD i `wp_head` bag `jxw_output_legacy_schema`. `jxw_get_opening_hours_specs()` til jxw-seo.
- Kategori-introtekst `jxw_term_intro`. Pluginet har ingen kategoriarkiv-skabelon; se `docs/FIELDS.md`.
- Gamle nøgler slettes aldrig.

## Verifikation

Kørt med `php tests/jxw-verify.php` (WP-stubs, `WP_DEBUG`-ækvivalent E_ALL på PHP 8.3):

- HTML-parser, normalizer, inherit → ikke angivet
- Påske uden calendar-extension (2024–2026) og danske helligdage **uden** Store bededag
- `jxw_paid`: Guld/Sølv/Platin → `1`, Intet → tom
- Migrering af butik, erhverv og forening: kopiér, tør kørsel, ingen overwrite, gamle nøgler urørte, erhverv/forening uden `jxw_hours`
- JSON-LD er gyldigt JSON; lukkede dage er ikke i `regular`; butikkens undtagelse vinder over helligdag

Live WordPress med rigtige poster fandtes ikke i dette miljø. Produktion: kør `wp jxw migrate-fields` (tør), derefter `--live`, og stikprøv mindst tre butikker, to erhverv og tre foreninger. Plugin-skabelonerne er tomme, så frontend-HTML før/efter kan kun sammenlignes i temaet. Helper: `jxw_get_hours_table_html()` med klasserne `butik-aabentider-tabel`, `idag`, `lukket`.

## Åbne punkter

1. **Abonnementstrin** (Sølv/Guld/Platin) har ingen ny nøgle. Selecten skriver stadig den gamle nøgle plus `jxw_paid` som boolean.
2. **Foreninger har intet betalende-felt.** `jxw_is_paid()` er falsk, medmindre feltet sættes manuelt (via REST eller meta).
3. **Erhverv og foreninger** beholder HTML-åbningstider på `erhverv_aabentider` / `fritid_aabentider`. Ikke i denne omgang.
4. **Frontend ligger i temaet.** Tomme filer i `templates/` er uændrede. Temaet skal skiftes til helpers, ellers vises stadig gamle nøgler (bevidst, rollback-venligt).
5. **Ingen `butik_aabentider_v2`, centershop eller JSON-LD** fandtes før. Parser, schema og helligdage er ny adfærd bag filter.
6. **Kategori-thumbnail** (`includes/functions-categorythumbnail.php`) gemmer stadig forkert (`_pagetitle` vs. upload-feltet). Uden for scope.
7. **Foreningers tomme postnr/by** viste før 4293/Dianalund som placeholder i formularen. Placeholderen er fjernet; tomme felter er tomme indtil migrering/indtastning.
8. **Gutenberg-editor** læser logo via det gamle REST-felt; PHP-render bruger `jxw_get_field()`. Sociale URL'er i editor-preview kan være tomme indtil migrering, fordi `register_post_meta` lægger `jxw_*` i `meta`, ikke som top-level REST-felter.

## Rollback

Se `docs/MIGRATION.md`. Tøm `jxw_*` — helperen falder tilbage til de gamle nøgler.
