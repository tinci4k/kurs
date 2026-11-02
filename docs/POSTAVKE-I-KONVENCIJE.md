# POSTAVKE I KONVENCIJE — kurskc.hr / KURS

> **PROČITAJ OVO PRVO.** Ova datoteka opisuje lokalni dev setup, naming konvencije, boje i debug logiranje za projekt KURS (Koprivnička udruga za rekreativni sport, malonogometne lige).
>
> **VAŽNO:** `CLAUDE.md` u korijenu repozitorija bio je **zastario i mjestimično netočan**, ali je **ažuriran 2026-06-22** i sada je usklađen s kodom. Sekcija **"Neusklađenosti CLAUDE.md ↔ stvarni kod"** na dnu ove datoteke ostaje kao povijesni zapis i potvrda točnog modela (CPT-ovi, taksonomije, CSS boje).

---

## 1. Lokalni razvojni setup (Local by Flywheel)

Projekt se vrti lokalno preko **Local by Flywheel**. Korijen WordPress instalacije je `app/public/` unutar repozitorija (`C:\Users\Tinci\Local Sites\kurshr\`).

| Stavka | Vrijednost | Izvor |
|---|---|---|
| Lokalni URL | `http://kurshr.local` (**napomena:** domena je `kurshr`, NE `kurskc`) | CLAUDE.md |
| Produkcijska domena | `kurskc.hr` (spominje se u plugin/theme headerima, autor URL) | `kurs-liga.php` |
| WP korijen | `app/public/` | putanje u debug.log |
| PHP | 8.1+ (plugin header `Requires PHP: 8.1`) | `kurs-liga.php:10` |
| OS | Windows 11 | dev okruženje |
| Primarni shell | PowerShell (Bash dostupan za POSIX skripte) | dev okruženje |

### 1.1 Baza podataka (`app/public/wp-config.php`, linije 24–39)

| Konstanta | Vrijednost (lokalni dev) |
|---|---|
| `DB_NAME` | `local` |
| `DB_USER` | `root` |
| `DB_PASSWORD` | `root` (lokalni dev, nije osjetljivo) |
| `DB_HOST` | `localhost` |
| `DB_CHARSET` | `utf8` |
| `DB_COLLATE` | `''` (prazno) |
| `$table_prefix` | `wp_` (`wp-config.php:71`) |

Auth keys/salts su postavljeni u `wp-config.php` (linije 52–60) — lokalni dev set, nije produkcijski.

### 1.2 Debug i okruženje (`wp-config.php`, linije 90–95)

| Konstanta | Vrijednost | Učinak |
|---|---|---|
| `WP_DEBUG` | `true` | debug mode uključen |
| `WP_DEBUG_LOG` | `true` | log se piše u `app/public/wp-content/debug.log` |
| `WP_DEBUG_DISPLAY` | `false` | greške se NE prikazuju na ekranu |
| `@ini_set('display_errors', 0)` | — | dodatno gušenje prikaza grešaka |
| `WP_ENVIRONMENT_TYPE` | `'local'` | WP zna da je lokalno okruženje |

**Pravilo:** sve PHP greške/warninge tražiš u `app/public/wp-content/debug.log`, NE na ekranu.

### 1.3 Local runtime — portovi i GOTCHA s php-cgi workerima (2026-10-16)

| Servis | Gdje | Napomena |
|---|---|---|
| nginx | `conf/nginx/site.conf.hbs` → runtime `…/AppData/Roaming/Local/run/df7bZ9tGl/conf/nginx/site.conf` | `upstream php { server 127.0.0.1:10008; server 127.0.0.1:10009; }` — **round-robin** na dva php-cgi workera; `fastcgi_read_timeout 1200s` |
| php-cgi | portovi **10008** i **10009** | PHP 8.2.30 (`lightning-services/php-8.2.30+1`) |
| MySQL | **127.0.0.1:10011** | `wp-config.php` ima `DB_HOST='localhost'`; port dolazi iz Localovog `php.ini` (`mysqli.default_port = 10011`, isto `pdo_mysql`/`mysql`) |

> **GOTCHA (svaki drugi zahtjev visi):** ako jedan od dva php-cgi workera zapne (npr. beskonačna petlja / dugi upit), nginx i dalje šalje svaki drugi zahtjev na njega → **svaki drugi zahtjev čeka do timeouta (1200 s)**, a ostali rade normalno. To izgleda kao „nasumično" visenje stranice. Rješenje: **restart sitea u Local aplikaciji** (Stop → Start site).

> **GOTCHA (PHP iz CLI-ja):** Localov `php.exe` iz terminala bez `PHPRC` ne učitava Localov `php.ini` → spaja se na MySQL port 3306 i ne vidi bazu. Uvijek postavi `PHPRC` na `…/Local/run/df7bZ9tGl/conf/php` (vidi §1.4; isto radi i `app/.envrc`).

### 1.4 Demo podaci — `tools/demo-podaci.php` (NOVO 2026-10-16, SAMO lokalno)

CLI skripta u korijenu repozitorija (`tools/`, **izvan** teme i plugina) koja puni lokalnu bazu realističnim demo podacima da se vide „Po kolima", „Za medije", ticker i naslovnica.

**Pokretanje** (Git Bash, iz korijena repozitorija):
```bash
PHPRC="C:/Users/Tinci/AppData/Roaming/Local/run/df7bZ9tGl/conf/php" \
"/c/Users/Tinci/AppData/Roaming/Local/lightning-services/php-8.2.30+1/bin/win64/php.exe" tools/demo-podaci.php          # napuni
# … tools/demo-podaci.php obrisi                                                                                         # samo obriši
```

**Što radi:**
- Guard: samo `PHP_SAPI === 'cli'` (web zahtjev odmah izlazi), učitava `app/public/wp-load.php`, traži aktivan plugin.
- **Uvijek najprije briše prethodni demo set** (opcija `kurs_demo_seed`); argument `obrisi` = samo brisanje.
- Uzima **najnoviju KC sezonu** (`league = 'kc_liga'`), po potrebi `Kurs_Season::scaffold()`.
- Dodaje **3 demo ekipe** (MNK Drava, Veterani Bilogora, NK Stari grad) + po 6 igrača; svi postovi imaju meta **`_kurs_demo = 1`**.
- **KC Liga = prvih 10 ekipa** (po ID-u), **Liga bez briga = sljedećih 9** (neparno → svako kolo jedna slobodna); rosteri iz postojećih `squads`.
- `Kurs_Match::generate_round_robin()` → 9 kola, **subotama od 12. 9. 2026.** (KC Liga od 15 h, Liga bez briga od 10 h, svaka utakmica +1 h), suci.
- Utakmice s datumom **prije današnjeg** se „odigraju" (Poisson golovi, ~1/12 penali, povremeni autogolovi, žuti/crveni kartoni). Na dan pisanja (16. 10. 2026.) to su kola 1–5, a 6. kolo je sutrašnji termin. **Jedna utakmica 4. kola KC Lige je namjerno odgođena** (neodigrana, datum u prošlosti → „odgođeno").
- U **svim odigranim utakmicama grupne faze koje nemaju nijedan gol** (u praksi Pan 2026) dodaje strijelce prema rezultatu.
- Sve dodano bilježi u opciju `kurs_demo_seed` (`divisions`, `events`, `posts`) i okida `kurs_data_changed`.

