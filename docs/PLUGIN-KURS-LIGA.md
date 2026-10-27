# Plugin `kurs-liga` — kompletna dokumentacija

Sustav za upravljanje malonogometnim ligama udruge KURS (Koprivnička udruga za rekreativni sport). Plugin pokriva cijeli životni ciklus natjecanja: definiranje sezone i formata, dodjelu ekipa u grupe, rostere igrača, generiranje rasporeda "svaka sa svakom", vođenje zapisnika utakmice (golovi/kartoni), dinamičku tablicu poretka po UEFA/HNS pravilima, playoff bracket te ispis/PDF zapisnika.

> **Putanje:** plugin je u `app/public/wp-content/plugins/kurs-liga/`. Tema (`app/public/wp-content/themes/kurskc/`) je odvojen sloj — vidi gotcha o neusklađenosti s `CLAUDE.md`.

---

## 1. Arhitektura i bootstrap

### 1.1 Glavna datoteka — `kurs-liga.php`

Header plugina: **KURS Liga v1.0.0**, autor Amondi Media, `Requires PHP: 8.1`.

Definirane konstante:

| Konstanta | Vrijednost | Značenje |
|---|---|---|
| `KURS_VERSION` | `'1.0.0'` | Verzija plugina |
| `KURS_DIR` | `plugin_dir_path(__FILE__)` | Apsolutni put do plugina |
| `KURS_URL` | `plugin_dir_url(__FILE__)` | URL do plugina (asseti) |
| `KURS_PREFIX` | `'kurs_'` | Prefiks DB tablica (uz `$wpdb->prefix`) |

**Redoslijed učitavanja** (`require_once`):
1. `includes/class-database.php`
2. `includes/helpers.php`
3. `includes/class-playoff.php`
4. `includes/class-cpt.php`
5. `includes/class-season.php`
6. `includes/class-match.php`

**Inicijalizacija:**
- `new Kurs_CPT();` — registrira CPT-ove (kuka se na `init`).
- `add_action('plugins_loaded', ['Kurs_Database','maybe_upgrade'])` — provjera/migracija DB sheme pri svakom učitavanju.
- `if (is_admin()) { require admin/class-admin.php; new Kurs_Admin(); }` — admin sloj se učitava **samo u wp-adminu**.
- `register_activation_hook` × 2 — `Kurs_Database::install()` (kreira tablice) + registracija CPT-ova i `flush_rewrite_rules()`.
- `init` 99 radi jednokratni `flush_rewrite_rules()` preko opcije `kurs_flush_needed` (sama se briše); `init` 100 je **samoizlječenje** — ako u spremljenim `rewrite_rules` nema pravila `ekipe/…` (npr. nakon migracije baze), pravila se regeneriraju (inače `/ekipe/…`, `/igraci/…` i `/vijesti/` vraćaju 404).

### 1.2 Slojevi

```
┌─ Bootstrap (kurs-liga.php) ── konstante, require, hookovi
│
├─ Podatkovni sloj
│   └─ Kurs_Database  ── DDL (CREATE TABLE), migracije
│
├─ Domenski sloj (static-only klase, global $wpdb)
│   ├─ Kurs_Season  ── struktura iznad utakmica + roster
│   ├─ Kurs_Match   ── utakmice, eventi, tablica poretka, strijelci
│   ├─ Kurs_Playoff ── bracket, seeding, napredovanje
│   └─ helpers.php   ── prezentacijski helperi (boje, badge)
│
├─ CPT sloj
│   └─ Kurs_CPT  ── kurs_ekipa, kurs_igrac
│
└─ Admin sloj (samo is_admin())
    ├─ Kurs_Admin (class-admin.php) ── meni, POST forme, AJAX, asseti
    ├─ admin/views/*.php            ── prezentacija
    └─ admin/assets/{admin.css, admin.js}
```

**Ključna arhitektonska napomena:** sve domenske klase imaju **isključivo statičke metode**, rade izravno preko `global $wpdb` i koriste privatni helper `t( string $name ): string` koji vraća `$wpdb->prefix . KURS_PREFIX . $name` (npr. `wp_kurs_matches`). Klase nemaju stanje ni konstruktor.

---

## 2. Struktura datoteka

```
kurs-liga/
├── kurs-liga.php                    — bootstrap
├── includes/
│   ├── class-database.php           — Kurs_Database (shema + migracije)
│   ├── class-cpt.php                — Kurs_CPT (kurs_ekipa, kurs_igrac)
│   ├── class-season.php             — Kurs_Season (sezone→divizije→faze→grupe→roster)
│   ├── class-match.php              — Kurs_Match (utakmice, eventi, poredak, strijelci)
│   ├── class-playoff.php            — Kurs_Playoff (bracket, seeding, napredovanje)
│   └── helpers.php                  — kurs_team_color / _text_color / _badge_html
└── admin/
    ├── class-admin.php              — Kurs_Admin (~30 KB: meni, POST, AJAX, asseti)
    ├── assets/
    │   ├── admin.css                — ~2154 linije stilova
    │   └── admin.js                 — flatpickr init + jQuery delegirani eventi
    └── views/
        ├── dashboard.php            — pregledna ploča
        ├── seasons.php              — lista + forma sezona
        ├── season-setup.php         — wizard "Postavi ligu" (koraci 2–4)
        ├── matches.php              — utakmice, ljestvice + playoff bracket
        ├── teams.php                — ekipe (lista + edit s logom i rosterom)
        ├── roster.php               — skriveni roster (kurs-roster)
        ├── match-record.php         — zapisnik utakmice
        ├── match-record-pdf.php     — standalone print/PDF view
        └── playoff.php              — samostalna playoff stranica (NIJE registrirana — vidi gotchas)
```

---

## 3. Podatkovni model

### 3.1 Hijerarhija domene

```
season (sezona)
 └─ division (divizija, npr. "Glavna liga", "Liga bez briga")
     └─ phase (faza: type = 'group' | 'playoff')
         └─ group (grupa A, B... s atributom playoff_spots)
             ├─ group_teams (ekipe u grupi)
             └─ matches (utakmice grupne faze)
                 └─ match_events (golovi, autogolovi, kartoni)

playoff_matches  ── visi izravno o division_id (NE o fazi)
squads (roster)  ── igrač pripada ekipi unutar divizije
```

