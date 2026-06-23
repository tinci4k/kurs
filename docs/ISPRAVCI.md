# Ispravci — changelog

> Popravci grešaka pronađenih tijekom istraživanja projekta. Kad neki dio
> dokumentacije opisuje grešku kao "aktualnu", provjeri ovdje je li u međuvremenu
> ispravljena.

## 2026-06-22 — popravak potvrđenih grešaka

Sve dolje navedeno potvrđeno je u kodu **i** u `wp-content/debug.log` prije ispravka.

| # | Datoteka | Greška | Ispravak |
|---|----------|--------|----------|
| 1 | `plugins/kurs-liga/includes/class-database.php` | `maybe_upgrade()` izvršavao `ALTER TABLE … ADD COLUMN IF NOT EXISTS` — **nije validan MySQL** (samo MariaDB); bacao SQL syntax error pri svakom učitavanju. | Uklonjeni `ADD COLUMN IF NOT EXISTS` upiti. Nove stupce (`groups.playoff_spots`, `playoff_matches.match_id`) sada dodaje `self::install()` / `dbDelta` (idempotentno, dio CREATE TABLE definicija). Ostao samo valjani `MODIFY` za `seasons.league`. |
| 2 | `plugins/kurs-liga/admin/views/match-record.php` | `$is_finished`/`$disp_home`/`$disp_away` računani su **nakon** `include match-record-pdf.php` → u PDF-u "Undefined variable" (prazan rezultat, status uvijek "U tijeku"). | Izračun premješten **prije** PDF includea. |
| 3 | `plugins/kurs-liga/admin/class-admin.php` | PDF zapisnik učitavao se tek u page callbacku (nakon `admin-header.php`) → `header()` pucao s "headers already sent", a PDF se iscrtavao **unutar** admin sučelja. | Dodan `maybe_render_pdf()` na `admin_init` — renderira PDF (`?page=kurs-match-record&format=pdf`) **prije** ispisa admin headera, kao samostalna stranica. |
| 4 | `plugins/kurs-liga/admin/views/match-record-pdf.php` | Footer `date('j.n.Y u H:i')` — neescapirani `u` je format znak za **mikrosekunde** → ispisivao `000000` umjesto "u". | Promijenjeno u `date_i18n('j.n.Y. \u H:i')` (escapiran `u`, WP timezone/lokalizacija). |
| 5 | `plugins/kurs-liga/admin/views/matches.php` | Hardkodirano ime tablice `'kurs_groups'` umjesto `KURS_PREFIX` konstante (radilo samo dok je prefiks `kurs_`). | Promijenjeno u `$wpdb->prefix . KURS_PREFIX . 'groups'`. |
| 6 | `plugins/kurs-liga/admin/views/season-setup.php` | Gumb "Upravljaj playoff bracketom" linkao na **neregistriranu** stranicu `kurs-playoff` (WP odbijao pristup). | Repointano na funkcionalnu rutu `admin.php?page=kurs-matches&playoff_div=…`. |
| 7 | `themes/kurskc/footer.php` | Svi ligaški linkovi (Tablice/Raspored/Rezultati/KC/Pan) vodili na `/liga/` — stranica koja se **ne kreira** → 404. | Repointano na `/kc-liga/` (s `?tab=tablica`/`?tab=raspored`) i `/pan-ljetna/`. |
| 8 | `plugins/kurs-liga/includes/class-playoff.php` | Tipfeleri u komentarima (`kvaificirane`, `Izgadi`). | Ispravljeno (`kvalificirane`, `Izgradi`). |

Svi izmijenjeni PHP fajlovi prošli su `php -l` (PHP 8.2) bez grešaka.

## Još uvijek otvoreno (svjesno NIJE dirano)

Ovo su rizičnije izmjene ili dizajnerske odluke, ostavljene namjerno:

- **`match_events.scored_for`** — mrtav stupac (nikad se ne upisuje/čita). Uklanjanje
  zahtijeva migraciju; bezopasno je pa ostaje.
- **Playoff runda `3rd`** (utakmica za 3. mjesto) — postoji u shemi i `ORDER BY`, ali se
  nikad ne generira. Nedovršena značajka, ne greška.
- **`admin/views/playoff.php` + `Kurs_Admin::pg_playoff()`** — napušten kod koji duplicira
  playoff prikaz iz `matches.php`. Sada je nedostupan (link #6 repointan), ali metoda i
  view i dalje postoje. Kandidat za uklanjanje u zasebnom čišćenju.
- **`playoff_matches.match_id BIGINT` vs `matches.id INT`** — neusklađenost tipova FK;
  kozmetički, ALTER na postojećim podacima je rizičan.
- **`Kurs_Match::reopen()` ne briše rezultat** (za razliku od `Kurs_Playoff::reopen_match()`)
  — to je namjerno: `get_standings()` broji samo `status='finished'`, a operater zadržava
  uneseni rezultat pri otključavanju za ispravak.
- **`strip_tags(): Passing null`** deprecation iz `wp-admin/admin-header.php` — dolazi iz
  WP jezgre (skrivene podstranice s `parent=null`), nije čisto rješivo iz plugina.
