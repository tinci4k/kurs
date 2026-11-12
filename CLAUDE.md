# kurskc.hr — WordPress projekt

## O projektu
Web stranica za **KURS — Koprivnička udruga za rekreativni sport**, koja organizira
malonogometna natjecanja:
- **KC liga** — zimska dvoranska liga (divizije: *KC Liga* / *Liga bez briga*; svaka jedna
  skupina, svatko sa svakim jednom, bez playoffa)
- **Pan ljetna liga** — ljetni turnir na travi (jedna liga, 2 ili 4 skupine, po 2 najbolje u playoff)

Sustav služi **isključivo KURS-u** — struktura natjecanja je **hardkodirana** u
`Kurs_Season::COMPETITIONS` (`includes/class-season.php`). Nova sezona = natjecanje + godina;
lige, faza „Grupna faza" i skupine nastaju same (`Kurs_Season::scaffold()`).

Sustav ima **dvije vlastite komponente**:
1. **Plugin `kurs-liga`** — cijela natjecateljska domena (sezone, divizije, faze, grupe,
   utakmice, događaji, playoff, pregled po kolima) + admin sučelje za vođenje lige.
2. **Tema `kurskc`** — front-end prikaz tih podataka na kurskc.hr.

> 📚 **Detaljna dokumentacija je u `docs/`** (na hrvatskom, za AI onboarding).
> Počni od [`docs/README.md`](docs/README.md). Za dubinu: `docs/ARHITEKTURA.md`,
> `docs/BAZA-PODATAKA.md`, `docs/PLUGIN-KURS-LIGA.md`, `docs/TEMA-KURSKC.md`,
> `docs/POSTAVKE-I-KONVENCIJE.md`. Changelog ispravaka grešaka: `docs/ISPRAVCI.md`.

## Tehnički stack
- **WordPress** lokalno via Local by Flywheel
- **Lokalni URL**: http://kurshr.local · **Produkcija**: https://kurskc.hr
- **WP korijen**: `app/public/`
- **Plugin**: `app/public/wp-content/plugins/kurs-liga`
- **Tema**: `app/public/wp-content/themes/kurskc` (vlastita tema od nule)
- **PHP**: 8.1+ (Local koristi 8.2) · **DB**: `local` (root/root), prefiks tablica `wp_`,
  MySQL na `127.0.0.1:10011`

## Model podataka

### Custom post typeovi (CPT)
| Slug | Naziv | Registrira | Rewrite | Napomena |
|------|-------|-----------|---------|----------|
| `kurs_ekipa` | Ekipe | **plugin** (`includes/class-cpt.php`) | `/ekipe/` | naziv = `post_title`, logo = istaknuta slika (jedan), boja dresa = postmeta `_kurs_team_color` (+ `_kurs_team_color2` za dvobojni, 1.32.0), sponzori ekipe = `_kurs_team_banners` (najviše 3) |
| `kurs_igrac` | Igrači | **plugin** (`includes/class-cpt.php`) | `/igraci/` | |
| `vijest` | Vijesti | **tema** (`inc/post-types.php`) | `/vijesti/` | taksonomija `vijest_kategorija` |

> CPT-ovi **nemaju** taksonomije `liga`/`sezona`. Natjecateljska struktura je u
> **custom tablicama**, ne u taksonomijama. `seasons.league` = `kc_liga` | `pan_ljetna`.

### Custom tablice (`wp_kurs_*`, kreira `includes/class-database.php`)
Hijerarhija:
```
seasons → divisions → phases → groups → group_teams (ekipe u grupi)
                                      → matches → match_events (golovi/kartoni)
divisions → playoff_matches (bracket: qf/sf/final; zapisnik = matches s group_id=0)
squads = roster (igrač × ekipa × divizija) — TRENUTNI (+ base_goals: preneseni golovi)
group_teams.base_* = preneseno stanje ekipe (kola odigrana prije unosa u sustav), rank_hint = poredak kod jednakih bodova
match_players = postava utakmice (spremljena pri završetku; povijest ne ovisi o rosteru)
```
Stupci `*_team_id` / `*_player_id` su `wp_posts.ID` CPT-ova `kurs_ekipa`/`kurs_igrac`
(**nema** DB foreign-key constraintova — integritet samo u kodu).

