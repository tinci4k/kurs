# Plugin `kurs-liga` — kompletna dokumentacija

Sustav za upravljanje malonogometnim ligama udruge KURS (Koprivnička udruga za rekreativni sport). Plugin pokriva cijeli životni ciklus natjecanja: kreiranje sezone (struktura se generira sama), dodjelu ekipa u lige/skupine, rostere igrača, generiranje rasporeda "svaka sa svakom", vođenje zapisnika utakmice (golovi/kartoni), dinamičku tablicu poretka po UEFA/HNS pravilima, pregled po kolima, playoff bracket te ispis/PDF zapisnika.

> **Putanje:** plugin je u `app/public/wp-content/plugins/kurs-liga/`. Tema (`app/public/wp-content/themes/kurskc/`) je odvojen sloj — vidi gotcha o neusklađenosti s `CLAUDE.md`.
>
> **Od 2026-10-16 sustav služi ISKLJUČIVO KURS-u:** struktura natjecanja je hardkodirana u `Kurs_Season::COMPETITIONS` (KC Liga = lige *KC Liga* + *Liga bez briga*; Pan Ljetna liga = jedna liga s 2 ili 4 skupine + playoff). Ručno dodavanje divizija/faza/grupa i podešavanje `playoff_spots` iz admina je **uklonjeno** — vidi §4.1 i §5.
>
> **Admin je preslagan po natjecanjima (2026-10-16, `KURS_VERSION` 1.16.0):** umjesto razbacanih stranica (Sezone, Utakmice, skriveni „Postavi ligu"/Roster, Ekipe) svako natjecanje ima **svoju stranicu** (`kurs-kc` / `kurs-pan`) s karticama Pregled · Ekipe · Utakmice · Postavke; ekipe i igrači su zajednički registar (`kurs-teams`); stari slugovi se samo preusmjeravaju. Vidi §5 i §6.
>
> ⚠️ Dokument je primarno usklađen s izmjenama od 2026-10-16. Značajke dodane između 2026-06-22 i 2026-10-16 (ticker `Kurs_Ticker`, uloge `Kurs_Roles`, zaključavanje sezone, prekršaji/kapetan/odsutni/suci u zapisniku, baneri/fotografija ekipe) ovdje su navedene samo sažeto.

---

## 1. Arhitektura i bootstrap

### 1.1 Glavna datoteka — `kurs-liga.php`

Header plugina: **KURS Liga v1.2.0**, autor Amondi Media, `Requires PHP: 8.1`. (Header verzija se ne podiže uz svaku izmjenu — mjerodavna je `KURS_VERSION`.)

Definirane konstante:

| Konstanta | Vrijednost | Značenje |
|---|---|---|
| `KURS_VERSION` | `'1.19.0'` | Verzija koda (cache-busting admin asseta) |
| `KURS_DIR` | `plugin_dir_path(__FILE__)` | Apsolutni put do plugina |
| `KURS_URL` | `plugin_dir_url(__FILE__)` | URL do plugina (asseti) |
| `KURS_PREFIX` | `'kurs_'` | Prefiks DB tablica (uz `$wpdb->prefix`) |
| `KURS_CAP` | `'manage_kurs_liga'` | Ovlast za cijelu KURS administraciju (admin + uloga Author, vidi `Kurs_Roles`) |

**Redoslijed učitavanja** (`require_once`):
1. `includes/class-database.php`
2. `includes/helpers.php`
3. `includes/class-playoff.php`
4. `includes/class-cpt.php`
5. `includes/class-season.php`
6. `includes/class-match.php`
7. `includes/class-players.php` — **1.19.0** (`Kurs_Players`, registar igrača: duplikati, spajanje, brisanje)
8. `includes/class-ticker.php`
9. `includes/class-rounds.php` — **NOVO 2026-10-16** (`Kurs_Rounds`, pregled po kolima)
10. `includes/class-roles.php`

**Inicijalizacija:**
- `new Kurs_CPT();` — registrira CPT-ove (kuka se na `init`).
- `Kurs_Ticker::init()` — na `kurs_data_changed` briše transient tickera.
- `Kurs_Roles::init()` — sinkronizira ovlasti (`KURS_CAP` za Author) i skriva menije urednicima.
- `add_action('plugins_loaded', ['Kurs_Database','maybe_upgrade'])` — provjera/migracija DB sheme pri svakom učitavanju.
- `if (is_admin()) { require admin/class-admin.php; new Kurs_Admin(); }` — admin sloj se učitava **samo u wp-adminu**.
- `register_activation_hook` × 3 — `Kurs_Database::install()` (kreira tablice), registracija CPT-ova + `flush_rewrite_rules()`, `Kurs_Roles::grant_caps`.
- `init` 99 radi jednokratni `flush_rewrite_rules()` preko opcije `kurs_flush_needed` (sama se briše); `init` 100 je **samoizlječenje** — ako u spremljenim `rewrite_rules` nema pravila `ekipe/…` (npr. nakon migracije baze), pravila se regeneriraju (inače `/ekipe/…`, `/igraci/…` i `/vijesti/` vraćaju 404).

> **Hook `kurs_data_changed`** (okida se iz `Kurs_Match::create/finish/reopen/delete`, `Kurs_Playoff` finish/reopen/`reset`, `Kurs_Admin` zaključavanje sezone, `ajax_update_match` i `do_delete_match` — potonji je redundantan jer ga `Kurs_Match::delete()` već okida) — ruši cache tickera (`Kurs_Ticker::flush`) i teme (`kurskc_active_league` transient). Svaka nova write operacija koja mijenja raspored/rezultat treba ga okinuti.

### 1.2 Slojevi

```
┌─ Bootstrap (kurs-liga.php) ── konstante, require, hookovi
│
├─ Podatkovni sloj
│   └─ Kurs_Database  ── DDL (CREATE TABLE), migracije
│
├─ Domenski sloj (static-only klase, global $wpdb)
│   ├─ Kurs_Season  ── fiksna struktura (COMPETITIONS, scaffold) + roster
│   ├─ Kurs_Match   ── utakmice, eventi, round-robin, tablica poretka, strijelci
│   ├─ Kurs_Playoff ── bracket, seeding, napredovanje
│   ├─ Kurs_Rounds  ── pregled po kolima (read-only: rezultati, ljestvica i strijelci nakon N. kola)
│   ├─ Kurs_Ticker  ── live ticker za header (transient cache)
│   ├─ Kurs_Roles   ── ovlast KURS_CAP za urednike
│   └─ helpers.php   ── prezentacijski helperi + dijeljeni front-end renderi
│
├─ CPT sloj
│   └─ Kurs_CPT  ── kurs_ekipa, kurs_igrac
│
└─ Admin sloj (samo is_admin())
    ├─ Kurs_Admin (class-admin.php) ── meni, hub_url()/legacy redirecti, POST forme, AJAX, asseti
    ├─ admin/views/*.php            ── prezentacija (stranica natjecanja = season-hub.php + hub-*.php)
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
│   ├── class-season.php             — Kurs_Season (COMPETITIONS + scaffold; sezone→divizije→faze→grupe→roster)
│   ├── class-match.php              — Kurs_Match (utakmice, eventi, round-robin, poredak, strijelci)
│   ├── class-playoff.php            — Kurs_Playoff (bracket, seeding, napredovanje)
│   ├── class-players.php            — Kurs_Players (registar igrača: duplikati, spajanje, brisanje) — 1.19.0
│   ├── class-rounds.php             — Kurs_Rounds (pregled po kolima) — NOVO 2026-10-16
│   ├── class-ticker.php             — Kurs_Ticker (live ticker za header)
│   ├── class-roles.php              — Kurs_Roles (KURS_CAP za urednike)
│   └── helpers.php                  — boje/badge/ikone + dijeljeni front-end renderi (ljestvica, bracket, kolo…)
└── admin/
    ├── class-admin.php              — Kurs_Admin (~46 KB: meni, HUBS/hub_url, legacy redirecti, POST, AJAX, asseti)
    ├── assets/
    │   ├── admin.css                — ~2750 linija stilova (na kraju sekcija „STRANICA NATJECANJA")
    │   └── admin.js                 — flatpickr init + jQuery delegirani eventi
    └── views/
        ├── dashboard.php            — „Pregled": kartice oba natjecanja + što traži pažnju (sve aktivne sezone)
        ├── season-hub.php           — stranica natjecanja (kurs-kc / kurs-pan): zaglavlje, kartice, uključuje hub-*.php
        ├── hub-overview.php         —   kartica Pregled (koraci, lige, čeka rezultat / sljedeće / zadnji rezultati)
        ├── hub-teams.php            —   kartica Ekipe (ekipe u ligama/skupinama, broj skupina, generiranje rasporeda)
        ├── hub-matches.php          —   kartica Utakmice (ljestvica, raspored po kolima, playoff bracket) — bivši matches.php
        ├── hub-settings.php         —   kartica Postavke (naziv/godina/status/faulovi, suci sezone, zaključavanje, brisanje)
        ├── seasons.php              — „Sve sezone": popis po natjecanju + forma „Nova sezona"
        ├── settings.php             — „Postavke": ticker izvan sezone (+ linkovi na suce u postavkama natjecanja)
        ├── teams.php                — „Ekipe": registar ekipa + podaci ekipe (logo, baneri) + popis liga → roster
        ├── players.php              — „Igrači" (1.19.0): registar igrača, podaci, karijera, spajanje
        ├── roster-panel.php         — roster ekipe u ligi (uključuje ga hub-teams.php, ?roster=)
        ├── match-record.php         — zapisnik utakmice (skrivena kurs-match-record)
        └── match-record-pdf.php     — standalone print/PDF view
```

> **Obrisano 2026-10-16 (restrukturiranje admina):** `season-setup.php` („Postavi ligu" → kartica Ekipe), `roster.php` (→ uređivanje ekipe u `teams.php`), `playoff.php` (napušten, nikad registriran), a `matches.php` je preimenovan (`git mv`) u `hub-matches.php`.

---

## 3. Podatkovni model

### 3.1 Hijerarhija domene

```
season (sezona; league = 'kc_liga' | 'pan_ljetna')
 └─ division (liga: "KC Liga", "Liga bez briga" | "Pan Ljetna liga"; has_playoff, advance_per_group)
     └─ phase (uvijek jedna: "Grupna faza", type = 'group')
         └─ group (KC: jedna skupina s imenom lige | Pan: "Grupa A".."Grupa D"; playoff_spots)
             ├─ group_teams (ekipe u grupi)
             └─ matches (utakmice grupne faze)
                 └─ match_events (golovi, autogolovi, kartoni…)

playoff_matches  ── visi izravno o division_id (NE o fazi); zapisnik = matches s group_id = 0
squads (roster)  ── igrač pripada ekipi unutar divizije
```

> Od 2026-10-16 ovu strukturu kreira **isključivo** `Kurs_Season::scaffold()` pri kreiranju sezone (vidi §4.1). Faza tipa `'playoff'` se više ne kreira — playoff živi samo u `playoff_matches`.

### 3.2 Tablice (`Kurs_Database::install`, `DB_VERSION = '1.12'`)

Stvarno ime = `$wpdb->prefix . KURS_PREFIX . <ime>` (npr. `wp_kurs_seasons`). Niže se navode kratka imena.

| Tablica | Ključni stupci |
|---|---|
| `seasons` | id, **league** (VARCHAR(100); nove sezone uvijek dobivaju ključ iz `COMPETITIONS`: `kc_liga` \| `pan_ljetna` — `is_pan_league()` ionako samo traži „pan"), name, year (SMALLINT), status (DEFAULT 'active'), **locked** (zaključana = konačni podaci), **max_fouls_per_half** (DEFAULT 5), **referees** (TEXT, JSON `[{ime, prezime}]` — baza sudaca sezone, od DB 1.10), created_at; KEY idx_status |
| `divisions` | id, season_id, name, sort_order, **has_playoff** (DEFAULT 1), **advance_per_group** (DEFAULT 2); KEY idx_season |
| `phases` | id, division_id, name, **type** (DEFAULT 'group'), sort_order; KEY idx_division |
| `groups` | id, phase_id, name, **playoff_spots** (TINYINT, DB DEFAULT 2 — ali `create_group()` uvijek upisuje vrijednost iz divizije, 0 bez playoffa); KEY idx_phase |
| `group_teams` | id, group_id, team_id (BIGINT, = post `kurs_ekipa`); **UNIQUE(group_id, team_id)** |
| `squads` | id, player_id, team_id, division_id, jersey_no (TINYINT, nullable); **UNIQUE(player_id, team_id, division_id)** — *trenutni* roster |
| `match_players` | id, match_id, team_id, player_id, jersey_no (nullable); **UNIQUE(match_id, player_id)**, KEY idx_player — **postava utakmice** spremljena pri završetku (od DB 1.12; vidi §4.2 „Postava utakmice”) |
| `matches` | id, group_id (**0 = playoff zapisnik**), home_team_id, away_team_id, home_score, away_score (nullable TINYINT), match_date (DATETIME), round (TINYINT), status (DEFAULT 'scheduled'), **referee1/referee2**, created_at; KEY idx_group, idx_status |
| `match_events` | id, match_id, team_id, player_id (nullable), **event_type** (VARCHAR(20): `goal\|penalty\|own_goal\|yellow_card\|second_yellow\|red_card\|foul` + inertni `captain`/`absent`), minute, **half** (1\|2), **scored_for** (nullable — mrtav stupac); KEY idx_match, idx_player |
| `playoff_matches` | id, division_id, **round** ('qf'\|'sf'\|'final'\|'3rd'), **slot**, home_team_id, away_team_id, home_score, away_score (sve nullable), status (DEFAULT 'scheduled'), match_date, **home_pen**/**away_pen** (penali — samo kod neriješenog, od DB 1.11), **home_label**/**away_label** (npr. "A1", "QF1-W"), **match_id** (FK → `matches.id`, zapisnik); KEY idx_division, idx_round |

### 3.3 Migracije — `Kurs_Database::maybe_upgrade()`

Usporedi `get_option('kurs_db_version')` s `DB_VERSION`; ako je niže:
- pozove `install()` — `dbDelta` idempotentno (re)kreira tablice i **dodaje stupce koji nedostaju** (`groups.playoff_spots` iz v1.2, `playoff_matches.match_id` iz v1.5, a isto tako i kasniji `locked`, `max_fouls_per_half`, `has_playoff`, `advance_per_group`, `referee1/2`, `half` do v1.9; svi su dio `CREATE TABLE` definicija).
- v1.1: `ALTER TABLE seasons MODIFY league VARCHAR(100)` (valjan MySQL `MODIFY`).
- v1.12: tablica `match_players`; zatim (uvjet je opcija `kurs_lineups_v1`, ne verzija — prekinuta migracija se ponovi) `Kurs_Match::backfill_lineups()`: svaka odigrana utakmica bez postave dobije je iz tadašnjeg rostera + igrača s događajima.

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

#### Fiksna natjecanja — `Kurs_Season::COMPETITIONS` (NOVO 2026-10-16)

Sustav služi isključivo KURS-u, pa je struktura natjecanja **konstanta**, ne podatak koji operater slaže. Nova sezona = odabir natjecanja + godine; lige, faza i skupine nastaju same (`scaffold()`).

| Ključ (`seasons.league`) | `label` | `divisions` (lige) | `groups` | `playoff` / `advance` | `span_year` | Rezultat `scaffold()` |
|---|---|---|---|---|---|---|
| `kc_liga` | KC Liga | `KC Liga`, `Liga bez briga` | `false` | `false` / 0 | `true` (zimska: „2026/2027") | 2 divizije (`has_playoff=0`), svaka faza „Grupna faza" + **jedna skupina s imenom lige**; jednokružno, bez playoffa |
| `pan_ljetna` | Pan Ljetna liga | `Pan Ljetna liga` | `true` | `true` / 2 | `false` („2026") | 1 divizija (`has_playoff=1`, `advance_per_group=2`), faza „Grupna faza" + **2 ili 4 skupine** „Grupa A"…; po 2 najbolje u playoff |

`PAN_GROUP_OPTIONS = [2, 4]` — broj skupina koje playoff motor podržava (4 → četvrtfinale, 2 → polufinale; seeding matrice 4×2 i 2×2 u §4.3).

| Metoda | Potpis | Ponašanje |
|---|---|---|
| `competition($league)` | `(string): ?array` | Definicija iz `COMPETITIONS` ili `null` (stari/slobodni nazivi lige). |
| `default_name($league,$year)` | `(string,int): string` | „KC Liga 2026/2027" (`span_year`) ili „Pan Ljetna liga 2026". |
| `find_for($league,$year)` | `(string,int): int` | ID postojeće sezone tog natjecanja i godine (0 = nema) — štiti od duplikata. |
| `scaffold($season_id,$league,$pan_groups=4)` | `(int,string,int): void` | Kreira divizije (`create_division` s `has_playoff`/`advance` iz definicije), fazu „Grupna faza" i skupine. **Idempotentno**: ne radi ništa ako sezona već ima divizije (postojeće sezone se ne diraju). `$pan_groups` izvan `[2,4]` → 4. |
| `group_phase($division_id)` | `(int): ?object` | Prva faza tipa `group` (svaka liga ima točno jednu). |
| `group_match_count($group_id)` | `(int): int` | Broj utakmica skupine. |
| `set_group_count($division_id,$count)` | `(int,int): bool\|WP_Error` | Pan 2 ↔ 4 skupine. Odbija ako `$count ∉ [2,4]`, ako **bracket već postoji** (`Kurs_Playoff::exists`) ili ako skupine koje bi se brisale imaju ekipe/utakmice (provjeri SVE prije brisanja). Dodaje prazne „Grupa X" (preskače postojeća imena) ili briše zadnje. |

> **Uklonjeno 2026-10-16:** `update_division_format()` i `update_group_playoff_spots()` — format divizije i broj prolaznika više se ne mijenjaju iz admina (dolaze iz `COMPETITIONS`).

**Sezone** (`seasons`)

| Metoda | Potpis | Ponašanje |
|---|---|---|
| `all()` | `(): array` | Sve sezone, `ORDER BY year DESC, id DESC`. |
| `active()` | `(): array` | `status='active'`, `ORDER BY year DESC`. |
| `is_pan_league($league)` | `(string): bool` | `stripos($league,'pan')` — sve ostalo = KC. |
| `for_league($is_pan)` / `current_for_league($is_pan)` | | Sezone jednog natjecanja / aktualna (prva `active`, inače najnovija) — koriste Liga hub, „Za medije" i admin (stranica natjecanja, Pregled, Sve sezone, „Igraju sada" u Ekipama). |
| `is_locked($id)` / `set_locked($id,$bool)` | | Zaključana sezona (`seasons.locked`) = konačni podaci; admin blokira izmjene. |
| `get($id)` | `(int): ?object` | Jedna sezona. |
| `get_referees($id)` / `referee_names($id)` | | **1.17.0.** Suci sezone (`seasons.referees`, JSON) kao `[ime, prezime]` retci sortirani po prezimenu (bez dijakritika) / puna imena „Ime Prezime" (prijedlozi u zapisniku). |
| `set_referees($id, $refs)` | `(int,array): void` | Prima retke `[ime, prezime]` ili puna imena („Ivan Horvat" → ime Ivan, prezime Horvat; jedna riječ = prezime). Izbacuje prazne; duplikat (bez obzira na velika slova) — vrijedi prvi. Prazna lista → `NULL`. |
| `add_referees($id, $names)` | `(int,array): void` | Dodaje puna imena koja sezona još nema (zove ga `ajax_record_set_referees` — sudac upisan u zapisniku). |
| `previous_season($id)` | `(int): ?object` | **1.18.0.** Prethodna sezona istog natjecanja (manja godina, ili ista godina i manji id) — izvor za prijenos ekipa i rostera. Dijeli privatni `earlier_seasons()` s `previous_with_referees()`. |
| `season_groups($id)` / `season_teams($id)` | | **1.18.0.** Skupine sezone (`[group_id => {id, name, division_id, division_name, groups_in_division}]`) / ekipe sezone po ligama (ključ **„division_id:team_id"** — ekipa može biti u više liga; `{team_id, team_name, division_id, division_name, group_id, group_name, players}`). |
| `carry_over_defaults($from,$to)` | `(int,int): array` | **1.18.0.** Prijedlog skupine za svaku ekipu ranije sezone: ista liga (po nazivu), skupina istog imena, a ako je nema (Pan 4 → 2 skupine) — skupina s najmanje ekipa. `[„div:team" => group_id\|0]`. |
| `carry_over_teams($from,$to,$carry,$rosters)` | `(int,int,array,bool): array` | **1.18.0.** `$carry = [„div:team" (izvor) => group_id u novoj sezoni]`. Ekipa ide u skupinu, uz `$rosters` i njezin roster iz izvorne lige s brojevima dresova (`add_to_squad`). **Preskače** ekipu koja je već u novoj sezoni (cijelu) i igrača koji u ciljnoj ligi već igra za drugu ekipu. Vraća `['teams','players','skipped']`. |
| `previous_with_referees($id)` | `(int): ?object` | Najnovija ranija sezona istog natjecanja koja ima suce — izvor za prijenos (`do_create_season` je kopira u novu sezonu) i gumb „Preuzmi iz sezone". |
| `create($data)` | `(array): int\|false` | Insert. Sanitizira `league`, `name`; `year` kroz `absint`; `max_fouls_per_half` (min 1, default 5); **status se uvijek postavlja na `'active'`** (ignorira `$data['status']`). Vraća `insert_id`. Strukturu NE kreira — to radi `scaffold()` (zove ga `Kurs_Admin::do_create_season`). |
| `update($id,$data)` | `(int,array): void` | Selektivni update polja `name, year, status, league` (+ `max_fouls_per_half`). Metoda i dalje prihvaća `league`, ali admin ga **više ne šalje** (natjecanje se ne mijenja nakon kreiranja). |
| `delete($id)` | `(int): void` | Kaskadno: za svaku diviziju `delete_division()`, pa briše sezonu. |

**Divizije** (`divisions`)

| Metoda | Ponašanje |
|---|---|
| `get_divisions($season_id)` | `ORDER BY sort_order, id`. |
| `get_division($id)` | Jedna divizija. |
| `create_division($season_id,$name,$order=0,$has_playoff=1,$advance=2)` | Insert (s `has_playoff`, `advance_per_group`). |
| `delete_division($id)` | Kaskadno: **najprije `Kurs_Playoff::reset($id)`** (bracket + playoff zapisnici s `group_id=0` + njihovi eventi — ✅ ISPRAVCI #17), zatim za svaku fazu `delete_phase()`, briše `squads` po `division_id`, pa diviziju. |

**Faze** (`phases`)

| Metoda | Ponašanje |
|---|---|
| `get_phases($division_id)` | `ORDER BY sort_order, id`. |
| `create_phase($division_id,$name,$type='group',$order=0)` | `type` whitelista — dopušteno samo `'group'`/`'playoff'`, inače fallback `'group'`. (`scaffold()` kreira samo `'group'`.) |
| `delete_phase($id)` | Kaskadno: za svaku grupu `delete_group()`, pa fazu. |

**Grupe** (`groups`)

| Metoda | Ponašanje |
|---|---|
| `get_groups($phase_id)` | `ORDER BY name`. |
| `create_group($phase_id,$name)` | Insert; **`playoff_spots` nasljeđuje iz divizije** (`advance_per_group` ako `has_playoff`, inače **0**) — zato KC lige nemaju oznaku prolaza. |
| `delete_group($id)` | **Najsloženije kaskadno brisanje**: dohvati id-eve utakmica grupe → obriši njihove `match_events` → `group_teams` → `matches` → grupu. (Koristi `$GLOBALS['wpdb']` umjesto `global $wpdb` — stilska nedosljednost.) |

**Ekipe u grupama** (`group_teams`; `team_id` = ID posta `kurs_ekipa`)

| Metoda | Ponašanje |
|---|---|
| `get_group_teams($group_id)` | Ekipe + `team_name` (JOIN na `$wpdb->posts`), `ORDER BY p.post_title`. |
| `assign_team($group_id,$team_id)` | `INSERT IGNORE` (UNIQUE sprječava duplikate u istoj skupini). Sama metoda **ne** provjerava drugu skupinu iste lige — to radi `Kurs_Admin::ajax_assign_team` preko `team_group_in_division()`. |
| `team_group_in_division($division_id,$team_id)` | **NOVO (1.16.0).** Skupina (`id`, `name`) u kojoj ekipa već igra unutar lige, ili `null`. Pravilo: ekipa igra u **najviše jednoj skupini po ligi**. |
| `team_match_count($group_id,$team_id)` | **NOVO (1.16.0).** Broj utakmica ekipe u skupini (domaćin ili gost). Dok je > 0, ekipa se ne smije maknuti iz skupine. |
| `remove_team($group_id,$team_id)` | Brisanje veze (bez provjera — guardovi su u `ajax_remove_team`). |
| `get_team_contexts($team_id)` | Sve sezone/divizije u kojima ekipa igra. Veliki `DISTINCT` JOIN `group_teams→groups→phases→divisions→seasons`, `ORDER BY s.year DESC, s.id DESC, d.sort_order, d.id`. Koristi se na uređivanju ekipe (oznake liga pod imenom, odabir lige za igrače, „← Natrag na ligu"). |

**Roster / igrači** (`squads`; igrač pripada ekipi **unutar divizije**)

| Metoda | Ponašanje |
|---|---|
| `get_squad($team_id,$division_id)` | Roster + `player_name` (JOIN posts), `ORDER BY jersey_no, post_title`. |
| `add_to_squad($player_id,$team_id,$division_id,$jersey=0)` | `$wpdb->replace` (upsert po UNIQUE). `jersey_no = $jersey ?: null` (0 → NULL). |
| `remove_from_squad($player_id,$team_id,$division_id)` | Brisanje. Odigrane utakmice čuvaju svoju postavu (`match_players`), pa micanje/prijelaz ne mijenja povijest. |
| `player_team_in_division($player_id,$division_id)` | Ekipa igrača u ligi (0 ako je nema) — pravilo: u jednoj ligi igrač igra za jednu ekipu. |

### 4.2 `Kurs_Match` — utakmice, eventi, poredak (`includes/class-match.php`)

> **Postava utakmice (1.19.0, tablica `match_players`).** Sastav ekipe na utakmici više se ne izvodi iz *trenutnog* rostera (micanje igrača iz ekipe brisalo ga je iz svih starih utakmica i iz karijere). `finish()` poziva **`save_lineup($id)`**: postava obiju ekipa = tadašnji roster lige + igrači koji za tu ekipu imaju događaj; postojeća postava se **ne prepisuje** (utakmica otvorena za ispravak zadržava sastav, samo se dopunjuje). **`lineup($match_id, $team_id, $division_id = 0)`** = spremljena postava, a dok je nema trenutni roster (+ igrači s događajima), sort po dresu pa imenu — **jedini izvor za zapisnik, PDF i javnu stranicu utakmice** (ne `get_squad`). Ostalo: `division_of($match_id)` (skupina ili playoff zapisnik → liga), `has_lineup()`, `add_to_lineup()` (izostavljeni igrač iz rostera, zapisnik → AJAX `kurs_record_add_lineup`), `backfill_lineups()` (migracija, demo podaci). Tko nije igrao i dalje je event `absent`. Brisanje utakmice/skupine/bracketa briše i njezinu postavu.
>
> **`player_career($player_id)`** (1.19.0) — karijera po sezoni × ligi × ekipi: nastupi (odigrane utakmice iz postave bez `absent`), golovi (`goal`+`penalty`), žuti, crveni (`red_card`+`second_yellow`); retci iz postava (ostaju i nakon prijelaza, `in_roster=false`) + trenutni roster ekipa uvrštenih u ligu (`in_roster=true`). Koriste ga profil igrača u temi i admin stranica Igrači.
>
> **`Kurs_Players`** (`includes/class-players.php`, 1.19.0) — registar igrača: `normalized_name()`, `duplicate_groups()` / `same_name()` (isto ime bez dijakritika), `totals($ids)` (nastupi/golovi), `current_teams($ids)` (ekipe u aktivnim sezonama), `has_history()`, **`delete()`** (samo bez utakmica/događaja; briše i rostere + zadanog kapetana), **`merge($keep, $drop)`** (rosteri, postave, događaji, kapetan prelaze na `$keep`, prazna fotografija/godina se dopune, `$drop` se briše; u zajedničkoj utakmici je igrao ako je igrao bilo koji zapis; **odbija** ako su u istoj ligi u različitim ekipama).

**Utakmice** (`matches`)

| Metoda | Ponašanje |
|---|---|
| `get($id)` | Utakmica + `home_name`/`away_name` (JOIN posts). |
| `get_by_group($group_id)` | `ORDER BY round, match_date, id`. |
| `create($group_id,$home,$away,$date=null,$round=1)` | Insert sa `status='scheduled'`; okida `kurs_data_changed`. |
| `generate_round_robin($group_id)` | **NOVO 2026-10-16** — jednokružni raspored skupine (vidi §4.6). Vraća broj NOVIH utakmica. |
| `finish($id,$home_score,$away_score)` | Postavi rezultat + `status='finished'`; okida `kurs_data_changed`. |
| `reopen($id)` | Vrati `status='scheduled'` — **NE briše rezultat** (vidi gotcha vs. playoff). |
| `delete($id)` | Briše `match_events`, pa utakmicu. |

**Eventi** (`match_events`) — whitelist u `ajax_record_add_event`: `goal, penalty, own_goal, yellow_card, second_yellow, red_card, foul` (`foul` = akumulirani prekršaj po poluvremenu). Uz to postoje **inertni** eventi `captain` / `absent` (kapetan i odsutni igrač utakmice) koji se ignoriraju u rezultatu, strijelcima i kartonima.

| Metoda | Ponašanje |
|---|---|
| `add_event($match_id,$type,$team_id,$player_id=null,$minute=null,$half=null)` | Insert. **NE upisuje `scored_for`.** |
| `get_events($match_id)` | Eventi + `player_name` (LEFT JOIN posts), `ORDER BY minute, id`. |
| `delete_event($id)` / `update_event(...)` | Brisanje / ispravak unosa. |
| `get_foul_counts`, `delete_last_foul`, `get_sent_off`, `get/set_match_captain`, `get/set_absent`, `set_referees` | Zapisnik: prekršaji, isključeni igrači, kapetan, odsutni, suci (`matches.referee1/2`). |

**Izračun rezultata iz eventa** — *nije* u domenskoj klasi nego u `Kurs_Admin::compute_score(object $match)` (privatna, `class-admin.php`):
- `goal` / `penalty` ekipe → +1 toj ekipi
- `own_goal` ekipe → +1 **protivniku** (autogol; `team_id` = ekipa igrača koji je zabio u svoju mrežu)
- kartoni / `foul` → ne mijenjaju rezultat

`match_events` su **izvor istine** za zapisnik; `matches.home_score/away_score` su **denormalizirani snapshot** koji admin upisuje kroz `Kurs_Match::finish()` pri zaključenju.

**Tablica poretka — `get_standings(int $group_id, ?int $max_round = null): array`**

Najvažnija domenska metoda. Računa se **dinamički** iz završenih utakmica (nije perzistirana, nema cachea). **NOVO 2026-10-16:** opcionalni `$max_round` daje ljestvicu „nakon N. kola" — broje se samo `finished` utakmice s `round <= N` (i kartoni iz njih, za fair-play). Koristi ga `Kurs_Rounds::standings()`.

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

**Strijelci — `get_top_scorers(int $division_id, int $limit=20): array`**

Lista strijelaca cijele **divizije** (`match_events → matches → groups → phases.division_id`, dakle **bez playoffa** jer je `group_id=0`). Broji `event_type IN ('goal','penalty')` (autogolovi se NE pribrajaju igraču), samo finished, `player_id IS NOT NULL`, `GROUP BY player_id`, `ORDER BY goals DESC LIMIT $limit`. Vraća `player_id`, `player_name`, **`team_id`** (`MAX(e.team_id)` — ✅ ISPRAVCI #18), `team_name`, `goals`.

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
| `reset($division_id)` | Briše bracket **zajedno s povezanim zapisnicima** (`matches` s `group_id=0` čiji je `id` u `playoff_matches.match_id`) i njihovim `match_events`, okida `kurs_data_changed`. Ranije su zapisnici ostajali kao siročad pa je ponovno generiranje stvaralo duplikate (✅ ISPRAVCI #17). Zove ga i `Kurs_Season::delete_division()`. |

**Zaključenje / sinkronizacija / napredovanje:**
- `finish_match($id,$home,$away,?$home_pen,?$away_pen): bool|WP_Error` (1.17.1): **neriješeno bez penala (ili s jednakim penalima) → `WP_Error('playoff_draw')`, ništa se ne sprema**. Inače rezultat (+ penali samo kod neriješenog) i `status='finished'`; ako postoji povezani zapisnik → `Kurs_Match::finish()`; zatim `advance_winner($id)`.
- **Sinkronizacija bracket ↔ zapisnik (1.18.1):** privatni `sync_record($id)` nakon `update_teams`, `update_date`, `advance_winner`, `retract_winner` — zapisnik (`matches`, group_id=0) dobiva termin, a ekipe samo dok nema događaja; slot bez obje ekipe → prazan zapisnik se briše (`match_id=NULL`). `update_teams()` vraća `WP_Error` ako zapisnik ima događaje, a ekipe bi se promijenile (`pm_error=teams`). Obrnuto: `ajax_update_match` za playoff zapisnik upisuje termin i u bracket i ne postavlja kolo.
- `Kurs_Match::division_match_ids_sql($division_id)` / `team_match_ids($team_id, $division_id)` (1.18.1): utakmice lige = skupine + playoff zapisnici — koristi ih tema (profil ekipe, karijera igrača); utakmice se ne filtriraju po ligi samo preko skupina.
- `winner_side($m): ?string` — `'home'|'away'|null` (nije završen / neriješeno bez penala); **jedino mjesto koje određuje pobjednika** — koriste ga `advance_winner`, admin i front bracket, prvak u tickeru (`compute_podium`), Pregled natjecanja i „Za medije". `pen_label($m)` → „pen. 5:4" ili `''` (bracket, zapisnik, `page-utakmica.php`, „Za medije").
- `reopen_match($id)`: prvo `retract_winner()`, zatim resetira rezultat na **NULL** i `status='scheduled'`; sinkronizira `Kurs_Match::reopen()`. (Za razliku od grupnog `reopen()`, ovdje se rezultat **briše** — nedosljedno.)
- `ensure_match_record($playoff_match_id)`: **lazy** kreira zapisnik u `matches` s **`group_id=0` (marker za playoff!)** ako meč ima obje ekipe, i poveže `match_id`. Vraća postojeći ako ga ima.
- `get_linked_match_id($id)` / `find_by_match_id($match_id)`: forward/reverse lookup veze playoff ↔ matches.

**Auto-advance:**
- `advance_winner(int $match_id)` (private): pobjednik = `winner_side()` (kod neriješenog odlučuju penali; bez pobjednika ne napreduje — `finish_match` to ionako ne dopušta). Preko `next_slot()` upiše pobjednika + label `{ROUND}{slot}-W`.
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

### 4.4 `Kurs_Rounds` — pregled po kolima (`includes/class-rounds.php`, NOVO 2026-10-16)

Read-only klasa (static, `global $wpdb`) koja sprema podatke za „N. kolo" jedne lige (divizije): rezultati kola sa strijelcima, ljestvica nakon kola (+ pomak) i strijelci nakon kola. **Isti podaci pune Liga hub (tab „Po kolima") i stranicu „Za medije"** (`Kurskc_Media_Kit` u temi) te sekciju „Iz lige" na naslovnici. Radi **samo s grupnom fazom** (JOIN `matches → groups → phases`); playoff (`group_id=0`) nema numerirana kola i prikazuje se bracketom.

| Metoda | Vraća / ponašanje |
|---|---|
| `rounds($division_id)` | Sva kola lige (`DISTINCT m.round`, uzlazno, bez `NULL`). |
| `round_status($division_id)` | `[ kolo => ['total' => n, 'finished' => n] ]` — za traku s kolima (stanje done/partial/upcoming). |
| `default_round($division_id)` | Zadnje kolo s barem jednom `finished` utakmicom; inače prvo kolo; inače 0. |
| `matches($division_id, $round)` | Utakmice kola + `group_name`, `home_name`/`away_name` (**sirovi `post_title`** — bez `&#038;` iz `get_the_title`), poredano po skupini pa terminu (`NULL` datumi zadnji). |
| `short_names($division_id)` | `player_id => kratko ime` kao u medijima: **prezime**; kad u ligi više igrača ima isto prezime, dodaje se najkraći razlikovni početak imena („M. Kolarić", „Dan. Nemčić"). Igrači s **istim imenom i prezimenom** dobiju isti oblik. Statički cache po diviziji. |
| `goals_by_match($division_id, $matches)` | `[ match_id => ['home' => [...], 'away' => [...]] ]`, stavka `['name','goals','own']`. Broji `goal`/`penalty`/`own_goal`; **autogol ide na suprotnu stranu** od `team_id` eventa. Sortirano po broju golova (stabilno — unutar istog broja redoslijed prvog gola). |
| `scorers_text($list)` | „Ranilović 3, Rac 2, Marić" (autogol s „(ag)"). |
| `byes($matches)` | `[ group_id => naziv ekipe ]` — ekipa slobodna u kolu kod **neparnog** broja ekipa; vraća se samo kad je izostala **točno jedna** ekipa (djelomično unesen raspored ne izgleda kao „slobodni"). |
| `standings($division_id, $round)` | Po skupini: `['group' => obj, 'rows' => get_standings(gid, $round) + 'move', 'unplayed' => n]`. `move` = pomak u odnosu na prethodno postojeće kolo (`>0` gore, `null` ako se prije nije igralo); `unplayed` = neodigrane utakmice s `round <= N` (nisu uračunate). |
| `scorers($division_id, ?$max_round)` | `['groups' => [ golovi => [ ['name','team_id','team'] … ] ], 'own' => [ ['name','team','goals'] … ]]` — strijelci grupirani po broju golova (silazno; unutar grupe po ekipi pa imenu, `strcoll`), autogolovi zasebno. Samo `finished`. |
| `date_label($matches)` | „subota, 28. 2. 2026." (jedan dan) ili raspon „21. – 24. 4. 2026." / „30. 4. – 2. 5. 2026."; prazno ako nema datuma. Dani u tjednu su hardkodirani (neovisno o lokalizaciji). |

> **GOTCHA:** pregled po kolima vjeruje stupcu `matches.round`. Ako je kolo krivo upisano (ekipa dvaput u istom kolu), ljestvica „nakon N. kola" i `byes()` su pogrešni — zato kartica Utakmice (`hub-matches.php`) upozorava (`.kurs-round-warn`, vidi §5.6).

### 4.5 `helpers.php` — prezentacijske funkcije i dijeljeni renderi (globalne)

| Funkcija | Ponašanje |
|---|---|
| `kurs_team_color(int $team_id): string` | Boja iz post-meta `_kurs_team_color`; validira `/^#[0-9a-fA-F]{3,6}$/`; default **`#1a3a6b`**. |
| `kurs_team_text_color(string $hex): string` | WCAG luminance `(0.299·R + 0.587·G + 0.114·B)/255`; vraća `#111317` ako `lum > 0.55`, inače `#ffffff`. Podržava 3-znamenkasti hex. |
| `kurs_team_badge_html(int $team_id, int $size=32, string $img_size='thumbnail', string $extra_css=''): string` | Badge ekipe: ako ima featured sliku → kružni `<img>`; inače obojeni krug s inicijalima (`mb_strtoupper(mb_substr(name,0,2))`, font `'Barlow Semi Condensed'`, bold italic). Veličina fonta `max(9, size*0.35)`. |
| `kurs_league_url(int $division_id, array $args = []): string` | **NOVO 2026-10-16.** URL Liga huba za diviziju: `/kc-liga/` ili `/pan-ljetna/` prema `seasons.league` (`is_pan_league`) + `season_id` + `division_id` + `$args` (npr. `['tab'=>'kola','kolo'=>5]`). Nepoznata divizija → `/kc-liga/`. Koriste ga ticker (podij), `page-utakmica.php`, „Za medije", naslovnica. |
| `kurs_render_standings(int $group_id, array $args = []): string` | Dijeljeni render ljestvice (markup `.standings`). Argumenti: `playoff_spots` (`null` = iz grupe, `0` = bez oznake prolaza), `show_title`, `title`, `limit`, `link_teams`, `show_legend` + **NOVO** `max_round` (ljestvica nakon N. kola), `rows` (gotovi redovi, npr. iz `Kurs_Rounds::standings`), `show_move` (strelica `.st-move--up/--down/--same` iz `$row['move']`). |
| `kurs_playoff_label(?string $label): string` | **NOVO.** Čitljiv naziv nepoznatog playoff sudionika: „QF1-W" → „Pobjednik ČF 1", „SF2-W" → „Pobjednik PF 2", „A1" → „1. skupine A"; prazno → „?". Koristi ga `kurs_render_playoff_bracket()` i „Za medije". |
| `kurs_render_playoff_bracket(int $division_id, array $args = []): string` | Bracket divizije (klase `.kurs-bracket*`, CSS portan u temu), bez admin akcija; meč linka na zapisnik ako je povezan. |
| `kurs_render_round_results(int $division_id, int $round): string` | **NOVO.** Rezultati kola (`.round-results`, `.rr-match`) sa strijelcima ispod utakmice; kod više skupina grupirano po skupini s datumom; neodigrano → vrijeme / „Nije odigrano (…)" / „Termin nije određen"; red „Slobodna u ovom kolu: …". |
| `kurs_render_round_scorers(int $division_id, ?int $round = null): string` | **NOVO.** Strijelci nakon kola grupirani po broju golova (`.round-scorers`, `.rs-row`) + red „AG" za autogolove. |
| `kurs_schedule_date_label(?string $datetime): string` | Hrvatska oznaka datuma rasporeda („Subota, 12. srpnja 2026."; prazno → „Termin nije određen"). |

Ostali helperi (iz ranijih izmjena, ovdje samo popis): `kurs_league_icon()` (inline SVG grbovi KC / Liga bez briga / Pan), `kurs_player_type()` / `kurs_player_type_label()`, `kurs_team_coach()` / `kurs_team_rep()` / `kurs_team_banners()` / `kurs_team_banner()` / `kurs_team_photo()`, `kurs_render_schedule()`. (Helperi `kurs_get_referees()` / `kurs_add_referees()` uklonjeni su u 1.17.0 — suci su sada `Kurs_Season::get_referees()` / `add_referees()` po sezoni.)

Helpere koriste i admin viewovi i tema (tema ih uvjetno `require`-a iz `functions.php`).

### 4.6 Round-robin raspored — `Kurs_Match::generate_round_robin()`

> ✅ **Premješteno 2026-10-16:** generator je ranije živio u AJAX handleru `Kurs_Admin::ajax_generate_fixtures()`; sada je domenska metoda `Kurs_Match::generate_round_robin(int $group_id): int`. Pozivaju je AJAX `kurs_generate_fixtures` (jedna skupina, „Generiraj sve" u kartici Utakmice — `hub-matches.php`) i POST `generate_season_fixtures` (sve skupine sezone, dno kartice Ekipe — `hub-teams.php`), a i `tools/demo-podaci.php`.

Klasični **circle method**, jednokružno (svatko sa svakim jednom):
1. Dohvati ekipe skupine (`< 2` → vraća 0); skupi postojeće parove (`min_max` ključ) da se izbjegnu duplikati — **sigurno je pokrenuti ponovno** („Dopuni raspored" za naknadno dodane ekipe).
2. Neparan broj → fiktivni "bye" igrač (`0`) → svako kolo jedna ekipa pauzira (9 ekipa = 9 kola po 4 utakmice).
3. Fiksira prvu ekipu (`$ids[0]`), ostatak (`$rotation`) rotira kroz `count-1` kola.
4. U kolu spaja `rt[i]` vs `rt[count-1-i]`; preskače parove s `0` i postojeće. Prvi par (`i=0`) u **parnim kolima zamijeni domaćina** da prva ekipa ne bude uvijek „doma".
5. `Kurs_Match::create($group_id, $h, $a, null, $round)` (datum `null` — upisuje se ručno preko ✎).
6. Rotacija: zadnji element ide na početak.

---

## 5. Admin sloj

### 5.1 `Kurs_Admin` (`admin/class-admin.php`, ~46 KB)

Jedna klasa registrira meni, asset-e, POST forme (`admin_init`) i AJAX (`wp_ajax_*`). View datoteke rade samo prezentaciju + `wp_nonce_field`; logika spremanja je u `Kurs_Admin` koja delegira na domenske klase. Capability je **svuda `KURS_CAP`** (`manage_kurs_liga`; ranije `manage_options`) — imaju je administrator i uloga Author (`Kurs_Roles`).

**Informacijska arhitektura (restrukturirano 2026-10-16, `KURS_VERSION` 1.16.0).** Vlasnik je stari admin opisao kao „mrvice razbacane posvuda" (Sezone, Utakmice, skriveni „Postavi ligu" i Roster, Ekipe), a nije bilo jasno pripadaju li ekipe i utakmice nekoj ligi. Sada:
- **svako natjecanje ima svoju stranicu** (`kurs-kc` = KC Liga, `kurs-pan` = Pan Ljetna liga) — aktualna sezona + odabir starijih, sve o sezoni u karticama **Pregled · Ekipe · Utakmice · Postavke**;
- **Ekipe** (`kurs-teams`) i **Igrači** (`kurs-players`, od 1.19.0) su **zajednički registri** (klub i igrač igraju više sezona i oba natjecanja); u ligu se ekipa uvrštava na kartici Ekipe natjecanja, a **roster** (igrači ekipe u ligi, s brojevima dresova) je na istoj kartici → „N igr.” (`?tab=ekipe&roster=&division_id=`, od 1.19.0);
- **Sve sezone** (`kurs-seasons`) = arhiva po natjecanju + kreiranje nove sezone; **Postavke** (`kurs-settings`) = opće postavke (ticker; suci su od 1.17.0 po sezoni u kartici Postavke natjecanja);
- **Pregled** (`kurs-dashboard`) = ulaz u oba natjecanja + ono što traži pažnju.

### 5.2 Admin izbornik — `menus()` (hook `admin_menu`)

Top-level: `add_menu_page('KURS Liga', 'KURS Liga', KURS_CAP, 'kurs-dashboard', [pg_dashboard], 'dashicons-shield', 25)`; prvi submenu istog sluga preimenuje ga u „Pregled". Stranice natjecanja se registriraju petljom po `Kurs_Admin::HUBS` (naslov = `COMPETITIONS[$league]['label']`).

| Slug stranice | Naslov u izborniku | Renderer | View | Vidljivost |
|---|---|---|---|---|
| `kurs-dashboard` | Pregled | `pg_dashboard()` | `dashboard.php` | vidljiva |
| `kurs-kc` | KC Liga | `pg_hub()` | `season-hub.php` (+ `hub-*.php`) | vidljiva — stranica natjecanja `kc_liga` |
| `kurs-pan` | Pan Ljetna liga | `pg_hub()` | `season-hub.php` (+ `hub-*.php`) | vidljiva — stranica natjecanja `pan_ljetna` |
| `kurs-teams` | Ekipe | `pg_teams()` | `teams.php` | vidljiva |
| `kurs-players` | Igrači | `pg_players()` | `players.php` | vidljiva (1.19.0) |
| `kurs-seasons` | Sve sezone | `pg_seasons()` | `seasons.php` | vidljiva |
| `kurs-settings` | Postavke | `pg_settings()` | `settings.php` | vidljiva |
| `kurs-match-record` | Zapisnik | `pg_match_record()` | `match-record.php` (+ `-pdf.php`) | **skrivena** (parent `null`) — otvara se iz utakmice |
| `kurs-season-setup`, `kurs-matches`, `kurs-roster` | — | `__return_null` | — | **skrivene, samo legacy** — registrirane isključivo da bi `redirect_legacy_pages()` mogao preusmjeriti stare linkove (vidi §5.2a) |

> **Uklonjeno 2026-10-16:** metode `pg_setup()`, `pg_matches()`, `pg_roster()`, `pg_playoff()` i viewovi `season-setup.php`, `roster.php`, `playoff.php`; „Postavi ligu" više ne postoji kao stranica (sadržaj je kartica Ekipe). Nikad registrirana stranica `kurs-playoff` je time nestala i iz koda.

### 5.2a Navigacija između admin stranica (statički helperi `Kurs_Admin`)

| Član | Ponašanje |
|---|---|
| `const HUBS` | `['kurs-kc' => 'kc_liga', 'kurs-pan' => 'pan_ljetna']` — slug stranice natjecanja → ključ iz `Kurs_Season::COMPETITIONS`. Koriste ga `menus()`, `dashboard.php`, `seasons.php`, `teams.php`. |
| `hub_slug($league)` | `is_pan_league($league)` ? `'kurs-pan'` : `'kurs-kc'`. |
| `hub_url($season_id, $tab = '', $args = [])` | **Kanonski način linkanja na stranicu natjecanja.** `page` se bira po `seasons.league` sezone, dodaje `season_id`, `tab` se izostavlja za `''`/`'pregled'`, `$args` se spajaju (`group_id`, `playoff_div`, notice parametri…). Nepostojeća sezona → `page=kurs-kc` bez `season_id` (stranica sama odabere aktualnu). Ne hardkodiraj `page=kurs-kc` u linkovima/redirectima. |
| `season_of_group($group_id)` | `season_id` preko `groups → phases → divisions` (0 ako ne postoji). |
| `season_of_match($match_id)` | Sezona utakmice; za playoff zapisnik (`group_id = 0`) preko `Kurs_Playoff::find_by_match_id()` → divizija. |
| `back_to($fallback)` (private) | POST `redirect_to` (`esc_url_raw`) prihvaća se **samo ako počinje s `admin_url()`**, inače `$fallback`. Koriste ga `do_add_team` i `do_toggle_season_lock`. |
| `redirect_legacy_pages()` | `admin_init` **prioritet 5** (prije `handle_forms`), samo GET zahtjevi (`empty($_POST)`) korisnika s `KURS_CAP`. Preusmjerava stare adrese uz zadržavanje parametara — tablica niže. |
| `menu_parent()` / `menu_highlight()` | Filteri `parent_file` / `submenu_file`: na skrivenom zapisniku (`kurs-match-record`) drže izbornik KURS Liga otvorenim i označe natjecanje kojem utakmica pripada (`season_of_match` → `hub_slug`). |

**Legacy preusmjeravanja (`redirect_legacy_pages`):**

| Stara adresa | Nova adresa |
|---|---|
| `kurs-season-setup&season_id=` (+ `created`, `groups_saved`, `groups_error`) | `hub_url($sid, 'ekipe', …)` |
| `kurs-matches&season_id=` (+ `group_id`, `playoff_div`, `generated`, `reset`, `generated_season`) | `hub_url($sid, 'utakmice', …)`; bez `season_id` sezona se izvodi iz `group_id` (`season_of_group`) |
| `kurs-roster&team_id=&division_id=` | `Kurs_Admin::roster_url(team, division)` → stranica natjecanja → Ekipe → roster (1.19.0) |
| `kurs-seasons&action=edit&id=` | `hub_url($id, 'postavke')` |

### 5.3 Asseti — `assets($hook)` (hook `admin_enqueue_scripts`)

Učitava samo kad `strpos($hook,'kurs-')` (hookovi su oblika `toplevel_page_kurs-dashboard`, `kurs-liga_page_kurs-kc`, a za skrivene stranice `admin_page_kurs-match-record` — zato nova admin stranica, osobito skrivena, treba slug s prefiksom `kurs-`). Učitava:
- `wp_enqueue_media()` (logo modal)
- **flatpickr** (CDN CSS+JS) + hrvatska lokalizacija `l10n/hr.js`
- Google Fonts (Barlow, Barlow Semi Condensed, Space Mono)
- `kurs-admin` CSS (`admin/assets/admin.css`) i JS (`admin/assets/admin.js`); JS ovisi o `jquery`, `flatpickr`, `flatpickr-hr`
- `wp_localize_script('kurs-admin','KursAjax', ['url'=>admin-ajax.php, 'nonce'=>wp_create_nonce('kurs_ajax')])`

**Sigurnost:** svi AJAX dijele jedan nonce **`kurs_ajax`** (`check_ajax_referer('kurs_ajax','nonce')`); svaki handler dodatno `current_user_can(KURS_CAP)` (inače `wp_die('',403)`).

### 5.4 POST forme — `handle_forms()` (hook `admin_init`)

Provjeri `KURS_CAP` + `$_POST['kurs_action']`, pa `match()` na `sanitize_key($_POST['kurs_action'])` → privatna `do_*` metoda. Svaka `do_*` ima vlastiti `check_admin_referer()` i radi `wp_safe_redirect() + exit` (PRG).

> **Uklonjeno 2026-10-16:** `add_division`, `edit_division`, `delete_division`, `add_phase`, `delete_phase` (i nonce `kurs_season_setup`) — struktura sezone nastaje automatski (`scaffold()`), a divizija se briše samo zajedno sa sezonom.

> **Redirecti od 1.16.0:** sve akcije nad sezonom, utakmicama i playoffom vraćaju na **stranicu natjecanja** preko `Kurs_Admin::hub_url($sid, $tab, $args)` (u tablici skraćeno `hub(tab, args)`). `$sid` dolazi iz POST `season_id`; gdje ga nema, izvodi se iz skupine/utakmice (`season_of_group` / `season_of_match`).

| `kurs_action` | Nonce | `do_*` | Akcija | Redirect |
|---|---|---|---|---|
| `create_season` | `kurs_create_season` | `do_create_season` | **Validira** `league` (mora postojati u `COMPETITIONS`) i `year` (2000–2100) → inače `kurs-seasons&invalid=1`; ako `find_for()` nađe postojeću sezonu → `kurs-seasons&exists=<id>`. Inače `Kurs_Season::create(['league','name'=>default_name(),'year','max_fouls_per_half'])` + `scaffold($id,$league,$_POST['pan_groups'] ?? 4)`. Forma postoji u „Sve sezone" i kao inline „Kreiraj prvu sezonu" na stranici natjecanja bez sezone. Zatim kopira suce iz `previous_with_referees()` i **starije aktivne sezone istog natjecanja (manja godina) prebacuje u arhivu** (1.17.1). | `hub('ekipe', created=1, archived=id,…)` |
| `edit_season` | `kurs_edit_season` | `do_edit_season` | `Kurs_Season::update(id,[name,year,status,max_fouls_per_half])` — **`league` se ne mijenja**. Forma je u kartici Postavke. | `hub('postavke', updated=1)` |
| `delete_season` | `kurs_delete_season` | `do_delete_season` | `Kurs_Season::delete(id)` (kaskadno; ekipe i igrači ostaju u registru). Gumb je u kartici Postavke samo za nezaključanu sezonu (handler sam ne provjerava `locked`). | `kurs-seasons&deleted=1` |
| `toggle_season_lock` | `kurs_toggle_lock` | `do_toggle_season_lock` | `set_locked(id, lock)` + `kurs_data_changed` | `back_to(kurs-seasons)` + `locked=1` / `unlocked=1` (stari `locked/unlocked/updated` se uklanjaju); kartica Postavke šalje `redirect_to` = vlastiti URL |
| `carry_over_teams` | `kurs_carry_over_teams` | `do_carry_over_teams` | **1.18.0.** `guard_unlocked`; izvor = `Kurs_Season::previous_season(season_id)` (određuje server, ne forma); `carry[„div:team"] = group_id` (0 = ne igra), `with_roster` → `carry_over_teams()` | `hub('ekipe', carried=N, carried_players=M, carried_skipped=K)` |
| `set_group_count` | `kurs_set_group_count` | `do_set_group_count` | Pan 2 ↔ 4 skupine: `Kurs_Season::set_group_count(did, count)`; zaključana sezona → samo redirect. Forma je u kartici Ekipe. | `hub('ekipe', groups_saved=1 \| groups_error=<poruka>)` |
| `generate_season_fixtures` | `kurs_generate_season_fixtures` | `do_generate_season_fixtures` | Za svaku diviziju sezone → `group_phase()` → svaka skupina → `Kurs_Match::generate_round_robin()`; postojeće utakmice se ne dupliraju; zaključana sezona → samo redirect | `hub('utakmice', generated_season=<broj novih>)` (zaključana: `hub('utakmice')`) |
| `add_match` | `kurs_add_match` | `do_add_match` | `Kurs_Match::create(gid,home,away,date,round)` | `hub('utakmice', group_id)` |
| `finish_match` | `kurs_finish_match` | `do_finish_match` | Grupna: `Kurs_Match::finish(mid,home,away)`. Playoff zapisnik: `Kurs_Playoff::finish_match(pm,home,away,home_pen,away_pen)` (sam zaključi i zapisnik); neriješeno bez penala → `pm_error=draw`, ništa se ne sprema | `from_record` → `kurs-match-record&match_id=…`; inače `hub('utakmice', group_id)` |
| `reopen_match` | `kurs_reopen_match` | `do_reopen_match` | `Kurs_Match::reopen(mid)` + sync playoff | `kurs-match-record&match_id=…` |
| `delete_match` | `kurs_delete_match` | `do_delete_match` | `Kurs_Match::delete(mid)` + `do_action('kurs_data_changed')` (dvostruko okidanje — bezopasno) | `hub('utakmice', group_id)` |
| `add_team` | `kurs_add_team` | `do_add_team` | `wp_insert_post(kurs_ekipa)` + meta `_kurs_team_color` | `back_to(kurs-teams)` + `added=1` |
| `edit_team` | `kurs_edit_team` | `do_edit_team` | `wp_update_post` + meta boje, trenera, predstavnika, kapetana | `redirect_to` (sirovi `esc_url_raw`, ne `back_to`) ili `kurs-teams&updated=1` |
| `delete_team` | `kurs_delete_team` | `do_delete_team` | **Guard (1.16.0):** ako ekipa ima ijednu utakmicu u `matches` (domaćin ili gost, bilo koja sezona) → **ne briše**. Inače briše retke `group_teams` i `squads` te ekipe, pa `wp_delete_post(id, true)`. | `kurs-teams&in_use=<N>` (notice „ne može se obrisati — možeš joj promijeniti naziv") ili `kurs-teams&deleted=1` |
| `remove_team_logo` / `remove_team_banner` / `remove_team_photo` | `kurs_remove_logo_<id>` / `kurs_remove_banner_<id>` / `kurs_remove_photo_<id>` | `do_remove_team_*` | `delete_post_thumbnail` / brisanje meta `_kurs_team_banner` / `_kurs_team_photo` | `redirect_to` ili `kurs-teams` |
| `save_team_banners` | `kurs_save_banners_<id>` | `do_save_team_banners` | meta `_kurs_team_banners` (JSON `[{id,link}]`) | `redirect_to` ili `kurs-teams` |
| `add_player` / `edit_player` | `kurs_add_player` / `kurs_edit_player_<id>` | `do_add_player` / `do_edit_player` | ime, `_kurs_player_type`, `_kurs_player_birthyear` (1940 … tekuća−5, inače se briše); isto ime → `exists=ID` | `kurs-players&action=edit` (`created` / `updated`) |
| `remove_player_photo` | `kurs_remove_player_photo_<id>` | `do_remove_player_photo` | `delete_post_thumbnail` | podaci igrača |
| `merge_players` | `kurs_merge_players_<id>` | `do_merge_players` | `Kurs_Players::merge(id, merge_from)` | podaci igrača (`merged` / `merge_error`) |
| `delete_player` | `kurs_delete_player_<id>` | `do_delete_player` | `Kurs_Players::delete` (igrač s utakmicama → `delete_error`) | `kurs-players&deleted=1` |
| `save_season_referees` | `kurs_save_season_referees` | `do_save_season_referees` | `Kurs_Season::set_referees(season_id, retci ref_ime[]/ref_prezime[])` → `seasons.referees` | `hub(postavke, refs_saved=1)` |
| `save_ticker_settings` | `kurs_ticker_settings` | `do_save_ticker_settings` | opcija `kurs_ticker_offseason` (`champions`\|`hidden`) + `Kurs_Ticker::flush()` | `kurs-settings&ticker_saved=1` |
| `generate_playoff` | `kurs_generate_playoff` | `do_generate_playoff` | `Kurs_Playoff::generate(did)` (divizija bez `has_playoff` → samo redirect na `hub('utakmice')`) | `hub('utakmice', playoff_div, generated=1)` |
| `reset_playoff` | `kurs_reset_playoff` | `do_reset_playoff` | `Kurs_Playoff::reset(did)` — briše i playoff zapisnike (✅ ISPRAVCI #17) | `hub('utakmice', playoff_div, reset=1)` |
| `finish_playoff_match` | `kurs_finish_playoff_match` | `do_finish_playoff_match` | `Kurs_Playoff::finish_match(id,home,away,home_pen,away_pen)`; greška → `pm_error=draw` (poruka iznad bracketa) | `hub('utakmice', playoff_div)` |
| `reopen_playoff_match` | `kurs_reopen_playoff_match` | `do_reopen_playoff_match` | `Kurs_Playoff::reopen_match(id)` | `hub('utakmice', playoff_div)` |
| `update_playoff_match` | `kurs_update_playoff_match` | `do_update_playoff_match` | `Kurs_Playoff::update_teams(...)` + `update_date(...)` | `hub('utakmice', playoff_div)` |

> Playoff handleri **nemaju fallback** za sezonu — oslanjaju se na skriveni `season_id` u formama `hub-matches.php`. Bez njega `hub_url(0, …)` vodi na `kurs-kc` (krivo za Pan).

**Privatni helperi klase:**
- `parse_hr_date($raw)` — `dd.mm.yyyy hh:mm` (ili `dd.mm.yyyy`, fallback `strtotime`) → MySQL `Y-m-d H:i:s`. Koristi se u `add_match`, `ajax_update_match`, `update_playoff_match`.
- `sanitize_team_color($raw)` — regex `/^#[0-9a-fA-F]{3,6}$/`, fallback `#1a3a6b`.
- `compute_score($match)` — izračun live rezultata iz eventa (vidi 4.2).
- `record_state($match_id)` — rezultat + akumulirani prekršaji + isključeni (odgovor AJAX-a zapisnika).
- `back_to($fallback)` — validirani `redirect_to` (vidi §5.2a).

### 5.5 AJAX akcije (`wp_ajax_<action>`)

Svaka: `check_ajax_referer('kurs_ajax','nonce')` + `KURS_CAP`. Greške dodjele ekipa vraćaju `wp_send_json_error(['message' => …])`, a `admin.js` poruku prikazuje kao `alert()`.

> **Uklonjeno 2026-10-16:** `kurs_add_group`, `kurs_del_group`, `kurs_update_playoff_spots` (i pripadni JS u `admin.js`).

| AJAX `action` | Metoda | Akcija / odgovor |
|---|---|---|
| `kurs_assign_team` | `ajax_assign_team` | **Guardovi (1.16.0), redom:** `group_id` mora postojati i `team_id` mora biti `kurs_ekipa` („Nepoznata ekipa."); sezona skupine ne smije biti zaključana („Sezona je zaključana."); ekipa ne smije već biti u nekoj skupini **iste lige** (`Kurs_Season::team_group_in_division`) — „Ekipa je već u ovoj ligi." / „Ekipa je već u skupini „X" — ekipa igra samo u jednoj skupini.". Zatim `assign_team(gid,tid)` → `{team_id, team_name, badge (kurs_team_badge_html 22px), roster (URL kurs-teams&action=edit&id=&division_id=)}` — JS iz toga odmah crta red s badgeom i linkom „0 igr." |
| `kurs_remove_team` | `ajax_remove_team` | **Guardovi (1.16.0):** zaključana sezona → greška; ekipa koja u toj skupini već ima utakmica (`Kurs_Season::team_match_count`) → „Ekipa već ima N utakmica u ovoj ligi. Najprije ih obriši u kartici Utakmice, pa je onda makni iz lige.". Inače `remove_team(gid,tid)`. |
| `kurs_set_logo` / `kurs_set_banner` / `kurs_set_photo` | `ajax_set_*` | `set_post_thumbnail` / meta `_kurs_team_banner` / `_kurs_team_photo` → `{url}` |
| `kurs_add_squad` | `ajax_add_squad` | `add_to_squad(player,team,division,jersey)`. Igrač koji je u toj ligi u rosteru druge ekipe → `{code:'other_team', team, message}`; JS pita „Prebaciti ga u ovu ekipu?” i ponovi s **`transfer=1`** = **prijelaz** (makne ga iz stare ekipe; njezine odigrane utakmice čuvaju postavu), odgovor ima `from`. U drugoj ligi iste sezone smije igrati. |
| `kurs_record_add_lineup` | `ajax_record_add_lineup` | utakmica otvorena za ispravak sa spremljenom postavom: igrač iz rostera te ekipe (u toj ligi) → `Kurs_Match::add_to_lineup` (1.19.0) |
| `kurs_set_player_photo` | `ajax_set_player_photo` | fotografija igrača = istaknuta slika `kurs_igrac` (provjera tipa posta i `wp_attachment_is_image`) (1.19.0) |
| `kurs_remove_squad` | `ajax_remove_squad` | `remove_from_squad(...)` |
| `kurs_quick_add_player` | `ajax_quick_add_player` | `wp_insert_post(kurs_igrac)` + opcionalno `add_to_squad` |
| `kurs_search_players` | `ajax_search_players` | LIKE pretraga `kurs_igrac` po `post_title`, LIMIT 10 → `[{id,name}]` |
| `kurs_update_squad_player` | `ajax_update_squad_player` | `$wpdb->update` na `..._squads` (jersey_no) + `wp_update_post` (ime) + tip igrača |
| `kurs_create_team` | `ajax_create_team` | provjera duplikata pa `wp_insert_post(kurs_ekipa)` → `{team_id,name}` ili `{code:'duplicate',...}` |
| `kurs_generate_fixtures` | `ajax_generate_fixtures` | jedna skupina: `< 2` ekipe → greška; inače `Kurs_Match::generate_round_robin(gid)` → `{created}` |
| `kurs_update_match` | `ajax_update_match` | `$wpdb->update` na `..._matches` (match_date, round) → formatirani datum; **okida `kurs_data_changed`** (✅ ISPRAVCI #19) |
| `kurs_record_add_event` | `ajax_record_add_event` | whitelist tipova + blokada isključenog igrača → `Kurs_Match::add_event(...)` → event + `record_state` |
| `kurs_record_del_event` / `kurs_record_update_event` | `ajax_record_*` | brisanje / ispravak eventa → `record_state` |
| `kurs_record_del_foul` | `ajax_record_del_foul` | `delete_last_foul` (undo prekršaja) |
| `kurs_record_set_captain` / `kurs_record_set_absent` / `kurs_record_set_referees` | `ajax_record_*` | kapetan / odsutni igrač / suci utakmice |

### 5.6 View datoteke

#### Stranica natjecanja — `season-hub.php` (`pg_hub`, stranice `kurs-kc` / `kurs-pan`) — NOVO 2026-10-16

„Početna" jednog natjecanja: sve o jednoj sezoni na jednom mjestu. URL: `admin.php?page=kurs-kc|kurs-pan&season_id=&tab=pregled|ekipe|utakmice|postavke` (+ parametri kartice). Gradi se isključivo preko `Kurs_Admin::hub_url()`.
- **Odabir sezone:** natjecanje iz `Kurs_Admin::HUBS[$page]`; `?season_id=` se prihvaća samo ako pripada tom natjecanju (`Kurs_Season::for_league($is_pan)`), inače aktualna (`current_for_league`). Akcent (`--hub-accent`) KC `#DA0F1E` / Pan `#F47B16`.
- **Breadcrumb** KURS Liga / natjecanje / sezona / kartica.
- **Zaglavlje `.kurs-hub-hd`:** ikona (`kurs_league_icon`), naziv natjecanja, naziv sezone, badge (🔒 Zaključana / Aktivna / Arhiva), popis liga sezone. Desno: odabir sezone (GET forma `#kurs-hub-season`, samo kad natjecanje ima > 1 sezonu; zadržava `tab`), **„Javna stranica ↗"** (`/kc-liga/` ili `/pan-ljetna/?season_id=`), **„Za medije ↗"** (`/za-medije/?liga=kc|pan&season_id=`), **„+ Nova sezona"** (→ `kurs-seasons&league=<ključ>#nova-sezona`).
- **Natjecanje bez sezone:** inline panel **„Kreiraj prvu sezonu"** (POST `create_season` sa skrivenim `league`; godina, za Pan broj skupina) i `return`.
- **Kartice `.kurs-hub-tabs`** s brojkama: Ekipe = broj različitih ekipa u sezoni; Utakmice = `odigrano/ukupno` (samo grupna faza).
- **Zaključana sezona:** banner `.kurs-hub-locked` na svim karticama osim Postavki (link na Postavke za otključavanje).
- Uključuje `hub-overview.php` / `hub-teams.php` / `hub-matches.php` / `hub-settings.php`. Varijable dostupne karticama: `$season`, `$season_id`, `$divisions`, `$is_pan`, `$comp`, `$accent`, `$is_locked`, `$hub_page` i closure `$hub_url($tab, $args)` (= `Kurs_Admin::hub_url($season_id, …)`).

**`hub-overview.php`** (kartica **Pregled**)
- **Koraci `.kurs-hub-steps`** (stanja `done` ✓ / `todo` ! / `live` ● / `wait` ·; svaki je link na mjesto gdje se radi): 1. *Ekipe u ligama* (todo dok neka skupina ima < 2 ekipe) → 2. *Raspored* → 3. *Termini* (broj utakmica bez `match_date`) → 4. *Rezultati* (todo dok postoje neodigrane utakmice s prošlim datumom → sidro `#ceka-rezultat`; live dok traje; done kad je grupna faza odigrana) → 5. *Playoff* (samo lige s `has_playoff`; done kad finale ima pobjednika — „Prvak: X") → zadnji *Zaključavanje* (todo kad je sve odigrano; done kad je sezona zaključana).
- **Kartice liga `.kurs-hub-league`** (po diviziji; boja KC `#DA0F1E` / Liga bez briga `#B07A1B` / Pan `#F47B16`): broj ekipa/skupina/utakmica; traka napretka (odigrano/ukupno) i **„N. kolo od M"** (N = zadnje kolo s rezultatom preko `Kurs_Rounds::default_round`, M = broj kola) ili „Počinje …"; **Vodi** (samo liga s jednom skupinom, prvi iz `get_standings`) ili **Prvak** (pobjednik finala); **Sljedeće** = prvo kolo s neodigranom utakmicom koja je u budućnosti ili bez termina (+ `Kurs_Rounds::date_label`); **Čeka rezultat** N. Gumbi „Utakmice i tablica", „Playoff" (Pan), „Na webu ↗" (`kurs_league_url(…, ['tab'=>'kola'])`).
- **Popisi `.kurs-hub-match`:** *Čeka rezultat* (`#ceka-rezultat`; neodigrane s datumom u prošlosti, LIMIT 12, gumb „Unesi zapisnik"), *Sljedeće utakmice* (LIMIT 8), *Zadnji rezultati* (LIMIT 8, cijeli red je link na zapisnik; uz naslov „Paket za medije ↗"). Upit obuhvaća i **playoff zapisnike** (`group_id = 0`) preko `LEFT JOIN playoff_matches pm ON pm.match_id = m.id` i `COALESCE(ph.division_id, pm.division_id)`; meta retka je „Liga · N. kolo" ili naziv playoff runde. Kratice dana su hardkodirane (`ned/pon/…`), neovisno o lokalizaciji.

**`hub-teams.php`** (kartica **Ekipe** — zamjenjuje obrisani `season-setup.php`)
- Notice: `created=1` („Sezona je kreirana s … Sad uvrsti ekipe u lige, pa generiraj raspored."), `groups_saved=1`, `groups_error=<poruka>`.
- **Zaključana sezona** → samo pregled (po skupini ekipe s brojem igrača i linkom na igrače) i `return`.
- **Preuzmi ekipe iz sezone X** (1.18.0, `<details class="kurs-panel kurs-carry">`, otvoren dok sezona nema nijednu ekipu): ekipe prethodne sezone natjecanja koje još nisu u ovoj sezoni; po retku badge, naziv, broj igrača (prošla liga u tooltipu) i `<select name="carry[div:team]">` s „— ne igra —" + skupinama sezone (predložena: `carry_over_defaults`). Kvačica „Prenesi i igrače s brojevima dresova" (zadano uključena), gumbi „Sve kao prošle sezone" / „Nijedna" (admin.js, `data-default`). Notice `carried` / `carried_players` / `carried_skipped`. Igrače koji više ne igraju operater miče iz rostera.
- **Uvod `.kurs-hub-intro`:** ekipa (klub) se upisuje jednom u „Ekipe" i može igrati više sezona — ovdje se samo uvrštava u ligu; igrači (roster) se uređuju klikom na „N igr.".
- **Roster ekipe (1.19.0)** — `?roster=TEAM&division_id=LIGA` (link „N igr.”, `Kurs_Admin::roster_url()`; ekipa mora biti u toj ligi sezone, inače se prikaže kartica): „← Sve ekipe sezone”, izbornik za prelazak na roster druge ekipe sezone (po ligama, s brojem igrača), naslov s badgeom, **`roster-panel.php`** (tablica s dresom, ↗ na podatke igrača, Uredi / ×; „Dodaj postojećeg igrača” — igrači iz druge ekipe iste lige označeni „— sada: Ekipa” = prijelaz; „Kreiraj novog igrača” s autocompleteom i duplikat-modalom) i bočni panel (kapetan, trener, „Podaci ekipe”, javni profil). Zaključana sezona → roster samo za pregled. Ovo je **jedino mjesto za uređivanje rostera**.
- **Struktura `.kurs-structure` / `.kurs-div-card`** po ligi (boja, ikona, broj ekipa, format „N skupine · po 2 najbolje u četvrtfinale|polufinale" ili „svatko sa svakim jednom · bez playoffa"). Za Pan (`comp['groups']`) forma **Broj skupina** (`set_group_count`, select iz `PAN_GROUP_OPTIONS`); kad bracket postoji → „Playoff je generiran — broj skupina je zaključan". Sezona bez divizija → poruka (obriši u Postavkama i kreiraj ponovno) i `return`.
- `window.KursTeams` (JSON za client-side autocomplete) + `<template id="kurs-pool-assign-tpl">` (gumbi „uvrsti u…" koje JS kopira za novo kreiranu ekipu).
- **Sve ekipe** (`.kurs-team-pool`, `#kurs-team-pool-list`): cijeli registar `kurs_ekipa`, **neuvrštene u ovoj sezoni prve** (zatim abecedno, `strcoll`), brojač `#kurs-pool-free-count`. Svaki `.kurs-team-pill` ima gumbe **`.kurs-pool-assign[data-group-id]`** za svaku ligu/skupinu sezone s kratkim labelama (*KC Liga* / *Bez briga* / *A B C D*). Uvrštena ekipa dobiva `.is-assigned` (prigušena, „✓" iz CSS-a, gumbi skriveni) i tooltip „Igra u: …". Ispod „+ Nova ekipa" (`#new-team-name-setup` + `#btn-create-team-setup` → `kurs_create_team`).
- **Lige i skupine** (`.kurs-groups-canvas`): po skupini `.kurs-group-col[data-group-id]` (naslov „Ekipe u ligi" kad liga ima jednu skupinu); redovi `.kurs-group-team` s badgeom, imenom, linkom **„N igr."** (`.kurs-group-team__roster`, `.is-empty` kad je 0; → `kurs-teams&action=edit&id=&division_id=`) i `×` (`.kurs-btn-rm` → `kurs_remove_team`); dodavanje pretragom (`.kurs-team-search` + `.kurs-btn-add-team` → `kurs_assign_team`).
- **Dno `.kurs-hub-next`:** kad neka skupina ima ≥ 2 ekipe → POST `generate_season_fixtures` — **„Generiraj raspored →"** (nema utakmica) ili **„↻ Dopuni raspored"** (dodaje samo parove koji nedostaju), s `confirm()`; kad ima utakmica još i „Utakmice →".

**`hub-matches.php`** (kartica **Utakmice** — bivši `matches.php`, preimenovan `git mv`)
- Parametri `?group_id=` / `?playoff_div=` **validiraju se protiv sezone** (skupina/divizija druge sezone se ignorira); zadano prva liga/skupina. Vlastitog odabira sezone više nema (to je u zaglavlju stranice natjecanja), kao ni gumba „Za medije"/„Ekipe u ligama" (sada u zaglavlju i karticama).
- Notice `generated_season=N` („Raspored je generiran — N novih utakmica…" / „Sve utakmice već postoje…").
- **Pill podnavigacija `.kurs-hub-sub`** (kad postoji > 1 izbor): lige/skupine (label = ime lige kad liga ima jednu skupinu) + **„🏆 Playoff"** za lige s `has_playoff`.
- **Grupni prikaz:** ljestvica (`get_standings`, stupci U/P/R/Pr/GR/Bod; prvih `playoff_spots` označeno `standings-playoff-cutoff`); **ime ekipe → `kurs-teams&action=edit&id=&division_id=`** (igrači u toj ligi). Raspored: „+ Dodaj ručno" (skriveni checkbox + `add_match`, datepicker `kurs-datepicker`), „↻ Generiraj sve" (`.kurs-btn-gen-fixtures` → `kurs_generate_fixtures`); utakmice po kolu sa „Zapisnik", ✎ datum/kolo (`.kurs-btn-edit-match` → `kurs_update_match`) i brisanjem (`delete_match`); upozorenje `.kurs-round-warn` „⚠ Više puta u ovom kolu: …" kad ista ekipa igra više puta u kolu. Bez skupina → „Uvrsti ekipe u lige →" (kartica Ekipe).
- **Playoff prikaz** (`?playoff_div=`): bracket (`get_bracket`, runde `qf/sf/final/3rd`); „▶ Generiraj bracket" (`generate_playoff`) / „↻ Resetiraj bracket" (`reset_playoff`, confirm upozorava da se brišu i playoff zapisnici); po meču badgevi (ili `?` TBD), rezultat, datum, „Zapisnik" (lazy `ensure_match_record()`), edit panel (rezultat `finish_playoff_match`, ekipe/labeli/datum `update_playoff_match`), za završeni meč „Otključaj" (`reopen_playoff_match`). Sve forme šalju skriveni `season_id`. Inline `<script>` za `.kurs-pm-edit-panel` (+ lazy flatpickr) ispisuje se samo u playoff prikazu.
- Ostalo je funkcionalno nepromijenjeno u odnosu na `matches.php`.

**`hub-settings.php`** (kartica **Postavke**)
- Notice `updated`, `refs_saved`, `locked`, `unlocked`.
- **Sezona** (`edit_season`): naziv, godina (KC: „Godina početka"), status (Aktivna — prikazuje se kao aktualna / Arhiva), max faulova po poluvremenu; napomena da se natjecanje ne mijenja i da je na webu aktualna najnovija *aktivna* sezona natjecanja.
- **Suci** (`save_season_referees`, od 1.17.0) — redak po sucu (Ime / Prezime, × ukloni), „+ Dodaj suca", Enter: Ime → Prezime → sljedeći/novi redak; brojač i oznaka „Nespremljene promjene" (+ `beforeunload`). Gumb „Preuzmi iz sezone X (N)" (ako postoji ranija sezona natjecanja sa sucima — `Kurs_Season::previous_with_referees()`) dodaje u popis suce koji nedostaju (klijentski, sprema se tek gumbom „Spremi suce"). JS u `admin.js` (sekcija „Suci sezone").
- **Zaključavanje** (`toggle_season_lock`, `redirect_to` = URL ove kartice; confirm samo pri zaključavanju).
- **Obriši sezonu** (opasna zona, `delete_season`, confirm) — prikazuje se samo za nezaključanu sezonu.

#### Ostale stranice

**`dashboard.php`** („Pregled", `kurs-dashboard`) — **prepisano 2026-10-16.**
- Zaglavlje: gumbi „Ekipe", „Igrači" i „Za medije ↗" (`/za-medije/`).
- **Dvije kartice natjecanja** `.kurs-comp-card--kc|pan` (petlja po `HUBS`, sezona = `current_for_league`): naziv sezone (+ „🔒 zaključana" / „arhiva"); po ligi red **„N ekipa · X. kolo od Y · vodi …"** (ili „Y kola, još se nije igralo" / „bez rasporeda"; vodeći samo za ligu s jednom skupinom); gumbi **Otvori** (stranica natjecanja) / **Utakmice** / **Ekipe** (`hub_url`).
- **Što traži pažnju** — preko svih **aktivnih nezaključanih** sezona (`s.status='active' AND s.locked=0`; i playoff zapisnici preko `playoff_matches`): *Čeka rezultat* (neodigrane s prošlim datumom, LIMIT 10, „Unesi zapisnik"), *Ovaj tjedan* (od sada do ponoći za 8 dana, LIMIT 20), *Zadnji rezultati* (LIMIT 8, link na zapisnik). Oznaka lige `.kurs-league-tag--kc|lbb|pan` + „N. kolo" ili playoff runda.
- **Uklonjeno:** stare stat kartice (aktivne sezone, broj ekipa/igrača), CTA „Dodaj igrača" / kreiranje sezone, forme tickera i baze sudaca (→ `settings.php`).

**`settings.php`** („Postavke", `kurs-settings`) — Opće postavke za sva natjecanja: **Ticker na vrhu weba — izvan sezone** (`champions` = podij prvaka / `hidden` → `save_ticker_settings`, notice `ticker_saved`). Panel **Suci** je od 1.17.0 samo uputa s linkovima na karticu Postavke aktualne KC i Pan sezone.

**`seasons.php`** („Sve sezone", `kurs-seasons`) — **prepisano 2026-10-16.**
- **Lijevo — sezone po natjecanju** (panel po `HUBS` s ikonom i „Stranica natjecanja →"): redovi `.kurs-season-row` vode na `hub_url($id)`; badgevi **„Aktualna na webu"** (= `current_for_league`), „🔒 Zaključana" / „Arhiva" / „Aktivna".
- **Desno — Nova sezona** (`#nova-sezona`, forma `#kurs-new-season`, nonce `kurs_create_season`): **Natjecanje** (`#ns-league` iz `COMPETITIONS`; `?league=` ga predodabire — link „+ Nova sezona" iz zaglavlja huba), **Godina** (`#ns-year`; za KC labela „Godina početka sezone"), za Pan **Broj skupina** (`pan_groups`, red `#ns-groups-row` skriven za KC), max faulova. Naziv se ne unosi (`default_name()`). Inline JS (`COMPETITIONS` kao JSON) ispisuje **live pregled** u `#ns-preview` („Kreirat će se **KC Liga 2026/2027**: lige KC Liga i Liga bez briga — svatko sa svakim jednom, bez playoffa.").
- Notice: `deleted`, `locked`/`unlocked` (fallback od `toggle_season_lock`), `invalid=1` („Odaberi natjecanje i ispravnu godinu"), `exists=<id>` („Sezona X već postoji — Otvori je →" na hub).
- **Forma za uređivanje sezone je uklonjena** — premještena u karticu Postavke (stari `?action=edit&id=` preusmjerava tamo).

**`teams.php`** („Ekipe”, `kurs-teams`) — dva moda:
- **Lista** (default) — **prepisano 2026-10-16.** Uvod `.kurs-hub-intro` (popis svih klubova; u ligu se uvrštava na stranici natjecanja → Ekipe, igrači se uređuju unutar ekipe za svaku ligu posebno). Filter pilule **Sve / Igraju sada / Bez lige** (`?filter=aktualne|bez`, s brojevima; „sada" = aktualne sezone oba natjecanja po `current_for_league`). Red `.kurs-team-row`: badge, ime (→ uređivanje), **oznake liga u aktualnim sezonama** (`.kurs-league-tag--kc|lbb|pan` → `hub_url(…, 'ekipe')`; uz ligu s više skupina i naziv skupine) ili „Ne igra u aktualnim sezonama", „· ranije N sezona", broj **različitih** igrača (svi rosteri ekipe), „✎ Uredi" i brisanje (`delete_team`, guard `in_use`). Desno forma **Nova ekipa** (`add_team`, color picker `kurs_color_picker_html()` — 12 preset boja + kotačić + hex). Notice `added` / `updated` / `deleted` / `in_use=N`.
- **Uređivanje** (`?action=edit&id=[&division_id=]`; `return` prije liste): breadcrumb KURS Liga / Ekipe / ekipa; ispod imena **oznake liga** (do 4 konteksta iz `get_team_contexts`, link na hub Ekipe te sezone); gumb **„← Natrag na ligu"** kad je stranica otvorena s `division_id` (iz kartice Ekipe ili ljestvice), inače „← Sve ekipe". Lijevo: Osnovno (`edit_team` — naziv, boja, trener/predstavnik, zadani kapetan), logo (`.kurs-logo-btn` → `kurs_set_logo`; `remove_team_logo`), baneri sponzora (`save_team_banners`), slika momčadi (`kurs_set_photo`). Desno (1.19.0): **Igrači po ligama** — za svaki kontekst (`get_team_contexts`) liga, sezona, broj igrača i link „Roster →” (`roster_url`); gumb „← Natrag na roster” kad je otvoreno s `division_id` lige ekipe. Ekipa bez ijedne lige → objašnjenje + gumbi „KC Liga → Ekipe” / „Pan Ljetna liga → Ekipe”.
- Roster se od 1.19.0 **ne uređuje ovdje** nego na stranici natjecanja (vidi `hub-teams.php`); stari `kurs-roster` URL vodi na roster u natjecanju.

**`players.php`** („Igrači”, `kurs-players`, 1.19.0) — dva moda:
- **Popis:** uvod, filter pilule **Svi / Igraju sada / Bez ekipe / Isto ime** (`?filter=sada|bez|duplikati`; „sada” = roster ekipe uvrštene u ligu aktivne sezone), tražilica (`?s=`, WP pretraga po imenu — collation ignorira dijakritike), tablica (ime → podaci, GLM, oznaka „isto ime”, godina rođenja, ekipe sada kao oznake lige → roster, nastupi, golovi — `Kurs_Players::totals`), paginacija po 50; desno forma **Novi igrač** (`add_player`).
- **Podaci igrača** (`?action=edit&id=`): zaglavlje (fotografija/inicijal, pozicija, godina, ukupno nastupa/golova, javni profil); lijevo **Osnovno** (`edit_player`: ime, pozicija, godina rođenja), **Fotografija** (`.kurs-player-photo-btn` → `kurs_set_player_photo`; `remove_player_photo`), **Brisanje** (samo bez utakmica); desno **Karijera** (`Kurs_Match::player_career` — ekipa u rosteru → link na roster, inače „(otišao)”) i **Spoji dvostruki upis** (`merge_players`; select s optgroupom „Isto ime” na vrhu).

**`match-record.php`** (zapisnik, `kurs-match-record`) — zahtijeva `?match_id=`. `Kurs_Match::get`. **Playoff meč se detektira po `group_id === 0`** (`$is_playoff`) i vuče info iz playoff tablice (`Kurs_Playoff::find_by_match_id`); obični mečevi JOIN-om dohvaćaju group/phase/division/season. Računa `division_id`/`season_id` + squad domaćina i gosta (`get_squad`). Ako `?format=pdf` → `include match-record-pdf.php; exit`. **Povratni link** (1.16.0) vodi na karticu Utakmice natjecanja — `hub_url($season_id, 'utakmice', ['group_id'] | ['playoff_div'])` s labelom „← <naziv sezone> · Utakmice"; izbornik pritom ostaje otvoren i označava natjecanje (filteri `parent_file`/`submenu_file`, §5.2a).
Sadržaj: **Hero scoreboard** (badge + rezultat; nezavršeno → live `$ev_home/$ev_away` iz eventa, završeno → spremljeni score; status "U TIJEKU"/"ZAVRŠENO"); **Uredi datum/sat/kolo** (`<details>` → `#btn-save-match-info` → `kurs_update_match`); **Golovi/eventi** (lista `get_events`; nezavršeno → forma "Dodaj" ekipa/igrač/tip/minuta → `#btn-add-event` → `kurs_record_add_event` + brisanje `.kurs-del-event` → `kurs_record_del_event`; tipovi `goal/own_goal/yellow_card/red_card`); **Postave** (squad tablice); **Zaključi utakmicu** (nonce `kurs_finish_match`, action `finish_match`, `from_record=1`; rezultat default `readonly` auto-računat, toggle "Ručni unos" `#finish-manual-toggle`; završeno → status + "Otključaj za ispravke" `reopen_match`); **Potpisi** (print-only: domaćin/sudac/gost). Na dnu `<script>` postavlja `window.Kurs*` (squad JSON, team id, imena, `KursMatchId`, `KursPdfUrl`). Topbar: "Ispiši" (`window.print()`), "Preuzmi PDF" (`#btn-download-pdf` → `?format=pdf` u novom prozoru).

**`match-record-pdf.php`** — standalone print/PDF view, uključen iz match-record.php kad `?format=pdf`. Naslijeđuje varijable parent scope-a (`$match`, `$events`, `$home_squad`...). Šalje vlastiti `Content-Type: text/html`, kompletan `<!DOCTYPE html>` s ugrađenim CSS-om (vlastiti `:root` tokeni, `@page { margin:0; size:A4 }`), Google Fonts. `window.onload → setTimeout(window.print, 400)`. Sadrži stripes, hero scoreboard, golove, postave, prostor za potpise, footer "KURS Liga · Koprivnica" + datum. **Nema server-side PDF generacije** — oslanja se na browserov print-to-PDF.

### 5.7 `admin.js` — ključne interakcije

Jedan IIFE inicijalizira flatpickr (hrvatski, format `d.m.Y H:i`) na `.kurs-datepicker`. Glavni `jQuery(function($){...})` delegiranim eventima (`$(document).on`) pokriva: dodjelu ekipa + client-side autocomplete (`window.KursTeams`), brzo kreiranje ekipe (handleri za grupe add/del i playoff-spots spinner **uklonjeni 2026-10-16**); color picker (`cpApplyColor`) + legacy swatch; logo/baner/slika (`wp.media` → `kurs_set_logo` / `kurs_set_photo`…); roster (autocomplete `kurs_search_players`, duplikat-modal, add/remove/inline-edit); utakmice (generiranje, inline edit); zapisnik (aktivan samo ako postoji `window.KursMatchId`: popunjavanje selecta igrača po ekipi, add/del eventa s live-score, auto/ručni toggle, PDF download, spremanje datuma/kola); helperi `h()`, `escAttr()`, `showMsg()`.

**Dodjela ekipa (kartica Ekipe, 1.16.0):**
- `assignTeam(group_id, team_id, $btn)` — jedini put do `kurs_assign_team` (koriste ga i `.kurs-btn-add-team` iz pretrage u stupcu i gumbi `.kurs-pool-assign` u popisu „Sve ekipe"). Na uspjeh dopisuje `.kurs-group-team` s `badge` HTML-om i linkom „0 igr." (`roster` URL iz odgovora), osvježi brojač stupca i pozove `markPool()`; na grešku prikaže `alert(res.data.message)` (zaključana sezona, ekipa već u drugoj skupini lige…).
- `markPool(team_id, assigned)` — prebacuje `.is-assigned` na `.kurs-team-pill` (ekipa je u bilo kojem stupcu) i osvježi `#kurs-pool-free-count`.
- `.kurs-btn-rm` → `kurs_remove_team`; na grešku (npr. ekipa već ima utakmica) `alert` poruke iz odgovora.
- Nova ekipa (`#btn-create-team-setup` → `kurs_create_team`) dodaje se na vrh popisa s gumbima kopiranima iz `<template id="kurs-pool-assign-tpl">` i ulazi u `window.KursTeams`.

> Bracket edit paneli imaju **vlastiti inline `<script>`** (na dnu `hub-matches.php`, samo u playoff prikazu) koji otvara/zatvara `.kurs-pm-edit-panel` i lazy-inicijalizira flatpickr — to **nije** u admin.js.

**`admin.css`** — na kraju je sekcija **„STRANICA NATJECANJA"** (`season-hub.php`): zaglavlje/kartice/banner huba (`.kurs-hub-hd`, `.kurs-hub-tabs`, `.kurs-hub-locked`), podnavigacija `.kurs-hub-sub` (i `--inline` za filtere), koraci `.kurs-hub-steps`, kartice liga `.kurs-hub-league`, redovi utakmica `.kurs-hub-match`, oznake liga `.kurs-league-tag--kc|lbb|pan`, gumbi popisa `.kurs-pool-assign` / `.kurs-team-pill.is-assigned`, kartice natjecanja na Pregledu `.kurs-comp-card`, redovi sezona `.kurs-season-row` i redovi ekipa `.kurs-team-row`. Stariji selektori (`.kurs-roster-*`, `.kurs-playoff-*`, `.kurs-matches-*`) i dalje postoje — dio se još koristi (npr. `.kurs-roster-table` na uređivanju ekipe, `.kurs-matches-layout` u `hub-matches.php`).

---

## 6. End-to-end workflow operatera

Sve se vodi sa **stranice natjecanja** (KURS Liga → *KC Liga* ili *Pan Ljetna liga*); kartica **Pregled** u koracima `.kurs-hub-steps` pokazuje što je sljedeće i vodi na pravo mjesto.

1. **Kreiraj sezonu** — „+ Nova sezona" u zaglavlju stranice natjecanja (→ `kurs-seasons&league=…#nova-sezona`) ili izravno u **Sve sezone**; za natjecanje bez ijedne sezone stranica natjecanja nudi inline „Kreiraj prvu sezonu". Odabiru se samo **natjecanje** i **godina** (za Pan i broj skupina 2/4); naziv, lige, faza „Grupna faza" i skupine nastaju automatski (`default_name()` + `scaffold()`); duplikat (isto natjecanje + godina) se odbija s linkom na postojeću sezonu. Redirect na karticu **Ekipe** (`created=1`).
2. **(Pan, po potrebi) broj skupina** — kartica Ekipe → forma „Broj skupina" (`set_group_count`) dok skupine koje se brišu nemaju ekipa/utakmica i dok bracket nije generiran.
3. **Ekipe u ligama** — kartica **Ekipe**: u popisu „Sve ekipe" (neuvrštene su na vrhu) klikni gumb lige/skupine uz ekipu (`.kurs-pool-assign`) ili je potraži u stupcu lige (`kurs_assign_team`); nova ekipa „+ Kreiraj ekipu" (AJAX `kurs_create_team`) ili u **Ekipe**. Ekipa igra u **jednoj skupini po ligi**; iz lige se miče `×` (`kurs_remove_team`) samo dok nema utakmica.
4. **Raspored** — na dnu kartice Ekipe „Generiraj raspored →" (`generate_season_fixtures` = `Kurs_Match::generate_round_robin()` za sve skupine sezone); kasnije „↻ Dopuni raspored" za naknadno dodane ekipe. Alternativno po skupini u kartici **Utakmice** („↻ Generiraj sve" = `kurs_generate_fixtures`) ili ručno („+ Dodaj ručno" = `add_match`). Datume/kola upiši preko ✎ (`kurs_update_match`) — korak „Termini" u Pregledu broji utakmice bez termina.
5. **Igrači (roster)** — klik na **„N igr."** uz ekipu u kartici Ekipe otvara roster te ekipe u toj ligi (na istoj kartici, `?roster=&division_id=`): dodaj postojeće/nove igrače s brojem dresa (`kurs_add_squad`, `kurs_quick_add_player`, `kurs_update_squad_player`); igrač iz druge ekipe iste lige dodaje se kao **prijelaz** (uz potvrdu). Izbornikom „Ekipa:” prelaziš na sljedeću ekipu. Podatke igrača (godina rođenja, fotografija, spajanje duplikata) uređuješ u **Igrači**.
6. **Rezultati / zapisnik** — kartica Pregled (i glavni Pregled `kurs-dashboard`) ističe **„Čeka rezultat"** → „Unesi zapisnik" otvara `kurs-match-record`: unesi golove i kartone (`kurs_record_add_event`), rezultat se live računa; zaključi (`finish_match`) → bilježi se u tablicu poretka i „Po kolima". Po potrebi otključaj (`reopen_match`). Povratak → kartica Utakmice.
7. **Za medije** — nakon kola: „Za medije ↗" u zaglavlju stranice natjecanja (ili „Paket za medije ↗" uz Zadnje rezultate) otvara javnu stranicu s paketom rezultata kola (HTML / tekst / PNG) za druge portale.
8. **Playoff (samo Pan)** — kad su skupine odigrane (korak „Playoff" postaje „Generiraj bracket"): kartica Utakmice → pilula **„🏆 Playoff"** (`playoff_div=`) → generiraj bracket iz ljestvica (`generate_playoff`; lige bez playoffa ga odbijaju), unosi rezultate (`finish_playoff_match`), ručno koriguraj parove/datume (`update_playoff_match`); zapisnik playoff meča preko lazy `ensure_match_record()`.
9. **PDF zapisnik** — iz zapisnika „Preuzmi PDF" / „Ispiši" → `?format=pdf` → browser print-to-PDF.
10. **Kraj sezone** — kartica **Postavke** → „Zaključaj sezonu" (`toggle_season_lock`; korak „Zaključavanje" u Pregledu postaje „!" kad je sve odigrano); front-end prikazuje „Završeno · konačni rezultati". Zaključana sezona u adminu: kartica Ekipe samo za čitanje, banner na ostalim karticama.

Opće postavke (ticker izvan sezone) su u **KURS Liga → Postavke** (`kurs-settings`), suci sezone u kartici **Postavke** stranice natjecanja, arhiva svih sezona u **Sve sezone**.

---

## 7. Gotchas, mrtav kod i nedosljednosti

**Mrtav kod / neregistrirano:**
- ✅ **Uklonjeno (1.16.0):** napuštena stranica `kurs-playoff` (`pg_playoff()` + `playoff.php`, nikad registrirana) obrisana je zajedno s `season-setup.php`, `roster.php` i metodama `pg_setup/pg_matches/pg_roster`. Playoff se vodi u kartici Utakmice (`hub_url($sid, 'utakmice', ['playoff_div' => $did])`).
- **Legacy slugovi `kurs-season-setup` / `kurs-matches` / `kurs-roster` moraju ostati registrirani** (`add_submenu_page(null, …, '__return_null')`): WP provjerava pristup stranici (`user_can_access_admin_page()` u `wp-admin/includes/menu.php`) **prije** `admin_init`, pa bi bez registracije stari link završio s „nemaš ovlasti" umjesto da ga `redirect_legacy_pages()` preusmjeri.
- **`match_events.scored_for`** (komentar 'za autogol: tim koji prima gol') postoji u shemi, ali se **nigdje ne upisuje ni čita** — `add_event` ga ne postavlja, `compute_score` ga ne koristi.
- **Round `'3rd'`** (utakmica za 3. mjesto) postoji u enum komentaru i u `ORDER BY FIELD(...,'3rd')`, ali se **nigdje ne generira ni puni** — mrtva grana.

**Separacija slojeva:**
- ✅ **Round-robin generator premješten u domenu (2026-10-16):** `Kurs_Match::generate_round_robin()`; `ajax_generate_fixtures` i `do_generate_season_fixtures` samo ga pozivaju.
- Izračun rezultata (`compute_score`) je i dalje u **admin klasi**, ne u `Kurs_Match`. Domenska klasa samo sprema gotov rezultat (`finish`). Ista pravila (goal/penalty +1, own_goal +1 protivniku) ponavljaju se u `Kurs_Rounds::goals_by_match()` — kod promjene bodovanja golova mijenjaj oba mjesta.

**Fiksna struktura (2026-10-16) — na što paziti:**
- Natjecanja i njihov format su **u kodu** (`Kurs_Season::COMPETITIONS`), ne u bazi. Nova liga/format = izmjena konstante (+ eventualno seeding matrice u `Kurs_Playoff` za drugi broj skupina).
- `scaffold()` radi samo za sezonu **bez** divizija — postojeće (starije) sezone se ne prepravljaju. Stranica natjecanja bira sezone po `is_pan_league()` (sve što nije „pan" ide pod KC), a `$comp` uzima iz `HUBS` ključa stranice, ne iz `seasons.league` — zato se npr. forma „Broj skupina" u kartici Ekipe prikazuje za svaku sezonu na Pan stranici, a opis formata lige čita `divisions.has_playoff`.
- Natjecanje sezone se nakon kreiranja ne mijenja kroz admin (o njemu ovise lige i skupine).
- Divizija se ne može obrisati zasebno (akcija `delete_division` uklonjena) — samo s cijelom sezonom.
- `set_group_count()` je jedini način promjene skupina; odbija kad playoff postoji ili kad uklonjene skupine imaju ekipe/utakmice.

**Admin nakon restrukturiranja (1.16.0) — na što paziti:**
- **Linkovi na stranicu natjecanja** uvijek preko `Kurs_Admin::hub_url($season_id, $tab, $args)` — `page` (`kurs-kc`/`kurs-pan`) ovisi o ligi sezone. Hardkodirani `page=kurs-kc&season_id=<Pan sezona>` ne puca, ali tiho prikaže **aktualnu KC sezonu** (sezona mora pripadati natjecanju stranice).
- **Integritet ekipa (guardovi su u adminu, ne u domeni):** ekipa najviše u jednoj skupini po ligi (`ajax_assign_team` + `team_group_in_division`), ne miče se iz skupine dok ima utakmica (`ajax_remove_team` + `team_match_count`), ne briše se dok ima ijednu utakmicu (`do_delete_team`). `Kurs_Season::assign_team()/remove_team()` i `wp_delete_post()` sami ništa ne provjeravaju — novi kod koji ih zove mora ponoviti provjere. `do_delete_team` broji samo `matches` (domaćin/gost); ekipa koja je samo u `playoff_matches` bez zapisnika teoretski prolazi (u praksi se zapisnik kreira čim se bracket s obje ekipe prikaže — `ensure_match_record()`).
- **Zaključana sezona** blokira na serveru samo: dodjelu/uklanjanje ekipa (AJAX), `set_group_count` i `generate_season_fixtures`. Kartica Ekipe je samo za čitanje, a ostale kartice imaju banner — ali `add_match`, `delete_match`, playoff akcije i zapisnik **nisu** blokirani (ni `delete_season`, čiji je gumb samo skriven).
- **Brojke se računaju na više mjesta:** „N. kolo od M", vodeći i „Čeka rezultat" postoje i u `dashboard.php` i u `hub-overview.php` (sličan SQL s `COALESCE(ph.division_id, pm.division_id)`), a boja/oznaka lige po `stripos($name, 'briga')` ponavlja se u `hub-overview.php`, `hub-teams.php`, `dashboard.php` i `teams.php`. Kod izmjene pravila mijenjaj sva mjesta.

**Nedosljednosti:**
- **`reopen` ponašanje (namjerno):** `Kurs_Match::reopen()` vraća status na scheduled ali **ne briše** `home_score/away_score` — operater zadržava uneseni rezultat pri otključavanju, a `get_standings()` ionako broji samo `status='finished'`. `Kurs_Playoff::reopen_match()` ih postavlja na NULL. Vidi ISPRAVCI ("još otvoreno").
- **`Kurs_Season::create()`** ignorira proslijeđeni `status` (uvijek `'active'`), dok `update()` ga dopušta — nepotpuna simetrija.
- **`build_seeding_pairs`** koristi `count($groups)` i `$groups[0]->playoff_spots` — pretpostavlja da SVE grupe imaju isti broj playoff mjesta. Ako se razlikuju, seeding može biti pogrešan: `generate` koristi per-grupu spots za labele, ali globalni `spots[0]` za parove. (Od 2026-10-16 to je u praksi zajamčeno: sve skupine nasljeđuju `advance_per_group` divizije, a `playoff_spots` se više ne uređuje po grupi.)
- **`delete_group`** koristi `$GLOBALS['wpdb']` umjesto `global $wpdb` (stilska razlika od ostatka klase).
- ✅ **Neriješen playoff rezultat** (1.17.1): penali (`playoff_matches.home_pen/away_pen`) obavezni kod neriješenog; prije je dalje tiho išao gost (ISPRAVCI #24).
- **Zaključana sezona (1.17.1):** `Kurs_Admin::guard_unlocked($season_id, $back)` na početku svakog handlera koji mijenja utakmice, zapisnike, playoff, rostere ili briše sezonu — forma se vraća na `$back` bez izmjene, AJAX vraća `{code:'season_locked', message}` (admin.js ga prikaže jednim `ajaxSuccess` handlerom). Sučelje (kartica Utakmice, playoff, zapisnik) u zaključanoj sezoni skriva gumbe za izmjene. Nova write akcija → dodaj `guard_unlocked`.
- **Datumi:** novi Pregled (`dashboard.php`) i kartica Pregled koriste `date()` s vlastitim kraticama dana (`ned/pon/…`) i `current_time('mysql')` za „sada" — neovisno o lokalizaciji WP-a; ostatak admina mjestimice koristi `date_i18n`. *(✅ `match-record-pdf.php` footer ispravljen na `date_i18n('j.n.Y. \u H:i')` — ISPRAVCI #4.)*
- **`ajax_search_players`** radi LIKE od 1 znaka, dok JS autocomplete traži min 2 znaka.
- ✅ **ISPRAVLJENO (ISPRAVCI #5):** u `matches.php` (danas `hub-matches.php`) je naziv tablice `'kurs_groups'` bio hardkodiran umjesto `KURS_PREFIX` konstante; sada koristi `$wpdb->prefix . KURS_PREFIX . 'groups'`.
- `redirect_to` iz `$_POST`: `do_add_team` i `do_toggle_season_lock` ga validiraju kroz `back_to()` (samo `admin_url()`), dok `do_edit_team`, `do_remove_team_*` i `do_save_team_banners` i dalje uzimaju sirovi `esc_url_raw` — open-redirect rizik ublažen jer `wp_safe_redirect` dozvoljava samo lokalne hostove.

**Duplikacija koda:**
- ✅ Riješeno brisanjem (1.16.0): bracket iz `playoff.php` (sada samo u `hub-matches.php`) i roster UI iz `roster.php` (sada samo u edit-modu `teams.php`).
- Admin bracket (`hub-matches.php`, s akcijama) i front-end `kurs_render_playoff_bracket()` (helpers.php, bez akcija) su odvojene implementacije istog prikaza.

**Neusklađenost s `CLAUDE.md` (povijesno — ✅ CLAUDE.md ispravljen 2026-06-22, dopunjen 2026-10-16):**
- `CLAUDE.md` je ranije opisivao **temu** `kurskc` (CPT-ovi `momcad`/`utakmica`/`vijest`, taksonomije `liga`/`sezona`). Plugin `kurs-liga` je **odvojen model podataka**: vlastiti CPT-ovi `kurs_ekipa`/`kurs_igrac` i vlastite DB tablice (ne taksonomije) za sezone/divizije/utakmice. Dva odvojena sloja istog projekta.