> ⚠️ **GOTCHA (destruktivno):** skripta **prazni obje lige najnovije KC sezone** (briše njihove utakmice, eventove, `group_teams` i `squads`) prije punjenja, a `obrisi` ih ostavlja **prazne** — ne vraća prethodno stanje. Ne pokretati nad KC sezonom sa stvarnim podacima.

> **Deploy napomena:** skripta nije dio teme/plugina pa je Plesk deployment akcije (`cp` teme i plugina) ne kopiraju u WordPress. Ali Plesk „Deploy files" istrese cijeli repo u document root, a završni `rm -rf` (app, docs, CLAUDE.md…) **ne uključuje `tools/`** — ako je `tools/` commitan, ostat će `httpdocs/tools/demo-podaci.php` (bezopasno: CLI guard + `wp-load.php` putanja ne postoji na serveru). Po želji dodaj `tools` u `rm -rf` deployment akciju.

**Stanje 2026-10-16:** demo podaci su trenutno **učitani u lokalnu bazu**.

---

## 2. Arhitektura na visokoj razini

Projekt ima **dva** glavna dijela:

1. **Plugin `kurs-liga`** (`app/public/wp-content/plugins/kurs-liga/`) — **sva domenska logika**: lige, sezone, divizije, grupe, utakmice, događaji (golovi/kartoni), playoff bracket, admin sučelje. CLAUDE.md ovaj plugin **uopće ne spominje** — to je kritični propust.
2. **Tema `kurskc`** (`app/public/wp-content/themes/kurskc/`) — vlastita tema, front-end prikaz, registracija CPT-a `vijest` i taksonomije `vijest_kategorija`, automatsko kreiranje stranica.

