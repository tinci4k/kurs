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

## 2026-10-16 — ispravci uz „Po kolima", „Za medije" i fiksnu strukturu natjecanja

Pronađeno tijekom izrade pregleda po kolima (`Kurs_Rounds`) i stranice „Za medije".
Nove značajke i pojednostavljenje backenda opisani su u `PLUGIN-KURS-LIGA.md` i `TEMA-KURSKC.md`;
ovdje su samo **greške** koje su usput ispravljene.

| # | Datoteka | Greška | Ispravak |
|---|----------|--------|----------|
| 9 | `themes/kurskc/page-utakmica.php` | Breadcrumb lige hardkodiran na `/kc-liga/?division_id=…` → za Pan ljetne utakmice vodio na KC hub. Playoff utakmice (`group_id=0`) nisu imale kontekst (JOIN po grupi vraća prazno) → breadcrumb bez lige i **prazne postave** (squad se dohvaća po `division_id`, a on je bio 0). | Breadcrumb preko `kurs_league_url( $division_id, … )` (slug `kc-liga`/`pan-ljetna` iz `seasons.league`); „N. kolo" je link na `?tab=kola&kolo=N#kola`. Za playoff meč `division_id` dolazi iz `Kurs_Playoff::find_by_match_id()` → rade postave, breadcrumb „Liga / Četvrtfinale \| Polufinale \| Finale" (→ `kolo=playoff`). |
| 10 | `plugins/kurs-liga/includes/class-ticker.php` (`compute_podium`) | Podij prvaka (off-season status `champions`) uvijek je linkao na `/kc-liga/` — krivo kad je zadnja liga Pan ljetna. | URL preko `kurs_league_url( $division_id )` (fallback `/kc-liga/` ako helper nije učitan). |
| 11 | `plugins/kurs-liga/includes/class-ticker.php` (`item_upcoming`) | Najava utakmica koristila `date_i18n( 'D j.n. H:i' )` → engleske kratice dana („Sat 17.10.") kad jezik WP-a nije hrvatski. | Vlastiti niz kratica `ned/pon/uto/sri/čet/pet/sub` + `date()` — neovisno o lokalizaciji WP-a. |
| 12 | `themes/kurskc/inc/news.php` (`kurskc_active_league()`) | Oznaka aktivne lige (widget „Iz lige · uživo") ispisivala je sirovi slug natjecanja (`kc_liga · 5. kolo`). | Upit dohvaća i `d.name`; label = naziv lige/divizije („KC Liga · 5. kolo"). URL vodi na pregled tog kola (`?tab=kola&kolo=N#kola`; bez kola → `tab=tablica`), CTA widgeta „Rezultati kola · tablica →". |
| 13 | `themes/kurskc/page-liga.php` (tab Tablica) | Tab je slijepo čitao `groups.playoff_spots` (fallback **2**) i za KC lige koje se igraju **bez playoffa** — postojeće KC skupine u bazi mogu imati 2 (DB default / divizija s defaultnim `has_playoff=1`) → prve dvije ekipe obojane kao „prolaz" + legenda „Playoff (1–2)". | Tab sada koristi `kurs_render_standings()` s `playoff_spots => 0` kad divizija nema `has_playoff` (odluka po diviziji, ne po skupini); ispod ljestvice se prikazuje bracket ako je generiran. Isto pravilo koriste „Po kolima" i „Za medije". (Nove skupine ionako nasljeđuju broj prolaznika iz divizije — `Kurs_Season::create_group()`, 0 bez playoffa.) |
| 14 | `themes/kurskc/page-liga.php` | `?division_id=` nije validiran — divizija druge sezone/natjecanja (npr. Pan divizija na `/kc-liga/`) prikazivala se pod krivim bannerom i izbornikom sezone. | `division_id` mora pripadati odabranoj sezoni, inače se uzima prva divizija sezone. |
| 15 | `themes/kurskc/assets/css/main.css` (Liga hub) | Na mobitelu je sadržaj huba bio širi od ekrana (horizontalni scroll) — i na postojećem tabu Tablica: `.hub-body` s `align-items:flex-start` nije rastezao glavni stupac, a stupci ljestvice nisu se mogli suziti. | `@media (max-width:900px) { .hub-body { align-items: stretch } }`; ≤600px grid ljestvice `26px minmax(0,1fr) 24px…` (ime kluba se skraćuje umjesto da gura širinu). |
| 16 | `themes/kurskc/assets/css/main.css` (header) | Na širinama ~600–960 px izbornik (~560 px) + tražilica (230 px) nisu stali u red → header se prelijevao horizontalno. | ≤1080px tražilica postaje ikona (`.header-search-toggle` → `.site-header.search-open`); ≤880px izbornik ide u hamburger (`.site-nav.is-open`, dropdowni liga postaju statični podpopisi). |
| 17 | `plugins/kurs-liga/includes/class-playoff.php` (`reset`) + `includes/class-season.php` (`delete_division`) | `reset()` je brisao samo `playoff_matches`; povezani zapisnici (`matches` s `group_id=0`) i njihovi eventi ostajali su kao **siročad**, a ponovno generiranje stvaralo je nove → duplicirane utakmice/golovi (npr. u statistici igrača). Brisanje divizije nije diralo playoff uopće. | `reset()` briše i povezane `matches` (`group_id=0`) + njihove `match_events` te okida `kurs_data_changed`; `delete_division()` najprije zove `Kurs_Playoff::reset()`. Confirm u `matches.php` upozorava da se brišu i zapisnici. |
| 18 | `plugins/kurs-liga/includes/class-match.php` (`get_top_scorers`) | Upit nije vraćao `team_id` → widget „Zlatna kopačka" (`page-liga.php`) crtao je badge za ekipu `0` (generički krug umjesto grba ekipe). | Dodano `MAX(e.team_id) AS team_id` (igrač koji je u diviziji igrao za više ekipa → jedna). |
| 19 | `plugins/kurs-liga/admin/class-admin.php` (`ajax_update_match`) | Promjena datuma/kola utakmice (inline ✎ u adminu) nije rušila cache → ticker (`Kurs_Ticker`) i „aktivna liga" (`kurskc_active_league`, transient 30 min) prikazivali su stari raspored. | Nakon `UPDATE` okida se `do_action('kurs_data_changed')`. |

## 2026-10-16 — restrukturiranje admina (`KURS_VERSION` 1.16.0)

Admin je preslagan po natjecanjima (stranice `kurs-kc` / `kurs-pan` s karticama, „Ekipe i igrači"
kao registar, „Sve sezone", „Postavke"; stari slugovi samo preusmjeravaju — vidi `PLUGIN-KURS-LIGA.md` §5).
Uz to su zatvorene rupe u integritetu ekipa — baza nema FK, pa su guardovi u admin handlerima:

| # | Datoteka | Greška | Ispravak |
|---|----------|--------|----------|
| 20 | `plugins/kurs-liga/admin/class-admin.php` (`ajax_assign_team`) + `includes/class-season.php` | Ista ekipa se mogla uvrstiti u **dvije skupine iste lige** (UNIQUE je samo `group_id + team_id`) → dvaput u Pan skupinama, dupli redovi u ljestvicama, kriv playoff. Handler nije provjeravao ni zaključanu sezonu ni postoji li ekipa (`team_id` bilo koji post). | Nova `Kurs_Season::team_group_in_division($division_id, $team_id)`; handler odbija (`wp_send_json_error(['message'])`, JS `alert`): nepoznatu ekipu („Nepoznata ekipa."), zaključanu sezonu („Sezona je zaključana."), ekipu koja je već u ligi („Ekipa je već u ovoj ligi." / „…već u skupini „X" — ekipa igra samo u jednoj skupini."). Uspješan odgovor vraća i `badge` HTML + `roster` URL. |
| 21 | `plugins/kurs-liga/admin/class-admin.php` (`ajax_remove_team`) | Ekipa s već odigranim/zakazanim utakmicama mogla se maknuti iz skupine → utakmice ostaju u rasporedu i „Po kolima", ali `get_standings()` broji samo utakmice u kojima su **obje** ekipe u `group_teams` — ekipa nestaje iz ljestvice, a protivnicima se tiho oduzmu bodovi i golovi iz utakmica s njom. Ni zaključana sezona nije bila blokirana. | Nova `Kurs_Season::team_match_count($group_id, $team_id)`; dok je > 0 handler odbija („Ekipa već ima N utakmica u ovoj ligi. Najprije ih obriši u kartici Utakmice…"). Zaključana sezona → greška. |
| 22 | `plugins/kurs-liga/admin/class-admin.php` (`do_delete_team`) | Brisanje ekipe radilo je samo `wp_delete_post()` → **siročad**: utakmice s nepostojećim `home/away_team_id` (`Kurs_Match::get_by_group()` i admin/front popisi rade inner JOIN na `wp_posts` pa ih tiho ispuštaju; `get_standings()` ekipu više ne vidi pa preskače i sve njene utakmice — protivnicima nestanu bodovi), te zaostali redovi u `group_teams` i `squads`. | Ekipa s ijednom utakmicom u `matches` se **ne briše** (redirect `kurs-teams&in_use=N`, poruka da joj se može promijeniti naziv). Bez utakmica briše i svoje `group_teams` i `squads` retke pa tek onda post. |

Usput uklonjeno (nije greška, nego mrtav kod): `admin/views/playoff.php` + `Kurs_Admin::pg_playoff()`
(napuštena, nikad registrirana stranica `kurs-playoff`), kao i `season-setup.php`, `roster.php` i
`pg_setup/pg_matches/pg_roster`; `matches.php` je postao `hub-matches.php`. `do_delete_match` sada
dodatno okida `kurs_data_changed` (redundantno — `Kurs_Match::delete()` ga već okida).

## 2026-10-27 — 404 na profilima ekipa

| # | Datoteka | Greška | Ispravak |
|---|----------|--------|----------|
| 23 | `plugins/kurs-liga/kurs-liga.php` | Klik na ekipu u ljestvici → **404**; isto `/igraci/…` i `/vijesti/`. Spremljena opcija `rewrite_rules` (94 pravila) nije imala **nijedno** CPT pravilo — generirana je u trenutku kad CPT-ovi nisu bili registrirani (vjerojatno migracija baze ili ažuriranje WP-a). Jednokratni flush (`kurs_cpt_flushed_v2`) se više nije okidao jer je opcija već bila postavljena. | Jednokratni flush zamijenjen **samoizlječenjem** na `init` 100: ako u `rewrite_rules` nema ključa `ekipe/…` (a permalinkovi nisu „plain"), `flush_rewrite_rules( false )`. Pravila su se regenerirala sama (156 pravila). |

## 2026-10-27 — paket bugova i zaštita (`KURS_VERSION` 1.17.1, DB 1.11)

Pronađeno pregledom organizacije admina; sve potvrđeno u kodu i testirano kroz prave handlere.

| # | Datoteka | Greška | Ispravak |
|---|----------|--------|----------|
| 24 | `plugins/kurs-liga/includes/class-playoff.php` (`advance_winner`) | Neriješen playoff meč: pobjednik se računao kao `home > away ? home : away`, pa je kod remija **dalje tiho išao gost** (provjera „neriješeno" ispod nikad nije okinula). Isto pravilo u prvaku tickera, Pregledu i „Za medije". Nije bilo načina upisati penale. | Novi stupci `playoff_matches.home_pen/away_pen` (DB 1.11). `finish_match()` kod neriješenog traži različite penale, inače `WP_Error` i ništa se ne sprema (admin: `pm_error=draw` poruka). Pobjednika određuje samo `Kurs_Playoff::winner_side()`; „pen. 5:4" se prikazuje u bracketu (admin + web), zapisniku, na stranici utakmice i u „Za medije". Polja „Penali" u brzom unosu bracketa i u zapisniku playoff meča. |
| 25 | `plugins/kurs-liga/admin/class-admin.php` (`ajax_add_squad`) | Isti igrač mogao se dodati u rostere **dvije ekipe iste lige** (UNIQUE je `player+team+division`). | Guard: igrač koji je u toj ligi već u drugoj ekipi → greška s imenom ekipe. Druga liga iste sezone i dalje dopuštena. |
| 26 | `plugins/kurs-liga/admin/class-admin.php` + `hub-matches.php`, `match-record.php` | Zaključavanje sezone štitilo je samo dodjelu ekipa i generiranje rasporeda — utakmice, zapisnici (golovi, kartoni, suci, kapetan, odsutni, zaključi/otključaj), playoff, rosteri i brisanje sezone i dalje su se mogli mijenjati. | `guard_unlocked()` u svim tim handlerima (forma → povratak bez izmjene, AJAX → `season_locked` poruka). U zaključanoj sezoni kartica Utakmice, playoff i zapisnik skrivaju gumbe za izmjene; zapisnik ima banner. |
| 27 | `plugins/kurs-liga/admin/views/teams.php` | Spremanje osnovnih podataka, loga, banera ili slike ekipe vraćalo je bez `division_id` → gubio se odabrani roster i „← Natrag na ligu". | `redirect_to` zadržava `division_id` kad je stranica otvorena iz lige. |
| 28 | `plugins/kurs-liga/admin/class-admin.php` (`do_create_season`) | Nova sezona ostavljala je staru sezonu istog natjecanja „Aktivnom" → i dalje je punila „Čeka rezultat" / „Ovaj tjedan". | Starije aktivne sezone istog natjecanja (manja godina) prebacuju se u arhivu; obavijest u kartici Ekipe nove sezone (vraća se u Postavkama). Drugo natjecanje se ne dira. |

## 2026-10-27 — prijenos sezone, vijesti i ostali ispravci (`KURS_VERSION` 1.18.1, tema 1.0.38)

Nova značajka „Preuzmi ekipe iz sezone X” (prijenos ekipa i rostera s brojevima dresova) opisana je u `PLUGIN-KURS-LIGA.md`; ugradnja kola/ljestvice u vijesti u `TEMA-KURSKC.md` §4. Ovdje su greške:

| # | Datoteka | Greška | Ispravak |
|---|----------|--------|----------|
| 29 | `themes/kurskc/single-kurs_igrac.php` | **Karijera igrača brojala je utakmice i golove ekipe iz drugih sezona/liga**: spoj na utakmice (`matches.group_id = g.id`) nije bio ograničen na ligu retka. Npr. Martin Kolarić (Klikaj.hr u KC i Pan) — za KC 2026/2027 prikazano 4 gola / 6 utakmica umjesto 3 / 5. „Utak.” je brojao i utakmice na kojima je igrač bio odsutan; playoff se nije brojao. | Redovi iz `squads` (samo lige u kojima je ekipa uvrštena), brojke po retku preko `Kurs_Match::team_match_ids()` (skupine + playoff) minus `absent`. |
| 30 | `themes/kurskc/single-kurs_ekipa.php` | Profil ekipe (forma, sljedeća utakmica, strijelci, sve utakmice, statistika) išao je samo preko skupina — **playoff utakmice ekipe nisu se vidjele**; oznaka uvijek „N. kolo”. | `Kurs_Match::division_match_ids_sql()` u svim upitima; `kurs_round_label()` („Četvrtfinale”…). |
| 31 | `plugins/kurs-liga/includes/class-playoff.php`, `admin/class-admin.php` | Bracket i zapisnik playoff meča vodili su ekipe i termin odvojeno: promjena termina/ekipa u bracketu ili prolaz pobjednika nije mijenjao zapisnik (i obrnuto); otvaranje ranije runde ostavljalo je zapisnik sa starom ekipom. Zapisnik je playoff meču upisivao „kolo”. | `sync_record()` u oba smjera; prazan zapisnik se briše kad slot ostane bez ekipe; promjena ekipa odbijena ako zapisnik ima događaje; zapisnik playoffa prikazuje rundu umjesto „Kolo 0” i nema polje kola. |
| 32 | `includes/helpers.php` (`kurs_render_standings`), `page-utakmica.php`, `single-kurs_igrac.php`, `inc/news.php` | Linkovi na ekipu bez lige → s ljestvice stare sezone otvarao se profil u najnovijoj sezoni. Breadcrumb profila igrača i guardovi vodili su na nepostojeće `/igraci/` i `/ekipe/` (404). | Novi helper `kurs_team_url($team_id, $division_id)`; breadcrumb na trenutnu ekipu; guardovi na naslovnicu. |
| 33 | `themes/kurskc/inc/news.php` | „Ugradi tablicu / ljestvicu” u vijestima ugrađivala je samo **aktualnu** ljestvicu (vijest o 5. kolu kasnije je pokazivala drugu tablicu), nije se moglo ugraditi kolo (rezultati, ljestvica nakon kola, strijelci) kao u „Za medije”, a bracket se nudio i ligama bez playoffa. | Novi izbornik liga → prikaz (vidi `TEMA-KURSKC.md` §4), zamrznuto na kolo; shortcode `[kurs_kolo]`; stari zapis i dalje radi. |
| 34 | `plugins/kurs-liga/admin/class-admin.php` (`do_add_team`, `do_edit_season`, `ajax_set_logo/photo`) | Forma „Nova ekipa” u „Ekipe i igrači” nije provjeravala postoji li ekipa istog imena (brzo dodavanje na stranici natjecanja jest). Promjena godine sezone mogla je napraviti dvije sezone istog natjecanja iste godine. Logo/slika mogli su se postaviti na bilo koji post (npr. igrača). | Duplikat → `exists=ID` obavijest s linkom; `year_taken` obavijest; provjera `kurs_ekipa` + `wp_attachment_is_image`. |
| 35 | `admin/views/hub-matches.php`, `match-record.php`, `admin.js`, `teams.php` | Generiranje playoffa bez upozorenja dok skupine nisu odigrane; potvrda „Otključaj za ispravke” tvrdila je da rezultat ostaje u tablici (ne ostaje — računa se samo završena); brojač „Bez lige u ovoj sezoni” nije rastao za novu ekipu (JS na nepostojeći `#pool-count`); „N igrača” u popisu ekipa zbrajalo je sve sezone. | Upozorenje s brojem neodigranih; ispravna poruka; brojač `#kurs-pool-free-count`; broj igrača u aktivnim sezonama. |

Usklađeni nazivi: akcije nad utakmicom su **Završi utakmicu** / **Otvori za ispravak** (prije „Zaključi” / „Otključaj”, lako zamjenjivo sa „Zaključaj sezonu”); suci u zapisniku „Promijeni”; „Uvrsti ekipe u skupinu”. Uklonjen mrtav kod: `ajax_set_banner` (+ `wp_ajax_kurs_set_banner`), `do_remove_team_banner` (+ akcija `remove_team_banner`), skriveni stari obrazac „Dodaj gol / event” u zapisniku (+ CSS `.kurs-add-event*`), JS `#squad-count-label`.

## 2026-10-27 — igrači, roster u natjecanju, postave utakmica (`KURS_VERSION` 1.19.0, DB 1.12, tema 1.0.39)

Preslagivanje (opisano u `PLUGIN-KURS-LIGA.md`): nova stranica **Igrači** (`kurs-players`: popis, tražilica, godina rođenja,
fotografija, karijera, spajanje dvostrukih upisa), **roster ekipe na kartici Ekipe natjecanja** (umjesto u „Ekipe i igrači",
koja je sad „Ekipe"), **prijelaz igrača** u drugu ekipu iste lige uz potvrdu, i **postava spremljena uz utakmicu**
(`match_players`). Greške riješene usput:

| # | Datoteka | Greška | Ispravak |
|---|----------|--------|----------|
| 36 | `themes/kurskc/page-utakmica.php` | Tijek utakmice skrivao je samo prekršaje — inertni događaji `captain` i `absent` prikazivali su se kao **gol** (ikona gola, oznaka „captain"/„absent"). Sastavi su prikazivali i igrače označene „nije igrao". | Tijek bez `foul`/`captain`/`absent`; sastavi iz postave bez odsutnih. |
| 37 | `plugins/kurs-liga/includes/class-match.php`, teme (zapisnik, PDF, stranica utakmice, profil igrača) | Sastav utakmice računao se iz **trenutnog** rostera: micanje igrača iz ekipe (odlazak, prijelaz, pogrešan unos) brisalo ga je iz svih odigranih utakmica i iz karijere, a igrač dodan usred sezone dobivao je nastupe i za utakmice prije dolaska. Prijelaz nije bio moguć bez gubitka povijesti. | Tablica `match_players`, `save_lineup()` pri završetku utakmice (migracija za postojeće), `lineup()` / `player_career()` čitaju postavu; prijelaz preko `kurs_add_squad` + `transfer=1`. |

## 2026-11-02 — stvarni podaci Pan Ljetne lige 2026 (`KURS_VERSION` 1.19.1, tema 1.0.41)

Lokalno su obrisani demo/probni podaci (KC 2026/2027 demo, izmišljeni rosteri i rezultati Pan 2026) i unesena je
Pan Ljetna liga 2026 iz službenih izvještaja (24 utakmice skupina, četvrtfinale, polufinale, utakmica za 3. mjesto,
finale, 63 strijelca, 8 vijesti s ugrađenim kolima/bracketom, galerija završnice). Uz to:

| # | Datoteka | Greška | Ispravak |
|---|----------|--------|----------|
| 38 | `themes/kurskc/page-utakmica.php`, `admin/views/match-record.php`, `match-record-pdf.php` | Događaj bez minute (unos iz izvještaja) prikazivao se kao „0'", a svi pod naslovom „1. poluvrijeme". | Bez minute: prazno (javno) / „–" (zapisnik, PDF); naslovi poluvremena samo kad ijedan događaj ima minutu ili poluvrijeme. |
| 39 | `includes/class-ticker.php` (`compute_podium`) | Treće mjesto u tickeru „Prvaci" uzimalo se iz tablice skupina i kad je odigrana utakmica za 3. mjesto (Pan 2026: prikazalo bi Instal Term umjesto Marksa). | Pobjednik utakmice za 3. mjesto (`winner_side`) ima prednost; tablica je samo zamjena. |
| 40 | `includes/helpers.php` (`kurs_render_playoff_bracket`) | Utakmica za 3. mjesto bila je četvrti stupac bracketa — na stranici lige (uz bočnu traku) izlazila je izvan vidljivog dijela. | Prikazuje se ispod finala (`.kurs-bracket__third`); meč se iscrtava jednom funkcijom za sve runde. |

## 2026-11-02 — KC liga 2025/2026 i preneseno stanje (`KURS_VERSION` 1.20.0, DB 1.13)

Iz službenog izvještaja završnog dana (28. 2. 2026.) postoje samo 9. kolo, konačne ljestvice i ukupne liste strijelaca.
Da ljestvica i strijelci budu službeni bez izmišljanja kola 1.–8., uvedeno je **preneseno stanje** (vidi `PLUGIN-KURS-LIGA.md` §4.1):
kola izvan sustava kao zbroj po ekipi (`group_teams.base_*`, `rank_hint` za Robin Marketi ispred Active Commercea kod 16 bodova)
i preneseni golovi po igraču (`squads.base_goals`). Sezona je arhivirana i zaključana; KC 2026/2027 dobila je iste ekipe i rostere
(prijenos iz prošle sezone), bez rasporeda. Vijest #113 zamijenjena je službenim izvještajem (datum 28. 2. 2026., oba 9. kola ugrađena).

| # | Datoteka | Greška | Ispravak |
|---|----------|--------|----------|
| 41 | `includes/class-season.php` (`add_to_squad`) | `REPLACE` briše i ponovno upisuje redak rostera — s novim stupcima obrisao bi prenesene golove igrača pri ponovnom dodavanju / promjeni dresa. | `INSERT … ON DUPLICATE KEY UPDATE jersey_no`. |
| 42 | `themes/kurskc/page-liga.php` (tab Raspored), `includes/helpers.php` (`kurs_render_schedule`) | Raspored lige i ugradnja „Raspored” u vijestima brali su utakmice samo preko skupina — **playoff se nije vidio** (Pan 2026: nedostajali četvrtfinale i završnica), a ni budući playoff parovi s terminom. Tab je imao svoj, dupli upit. | Zajednički `Kurs_Rounds::schedule()` (skupine + playoff zapisnici + parovi bez zapisnika) i jedan renderer (`round_labels` za stranicu lige); penali uz rezultat, oznaka playoff runde uz datum (1.20.1 / tema 1.0.42). |

Nesuglasja u izvještaju (podaci upisani doslovno): zbroj golova KC lige 202:204; strijelci Active Commercea zbrajaju 31 (ljestvica 27 —
„Sminderovac 4" je vjerojatno ETM); dva „Šurić" u HEP-u (2 i 1). Vidi poruku korisniku / memoriju.

## 2026-11-02 — KC liga 2025/2026: sva kola kao stvarne utakmice (samo podaci)

S Facebook stranice KC lige (izvještaji 1., 4., 5., 6., 7. i 9. kola, najave 6. i 9. kola s ljestvicama i strijelcima) i
KURS-ove slike rasporeda 1.–6. kola sezona #21 ponovno je upisana: **81 utakmica, kola 1.–9. sa stvarnim datumima i satnicom**.
8. kolo (bez izvještaja) izvedeno je iz razlike ljestvica i lista strijelaca nakon 7. i 8. kola. Preneseno stanje ekipa je
ukinuto (`base_*` = 0, bez `rank_hint`); ostali su samo **preneseni golovi** za 2. i 3. kolo (nema izvještaja, a ekipa je
zabijala u obje utakmice, pa se gol ne može pripisati utakmici). Ljestvica nakon 1., 4., 5., 6., 7., 8. i 9. kola provjerena
je prema službenim tablicama — slaže se, osim Inducte (vidi niže). Postave utakmica = samo strijelci (pune postave nepoznate).

Ispravljeni podaci prvog uvoza: jedan Sminderovac (ETM, 5 golova; „Active Commerce" u listama je greška grupiranja), jedan
Šurić (HEP, 5 golova prema izvještajima), Zagorščak je igrač Forme Legalis (autogol u Forma – KURS), puna imena iz izvještaja
(Zoran Ogrizović, Goran Jagić, Igor Topalović, Karlo Galinec, Marko Matijašec, Slobodan Posavec, Matija Đeno, Krešo Škvorc).
Isto je ispravljeno u rosterima KC 2026/2027.

Preostala nesuglasja izvora: službena ljestvica Inducti daje 2 primljena gola više nego zbroj rezultata (13:30 vs 13:28) — to je
i razlog zbroja 202:204; FB slika ima Beertija – Gudsport 0:9, izvještaj i ljestvica 0:8 (upisano 0:8); Kokša (AL Nemčić) ima
3 gola prema listi nakon 4. kola i izvještaju 6. kola, završna lista kaže 2; nepoznata su 3 strijelca (KURS u 6. kolu, AL Nemčić
u 8. kolu i jedan gol AL Nemčića u 2./3. kolu) — ti golovi postoje u rezultatu, ali bez igrača.

Vijesti po kolima (#449–455, kategorija KC Liga, oznaka „KC liga 2025/2026”): 1., 4., 5. i 7. kolo prema izvještajima,
2. i 3. kolo samo iz rezultata (uz napomenu da popis strijelaca nije potpun), 8. kolo prema najavi 9. kola. Obje lige
ugrađene su s `[kurs_kolo]`, uz parove sljedećeg kola. Postojeća vijest 6. kola (#115) dobila je datum kola (25. 1. 2026.,
bila je 24. 2.) i istu ugradnju.

| # | Datoteka | Greška | Ispravak |
|---|----------|--------|----------|
| 43 | `includes/class-rounds.php` (`scorers`), `class-database.php`, `class-season.php`, `admin/views/hub-teams.php`, `admin/class-admin.php` | Preneseni golovi igrača pribrajali su se listi strijelaca **nakon svakog kola** — kad su to golovi 2. i 3. kola (KC 2025/2026), lista nakon 1. kola imala je npr. Sabolića sa 6 golova umjesto 4. | Novi stupac `divisions.base_round` (DB 1.14): kolo do kojeg su preneseni golovi postignuti; lista nakon ranijeg kola ih ne pribraja. Polje u panelu „Preneseno stanje” (`save_base_standings`), `Kurs_Season::set_base_round()`. KURS 1.21.0. |
| 44 | `themes/kurskc/inc/news.php`, `front-page.php`, `index.php`, `single.php`, `search.php`, `main.css` | Vijest bez istaknute slike imala je prazan svijetli okvir na kartici (Pan dani 1.–6., KC kola) — izgledalo je kao da slika nije učitana. | `kurskc_thumb_placeholder()`: podloga u boji lige (`--ph`) s prugama i grbom lige (`kurs_league_icon`), izvan liga KURS monogram (`.kc-ph`). Tema 1.0.43. |

## Još uvijek otvoreno (svjesno NIJE dirano)

Ovo su rizičnije izmjene ili dizajnerske odluke, ostavljene namjerno:

- **`match_events.scored_for`** — mrtav stupac (nikad se ne upisuje/čita). Uklanjanje
  zahtijeva migraciju; bezopasno je pa ostaje.
- **Playoff runda `3rd`** (utakmica za 3. mjesto) — `generate()` je ne stvara (poraženi u polufinalu ne
  idu sami u nju); kad postoji, prikazuju je bracket, ticker i admin (vidi #39). Za Pan 2026 unesena ručno.
- **`playoff_matches.match_id BIGINT` vs `matches.id INT`** — neusklađenost tipova FK;
  kozmetički, ALTER na postojećim podacima je rizičan.
- **`Kurs_Match::reopen()` ne briše rezultat** (za razliku od `Kurs_Playoff::reopen_match()`)
  — to je namjerno: `get_standings()` broji samo `status='finished'`, a operater zadržava
  uneseni rezultat pri otključavanju za ispravak.
- **`strip_tags(): Passing null`** deprecation iz `wp-admin/admin-header.php` — dolazi iz
  WP jezgre (skrivene podstranice s `parent=null`), nije čisto rješivo iz plugina.
- **`Kurs_Season::update()`** i dalje prihvaća `league`; samo admin forma (`do_edit_season`) ga
  više ne šalje. Natjecanje sezone se mijenja jedino izravno u bazi — i tada `scaffold()` ne
  prepravlja postojeće lige/skupine.
