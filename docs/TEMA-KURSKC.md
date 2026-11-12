# TEMA KURSKC — Jezgra teme i front-end prikaz lige

> Baza znanja za temu `kurskc` (WordPress, projekt **kurskc.hr** — Koprivnička udruga za rekreativni sport / KURS).
> Putanja teme: `app/public/wp-content/themes/kurskc/`. Plugin: `app/public/wp-content/plugins/kurs-liga/`.
> Dokument pokriva: pregled teme, WP template hijerarhiju, registraciju assetsa/izbornika/CPT-ova u temi, `header.php`/`footer.php`, te front-end stranice lige (front-page, liga hub, centar utakmice, profili ekipe i igrača) i NAČIN dohvata podataka iz custom tablica plugina, plus JS/CSS.
> Sve nedosljednosti (npr. CPT registracija tema vs plugin, rute, verzije) jasno su označene oznakom **GOTCHA**.

---

## 0. Pregled na 30 sekundi

- Tema `kurskc` je **vlastita tema od nule** za portal dvije malonogometne lige: **KC Liga** (zima, dvorana) i **Pan Ljetna liga** (ljeto, trava).
- Tema je **tijesno spregnuta s pluginom `kurs-liga`**: posuđuje njegove helpere (`includes/helpers.php`), izvodi direktne `$wpdb` upite nad njegovim custom tablicama (`{prefix}kurs_*`) i poziva njegove statičke metode (`Kurs_Season`, `Kurs_Match`).
- **Podjela odgovornosti CPT-ova:**
  - Tema registrira SAMO CPT `vijest` + taksonomiju `vijest_kategorija`.
  - Plugin registrira CPT-ove `kurs_ekipa` (rewrite `ekipe`) i `kurs_igrac` (rewrite `igraci`).
  - **GOTCHA:** `CLAUDE.md` opisuje zastarjele CPT-ove `momcad`/`utakmica`/`vijest` i taksonomije `liga`/`sezona` — oni NE postoje u kodu. Stvarni model je: tema = `vijest`; plugin = `kurs_ekipa` + `kurs_igrac` + custom DB tablice za sve ligaške podatke (sezone, divizije, faze, grupe, utakmice, događaji, sastavi).
- **Navigacija je hardkodirana** u `header.php`/`footer.php` — registrirani izbornici (`primary`, `footer`) se nigdje ne renderiraju.
- **Front-end filteri/tabovi lige rade server-side** preko GET parametara i reload-a stranice — JS pokriva mobilni meni, tražilicu, dropdowne (`.hub-dd`), kopiranje linkova i stranicu „Za medije" (kopiranje/PNG).
- **NOVO 2026-10-16:** Liga hub ima tab **„Po kolima"** (`?tab=kola&kolo=N|playoff` — rezultati kola sa strijelcima, ljestvica i strijelci nakon kola, podaci iz plugin klase `Kurs_Rounds`); nova javna stranica **„Za medije"** (`/za-medije/`, `page-za-medije.php` + `inc/media-kit.php`) daje portalima paket rezultata kola (HTML / tekst / PNG); naslovnica ima sekciju **„Iz lige"**.

> ⚠️ Dokument je primarno usklađen s izmjenama od 2026-10-16. Značajke dodane između 2026-06-22 i 2026-10-16 (sustav vijesti `inc/news.php`, sponzori `inc/sponsors.php`, ikone liga, `.hub-dd` dropdowni, `Kurs_Ticker`) ovdje su opisane samo djelomično.

---

## 1. `functions.php`

Putanja: `themes/kurskc/functions.php`

### 1.1 Konstante
| Konstanta | Vrijednost | Izvor |
|-----------|-----------|-------|
| `KURSKC_VERSION` | `'1.0.62'` | hardkodirano (cache-busting `main.css`/`main.js` — podigni pri izmjeni asseta) |
| `KURSKC_DIR` | `get_template_directory()` | apsolutni FS put do teme |
| `KURSKC_URI` | `get_template_directory_uri()` | URL do teme |

> **GOTCHA (verzija):** `KURSKC_VERSION = '1.0.62'` u `functions.php` ne poklapa se sa `Version: 1.0.0` u `style.css` (namjerno — `style.css` se ne podiže).

