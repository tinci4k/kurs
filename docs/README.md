# KURS — kurskc.hr · Baza znanja (ULAZNA TOČKA za AI)

> Ovo je ulazna točka u dokumentaciju. Pročitaj ovaj dokument PRVI, pa onda po potrebi otvori specijalizirane datoteke navedene u kazalu niže.

---

## TL;DR za AI

**kurskc.hr** je WordPress stranica za **KURS (Koprivnička udruga za rekreativni sport)** koja vodi malonogometne lige (**KC liga** — zima/dvorana, lige *KC Liga* + *Liga bez briga*; **Pan ljetna liga** — ljeto/trava, skupine + playoff). Sustav se sastoji od **dva odvojena dijela**: (1) vlastiti plugin **`kurs-liga`** koji nosi cijelu natjecateljsku domenu — sezone, divizije, faze, grupe, utakmice, događaje i playoff — preko **9 custom DB tablica `wp_kurs_*`** plus dva CPT-a `kurs_ekipa` i `kurs_igrac`; i (2) vlastita tema **`kurskc`** koja prikazuje te podatke na front-endu i registrira CPT `vijest`. Domenske klase plugina (`Kurs_Season`, `Kurs_Match`, `Kurs_Playoff`, `Kurs_Rounds`) su static-only i rade izravno preko `$wpdb`; admin sloj (`Kurs_Admin`) obrađuje sve forme i AJAX. **Admin je organiziran po natjecanjima** (od `KURS_VERSION` 1.16.0): izbornik KURS Liga → Pregled · **KC Liga** (`kurs-kc`) · **Pan Ljetna liga** (`kurs-pan`) · Ekipe · Igrači · Sve sezone · Postavke; stranica natjecanja ima kartice Pregled / Ekipe / Utakmice / Postavke za odabranu sezonu. **Od 2026-10-16 struktura natjecanja je hardkodirana** (`Kurs_Season::COMPETITIONS`) — sustav služi isključivo KURS-u. **Lokalni razvoj** je preko Local by Flywheel (`http://kurshr.local`, DB `local`, root/root, WP korijen `app/public/`).

**Napomena:** Korijenski `CLAUDE.md` **ažuriran je 2026-06-22 i dopunjen 2026-10-16** (fiksna struktura, `Kurs_Rounds`, „Po kolima", „Za medije", demo podaci, Local gotcha, admin po natjecanjima + `Kurs_Admin::hub_url()`). Točan model podataka sažet je u sekciji **Ključne činjenice** na dnu.

> ⚠️ Dokumentacija je dorađena za izmjene od 2026-10-16. Značajke dodane između 2026-06-22 i 2026-10-16 (ticker `Kurs_Ticker`, uloge `Kurs_Roles` / `KURS_CAP`, zaključavanje sezone, prekršaji/kapetan/suci u zapisniku, sustav vijesti `inc/news.php`, sponzori, DB stupci do `DB_VERSION` 1.9) opisane su samo djelomično — kod je mjerodavan.

---

## Mapa repozitorija (ključne putanje)