**Tema ovisi o pluginu:** `themes/kurskc/functions.php:13-17` uvjetno `require`-a `WP_PLUGIN_DIR/kurs-liga/includes/helpers.php`. Ako je plugin deaktiviran, helper funkcije za badge/boje ekipa nedostaju.

---

## 3. Naming konvencije

### 3.1 Plugin `kurs-liga` (`kurs-liga.php`)

| Element | Konvencija / vrijednost |
|---|---|
| Verzija | `KURS_VERSION = '1.20.1'` (header plugina `Version: 1.2.0` se ne podiže; podigni `KURS_VERSION` pri izmjeni `admin.css`/`admin.js` — cache-busting) |
| Putanje | `KURS_DIR` = `plugin_dir_path(__FILE__)`, `KURS_URL` = `plugin_dir_url(__FILE__)` |
| Prefiks | `KURS_PREFIX = 'kurs_'` |
| Ovlast | `KURS_CAP = 'manage_kurs_liga'` (cijeli KURS admin; ranije `manage_options`) |
| Klase | `Kurs_Database`, `Kurs_CPT`, `Kurs_Season`, `Kurs_Match`, `Kurs_Playoff`, `Kurs_Rounds` (2026-10-16), `Kurs_Ticker`, `Kurs_Roles`, `Kurs_Admin` |
| Konstante domene | `Kurs_Season::COMPETITIONS` (ključevi `kc_liga` / `pan_ljetna` = vrijednosti `seasons.league`), `Kurs_Season::PAN_GROUP_OPTIONS = [2, 4]` |
| Hookovi | `kurs_data_changed` — okidaj nakon svake promjene rezultata/rasporeda (ruši cache tickera i `kurskc_active_league`) |
| Admin stranice (slugovi) | prefiks `kurs-` (asseti se učitavaju samo kad hook sadrži `kurs-`): `kurs-dashboard` (Pregled), **`kurs-kc` / `kurs-pan`** (stranica natjecanja; `Kurs_Admin::HUBS` → ključ `COMPETITIONS`; kartice `&tab=pregled\|ekipe\|utakmice\|postavke`), `kurs-teams` (Ekipe), `kurs-players` (Igrači, 1.19.0), `kurs-seasons` (Sve sezone), `kurs-settings` (Postavke), skriven `kurs-match-record`. Legacy (samo preusmjeravaju): `kurs-season-setup`, `kurs-matches`, `kurs-roster`. Linkove na stranicu natjecanja gradi `Kurs_Admin::hub_url($season_id, $tab, $args)`. |
| CPT-ovi | `kurs_ekipa` (Ekipe), `kurs_igrac` (Igrači) — registrira `Kurs_CPT` na `init` |
| Funkcije / helperi | prefiks `kurs_` (npr. `kurs_team_color`, `kurs_team_badge_html`, `kurs_team_text_color` u `includes/helpers.php`) |
| Post meta | prefiks `_kurs_` (npr. `_kurs_team_color`) |
| Opcije | prefiks `kurs_` (`kurs_db_version`, `kurs_flush_needed`, `kurs_ticker_offseason`; lokalno i `kurs_demo_seed` iz `tools/demo-podaci.php`) |

