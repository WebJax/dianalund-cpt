# Migrering af CPT-felter

Kopierer gamle meta-nøgler til `jxw_*` når den nye nøgle er tom. Overskriver aldrig. Sletter aldrig gamle nøgler. Idempotent. Standard er tør kørsel.

Version: `_jxw_fields_migrated` = `1` (konstant `JXW_FIELDS_VERSION`).

## WP-CLI

```bash
# Tør kørsel (standard)
wp jxw migrate-fields
wp jxw migrate-fields --dry-run --post-type=butiksside --limit=20

# Skriv nye nøgler
wp jxw migrate-fields --live
wp jxw migrate-fields --live --post-type=erhvervsside
```

`--dry-run` vinder over `--live` hvis begge er sat.

## Admin

Værktøjer → JXW feltmigrering. Knapper: **Tør kørsel** og **Kør migrering**. Valgfri indholdstype og limit. Rapport som tabel og CSV-download.

Kræver `manage_options`. Nonce + `wp_unslash()` før sanitering.

## Hvad der kopieres

| Ny nøgle | Kilde | Regel |
| --- | --- | --- |
| Kontaktfelter | `{butik\|bizz\|fritid}_payed_*` | Kopiér hvis ny er tom. Sanitering pr. felt. |
| `jxw_paid` | `butik_payed_payedbutik` / `bizz_payed_payedbizz` | `1` hvis gammel værdi ikke er tom/`Intet`, ellers tom. Foreninger har ingen kilde. |
| `jxw_hours` | `butik_aabentider_v2` hvis den findes, ellers HTML i `butik_aabentider` | Kun `butiksside`. Inherit-dage får `status=open` og tomme tider og rapporteres som `ikke angivet`. |

Erhverv (`erhverv_aabentider`) og foreninger (`fritid_aabentider`) migreres ikke. `jxw_lat`, `jxw_lng` og `jxw_holidays_closed` har ingen legacy-kilde.

Rapportkolonner: `post-id`, `felt`, `gammel værdi`, `ny værdi`, `status` (`kopieret`, `sprunget over`, `fejl`, `ikke angivet`).

Kørslen behandler poster i batches à 50.

## Rollback

1. Stop med at stole på de nye nøgler: `jxw_get_field()` falder automatisk tilbage til gamle nøgler når den nye er tom.
2. Tøm eller slet `jxw_*` og `_jxw_fields_migrated` på de berørte poster — de gamle nøgler er urørte.
3. Metaboxe gemmer fremover kun til `jxw_*` (plus abonnementstrin på den gamle nøgle). En gemt post efter rollback vil skrive nye nøgler igen.

Eksempel (WP-CLI, ét indlæg):

```bash
wp post meta delete 123 jxw_name
wp post meta delete 123 jxw_street
# …øvrige jxw_* nøgler
wp post meta delete 123 _jxw_fields_migrated
```

Ingen database-sletninger kører automatisk.

## Verifikation

Efter live-kørsel: for stikprøver af mindst tre butikker, to erhverv og tre foreninger skal `jxw_get_field()` returnere samme synlige værdi som den gamle nøgle (betaling normaliseret til `1`/tom). Frontend-HTML er uændret så længe temaet stadig læser gamle nøgler; plugin-skabelonerne er tomme.