Repozitorij korijen: `C:\Users\Tinci\Local Sites\kurshr\`. WordPress korijen: `app/public/`.

### Plugin `kurs-liga` — domenska logika (NAJVAŽNIJE)
`app/public/wp-content/plugins/kurs-liga/`

| Putanja | Opis |
|---|---|
| `kurs-liga.php` | Bootstrap: konstante (`KURS_VERSION='1.34.0'`, `KURS_PREFIX='kurs_'`, `KURS_CAP='manage_kurs_liga'`), require-ovi, hookovi, instanca `Kurs_CPT`, `Kurs_Ticker::init()`, `Kurs_Roles::init()` |
| `includes/class-database.php` | Sve 12 custom tablica (`install()` + `dbDelta`), `DB_VERSION='1.16'`, migracije (`maybe_upgrade()`) |
| `includes/class-cpt.php` | CPT-ovi `kurs_ekipa` (slug `ekipe`) i `kurs_igrac` (slug `igraci`) |
| `includes/class-players.php` | `Kurs_Players` (1.19.0) — registar igrača: duplikati istog imena, zbroj nastupa/golova, ekipe u aktivnim sezonama, **spajanje** i brisanje igrača |
| `includes/class-season.php` | `Kurs_Season` — **`COMPETITIONS`** (fiksna natjecanja) + `scaffold()` / `set_group_count()`; CRUD nad sezone/divizije/faze/grupe/group_teams/squads (static) |
| `includes/class-match.php` | `Kurs_Match` — utakmice, događaji, **`generate_round_robin()`**, **`get_standings($gid, ?$max_round)`** (ljestvica), **`get_top_scorers()`** |
| `includes/class-playoff.php` | `Kurs_Playoff` — playoff bracket (generate/seeding/advance/reset) |
| `includes/class-rounds.php` | **NOVO 2026-10-16** — `Kurs_Rounds`: pregled po kolima (rezultati sa strijelcima, ljestvica i strijelci nakon N. kola) |
| `includes/class-ticker.php` | `Kurs_Ticker` — live ticker za header (transient, `kurs_data_changed`) |
| `includes/class-roles.php` | `Kurs_Roles` — ovlast `KURS_CAP` za urednike (Author) + uloga **Zapisničar** (`KURS_RECORD_CAP`) |
| `includes/class-zapisnik.php` + `front/zapisnik.{php,css}` | **1.24.0** — `Kurs_Zapisnik`: stranica **`/zapisnik/`** za zapisničare (prijava, popis utakmica, isti zapisnik kao u adminu, bez wp-admina) |
| `includes/class-presjek.php` + `front/presjek.{php,css,js,-data.json}` | **1.27.0** — `Kurs_Presjek`: **`/presjek/?k=…`** presjek ekipa i rostera za KURS (snimka u JSON-u) + odgovori na otvorena pitanja (opcija `kurs_presjek_odgovori`, admin → Presjek ekipa) |
| `includes/helpers.php` | Prezentacijski helperi (`kurs_team_color/_text_color/_badge_html`, `kurs_league_icon`, **`kurs_league_url`**…) + dijeljeni renderi (`kurs_render_standings`, `kurs_render_playoff_bracket`, **`kurs_render_round_results/_scorers`**) |
| `admin/class-admin.php` | `Kurs_Admin` — sav admin: meni (`HUBS`), **`hub_url()`** (linkovi na stranicu natjecanja), preusmjeravanje starih slugova (`redirect_legacy_pages`), asseti, POST forme (`handle_forms`), AJAX (s guardovima za ekipe), `compute_score()` |
| `admin/assets/admin.css` / `admin.js` | Admin stilovi (~2750 lin., sekcija „STRANICA NATJECANJA") i JS (flatpickr, AJAX interakcije, `assignTeam`/`markPool`) |
| `admin/views/*.php` | Admin viewovi: `dashboard` (Pregled), **`season-hub`** (stranica natjecanja) + kartice `hub-overview` / `hub-teams` / `hub-matches` / `hub-settings`, `seasons` (Sve sezone), `teams` (Ekipe), `players` (Igrači), `roster-panel` (roster ekipe u kartici Ekipe), `settings` (Postavke), `match-record`, `match-record-pdf`. *(Obrisani 2026-10-16: `season-setup`, `roster`, `playoff`; `matches` → `hub-matches`.)* |

### Tema `kurskc` — front-end
`app/public/wp-content/themes/kurskc/`

| Putanja | Opis |
|---|---|
| `functions.php` | `KURSKC_VERSION='1.0.63'`, theme supports, auto-kreiranje stranica (uklj. `za-medije`), uvjetni require plugin `helpers.php`, `kurs_time_ago()` |
| `style.css` | Samo theme header (Version 1.0.0 — nedosljedno s functions.php) |
| `inc/post-types.php` | Registrira SAMO CPT `vijest` + taksonomiju `vijest_kategorija` |
| `inc/enqueue.php` | Google Fonts + `kurskc-main` CSS/JS |
| `inc/nav-menus.php` | Registrira lokacije `primary`/`footer` (ali se `wp_nav_menu()` NIGDJE ne poziva — mrtvo) |
| `inc/news.php` | Sustav vijesti (meta, pregledi, widgeti, shortcodeovi) + **`kurskc_active_league()`** (liga s najbližom utakmicom) |
| `inc/sponsors.php` | Sponzori (traka iznad footera) |
| `inc/media-kit.php` | **NOVO 2026-10-16** — `Kurskc_Media_Kit`: paket „Za medije" (HTML koji preživljava kses, običan tekst) |
| `header.php` / `footer.php` | Hardkodirana navigacija (dropdowni liga: Tablica / **Po kolima** / Raspored / Statistika); header ima live ticker (`Kurs_Ticker`) |
| `front-page.php` | Naslovnica (vijesti + ulaz u lige + **„Iz lige"**: zadnje kolo po ligi aktualnog natjecanja) |
| `page-liga.php` | **Liga Hub** (Template Name `Liga Hub`) — tablica/**po kolima**/raspored/statistika; dijeli `/kc-liga/` i `/pan-ljetna/` |
| `page-utakmica.php` | **Centar utakmice** (`/utakmica/?match_id=X`) |
| `page-za-medije.php` | **NOVO 2026-10-16** — **Za medije** (`/za-medije/?liga=kc\|pan&season_id=&kolo=`): paket rezultata kola za druge portale (HTML / tekst / PNG) |
| `page-o-nama.php` | Stranica "O nama" |
| `single-kurs_ekipa.php` / `single-kurs_igrac.php` | Profili ekipe / igrača |
| `index.php` / `single.php` / `page.php` / `search.php` / `404.php` | Generički templati (index pokriva sve arhive) |
| `assets/css/main.css` | Svi front-end stilovi; CSS varijable `--c-*` u `:root` |
| `assets/js/main.js` | Mobilni meni, tražilica, `.hub-dd` dropdowni, kopiranje linka, „Za medije" (kopiranje, PNG preko html-to-image) |
| `assets/img/` | Logotipi (`KURS-clean-ink.png`, `KURS-clean.png`, `kurs-media-logo*.png`), favicon (grb KC lige: `favicon.ico`, `favicon-32.png`, `favicon-192.png`, `apple-touch-icon.png`) |

