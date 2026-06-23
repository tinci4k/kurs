# kurskc.hr — WordPress projekt

## O projektu
Web stranica za **KURS — Koprivnička udruga za rekreativni sport**, koja organizira
malonogometna natjecanja:
- **KC liga** — zimska dvoranska liga (divizije: *KC Liga* / *Liga bez briga*)
- **Pan ljetna liga** — ljetni turnir na travi

Sustav ima **dvije vlastite komponente**:
1. **Plugin `kurs-liga`** — cijela natjecateljska domena (sezone, divizije, faze, grupe,
   utakmice, događaji, playoff) + admin sučelje za vođenje lige.
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
- **PHP**: 8.1+ (Local koristi 8.2) · **DB**: `local` (root/root), prefiks tablica `wp_`

## Model podataka

### Custom post typeovi (CPT)
| Slug | Naziv | Registrira | Rewrite | Napomena |
|------|-------|-----------|---------|----------|
| `kurs_ekipa` | Ekipe | **plugin** (`includes/class-cpt.php`) | `/ekipe/` | naziv = `post_title`, logo = istaknuta slika, boja = postmeta `_kurs_team_color` |
| `kurs_igrac` | Igrači | **plugin** (`includes/class-cpt.php`) | `/igraci/` | |
| `vijest` | Vijesti | **tema** (`inc/post-types.php`) | `/vijesti/` | taksonomija `vijest_kategorija` |

> CPT-ovi **nemaju** taksonomije `liga`/`sezona`. Natjecateljska struktura je u
> **custom tablicama**, ne u taksonomijama.

### Custom tablice (`wp_kurs_*`, kreira `includes/class-database.php`)
Hijerarhija:
```
seasons → divisions → phases → groups → group_teams (ekipe u grupi)
                                      → matches → match_events (golovi/kartoni)
divisions → playoff_matches (bracket: qf/sf/final)
squads = roster (igrač × ekipa × divizija)
```
Stupci `*_team_id` / `*_player_id` su `wp_posts.ID` CPT-ova `kurs_ekipa`/`kurs_igrac`
(**nema** DB foreign-key constraintova — integritet samo u kodu).

## Front-end stranice (auto-kreira `themes/kurskc/functions.php`)
| Slug | Template | Sadržaj |
|------|----------|---------|
| `kc-liga` | `page-liga.php` | Liga Hub za KC ligu (tablica/raspored/statistika; `?tab=`, `?season_id=`, `?division_id=`) |
| `pan-ljetna` | `page-liga.php` | Liga Hub za Pan ljetnu (isti template, razlikuje se po slugu) |
| `utakmica` | `page-utakmica.php` | Detalj utakmice (`?match_id=X`) |
| `o-kurs-u` | `page-o-nama.php` | Statična stranica o udruzi |

## Razvojne napomene
- `WP_DEBUG` je uključen, log se piše u `app/public/wp-content/debug.log`.
- **Naming konvencije**: `KURS_PREFIX = 'kurs_'` (tablice `wp_kurs_*`, CPT `kurs_*`,
  postmeta `_kurs_*`); tema koristi prefiks `kurskc_` / `kurs_`. Uvijek koristi
  `$wpdb->prefix . KURS_PREFIX . 'tablica'`, nikad hardkodirano ime tablice.
- **CSS** koristi varijable u `:root` (`themes/kurskc/assets/css/main.css`) — mijenjaj
  boje samo tamo. Glavne boje:
  `--c-kc: #DA0F1E` (KC, crvena), `--c-pan: #F47B16` (Pan, narančasta),
  `--c-gold: #FDE206`, `--c-dark: #111317`, `--c-bg: #F4F4EE`.
  Fallback boja ekipe (`includes/helpers.php`) je `#1a3a6b`.
- **Domenska logika**: ljestvica/bodovanje u `includes/class-match.php::get_standings()`
  (3-1-0, UEFA/HNS razrješavanje); **round-robin raspored** i **izračun rezultata iz
  golova** su u `admin/class-admin.php` (`ajax_generate_fixtures()`, `compute_score()`),
  **ne** u domenskim klasama.
- **Ovisnost teme o pluginu**: `functions.php` uvjetno učita plugin `helpers.php`, a
  header/front-end rade direktne `$wpdb` upite po `wp_kurs_*`. Bez aktivnog plugina
  front-end degradira.

## Poznata ograničenja (nisu bugovi, namjerno/dokumentirano)
- `match_events.scored_for` i playoff runda `3rd` (utakmica za 3. mjesto) postoje u
  shemi ali se ne koriste/generiraju (mrtav stupac / negenerirana grana).
- `admin/views/playoff.php` + `Kurs_Admin::pg_playoff()` su napušteni — funkcionalni
  playoff je na `admin.php?page=kurs-matches&playoff_div=...`.
- Verzije se razlikuju namjerno: plugin `KURS_VERSION=1.0.0` vs `DB_VERSION=1.5`;
  tema `KURSKC_VERSION=1.0.4` vs `style.css` 1.0.0.
