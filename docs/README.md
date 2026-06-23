# KURS — kurskc.hr · Baza znanja (ULAZNA TOČKA za AI)

> Ovo je ulazna točka u dokumentaciju. Pročitaj ovaj dokument PRVI, pa onda po potrebi otvori specijalizirane datoteke navedene u kazalu niže.

---

## TL;DR za AI

**kurskc.hr** je WordPress stranica za **KURS (Koprivnička udruga za rekreativni sport)** koja vodi malonogometne lige (**KC liga** — zima/dvorana, **Pan ljetna liga** — ljeto/trava). Sustav se sastoji od **dva odvojena dijela**: (1) vlastiti plugin **`kurs-liga`** koji nosi cijelu natjecateljsku domenu — sezone, divizije, faze, grupe, utakmice, događaje i playoff — preko **9 custom DB tablica `wp_kurs_*`** plus dva CPT-a `kurs_ekipa` i `kurs_igrac`; i (2) vlastita tema **`kurskc`** koja prikazuje te podatke na front-endu i registrira CPT `vijest`. Domenske klase plugina (`Kurs_Season`, `Kurs_Match`, `Kurs_Playoff`) su static-only i rade izravno preko `$wpdb`; admin sloj (`Kurs_Admin`) obrađuje sve forme i AJAX. **Lokalni razvoj** je preko Local by Flywheel (`http://kurshr.local`, DB `local`, root/root, WP korijen `app/public/`).

**Napomena:** Korijenski `CLAUDE.md` **ažuriran je 2026-06-22** i sada je usklađen s kodom (ranije je bio netočan o CPT-ovima, taksonomijama i bojama). Točan model podataka sažet je u sekciji **Ključne činjenice** na dnu.

---

## Mapa repozitorija (ključne putanje)