## Front-end stranice (auto-kreira `themes/kurskc/functions.php`)
| Slug | Template | Sadržaj |
|------|----------|---------|
| `kc-liga` | `page-liga.php` | Liga Hub za KC ligu — tabovi `tablica` / `kola` („Po kolima") / `raspored` / `statistika`; `?tab=`, `?season_id=`, `?division_id=` (validira se protiv sezone), `?kolo=N\|playoff` |
| `pan-ljetna` | `page-liga.php` | Liga Hub za Pan ljetnu (isti template, razlikuje se po slugu) |
| `utakmica` | `page-utakmica.php` | Detalj utakmice (`?match_id=X`; radi i za playoff mečeve) |
| `za-medije` | `page-za-medije.php` | Paket rezultata kola za druge portale (HTML/tekst/PNG); `?liga=kc\|pan&season_id=&kolo=` |
| `o-kurs-u` | `page-o-nama.php` | Statična stranica o udruzi |
| `zapisnik` | **plugin** `front/zapisnik.php` (`Kurs_Zapisnik`, rewrite, nije WP stranica) | Zapisnik za **zapisničare** (1.24.0): prijava → popis utakmica (čeka rezultat / nadolazeće / nedavno završene) → isti zapisnik kao u adminu (`?match_id=`), bez wp-admina i teme |
| `portal-ekipe` | **plugin** `front/portal.php` (`Kurs_Portal`, rewrite, `?e=ID&k=` ključ po ekipi ovisan o solima + `_kurs_portal_v`; linkovi u admin → KURS Liga → Zahtjevi ekipa → Linkovi) | **Portal ekipe** (1.31.0): ekipa provjerava/ispravlja roster aktualne lige, šalje logo, sponzore ekipe, kontakt voditelja/trenera i poruku — sve kao **zahtjev** (`wp_kurs_requests`) koji KURS odobrava; tek odobrenje (`Kurs_Portal::apply()`) mijenja roster / istaknutu sliku / `_kurs_team_banners` / `_kurs_team_rep*`/`coach*` |
| `presjek` | **plugin** `front/presjek.php` (`Kurs_Presjek`, rewrite, `?k=` ključ ovisan o solima — link iz admin → KURS Liga → Presjek ekipa) | Presjek ekipa i rostera nove sezone za KURS (1.27.0; snimka `front/presjek-data.json`, ne baza) + odgovori na otvorena pitanja u opciji `kurs_presjek_odgovori` + **sponzori lige** koje šalje KURS (1.30.1, opcija `kurs_presjek_sponzori` ključ 0) — **migracija baze ih pregazi** |

## Admin (wp-admin → KURS Liga; plugin `admin/class-admin.php` + `admin/views/`)
Organiziran **po natjecanjima** (od `KURS_VERSION` 1.16.0), sve iza `KURS_CAP`:
| Slug | Stranica |
|------|----------|
| `kurs-dashboard` | **Pregled** — oba natjecanja (aktualna sezona) + „Čeka rezultat" / „Ovaj tjedan" / „Zadnji rezultati" |
| *(Nadzorna ploča WP)* | **Widget „KURS — utakmice i zapisnici"** (`dashboard-widget.php`, 1.22.0) — prvo što se vidi nakon prijave: čeka rezultat + sljedeća dva dana igranja s gumbom za zapisnik; urednicima jedini widget |
| `kurs-kc`, `kurs-pan` | **Stranica natjecanja** (`season-hub.php`) — sezona (`?season_id=`, zadano aktualna) u karticama `?tab=` pregled / ekipe / utakmice / postavke (`hub-overview/teams/matches/settings.php`; ekipe = uvrštavanje u lige (stupci liga + ekipe bez lige; uvijek potvrda i „Poništi”) + **prijenos ekipa i rostera iz prošle sezone** + **roster ekipe** `&roster=&division_id=` (`roster-panel.php`, link `Kurs_Admin::roster_url()`); postavke = sezona, **suci sezone**, zaključavanje) |
| `kurs-teams` | **Ekipe** — zajednički registar klubova (logo, boja, trener, kapetan, sponzori) + popis liga s linkom na roster |
| `kurs-players` | **Igrači** — registar igrača (`players.php`, `Kurs_Players`): tražilica, pozicija/godina rođenja/fotografija, karijera, spajanje dvostrukih upisa |
| `kurs-seasons` | **Sve sezone** — arhiva po natjecanju + „Nova sezona" |
| `kurs-stats` | **Statistika** posjeta (1.30.0, `includes/class-stats.php` `Kurs_Stats` + `admin/views/stats.php`): vlastito anonimno brojanje **bez kolačića** (beacon iz `wp_footer` → REST `kurs/v1/posjet` → tablica `wp_kurs_stats`; IP se ne sprema, dnevni hash s dnevnom soli; prijavljeni se ne broje); rasponi, posjeti/pregledi, izvori, uređaji, dan u tjednu, top utakmice/ekipe/igrači/vijesti; „Ispiši / PDF izvještaj” za sponzore. **Migracija lokalne baze na produkciju pregazi produkcijsku statistiku.** |
| `kurs-requests` | **Zahtjevi ekipa** (1.31.0, `Kurs_Portal`, `admin/views/requests.php`): na čekanju po ekipi (Odobri / Odbij s napomenom / Odobri sve), riješeno, **tajni linkovi ekipa** (Kopiraj, „Novi link” poništi stari); brojač u izborniku |
| `kurs-sponsors` | **Sponzori** lige (1.31.0, `Kurs_Sponsors`, `admin/views/sponsors.php`): CPT `sponzor` iz teme u KURS sučelju (kartice, razina, natjecanje `_kurs_sponsor_comp`, redoslijed ↑↓, sakrij, ukloni + Poništi, „Dodaj na web” za sponzore iz presjeka); WP ekrani CPT-a preusmjeravaju se ovamo (tema: `show_in_menu => false`) |
| `kurs-settings` | **Postavke** — ticker izvan sezone (suci se vode po sezoni: stranica natjecanja → Postavke) |

Skriven je `kurs-match-record` (zapisnik). **Prijava** (wp-login.php i /zapisnik/) je u KURS stilu s logom (`Kurs_Branding`, `assets/kurs-logo-white.png`, hrvatski tekstovi preko `gettext` samo na login ekranu). **Uloga Zapisničar** (`kurs_zapisnicar`, samo `KURS_RECORD_CAP = 'kurs_zapisnik'`) vodi zapisnik na `/zapisnik/` i ne ulazi u wp-admin; AJAX `kurs_record_*` i forme završi/otvori za ispravak traže `KURS_RECORD_CAP` (imaju je i admin i urednik), promjena datuma/kola ostaje na `KURS_CAP`. Unos u zapisniku je **po ekipama** (1.25.0, `#kurs-rc`: svaka ekipa Gol · Žuti · Crveni · Prekršaj, klik na igrača odmah sprema, minuta neobavezna, „Poništi”). PDF zapisnika (`match-record-pdf.php`, 1.26.0) je službeni obrazac na **jednoj A4** (sam se smanji ako ne stane; `&prazni=1` = prazni obrazac s popisom igrača). Linkove/redirecte na stranicu natjecanja gradi
**`Kurs_Admin::hub_url($season_id, $tab, $args)`** (bira `kurs-kc`/`kurs-pan` po ligi sezone) — ne hardkodiraj
`page=`. Stari slugovi `kurs-season-setup` / `kurs-matches` / `kurs-roster` (i `kurs-seasons&action=edit`)
samo se preusmjeravaju (`redirect_legacy_pages()`) i moraju ostati registrirani.

## Razvojne napomene
- `WP_DEBUG` je uključen, log se piše u `app/public/wp-content/debug.log`.
- **Naming konvencije**: `KURS_PREFIX = 'kurs_'` (tablice `wp_kurs_*`, CPT `kurs_*`,
  postmeta `_kurs_*`); tema koristi prefiks `kurskc_` / `kurs_`. Uvijek koristi
  `$wpdb->prefix . KURS_PREFIX . 'tablica'`, nikad hardkodirano ime tablice.
  Admin ovlast je `KURS_CAP` (`manage_kurs_liga`), ne `manage_options`.
- **CSS** koristi varijable u `:root` (`themes/kurskc/assets/css/main.css`) — mijenjaj
  boje samo tamo. Glavne boje:
  `--c-kc: #DA0F1E` (KC, crvena), `--c-pan: #F47B16` (Pan, narančasta),
  `--c-gold: #FDE206`, `--c-dark: #111317`, `--c-bg: #F4F4EE`.
  Fallback boja ekipe (`includes/helpers.php`) je `#1a3a6b`.
- **Domenska logika**: ljestvica/bodovanje u `includes/class-match.php::get_standings($gid, ?$max_round)`
  (3-1-0, UEFA/HNS razrješavanje; `$max_round` = ljestvica nakon N. kola); **round-robin
  raspored** je `Kurs_Match::generate_round_robin()`; **izračun rezultata iz golova** je i dalje
  u `admin/class-admin.php::compute_score()` (ne u domenskoj klasi).
- **Pregled po kolima**: `includes/class-rounds.php` (`Kurs_Rounds`, read-only) — jedan izvor
  za Liga hub (tab „Po kolima"), naslovnicu („Iz lige") i „Za medije" (`themes/kurskc/inc/media-kit.php`).
  Ovisi o ispravnom `matches.round`.
- **URL lige iz divizije** uvijek preko `kurs_league_url($division_id, $args)` (helpers.php) —
  nikad hardkodirani `/kc-liga/`.
- **Ime ekipe/igrača na frontu** uvijek kao poveznica: `kurs_team_link($id, $name, $div)` / `kurs_player_link($id, $name)`
  (helpers.php, 1.29.0). U retku koji je i sam link (utakmica) koristi `.has-row-link` + `<a class="kc-row-link">` — nikad `<a>` u `<a>`.
- **Ugradnja kola/ljestvice u vijest** = blok `kurs/liga` (tema `inc/embed-block.php`, 1.0.47); stari `_kurs_embed` samo za postojeće vijesti.
- **`kurs_data_changed`** — okini nakon svake promjene rezultata/rasporeda (ruši cache tickera
  i `kurskc_active_league`).
- **Zaključana sezona** — svaki admin handler koji mijenja utakmice, zapisnik, playoff ili roster
  počinje s `self::guard_unlocked( $season_id, $back )` (`admin/class-admin.php`); novi handler isto.
- **Preneseno stanje** (kola odigrana prije unosa u sustav): `group_teams.base_w/d/l/gf/ga` (+ `rank_hint`) i
  `squads.base_goals/base_own_goals` — pribrajaju ih `get_standings()`, `get_top_scorers()`, `Kurs_Rounds::scorers()`,
  `player_career()`, `Kurs_Players::totals()` i profil ekipe. Novi izračun golova/ljestvice mora ih uključiti.
  Admin: stranica natjecanja → Ekipe → „Preneseno stanje” i roster → Uredi → „Preneseni golovi”.
  `divisions.base_round` = kolo do kojeg su preneseni golovi postignuti — lista strijelaca nakon ranijeg kola
  (`Kurs_Rounds::scorers($div, $max_round)`) ih ne pribraja (KC 2025/2026: 3, golovi 2./3. kola bez poznate utakmice).
- **Sastav ekipe na utakmici** samo preko `Kurs_Match::lineup($match_id, $team_id)` (postava spremljena pri
  završetku u `match_players`), **ne** `Kurs_Season::get_squad()` (to je trenutni roster). Karijera igrača =
  `Kurs_Match::player_career()`. Prijelaz igrača u drugu ekipu iste lige = `kurs_add_squad` s `transfer=1`.
- **Pobjednik playoff meča** samo preko `Kurs_Playoff::winner_side()` (kod neriješenog odlučuju
  penali `playoff_matches.home_pen/away_pen`) — nikad `home_score > away_score`.
- **Ovisnost teme o pluginu**: `functions.php` uvjetno učita plugin `helpers.php`, a
  header/front-end rade direktne `$wpdb` upite po `wp_kurs_*`. Bez aktivnog plugina
  front-end degradira.
- **Demo podaci (samo lokalno)**: `tools/demo-podaci.php` — puni najnoviju KC sezonu (9 kola,
  kola 1–5 odigrana) i dodaje strijelce u Pan utakmice; `… tools/demo-podaci.php obrisi` briše
  (opcija `kurs_demo_seed`, meta `_kurs_demo`). **Destruktivno** za tu KC sezonu. Pokretanje (Git Bash):
  `PHPRC="C:/Users/Tinci/AppData/Roaming/Local/run/df7bZ9tGl/conf/php" "/c/Users/Tinci/AppData/Roaming/Local/lightning-services/php-8.2.30+1/bin/win64/php.exe" tools/demo-podaci.php [obrisi]`
  (bez `PHPRC` CLI ne nađe MySQL port 10011). Demo podaci su **obrisani** (2026-11-02); lokalno su
  **stvarni podaci Pan Ljetne lige 2026** (iz službenih izvještaja; kolo = natjecateljski dan; zaključana), **KC liga 2025/2026**
  (svih 9 kola kao stvarne utakmice — izvještaji s FB stranice KC lige; dio golova 2./3. kola kao preneseni golovi igrača;
  arhivirana i zaključana) i **početna KC 2026/2027** (ekipe i rosteri prošle sezone; Gudsport u KC Ligi zamijenjen novom ekipom „Knjigovodstveni obrt Doroteja” s rosterom bez brojeva, KURS u LBB novom „ENDURA fit”, CB Beertija odustala → KC Liga 9, LBB 9 ekipa; HEP preimenovan u HEP-ODS; start 14. 11. 2026.,
  službeni raspored 9 kola do 13. 2. 2027. unesen 2026-11-12; rosteri prema popisima ekipa za 12 ekipa — ETM, Inducta, Doroteja, Robin Marketi, ZIP Drnje, 193, Čekićari, Profesori, coARt, Forma Legalis, Bimbo, Romantičari — ostalih 6 preneseno, pregled na `/presjek/`) — demo skriptu ne pokretati jer briše najnoviju KC sezonu.
- **Local gotcha**: nginx round-robin na **dva php-cgi workera** (10008/10009,
  `fastcgi_read_timeout 1200s`) — ako jedan zapne, **svaki drugi zahtjev visi** → restartaj site u Localu.

## Poznata ograničenja (nisu bugovi, namjerno/dokumentirano)
- `match_events.scored_for` je mrtav stupac. Playoff runda `3rd` (utakmica za 3. mjesto) se **ne generira**
  (`Kurs_Playoff::generate()` ne stvara slot), ali se prikazuje ako postoji: bracket ispod finala, ticker
  (3. mjesto), admin Utakmice; za Pan 2026 upisana je ručno.
- Admin više nema ručno dodavanje divizija/faza/grupa ni `playoff_spots` (sve iz `COMPETITIONS`);
  Pan broj skupina (2↔4) mijenja se na stranici natjecanja → kartica **Ekipe** dok skupine nemaju
  ekipa i dok nema bracketa.
- Integritet ekipa čuvaju **admin handleri** (nema FK): ekipa je u najviše jednoj skupini po ligi, ne
  miče se iz lige dok ima utakmica i ne briše se dok ima ijednu utakmicu (`ajax_assign_team`,
  `ajax_remove_team`, `do_delete_team`). Domenske metode same ništa ne provjeravaju.
- Verzije se razlikuju namjerno: plugin header `1.2.0` vs `KURS_VERSION=1.34.0` vs
  `DB_VERSION=1.16`; tema `KURSKC_VERSION=1.0.63` vs `style.css` 1.0.0.
