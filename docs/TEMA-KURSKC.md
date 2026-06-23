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
- **Front-end filteri/tabovi lige rade server-side** preko GET parametara i reload-a stranice — JS pokriva samo mobilni meni i search overlay.

---

## 1. `functions.php`

Putanja: `themes/kurskc/functions.php`

### 1.1 Konstante
| Konstanta | Vrijednost | Izvor |
|-----------|-----------|-------|
| `KURSKC_VERSION` | `'1.0.4'` | hardkodirano |
| `KURSKC_DIR` | `get_template_directory()` | apsolutni FS put do teme |
| `KURSKC_URI` | `get_template_directory_uri()` | URL do teme |

> **GOTCHA (verzija):** `KURSKC_VERSION = '1.0.4'` u `functions.php` ne poklapa se sa `Version: 1.0.0` u `style.css`.

### 1.2 Uključene datoteke (`require_once`)
1. `inc/post-types.php`
2. `inc/enqueue.php`
3. `inc/nav-menus.php`
4. `WP_PLUGIN_DIR . '/kurs-liga/includes/helpers.php'` — **uvjetno**: samo ako `! function_exists('kurs_team_badge_html')` i `file_exists(...)`. Tako tema posuđuje plugin helpere (`kurs_team_badge_html()`, `kurs_team_color()`, `kurs_team_text_color()`). Ovo je čvor sprege teme i plugina.

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

1. `<!DOCTYPE html>` + `<head>`: charset, viewport, `preconnect` na Google Fonts, favicon `assets/img/favicon.svg`, `wp_head()`.
2. `<body body_class()>` + `wp_body_open()`.
3. **Ticker rezultata** — renderira se SAMO ako `class_exists('Kurs_Match')`. Direktan `$wpdb` upit (prefix `{$wpdb->prefix}kurs_`) koji JOIN-a:
   `kurs_matches m` → `kurs_groups g` → `kurs_phases ph` → `kurs_divisions d`, plus `wp_posts` dvaput (`h` = domaćin, `a` = gost). Filtar: `m.status='finished'` ILI `match_date >= DATE_SUB(NOW(), INTERVAL 3 DAY)`, `ORDER BY m.match_date DESC LIMIT 6`. Ispisuje `.site-ticker` s rezultatima (`home_score–away_score`) ili vremenom (`date_i18n('j.n. H:i')`); `.lg` prikazuje prva 2 znaka naziva divizije.