### 1.2 Uključene datoteke (`require_once`)
1. `inc/post-types.php`
2. `inc/enqueue.php`
3. `inc/nav-menus.php`
4. `inc/news.php` — sustav vijesti (meta, pregledi, widgeti, shortcodeovi) + `kurskc_active_league()`. **Ugradnja iz lige (1.0.38):** meta okvir „KURS — opcije objave” ima dva izbornika (liga → prikaz) i skriveno polje `kurs_embed`; vrijednosti `round|table|results:DIV:KOLO` (kolo: rezultati + ljestvica nakon kola + strijelci / samo ljestvica nakon kola / rezultati ili najava kola — **zamrznuto na to kolo**, `Kurs_Rounds`), `live|schedule|bracket:DIV` (aktualna ljestvica, raspored, playoff samo za lige s playoffom) i stari `standings:SKUPINA`. `kurskc_embed_options()` / `kurskc_embed_parse()` / `kurskc_embed_render()`; prikaz ispod teksta (`single.php` → `kurskc_post_embed_html`) s linkom „Sve na stranici lige”. Shortcode `[kurs_kolo division_id kolo prikaz="kolo|tablica|rezultati"]`.
5. `inc/sponsors.php` — sponzori (traka `kurskc_sponsors_strip()` iznad footera)
6. `inc/media-kit.php` — **NOVO 2026-10-16**: klasa `Kurskc_Media_Kit` (paket „Za medije", vidi §16a)
7. `WP_PLUGIN_DIR . '/kurs-liga/includes/helpers.php'` — **uvjetno**: samo ako `! function_exists('kurs_team_badge_html')` i `file_exists(...)`. Tako tema posuđuje plugin helpere (`kurs_team_badge_html()`, `kurs_team_color()`, `kurs_render_standings()`, `kurs_league_url()`…). Ovo je čvor sprege teme i plugina.

### 1.3 Theme supports — `kurskc_setup()` (hook `after_setup_theme`)
- `load_theme_textdomain('kurskc', KURSKC_DIR.'/languages')` — **GOTCHA:** direktorij `languages/` ne postoji u temi (mrtvo).
- `add_theme_support('title-tag')`
- `add_theme_support('post-thumbnails')`
- `add_theme_support('html5', [...])` — `search-form, comment-form, comment-list, gallery, caption, style, script`
- `add_theme_support('custom-logo')` — **GOTCHA:** `the_custom_logo()` se NE koristi; logo je hardkodirani `<img>` u headeru/footeru. Custom-logo podrška je faktički neiskorištena.

### 1.4 Automatsko kreiranje stranica
- **`kurskc_create_pages()`** (hookovi `after_switch_theme` + anonimna `init` priority 20 kao sigurnosna mreža). Osigurava stranice (ako postoje samo ispravlja meta `_wp_page_template`, inače `wp_insert_post`):

  | Naslov | Slug | Template (`_wp_page_template`) |
  |--------|------|--------------------------------|
  | KC Liga | `kc-liga` | `page-liga.php` |
  | Pan Ljetna | `pan-ljetna` | `page-liga.php` |
  | Utakmica | `utakmica` | `page-utakmica.php` |
  | Za medije | `za-medije` | `page-za-medije.php` (**NOVO 2026-10-16**) |

  Sigurnosna mreža na `init` (priority 20) poziva `kurskc_create_pages()` ako nedostaje bilo koja od stranica `kc-liga`, `pan-ljetna`, `utakmica`, `za-medije`.

- **Anonimna `init` (priority 25)** kreira stranicu **"O KURS-u"** (slug `o-kurs-u`, template `page-o-nama.php`) s opsežnim hardkodiranim Gutenberg sadržajem (povijest udruge — predsjednik Tomislav Zalar, KC Liga & Liga bez briga, Pan Ljetna liga sa sponzorom Carlsberg/Pan pivo, "Kup nacija" mininogomet itd.). Stvara se samo ako `get_page_by_path('o-kurs-u')` ne vrati ništa.

> **GOTCHA (idempotentni overhead):** `kurskc_create_pages()` i "O KURS-u" init hook izvršavaju se na svakom `init` loadu (uz `get_page_by_path` provjeru) — mali overhead na svakom zahtjevu, ali idempotentno.

### 1.5 Pomoćna funkcija prikaza
- **`kurs_time_ago(string $date_str): string`** — hrvatski relativni datum. Pragovi: `< 60s` → "upravo sad"; `< 3600` → "prije X min"; `< 7200` → "prije 1 sat"; `< 86400` → "prije X h"; `< 172800` → "jučer"; `< 604800` → "prije X dana"; `< 1209600` → "prije tjedan dana"; `< 2592000` → "prije X tjedna"; inače `date_i18n('j.n.Y.', $time)`. Koristi `current_time('timestamp')`.

### 1.6 Rewrite rules
- **`kurskc_maybe_flush()`** (`init` priority 99) — ako je opcija `kurskc_flush_needed` postavljena → `flush_rewrite_rules()` pa `delete_option`.
- **`after_switch_theme`** anonimna — postavlja `kurskc_flush_needed=1` i odmah `flush_rewrite_rules()`.

### 1.7 Admin prilagodbe
- **`admin_menu`** anonimna — `remove_menu_page('edit.php')` (skriva native "Objave/Posts") i `remove_menu_page('edit-comments.php')` (skriva Komentare). Tema namjerno koristi CPT `vijest` umjesto native `post`.
- **`load-edit.php`** anonimna — preusmjerava `/wp-admin/edit.php` (bez `post_type`) na `edit.php?post_type=vijest`. Logika: ako je `post_type` postavljen i nije `'post'` → return; ako uopće nije postavljen → `wp_safe_redirect`.

---

## 2. `style.css` (theme header)

Sadrži SAMO WP theme header (stilovi su u `assets/css/main.css`):
- Theme Name: **KurSKC**
- Author: **Amondi Media**
- Description: tema za SK Koprivnica (KC liga i Pan ljetna liga)
- **Version: 1.0.0** (vidi GOTCHA verzije u 1.1)
- Requires at least: 6.4 · Requires PHP: 8.1 · Text Domain: `kurskc`

---

## 3. `inc/enqueue.php`

Funkcija **`kurskc_enqueue_assets()`** na hooku `wp_enqueue_scripts`:

| Handle | Tip | URL/Put | Deps | Verzija | Footer? |
|--------|-----|---------|------|---------|---------|
| `kurskc-fonts` | style | Google Fonts: **Barlow**, **Barlow Semi Condensed**, **Space Mono** (`display=swap`) | `[]` | `null` | — |
| `kurskc-main` | style | `KURSKC_URI . '/assets/css/main.css'` | `['kurskc-fonts']` | `KURSKC_VERSION` | — |
| `kurskc-main` | script | `KURSKC_URI . '/assets/js/main.js'` | `[]` | `KURSKC_VERSION` | `true` |

> Napomena: style i script dijele isti handle `kurskc-main` (dozvoljeno — odvojeni registri stilova i skripti).

---

## 4. `inc/nav-menus.php` — izbornici (mrtvi)

Funkcija **`kurskc_register_menus()`** (hook `after_setup_theme`) registrira lokacije:
- `primary` → "Glavni izbornik"
- `footer` → "Footer izbornik"

> **GOTCHA (mrtvi izbornici):** Ni `header.php` ni `footer.php` NE pozivaju `wp_nav_menu()`. Navigacija je u oba slučaja potpuno hardkodirana. Registrirane lokacije se nigdje ne renderiraju (admin ih nudi za dodjelu, ali bezuspješno).

---

## 5. `inc/post-types.php` — CPT i taksonomije TEME

Funkcija **`kurskc_register_post_types()`** (hook `init`):

### CPT `vijest`
- Labeli na hrvatskom (Vijesti / Vijest).
- `public => true`, `menu_icon => dashicons-megaphone`
- `supports => ['title','thumbnail','editor','excerpt']`
- `has_archive => true`, `rewrite => ['slug' => 'vijesti']` → arhiva `/vijesti/`
- `show_in_rest => true` (Gutenberg)
- `taxonomies => ['vijest_kategorija']`

### Taksonomija `vijest_kategorija`
- Vezana uz `vijest`, `hierarchical => true` (kategorije, ne tagovi)
- `public => true`, `show_in_rest => true`, `show_admin_column => true`
- `rewrite => ['slug' => 'vijesti/kategorija']`

### Defaultne kategorije (anonimna `init` priority 20)
Kreira termove ako ne postoje (`term_exists` → `wp_insert_term`):
| Slug | Naziv |
|------|-------|
| `vijesti` | Vijesti |
| `kc-liga` | KC Liga |
| `pan-ljetna` | Pan Ljetna liga |

### Usporedba s pluginom (NEMA dupliciranja)
- Tema registrira SAMO `vijest` + `vijest_kategorija`. Komentar u `inc/post-types.php:4`: *"Ekipe (kurs_ekipa) i Igrači (kurs_igrac) registrira kurs-liga plugin."*
- Plugin (`kurs-liga/includes/class-cpt.php`) registrira `kurs_ekipa` i `kurs_igrac`. Tema ih NE duplicira.
- **GOTCHA:** Stari CPT-ovi `momcad`/`utakmica` i taksonomije `liga`/`sezona` iz `CLAUDE.md` ne postoje. Lige/sezone se modeliraju kroz custom DB tablice plugina.

---

## 6. `header.php`

Struktura odozgo prema dolje:

1. `<!DOCTYPE html>` + `<head>`: charset, viewport, `preconnect` na Google Fonts, favicon = grb KC lige (`favicon.ico` + `favicon-32/192.png` + `apple-touch-icon.png`, `theme-color` #111317), `wp_head()`.
2. `<body body_class()>` + `wp_body_open()`.
3. **Live ticker** — renderira se SAMO ako `class_exists('Kurs_Ticker')` i `Kurs_Ticker::is_visible($state)`. Stanje daje plugin `Kurs_Ticker::get_state()` (`includes/class-ticker.php`): status `live` / `results` / `upcoming` / `champions` računa se iz rasporeda i zaključanih zapisnika, kešira u transientu `kurs_ticker_state_v1` (TTL po statusu) i ruši na `kurs_data_changed`. Header samo iscrtava `.site-ticker--{status}` sa stavkama `.ticker-item--finished|upcoming|live|champ` (≤3 stavke → statično, inače CSS petlja). Najave koriste hrvatske kratice dana (`sub 17.10. 15:00`) neovisno o jeziku WP-a, a podij prvaka linka na pravu ligu preko `kurs_league_url()` (✅ ISPRAVCI #10, #11).
4. **`<header class="site-header">`**:
   - Logo: hardkodirani `<img>` `assets/img/KURS-clean-ink.png` (120×26), link na home.
   - **Hardkodirana navigacija** `<nav class="site-nav" id="site-nav">`:
     - Vijesti → `/vijesti/` (`aria-current` ako `is_post_type_archive('vijest')` ili `is_singular('vijest')`)
     - KC Liga → `/kc-liga/` (klase `nav-league nav-kc`, ikona `kurs_league_icon()`, `aria-current` ako `is_page('kc-liga')`) + dropdown `.nav-dd`: **Tablica** (`?tab=tablica#liga`), **Po kolima** (`?tab=kola#kola`, NOVO 2026-10-16), **Raspored** (`?tab=raspored#liga`), **Statistika** (`?tab=statistika#liga`)
     - Pan Ljetna liga → `/pan-ljetna/` (klase `nav-league nav-pan`) + isti dropdown za `/pan-ljetna/`
     - O nama → `/o-kurs-u/` (`aria-current` ako `is_page('o-kurs-u')`)
     - Kontakt → `/kontakt/` (`aria-current` ako `is_page('kontakt')`)
   - Inline tražilica `form.header-search` (`name="s"`), gumb `.header-search-toggle` (#search-toggle) i `.nav-toggle` (#nav-toggle, hamburger s 3 `<span>`).
   - **Responzivno (✅ ISPRAVCI #16):** ≤1080px tražilica se skriva iza ikone (`#search-toggle` toggla `.site-header.search-open`, panel ispod headera); ≤880px izbornik ide u hamburger (`.site-nav.is-open` preko cijelog ekrana, dropdowni liga postaju statični podpopisi). Ranije se header na ~600–960px prelijevao horizontalno.
5. Otvara `<div class="site-box"><main id="main" class="site-main">` (zatvara `footer.php`).

> ✅ **(ranije GOTCHA „ruta /kontakt/")** — `functions.php` u međuvremenu automatski kreira i `kontakt` (template `page-kontakt.php`) te pravne stranice `kolacici`, `privatnost`, `uvjeti-koristenja` (anonimni `init` hookovi, samo ako ne postoje).

---

## 7. `footer.php`

1. Zatvara `</main></div>` (`.site-box`).
2. **`<footer class="site-footer">`**:
   - `.site-footer__stripes` — tri pruge: `--c-kc`, `--c-pan`, `--c-gold`.
   - `.site-footer__inner`, 5 stupaca:
     1. **Brend** — logo `assets/img/KURS-clean.png` (80×28), tagline, popis dviju liga (točkice `--c-kc`/`--c-pan`).
     2. **Portal** — Vijesti (`/vijesti/`), Tablice (`/kc-liga/?tab=tablica#liga`), **Rezultati po kolima** (`/kc-liga/?tab=kola#kola`), Raspored (`/kc-liga/?tab=raspored#liga`), **Za medije** (`/za-medije/`). *(2026-10-16: ranije su „Raspored kola" i „Rezultati" oba vodila na `?tab=raspored`.)*
     3. **Natjecanja** — KC Liga (`/kc-liga/`) i Pan Ljetna liga (`/pan-ljetna/`).
     4. **Udruga** — O KURS-u (`/o-kurs-u/`), Kontakt (`/kontakt/`).
     5. **Pratite nas** — inline SVG ikone (Facebook, Instagram, YouTube), svi `href="#"` (placeholderi).
   - `.site-footer__bottom` — copyright "Koprivnička udruga za rekreativni sport · © `date('Y')` KURS" + pravni linkovi: Uvjeti (`/uvjeti-koristenja/`), Privatnost (`/privatnost/`), Kolačići (`/kolacici/`).
3. `wp_footer()`.

> ✅ **ISPRAVLJENO (2026-06-22, ISPRAVCI #7):** footer je ranije sve ligaške linkove (Tablice, Raspored, Rezultati, KC, Pan) vodio na `/liga/` — stranicu koja se **ne kreira automatski** → 404. Sada vode na `/kc-liga/` (s `?tab=tablica`/`?tab=raspored`) i `/pan-ljetna/`, usklađeno s headerom.
> **GOTCHA:** social linkovi su `#` placeholderi. (`/kontakt/`, `/uvjeti-koristenja/`, `/privatnost/`, `/kolacici/` se u međuvremenu kreiraju automatski iz `functions.php`.)

---

## 8. Generičke template datoteke (jezgra)

| Datoteka | Uloga | Ključni detalji |
|----------|-------|-----------------|
| `index.php` | fallback/arhiva (vijesti, home, sve arhive, taksonomije) | `.sec-head` naslov: "Vijesti" za `is_post_type_archive('vijest')`/`is_home()`, "Ekipe" za `kurs_ekipa`, "Igrači" za `kurs_igrac`, inače `the_archive_title()`. Petlja → `.news-grid > .news-card` (thumbnail `medium`, badge = ime prvog terma `vijest_kategorija` ili "Vijest", naslov, datum `get_the_date('j.n.Y.')`). `the_posts_navigation()`. Prazno → `.kurs-empty` "Nema objava." |
| `page.php` | generičke statične stranice | `.container > article.page-content` s `.entry-header > .entry-title` i `.entry-content > the_content()`. *(Datoteka prisutna u CLAUDE strukturi; provjeriti postoji li — generičke stranice padaju na nju.)* |
| `single.php` | pojedinačni `vijest` i drugi generički single | `.article-wrap` s `.article-meta` (datum + prvi term `vijest_kategorija`), `.article-title`, `.article-thumb` (large), `.article-body > the_content()`. |
| `404.php` | greška 404 | `.error-404` "404 — Stranica nije pronađena" + `.btn.btn-primary` natrag na home. |
| `search.php` | rezultati pretrage | `.sec-head` "Rezultati pretrage: …", petlja `.news-grid > .news-card`, badge = `singular_name` post type-a (ili "Objava"). Prazno → `.kurs-empty`. `the_posts_navigation()`. |
| `template-parts/content.php` | kartica posta | **VJEROJATNO MRTAV KOD** — `index.php`/`search.php` ne pozivaju `get_template_part('template-parts/content')`, već inline renderiraju `.news-card`. |
| `template-parts/content-none.php` | prazan rezultat | **VJEROJATNO MRTAV KOD** — templati koriste vlastite `.kurs-empty` poruke. |

> **GOTCHA (mrtvi template-parts):** `content.php` i `content-none.php` ne pozivaju se ni iz jednog pregledanog predloška.

---

## 9. WordPress template hijerarhija

| Tip zahtjeva | Datoteka |
|--------------|----------|
| Početna (front page) | `front-page.php` |
| Blog/posts index, home | `index.php` (nema `home.php`) |
| Arhiva CPT `vijest` (`/vijesti/`) | `index.php` (nema `archive.php`/`archive-vijest.php`) |
| Arhiva taksonomije `vijest_kategorija` | `index.php` (nema `taxonomy.php`) |
| Arhive `kurs_ekipa` (`/ekipe/`), `kurs_igrac` (`/igraci/`) | `index.php` (naslovi "Ekipe"/"Igrači" hardkodirani u `index.php`) |
| Pojedinačni `vijest` | `single.php` |
| Pojedinačni `kurs_ekipa` | `single-kurs_ekipa.php` |
| Pojedinačni `kurs_igrac` | `single-kurs_igrac.php` |
| Statična stranica (generička) | `page.php` |
| Stranice `kc-liga` / `pan-ljetna` | `page-liga.php` (Template Name "Liga Hub") |
| Stranica `utakmica` | `page-utakmica.php` (Template Name "Centar utakmice") |
| Stranica `za-medije` | `page-za-medije.php` (Template Name "Za medije") — NOVO 2026-10-16 |
| Stranica "O KURS-u" | `page-o-nama.php` (Template Name "O nama") |
| Pretraga (`?s=`) | `search.php` |
| 404 | `404.php` |

Nedostaju (svjesno): `home.php`, `archive.php`, `taxonomy.php`, `category.php`, `tag.php`, `author.php`, `date.php`. Sve arhive padaju na `index.php`.

> **Bitno:** Page Template datoteke (`page-liga.php`, `page-utakmica.php`, `page-za-medije.php`, `page-o-nama.php`) vežu se uz stranicu preko **Template Name headera**, NE preko sluga datoteke. `functions.php` dodjeljuje template meta automatski stvorenim stranicama.

---

## 10. Asseti (`assets/img/`)

| Datoteka | Korištenje |
|----------|-----------|
| `KURS-clean-ink.png` | logo u headeru (`site-logo`) |
| `KURS-clean.png` | logo u footeru (`site-footer__logo`) |
| `KURS-monogram.png` | ikona liga kartica (front-page), banner ikona (`page-liga.php`) |
| `KURS-KC-boje.png` | nije referencirano u pregledanim predlošcima |
| `favicon.ico`, `favicon-32.png`, `favicon-192.png`, `apple-touch-icon.png` | favicon (grb KC lige, 1.0.46) u `<head>` |
| `kurs-media-logo.png` | **NOVO 2026-10-16** — bijeli KURS logo (240 px) u tamnom zaglavlju paketa „Za medije" (`Kurskc_Media_Kit::html()`, apsolutni URL) |

---

# FRONT-END PRIKAZ LIGE

## 11. Model dohvata podataka (KLJUČNO)

Front-end kombinira **WordPress objekte** (ekipe/igrači kao `WP_Post`, njihovi thumbnaili i meta) i **custom tablice plugina `kurs-liga`**. Postoje **dva paralelna pristupa dohvatu** koja se miješaju unutar istih datoteka:

1. **Statičke metode plugina** — `Kurs_Season::*` i `Kurs_Match::*` (glavni put za tablicu poretka, strijelce, sastave, kontekste).
2. **Direktni `$wpdb` upiti u predlošcima** — raspored, disciplina, forma ekipe, sve utakmice ekipe, cijela karijera igrača. Pišu SQL ručno koristeći prefiks:
   - `$wpdb->prefix . 'kurs_'` (literal — u `header.php`, `page-liga.php`, `page-utakmica.php`), ili
   - `$wpdb->prefix . KURS_PREFIX` (u `single-kurs_ekipa.php` i `single-kurs_igrac.php`); konstanta `KURS_PREFIX = 'kurs_'` definirana je u `plugins/kurs-liga/kurs-liga.php:18`. Oba oblika daju isti prefiks, npr. `wp_kurs_`.

**Tablice plugina korištene na front-endu:** `kurs_matches`, `kurs_match_events`, `kurs_groups`, `kurs_group_teams`, `kurs_phases`, `kurs_divisions`, `kurs_seasons`, `kurs_squads`. Imena ekipa/igrača dolaze JOIN-om na `wp_posts` (jer su `kurs_ekipa`/`kurs_igrac` CPT-ovi).

> **GOTCHA (decentralizirana logika):** Dohvat nije centraliziran — dio kroz metode plugina, dio kroz ad-hoc SQL u temi. Promjena sheme tablica zahtijeva izmjene i u pluginu i u više template datoteka.

### Korištene metode plugina (sažetak)
| Metoda | Vraća |
|--------|-------|
| `Kurs_Season::all()` | sve sezone (objekti `id, name, year, league, …`) |
| `Kurs_Season::get($id)` | jedna sezona |
| `Kurs_Season::get_divisions($season_id)` / `get_division($id)` | divizije sezone / jedna divizija |
| `Kurs_Season::get_phases($division_id)` | faze divizije (objekti `id, name, …`) |
| `Kurs_Season::get_groups($phase_id)` | grupe faze (objekti `id, name, playoff_spots, …`) |
| `Kurs_Season::get_squad($team_id, $division_id)` | *trenutni* roster: redovi `squads` + `player_name`, sort po dresu pa imenu (profil ekipe) |
| `Kurs_Match::lineup($match_id, $team_id, $division_id)` | sastav **na utakmici** (spremljena postava; prije završetka roster) — za stranicu utakmice |
| `Kurs_Match::player_career($player_id)` | karijera igrača (profil igrača) |
| `Kurs_Season::get_team_contexts($team_id)` | konteksti ekipe: `season_id, season_name, league, division_id, division_name` |
| `Kurs_Match::get($match_id)` | red `matches` + `home_name`, `away_name` |
| `Kurs_Match::get_events($match_id)` | `match_events` + `player_name`, sort po minuti |
| `Kurs_Season::for_league($is_pan)` / `current_for_league($is_pan)` | sezone jednog natjecanja / aktualna sezona (izbornik, „Za medije") |
| `Kurs_Season::group_phase($division_id)` | grupna faza lige (svaka liga ima točno jednu) |
| `Kurs_Match::get_standings($group_id, ?$max_round)` | poredak grupe (vidi 14.1); s `$max_round` = „nakon N. kola" |
| `Kurs_Match::get_top_scorers($division_id, $limit)` | strijelci (objekti `player_id, player_name, team_id, team_name, goals`; `team_id` vraća tek od 2026-10-16 — ✅ ISPRAVCI #18) |
| `Kurs_Rounds::*` (NOVO 2026-10-16) | `round_status`, `default_round`, `matches`, `goals_by_match`, `byes`, `standings`, `scorers`, `date_label` — pregled po kolima (detalji u `PLUGIN-KURS-LIGA.md` §4.4) |
| `Kurs_Playoff::exists/get_bracket/find_by_match_id` | postoji li bracket / bracket po rundama / playoff meč iz zapisnika (`group_id=0`) |

### Helperi za prikaz
| Funkcija | Datoteka | Uloga |
|----------|----------|-------|
| `kurs_team_badge_html($team_id, $size, …)` | plugin `includes/helpers.php` | kružni badge: thumbnail ekipe ili dvoslovni inicijali na boji ekipe |
| `kurs_team_color($team_id)` | plugin `includes/helpers.php` | meta `_kurs_team_color` ili default `#1a3a6b` |
| `kurs_team_text_color($hex)` | plugin `includes/helpers.php` | WCAG luminancija → bijeli/crni tekst |
| `kurs_league_icon($text, $size)` | plugin `includes/helpers.php` | grb lige: KC = službeni logo (`<img>`, plugin `assets/kc-liga-logo.png`), Liga bez briga / Pan = inline SVG |
| `kurs_league_url($division_id, $args)` | plugin `includes/helpers.php` | **NOVO** — URL huba prave lige (`/kc-liga/` ili `/pan-ljetna/`) + `season_id`/`division_id`/`$args` |
| `kurs_render_standings($group_id, $args)` | plugin `includes/helpers.php` | dijeljeni render ljestvice; novi argumenti `max_round`, `rows`, `show_move` |
| `kurs_render_playoff_bracket($division_id)` + `kurs_playoff_label($label)` | plugin `includes/helpers.php` | bracket; nepoznati sudionici „Pobjednik ČF 1" / „1. skupine A" umjesto „QF1-W" / „A1" |
| `kurs_render_round_results($division_id, $round)` / `kurs_render_round_scorers($division_id, $round)` | plugin `includes/helpers.php` | **NOVO** — rezultati kola sa strijelcima / strijelci nakon kola |
| `kurs_schedule_date_label($datetime)` | plugin `includes/helpers.php` | „Subota, 12. srpnja 2026." |
| `kurs_time_ago($date_str)` | tema `functions.php` | hrvatski relativni datum |
| `kurskc_badge_color/label($post_id)` | tema `inc/news.php` | boja/oznaka vijesti po `vijest_kategorija` |
| `kurskc_thumb_placeholder($post_id, $size = 72)` | tema `inc/news.php` | vijest bez istaknute slike: `<span class="kc-ph kc-ph--kc|lbb|pan|kurs">` — tamna podloga, plameni rub u bojama lige (`.kc-ph__fire`, SVG maska), grb (`kurs_league_icon()`, izvan liga KURS monogram) i naziv lige (`.kc-ph__lbl`, samo u okvirima ≥300×170 — container query); na mobitelu u heroju/izdvojenoj grb malen gore desno — kartice naslovnice/arhive/pretrage, povezano, hero i izdvojena vijest (tema 1.0.43, redizajn 1.0.46) |
| `kurskc_active_league()` | tema `inc/news.php` | „aktivna" liga = najbliža utakmica (`league`, `season_id`, `division_id`, `round`, `label`, `url`); transient 30 min, ruši se na `kurs_data_changed`. **2026-10-16:** `label` = naziv lige („KC Liga · 5. kolo", ranije sirovi slug `kc_liga`), `url` → `?tab=kola&kolo=N#kola` (✅ ISPRAVCI #12). Koriste ga widget „Iz lige · uživo", naslovnica („Iz lige") i „Za medije" (zadano natjecanje). |
| `Kurskc_Media_Kit::*` | tema `inc/media-kit.php` | **NOVO** — paket „Za medije" (vidi §16a) |

---

## 12. Mapiranje stranica na slugove (front-end)

| Datoteka | Template Name | Stranica (slug) | URL |
|----------|---------------|-----------------|-----|
| `page-liga.php` | `Liga Hub` | `kc-liga` **i** `pan-ljetna` (ista datoteka, dvije stranice) | `/kc-liga/`, `/pan-ljetna/` |
| `page-utakmica.php` | `Centar utakmice` | `utakmica` | `/utakmica/?match_id=X` |
| `page-za-medije.php` | `Za medije` | `za-medije` (NOVO 2026-10-16) | `/za-medije/?liga=kc\|pan&season_id=X&kolo=N\|playoff` |
| `page-o-nama.php` | `O nama` | `o-kurs-u` | `/o-kurs-u/` |

CPT single predlošci (slug iz `plugins/kurs-liga/includes/class-cpt.php`):
| CPT | rewrite slug | Single predložak | Arhiva |
|-----|-------------|------------------|--------|
| `kurs_ekipa` | `ekipe` | `single-kurs_ekipa.php` | `/ekipe/` |
| `kurs_igrac` | `igraci` | `single-kurs_igrac.php` | `/igraci/` |

Razlikovanje KC vs Pan u `page-liga.php` ide po **slugu stranice**: `$current_slug = get_post_field('post_name', get_the_ID())`, pa `$is_pan = ($current_slug === 'pan-ljetna')`. Obrnuto — iz divizije do prave stranice lige — uvijek gradi **`kurs_league_url($division_id, $args)`** (ne hardkodiraj `/kc-liga/`).

---

## 13. `front-page.php` — Naslovnica

Fokus: vijesti + ulaz u lige + (od 2026-10-16) zadnje kolo aktualnog natjecanja.

- **Vijesti:** `get_posts(...)` (istaknuta + najnovije). Prva je hero (`.kurs-hero__main`, **16:9** kao kartice vijesti — kartice liga `.kurs-hero__side` istežu se na istu visinu; ≤600 px visina prema sadržaju, min. 240 px), ostale 3 u `.news-grid > .news-card`. Izdvojena vijest u arhivi (`.kc-feature__img`) je također 16:9 (tema 1.0.45).
- **Badge vijesti:** `kurskc_badge_color($post_id)` / `kurskc_badge_label($post_id)` iz `inc/news.php` — čitaju taksonomiju **`vijest_kategorija`** (ne `liga`!).
- **Liga kartice** (`.kurs-hero__side`): dvije statične kartice — KC Liga ("Zima · Dvorana") i Pan Ljetna ("Ljeto · Trava"). Linkovi **hardkodirani** na `home_url('/kc-liga/')` i `home_url('/pan-ljetna/')`; CTA je od 2026-10-16 **„Rezultati · Tablica →"** (ranije „Tablica · Raspored →").
- **„Iz lige" (NOVO 2026-10-16)** — sekcija između hero dijela i „Najnovije", s linkom „Za medije →" u naslovu. Natjecanje = `kurskc_active_league()`; za **svaku ligu (diviziju) te sezone**:
  - kolo = `Kurs_Rounds::default_round()` (zadnje s odigranom utakmicom; preskače ligu bez kola), utakmice `Kurs_Rounds::matches()`, datum `date_label()`;
  - badge „Rezultati" (ima odigranih) ili „Najava";
  - **vodeći** („Vodi: X · N bod.") samo za ligu s **jednom skupinom** (KC) — `Kurs_Match::get_standings()`; za Pan (više skupina) umjesto toga „Sljedeće: N. kolo · datum";
  - sljedeće kolo = prvo kasnije kolo s neodigranim utakmicama;
  - link (zaglavlje kartice i „Ljestvica i strijelci →") = `kurs_league_url($did, ['tab'=>'kola','kolo'=>$r]) . '#kola'`; svaka utakmica (`.hr-match`, pobjednik `.is-win`) → `/utakmica/?match_id=`.
  - Markup/CSS: `.home-rounds` (2 stupca; `--single` za jednu ligu), `.home-round--kc|lbb|pan` (boja tona), `.home-round__head/__list/__foot/__next`. CSS sekcija „NASLOVNICA — Iz lige" u `main.css`.
- **Datum hero vijesti:** `date_i18n('j.n.Y.', …)`; datum grid kartica: `kurs_time_ago($p->post_date)`.

---

## 14. `page-liga.php` — Liga Hub (najvažniji ekran)

**Guard na vrhu:** ako `! class_exists('Kurs_Season')` → ispiše "KURS Liga plugin nije aktivan.", `get_footer()` i `return`.

Kontekst se bira ISKLJUČIVO preko **GET parametara** (nema ACF/meta):
- `?season_id=X` — sezona (`absint`). Mora pripadati natjecanju stranice (`Kurs_Season::for_league($is_pan)`), inače se ignorira. Default: `Kurs_Season::current_for_league($is_pan)` (prva aktivna, inače najnovija).
- `?division_id=Y` — liga (divizija). **Od 2026-10-16 se validira:** mora biti među divizijama odabrane sezone, inače prva divizija sezone (✅ ISPRAVCI #14 — ranije se mogla prikazati divizija druge sezone/natjecanja).
- `?tab=tablica|kola|raspored|statistika` — prikaz (default `tablica`; whitelist = ključevi `$tabs`, `sanitize_key`). Labela taba `kola` je **„Po kolima"**.
- `?kolo=N|playoff` — **NOVO 2026-10-16**, samo za `tab=kola` (vidi 14.2).

Iz divizije se računa `$has_playoff = !empty($division->has_playoff)` i `$has_bracket = Kurs_Playoff::exists($division_id)`.

URL-ovi za prebacivanje grade se helperom **`kurs_hub_url($base, $sid, $did, $tab, array $extra = [])`** (`add_query_arg`; `$extra` npr. `['kolo' => 5]`). Sezona se mijenja custom dropdownom `.hub-dd--season` (`#season-dd`, linkovi, JS u `main.js`); liga (`.div-toggle`) i view tabovi (`.view-tabs`, kontejner `#liga`) su `<a>` linkovi s klasom `is-active`. U tabu „Po kolima" prebacivanje lige **zadržava isto kolo** (`$extra = ['kolo' => $kolo]`). Desno od view tabova, u `.hub-tabs__end`, je gumb **„Za medije”** (`.hub-media`, od 1.0.40) na **svakom tabu**: vodi na `/za-medije/?liga=kc|pan&season_id=` (+ `&kolo=` u tabu „Po kolima”, za prikazano kolo/playoff; inače stranica sama uzme zadnje odigrano kolo). Na ≤600 px tabovi se pomiču vodoravno, a gumb ostaje vidljiv.

Branding po ligi (po `$is_pan`): `$league_label`, `$league_sub`, `$accent` (`var(--c-pan)`/`var(--c-kc)`), CSS klase `league-hub--pan` / `league-banner--pan`, ikona `kurs_league_icon()`. Zaključana sezona → oznaka „🔒 Završeno · konačni rezultati".

### 14.1 Tab "tablica" (poredak)
Od 2026-10-16: `Kurs_Season::group_phase($division_id)` → skupine → za svaku **`kurs_render_standings($grp->id, ['title' => (više skupina ? naziv : ''), 'playoff_spots' => $has_playoff ? null : 0])`**. KC lige (bez playoffa) zato **nemaju** oznaku prolaza ni legendu (✅ ISPRAVCI #13 — ranije „Playoff (1–2)" iz DB defaulta `playoff_spots=2`); Pan koristi `playoff_spots` skupine (u Pan boji, `.league-hub--pan .standings__row.playoff`). Ako ništa nije ispisano → „Ljestvica će biti dostupna kad se ekipe rasporede u ligu." Ako bracket postoji, **ispod ljestvica** se prikazuje „Playoff" + `kurs_render_playoff_bracket($division_id)`.

`get_standings()` vraća **niz asocijativnih nizova** (već sortiran) sa stupcima: `team_id, team_name, P` (odigrane), `W` (pobjede), `D` (remiji), `L` (porazi), `GF, GA, GD, Pts, FP` (fair-play bodovi). Računa se dinamički iz `finished` utakmica (3/1/0). Sortiranje je **UEFA/HNS stil**: bodovi → mini-tablica međusobnih ogleda (h2h Pts/GD/GF) → ukupni GD → ukupni GF → fair-play (žuti×3 + crveni×10, manje je bolje).

Prikaz (`.standings`, markup iz `kurs_render_standings`): glava `# Klub O P N I GR Bod`. Badge ekipe → `kurs_team_badge_html($row['team_id'], 32)`. Playoff pozicije (`$i < $spots`) dobivaju klase `playoff`/`is-playoff`; granični red `standings__row--cutoff`. Svaki red je `<a>` na profil ekipe (`get_post_permalink($team_id)`). Legenda „Playoff (1–N)" samo kad je `$spots > 0`.

### 14.2 Tab "kola" — „Po kolima" (NOVO 2026-10-16)
Pregled jednog kola lige: rezultati sa strijelcima, ljestvica i strijelci **nakon tog kola**. Svi podaci iz plugin klase **`Kurs_Rounds`** (vidi `PLUGIN-KURS-LIGA.md` §4.4) — isti kao na „Za medije".

- **Odabir kola:** `$rounds = Kurs_Rounds::round_status($division_id)`. `?kolo=playoff` vrijedi samo ako bracket postoji; `?kolo=N` samo ako kolo postoji. Inače **zadano**: `Kurs_Rounds::default_round()` (zadnje odigrano kolo), osim kad bracket postoji i (nema kola **ili**, bez `?kolo` u URL-u, grupna faza je gotova / playoff je počeo — `Kurskc_Media_Kit::playoff_started()`) → `playoff`.
- **Traka kola** `nav.round-nav#kola` (sidro `#kola`, `scroll-margin-top`): strelice ‹ › (`.round-nav__arrow`, `is-disabled`), pilule `.round-pill--done|partial|upcoming` (title „5. kolo · odigrano 3/4"), `is-active` + `aria-current`, na kraju pilula **„Playoff"** (`.round-pill--playoff`) ako bracket postoji. „Sljedeće" iz zadnjeg kola vodi na playoff. Linkovi = `kurs_hub_url(..., 'kola', ['kolo' => $k]) . '#kola'`.
- **`kolo=playoff`:** zaglavlje `.round-head` („Playoff") + `kurs_render_playoff_bracket()`.
- **`kolo=N`:** zaglavlje `.round-head` (kicker = naziv lige, „N. kolo", datum `Kurs_Rounds::date_label()`) + kad je u kolu išta odigrano link **`.round-head__media` „Za medije · Preuzmi rezultate →"** → `/za-medije/?liga=kc|pan&season_id=…&kolo=N`.
  - **Rezultati / Utakmice** — `kurs_render_round_results($division_id, $kolo)` (strijelci ispod rezultata, „(ag)" za autogol, kod više skupina grupirano po skupini, red „Slobodna u ovom kolu: …" kod neparnog broja ekipa).
  - Ako je išta odigrano: **„Ljestvica nakon N. kola"** — za svaku skupinu iz `Kurs_Rounds::standings()` → `kurs_render_standings(gid, ['rows' => …, 'show_move' => true, 'playoff_spots' => $has_playoff ? null : 0])` (strelice ▲/▼/– `.st-move`) + napomena `.round-note` ako ima neodigranih utakmica zaključno s kolom (nisu uračunate); **„Strijelci nakon N. kola"** — `kurs_render_round_scorers()` (grupirano po broju golova, red „AG" za autogolove).
  - Neodigrano kolo → „Ovo kolo još nije odigrano — ljestvica i strijelci pojavit će se nakon prvih rezultata."
- Nema kola ni bracketa → „Raspored za ovu ligu još nije objavljen."
- CSS: sekcija „LIGA HUB — Po kolima" u `main.css` (`.round-nav`, `.round-pill*`, `.round-head*`, `.round-sec*`, `.round-results`, `.rr-match*`, `.rr-bye`, `.st-move*`, `.round-scorers`, `.rs-*`, `.round-note`).

> **GOTCHA:** pregled po kolima vjeruje `matches.round`. Ekipa upisana dvaput u isto kolo kvari ljestvicu „nakon N. kola" i oznaku slobodne ekipe — admin (stranica natjecanja → kartica Utakmice, `hub-matches.php`) zato prikazuje upozorenje `.kurs-round-warn`.

### 14.3 Tab "raspored"
**Od 1.0.42:** `kurs_render_schedule($division_id, ['round_labels' => true])` (plugin `helpers.php`) — isti renderer kao ugradnja „Raspored” u vijestima. Podaci: `Kurs_Rounds::schedule()` = utakmice skupina **+ playoff** (zapisnici, s penalima „pen. 2:0” ispod rezultata, i parovi bez zapisnika — npr. „Pobjednik ČF 1 – Pobjednik ČF 2”, bez linka), po terminu. Grupira po **danu** (`.sched-round-label` = `kurs_schedule_date_label()`); ispred datuma `.sched-round-label__no`: kola skupina („5. kolo”, „4. i 5. kolo” kad se odgođena utakmica igra kasnije) i playoff runde („Polufinale · Ut. za 3. mjesto · Finale”). Završene → rezultat; ostale → vrijeme (`H:i`) ili „vs”; utakmica sa zapisnikom → `/utakmica/?match_id=`. Prije je tab imao vlastiti upit samo nad skupinama, pa se playoff nije vidio (ISPRAVCI #42).

### 14.4 Tab "statistika"
`Kurs_Match::get_top_scorers($division_id, 20)` → `.widget` "Strijelci", redovi `.scorer-row` s dvoslovnim sufiksom ekipe (`mb_substr(team_name, 0, 2)`).

### 14.5 Side rail (`aside.hub-side`, vidljiv kad je odabrana divizija)
- **"Zlatna kopačka":** `Kurs_Match::get_top_scorers($division_id, 10)` — manji widget s rangom, badgeom ekipe (`kurs_team_badge_html($sc->team_id, 28)` — radi tek otkad upit vraća `team_id`, ✅ ISPRAVCI #18) i linkom na igrača (`get_permalink($player_id)`); ako ima ≥10 → link "Svi strijelci →" na tab statistika.
- **"Disciplina":** **direktan `$wpdb` upit** koji broji `yellow_card` i `red_card`+`second_yellow` iz `{p}match_events e JOIN matches/groups/phases` za diviziju. Prikazuje dvije kutije (`.disc-box--y` žuti / `.disc-box--r` crveni) samo ako `yellow > 0 || red > 0`.

> ✅ **Bracket se prikazuje** (ranije GOTCHA „playoff bracket se NE prikazuje"): na tabu Tablica ispod ljestvica i kao „kolo" Playoff u tabu Po kolima, preko `kurs_render_playoff_bracket()`.

> **Mobitel (✅ ISPRAVCI #15):** ≤900px `.hub-body { align-items: stretch }`, ≤600px grid ljestvice `26px minmax(0,1fr) …` — ranije je hub (i tab Tablica) bio širi od ekrana.

---

## 15. `page-utakmica.php` — Centar utakmice

URL `/utakmica/?match_id=X`. **Guardovi (svaki s `get_footer()` + `return`):** nema klase `Kurs_Match` → "plugin nije aktivan"; nema `match_id` → "Nije odabrana utakmica."; `Kurs_Match::get()` vrati prazno → "Utakmica ne postoji."

- **Dohvat utakmice:** `Kurs_Match::get($match_id)` (red `matches` + `home_name`, `away_name`).
- **Kontekst:** **direktan `$wpdb` upit** `{p}groups g JOIN phases ph JOIN divisions d JOIN seasons s WHERE g.id = %d` → `group_name, division_id, div_name, season_name, phase_name`.
- **Playoff meč (`group_id = 0`, NOVO 2026-10-16):** kontekst iz grupe je prazan, pa se `division_id` uzima iz `Kurs_Playoff::find_by_match_id($match_id)->division_id` (+ `$po_div = Kurs_Season::get_division()`).
- **Događaji:** `Kurs_Match::get_events($match_id)` → `match_events` + `player_name`, sort po minuti.
- **Sastavi (1.0.39):** `Kurs_Match::lineup($match_id, $team_id, $division_id)` — postava spremljena uz utakmicu (prije završetka trenutni roster lige), **bez igrača označenih „nije igrao”** (`get_absent`); ime igrača vodi na njegov profil. Tijek utakmice ne prikazuje inertne događaje `captain` / `absent` (prije su se prikazivali kao gol — ISPRAVCI #36). Događaj bez minute (unos iz izvještaja) nema „0'\", a naslovi poluvremena se ne prikazuju kad nijedan događaj nema minutu/poluvrijeme (ISPRAVCI #38).
- **Prikaz:**
  - Breadcrumb (grupna faza): Naslovnica / `div_name` (link `kurs_league_url($division_id, ['tab'=>'raspored'])`) / **„N. kolo"** (link na `kurs_league_url(..., ['tab'=>'kola','kolo'=>N]) . '#kola'`).
  - Breadcrumb (playoff): Naslovnica / naziv lige / „Četvrtfinale | Polufinale | Finale" (link na `kolo=playoff`; mapa `$po_names`, uklj. `3rd` → „Utakmica za 3. mjesto").
  - "Semafor" scoreboard (`.semafor`): badge ekipa 64px (`kurs_team_badge_html`, link na profil), rezultat ili "– : –", status "Završeno"/datum (`date_i18n('j.n.Y., H:i')`).
  - Timeline "Tijek utakmice" (`.kurs-event-timeline > .tl-row`): ikone preko `$icon_map` (`goal`/`own_goal` → `goal`, `yellow_card` → `yellow`, `red_card` → `red`), minuta, igrač, "ekipa · tip" (npr. "gol", "gol (vlastita mreža)", "žuti karton").
  - Dvije kolone sastava (`.lineup-cols > .lineup-team`) s brojem dresa (`jersey_no` ili "—").

> ✅ **ISPRAVLJENO (2026-10-16, ISPRAVCI #9):** `$liga_url` se ranije gradio kao `home_url('/kc-liga/?division_id=…&tab=raspored')` — uvijek `/kc-liga/`, i za Pan ljetne utakmice; playoff mečevi nisu imali ni breadcrumb lige ni postave. Sada `kurs_league_url()` + playoff kontekst iz `Kurs_Playoff::find_by_match_id()`.

---

## 16. `page-o-nama.php` — O nama

Najjednostavniji: `.o-nama-hero` (label "Udruga", naslov "O nama") + `.o-nama-content` koji renderira `the_content()` (uređivo iz WP admina — sadržaj predpopunjava `functions.php`). Bez ligaških podataka.

---

## 16a. `page-za-medije.php` + `inc/media-kit.php` — „Za medije" (NOVO 2026-10-16)

Javna stranica (`/za-medije/`, Template Name **„Za medije"**, auto-kreira `kurskc_create_pages()`) s koje drugi portali preuzimaju **paket rezultata jednog kola** s KURS brandingom: rezultati sa strijelcima, ljestvica i strijelci nakon kola. Podaci su isti kao u tabu „Po kolima" (`Kurs_Rounds`). Linkovi na nju: footer („Za medije"), naslovnica („Iz lige" → „Za medije →"), Liga hub (`.round-head__media` u tabu Po kolima), admin: zaglavlje stranice natjecanja `season-hub.php` („Za medije ↗", `?liga=kc|pan&season_id=`), kartica Pregled („Paket za medije ↗") i glavni Pregled `dashboard.php` („Za medije ↗").

### Parametri i zadane vrijednosti (`page-za-medije.php`)
- `?liga=kc|pan` — natjecanje; bez/nevaljano → prema `kurskc_active_league()` (natjecanje s najbližom utakmicom), inače `kc`.
- `?season_id=X` — mora pripadati natjecanju (`for_league`), inače `current_for_league()`.
- `?kolo=N|playoff` — kola su **unija svih liga sezone** (`Kurskc_Media_Kit::season_rounds()` → `[kolo => total, finished, date]`). `playoff` samo ako neka divizija ima bracket. Zadano: playoff ako je bracket započet (`playoff_started()`), inače zadnje kolo s barem jednom odigranom utakmicom, inače prvo kolo.
- Paket: za `kolo=N` po jedan `division_data($d, N, $is_pan)` za **svaku ligu sezone** (KC = KC Liga + Liga bez briga u jednoj kartici); za `playoff` samo divizija s bracketom.
- Guard: bez `Kurs_Season`/`Kurs_Rounds` → „KURS Liga plugin nije aktivan."; bez rasporeda → „Za odabrano natjecanje još nema rasporeda…".

### Sučelje
- **Hero** (`.media-hero`, koraci 1–3) + **picker** `form.media-picker` (GET, sidro `#paket`): toggle natjecanja (`.div-toggle`, linkovi), `<select name="season_id">` i `<select name="kolo">` s atributom **`data-autosubmit`** (JS šalje formu na `change`; `<noscript>` gumb „Prikaži"). Opcija kola nosi datum i stanje („— djelomično" / „— najava").
- **Kartice** `.media-tabs` (`data-media-tab`): **Pregled** (`.media-paper` = sam paket; za neodigrano kolo oznaka `.media-flag` „najava"), **Tekst** (`<pre>`), **HTML kod** (`textarea#media-src-html`, fokus = select all).
- **Akcije** (`aside.media-actions`):
  - **Kopiraj HTML za WordPress** (`data-media-copy="html"`) — `navigator.clipboard.write([new ClipboardItem({'text/plain': kod, 'text/html': kod})])`: lijepljenje u blok „Prilagođeni HTML" daje kod, u vizualni uređivač oblikovani sadržaj. Fallback `writeText` → `execCommand('copy')` (`kurskcFallbackCopy`).
  - **Kopiraj kao običan tekst** (`data-media-copy="text"`, izvor skriveni `textarea#media-src-text`).
  - **Preuzmi sliku (PNG)** (`data-media-png="media-png-all"`) + za KC (više liga) po jedan gumb **PNG · <liga>** (`media-png-0`, `media-png-1`). Ime datoteke iz `data-media-name` (npr. `kurs-kc-liga-9-kolo.png`).
  - Upute za objavu na WordPressu, napomena o navođenju izvora.
- **PNG izvori:** skriveni offscreen `.media-png-src` s karticama `.media-png-card` fiksne širine **680 px** (cijeli paket + po ligi — `Kurskc_Media_Kit::html([$d], …)`).

### PNG izvoz — html-to-image (NE html2canvas)
- `wp_enqueue_script('html-to-image', 'https://cdnjs.cloudflare.com/ajax/libs/html-to-image/1.11.11/html-to-image.min.js')` — enqueue se radi u templateu **prije `get_header()`**.
- **GOTCHA:** html2canvas je Barlow tekst crtao pomaknut/odrezan; html-to-image crta preko SVG `foreignObject`, točno kao preglednik.
- Web fontovi moraju biti ugrađeni kao `data:` URL inače slika padne na Arial: `main.js` jednom dohvati CSS Google Fontsa (`link#kurskc-fonts-css`), zadrži samo `@font-face` blokove **latin + latin-ext** (hrvatski znakovi), woff2 pretvori u data URL i preda kao `fontEmbedCSS`; čeka `document.fonts.ready`; `toPng(el, {pixelRatio: 2, backgroundColor: '#ffffff'})`.

### `Kurskc_Media_Kit` (`inc/media-kit.php`) — static klasa
| Metoda | Ponašanje |
|---|---|
| `accent($division_name, $is_pan)` | Boja lige: KC `#DA0F1E`, Liga bez briga `#B07A1B` (naziv sadrži „briga"), Pan `#F47B16`. |
| `genitive($name)` | „KC Liga" → „KC lige", „Liga bez briga" → „Lige bez briga", „Pan Ljetna liga" → „Pan ljetne lige" (naslov „Rezultati 9. kola KC lige"). |
| `season_rounds($divisions)` | Unija kola svih liga: `[kolo => ['total','finished','date']]`. |
| `playoff_started($division_id)` | Ima li bracket barem jednu `finished` utakmicu (koristi i `page-liga.php`). |
| `division_data($division, $round, $is_pan)` | Sve za jednu ligu: `name`, `accent`, `spots` (= `has_playoff`), `url` (`kurs_league_url(...tab=kola, kolo)#kola`); za kolo `matches`, `goals`, `byes`, `date`, `finished`, `tables` + `scorers` (samo ako je išta odigrano); za `playoff` runde `qf/sf/final` s golovima i **`champion`** (pobjednik odigranog finala). |
| `html($datas, $round, $competition, $season_name)` | Paket kao HTML (vidi pravila niže). |
| `text($datas, $round, $season_name)` | Paket kao običan tekst (stil izvještaja portala). |

**Pravila HTML-a (mora preživjeti `wp_kses_post` kod autora bez `unfiltered_html`):**
- samo **inline stilovi i tablice** (`<table width="100%" cellpadding="0" …>`), nema `<style>`, `<script>`, `<svg>`, `data:` URI-ja ni komentara; **hex boje** (kses ne propušta `rgba()`); **apsolutni URL-ovi** slika i poveznica;
- **`font-family` bez navodnika** (`Barlow Semi Condensed,Arial Narrow,Arial,sans-serif`) — kses odbacuje vrijednosti s navodnicima. Portali nemaju Barlow pa padaju na Arial Narrow/Segoe UI/Consolas (PNG s naše stranice ima prave fontove);
- **sve u jednom redu** (`preg_replace('/>\s+</', '><', …)`) — klasični uređivač (wpautop) bi inače ubacio `<p>`/`<br>`;
- provjereno je da svi tagovi i CSS deklaracije prolaze `wp_kses_post`;
- „pilule" (rezultat, broj golova) su `<div>` fiksne širine/visine (`line-height = height`) — predvidljivo u tuđim temama.

**Struktura paketa (`html()`):** max 680 px, bijela pozadina → tamno zaglavlje (`#111317`) s logom `assets/img/kurs-media-logo.png` i naslovom „KC Liga · 9. kolo" (+ datum · sezona) → trobojna crta KC crvena / zlatna `#FDE206` / Pan narančasta → po ligi blok s naslovom „Rezultati|Najava N. kola <genitiv>" (lijevi rub u boji lige), rezultati (strijelci domaćina / gosta ispod; neodigrano: vrijeme / „odgođeno" / „termin naknadno"), red „Slobodna u ovom kolu", „Ljestvica nakon N. kola" (prolaz u playoff isprekidanom crtom u boji lige + napomena, strelice pomaka, napomena o neodigranim), „Strijelci" (zlatna pilula za prvu grupu, red AUTOGOL) → podnožje „Izvor: **KURS · <host>**" + „Zapisnici i statistika →" (link na pregled kola). **Neodigrano kolo** = paket **„Najava"** s terminima. **Playoff** paket: zlatni banner „🏆 Prvak" (ako je finale odigrano) + runde Četvrtfinale / Polufinale / Finale (nepoznati sudionici preko `kurs_playoff_label()`).

**Tekst (`text()`):** npr.
```
REZULTATI 9. KOLA KC LIGE (subota, 28. 2. 2026.)

Ekipa A – Ekipa B 3:1
(Horvat 2, Kovač / Babić)
Slobodna: Ekipa C

KC LIGA – LJESTVICA
1. Ekipa A 9 7 1 1 30:12 22

STRIJELCI
12 – Horvat (Ekipa A), …
Autogol – Kovač (Ekipa B)

Izvor: KURS – Koprivnička udruga za rekreativni sport (kurskc.hr)
```
Strijelci su „(domaćin / gost)"; kad je zabila samo jedna strana — bez kose crte. Neodigrane: „NAJAVA …", „(18:00)" / „(odgođeno)" / „(termin naknadno)"; playoff: „PLAYOFF PAN LJETNE LIGE", „ČETVRTFINALE…", „PRVAK: …".

CSS: sekcija „ZA MEDIJE" u `main.css` (`.media-hero*`, `.media-picker*`, `.media-tabs`, `.media-panel`, `.media-paper`, `.media-actions`, `.media-card*`, `.media-btn*` s `is-busy/is-done/is-failed`, `.media-png-src`/`.media-png-card`).

---

## 17. `single-kurs_ekipa.php` — Profil ekipe

**Guard:** ako `! class_exists('Kurs_Season') || ! class_exists('Kurs_Match')` → `wp_redirect(home_url('/')); exit;` (PRIJE `get_header()`; `/ekipe/` nema arhivu).

> **Od 1.18.1:** sve utakmice lige biraju se preko `$in_div = Kurs_Match::division_match_ids_sql($division_id)` (`m.id IN ( … )`) — skupine **i playoff** (prije su upiti išli samo preko skupina pa se Pan playoff nije vidio). Oznaka runde preko `kurs_round_label($round, $po_round)` („3. kolo” / „Četvrtfinale”). `$team_id = get_the_ID()`. Prefiks `$p = $wpdb->prefix . KURS_PREFIX`.

- **Konteksti:** `Kurs_Season::get_team_contexts($team_id)` → `season_id, season_name, league, division_id, division_name`. Aktivna divizija iz `?division_id=` (validira se protiv `array_column($contexts,'division_id')`); default prvi.
- **Pozicija u tablici:** petlja `get_phases` → `get_groups` → `Kurs_Match::get_standings`, traži red s `team_id == $team_id`, izvlači `pos` i `total`.
- **Forma (zadnjih 5):** **direktan `$wpdb` upit** nad `matches` (`finished`, ekipa kao home ili away, `ORDER BY match_date DESC LIMIT 5`). Prikaz P/N/I kockica (`--c-win`/`--c-draw`/`--c-loss`), svaka linkana na utakmicu.
- **Sljedeća utakmica:** **direktan `$wpdb` upit**, prvi `scheduled` meč (`.next-match-card`).
- **Roster:** `Kurs_Season::get_squad($team_id, $division_id)` + `$scorer_map` (direktan `$wpdb` upit nad `match_events`, `event_type IN ('goal','own_goal')`, grupirano po igraču). Prikaz `.roster-row` s linkom na igrača i golovima.
- **Sve utakmice u diviziji:** **direktan `$wpdb` upit**, popis s rezultatima (obojano win/draw/loss) ili datumima.
- **Stat strip** (`.stat-strip`): pozicija, bodovi, P/N/I, golovi (GF:GA) — samo ako `$team_pos`.
- **Division tabs** (`.hub-tabs`) ako `count($contexts) > 1`. Boja po ligi: lokalna `ekipa_league_color($league)` (sadrži "pan" → `var(--c-pan)`, inače `var(--c-kc)`).
- **Widget "Tablica"** (side): velika pozicija, "od N", bodovi/GR, link "Puna tablica →" na `/{kc-liga|pan-ljetna}/` — slug se OVDJE bira ispravno po ligi (`str_contains(league, 'pan') ? 'pan-ljetna' : 'kc-liga'`).

> **GOTCHA (dvostruko računanje):** `Kurs_Match::get_standings($grp->id)` se poziva **dvaput za istu grupu** (jednom za petlju redova, jednom unutar `array_merge` za `'total' => count(...)`) — nepotrebno duplo računanje skupe operacije.

---

## 18. `single-kurs_igrac.php` — Profil igrača

**Guard:** isti kao ekipa (redirect na naslovnicu; `/igraci/` nema arhivu). Breadcrumb vodi na trenutnu ekipu (`kurs_team_url(team, division)`). `$player_id = get_the_ID()`. Prefiks `$p = $wpdb->prefix . KURS_PREFIX`. Gotovo SVE preko **direktnih `$wpdb` upita** (ne koristi metode plugina za glavne podatke):

- **Karijera (1.0.39):** `Kurs_Match::player_career($player_id)` — retci sezona × liga × ekipa iz **spremljenih postava utakmica** (ostaju i nakon prijelaza ili odlaska iz ekipe) + trenutni roster; nastupi = odigrane utakmice u postavi bez „nije igrao”, golovi/kartoni strogo po ligi (skupine + playoff). Napomena „isti igrač, više divizija” broji različite lige (prijelaz unutar lige se ne broji). Godinu rođenja (`_kurs_player_birthyear`) i fotografiju (istaknuta slika) upisuje admin → **Igrači**.
- **Totali karijere:** `array_sum(array_column($career, …))` za golove, utakmice, žute, crvene.
- **Hero:** trenutna ekipa (`$career[0]`, najnovija sezona), broj dresa, golovi/utakmice/kartoni.
- **Karijerna tablica** (`.career-table`): grupirano po sezoni (`$rows_buffer`) s podzbrojevima po sezoni (ako >1 redak) i ukupnim `tfoot` redom. Badge divizije obojan `igrac_color($league)`.
- **Nedavni događaji:** zaseban `$wpdb` upit (zadnjih 10 `finished` događaja igrača) → `.event-row` s linkom na utakmicu i ikonama (⚽/🟨/🟥).
- **Ekipe kroz karijeru** (side widget): jedinstvene ekipe (`$unique_teams`) ako >1.

> **GOTCHA (lokalne funkcije u single predlošcima):** `ekipa_league_color` (ekipa) te `igrac_badge`/`igrac_color` (igrač) definirane su globalno u single predlošcima. Re-deklaracija nije problem jer se single učitava jednom po zahtjevu, ali su to globalne funkcije bez zaštite imena.

---

## 19. `assets/js/main.js` — interakcije

Sve unutar jednog `DOMContentLoaded`:

1. **Mobilni meni:** klik na `#nav-toggle` toggla klasu `is-open` na `#site-nav` i ažurira `aria-expanded`. Klik izvan menija zatvara.
2. **Tražilica:** `#search-toggle` toggla `.site-header.search-open` (≤1080px panel ispod headera) i fokusira `.header-search__input`; klik izvan headera i `Escape` zatvaraju.
3. **Custom dropdowni `.hub-dd`** (izbor sezone u Liga hubu, profil ekipe…): `.hub-dd__toggle` → `is-open` + `aria-expanded`; klik izvan / `Escape` zatvaraju.
4. **Kopiranje linka** `[data-copy-link]` (Clipboard API + `kurskcFallbackCopy()` preko `execCommand('copy')`); pauza trake sponzora na dodir.
5. **NOVO 2026-10-16:** `select[data-autosubmit]` šalje svoju formu na `change` (picker „Za medije"); **„Za medije"** (samo ako postoji `.media-kit`): kartice `[data-media-tab]`/`[data-media-panel]`, kopiranje `[data-media-copy="html|text"]` (ClipboardItem `text/plain` + `text/html`, fallback), PNG `[data-media-png]` preko `window.htmlToImage.toPng()` s ugrađenim fontovima (`fontEmbedCSS`, vidi §16a); gumbi dobivaju `is-busy` / `is-done` / `is-failed`.

> **GOTCHA (nema JS-a za lige):** Svi tabovi/filteri lige (tablica/po kolima/raspored/statistika, izbor kola/divizije, division toggle) rade **server-side preko GET parametara i reload-a stranice** (`<a href>` linkovi; `.hub-dd` je samo prikaz liste linkova).

---

## 20. CSS (`assets/css/main.css`) — relevantne komponente

Stilizirano CSS varijablama u `:root`. Ključne boje: `--c-kc` (KC akcent), `--c-pan` (Pan), `--c-gold`, `--c-win`/`--c-draw`/`--c-loss`, `--c-muted`. Fontovi: `--font-cond` (Barlow Semi Condensed — naslovi), `--font-mono` (Space Mono — brojke/labele).

> **GOTCHA (nedosljedna imena boja u dokumentaciji):** `CLAUDE.md` navodi `--color-primary`/`--color-accent`, ali stvarni kod (footer, predlošci) koristi `--c-kc`/`--c-pan`/`--c-gold` itd.

Glavne komponentne klase po sekcijama:
- **Naslovnica:** `kurs-hero`, `kurs-hero__main`/`__side`, `hero-stripe`/`hero-img`/`hero-gradient`/`hero-cat`/`hero-title`/`hero-meta`, `league-card`(`--pan`), `news-grid`/`news-card`, `sec-head`; **„Iz lige"** (sekcija „NASLOVNICA — Iz lige", 2026-10-16): `home-rounds`(`--single`), `home-round`(`--kc`/`--lbb`/`--pan`, `__head`/`__icon`/`__title`/`__league`/`__meta`/`__badge`/`__list`/`__foot`/`__next`), `hr-match`(`__team`/`is-win`/`__score`/`--tbd`).
- **Liga hub:** `league-banner`(`--pan`), `hub-dd`(`--season`), `hub-tabs`/`div-toggle`/`view-tabs` (+`is-active`), `hub-tabs__end` + `hub-media` (gumb „Za medije”), `hub-body`/`hub-main`/`hub-side`, `standings` (+`__head`, `__row`, `playoff`, `standings__row--cutoff`, `st-pos`/`is-playoff`/`st-club`/`st-gd`/`st-pts`, `st-move--up/--down/--same`), `schedule`/`sched-round-label`(+`__no`)/`sched-match` (+`__home`/`__score`/`__away`/`__score--vs`), `widget`/`widget__title`/`scorer-row`/`scorer-badge`/`scorer-goals`, `disc-boxes`/`disc-box`(`--y`/`--r`), `kurs-bracket*` (playoff). **„Po kolima"** (sekcija „LIGA HUB — Po kolima"): `round-nav`(+`__arrow`/`__pills`), `round-pill`(`--done`/`--partial`/`--upcoming`/`--playoff`), `round-head`(+`__kicker`/`__title`/`__date`/`__media`), `round-sec`/`__title`, `round-results`/`__group`, `rr-match`(+`__team`/`__name`/`__score`/`--tbd`/`__scorers`/`__sc`/`__note`), `rr-bye`, `round-scorers`/`rs-row`/`rs-goals`/`rs-names`/`rs-name`, `round-note`.
- **Za medije** (sekcija „ZA MEDIJE"): `media-kit`, `media-hero*`, `media-body`, `media-picker*`, `media-layout`/`media-main`, `media-tabs`, `media-panel`, `media-flag`, `media-paper`, `media-pre`/`media-code`, `media-actions`, `media-card*`, `media-btn`(`--primary`/`--small`, `__label`/`__done`), `media-hint`, `media-howto`, `media-png-src`/`media-png-card`.
- **Responzivni ispravci (2026-10-16):** header ≤1080px (tražilica → ikona) i ≤880px (hamburger); hub ≤900px `.hub-body{align-items:stretch}`, ≤600px ljestvica `minmax(0,1fr)` (✅ ISPRAVCI #15, #16).
- **Utakmica:** `match-wrap`/`match-breadcrumb`, `semafor` (+`__stripe`/`__hatch`/`__inner`/`__team`/`__badge`/`__name`/`__center`/`__score`/`__status`/`__date`), `match-body`/`match-events`/`match-sec-title`, `kurs-event-timeline`/`tl-row`/`tl-min`/`tl-icon`/`tl-player`/`tl-team`, `match-lineups`/`lineup-cols`/`lineup-team`/`lineup-head`/`lineup-badge`/`lineup-player`/`lineup-no`.
- **Profil ekipe:** `team-profile`/`team-hero`(+`__overlay`/`__inner`/`__badge`/`__name`), `team-body`/`team-main`/`team-side`, `stat-strip`/`stat-box`, `next-match-card`, `roster-row`(+`__num`/`__name`/`__goals`).
- **Profil igrača:** `player-profile`/`player-hero`(+`__overlay`/`__inner`/`__photo`/`__info`/`__team`/`__jersey`/`__name`/`__stats`/`__stat`/`__sep`), `player-body`/`player-main`/`player-side`, `career-table`(+`career-row`/`career-row--season-start`/`--subtotal`/`career-total`/`career-season`/`career-div`/`career-badge`/`career-team`/`career-num`/`--goals`), `event-row`(+`__icon`/`__body`/`__label`/`__match`/`__date`).
- **Općenito:** `container`, `site-box`, `site-main`, `site-ticker`/`ticker-item`(`--sched`), `kurs-empty` (prazna stanja), `o-nama-wrap`/`o-nama-hero`/`o-nama-content`.

---

## 21. Zbirni popis GOTCHA-a (za brzu referencu)

1. **Verzija:** `KURSKC_VERSION='1.0.62'` (functions.php) ≠ `Version: 1.0.0` (style.css).
2. **CLAUDE.md (ranije zastario, ✅ ispravljen 2026-06-22):** spominjao je CPT `momcad`/`utakmica` i taksonomije `liga`/`sezona` koje ne postoje. Stvarno: tema = `vijest` + `vijest_kategorija`; plugin = `kurs_ekipa` + `kurs_igrac` + custom DB tablice.
3. **Mrtvi izbornici:** `register_nav_menus('primary','footer')`, ali `wp_nav_menu()` se nigdje ne poziva — sva navigacija hardkodirana.
4. **Neiskorišten custom-logo:** `add_theme_support('custom-logo')` postoji, ali `the_custom_logo()` se ne koristi — logo je hardkodirani `<img>`.
5. ✅ **ISPRAVLJENO (ISPRAVCI #7):** footer je ranije sve ligaške linkove vodio na nepostojeći `/liga/` (404); sada na `/kc-liga/` i `/pan-ljetna/`, usklađeno s headerom.
6. ✅ **Stranice kontakt/pravne** (`/kontakt/`, `/uvjeti-koristenja/`, `/privatnost/`, `/kolacici/`) se u međuvremenu kreiraju automatski (`functions.php`); social linkovi su i dalje `#`.
7. **Mrtav kod:** `template-parts/content.php` i `content-none.php` se ne pozivaju.
8. **`languages/`** direktorij ne postoji iako `load_theme_textdomain` referira na njega.
9. **Tijesna sprega tema↔plugin:** predlošci izvode direktne `$wpdb` upite nad plugin tablicama, header koristi `Kurs_Ticker`, Liga hub / naslovnica / „Za medije" `Kurs_Rounds`; `functions.php` require-a plugin `helpers.php`. Tema funkcionalno ovisi o pluginu `kurs-liga`.
10. **Idempotentni overhead:** `kurskc_create_pages()` i "O KURS-u" init hook izvršavaju se na svakom `init` loadu (uz provjeru postojanja).
11. ✅ **Playoff bracket se prikazuje** (2026-10-16): tab Tablica ispod ljestvica + „Playoff" u tabu Po kolima (`kurs_render_playoff_bracket()`).
12. ✅ **Breadcrumb utakmice (ISPRAVCI #9):** `page-utakmica.php` je uvijek vodio na `/kc-liga/` (krivo za Pan) i nije radio za playoff mečeve; sada `kurs_league_url()` + `Kurs_Playoff::find_by_match_id()`. Pravilo: URL lige iz divizije uvijek preko `kurs_league_url()`.
13. **Dvostruko `get_standings()`** za istu grupu u `single-kurs_ekipa.php` (`$team_pos`/`total`).
14. **Globalne funkcije bez prefiks-zaštite:** `ekipa_league_color` (ekipa), `igrac_badge`/`igrac_color` (igrač), `kurs_hub_url` (`page-liga.php`). (Badge vijesti su u međuvremenu postali `kurskc_badge_color/label` u `inc/news.php`.)
15. **Decentraliziran dohvat:** miješanje metoda plugina i ad-hoc `$wpdb` SQL-a u temi otežava održavanje (promjena sheme = izmjene na više mjesta). Novi kod za kola koristi `Kurs_Rounds` (plugin) — raspored i disciplina u `page-liga.php` i dalje su ad-hoc SQL.
16. **Pretpostavka postojanja stranica/dodjele templatea:** front-end linkovi pretpostavljaju da postoje stranice sa slugovima `kc-liga`, `pan-ljetna`, `vijesti`, `ekipe`, `igraci`, `utakmica`, `za-medije`, `o-kurs-u` i da im je dodijeljen odgovarajući Template Name — inače 404.
17. **CSS varijable boja:** stvarna imena su `--c-kc`/`--c-pan`/`--c-gold`/`--c-win`/`--c-draw`/`--c-loss`/`--c-muted` (ne `--color-primary`/`--color-accent` iz CLAUDE.md).
18. **„Za medije" HTML mora preživjeti kses:** inline stilovi + tablice, hex boje, apsolutni URL-ovi, jedan red, `font-family` **bez navodnika** — ne dodavati `<style>`, `rgba()`, SVG ni komentare u `Kurskc_Media_Kit::html()` (vidi §16a).
19. **PNG izvoz:** koristi html-to-image 1.11.11 (cdnjs) s ugrađenim latin/latin-ext fontovima; **ne vraćati html2canvas** (krivo crta Barlow).
20. **„Po kolima" ovisi o `matches.round`** — krivo kolo kvari ljestvicu nakon kola i „slobodnu" ekipu (admin upozorava `.kurs-round-warn`).


## Izmjene 1.0.47 (2026-11-12)

- **Blok „KURS — iz lige” (`kurs/liga`, `inc/embed-block.php` + `assets/js/embed-block.js`)** — zamjena za nejasan odabir „Ugradi iz lige” u bočnom stupcu (izbornici su bili odrezani, a ugradnja se vidjela tek nakon objave). Dinamički blok (bez build koraka, `wp.*` globali): atribut `value` u istom zapisu kao `_kurs_embed` (`round|table|results:DIV:KOLO`, `live|schedule|bracket:DIV`), render = `kurskc_embed_render()` u `<div class="post-embed post-embed--block">`. U uređivaču: odabir lige (najnovija sezona prva — `KURSKC_EMBED.order`) i prikaza u samom bloku + **pregled u `<iframe srcdoc>` s `main.css`** (REST `GET kurskc/v1/embed?value=` samo za `edit_posts`). Kategorija blokova „KURS”. Radi i u Pretpregledu jer je dio sadržaja. Metabox „KURS — opcije objave”: gumb „+ Dodaj blok „Iz lige”” (`wp.data.dispatch('core/block-editor').insertBlocks`), a stara ugradnja ispod teksta (`_kurs_embed`) prikazuje se s „Pretvori u blok” / „Ukloni” (i dalje se renderira ispod teksta dok se ne ukloni). Shortcode `[kurs_kolo]` i dalje radi.
- **Poveznice na igrače i ekipe** (plugin helperi `kurs_team_link()` / `kurs_player_link()`): Po kolima (ekipe + strijelci), lista strijelaca kola, raspored, bracket, „Iz lige” na naslovnici (+ „Vodi:”), statistika i Zlatna kopačka (ekipa), semafor i postave na stranici utakmice, tijek utakmice (igrač), sljedeća utakmica na profilu ekipe. CSS `.kurs-tlink/.kurs-plink` (boja okoline, podcrtano na hover) i uzorak `.has-row-link` + `.kc-row-link` (rastegnut link retka ispod, imena iznad, `z-index`). Retci `rr-match`, `sched-match`, `hr-match`, `kurs-bracket__match` su sada `<div>` (prije `<a>`) — nema ugniježđenih linkova.
- **Odabir lige na stranici lige** (`page-liga.php`, `.league-pick`): kad sezona ima više liga (KC Liga / Liga bez briga), ispod bannera su dvije velike kartice „Odaberi ligu” (grb, naziv, broj ekipa, „prikazano” / „Prikaži ovu ligu →”, kvačica u boji lige) umjesto malog prekidača; u traci kartica `.hub-tabs__league` pokazuje odabranu ligu. **Izravne poveznice:** padajući izbornik „KC Liga” u zaglavlju (odjeljak „Lige”) i podnožje (Natjecanja) vode na obje lige aktualne sezone — `kurskc_league_divisions( $is_pan )` u `inc/nav-menus.php`.
- **Potpis izrade u podnožju:** `.site-footer__credit` — „Web izradio [ikona] webdot (ikona = Webdotov „O” znak `assets/img/webdot-o.png`, preuzet s webdot.hr, unutrašnjost popunjena bijelom da se vidi na tamnom; tema 1.0.49) — domaći web iz Koprivnice · asistencija za svaki gol” → https://webdot.hr/ (`rel="noopener"`, bez nofollow).

## Izmjene 1.0.48 (2026-11-12) — „Obje lige”

- Korisnik: u rasporedu samo KC lige izgleda kao da utakmice traju 1 h 20 min (lige se igraju naizmjence iste večeri). **Raspored i Po kolima zadano prikazuju obje lige zajedno** (`$all_mode` u `page-liga.php`: sezona s više liga + tab `raspored|kola` + URL bez `division_id` ili `division_id=0`), kronološki, s oznakom lige `kurs_division_chip()` (KC crveno / LBB zlatno). **Tablica i Statistika ostaju po ligi.**
- Kartice „Odaberi ligu”: na Rasporedu i Po kolima prva je **„Obje lige”** (18 ekipa, „Cijela večer →”), uz KC Liga / Liga bez briga za suženi prikaz. Kartice prikaza „Raspored” i „Po kolima” uvijek vode na obje lige (`$tab_div`), Tablica/Statistika zadržavaju odabranu ligu.
- Po kolima (obje lige): jedna kronološka lista rezultata (+ slobodne ekipe obiju liga), zatim za svaku ligu „Ljestvica nakon N. kola — liga” i „Strijelci nakon N. kola — liga”; bočni stupac (Zlatna kopačka, Disciplina) za svaku ligu s oznakom.
- Plugin helperi: `kurs_render_schedule()` i `kurs_render_round_results()` primaju **ID lige ili niz ID-eva** (stari pozivi s jednim ID-em rade isto); `kurs_division_badge()` / `kurs_division_chip()`.

## Izmjene 1.0.50 (2026-11-12) — ticker

- Korisnik: ticker „sve sivo, ne kuži se što je što” i prebrz. Sada: **ekipe bijele i podebljane**, oznaka lige kao obojena pločica (**KC** crveno, **LBB** zlatno, **LJ** narančasto — `Kurs_Ticker::league_short()` sada razlikuje Ligu bez briga po nazivu divizije), **sat zlatnom bojom** ispred para, rezultat kao bijela pločica, točka između utakmica. Kad su sve najave isti dan, dan piše jednom u oznaci („Najava | sub 14.11.”), inače uz sat. Petlja sporija: `max(40, broj_stavki × 8)` s (prije `max(24, × 5)`).
- Plugin `Kurs_Ticker` (1.29.0): stavke imaju `tone`, `home_id`/`away_id`, najava `day` + `time` odvojeno; transient `kurs_ticker_state_v2`.

## Izmjene 1.0.51 (2026-11-12) — ugradnja = kartica „Za medije”

- Korisnik: blok „KURS — iz lige” neka izgleda **identično paketu „Za medije”**. `kurskc_embed_render()` za `round` / `results` / `table` / `live` / `bracket` sada vraća **istu karticu** kao `/za-medije/` (`kurskc_embed_media_card()` → `Kurskc_Media_Kit::html()`, inline stilovi, KURS zaglavlje, trobojna crta, „Izvor: KURS” podnožje) u `<div class="post-embed__card">`. Vrijedi za blok, stari `_kurs_embed` i shortcode `[kurs_kolo]`. Raspored (`schedule`) i stari `standings:SKUPINA` ostaju klasični prikaz.
- `Kurskc_Media_Kit::html( $datas, $round, $competition, $season, $opts )` — novi `$opts['parts']` (`results` / `table` / `scorers`) i `$round === 'live'` (+ `live_data()` = aktualna ljestvica); `/za-medije/` zove bez `$opts` (nepromijenjeno). U kartici nema poveznica na igrače/ekipe (namjerno — isto što dobivaju portali).

## Izmjene 1.0.52 (2026-11-12) — centar utakmice + ticker na mobitelu

- **`page-utakmica.php`** (korisnik: „stranica s rezultatom nedorečena”) — klasa `.mc`:
  - **Semafor:** kicker (sezona · liga · kolo, grb lige), ispod imena ekipa **strijelci** (prezime + minute / broj golova, (p), (ag); poveznice na igrače) i **isključeni** (crveni karton), u sredini **poluvrijeme** (kad događaji imaju `half`), status (Završeno / Najava) i termin (dan, datum + sat zlatno; 00:00 se ne prikazuje).
  - **Tijek utakmice** dvostrano: domaćin lijevo, gost desno, u sredini minuta i **međurezultat** nakon svakog gola (samo kad postoje minute — inače pravi redoslijed nije poznat), separatori „1./2. poluvrijeme”, „Poluvrijeme X:Y”, „Kraj X:Y”; autogol ide na stranu protivnika. Prije utakmice: poruka s terminom.
  - **Statistika** (trake domaćin/gost): golovi, žuti, crveni, prekršaji po poluvremenu (`event_type='foul'`) — prikazuju se samo retci s vrijednošću.
  - **Sastavi:** broj dresa samo ako ga netko ima, oznake G (golman), C (kapetan utakmice — `Kurs_Match::get_match_captain`), ikone gol/autogol/žuti/crveni; trener i voditelj **bez telefona** (prije su se javno ispisivali brojevi).
  - **Bočni stupac:** O utakmici (natjecanje, kolo/skupina, termin, suci `referee1/2`, status), **Ostale utakmice večeri** (sve lige sezone u istom kolu, oznaka KC/LBB, trenutna istaknuta), **Ljestvica** skupine s istaknute dvije ekipe, **Međusobni susreti** (sve sezone, zadnjih 6 + omjer pobjeda/neriješeno).
- **Ticker na mobitelu (≤700px):** oznaka uska — riječ i dan jedno ispod drugog, manji razmaci, više mjesta za utakmice.

## Izmjene 1.0.53 (2026-11-12)

- **Profil ekipe → „Puna tablica”** vodi na tablicu **odabrane** sezone (`kurs_league_url( $division_id )` sa `season_id`), ne na aktualnu.
- **Logotipi ekipa** (`kurs_team_badge_html`, plugin): logo je bijela zaobljena pločica s cijelim logom (`object-fit:contain`, tanki obrub) — prije krug s `cover` unutar tamnog okvira `.team-badge` (26 px) → „krivo izrezani” rubovi. `.team-badge` više nema tamnu podlogu ni rezanje. Inicijali svijetle boje ekipe dobiju tanki obrub.
- **„Iz lige” widget** (`inc/league-widget.php`, `kurskc_league_widget()`, keš 10 min, briše se na `kurs_data_changed`) mijenja se s fazom natjecanja (najbliže po datumu, `kurskc_active_league`): **pre** (odbrojavanje + parovi 1. kola) · **today** (današnje utakmice s rezultatima, pulsirajuća točka) · **mid** (sljedeće kolo + vrh tablice svake lige s jednom skupinom) · **post** (prvaci: playoff pobjednik ili prvi na ljestvici). Zamjenjuje „Vrh tablice · KC” i „Iz lige · uživo” u bočnom stupcu vijesti i na `/vijesti/` (stara funkcija je pokazivala LBB s oznakom „KC” i nule prije sezone).
- **Društvene mreže** (`inc/social.php`): Izgled → Prilagodi → **„KURS — društvene mreže”** (Facebook zadano = stranica KC lige, Instagram/YouTube/TikTok prazno = skriveno). Prikaz: podnožje (ikone iz postavki umjesto `href="#"`), **„Pratite KC ligu”** kartica u bočnom stupcu vijesti i stranice lige te traka na kraju članka. `kurskc_icon()` = SVG ikone.
- **Dijeljenje članka** (`kurskc_share_html( $id, 'compact'|'full' )`): u zaglavlju ikone, ispod članka obojeni gumbi — **Podijeli** (Web Share API, samo gdje preglednik podržava — mobitel), Facebook, WhatsApp, Viber, Telegram, X, e-mail, kopiraj link.
- **Open Graph / Twitter kartica** u `<head>` (ako nema Yoast/Rank Math/SEOPress): naslov, sažetak, istaknuta slika ili **`assets/img/og-default.png`** (1200×630, grb KC lige) — link na Facebooku/WhatsAppu ima naslov i sliku.

## Izmjene 1.0.54 (2026-11-12)

- **Font oznaka:** `--font-mono` više nije Space Mono („previše informatički”) nego **Barlow** (ista obitelj kao ostatak teme); brojke u vremenima/rezultatima `tabular-nums`. Space Mono se više ne učitava na webu (Google Fonts URL u `inc/enqueue.php` i `inc/embed-block.php`); i paket „Za medije” (`Kurskc_Media_Kit::F_MONO`) koristi Barlow. Admin, `/zapisnik/` i `/presjek/` i dalje imaju Space Mono.
- **Profil ekipe — zaglavlje:** umjesto oznake za svaku sezonu (raslo bi s godinama) **jedna oznaka po natjecanju**: „KC liga · 2 sezone · od 2025.” / „Pan Ljetna liga · 1 sezona · od 2026.” (`ekipa_sezona()`, `ekipa_ctx_label()`). Izbornik „Liga / natjecanje” grupiran po natjecanju (`.hub-dd__grp`), kraći nazivi („KC Liga 2026/2027” umjesto „KC Liga · KC Liga 2026/2027”).
- **Javni zapisnik:** `/utakmica/?match_id=X&zapisnik=1` — samo **završene** utakmice → isti A4 zapisnik kao u adminu (`admin/views/match-record.php` PDF grana, `$GLOBALS['kurs_record_public']`), s „Ispiši” / „Preuzmi PDF” / „← Natrag na utakmicu”; prazni obrazac javno nije dostupan; `noindex`. Gumb „Zapisnik utakmice · ispis · PDF” u bočnom stupcu centra utakmice. U PDF-u se rezultat poluvremena prikazuje samo kad su poluvremena upisana (inače `___ : ___`).
- **Vodoravno pomicanje na mobitelu** (`main.js`): `.career-table-wrap`, `.pf-table-wrap`, `.kurs-bracket`, `.media-paper`, `.view-tabs`, `.round-nav__pills` dobiju zatamnjen rub (`.xs-left/.xs-right`, CSS mask) gdje ima još sadržaja, a iznad tablica žuta oznaka „↔ Povuci lijevo-desno za cijelu tablicu” dok se prvi put ne pomakne.
- **Ticker na mobitelu:** manje praznog prostora u oznaci, veći tekst („NAJAVA” 12,5 px, dan 11,5 px).

## Izmjene 1.0.55 (2026-11-12)

- **Blok „KURS — iz lige” — „Obje lige”:** za sezonu s više liga izbornik nudi i „KC Liga 2026/2027 — Obje lige” (ključ `3+4`). Zapis u `value` sadrži ID-eve spojene s `+` (`round:3+4:1`, `results|table:…`, `live:3+4`, `schedule:3+4`); `kurskc_embed_parse()` vraća i `division_ids`. Kartica je ista kao paket „Za medije” s obje lige (`Kurskc_Media_Kit::html( [ d1, d2 ], … )`, naslov „KC liga · N. kolo”); raspored obje lige = `kurs_render_schedule( [ids] )`. Paket na `/za-medije/` je već bio objedinjen (obje lige + PNG po ligi).
- **Naslovnica — nove sekcije** ispod „Najnovije” (`inc/home-extra.php`, `kurskc_home_extra()`, keš 10 min, `kurs_data_changed`): **Liga u brojkama** (N. sezona = godina − 2011, ekipe, prijavljeni igrači, odigrane utakmice i golovi u arhivi), **Ekipe sezone** (grbovi obje lige → profili), **Zlatna kopačka** (top 5 po ligi aktualne sezone; dok nema golova — prošla sezona s oznakom), **Aktualni prvaci** (zadnja zaključana sezona svakog natjecanja: playoff pobjednik ili prvi na ljestvici), **Pratite KC ligu** + **Gdje igramo** (dvorana, karta).

## Izmjene 1.0.56 (2026-11-12)

- **Pretraga** (`inc/search.php` + `search.php`): rezultati grupirani — **Ekipe** (grb), **Igrači** (zadnja ekipa, `kurskc_player_last_team()`), **Vijesti** (slika, kategorija, datum, isječak oko pogotka), **Stranice**; kartice filtera `?tip=ekipe|igraci|vijesti|stranice` s brojem rezultata; pojam istaknut `<mark>` bez obzira na kvačice (`kurskc_search_regex()`: c↔č↔ć, s↔š, z↔ž, d↔đ — baza je `utf8mb4_unicode_520_ci` pa i LIKE ignorira kvačice); prazno stanje s prijedlozima. Glavni upit pretrage = samo vijesti, 12 po stranici (`pre_get_posts`).
- **Prijedlozi dok se tipka:** REST `GET kurskc/v1/search?q=` (javno; ekipe 4, igrači 5, vijesti 4) → padajući izbornik ispod tražilice u zaglavlju i u mobilnom izborniku (`form[data-suggest]`, `main.js`; strelice ↑↓, Esc, „Svi rezultati →”). `.header-search` više nema `overflow:hidden`.
- **Mobilni izbornik (≤880px):** tamni panel preko cijelog ekrana — gore KURS trake + bijeli logo i X (hamburger se pretvara u X, `position:fixed` u redu s logom), tražilica s prijedlozima, lige kao tamne kartice s obojenim rubom (KC crveno, Pan narančasto) i podlinkovima u dva stupca (lige + Tablica/Po kolima/Raspored/Statistika), O nama / Kontakt, dolje mreže. Pozadina se ne pomiče (`body.nav-locked`), Esc zatvara. Mobilni dijelovi su u `header.php` kao `.site-nav__mob` (skriveni na desktopu).
- **Naslovnica „Iz lige”:** veći font ekipa (15,5 px) i sitnog teksta (kolo/datum 12,5 px, podnožje 14 px); dugi nazivi se lome u dva reda (iza „/” preko `<wbr>`) umjesto „…”.
- **Profil ekipe:** **Roster** i **Utakmice sezone** kao kartice s tamnim zaglavljem (`.tm-card`) — roster: broj dresa ili inicijali u boji ekipe, GLM / C (`_kurs_team_captain`), golovi; utakmice: kolo · termin · protivnik (doma / u gostima, grb, link) · P/N/I s rezultatom ili sat početka, obojeni lijevi rub; zaglavlje s omjerom P·N·I.

## Izmjene 1.0.57 (2026-11-12)

- **Zapisnik (PDF, plugin `match-record-pdf.php`):** u zaglavlju pravi logo KURS-a (`KURS-clean-ink.png` iz teme; bez teme ostaju trake + natpis) i grb natjecanja (`kurs_league_icon`, KC grb / Pan ikona) — i u adminu, /zapisnik/ i javnom ispisu.
- **Ikona gola = nogometna lopta** (SVG data-URI u `main.css`, `#` mora biti `%23`): tijek utakmice, postave (autogol s crvenim rubom), semafor, roster na profilu ekipe.
- **/vijesti/:** izdvojena vijest i desni stupac jednake visine — slika izdvojene vijesti raste (`.kc-lead-row .kc-feature__img { flex:1 }`), na mobitelu 16:9; widget lige u tom stupcu je sažet (`kurskc_league_widget( true )` — najviše 4 utakmice + „još N”).
- **Kartica „Za medije” / ugradnja u vijest — poveznice:** rezultat ili sat → `/utakmica/?match_id=`, ekipe → profil ekipe, strijelci → profil igrača, ekipe u ljestvici → profil (`Kurskc_Media_Kit::a()`, inline stil `color:inherit;text-decoration:none` da ih portal ne preboji). Vrijedi za HTML paket koji portali kopiraju i za ugradnju na našem webu (PNG i tekst nemaju poveznice). `division_data()` / `live_data()` vraćaju i `did`.
- **Dijeljenje ispod članka:** 8 gumba u mreži 4×2 (na mobitelu ikona iznad naziva); bez Web Share API-ja „Kopiraj link” zauzme dva mjesta.

## Izmjene 1.0.58 (2026-11-12) — Facebook stranice

- Dvije službene Facebook stranice: **KC liga** `https://www.facebook.com/profile.php?id=100054493718852` i **Pan Ljetna liga** `https://www.facebook.com/panljetnaligakoprivnica/`. U `inc/social.php` ključevi `facebook` i `facebook_pan` (Customizer „KURS — društvene mreže”), `kurskc_social_links()` vraća i `icon`; ikone mreža sada imaju i naziv (podnožje, mobilni izbornik), „Pratite nas” kartica ima oba gumba (Pan narančasto).
- Stranica **Kontakt**: gumbi „Pan Ljetna liga Koprivnica” / „Zimska KC Liga” vode na prave stranice (predložak u `functions.php` + lokalna baza). Vijesti #115 i #121: Facebookovi preusmjeravajući linkovi (`l.facebook.com/l.php?u=…&fbclid…`) zamijenjeni izravnim `https://klikaj.hr/` (backup `backups/local-prije-fb-linkova-20261112.sql`).

## Izmjene 1.0.59 (2026-11-12)

- **Profil ekipe:** traka „Prikazano” je uvijek vidljiva — izbornik kad ekipa ima više liga/sezona, inače fiksna oznaka (`.hub-dd__static`); uz nju **„Aktualna sezona”** ili **„Arhiva · završena sezona”** (uspoređuje s `Kurs_Season::current_for_league()`), a ako ekipa ne igra u aktualnoj sezoni: „Ekipa ne nastupa u sezoni …”. Kartica utakmica: „Utakmice · Liga bez briga · KC Liga 2025/2026”.
- **/vijesti/ — izdvojena vijest:** slika uvijek **16:9** (fotografije se ne režu ni ne razvlače); desni stupac = sažeti widget lige + „Pratite nas”, a **„Najčitanije” je vodoravna traka** ispod (`.kc-mostread-row`, 3 stupca; na mobitelu lista).

## Izmjene 1.0.60 (2026-11-12)
- **Profil ekipe → Utakmice:** playoff runda više ne ispisuje puni naziv („Četvrtfinale”) u uskom stupcu kola. Plugin helper
  `kurs_round_short($round, $po_round)` vraća `[veliko, malo]`: `5.`/kolo, `1/4`/finale, `1/2`/finale, pehar (SVG)/finale, `3.`/mjesto.
  Playoff oznaka je zlatna pločica (finale tamna sa zlatnim), puni naziv u `title`; prije prvog playoff meča razdjelnik `.tm-sep` „Doigravanje”.
- Lakši rez u popisima profila ekipe (imena 600, brojke 700).

## Izmjene 1.0.61 (2026-11-12)
- **Profil ekipe u diviziji s doigravanjem (Pan):** gornje kartice više ne pokazuju samo grupnu ljestvicu.
  **Plasman** iz bracketa (`Kurs_Playoff::winner_side`, penali uključeni): Prvak / Finalist / 3. ili 4. mjesto /
  Polufinale / Četvrtfinale / Skupina (samo kad su svi parovi prvog kola doigravanja popunjeni); zatim pozicija i bodovi
  „u skupini X”, te **P/N/I i golovi svih utakmica** (skupina + doigravanje). KC (bez playoffa) ostaje Pozicija/Bodova/P-N-I/Golovi.
- „Statistika lige” → „Statistika sezone · skupina + doigravanje” kad postoji playoff; „Forma (zadnje 4 utakmice)” — ispravan padež.
- Playoff utakmice u popisu: ispod rezultata „prošli/ispali · pen. 4:5” (iz perspektive ekipe).

## Izmjene 1.0.62 (2026-11-12)
- **Za medije / blok `kurs/liga` — doigravanje:** ispod rezultata playoff meča koji je završio neriješeno piše
  „pen. 4:5” (`Kurs_Media_Kit::match_rows()` novi argument `$sub`). Prije se bilješka s penalima prikazivala samo za
  neodigrane mečeve (umjesto rezultata) pa je kod odigranih nestajala. Tekstualna verzija ih je već imala („(pen. 4:5)”).