Repozitorij korijen: `C:\Users\Tinci\Local Sites\kurshr\`. WordPress korijen: `app/public/`.

### Plugin `kurs-liga` — domenska logika (NAJVAŽNIJE)
`app/public/wp-content/plugins/kurs-liga/`

| Putanja | Opis |
|---|---|
| `kurs-liga.php` | Bootstrap: konstante (`KURS_VERSION='1.0.0'`, `KURS_PREFIX='kurs_'`), require-ovi, hookovi, instanca `Kurs_CPT` |
| `includes/class-database.php` | Sve 9 custom tablica (`install()` + `dbDelta`), `DB_VERSION='1.5'`, migracije (`maybe_upgrade()`) |
| `includes/class-cpt.php` | CPT-ovi `kurs_ekipa` (slug `ekipe`) i `kurs_igrac` (slug `igraci`) |
| `includes/class-season.php` | `Kurs_Season` — CRUD nad sezone/divizije/faze/grupe/group_teams/squads (static) |
| `includes/class-match.php` | `Kurs_Match` — utakmice, događaji, **`get_standings()`** (ljestvica), **`get_top_scorers()`** |
| `includes/class-playoff.php` | `Kurs_Playoff` — playoff bracket (generate/seeding/advance) |
| `includes/helpers.php` | Prezentacijski helperi: `kurs_team_color()`, `kurs_team_text_color()`, `kurs_team_badge_html()` |
| `admin/class-admin.php` | `Kurs_Admin` — sav admin: meni, asseti, POST forme (`handle_forms`), AJAX, `compute_score()`, round-robin generator |
| `admin/assets/admin.css` / `admin.js` | Admin stilovi (~2154 lin.) i JS (flatpickr, AJAX interakcije) |
| `admin/views/*.php` | Admin viewovi: `dashboard`, `seasons`, `season-setup`, `matches`, `teams`, `roster`, `match-record`, `match-record-pdf`, `playoff` |

### Tema `kurskc` — front-end
`app/public/wp-content/themes/kurskc/`

| Putanja | Opis |
|---|---|
| `functions.php` | `KURSKC_VERSION='1.0.4'`, theme supports, auto-kreiranje stranica, uvjetni require plugin `helpers.php`, `kurs_time_ago()` |
| `style.css` | Samo theme header (Version 1.0.0 — nedosljedno s functions.php) |
| `inc/post-types.php` | Registrira SAMO CPT `vijest` + taksonomiju `vijest_kategorija` |
| `inc/enqueue.php` | Google Fonts + `kurskc-main` CSS/JS |
| `inc/nav-menus.php` | Registrira lokacije `primary`/`footer` (ali se `wp_nav_menu()` NIGDJE ne poziva — mrtvo) |
| `header.php` / `footer.php` | Hardkodirana navigacija; header ima ticker rezultata (direktan `$wpdb` na `kurs_*` tablice) |
| `front-page.php` | Naslovnica (vijesti + ulaz u lige) |
| `page-liga.php` | **Liga Hub** (Template Name `Liga Hub`) — tablica/raspored/statistika; dijeli `/kc-liga/` i `/pan-ljetna/` |
| `page-utakmica.php` | **Centar utakmice** (`/utakmica/?match_id=X`) |
| `page-o-nama.php` | Stranica "O nama" |
| `single-kurs_ekipa.php` / `single-kurs_igrac.php` | Profili ekipe / igrača |
| `index.php` / `single.php` / `page.php` / `search.php` / `404.php` | Generički templati (index pokriva sve arhive) |
| `assets/css/main.css` | Svi front-end stilovi; CSS varijable `--c-*` u `:root` |
| `assets/js/main.js` | Samo mobilni meni + search overlay |
| `assets/img/` | Logotipi (`KURS-clean-ink.png`, `KURS-clean.png`), `favicon.svg` |

### Ostalo
| Putanja | Opis |
|---|---|
| `app/public/wp-config.php` | DB pristup, WP_DEBUG, `WP_ENVIRONMENT_TYPE='local'` |
| `app/public/wp-content/debug.log` | Debug log (sadrži poznate greške — vidi POSTAVKE-I-KONVENCIJE.md) |
| `app/public/kurs_seed.sql` | Krhki seed (igrači + rezultati); ovisi o tvrdo kodiranim ID-evima |

---

## Kazalo dokumentacije — kad koju datoteku čitati

| Datoteka | Čitaj kada… |
|---|---|
| **`ARHITEKTURA.md`** | Trebaš pregled cijelog sustava: kako se plugin i tema povezuju, podjela slojeva (domena/admin/front-end), tok podataka (CPT + custom tablice), ER hijerarhija na visokoj razini. Počni ovdje za "veliku sliku". |
| **`BAZA-PODATAKA.md`** | Radiš s podacima: shema svih 9 tablica `wp_kurs_*` (stupci, tipovi, indeksi), veze prema `wp_posts` (`kurs_ekipa`/`kurs_igrac`), sustav verzioniranja sheme (`DB_VERSION`, migracije), seed. Bitno za svaku izmjenu DB upita ili sheme. |
| **`PLUGIN-KURS-LIGA.md`** | Mijenjaš natjecateljsku logiku: domenske klase (`Kurs_Season`/`Kurs_Match`/`Kurs_Playoff`), izračun ljestvice (UEFA/HNS sortiranje), bodovanje, playoff seeding/advance, round-robin generator, te cijeli admin sloj (`Kurs_Admin`, meni, forme, AJAX, viewovi, zapisnik, PDF). |
| **`TEMA-KURSKC.md`** | Radiš na front-endu/izgledu: template hijerarhija, Liga Hub i Centar utakmice, profili ekipe/igrača, header/footer, navigacija, CPT `vijest`, CSS varijable i komponente, JS. |
| **`POSTAVKE-I-KONVENCIJE.md`** | Trebaš okruženje i konvencije: Local setup, DB pristup, debug, naming (prefiksi `kurs_`/`kurskc_`/`_kurs_`), CSS boje, hookovi, te **detaljan audit neusklađenosti CLAUDE.md vs stvarni kod** i poznate greške iz debug.log. |
| **`ISPRAVCI.md`** | Changelog ispravaka. Ako neki dio dokumentacije opisuje grešku kao aktualnu, provjeri ovdje je li u međuvremenu popravljena (npr. ispravci od 2026-06-22). |

---

## Brzi start za zadatke (gdje gledati)

| Zadatak | Gdje gledati |
|---|---|
| Dodati/izmijeniti stupac u DB | `includes/class-database.php` (`install()` + nova migracija u `maybe_upgrade()`, podigni `DB_VERSION`) |
| Promijeniti bodovanje / sortiranje ljestvice | `includes/class-match.php` → `get_standings()` |
| Izračun rezultata iz golova | `admin/class-admin.php` → `compute_score()` (NIJE u domenskoj klasi!) |
| Generiranje rasporeda "svaka sa svakom" | `admin/class-admin.php` → `ajax_generate_fixtures()` (NIJE u `Kurs_Match`!) |
| Playoff seeding / bracket / napredovanje | `includes/class-playoff.php` (`generate`, `build_seeding_pairs`, `advance_winner`, `next_slot`) |
| Novi admin AJAX / forma | `admin/class-admin.php` (registracija u konstruktoru + `do_*`/`ajax_*`; nonce `kurs_ajax` za AJAX) |
| Izmjena admin sučelja (prikaz) | `admin/views/*.php` (logika je u `Kurs_Admin`, viewovi su samo prezentacija) |
| Zapisnik utakmice / PDF | `admin/views/match-record.php` + `match-record-pdf.php` (PDF je browser print-to-PDF) |
| Boja / badge / logo ekipe | `includes/helpers.php` (meta `_kurs_team_color`, naziv=post_title, logo=featured image) |
| Front-end tablica/raspored/statistika lige | `themes/kurskc/page-liga.php` (GET param `?season_id`/`?division_id`/`?tab`) |
| Front-end stranica utakmice | `themes/kurskc/page-utakmica.php` (`?match_id=X`) |
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

3. **Taksonomije `liga`/`sezona` NE POSTOJE.** Lige su slobodan tekst u `wp_kurs_seasons.league`; sezone/divizije su DB redovi (`wp_kurs_seasons` / `wp_kurs_divisions`). Jedina stvarna taksonomija je **`vijest_kategorija`** (na CPT `vijest`).

4. **CSS boje iz CLAUDE.md ne postoje.** `--color-primary:#1a3a6b` i `--color-accent:#e8b84b` NISU u `main.css`. Stvarne su `--c-*` (npr. `--c-kc:#DA0F1E` crvena za KC, `--c-pan:#F47B16` narančasta za Pan, `--c-gold:#FDE206`). `#1a3a6b` preživljava SAMO kao fallback default boja ekipe u `helpers.php` (`kurs_team_color`); `#e8b84b` nigdje.

5. **Cijeli plugin `kurs-liga` je nedokumentiran u CLAUDE.md.** Sva domenska arhitektura (9 DB tablica, playoff, admin sučelje) je izostavljena.

**Točan mentalni model podataka:**
- **WP postovi** (`kurs_ekipa`, `kurs_igrac`) nose: naziv (`post_title`), logo (featured image), boju (postmeta `_kurs_team_color`).
- **Custom tablice `wp_kurs_*`** nose natjecateljsku strukturu i rezultate. Stupci `team_id`/`player_id`/`home_team_id`/`away_team_id`/`scored_for` su `BIGINT UNSIGNED` = `wp_posts.ID` (NEMA DB foreign-key constraintova — integritet je samo u aplikacijskom kodu).

**Dodatni gotchas** — ✅ = ispravljeno 2026-06-22 (vidi [`ISPRAVCI.md`](ISPRAVCI.md)), ⚠️ = i dalje aktualno:
- ✅ `class-database.php` više **ne** koristi `ALTER TABLE ... ADD COLUMN IF NOT EXISTS` (nije validan MySQL) — nove stupce dodaje `dbDelta` preko `install()`.
- ✅ `admin/views/match-record-pdf.php` — riješene nedefinirane varijable (`$disp_home`/`$disp_away`/`$is_finished`) i "headers already sent" (PDF se renderira na `admin_init`, prije admin headera).
- ✅ Footer rute `/liga/` (stranica se ne kreira → 404) repointane na `/kc-liga/` i `/pan-ljetna/`.
- ✅ Gumb "Upravljaj playoff bracketom" (`season-setup.php`) repointan s neregistrirane stranice `kurs-playoff` na funkcionalnu rutu `kurs-matches?playoff_div=`.
- ⚠️ Tema **ovisi o pluginu**: `functions.php` uvjetno require-a plugin `helpers.php`; header izvodi direktan `$wpdb` JOIN po plugin tablicama. Ako je plugin deaktiviran, front-end degradira. (dizajn)
- ⚠️ `pg_playoff()` + `views/playoff.php` i dalje postoje kao napušten kod (sada nedostupan); funkcionalni playoff je na `kurs-matches?playoff_div=...`.

---

*Verzije u kodu su nedosljedne: plugin `KURS_VERSION=1.0.0` vs DB `DB_VERSION=1.5`; tema `KURSKC_VERSION=1.0.4` vs `style.css` Version `1.0.0`. To su poznate nedosljednosti, ne bugovi.*