### Ostalo
| Putanja | Opis |
|---|---|
| `app/public/wp-config.php` | DB pristup, WP_DEBUG, `WP_ENVIRONMENT_TYPE='local'` |
| `app/public/wp-content/debug.log` | Debug log (sadrži poznate greške — vidi POSTAVKE-I-KONVENCIJE.md) |
| `app/public/kurs_seed.sql` | Krhki seed (igrači + rezultati); ovisi o tvrdo kodiranim ID-evima |
| `tools/demo-podaci.php` | **NOVO 2026-10-16** — CLI skripta za **lokalne** demo podatke (KC sezona s 9 kola, strijelci u Pan utakmicama); `obrisi` ih uklanja. Nije dio teme/plugina pa ga deployment akcije ne kopiraju u WordPress (detalji i deploy napomena u POSTAVKE-I-KONVENCIJE.md §1.4) |

---

## Kazalo dokumentacije — kad koju datoteku čitati

| Datoteka | Čitaj kada… |
|---|---|
| **`ARHITEKTURA.md`** | Trebaš pregled cijelog sustava: kako se plugin i tema povezuju, podjela slojeva (domena/admin/front-end), tok podataka (CPT + custom tablice), ER hijerarhija na visokoj razini. Počni ovdje za "veliku sliku". |
| **`BAZA-PODATAKA.md`** | Radiš s podacima: shema svih 10 tablica `wp_kurs_*` (stupci, tipovi, indeksi), veze prema `wp_posts` (`kurs_ekipa`/`kurs_igrac`), sustav verzioniranja sheme (`DB_VERSION`, migracije), seed. Bitno za svaku izmjenu DB upita ili sheme. |
| **`PLUGIN-KURS-LIGA.md`** | Mijenjaš natjecateljsku logiku: domenske klase (`Kurs_Season` s `COMPETITIONS`/`scaffold`, `Kurs_Match`, `Kurs_Playoff`, `Kurs_Rounds`), izračun ljestvice (UEFA/HNS sortiranje), bodovanje, playoff seeding/advance, round-robin generator, helperi/renderi, te cijeli admin sloj (`Kurs_Admin`, izbornik i stranice natjecanja, `hub_url()`, legacy redirecti, forme, AJAX, viewovi, zapisnik, PDF, workflow operatera). |
| **`TEMA-KURSKC.md`** | Radiš na front-endu/izgledu: template hijerarhija, Liga Hub (uklj. „Po kolima") i Centar utakmice, „Za medije" (`page-za-medije.php` + `inc/media-kit.php`), naslovnica („Iz lige"), profili ekipe/igrača, header/footer, navigacija, CPT `vijest`, CSS varijable i komponente, JS. |
| **`POSTAVKE-I-KONVENCIJE.md`** | Trebaš okruženje i konvencije: Local setup (portovi, **php-cgi gotcha**), DB pristup, debug, **demo podaci** (`tools/demo-podaci.php`), naming (prefiksi `kurs_`/`kurskc_`/`_kurs_`), CSS boje, hookovi, te audit neusklađenosti CLAUDE.md vs kod i poznate greške iz debug.log. |
| **`ISPRAVCI.md`** | Changelog ispravaka. Ako neki dio dokumentacije opisuje grešku kao aktualnu, provjeri ovdje je li u međuvremenu popravljena (ispravci #1–#8 od 2026-06-22, #9–#22 od 2026-10-16). |

---

## Brzi start za zadatke (gdje gledati)

| Zadatak | Gdje gledati |
|---|---|
| Dodati/izmijeniti stupac u DB | `includes/class-database.php` (`install()` + nova migracija u `maybe_upgrade()`, podigni `DB_VERSION`) |
| Promijeniti bodovanje / sortiranje ljestvice | `includes/class-match.php` → `get_standings()` |
| Izračun rezultata iz golova | `admin/class-admin.php` → `compute_score()` (NIJE u domenskoj klasi!); ista pravila i u `Kurs_Rounds::goals_by_match()` |
| Generiranje rasporeda "svaka sa svakom" | `includes/class-match.php` → `Kurs_Match::generate_round_robin()` (od 2026-10-16; admin `ajax_generate_fixtures` / `do_generate_season_fixtures` ga samo pozivaju) |
| Struktura natjecanja (lige/skupine nove sezone, KC vs Pan format) | `includes/class-season.php` → `Kurs_Season::COMPETITIONS`, `scaffold()`, `set_group_count()` |
| Kreiranje sezone / dodjela ekipa (admin) | `admin/views/seasons.php` („Sve sezone": natjecanje + godina) ili inline „Kreiraj prvu sezonu" u `season-hub.php` → `do_create_season`; `admin/views/hub-teams.php` (kartica Ekipe: ekipe u ligama + „Generiraj raspored"); guardovi u `ajax_assign_team` / `ajax_remove_team` |
| Admin stranica natjecanja (kartice Pregled / Ekipe / Utakmice / Postavke) | `admin/views/season-hub.php` + `hub-overview.php` / `hub-teams.php` / `hub-matches.php` / `hub-settings.php`; izbornik u `Kurs_Admin::menus()` (`HUBS`) |
| Link / redirect na admin stranicu natjecanja | `Kurs_Admin::hub_url($season_id, $tab, $args)` (ne hardkodiraj `page=kurs-kc`); stari slugovi → `redirect_legacy_pages()` |
| Pregled po kolima (rezultati, ljestvica i strijelci nakon kola) | `includes/class-rounds.php` (`Kurs_Rounds`) + `helpers.php` (`kurs_render_round_results/_scorers`) + `page-liga.php` (`?tab=kola&kolo=`) |
| Paket „Za medije" (HTML/tekst/PNG) | `themes/kurskc/inc/media-kit.php` (`Kurskc_Media_Kit`) + `page-za-medije.php` + `assets/js/main.js` |
| Link na hub prave lige iz `division_id` | `helpers.php` → `kurs_league_url($division_id, $args)` (ne hardkodiraj `/kc-liga/`) |
| Playoff seeding / bracket / napredovanje | `includes/class-playoff.php` (`generate`, `build_seeding_pairs`, `advance_winner`, `next_slot`, `reset`) |
| Novi admin AJAX / forma | `admin/class-admin.php` (registracija u konstruktoru + `do_*`/`ajax_*`; nonce `kurs_ajax` za AJAX) |
| Izmjena admin sučelja (prikaz) | `admin/views/*.php` (logika je u `Kurs_Admin`, viewovi su samo prezentacija) |
| Zapisnik utakmice / PDF | `admin/views/match-record.php` + `match-record-pdf.php` (PDF je browser print-to-PDF) |
| Boja / badge / logo ekipe | `includes/helpers.php` (meta `_kurs_team_color`, naziv=post_title, logo=featured image) |
| Front-end tablica/po kolima/raspored/statistika lige | `themes/kurskc/page-liga.php` (GET param `?season_id`/`?division_id`/`?tab`/`?kolo`) |
| Front-end stranica utakmice | `themes/kurskc/page-utakmica.php` (`?match_id=X`) |
| Demo podaci za lokalni razvoj | `tools/demo-podaci.php` (`php tools/demo-podaci.php [obrisi]`, vidi POSTAVKE §1.4) |
| Profil ekipe / igrača | `themes/kurskc/single-kurs_ekipa.php` / `single-kurs_igrac.php` (puno direktnih `$wpdb` upita) |
| Navigacija / header / footer | `themes/kurskc/header.php` / `footer.php` (hardkodirano, NE `wp_nav_menu`) |
| CSS boje / komponente | `themes/kurskc/assets/css/main.css` (`:root` varijable `--c-*`) |
| Registracija CPT `vijest` | `themes/kurskc/inc/post-types.php` |
| Registracija CPT-ova ekipa/igrač | `plugins/kurs-liga/includes/class-cpt.php` |
| Auto-kreiranje WP stranica | `themes/kurskc/functions.php` (`kurskc_create_pages`) |

---

## 📌 Ključne činjenice o modelu podataka (OBAVEZNO PROČITAJ)

> Ovo je bio glavni izvor zabune: do 2026-06-22 `CLAUDE.md` je opisivao nepostojeće CPT-ove/taksonomije/boje. `CLAUDE.md` je sada ispravljen, ali svejedno zapamti točan model:

1. **CPT-ovi NISU `momcad`/`utakmica`/`vijest`.** Stvarno postoje:
   - `kurs_ekipa` (Ekipe, slug `ekipe`) — registrira **plugin** (`includes/class-cpt.php`)
   - `kurs_igrac` (Igrači, slug `igraci`) — registrira **plugin** (CLAUDE.md ga uopće ne spominje)
   - `vijest` (slug `vijesti`) — registrira **tema** (`inc/post-types.php`)
   - `momcad` se NIGDJE ne registrira (nema ni mrtvog koda).

2. **Utakmice NISU CPT.** Žive u tablici **`wp_kurs_matches`**. Postoji samo `page-utakmica.php` template/stranica.

3. **Taksonomije `liga`/`sezona` NE POSTOJE.** Natjecanje je vrijednost u `wp_kurs_seasons.league` (VARCHAR; nove sezone uvijek `kc_liga` ili `pan_ljetna` — ključevi `Kurs_Season::COMPETITIONS`); sezone/divizije su DB redovi (`wp_kurs_seasons` / `wp_kurs_divisions`). Jedina stvarna taksonomija je **`vijest_kategorija`** (na CPT `vijest`).

3a. **Struktura je hardkodirana (od 2026-10-16).** KC Liga = divizije *KC Liga* + *Liga bez briga*, svaka jedna skupina, jednokružno, bez playoffa. Pan Ljetna liga = divizija *Pan Ljetna liga*, 2 ili 4 skupine, po 2 najbolje u playoff (ČF/PF). Lige/fazu/skupine kreira `Kurs_Season::scaffold()` pri kreiranju sezone; admin više nema ručno dodavanje divizija/faza/grupa ni `playoff_spots`.

4. **CSS boje iz CLAUDE.md ne postoje.** `--color-primary:#1a3a6b` i `--color-accent:#e8b84b` NISU u `main.css`. Stvarne su `--c-*` (npr. `--c-kc:#DA0F1E` crvena za KC, `--c-pan:#F47B16` narančasta za Pan, `--c-gold:#FDE206`). `#1a3a6b` preživljava SAMO kao fallback default boja ekipe u `helpers.php` (`kurs_team_color`); `#e8b84b` nigdje.

5. **Cijeli plugin `kurs-liga` je nedokumentiran u CLAUDE.md.** Sva domenska arhitektura (9 DB tablica, playoff, admin sučelje) je izostavljena.

**Točan mentalni model podataka:**
- **WP postovi** (`kurs_ekipa`, `kurs_igrac`) nose: naziv (`post_title`), logo (featured image), boju (postmeta `_kurs_team_color`).
- **Custom tablice `wp_kurs_*`** nose natjecateljsku strukturu i rezultate. Stupci `team_id`/`player_id`/`home_team_id`/`away_team_id`/`scored_for` su `BIGINT UNSIGNED` = `wp_posts.ID` (NEMA DB foreign-key constraintova — integritet je samo u aplikacijskom kodu).

**Dodatni gotchas** — ✅ = ispravljeno 2026-06-22 (vidi [`ISPRAVCI.md`](ISPRAVCI.md)), ⚠️ = i dalje aktualno:
- ✅ `class-database.php` više **ne** koristi `ALTER TABLE ... ADD COLUMN IF NOT EXISTS` (nije validan MySQL) — nove stupce dodaje `dbDelta` preko `install()`.
- ✅ `admin/views/match-record-pdf.php` — riješene nedefinirane varijable (`$disp_home`/`$disp_away`/`$is_finished`) i "headers already sent" (PDF se renderira na `admin_init`, prije admin headera).
- ✅ Footer rute `/liga/` (stranica se ne kreira → 404) repointane na `/kc-liga/` i `/pan-ljetna/`.
- ✅ Gumb "Upravljaj playoff bracketom" (`season-setup.php`) repointan s neregistrirane stranice `kurs-playoff` na funkcionalnu rutu `kurs-matches?playoff_div=` (2026-06-22; obje stranice su u međuvremenu zamijenjene stranicom natjecanja — vidi niže).
- ⚠️ Tema **ovisi o pluginu**: `functions.php` uvjetno require-a plugin `helpers.php`; predlošci izvode direktne `$wpdb` upite po plugin tablicama, a header / hub / „Za medije" koriste `Kurs_Ticker` / `Kurs_Rounds`. Ako je plugin deaktiviran, front-end degradira. (dizajn)
- ✅ (2026-10-16, 1.16.0) Admin preslagan po natjecanjima: napušteni `pg_playoff()` + `views/playoff.php` su **obrisani** (kao i `season-setup.php`, `roster.php`); playoff je na stranici natjecanja → kartica Utakmice → „🏆 Playoff". Stari slugovi `kurs-season-setup` / `kurs-matches` / `kurs-roster` samo preusmjeravaju.
- ⚠️ Integritet ekipa (jedna skupina po ligi, nema micanja/brisanja ekipe s utakmicama) čuvaju **admin handleri**, ne domenske metode ni baza (ISPRAVCI #20–#22).
- ✅ (2026-10-16) Round-robin premješten u `Kurs_Match::generate_round_robin()`; breadcrumb utakmice više nije hardkodiran na `/kc-liga/`; playoff reset više ne ostavlja siročad u `matches`; ostali ispravci od 2026-10-16 su ISPRAVCI #9–#19.
- ⚠️ „Po kolima" i „Za medije" vjeruju stupcu `matches.round` — krivo kolo kvari ljestvicu nakon kola (admin upozorava).
- ⚠️ Lokalno: Local vrti **dva php-cgi workera** — ako jedan zapne, svaki drugi zahtjev istekne → restartaj site u Localu (POSTAVKE §1.3).

---

*Verzije u kodu su nedosljedne (namjerno): plugin header `Version: 1.2.0` vs `KURS_VERSION=1.34.0` vs DB `DB_VERSION=1.16`; tema `KURSKC_VERSION=1.0.63` vs `style.css` Version `1.0.0`. To su poznate nedosljednosti, ne bugovi.*