4. **`<header class="site-header">`**:
   - Logo: hardkodirani `<img>` `assets/img/KURS-clean-ink.png` (120×26), link na home.
   - **Hardkodirana navigacija** `<nav class="site-nav" id="site-nav">`:
     - Vijesti → `/vijesti/` (`aria-current` ako `is_post_type_archive('vijest')` ili `is_singular('vijest')`)
     - KC Liga → `/kc-liga/` (klase `nav-league nav-kc`, `aria-current` ako `is_page('kc-liga')`)
     - Pan Ljetna liga → `/pan-ljetna/` (klase `nav-league nav-pan`, `aria-current` ako `is_page('pan-ljetna')`)
     - O nama → `/o-kurs-u/` (`aria-current` ako `is_page('o-kurs-u')`)
     - Kontakt → `/kontakt/` (`aria-current` ako `is_page('kontakt')`)
   - Gumbi `.search-toggle` (#search-toggle) i `.nav-toggle` (#nav-toggle, hamburger s 3 `<span>`).
5. **Search overlay** `.search-overlay` (#search-overlay, `hidden`) — forma `method=get action=home_url('/')`, `name="s"`, `#search-input`, placeholder "Pretraži vijesti, ekipe, igrače…".
6. Otvara `<div class="site-box"><main id="main" class="site-main">` (zatvara `footer.php`).

> **GOTCHA (ruta /kontakt/):** Header linka na `/kontakt/`, ali tema NE kreira tu stranicu automatski (kreira samo `kc-liga`, `pan-ljetna`, `utakmica`, `o-kurs-u`) → potencijalni 404.

---

## 7. `footer.php`

1. Zatvara `</main></div>` (`.site-box`).
2. **`<footer class="site-footer">`**:
   - `.site-footer__stripes` — tri pruge: `--c-kc`, `--c-pan`, `--c-gold`.
   - `.site-footer__inner`, 5 stupaca:
     1. **Brend** — logo `assets/img/KURS-clean.png` (80×28), tagline, popis dviju liga (točkice `--c-kc`/`--c-pan`).
     2. **Portal** — Vijesti (`/vijesti/`), Tablice (`/kc-liga/?tab=tablica`), Raspored kola i Rezultati (`/kc-liga/?tab=raspored`).
     3. **Natjecanja** — KC Liga (`/kc-liga/`) i Pan Ljetna liga (`/pan-ljetna/`).
     4. **Udruga** — O KURS-u (`/o-kurs-u/`), Kontakt (`/kontakt/`).
     5. **Pratite nas** — inline SVG ikone (Facebook, Instagram, YouTube), svi `href="#"` (placeholderi).
   - `.site-footer__bottom` — copyright "Koprivnička udruga za rekreativni sport · © `date('Y')` KURS" + pravni linkovi: Uvjeti (`/uvjeti-koristenja/`), Privatnost (`/privatnost/`), Kolačići (`/kolacici/`).
3. `wp_footer()`.

> ✅ **ISPRAVLJENO (2026-06-22, ISPRAVCI #7):** footer je ranije sve ligaške linkove (Tablice, Raspored, Rezultati, KC, Pan) vodio na `/liga/` — stranicu koja se **ne kreira automatski** → 404. Sada vode na `/kc-liga/` (s `?tab=tablica`/`?tab=raspored`) i `/pan-ljetna/`, usklađeno s headerom.
> **GOTCHA (nepostojeće stranice):** `/kontakt/`, `/uvjeti-koristenja/`, `/privatnost/`, `/kolacici/` se ne kreiraju automatski. Social linkovi su `#` placeholderi.

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
| Stranica "O KURS-u" | `page-o-nama.php` (Template Name "O nama") |
| Pretraga (`?s=`) | `search.php` |
| 404 | `404.php` |

Nedostaju (svjesno): `home.php`, `archive.php`, `taxonomy.php`, `category.php`, `tag.php`, `author.php`, `date.php`. Sve arhive padaju na `index.php`.

> **Bitno:** Page Template datoteke (`page-liga.php`, `page-utakmica.php`, `page-o-nama.php`) vežu se uz stranicu preko **Template Name headera**, NE preko sluga datoteke. `functions.php` dodjeljuje template meta automatski stvorenim stranicama.

---

## 10. Asseti (`assets/img/`)

| Datoteka | Korištenje |
|----------|-----------|
| `KURS-clean-ink.png` | logo u headeru (`site-logo`) |
| `KURS-clean.png` | logo u footeru (`site-footer__logo`) |
| `KURS-monogram.png` | ikona liga kartica (front-page), banner ikona (`page-liga.php`) |
| `KURS-KC-boje.png` | nije referencirano u pregledanim predlošcima |
| `favicon.svg` | favicon u `<head>` |

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
| `Kurs_Season::get_squad($team_id, $division_id)` | sastav: redovi `squads` + `player_name`, sort po dresu pa imenu |
| `Kurs_Season::get_team_contexts($team_id)` | konteksti ekipe: `season_id, season_name, league, division_id, division_name` |
| `Kurs_Match::get($match_id)` | red `matches` + `home_name`, `away_name` |
| `Kurs_Match::get_events($match_id)` | `match_events` + `player_name`, sort po minuti |
| `Kurs_Match::get_standings($group_id)` | poredak grupe (vidi 12.1) |
| `Kurs_Match::get_top_scorers($division_id, $limit)` | strijelci (objekti `player_id, player_name, team_name, team_id, goals`) |

### Helperi za prikaz
| Funkcija | Datoteka | Uloga |
|----------|----------|-------|
| `kurs_team_badge_html($team_id, $size, …)` | plugin `includes/helpers.php` | kružni badge: thumbnail ekipe ili dvoslovni inicijali na boji ekipe |
| `kurs_team_color($team_id)` | plugin `includes/helpers.php` | meta `_kurs_team_color` ili default `#1a3a6b` |
| `kurs_team_text_color($hex)` | plugin `includes/helpers.php` | WCAG luminancija → bijeli/crni tekst |
| `kurs_time_ago($date_str)` | tema `functions.php` | hrvatski relativni datum |
| `kurs_badge_color/label($post_id)` | tema `front-page.php` (lokalne) | boja/oznaka vijesti po `vijest_kategorija` |

---

## 12. Mapiranje stranica na slugove (front-end)

| Datoteka | Template Name | Stranica (slug) | URL |
|----------|---------------|-----------------|-----|
| `page-liga.php` | `Liga Hub` | `kc-liga` **i** `pan-ljetna` (ista datoteka, dvije stranice) | `/kc-liga/`, `/pan-ljetna/` |
| `page-utakmica.php` | `Centar utakmice` | `utakmica` | `/utakmica/?match_id=X` |
| `page-o-nama.php` | `O nama` | `o-kurs-u` | `/o-kurs-u/` |

CPT single predlošci (slug iz `plugins/kurs-liga/includes/class-cpt.php`):
| CPT | rewrite slug | Single predložak | Arhiva |
|-----|-------------|------------------|--------|
| `kurs_ekipa` | `ekipe` | `single-kurs_ekipa.php` | `/ekipe/` |
| `kurs_igrac` | `igraci` | `single-kurs_igrac.php` | `/igraci/` |

Razlikovanje KC vs Pan u `page-liga.php` ide po **slugu stranice**: `$current_slug = get_post_field('post_name', get_the_ID())`, pa `$is_pan = ($current_slug === 'pan-ljetna')`.

---

## 13. `front-page.php` — Naslovnica

Ne dohvaća ligaške tablice; fokus: vijesti + ulaz u lige.

- **Vijesti:** `get_posts(['post_type' => ['vijest','post'], 'numberposts' => 4, …])`. Prva je hero (`.kurs-hero__main`), ostale 3 u `.news-grid > .news-card`. Ako nema vijesti → 3 placeholder kartice.
- **Badge vijesti:** lokalne funkcije (definirane u ovoj datoteci):
  - `kurs_badge_color($post_id)` — čita taksonomiju **`vijest_kategorija`** (ne `liga`!). Slug sadrži `pan`/`ljetn` → `#F47B16`; sadrži `kc` → `#DA0F1E`; inače `#111317`.
  - `kurs_badge_label($post_id)` — naziv prvog terma `vijest_kategorija` ili "Vijest".
- **Liga kartice** (`.kurs-hero__side`): dvije statične kartice — KC Liga ("Zima · Dvorana") i Pan Ljetna ("Ljeto · Trava"), ikona `KURS-monogram.png`. Linkovi **hardkodirani** na `home_url('/kc-liga/')` i `home_url('/pan-ljetna/')`.
- **Datum hero vijesti:** `date_i18n('j.n.Y.', strtotime($hero_post->post_date))`; datum grid kartica: `kurs_time_ago($p->post_date)`.
- **Partneri:** prazna placeholder traka ("Vaš logo ovdje").

> **GOTCHA (globalne funkcije bez prefiksa):** `kurs_badge_color`/`kurs_badge_label` definirane su globalno bez zaštite imena — mogući konflikt ako se isto ime pojavi drugdje.

---

## 14. `page-liga.php` — Liga Hub (najvažniji ekran)

**Guard na vrhu:** ako `! class_exists('Kurs_Season')` → ispiše "KURS Liga plugin nije aktivan.", `get_footer()` i `return`.

Kontekst se bira ISKLJUČIVO preko **GET parametara** (nema ACF/meta):
- `?season_id=X` — sezona (`absint`). Default: prva iz `Kurs_Season::all()`.
- `?division_id=Y` — divizija. Default: prva iz `Kurs_Season::get_divisions($season_id)`.
- `?tab=tablica|raspored|statistika` — prikaz (default `tablica`; whitelist `in_array`).

URL-ovi za prebacivanje grade se helperom **`kurs_hub_url($base, $sid, $did, $tab)`** (`add_query_arg`). Sezona se mijenja `<select class="season-select" onchange="location.href=this.value">`; divizija (`.div-toggle`) i view tabovi (`.view-tabs`) su `<a>` linkovi s klasom `is-active`.

Branding po ligi (po `$is_pan`): `$league_label`, `$league_sub`, `$accent` (`var(--c-pan)`/`var(--c-kc)`), CSS klase `league-hub--pan` / `league-banner--pan`. Banner ikona `KURS-monogram.png`.

### 14.1 Tab "tablica" (poredak)
Za svaku fazu (`Kurs_Season::get_phases($division_id)`) i svaku grupu (`Kurs_Season::get_groups($phase_id)`) zove `Kurs_Match::get_standings($grp->id)`. Naslov "Faza · Grupa" se ispisuje samo ako `count($groups) > 1 || count($phases) > 1`.

`get_standings()` vraća **niz asocijativnih nizova** (već sortiran) sa stupcima: `team_id, team_name, P` (odigrane), `W` (pobjede), `D` (remiji), `L` (porazi), `GF, GA, GD, Pts, FP` (fair-play bodovi). Računa se dinamički iz `finished` utakmica (3/1/0). Sortiranje je **UEFA/HNS stil**: bodovi → mini-tablica međusobnih ogleda (h2h Pts/GD/GF) → ukupni GD → ukupni GF → fair-play (žuti×3 + crveni×10, manje je bolje).

Prikaz (`.standings`): glava `# Klub O P N I GR Bod`. Badge ekipe → `kurs_team_badge_html($row['team_id'], 32)`. Playoff pozicije (`$i < $grp->playoff_spots`, default **2**) dobivaju klase `playoff`/`is-playoff`; granični red `standings__row--cutoff`. Svaki red je `<a>` na profil ekipe (`get_post_permalink($team_id)`). Legenda: "Playoff (1–4)".

### 14.2 Tab "raspored"
**Direktan `$wpdb` upit** (ne metoda plugina): `{p}matches m JOIN {p}groups g JOIN {p}phases ph` + 2× JOIN na `{$wpdb->posts}` (home/away naziv), `WHERE ph.division_id = %d`, `ORDER BY m.round, m.match_date, m.id`. Grupira po kolu (`.sched-round-label` "N. kolo"). Završene → rezultat (`home_score : away_score`); ostale → datum (`date_i18n('j.n. H:i')`) ili "vs". Svaka utakmica → `add_query_arg('match_id', $m->id, home_url('/utakmica/'))`.

### 14.3 Tab "statistika"
`Kurs_Match::get_top_scorers($division_id, 20)` → `.widget` "Strijelci", redovi `.scorer-row` s dvoslovnim sufiksom ekipe (`mb_substr(team_name, 0, 2)`).

### 14.4 Side rail (`aside.hub-side`, vidljiv kad je odabrana divizija)
- **"Zlatna kopačka":** `Kurs_Match::get_top_scorers($division_id, 10)` — manji widget s rangom, badgeom ekipe i linkom na igrača (`get_permalink($player_id)`); ako ima ≥10 → link "Svi strijelci →" na tab statistika.
- **"Disciplina":** **direktan `$wpdb` upit** koji broji `yellow_card`/`red_card` iz `{p}match_events e JOIN matches/groups/phases` za diviziju. Prikazuje dvije kutije (`.disc-box--y` žuti / `.disc-box--r` crveni) samo ako `yellow > 0 || red > 0`.

> **GOTCHA (playoff bracket se NE prikazuje):** iako plugin ima klasu `Kurs_Playoff` (`includes/class-playoff.php`), front-end ne renderira bracket — tablica samo bojom označava playoff pozicije.

---

## 15. `page-utakmica.php` — Centar utakmice

URL `/utakmica/?match_id=X`. **Guardovi (svaki s `get_footer()` + `return`):** nema klase `Kurs_Match` → "plugin nije aktivan"; nema `match_id` → "Nije odabrana utakmica."; `Kurs_Match::get()` vrati prazno → "Utakmica ne postoji."

- **Dohvat utakmice:** `Kurs_Match::get($match_id)` (red `matches` + `home_name`, `away_name`).
- **Kontekst:** **direktan `$wpdb` upit** `{p}groups g JOIN phases ph JOIN divisions d JOIN seasons s WHERE g.id = %d` → `group_name, division_id, div_name, season_name, phase_name`.
- **Događaji:** `Kurs_Match::get_events($match_id)` → `match_events` + `player_name`, sort po minuti.
- **Sastavi:** `Kurs_Season::get_squad($home_team_id, $division_id)` i isto za gosta → redovi `squads` + `player_name`.
- **Prikaz:**
  - Breadcrumb: Naslovnica / `div_name` (link) / "N. kolo".
  - "Semafor" scoreboard (`.semafor`): badge ekipa 64px (`kurs_team_badge_html`, link na profil), rezultat ili "– : –", status "Završeno"/datum (`date_i18n('j.n.Y., H:i')`).
  - Timeline "Tijek utakmice" (`.kurs-event-timeline > .tl-row`): ikone preko `$icon_map` (`goal`/`own_goal` → `goal`, `yellow_card` → `yellow`, `red_card` → `red`), minuta, igrač, "ekipa · tip" (npr. "gol", "gol (vlastita mreža)", "žuti karton").
  - Dvije kolone sastava (`.lineup-cols > .lineup-team`) s brojem dresa (`jersey_no` ili "—").

> **GOTCHA (hardkodirani slug lige u breadcrumbu):** `$liga_url` se gradi kao `home_url('/kc-liga/?division_id=…&tab=raspored')` — uvijek `/kc-liga/`, čak i za Pan ljetne utakmice. Breadcrumb tako vodi na krivu ligu za Pan ljetne mečeve.

---

## 16. `page-o-nama.php` — O nama

Najjednostavniji: `.o-nama-hero` (label "Udruga", naslov "O nama") + `.o-nama-content` koji renderira `the_content()` (uređivo iz WP admina — sadržaj predpopunjava `functions.php`). Bez ligaških podataka.

---

## 17. `single-kurs_ekipa.php` — Profil ekipe

**Guard:** ako `! class_exists('Kurs_Season') || ! class_exists('Kurs_Match')` → `wp_redirect(home_url('/ekipe/')); exit;` (PRIJE `get_header()`). `$team_id = get_the_ID()`. Prefiks `$p = $wpdb->prefix . KURS_PREFIX`.

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

**Guard:** isti kao ekipa, ali redirect na `home_url('/igraci/')`. `$player_id = get_the_ID()`. Prefiks `$p = $wpdb->prefix . KURS_PREFIX`. Gotovo SVE preko **direktnih `$wpdb` upita** (ne koristi metode plugina za glavne podatke):

- **Karijera:** veliki upit nad `{p}squads sq JOIN divisions d JOIN seasons s JOIN posts t(ekipa)` + LEFT JOIN-ovi `group_teams gt → groups g → phases ph → matches m → match_events e`. Po sezoni/diviziji/ekipi računa: `matches_played` (`COUNT(DISTINCT … finished …)`), `goals` (`goal`+`own_goal` filtrirano `e.player_id`), `yellows`, `reds`, `jersey_no`. `GROUP BY s.id, d.id, sq.team_id`, `ORDER BY s.year DESC, s.id DESC, d.sort_order ASC, d.id ASC`.
- **Totali karijere:** `array_sum(array_column($career, …))` za golove, utakmice, žute, crvene.
- **Hero:** trenutna ekipa (`$career[0]`, najnovija sezona), broj dresa, golovi/utakmice/kartoni.
- **Karijerna tablica** (`.career-table`): grupirano po sezoni (`$rows_buffer`) s podzbrojevima po sezoni (ako >1 redak) i ukupnim `tfoot` redom. Badge divizije obojan `igrac_color($league)`.
- **Nedavni događaji:** zaseban `$wpdb` upit (zadnjih 10 `finished` događaja igrača) → `.event-row` s linkom na utakmicu i ikonama (⚽/🟨/🟥).
- **Ekipe kroz karijeru** (side widget): jedinstvene ekipe (`$unique_teams`) ako >1.

> **GOTCHA (lokalne funkcije u single predlošcima):** `ekipa_league_color` (ekipa) te `igrac_badge`/`igrac_color` (igrač) definirane su globalno u single predlošcima. Re-deklaracija nije problem jer se single učitava jednom po zahtjevu, ali su to globalne funkcije bez zaštite imena.

---

## 19. `assets/js/main.js` — interakcije

Minimalan, dvije funkcionalnosti unutar `DOMContentLoaded`:

1. **Mobilni meni:** klik na `#nav-toggle` toggla klasu `is-open` na `#site-nav` i ažurira `aria-expanded`. Klik izvan menija (`!siteNav.contains(target) && !navToggle.contains(target)`) zatvara.
2. **Search overlay:** `#search-toggle` otvara/zatvara `#search-overlay` (preko `hidden` atributa), fokusira `#search-input` (kroz `requestAnimationFrame`); `#search-close`, klik na pozadinu (`e.target === searchOverlay`) i tipka `Escape` zatvaraju.

> **GOTCHA (nema JS-a za lige):** Svi tabovi/filteri lige (tablica/raspored/statistika, izbor sezone/divizije, division toggle) rade **server-side preko GET parametara i reload-a stranice** (`<a href>` i `<select onchange="location.href=this.value">`), bez JavaScripta.

---

## 20. CSS (`assets/css/main.css`) — relevantne komponente

Stilizirano CSS varijablama u `:root`. Ključne boje: `--c-kc` (KC akcent), `--c-pan` (Pan), `--c-gold`, `--c-win`/`--c-draw`/`--c-loss`, `--c-muted`. Fontovi: `--font-cond` (Barlow Semi Condensed — naslovi), `--font-mono` (Space Mono — brojke/labele).

> **GOTCHA (nedosljedna imena boja u dokumentaciji):** `CLAUDE.md` navodi `--color-primary`/`--color-accent`, ali stvarni kod (footer, predlošci) koristi `--c-kc`/`--c-pan`/`--c-gold` itd.

Glavne komponentne klase po sekcijama:
- **Naslovnica:** `kurs-hero`, `kurs-hero__main`/`__side`, `hero-stripe`/`hero-img`/`hero-gradient`/`hero-cat`/`hero-title`/`hero-meta`, `league-card`(`--pan`), `news-grid`/`news-card`, `partners-bar`, `sec-head`.
- **Liga hub:** `league-banner`(`--pan`), `season-select`, `hub-tabs`/`div-toggle`/`view-tabs` (+`is-active`), `hub-body`/`hub-main`/`hub-side`, `standings` (+`__head`, `__row`, `playoff`, `standings__row--cutoff`, `st-pos`/`is-playoff`/`st-club`/`st-gd`/`st-pts`), `schedule`/`sched-round-label`/`sched-match` (+`__home`/`__score`/`__away`/`__score--vs`), `widget`/`widget__title`/`scorer-row`/`scorer-badge`/`scorer-goals`, `disc-boxes`/`disc-box`(`--y`/`--r`).
- **Utakmica:** `match-wrap`/`match-breadcrumb`, `semafor` (+`__stripe`/`__hatch`/`__inner`/`__team`/`__badge`/`__name`/`__center`/`__score`/`__status`/`__date`), `match-body`/`match-events`/`match-sec-title`, `kurs-event-timeline`/`tl-row`/`tl-min`/`tl-icon`/`tl-player`/`tl-team`, `match-lineups`/`lineup-cols`/`lineup-team`/`lineup-head`/`lineup-badge`/`lineup-player`/`lineup-no`.
- **Profil ekipe:** `team-profile`/`team-hero`(+`__overlay`/`__inner`/`__badge`/`__name`), `team-body`/`team-main`/`team-side`, `stat-strip`/`stat-box`, `next-match-card`, `roster-row`(+`__num`/`__name`/`__goals`).
- **Profil igrača:** `player-profile`/`player-hero`(+`__overlay`/`__inner`/`__photo`/`__info`/`__team`/`__jersey`/`__name`/`__stats`/`__stat`/`__sep`), `player-body`/`player-main`/`player-side`, `career-table`(+`career-row`/`career-row--season-start`/`--subtotal`/`career-total`/`career-season`/`career-div`/`career-badge`/`career-team`/`career-num`/`--goals`), `event-row`(+`__icon`/`__body`/`__label`/`__match`/`__date`).
- **Općenito:** `container`, `site-box`, `site-main`, `site-ticker`/`ticker-item`(`--sched`), `kurs-empty` (prazna stanja), `o-nama-wrap`/`o-nama-hero`/`o-nama-content`.

---

## 21. Zbirni popis GOTCHA-a (za brzu referencu)

1. **Verzija:** `KURSKC_VERSION='1.0.4'` (functions.php) ≠ `Version: 1.0.0` (style.css).
2. **CLAUDE.md (ranije zastario, ✅ ispravljen 2026-06-22):** spominjao je CPT `momcad`/`utakmica` i taksonomije `liga`/`sezona` koje ne postoje. Stvarno: tema = `vijest` + `vijest_kategorija`; plugin = `kurs_ekipa` + `kurs_igrac` + custom DB tablice.
3. **Mrtvi izbornici:** `register_nav_menus('primary','footer')`, ali `wp_nav_menu()` se nigdje ne poziva — sva navigacija hardkodirana.
4. **Neiskorišten custom-logo:** `add_theme_support('custom-logo')` postoji, ali `the_custom_logo()` se ne koristi — logo je hardkodirani `<img>`.
5. ✅ **ISPRAVLJENO (ISPRAVCI #7):** footer je ranije sve ligaške linkove vodio na nepostojeći `/liga/` (404); sada na `/kc-liga/` i `/pan-ljetna/`, usklađeno s headerom.
6. **Nepostojeće stranice:** `/kontakt/`, `/uvjeti-koristenja/`, `/privatnost/`, `/kolacici/` se ne kreiraju automatski; social linkovi su `#`.
7. **Mrtav kod:** `template-parts/content.php` i `content-none.php` se ne pozivaju.
8. **`languages/`** direktorij ne postoji iako `load_theme_textdomain` referira na njega.
9. **Tijesna sprega tema↔plugin:** `header.php` izvodi direktan `$wpdb` JOIN nad plugin tablicama; `functions.php` require-a plugin `helpers.php`. Tema funkcionalno ovisi o pluginu `kurs-liga`.
10. **Idempotentni overhead:** `kurskc_create_pages()` i "O KURS-u" init hook izvršavaju se na svakom `init` loadu (uz provjeru postojanja).
11. **Playoff bracket se NE prikazuje** na front-endu iako postoji `Kurs_Playoff`; tablica samo boja označava playoff pozicije.
12. **Hardkodiran slug u breadcrumbu utakmice:** `page-utakmica.php` uvijek vodi na `/kc-liga/` (krivo za Pan ljetne mečeve). `single-kurs_ekipa.php` ipak bira slug ispravno po ligi.
13. **Dvostruko `get_standings()`** za istu grupu u `single-kurs_ekipa.php` (`$team_pos`/`total`).
14. **Globalne funkcije bez prefiks-zaštite:** `kurs_badge_color`/`kurs_badge_label` (front-page), `ekipa_league_color` (ekipa), `igrac_badge`/`igrac_color` (igrač).
15. **Decentraliziran dohvat:** miješanje metoda plugina i ad-hoc `$wpdb` SQL-a u temi otežava održavanje (promjena sheme = izmjene na više mjesta).
16. **Pretpostavka postojanja stranica/dodjele templatea:** front-end linkovi pretpostavljaju da postoje stranice sa slugovima `kc-liga`, `pan-ljetna`, `vijesti`, `ekipe`, `igraci`, `utakmica`, `o-kurs-u` i da im je dodijeljen odgovarajući Template Name — inače 404.
17. **CSS varijable boja:** stvarna imena su `--c-kc`/`--c-pan`/`--c-gold`/`--c-win`/`--c-draw`/`--c-loss`/`--c-muted` (ne `--color-primary`/`--color-accent` iz CLAUDE.md).