### 3.2 Tablice (`Kurs_Database::install`, `DB_VERSION = '1.5'`)

Stvarno ime = `$wpdb->prefix . KURS_PREFIX . <ime>` (npr. `wp_kurs_seasons`). Niže se navode kratka imena.

| Tablica | Ključni stupci |
|---|---|
| `seasons` | id, **league** (VARCHAR(100), slobodan unos naziva lige), name, year (SMALLINT), status (DEFAULT 'active'), created_at; KEY idx_status |
| `divisions` | id, season_id, name, sort_order; KEY idx_season |
| `phases` | id, division_id, name, **type** (DEFAULT 'group'), sort_order; KEY idx_division |
| `groups` | id, phase_id, name, **playoff_spots** (TINYINT, DEFAULT 2); KEY idx_phase |
| `group_teams` | id, group_id, team_id (BIGINT, = post `kurs_ekipa`); **UNIQUE(group_id, team_id)** |
| `squads` | id, player_id, team_id, division_id, jersey_no (TINYINT, nullable); **UNIQUE(player_id, team_id, division_id)** |
| `matches` | id, group_id, home_team_id, away_team_id, home_score, away_score (nullable TINYINT), match_date (DATETIME), round (TINYINT), status (DEFAULT 'scheduled'), created_at; KEY idx_group, idx_status |
| `match_events` | id, match_id, team_id, player_id (nullable), **event_type** (VARCHAR(20), komentar 'goal\|own_goal\|yellow\|red'), minute, **scored_for** (nullable — mrtav stupac); KEY idx_match, idx_player |
| `playoff_matches` | id, division_id, **round** ('qf'\|'sf'\|'final'\|'3rd'), **slot**, home_team_id, away_team_id, home_score, away_score (sve nullable), status (DEFAULT 'scheduled'), match_date, **home_label**/**away_label** (npr. "A1", "QF1-W"), **match_id** (FK → `matches.id`, zapisnik); KEY idx_division, idx_round |

### 3.3 Migracije — `Kurs_Database::maybe_upgrade()`

Usporedi `get_option('kurs_db_version')` s `DB_VERSION`; ako je niže:
- pozove `install()` — `dbDelta` idempotentno (re)kreira tablice i **dodaje stupce koji nedostaju** (`groups.playoff_spots` iz v1.2, `playoff_matches.match_id` iz v1.5; oni su dio `CREATE TABLE` definicija).
- v1.1: `ALTER TABLE seasons MODIFY league VARCHAR(100)` (valjan MySQL `MODIFY`).

> ✅ **ISPRAVLJENO (ISPRAVCI #1):** ranije su se `playoff_spots`/`match_id` dodavali preko `ADD COLUMN IF NOT EXISTS` (nevalidan MySQL → SQL greška u `debug.log`); sada ih dodaje `dbDelta`.

### 3.4 CPT-ovi — `Kurs_CPT::register()` (hook `init`)

| Slug | Naziv | `supports` | rewrite slug |
|---|---|---|---|
| `kurs_ekipa` | Ekipe | title, thumbnail, editor | `ekipe` |
| `kurs_igrac` | Igrači | title, thumbnail | `igraci` |

Oba `public`, `show_in_rest`, `has_archive => false`. Naslov posta = ime ekipe/igrača. Boja ekipe je u post-meta **`_kurs_team_color`**. Logo ekipe je istaknuta slika (featured image).

---

## 4. Domenski sloj

### 4.1 `Kurs_Season` — struktura natjecanja (`includes/class-season.php`)

CRUD nad cijelom hijerarhijom **iznad** utakmica + roster. Sve metode statičke.

**Sezone** (`seasons`)

| Metoda | Potpis | Ponašanje |
|---|---|---|
| `all()` | `(): array` | Sve sezone, `ORDER BY year DESC, id DESC`. |
| `active()` | `(): array` | `status='active'`, `ORDER BY year DESC`. |
| `get($id)` | `(int): ?object` | Jedna sezona. |
| `create($data)` | `(array): int\|false` | Insert. Sanitizira `league`, `name`; `year` kroz `absint`; **status se uvijek postavlja na `'active'`** (ignorira `$data['status']`). Vraća `insert_id`. |
| `update($id,$data)` | `(int,array): void` | Selektivni update polja `name, year, status, league` (samo postavljena, sva kroz `sanitize_text_field`). |
| `delete($id)` | `(int): void` | Kaskadno: za svaku diviziju `delete_division()`, pa briše sezonu. |

**Divizije** (`divisions`)

| Metoda | Ponašanje |
|---|---|
| `get_divisions($season_id)` | `ORDER BY sort_order, id`. |
| `get_division($id)` | Jedna divizija. |
| `create_division($season_id,$name,$order=0)` | Insert. |
| `delete_division($id)` | Kaskadno: za svaku fazu `delete_phase()`, briše `squads` po `division_id`, pa diviziju. |

**Faze** (`phases`)

| Metoda | Ponašanje |
|---|---|
| `get_phases($division_id)` | `ORDER BY sort_order, id`. |
| `create_phase($division_id,$name,$type='group',$order=0)` | `type` whitelista — dopušteno samo `'group'`/`'playoff'`, inače fallback `'group'`. |
| `delete_phase($id)` | Kaskadno: za svaku grupu `delete_group()`, pa fazu. |

**Grupe** (`groups`)

| Metoda | Ponašanje |
|---|---|
| `get_groups($phase_id)` | `ORDER BY name`. |
| `create_group($phase_id,$name)` | Insert. |
| `update_group_playoff_spots($group_id,$spots)` | `playoff_spots = max(0,$spots)` — broj ekipa iz grupe koje idu u playoff. |
| `delete_group($id)` | **Najsloženije kaskadno brisanje**: dohvati id-eve utakmica grupe → obriši njihove `match_events` → `group_teams` → `matches` → grupu. (Koristi `$GLOBALS['wpdb']` umjesto `global $wpdb` — stilska nedosljednost.) |

**Ekipe u grupama** (`group_teams`; `team_id` = ID posta `kurs_ekipa`)

| Metoda | Ponašanje |
|---|---|
| `get_group_teams($group_id)` | Ekipe + `team_name` (JOIN na `$wpdb->posts`), `ORDER BY p.post_title`. |
| `assign_team($group_id,$team_id)` | `INSERT IGNORE` (UNIQUE sprječava duplikate). |
| `remove_team($group_id,$team_id)` | Brisanje veze. |
| `get_team_contexts($team_id)` | Sve sezone/divizije u kojima ekipa igra. Veliki `DISTINCT` JOIN `group_teams→groups→phases→divisions→seasons`, `ORDER BY s.year DESC, s.id DESC, d.sort_order, d.id`. Koristi se za povijest ekipe / selektor rostera. |

**Roster / igrači** (`squads`; igrač pripada ekipi **unutar divizije**)

| Metoda | Ponašanje |
|---|---|
| `get_squad($team_id,$division_id)` | Roster + `player_name` (JOIN posts), `ORDER BY jersey_no, post_title`. |
| `add_to_squad($player_id,$team_id,$division_id,$jersey=0)` | `$wpdb->replace` (upsert po UNIQUE). `jersey_no = $jersey ?: null` (0 → NULL). |
| `remove_from_squad($player_id,$team_id,$division_id)` | Brisanje. |

### 4.2 `Kurs_Match` — utakmice, eventi, poredak (`includes/class-match.php`)

**Utakmice** (`matches`)

| Metoda | Ponašanje |
|---|---|
| `get($id)` | Utakmica + `home_name`/`away_name` (JOIN posts). |
| `get_by_group($group_id)` | `ORDER BY round, match_date, id`. |
| `create($group_id,$home,$away,$date=null,$round=1)` | Insert sa `status='scheduled'`. |
| `finish($id,$home_score,$away_score)` | Postavi rezultat + `status='finished'`. |
| `reopen($id)` | Vrati `status='scheduled'` — **NE briše rezultat** (vidi gotcha vs. playoff). |
| `delete($id)` | Briše `match_events`, pa utakmicu. |

**Eventi** (`match_events`) — `event_type ∈ { goal, own_goal, yellow_card, red_card }` (whitelist u `ajax_record_add_event`)

| Metoda | Ponašanje |
|---|---|
| `add_event($match_id,$type,$team_id,$player_id=null,$minute=null)` | Insert. **NE upisuje `scored_for`.** |
| `get_events($match_id)` | Eventi + `player_name` (LEFT JOIN posts), `ORDER BY minute, id`. |
| `delete_event($id)` | Brisanje. |

**Izračun rezultata iz eventa** — *nije* u domenskoj klasi nego u `Kurs_Admin::compute_score(object $match)` (privatna, ~linija 667 u `class-admin.php`):
- `goal` ekipe → +1 toj ekipi
- `own_goal` ekipe → +1 **protivniku** (autogol)
- `yellow_card`/`red_card` → ne mijenjaju rezultat

`match_events` su **izvor istine** za zapisnik; `matches.home_score/away_score` su **denormalizirani snapshot** koji admin upisuje kroz `Kurs_Match::finish()` pri zaključenju.

**Tablica poretka — `get_standings(int $group_id): array`** (~linija 111)

Najvažnija domenska metoda. Računa se **dinamički** iz završenih utakmica (nije perzistirana, nema cachea).

Bodovanje: **pobjeda = 3, remi = 1, poraz = 0**.

Postupak:
1. Dohvati ekipe grupe (`group_teams` + posts).
2. Dohvati sve `matches` grupe sa `status='finished'`.
3. Dohvati žute/crvene kartone po ekipi (iz `match_events` za te utakmice). Fair-play: **`FP = yellow*3 + red*10`** (niži = bolji).
4. Po ekipi računa: `P` (odigrane), `W/D/L`, `GF`, `GA`, `GD = GF − GA`, `Pts`, `FP`.

**Sortiranje (UEFA/HNS — međusobni susreti imaju prednost):**
1. **Pts** (silazno)
2–4. **Head-to-head** za izjednačene po bodovima: gradi mini-tablicu **samo od međusobnih utakmica** izjednačenih ekipa, sortira po `h2h Pts → h2h GD → h2h GF`. Cirkularni slučajevi dobiju isti rang i prepuštaju se ukupnim kriterijima. (Implementacija: ekipe se grupiraju po `Pts` u `$pt_groups`; za svaku skupinu 2+ gradi se `$mini` i dodjeljuje `$h2h_rank[team_id]`; završni `usort` koristi taj rang.)
5. **Ukupni GD** (silazno)
6. **Ukupni GF** (silazno)
7. **FP** (uzlazno — manje kartona = bolje)

**Strijelci — `get_top_scorers(int $division_id, int $limit=20): array`** (~linija 283)

Lista strijelaca cijele **divizije** (`match_events → matches → groups → phases.division_id`). Broji `event_type IN ('goal','own_goal')`, samo finished, `player_id IS NOT NULL`, `GROUP BY player_id`, `ORDER BY goals DESC LIMIT $limit`. Vraća `player_name`, `team_name`, `goals`.

### 4.3 `Kurs_Playoff` — bracket (`includes/class-playoff.php`)

Tablica `playoff_matches` (vidi 3.2). Playoff visi o `division_id`.

**Generiranje — `generate(int $division_id): bool`**
1. Grupe divizije, `ORDER BY g.name ASC`.
2. Za svaku grupu `Kurs_Match::get_standings()` → top `playoff_spots` ekipa; label `"{IME_GRUPE_VELIKIM}{rang+1}"` → `A1, A2, B1...`.
3. Parovi preko `build_seeding_pairs(n_groups, spots)`.
4. Struktura rundi preko `bracket_rounds(count($pairs))`.
5. **Obriše stari bracket** divizije (`DELETE ... WHERE division_id`).
6. Umetne prvu rundu (QF): `home_team_id/away_team_id` + `home_label/away_label`. Ako ekipa nepoznata → fallback label `"G{gi+1}{ri+1}"`.
7. Umetne **prazne** kasnije runde (SF, Final): source-labeli (`QF1-W` itd.) preko `source_label()`, bez ekipa.

**Seeding matrica — `build_seeding_pairs(int $n_groups, int $spots): array`** → niz `[gi, ri, gj, rj]` (0-based):

| Grupe × mjesta | Parovi (home–away po labelu) |
|---|---|
| **4 × 2** | QF1: A1–B2 · QF2: C1–D2 · QF3: B1–A2 · QF4: D1–C2 |
| **2 × 2** | SF1: A1–B2 · SF2: A2–B1 (odmah u final) |
| **4 × 1** | A1–D1 · B1–C1 |
| ostalo | generic "1. vs zadnji": prva polovica seedova × obrnuta druga polovica |

**Struktura rundi — `bracket_rounds(int $qf_count): array`**

| Broj parova | qf (1. runda) | later | prev (mapiranje izvora) |
|---|---|---|---|
| 4 | `'qf'` | `sf=2, final=1` | `sf←qf, final←sf` |
| 2 | `'sf'` | `final=1` | `final←sf` |
| ostalo | `'final'` | — | — |

**`source_label(string $round, int $slot)`** → `qf→QF, sf→SF, final→F` + `{slot}-W` (npr. `QF1-W`, `SF2-W`).

**CRUD:**

| Metoda | Ponašanje |
|---|---|
| `get_bracket($division_id)` | Svi mečevi grupirani `[$round][$slot]`. `ORDER BY FIELD(round,'qf','sf','final','3rd'), slot`. |
| `get_match($id)` | Jedan meč + imena. |
| `update_teams($id,$home_id,$away_id,$home_label,$away_label)` | Ručno postavljanje ekipa/labela (0/prazno → NULL). |
| `update_date($id,$date)` | Postavlja `match_date`. |
| `exists($division_id)` | Postoji li bracket. |
| `reset($division_id)` | Briše bracket. |

**Zaključenje / sinkronizacija / napredovanje:**
- `finish_match($id,$home_score,$away_score)`: rezultat + `status='finished'`; ako postoji povezani zapisnik (`get_linked_match_id`) → `Kurs_Match::finish()`; zatim `advance_winner($id)`.
- `reopen_match($id)`: prvo `retract_winner()`, zatim resetira rezultat na **NULL** i `status='scheduled'`; sinkronizira `Kurs_Match::reopen()`. (Za razliku od grupnog `reopen()`, ovdje se rezultat **briše** — nedosljedno.)
- `ensure_match_record($playoff_match_id)`: **lazy** kreira zapisnik u `matches` s **`group_id=0` (marker za playoff!)** ako meč ima obje ekipe, i poveže `match_id`. Vraća postojeći ako ga ima.
- `get_linked_match_id($id)` / `find_by_match_id($match_id)`: forward/reverse lookup veze playoff ↔ matches.

**Auto-advance:**
- `advance_winner(int $match_id)` (private): za finished meč pobjednik = `home_score > away_score`. **Neriješeno → ne napreduje automatski** (treba ručni unos). Preko `next_slot()` upiše pobjednika + label `{ROUND}{slot}-W`.
- `retract_winner(object $m)` (private): suprotno — sljedeći slot na NULL.
- `next_slot(string $round, int $slot): array` (private) — fiksno mapiranje:

| Iz | U (runda / slot / pozicija) |
|---|---|
| QF1 | SF slot1 / home |
| QF2 | SF slot1 / away |
| QF3 | SF slot2 / home |
| QF4 | SF slot2 / away |
| SF1 | Final slot1 / home |
| SF2 | Final slot1 / away |
| ostalo | `[null,null]` (kraj) |

### 4.4 `helpers.php` — prezentacijske funkcije (globalne)

| Funkcija | Ponašanje |
|---|---|
| `kurs_team_color(int $team_id): string` | Boja iz post-meta `_kurs_team_color`; validira `/^#[0-9a-fA-F]{3,6}$/`; default **`#1a3a6b`** (= `--color-primary` teme). |
| `kurs_team_text_color(string $hex): string` | WCAG luminance `(0.299·R + 0.587·G + 0.114·B)/255`; vraća `#111317` ako `lum > 0.55`, inače `#ffffff`. Podržava 3-znamenkasti hex. |
| `kurs_team_badge_html(int $team_id, int $size=32, string $img_size='thumbnail', string $extra_css=''): string` | Badge ekipe: ako ima featured sliku → kružni `<img>`; inače obojeni krug s inicijalima (`mb_strtoupper(mb_substr(name,0,2))`, font `'Barlow Semi Condensed'`, bold italic). Veličina fonta `max(9, size*0.35)`. |

Koriste se u `teams.php`, `match-record(.php/-pdf.php)`, `season-setup.php`, `playoff.php`, `matches.php`.

### 4.5 Round-robin raspored (u admin sloju)

> **Napomena (slabija separacija slojeva):** generiranje rasporeda **nije** u `Kurs_Match` nego u AJAX handleru `Kurs_Admin::ajax_generate_fixtures()` (~linija 529).

Klasični **circle method**, single round-robin:
1. Dohvati ekipe; skupi postojeće parove (`min_max` ključ) da se izbjegnu duplikati.
2. Neparan broj → fiktivni "bye" igrač (`0`).
3. Fiksira prvu ekipu (`$ids[0]`), ostatak (`$rotation`) rotira kroz `count-1` kola.
4. U kolu spaja `rt[i]` vs `rt[count-1-i]`; preskače parove s `0` i postojeće.
5. `Kurs_Match::create($group_id, $h, $a, null, $round)` (datum `null`).
6. Rotacija: zadnji element ide na početak.

---

## 5. Admin sloj

### 5.1 `Kurs_Admin` (`admin/class-admin.php`, ~30 KB)

Jedna klasa registrira meni, asset-e, POST forme (`admin_init`) i AJAX (`wp_ajax_*`). View datoteke rade samo prezentaciju + `wp_nonce_field`; logika spremanja je u `Kurs_Admin` koja delegira na domenske klase. Capability je **svuda `manage_options`**.

### 5.2 Admin izbornik — `menus()` (hook `admin_menu`)

Top-level: `add_menu_page('KURS Liga', ..., 'kurs-dashboard', 'dashicons-awards', 25)`.

| Slug stranice | Naslov | Renderer | View | Vidljivost |
|---|---|---|---|---|
| `kurs-dashboard` | Dashboard | `pg_dashboard()` | `dashboard.php` | vidljiva |
| `kurs-seasons` | Sezone | `pg_seasons()` | `seasons.php` | vidljiva |
| `kurs-season-setup` | Postavi ligu | `pg_setup()` | `season-setup.php` | vidljiva |
| `kurs-matches` | Utakmice | `pg_matches()` | `matches.php` | vidljiva |
| `kurs-teams` | Ekipe | `pg_teams()` | `teams.php` | vidljiva |
| `kurs-roster` | Roster | `pg_roster()` | `roster.php` | **skrivena** (`add_submenu_page(null,...)`) |
| `kurs-match-record` | Zapisnik | `pg_match_record()` | `match-record.php` (+ `-pdf.php`) | **skrivena** (parent `null`) |
| `kurs-playoff` | — | `pg_playoff()` | `playoff.php` | **NIJE registrirana** (vidi gotchas) |

### 5.3 Asseti — `assets($hook)` (hook `admin_enqueue_scripts`)

Učitava samo kad `strpos($hook,'kurs-')`. Učitava:
- `wp_enqueue_media()` (logo modal)
- **flatpickr** (CDN CSS+JS) + hrvatska lokalizacija `l10n/hr.js`
- Google Fonts (Barlow, Barlow Semi Condensed, Space Mono)
- `kurs-admin` CSS (`admin/assets/admin.css`) i JS (`admin/assets/admin.js`); JS ovisi o `jquery`, `flatpickr`, `flatpickr-hr`
- `wp_localize_script('kurs-admin','KursAjax', ['url'=>admin-ajax.php, 'nonce'=>wp_create_nonce('kurs_ajax')])`

**Sigurnost:** svi AJAX dijele jedan nonce **`kurs_ajax`** (`check_ajax_referer('kurs_ajax','nonce')`); svaki handler dodatno `current_user_can('manage_options')`.

### 5.4 POST forme — `handle_forms()` (hook `admin_init`)

Provjeri `manage_options` + `$_POST['kurs_action']`, pa `match()` na `sanitize_key($_POST['kurs_action'])` → privatna `do_*` metoda. Svaka `do_*` ima vlastiti `check_admin_referer()` i radi `wp_safe_redirect() + exit` (PRG).

| `kurs_action` | Nonce | `do_*` | Akcija | Redirect |
|---|---|---|---|---|
| `create_season` | `kurs_create_season` | `do_create_season` | `Kurs_Season::create($_POST)` | season-setup&season_id=…&step=2 |
| `edit_season` | `kurs_edit_season` | `do_edit_season` | `Kurs_Season::update(id,[league,name,year,status])` | kurs-seasons&updated=1 |
| `delete_season` | `kurs_delete_season` | `do_delete_season` | `Kurs_Season::delete(id)` | kurs-seasons&deleted=1 |
| `add_division` | `kurs_season_setup` | `do_add_division` | `create_division(sid, div_name)` | setup step=2 |
| `delete_division` | `kurs_season_setup` | `do_delete_division` | `delete_division(did)` | setup step=2 |
| `add_phase` | `kurs_season_setup` | `do_add_phase` | `create_phase(did, phase_name, 'group')` | setup step=2 |
| `delete_phase` | `kurs_season_setup` | `do_delete_phase` | `delete_phase(pid)` | setup step=2 |
| `add_match` | `kurs_add_match` | `do_add_match` | `Kurs_Match::create(gid,home,away,date,round)` | kurs-matches&group_id=… |
| `finish_match` | `kurs_finish_match` | `do_finish_match` | `Kurs_Match::finish(mid,home,away)` + sync playoff | record ili matches (ovisi o `from_record`) |
| `reopen_match` | `kurs_reopen_match` | `do_reopen_match` | `Kurs_Match::reopen(mid)` + sync playoff | kurs-match-record&match_id=… |
| `delete_match` | `kurs_delete_match` | `do_delete_match` | `Kurs_Match::delete(mid)` | kurs-matches&group_id=… |
| `add_team` | `kurs_add_team` | `do_add_team` | `wp_insert_post(kurs_ekipa)` + meta `_kurs_team_color` | kurs-teams&added=1 |
| `edit_team` | `kurs_edit_team` | `do_edit_team` | `wp_update_post` + meta boje; `redirect_to` ako postoji | kurs-teams&updated=1 |
| `delete_team` | `kurs_delete_team` | `do_delete_team` | `wp_delete_post(id, true)` | kurs-teams&deleted=1 |
| `remove_team_logo` | `kurs_remove_logo_<team_id>` | `do_remove_team_logo` | `delete_post_thumbnail(id)` | `redirect_to` ili kurs-teams |
| `generate_playoff` | `kurs_generate_playoff` | `do_generate_playoff` | `Kurs_Playoff::generate(did)` | kurs-matches&playoff_div=…&generated=1 |
| `reset_playoff` | `kurs_reset_playoff` | `do_reset_playoff` | `Kurs_Playoff::reset(did)` | kurs-matches&playoff_div=…&reset=1 |
| `finish_playoff_match` | `kurs_finish_playoff_match` | `do_finish_playoff_match` | `Kurs_Playoff::finish_match(id,home,away)` | kurs-matches&playoff_div=… |
| `reopen_playoff_match` | `kurs_reopen_playoff_match` | `do_reopen_playoff_match` | `Kurs_Playoff::reopen_match(id)` | kurs-matches&playoff_div=… |
| `update_playoff_match` | `kurs_update_playoff_match` | `do_update_playoff_match` | `Kurs_Playoff::update_teams(...)` + `update_date(...)` | kurs-matches&playoff_div=… |

**Privatni helperi klase:**
- `parse_hr_date($raw)` — `dd.mm.yyyy hh:mm` (ili `dd.mm.yyyy`, fallback `strtotime`) → MySQL `Y-m-d H:i:s`. Koristi se u `add_match`, `ajax_update_match`, `update_playoff_match`.
- `sanitize_team_color($raw)` — regex `/^#[0-9a-fA-F]{3,6}$/`, fallback `#1a3a6b`.
- `compute_score($match)` — izračun live rezultata iz eventa (vidi 4.2).

### 5.5 AJAX akcije (`wp_ajax_<action>`)

Svaka: `check_ajax_referer('kurs_ajax','nonce')` + `manage_options`.

| AJAX `action` | Metoda | Akcija / odgovor |
|---|---|---|
| `kurs_add_group` | `ajax_add_group` | `create_group(phase_id,name)` → `{id,name}` |
| `kurs_del_group` | `ajax_del_group` | `delete_group(group_id)` |
| `kurs_assign_team` | `ajax_assign_team` | `assign_team(gid,tid)` → `{team_id,team_name}` |
| `kurs_remove_team` | `ajax_remove_team` | `remove_team(gid,tid)` |
| `kurs_set_logo` | `ajax_set_logo` | `set_post_thumbnail(team_id,attachment_id)` → `{url}` |
| `kurs_add_squad` | `ajax_add_squad` | `add_to_squad(player,team,division,jersey)` |
| `kurs_remove_squad` | `ajax_remove_squad` | `remove_from_squad(...)` |
| `kurs_quick_add_player` | `ajax_quick_add_player` | `wp_insert_post(kurs_igrac)` + opcionalno `add_to_squad` |
| `kurs_search_players` | `ajax_search_players` | LIKE pretraga `kurs_igrac` po `post_title`, LIMIT 10 → `[{id,name}]` |
| `kurs_update_squad_player` | `ajax_update_squad_player` | `$wpdb->update` na `..._squads` (jersey_no) + `wp_update_post` (ime) |
| `kurs_create_team` | `ajax_create_team` | provjera duplikata pa `wp_insert_post(kurs_ekipa)` → `{team_id,name}` ili `{code:'duplicate',...}` |
| `kurs_update_playoff_spots` | `ajax_update_playoff_spots` | `update_group_playoff_spots(gid,spots)` (clamp na broj ekipa) |
| `kurs_generate_fixtures` | `ajax_generate_fixtures` | round-robin (circle method) → `{created}` |
| `kurs_update_match` | `ajax_update_match` | `$wpdb->update` na `..._matches` (match_date, round) → formatirani datum |
| `kurs_record_add_event` | `ajax_record_add_event` | `Kurs_Match::add_event(...)` → event + novi `home_score/away_score` |
| `kurs_record_del_event` | `ajax_record_del_event` | `Kurs_Match::delete_event(id)` → novi score |

### 5.6 View datoteke

**`dashboard.php`** — pregledna ploča. Direktnim `$wpdb` JOIN-ovima (`matches/groups/phases/divisions`) računa: aktivne sezone (`Kurs_Season::active()`), broj ekipa/igrača (CPT), utakmice u idućih 7 dana, nadolazeće (scheduled, LIMIT 6), zadnje rezultate (finished, LIMIT 6). CTA gumbi → kreiranje sezone, `post-new.php?post_type=kurs_ekipa/kurs_igrac`. Nadolazeće utakmice imaju "Zapisnik" link.

**`seasons.php`** — dvostupčani layout: lista (`Kurs_Season::all()`) + forma. Edit-mod preko `?action=edit&id=`. Notice iz `$_GET['updated|deleted|added']`. Nova sezona (nonce `kurs_create_season`): `league` (select kc_liga/pan_ljetna), `name`, `year`. Edit (nonce `kurs_edit_season`) dodaje `status`. Brisanje = inline POST s `confirm()`.

**`season-setup.php`** (wizard "Postavi ligu") — zahtijeva `?season_id=` (bez njega redirect na prvu sezonu) i `?step=` (default 2). Stepper: 1 Osnovno (done), 2 Format & Divizije, 3 Dodjela ekipa, 4 Utakmice (link na kurs-matches). Status koraka dinamičan (`$step2_done` = ima divizija, `$step3_done` = neka grupa ima ekipe).
- **Korak 2:** forma "Dodaj diviziju" (`add_division`); po diviziji brisanje + faze; dodavanje faze preko `<details>` (`add_phase`, hardkodiran `phase_type=group`); za `group` faze AJAX upravljanje grupama (`.kurs-groups-mgr[data-phase-id]` → `kurs_add_group`/`kurs_del_group`); "Playoff spots" po grupi (`.kurs-spots-row__val[data-group-id]` → debounced `kurs_update_playoff_spots`); za ne-`group` faze link na funkcionalnu playoff rutu `kurs-matches?playoff_div=` (ranije je vodio na neregistriranu stranicu `kurs-playoff` — ✅ ISPRAVCI #6).
- **Korak 3:** `window.KursTeams` JSON (client-side autocomplete); pool ekipa + "Nova ekipa" (`#btn-create-team-setup` → `kurs_create_team`); po grupi lista ekipa s uklanjanjem (`.kurs-btn-rm` → `kurs_remove_team`) + autocomplete dodavanje (`.kurs-team-search` + `.kurs-btn-add-team` → `kurs_assign_team`).

**`teams.php`** — dva moda:
- **Lista** (default): sve ekipe (`get_posts kurs_ekipa`), badge (`kurs_team_badge_html`), edit link, inline delete (`delete_team`), "Dodaj ekipu" (`add_team`) s color pickerom (`kurs_color_picker_html()` — 12 preset boja + color wheel + hex).
- **Edit** (`?action=edit&id=`): `return` prije liste. Lijevo: osnovno (naziv + boja, `edit_team`), logo panel (Media Library `.kurs-logo-btn` → `kurs_set_logo`; uklanjanje `remove_team_logo`). Desno: roster za odabranu sezonu/diviziju — **ista logika i isti HTML/JS hookovi kao `roster.php`** (`#kurs-roster-ctx`, `#kurs-squad-list`); uključuje duplikat-modal.

**`roster.php`** (skrivena `kurs-roster`) — zahtijeva `?team_id=`, opcionalno `?division_id=`. Kontekst (`get_team_contexts`) + squad (`get_squad`). GET selektor sezone/divizije. Skriveni `#kurs-roster-ctx[data-team-id][data-division-id]` čita JS. Sidebar: "Dodaj postojećeg" (select → `#btn-add-squad` → `kurs_add_squad`); "Kreiraj novog" s autocomplete-om (`kurs_search_players`) + duplikat-modal (postojeći / novi `kurs_quick_add_player`). Inline edit retka (ime + broj) → `kurs_update_squad_player`. Uklanjanje → `kurs_remove_squad`.

**`matches.php`** (najveća view datoteka) — parametri `?season_id=`, `?group_id=`, `?playoff_div=`. Flatten grupa/divizija; selektor sezone + selektor prikaza (optgroup po divizijama + "— Playoff —").
- **Grupni prikaz** (`$view='group'`): **Ljestvica** iz `get_standings($group_id)` — stupci U/P/R/Pr/GR/Bod (W=3,D=1,L=0). Prvih N (`playoff_spots`) označeno `standings-playoff-cutoff`. Ime ekipe → link na `kurs-roster`. **Raspored** — "Dodaj ručno" (skriveni checkbox + `add_match`, datepicker `kurs-datepicker`), "Generiraj sve" (`.kurs-btn-gen-fixtures` → `kurs_generate_fixtures`). Utakmice po kolu; svaka: "Zapisnik" link, inline edit datuma/kola (`.kurs-btn-edit-match` → `kurs_update_match`), brisanje (`delete_match`).
- **Playoff prikaz** (`$view='playoff'`, kad je `?playoff_div=` postavljen): vizualni bracket (vidi `playoff.php` — gotovo identično, duplicirana logika).

**`playoff.php`** (renderer `pg_playoff`, stranica `kurs-playoff`) — samostalna playoff-admin stranica s vlastitim selektorima i istim bracketom kao matches.php. **Stranica NIJE registrirana u `menus()`** pa joj se ne može pristupiti (gotcha). Bracket: `get_bracket($division_id)` grupiran po rundi (`qf/sf/final/3rd`); gumbi "Generiraj bracket" (`generate_playoff`) / "Resetiraj" (`reset_playoff`); po meču badge ekipa (ili `?` TBD), rezultat, datum, "Zapisnik" link (lazy `ensure_match_record()` → `match_id`), edit panel s formom za rezultat (`finish_playoff_match`) i formom za ekipe/datum (`update_playoff_match`); završeni meč ima "Otključaj" (`reopen_playoff_match`).

**`match-record.php`** (zapisnik, `kurs-match-record`) — zahtijeva `?match_id=`. `Kurs_Match::get`. **Playoff meč se detektira po `group_id === 0`** (`$is_playoff`) i vuče info iz playoff tablice (`Kurs_Playoff::find_by_match_id`); obični mečevi JOIN-om dohvaćaju group/phase/division/season. Računa `division_id`/`season_id` + squad domaćina i gosta (`get_squad`). Ako `?format=pdf` → `include match-record-pdf.php; exit`.
Sadržaj: **Hero scoreboard** (badge + rezultat; nezavršeno → live `$ev_home/$ev_away` iz eventa, završeno → spremljeni score; status "U TIJEKU"/"ZAVRŠENO"); **Uredi datum/sat/kolo** (`<details>` → `#btn-save-match-info` → `kurs_update_match`); **Golovi/eventi** (lista `get_events`; nezavršeno → forma "Dodaj" ekipa/igrač/tip/minuta → `#btn-add-event` → `kurs_record_add_event` + brisanje `.kurs-del-event` → `kurs_record_del_event`; tipovi `goal/own_goal/yellow_card/red_card`); **Postave** (squad tablice); **Zaključi utakmicu** (nonce `kurs_finish_match`, action `finish_match`, `from_record=1`; rezultat default `readonly` auto-računat, toggle "Ručni unos" `#finish-manual-toggle`; završeno → status + "Otključaj za ispravke" `reopen_match`); **Potpisi** (print-only: domaćin/sudac/gost). Na dnu `<script>` postavlja `window.Kurs*` (squad JSON, team id, imena, `KursMatchId`, `KursPdfUrl`). Topbar: "Ispiši" (`window.print()`), "Preuzmi PDF" (`#btn-download-pdf` → `?format=pdf` u novom prozoru).

**`match-record-pdf.php`** — standalone print/PDF view, uključen iz match-record.php kad `?format=pdf`. Naslijeđuje varijable parent scope-a (`$match`, `$events`, `$home_squad`...). Šalje vlastiti `Content-Type: text/html`, kompletan `<!DOCTYPE html>` s ugrađenim CSS-om (vlastiti `:root` tokeni, `@page { margin:0; size:A4 }`), Google Fonts. `window.onload → setTimeout(window.print, 400)`. Sadrži stripes, hero scoreboard, golove, postave, prostor za potpise, footer "KURS Liga · Koprivnica" + datum. **Nema server-side PDF generacije** — oslanja se na browserov print-to-PDF.

### 5.7 `admin.js` — ključne interakcije

Jedan IIFE inicijalizira flatpickr (hrvatski, format `d.m.Y H:i`) na `.kurs-datepicker`. Glavni `jQuery(function($){...})` delegiranim eventima (`$(document).on`) pokriva: grupe (add/del), playoff-spots spinner (debounce 600 ms), dodjelu ekipa + client-side autocomplete (`window.KursTeams`), brzo kreiranje ekipe; color picker (`cpApplyColor`) + legacy swatch; logo upload (`wp.media` → `kurs_set_logo`); roster (autocomplete `kurs_search_players`, duplikat-modal, add/remove/inline-edit); utakmice (generiranje, inline edit); zapisnik (aktivan samo ako postoji `window.KursMatchId`: popunjavanje selecta igrača po ekipi, add/del eventa s live-score, auto/ručni toggle, PDF download, spremanje datuma/kola); helperi `h()`, `escAttr()`, `showMsg()`.

> Bracket edit paneli imaju **vlastiti inline `<script>`** (na dnu matches.php i playoff.php) koji otvara/zatvara `.kurs-pm-edit-panel` i lazy-inicijalizira flatpickr — to **nije** u admin.js.

---

## 6. End-to-end workflow operatera

1. **Kreiraj sezonu** — `kurs-seasons` → `create_season` (liga, naziv, godina). Redirect na setup step 2.
2. **Postavi format** — `kurs-season-setup` step 2: dodaj diviziju/e (`add_division`); unutar divizije faze (`add_phase`, tip group); unutar grupne faze grupe (AJAX `kurs_add_group`); postavi `playoff_spots` po grupi (AJAX `kurs_update_playoff_spots`).
3. **Dodjela ekipa** — step 3: kreiraj ekipe (AJAX `kurs_create_team`), rasporedi u grupe (`kurs_assign_team`/`kurs_remove_team`).
4. **Roster** — preko `kurs-teams → Uredi` ili skrivene `kurs-roster`: dodaj postojeće/nove igrače u squad po sezoni/diviziji (`kurs_add_squad`, `kurs_quick_add_player`, `kurs_update_squad_player`).
5. **Unos utakmica** — `kurs-matches`: generiraj cijeli raspored "svaka sa svakom" (`kurs_generate_fixtures`) ili ručno (`add_match`); uredi datum/kolo (`kurs_update_match`).
6. **Zapisnik** — `kurs-match-record`: unesi golove i kartone (`kurs_record_add_event`), rezultat se live računa; zaključi (`finish_match`) → bilježi se u tablicu poretka. Po potrebi otključaj (`reopen_match`).
7. **Playoff** — kad su grupe odigrane: na `kurs-matches?playoff_div=…` (selektor "— Playoff —") generiraj bracket iz ljestvica (`generate_playoff`), unosi rezultate (`finish_playoff_match`), ručno koriguraj parove/datume (`update_playoff_match`); zapisnik playoff meča preko lazy `ensure_match_record()`.
8. **PDF zapisnik** — iz zapisnika "Preuzmi PDF" / "Ispiši" → `?format=pdf` → browser print-to-PDF.

---

## 7. Gotchas, mrtav kod i nedosljednosti

**Mrtav kod / neregistrirano:**
- **`kurs-playoff` stranica nije registrirana** u `menus()` — `pg_playoff()` i `playoff.php` postoje, ali nema `add_submenu_page` za njih, pa je taj view **mrtav/nedostupan**. Funkcionalni playoff ide **samo** kroz `kurs-matches?playoff_div=`. ✅ Jedini živi link na nju (iz `season-setup.php`) repointan je na funkcionalnu rutu (ISPRAVCI #6); preostaje samo `self_url` unutar same (nedostupne) `playoff.php`. Kandidat za uklanjanje.
- **`match_events.scored_for`** (komentar 'za autogol: tim koji prima gol') postoji u shemi, ali se **nigdje ne upisuje ni čita** — `add_event` ga ne postavlja, `compute_score` ga ne koristi.
- **Round `'3rd'`** (utakmica za 3. mjesto) postoji u enum komentaru i u `ORDER BY FIELD(...,'3rd')`, ali se **nigdje ne generira ni puni** — mrtva grana.

**Separacija slojeva:**
- Round-robin generator (`ajax_generate_fixtures`) i izračun rezultata (`compute_score`) su u **admin klasi**, ne u `Kurs_Match`. Domenska klasa samo sprema gotov rezultat (`finish`). Scoring logika duplicirana izvan domene.

**Nedosljednosti:**
- **`reopen` ponašanje (namjerno):** `Kurs_Match::reopen()` vraća status na scheduled ali **ne briše** `home_score/away_score` — operater zadržava uneseni rezultat pri otključavanju, a `get_standings()` ionako broji samo `status='finished'`. `Kurs_Playoff::reopen_match()` ih postavlja na NULL. Vidi ISPRAVCI ("još otvoreno").
- **`Kurs_Season::create()`** ignorira proslijeđeni `status` (uvijek `'active'`), dok `update()` ga dopušta — nepotpuna simetrija.
- **`build_seeding_pairs`** koristi `count($groups)` i `$groups[0]->playoff_spots` — pretpostavlja da SVE grupe imaju isti broj playoff mjesta. Ako se razlikuju, seeding može biti pogrešan: `generate` koristi per-grupu spots za labele, ali globalni `spots[0]` za parove.
- **`delete_group`** koristi `$GLOBALS['wpdb']` umjesto `global $wpdb` (stilska razlika od ostatka klase).
- **Neriješen playoff rezultat** → `advance_winner` tiho ne napreduje; nema modela za penale/produžetke (treba ručni `update_teams`).
- **Datumi:** dashboard miješa `date_i18n` i `date()` — nedosljedan format/timezone. *(✅ `match-record-pdf.php` footer ispravljen na `date_i18n('j.n.Y. \u H:i')` — ISPRAVCI #4.)*
- **`ajax_search_players`** radi LIKE od 1 znaka, dok JS autocomplete traži min 2 znaka.
- ✅ **ISPRAVLJENO (ISPRAVCI #5):** u `matches.php` je naziv tablice `'kurs_groups'` bio hardkodiran umjesto `KURS_PREFIX` konstante; sada koristi `$wpdb->prefix . KURS_PREFIX . 'groups'`.
- `redirect_to` iz `$_POST` (esc_url_raw) u `do_remove_team_logo`/`do_add_team`/`do_edit_team` — open-redirect rizik ublažen jer `wp_safe_redirect` dozvoljava samo lokalne hostove.

**Duplikacija koda:**
- Cijeli bracket prikaz + inline `<script>` za pm-edit panele je gotovo identičan u `matches.php` i `playoff.php`.
- Edit roster UI (uključujući duplikat-modal) dupliciran je u `roster.php` i u edit-modu `teams.php`.

**Neusklađenost s `CLAUDE.md`:**
- `CLAUDE.md` opisuje **temu** `kurskc` (CPT-ovi `momcad`/`utakmica`/`vijest`, taksonomije `liga`/`sezona`). Plugin `kurs-liga` je **odvojen model podataka**: vlastiti CPT-ovi `kurs_ekipa`/`kurs_igrac` i vlastite DB tablice (ne taksonomije) za sezone/divizije/utakmice. Dva odvojena sloja istog projekta.