### 3.2 DB tablice (`includes/class-database.php`)

Prefiks tablica: `$wpdb->prefix . KURS_PREFIX` = **`wp_kurs_`**. DB verzija: `Kurs_Database::DB_VERSION = '1.13'` (sprema se u opciju `kurs_db_version`). Donja tablica prikazuje glavne stupce; kasnije dodani (`seasons.locked/max_fouls_per_half`, `divisions.has_playoff/advance_per_group`, `matches.referee1/2`, `match_events.half`) opisani su u `PLUGIN-KURS-LIGA.md` §3.2.

| Tablica | Svrha | Ključni stupci |
|---|---|---|
| `wp_kurs_seasons` | Sezone natjecanja | id, league, name, year, status, referees (JSON suci sezone), created_at |
| `wp_kurs_divisions` | Lige (divizije) unutar sezone (KC: „KC Liga" + „Liga bez briga"; Pan: „Pan Ljetna liga") | id, season_id, name, sort_order, has_playoff, advance_per_group |
| `wp_kurs_phases` | Faze (group/playoff) | id, division_id, name, type, sort_order |
| `wp_kurs_groups` | Grupe (Grupa A, B…) | id, phase_id, name, playoff_spots |
| `wp_kurs_group_teams` | Ekipe dodijeljene grupama | id, group_id, team_id (BIGINT → `wp_posts.ID`) |
| `wp_kurs_squads` | Roster igrača po ekipi/diviziji (trenutni) | id, player_id, team_id, division_id, jersey_no |
| `wp_kurs_match_players` | Postava utakmice (spremljena pri završetku, DB 1.12) | id, match_id, team_id, player_id, jersey_no |
| `wp_kurs_matches` | Utakmice grupne faze | id, group_id, home/away_team_id, home/away_score, match_date, round, status |
| `wp_kurs_match_events` | Golovi/kartoni/prekršaji | id, match_id, team_id, player_id, event_type (goal/penalty/own_goal/yellow_card/second_yellow/red_card/foul + inertni captain/absent), minute, half, scored_for |
| `wp_kurs_playoff_matches` | Playoff bracket | id, division_id, round (qf/sf/final/3rd), slot, home/away_team_id, home/away_score, status, match_date, home/away_label, match_id |

**Ključno:** `team_id` / `player_id` su `BIGINT` i referenciraju `wp_posts.ID` (postovi tipa `kurs_ekipa` / `kurs_igrac`). Utakmice **NISU** CPT — žive u tablici `wp_kurs_matches`.

### 3.3 Tema `kurskc` (`functions.php`)

| Element | Konvencija / vrijednost |
|---|---|
| Verzija | `KURSKC_VERSION = '1.0.42'` (podigni pri izmjeni `main.css`/`main.js` — cache-busting) |
| Putanje | `KURSKC_DIR` = `get_template_directory()`, `KURSKC_URI` = `get_template_directory_uri()` |
| Funkcije | prefiks `kurskc_` (`kurskc_setup`, `kurskc_create_pages`, `kurskc_register_post_types`, `kurskc_enqueue_assets`, `kurskc_maybe_flush`, `kurskc_active_league`…); plus poneka `kurs_` (`kurs_time_ago`, `kurs_hub_url` u `page-liga.php`) |
| Klase | `Kurskc_Media_Kit` (`inc/media-kit.php`, 2026-10-16) |
| Opcije / transienti | `kurskc_flush_needed`; transient `kurskc_active_league` (30 min, ruši se na `kurs_data_changed`) |
| Registrira | CPT `vijest` + taksonomiju `vijest_kategorija` (u `inc/post-types.php`); CPT `sponzor` (u `inc/sponsors.php`) |

### 3.4 Stvarni theme template fajlovi

`page.php`, `404.php`, `page-liga.php`, `footer.php`, `single.php`, `front-page.php`, `search.php`, `single-kurs_igrac.php`, `single-kurs_ekipa.php`, `page-utakmica.php`, `page-za-medije.php` (2026-10-16), `page-kontakt.php`, `index.php`, `functions.php`, `page-o-nama.php`, `header.php`.
`inc/`: `nav-menus.php`, `enqueue.php`, `post-types.php`, `news.php`, `sponsors.php`, `media-kit.php` (2026-10-16).
`template-parts/`: `content.php`, `content-none.php`.
Izvan teme (ne deploya se kao dio WP-a): `tools/demo-podaci.php` (§1.4).

---

## 4. CSS boje i varijable

**Stvarne** CSS varijable su definirane u `themes/kurskc/assets/css/main.css` u `:root` (linije 7–27). Koriste prefiks **`--c-*`** (NE `--color-*` kako tvrdi CLAUDE.md).

```css
:root {
  /* Fontovi */
  --font-body:  'Barlow', sans-serif;
  --font-cond:  'Barlow Semi Condensed', sans-serif;
  --font-mono:  'Space Mono', monospace;

  /* Neutralne */
  --c-bg:     #F4F4EE;   --c-dark:   #111317;   --c-dark2:  #1b1d22;
  --c-white:  #fff;      --c-border: #e6e6df;   --c-muted:  #9a9a94;
  --c-text:   #111317;

  /* Brend / statusne */
  --c-kc:     #DA0F1E;   /* KC liga — crvena */
  --c-pan:    #F47B16;   /* Pan ljetna liga — narančasta */
  --c-gold:   #FDE206;   --c-bronze: #B07A1B;
  --c-win:    #1F7A43;   --c-draw:   #c9a227;   --c-loss: #DA0F1E;
}
```

**Napomena:** `body` ima tvrdo kodiranu pozadinu `#d8d6cf` (boja platna oko sadržajnog boxa), `main.css:33`.

### 4.1 Fontovi

Učitavaju se s Google Fonts u `inc/enqueue.php`: **Barlow**, **Barlow Semi Condensed**, **Space Mono**. `wp_enqueue_scripts` hook → handle `kurskc-main` (CSS, ovisi o `kurskc-fonts`) i `kurskc-main` (JS, u footeru).

**Pravilo:** boje mijenjaj SAMO u `:root` bloku `main.css`. Ne traži `--color-primary` / `--color-accent` — ne postoje.

---

## 5. Hookovi i posebnosti ponašanja

- `Kurs_CPT` se instancira u `kurs-liga.php:27`, registrira `kurs_ekipa` / `kurs_igrac` na `init`.
- `Kurs_Database::maybe_upgrade` na `plugins_loaded`; `Kurs_Database::install` na `register_activation_hook`.
- Više `flush_rewrite_rules` mehanizama: opcija `kurs_flush_needed` (`init` 99) i samoizlječenje kad u `rewrite_rules` nema `ekipe/…` (`init` 100) u pluginu; u temi `kurskc_flush_needed`.
- **Tema automatski kreira stranice** (`kurskc_create_pages`, hook `after_switch_theme` + `init` priority 20) ako nedostaju: `kc-liga`, `pan-ljetna` (template `page-liga.php`), `utakmica` (`page-utakmica.php`), **`za-medije`** (`page-za-medije.php`, od 2026-10-16), te `o-kurs-u` (`page-o-nama.php`, `init` priority 25 s tvrdo kodiranim Gutenberg sadržajem). **Sigurnosna mreža** — može iznenaditi pri ručnom brisanju stranica jer ih sustav ponovno stvori.
- **`kurs_data_changed`** (plugin action) — okidaju ga `Kurs_Match` create/finish/reopen/delete, `Kurs_Playoff` finish/reopen/reset, zaključavanje sezone i `ajax_update_match`; slušaju ga `Kurs_Ticker::flush` i tema (`delete_transient('kurskc_active_league')`).
- `admin_menu` hook skriva default WP "Objave" (`edit.php`) i "Komentari"; `load-edit.php` redirecta na `edit.php?post_type=vijest`.
- **Plugin admin (1.16.0):** `Kurs_Admin::redirect_legacy_pages` na `admin_init` **prioritet 5** (prije `handle_forms`) preusmjerava stare slugove `kurs-season-setup` / `kurs-matches` / `kurs-roster` (i `kurs-seasons&action=edit`) na stranicu natjecanja / uređivanje ekipe. Ti slugovi moraju ostati registrirani (`__return_null`) — WP provjerava pristup stranici prije `admin_init`. Filteri `parent_file` / `submenu_file` drže izbornik otvorenim na skrivenom zapisniku.

---

## 6. Debug logiranje — poznate greške u `debug.log`

Sljedeće se ponavljalo u logu. Stavke 1–3 su **ISPRAVLJENE 2026-06-22** (vidi [`ISPRAVCI.md`](ISPRAVCI.md)); 4–5 su benigne/okolinske.

1. ✅ **ISPRAVLJENO — SQL syntax error:** `Kurs_Database::maybe_upgrade` izvršavao `ALTER TABLE … ADD COLUMN IF NOT EXISTS …` (nije podržano u MySQL-u, samo MariaDB) za `wp_kurs_groups.playoff_spots` i `wp_kurs_playoff_matches.match_id`. Sada uklonjeno — stupce dodaje `self::install()` / `dbDelta`.
2. ✅ **ISPRAVLJENO — "headers already sent" iz `match-record-pdf.php:20`:** PDF se sada renderira na `admin_init` (`Kurs_Admin::maybe_render_pdf`) prije admin headera. *(Napomena: zasebni "headers already sent" iz `admin-header.php:255` bio je vezan uz redirect-nakon-ispisa na napuštenoj `playoff.php` stranici — ta je datoteka 2026-10-16 obrisana pri restrukturiranju admina.)*
3. ✅ **ISPRAVLJENO — Undefined variable** u `match-record-pdf.php` (`$disp_home`/`$disp_away`/`$is_finished`): izračun premješten prije PDF includea u `match-record.php`.
4. **PHP Deprecated** `strip_tags(): Passing null` iz `admin-header.php:41` — uglavnom WP core / PHP 8.1 deprecation, ne projektni kod (nije dirano). Javlja se na skrivenim podstranicama s `parent = null` (zapisnik `kurs-match-record`; legacy slugovi se preusmjere prije ispisa).
5. **`wp_version_check` / `wp_update_plugins` warninzi** — Local nema vezu prema WordPress.org (offline dev), nije projektni bug.

---

## 7. ⚠️ Neusklađenosti CLAUDE.md ↔ stvarni kod (OBAVEZNO PROČITATI)

> **Napomena (2026-06-22):** `CLAUDE.md` je u međuvremenu **prepravljen** da odgovara stvarnom kodu, pa donja tablica više nije "live" odstupanje nego povijesni zapis. Stupac **"Stvarni kod" i dalje vrijedi kao izvor istine** o CPT-ovima, taksonomijama i bojama.

| # | CLAUDE.md tvrdi | Stvarni kod | Status |
|---|---|---|---|
| 1 | CPT `momcad` (Momčadi) | Plugin registrira `kurs_ekipa` (Ekipe). `momcad` se **nigdje** ne registrira. | ❌ NETOČNO |
| 2 | CPT `utakmica` (Utakmice) | Utakmice **NISU CPT** — žive u tablici `wp_kurs_matches`. Postoji samo `page-utakmica.php` template. | ❌ NETOČNO |
| 3 | CPT `vijest` (Vijesti) | TOČNO — `vijest` se registrira u `themes/kurskc/inc/post-types.php`. | ✅ OK |
| 4 | Taksonomije `liga` i `sezona` (hierarchical/flat) | **Ne postoje.** Lige su slobodan tekst (`wp_kurs_seasons.league`), sezone su DB redovi (`wp_kurs_seasons`), divizije `wp_kurs_divisions`. | ❌ NETOČNO |
| 5 | (ne spominje) | Postoji CPT `kurs_igrac` (Igrači) — CLAUDE.md ga uopće ne navodi. | ⚠️ NEDOKUMENTIRANO |
| 6 | (ne spominje) | Postoji taksonomija `vijest_kategorija` (hierarchical, slug `vijesti/kategorija`) s default termima `vijesti`/`kc-liga`/`pan-ljetna`. | ⚠️ NEDOKUMENTIRANO |
| 7 | `inc/post-types.php` registrira `momcad/utakmica/vijest` + `liga/sezona` | Registrira SAMO `vijest` + `vijest_kategorija`. Komentar u datoteci kaže: "Ekipe (kurs_ekipa) i Igrači (kurs_igrac) registrira kurs-liga plugin." Nema mrtvog koda starih CPT-ova. | ❌ NETOČNO |
| 8 | CSS varijable `--color-primary: #1a3a6b`, `--color-accent: #e8b84b` | **Ne postoje** u `main.css`. Stvarne su `--c-*` (vidi §4): `--c-kc #DA0F1E`, `--c-pan #F47B16`, `--c-gold #FDE206`. `#1a3a6b` preživljava SAMO kao fallback default boja ekipe u `helpers.php:10` (`kurs_team_color`). `#e8b84b` se ne pojavljuje nigdje. | ❌ NETOČNO |
| 9 | Struktura teme: `index.php / page.php / single.php / 404.php` | Puno više templatea (front-page, search, page-liga, page-utakmica, page-o-nama, single-kurs_ekipa, single-kurs_igrac). Struktura je nepotpuna. | ⚠️ NEPOTPUNO |
| 10 | (ne spominje) | Cijela domenska logika (lige/sezone/utakmice/playoff/admin/DB sheme) živi u pluginu `kurs-liga` koji CLAUDE.md UOPĆE ne spominje. | 🔴 KRITIČNO NEDOKUMENTIRANO |

---

## 8. Gotchas (zamke pri radu)

- **CLAUDE.md je RANIJE bio zastario** (✅ ispravljen 2026-06-22 — vidi §7). Tvrdio je CPT-ove `momcad`/`utakmica`/`vijest` i taksonomije `liga`/`sezona`; stvarno postoje samo `vijest` + `vijest_kategorija` (tema) i `kurs_ekipa`/`kurs_igrac` (plugin). `momcad`, `liga`, `sezona` se **nigdje** ne registriraju (nema ni mrtvog koda).
- **Utakmice nisu CPT** — žive u `wp_kurs_matches`; postoji samo `page-utakmica.php` template.
- **CSS boje iz CLAUDE.md ne postoje.** `--color-primary #1a3a6b` / `--color-accent #e8b84b` nisu u kodu. Stvarne su `--c-*` (`#DA0F1E` KC, `#F47B16` Pan). `#e8b84b` nigdje; `#1a3a6b` samo kao default team color u `helpers.php:10`.
- ✅ **ISPRAVLJENO (ISPRAVCI #1):** `class-database.php` više ne koristi `ALTER TABLE … ADD COLUMN IF NOT EXISTS` (nevalidan MySQL, samo MariaDB) — stupce dodaje `dbDelta`. Više nema te SQL greške u `debug.log`.
- ✅ **ISPRAVLJENO (ISPRAVCI #2–4):** `match-record-pdf.php` — `$disp_home`/`$disp_away`/`$is_finished` računaju se prije PDF includea, PDF se renderira na `admin_init` (nema više "headers already sent"), footer datum koristi `date_i18n`.
- **CLAUDE.md ne spominje plugin `kurs-liga`** — cijela domenska arhitektura (DB sheme, playoff, admin) je u njemu nedokumentirana.
- **Tema automatski kreira stranice** (`kc-liga`, `pan-ljetna`, `utakmica`, `za-medije`, `o-kurs-u`) na svakom `init` ako nedostaju — sigurnosna mreža koja može iznenaditi pri čišćenju stranica.
- **Tema ovisi o pluginu:** `functions.php` učitava plugin `helpers.php` za badge/boje/renderere. Ako je plugin deaktiviran, te funkcije nedostaju i front-end može pasti.
- **Struktura natjecanja je u kodu (2026-10-16):** `Kurs_Season::COMPETITIONS` — ne tražiti admin sučelje za dodavanje divizija/faza/grupa (uklonjeno). Nova sezona = natjecanje + godina.
- **Local: svaki drugi zahtjev visi?** Zaglavio je jedan od dva php-cgi workera (10008/10009) → restart sitea u Localu (§1.3).
- **CLI PHP bez `PHPRC`** ne vidi bazu (port 10011 dolazi iz Localovog `php.ini`) — §1.3/§1.4.
- **`tools/demo-podaci.php` je destruktivan** za najnoviju KC sezonu (prazni obje lige) — samo lokalno (§1.4).

---

## 9. Brzi podsjetnik (TL;DR)

- Local by Flywheel; WP korijen `app/public/`; lokalni URL `http://kurshr.local` (domena `kurshr`, ne `kurskc`); produkcija `kurskc.hr`.
- DB: `DB_NAME=local`, `DB_USER=root`, `DB_PASSWORD=root`, `DB_HOST=localhost` (MySQL stvarno na `127.0.0.1:10011`), prefix `wp_`, charset `utf8`.
- `WP_DEBUG=true`, `WP_DEBUG_LOG=true` (log u `app/public/wp-content/debug.log`), `WP_DEBUG_DISPLAY=false`, `WP_ENVIRONMENT_TYPE=local`.
- Local: dva php-cgi workera (10008/10009, round-robin) — ako svaki drugi zahtjev visi, restartaj site.
- Plugin `kurs-liga`: prefiks `kurs_`, klase `Kurs_*`, CPT `kurs_ekipa` + `kurs_igrac`, meta `_kurs_`, DB tablice `wp_kurs_*` (DB_VERSION 1.13), `KURS_VERSION 1.20.1`, ovlast `KURS_CAP`; struktura natjecanja u `Kurs_Season::COMPETITIONS`; admin po natjecanjima (`kurs-kc` / `kurs-pan`, linkovi preko `Kurs_Admin::hub_url()`).
- Tema `kurskc`: `KURSKC_VERSION 1.0.42`, prefiks `kurskc_`; registrira `vijest` + `vijest_kategorija` (+ `sponzor`).
- Demo podaci lokalno: `tools/demo-podaci.php [obrisi]` (s `PHPRC`, §1.4).
- CSS varijable su `--c-*` (NE `--color-*`); KC crvena `#DA0F1E`, Pan narančasta `#F47B16`, gold `#FDE206`.
- **Prije rada: pročitaj §7 (neusklađenosti) — CLAUDE.md nije pouzdan za CPT/taksonomije/boje.**
