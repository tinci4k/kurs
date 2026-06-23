SET NAMES utf8mb4;

-- ── 1. IGRAČI (5 po ekipi kao WP postovi) ────────────────────────────────

-- Auto Loris (10)
INSERT INTO wp_posts (post_title,post_type,post_status,post_date,post_modified,post_date_gmt,post_modified_gmt,post_author,guid,comment_status,ping_status,post_name,to_ping,pinged,post_content,post_excerpt,post_content_filtered) VALUES
('Tomislav Horvatić','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','tomislav-horvatic','','','','',''),
('Darko Šimunović','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','darko-simunovic','','','','',''),
('Nikola Blažević','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','nikola-blazevic','','','','',''),
('Ivan Kolar','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','ivan-kolar','','','','',''),
('Marko Vlahović','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','marko-vlahovic','','','','','');
SET @al1=LAST_INSERT_ID(); SET @al2=@al1+1; SET @al3=@al1+2; SET @al4=@al1+3; SET @al5=@al1+4;
INSERT INTO wp_kurs_squads (player_id,team_id,division_id,jersey_no) VALUES (@al1,10,2,1),(@al2,10,2,5),(@al3,10,2,7),(@al4,10,2,9),(@al5,10,2,11);

-- Klikaj.hr / Zona tri (9)
INSERT INTO wp_posts (post_title,post_type,post_status,post_date,post_modified,post_date_gmt,post_modified_gmt,post_author,guid,comment_status,ping_status,post_name,to_ping,pinged,post_content,post_excerpt,post_content_filtered) VALUES
('Mario Horvat','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','mario-horvat','','','','',''),
('Luka Perić','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','luka-peric','','','','',''),
('Dario Majić','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','dario-majic','','','','',''),
('Stjepan Knežević','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','stjepan-knezevic','','','','',''),
('Branimir Žilić','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','branimir-zilic','','','','','');
SET @kz1=LAST_INSERT_ID(); SET @kz2=@kz1+1; SET @kz3=@kz1+2; SET @kz4=@kz1+3; SET @kz5=@kz1+4;
INSERT INTO wp_kurs_squads (player_id,team_id,division_id,jersey_no) VALUES (@kz1,9,2,10),(@kz2,9,2,7),(@kz3,9,2,3),(@kz4,9,2,8),(@kz5,9,2,99);

-- Lepal (24)
INSERT INTO wp_posts (post_title,post_type,post_status,post_date,post_modified,post_date_gmt,post_modified_gmt,post_author,guid,comment_status,ping_status,post_name,to_ping,pinged,post_content,post_excerpt,post_content_filtered) VALUES
('Goran Bauer','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','goran-bauer','','','','',''),
('Petar Štefanec','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','petar-stefanec','','','','',''),
('Josip Vranić','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','josip-vranic','','','','',''),
('Kristijan Fabek','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','kristijan-fabek','','','','',''),
('Robert Filipović','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','robert-filipovic','','','','','');
SET @lp1=LAST_INSERT_ID(); SET @lp2=@lp1+1; SET @lp3=@lp1+2; SET @lp4=@lp1+3; SET @lp5=@lp1+4;
INSERT INTO wp_kurs_squads (player_id,team_id,division_id,jersey_no) VALUES (@lp1,24,2,2),(@lp2,24,2,4),(@lp3,24,2,6),(@lp4,24,2,8),(@lp5,24,2,12);

-- Montsistemi (11)
INSERT INTO wp_posts (post_title,post_type,post_status,post_date,post_modified,post_date_gmt,post_modified_gmt,post_author,guid,comment_status,ping_status,post_name,to_ping,pinged,post_content,post_excerpt,post_content_filtered) VALUES
('Andrija Štimac','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','andrija-stimac','','','','',''),
('Denis Turković','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','denis-turkovic','','','','',''),
('Zdravko Ergović','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','zdravko-ergovic','','','','',''),
('Lovro Benković','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','lovro-benkovic','','','','',''),
('Tomáš Novák','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','tomas-novak','','','','','');
SET @mt1=LAST_INSERT_ID(); SET @mt2=@mt1+1; SET @mt3=@mt1+2; SET @mt4=@mt1+3; SET @mt5=@mt1+4;
INSERT INTO wp_kurs_squads (player_id,team_id,division_id,jersey_no) VALUES (@mt1,11,2,1),(@mt2,11,2,6),(@mt3,11,2,11),(@mt4,11,2,14),(@mt5,11,2,20);

-- Active Commerce (14)
INSERT INTO wp_posts (post_title,post_type,post_status,post_date,post_modified,post_date_gmt,post_modified_gmt,post_author,guid,comment_status,ping_status,post_name,to_ping,pinged,post_content,post_excerpt,post_content_filtered) VALUES
('Filip Bogdan','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','filip-bogdan','','','','',''),
('Siniša Kranjec','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','sinisa-kranjec','','','','',''),
('Matej Dominković','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','matej-dominkovic','','','','',''),
('Boris Haramija','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','boris-haramija','','','','',''),
('Domagoj Šarić','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','domagoj-saric','','','','','');
SET @ac1=LAST_INSERT_ID(); SET @ac2=@ac1+1; SET @ac3=@ac1+2; SET @ac4=@ac1+3; SET @ac5=@ac1+4;
INSERT INTO wp_kurs_squads (player_id,team_id,division_id,jersey_no) VALUES (@ac1,14,2,3),(@ac2,14,2,7),(@ac3,14,2,9),(@ac4,14,2,15),(@ac5,14,2,22);

-- CB K Johannu (15)
INSERT INTO wp_posts (post_title,post_type,post_status,post_date,post_modified,post_date_gmt,post_modified_gmt,post_author,guid,comment_status,ping_status,post_name,to_ping,pinged,post_content,post_excerpt,post_content_filtered) VALUES
('Sebastijan Vuk','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','sebastijan-vuk','','','','',''),
('Kristofer Paar','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','kristofer-paar','','','','',''),
('Mislav Špoljar','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','mislav-spoljar','','','','',''),
('Nenad Cvetković','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','nenad-cetkovic','','','','',''),
('Aleksandar Rožić','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','aleksandar-rozic','','','','','');
SET @cbk1=LAST_INSERT_ID(); SET @cbk2=@cbk1+1; SET @cbk3=@cbk1+2; SET @cbk4=@cbk1+3; SET @cbk5=@cbk1+4;
INSERT INTO wp_kurs_squads (player_id,team_id,division_id,jersey_no) VALUES (@cbk1,15,2,1),(@cbk2,15,2,5),(@cbk3,15,2,8),(@cbk4,15,2,13),(@cbk5,15,2,17);

-- Kolarić završni radovi (16)
INSERT INTO wp_posts (post_title,post_type,post_status,post_date,post_modified,post_date_gmt,post_modified_gmt,post_author,guid,comment_status,ping_status,post_name,to_ping,pinged,post_content,post_excerpt,post_content_filtered) VALUES
('Martin Kolarić','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','martin-kolaric','','','','',''),
('Bruno Kolarić','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','bruno-kolaric','','','','',''),
('Tomislav Gregurić','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','tomislav-greguric','','','','',''),
('Josip Tkalčić','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','josip-tkalcic','','','','',''),
('Vedran Marinić','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','vedran-marinic','','','','','');
SET @kr1=LAST_INSERT_ID(); SET @kr2=@kr1+1; SET @kr3=@kr1+2; SET @kr4=@kr1+3; SET @kr5=@kr1+4;
INSERT INTO wp_kurs_squads (player_id,team_id,division_id,jersey_no) VALUES (@kr1,16,2,10),(@kr2,16,2,11),(@kr3,16,2,7),(@kr4,16,2,4),(@kr5,16,2,19);

-- Naturavita (13)
INSERT INTO wp_posts (post_title,post_type,post_status,post_date,post_modified,post_date_gmt,post_modified_gmt,post_author,guid,comment_status,ping_status,post_name,to_ping,pinged,post_content,post_excerpt,post_content_filtered) VALUES
('Zvonimir Leš','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','zvonimir-les','','','','',''),
('Davor Požgaj','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','davor-pozgaj','','','','',''),
('Patrik Šimić','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','patrik-simic','','','','',''),
('Toni Vrančić','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','toni-vrancic','','','','',''),
('Dragan Pavlić','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','dragan-pavlic','','','','','');
SET @nv1=LAST_INSERT_ID(); SET @nv2=@nv1+1; SET @nv3=@nv1+2; SET @nv4=@nv1+3; SET @nv5=@nv1+4;
INSERT INTO wp_kurs_squads (player_id,team_id,division_id,jersey_no) VALUES (@nv1,13,2,1),(@nv2,13,2,6),(@nv3,13,2,9),(@nv4,13,2,14),(@nv5,13,2,21);

-- Mobiteli popravljaona (25)
INSERT INTO wp_posts (post_title,post_type,post_status,post_date,post_modified,post_date_gmt,post_modified_gmt,post_author,guid,comment_status,ping_status,post_name,to_ping,pinged,post_content,post_excerpt,post_content_filtered) VALUES
('Danijel Horvatin','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','danijel-horvatin','','','','',''),
('Igor Čačković','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','igor-cackovic','','','','',''),
('Krunoslav Bedenik','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','krunoslav-bedenik','','','','',''),
('Velimir Bedeković','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','velimir-bedekovic','','','','',''),
('Slaven Mesarić','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','slaven-mesaric','','','','','');
SET @mb1=LAST_INSERT_ID(); SET @mb2=@mb1+1; SET @mb3=@mb1+2; SET @mb4=@mb1+3; SET @mb5=@mb1+4;
INSERT INTO wp_kurs_squads (player_id,team_id,division_id,jersey_no) VALUES (@mb1,25,2,2),(@mb2,25,2,5),(@mb3,25,2,8),(@mb4,25,2,12),(@mb5,25,2,18);

-- Petgrad (19)
INSERT INTO wp_posts (post_title,post_type,post_status,post_date,post_modified,post_date_gmt,post_modified_gmt,post_author,guid,comment_status,ping_status,post_name,to_ping,pinged,post_content,post_excerpt,post_content_filtered) VALUES
('Antun Petračić','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','antun-petracic','','','','',''),
('Zlatko Brgles','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','zlatko-brgles','','','','',''),
('Mladen Đurić','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','mladen-duric','','','','',''),
('Samir Huseinović','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','samir-huseinovic','','','','',''),
('Vladimir Toth','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','vladimir-toth','','','','','');
SET @pg1=LAST_INSERT_ID(); SET @pg2=@pg1+1; SET @pg3=@pg1+2; SET @pg4=@pg1+3; SET @pg5=@pg1+4;
INSERT INTO wp_kurs_squads (player_id,team_id,division_id,jersey_no) VALUES (@pg1,19,2,1),(@pg2,19,2,7),(@pg3,19,2,10),(@pg4,19,2,16),(@pg5,19,2,23);

-- Stakleni most (18)
INSERT INTO wp_posts (post_title,post_type,post_status,post_date,post_modified,post_date_gmt,post_modified_gmt,post_author,guid,comment_status,ping_status,post_name,to_ping,pinged,post_content,post_excerpt,post_content_filtered) VALUES
('Hrvoje Glasnović','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','hrvoje-glasnovic','','','','',''),
('Bojan Perković','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','bojan-perkovic','','','','',''),
('Renato Kovačević','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','renato-kovacevic','','','','',''),
('Matija Barić','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','matija-baric','','','','',''),
('Leon Galić','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','leon-galic','','','','','');
SET @sm1=LAST_INSERT_ID(); SET @sm2=@sm1+1; SET @sm3=@sm1+2; SET @sm4=@sm1+3; SET @sm5=@sm1+4;
INSERT INTO wp_kurs_squads (player_id,team_id,division_id,jersey_no) VALUES (@sm1,18,2,3),(@sm2,18,2,6),(@sm3,18,2,9),(@sm4,18,2,15),(@sm5,18,2,20);

-- Zvonarek sistemi (17)
INSERT INTO wp_posts (post_title,post_type,post_status,post_date,post_modified,post_date_gmt,post_modified_gmt,post_author,guid,comment_status,ping_status,post_name,to_ping,pinged,post_content,post_excerpt,post_content_filtered) VALUES
('Karlo Zvonarek','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','karlo-zvonarek','','','','',''),
('Dario Zvonarek','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','dario-zvonarek','','','','',''),
('Patrik Kušan','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','patrik-kusan','','','','',''),
('Andrej Balaž','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','andrej-balaz','','','','',''),
('Filip Novinc','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','filip-novinc','','','','','');
SET @zv1=LAST_INSERT_ID(); SET @zv2=@zv1+1; SET @zv3=@zv1+2; SET @zv4=@zv1+3; SET @zv5=@zv1+4;
INSERT INTO wp_kurs_squads (player_id,team_id,division_id,jersey_no) VALUES (@zv1,17,2,1),(@zv2,17,2,2),(@zv3,17,2,7),(@zv4,17,2,11),(@zv5,17,2,25);

-- ETM Elektroinstalacije (22)
INSERT INTO wp_posts (post_title,post_type,post_status,post_date,post_modified,post_date_gmt,post_modified_gmt,post_author,guid,comment_status,ping_status,post_name,to_ping,pinged,post_content,post_excerpt,post_content_filtered) VALUES
('Bruno Elektra','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','bruno-elektra','','','','',''),
('Jurica Modrić','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','jurica-modric','','','','',''),
('Niko Barišić','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','niko-barisic','','','','',''),
('Sandro Šarić','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','sandro-saric','','','','',''),
('Alen Vrdoljak','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','alen-vrdoljak','','','','','');
SET @et1=LAST_INSERT_ID(); SET @et2=@et1+1; SET @et3=@et1+2; SET @et4=@et1+3; SET @et5=@et1+4;
INSERT INTO wp_kurs_squads (player_id,team_id,division_id,jersey_no) VALUES (@et1,22,2,1),(@et2,22,2,10),(@et3,22,2,7),(@et4,22,2,4),(@et5,22,2,18);

-- Instal Term (23)
INSERT INTO wp_posts (post_title,post_type,post_status,post_date,post_modified,post_date_gmt,post_modified_gmt,post_author,guid,comment_status,ping_status,post_name,to_ping,pinged,post_content,post_excerpt,post_content_filtered) VALUES
('Vedran Termal','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','vedran-termal','','','','',''),
('Krešimir Pipić','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','kresimir-pipic','','','','',''),
('Marko Instalić','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','marko-instalic','','','','',''),
('Toni Vrban','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','toni-vrban','','','','',''),
('Dražen Kovač','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','drazen-kovac','','','','','');
SET @it1=LAST_INSERT_ID(); SET @it2=@it1+1; SET @it3=@it1+2; SET @it4=@it1+3; SET @it5=@it1+4;
INSERT INTO wp_kurs_squads (player_id,team_id,division_id,jersey_no) VALUES (@it1,23,2,3),(@it2,23,2,8),(@it3,23,2,11),(@it4,23,2,16),(@it5,23,2,22);

-- Marks (20)
INSERT INTO wp_posts (post_title,post_type,post_status,post_date,post_modified,post_date_gmt,post_modified_gmt,post_author,guid,comment_status,ping_status,post_name,to_ping,pinged,post_content,post_excerpt,post_content_filtered) VALUES
('Ognjen Vuković','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','ognjen-vukovic','','','','',''),
('Duško Pranjić','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','dusko-pranjic','','','','',''),
('Mirko Slišković','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','mirko-sliskovic','','','','',''),
('Tomislav Ivić','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','tomislav-ivic','','','','',''),
('Stjepan Oremuš','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','stjepan-oremus','','','','','');
SET @mk1=LAST_INSERT_ID(); SET @mk2=@mk1+1; SET @mk3=@mk1+2; SET @mk4=@mk1+3; SET @mk5=@mk1+4;
INSERT INTO wp_kurs_squads (player_id,team_id,division_id,jersey_no) VALUES (@mk1,20,2,6),(@mk2,20,2,9),(@mk3,20,2,12),(@mk4,20,2,17),(@mk5,20,2,21);

-- Mužek Kaminko (21)
INSERT INTO wp_posts (post_title,post_type,post_status,post_date,post_modified,post_date_gmt,post_modified_gmt,post_author,guid,comment_status,ping_status,post_name,to_ping,pinged,post_content,post_excerpt,post_content_filtered) VALUES
('Mladen Mužek','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','mladen-muzek','','','','',''),
('Toni Kamenko','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','toni-kamenko','','','','',''),
('Robert Varga','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','robert-varga','','','','',''),
('Damir Gradišar','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','damir-gradisar','','','','',''),
('Krešimir Nixo','kurs_igrac','publish',NOW(),NOW(),NOW(),NOW(),1,UUID(),'closed','closed','kresimir-nixo','','','','','');
SET @mz1=LAST_INSERT_ID(); SET @mz2=@mz1+1; SET @mz3=@mz1+2; SET @mz4=@mz1+3; SET @mz5=@mz1+4;
INSERT INTO wp_kurs_squads (player_id,team_id,division_id,jersey_no) VALUES (@mz1,21,2,1),(@mz2,21,2,5),(@mz3,21,2,9),(@mz4,21,2,14),(@mz5,21,2,20);


-- ── 2. REZULTATI (nasumični ali realistični za malonogometnu ligu) ─────────
-- Svaka grupa ima 6 utakmica (round-robin 4 ekipe)
-- Datumi: travanj–lipanj 2026

-- GRUPA A (group_id=5)
-- id1: Klikaj.hr(9) vs Auto Loris(10)
-- id2: Auto Loris(10) vs Montsistemi(11)
-- id3: Klikaj.hr(9) vs Lepal(24)
-- id4: Auto Loris(10) vs Lepal(24)
-- id5: Montsistemi(11) vs Klikaj.hr(9)
-- id6: Lepal(24) vs Montsistemi(11)

UPDATE wp_kurs_matches SET home_score=3, away_score=1, status='finished', match_date='2026-04-07 18:00:00' WHERE id=1;
UPDATE wp_kurs_matches SET home_score=2, away_score=2, status='finished', match_date='2026-04-07 19:30:00' WHERE id=2;
UPDATE wp_kurs_matches SET home_score=4, away_score=0, status='finished', match_date='2026-04-14 18:00:00' WHERE id=3;
UPDATE wp_kurs_matches SET home_score=1, away_score=3, status='finished', match_date='2026-04-14 19:30:00' WHERE id=4;
UPDATE wp_kurs_matches SET home_score=1, away_score=2, status='finished', match_date='2026-04-21 18:00:00' WHERE id=5;
UPDATE wp_kurs_matches SET home_score=2, away_score=1, status='finished', match_date='2026-04-21 19:30:00' WHERE id=6;
-- Rezultati A: Klikaj.hr 9b (3W), Lepal 6b (2W), Auto Loris 4b (1W1D), Montsistemi 1b (1D)

-- GRUPA B (group_id=6)
-- id7: Active Commerce(14) vs Naturavita(13)
-- id8: CB K Johannu(15) vs Kolarić(16)
-- id9: Active Commerce(14) vs Kolarić(16)
-- id10: Naturavita(13) vs CB K Johannu(15)
-- id11: Active Commerce(14) vs CB K Johannu(15)
-- id12: Kolarić(16) vs Naturavita(13)

UPDATE wp_kurs_matches SET home_score=2, away_score=3, status='finished', match_date='2026-04-08 18:00:00' WHERE id=7;
UPDATE wp_kurs_matches SET home_score=1, away_score=1, status='finished', match_date='2026-04-08 19:30:00' WHERE id=8;
UPDATE wp_kurs_matches SET home_score=3, away_score=2, status='finished', match_date='2026-04-15 18:00:00' WHERE id=9;
UPDATE wp_kurs_matches SET home_score=2, away_score=0, status='finished', match_date='2026-04-15 19:30:00' WHERE id=10;
UPDATE wp_kurs_matches SET home_score=1, away_score=2, status='finished', match_date='2026-04-22 18:00:00' WHERE id=11;
UPDATE wp_kurs_matches SET home_score=3, away_score=1, status='finished', match_date='2026-04-22 19:30:00' WHERE id=12;
-- Rezultati B: Naturavita 7b, Kolarić 7b, Active Commerce 6b, CB K Johannu 1b

-- GRUPA C (group_id=7)
-- id19: Mobiteli(25) vs Zvonarek(17)
-- id20: Petgrad(19) vs Stakleni most(18)
-- id21: Mobiteli(25) vs Stakleni most(18)
-- id22: Zvonarek(17) vs Petgrad(19)
-- id23: Mobiteli(25) vs Petgrad(19)
-- id24: Stakleni most(18) vs Zvonarek(17)

UPDATE wp_kurs_matches SET home_score=2, away_score=1, status='finished', match_date='2026-04-09 18:00:00' WHERE id=19;
UPDATE wp_kurs_matches SET home_score=3, away_score=3, status='finished', match_date='2026-04-09 19:30:00' WHERE id=20;
UPDATE wp_kurs_matches SET home_score=4, away_score=1, status='finished', match_date='2026-04-16 18:00:00' WHERE id=21;
UPDATE wp_kurs_matches SET home_score=0, away_score=2, status='finished', match_date='2026-04-16 19:30:00' WHERE id=22;
UPDATE wp_kurs_matches SET home_score=1, away_score=2, status='finished', match_date='2026-04-23 18:00:00' WHERE id=23;
UPDATE wp_kurs_matches SET home_score=2, away_score=0, status='finished', match_date='2026-04-23 19:30:00' WHERE id=24;
-- Rezultati C: Petgrad 7b, Mobiteli 6b, Stakleni most 4b, Zvonarek 3b

-- GRUPA D (group_id=8)
-- id13: ETM(22) vs Mužek(21)
-- id14: Instal Term(23) vs Marks(20)
-- id15: ETM(22) vs Marks(20)
-- id16: Mužek(21) vs Instal Term(23)
-- id17: ETM(22) vs Instal Term(23)
-- id18: Marks(20) vs Mužek(21)

UPDATE wp_kurs_matches SET home_score=3, away_score=0, status='finished', match_date='2026-04-10 18:00:00' WHERE id=13;
UPDATE wp_kurs_matches SET home_score=2, away_score=4, status='finished', match_date='2026-04-10 19:30:00' WHERE id=14;
UPDATE wp_kurs_matches SET home_score=2, away_score=1, status='finished', match_date='2026-04-17 18:00:00' WHERE id=15;
UPDATE wp_kurs_matches SET home_score=1, away_score=3, status='finished', match_date='2026-04-17 19:30:00' WHERE id=16;
UPDATE wp_kurs_matches SET home_score=4, away_score=1, status='finished', match_date='2026-04-24 18:00:00' WHERE id=17;
UPDATE wp_kurs_matches SET home_score=2, away_score=2, status='finished', match_date='2026-04-24 19:30:00' WHERE id=18;
-- Rezultati D: ETM 9b (3W), Marks 7b (2W1D), Instal Term 3b (1W), Mužek 1b (1D)

-- Provjera
SELECT
  g.name AS grupa,
  p.post_title AS ekipa,
  SUM(CASE WHEN m.home_team_id=gt.team_id THEN m.home_score
           WHEN m.away_team_id=gt.team_id THEN m.away_score END) AS gf,
  SUM(CASE WHEN m.home_team_id=gt.team_id THEN m.away_score
           WHEN m.away_team_id=gt.team_id THEN m.home_score END) AS ga,
  SUM(CASE
    WHEN m.home_team_id=gt.team_id AND m.home_score>m.away_score THEN 3
    WHEN m.away_team_id=gt.team_id AND m.away_score>m.home_score THEN 3
    WHEN m.home_score=m.away_score THEN 1 ELSE 0 END) AS pts
FROM wp_kurs_group_teams gt
JOIN wp_kurs_groups g ON g.id=gt.group_id
JOIN wp_posts p ON p.ID=gt.team_id
JOIN wp_kurs_matches m ON (m.home_team_id=gt.team_id OR m.away_team_id=gt.team_id) AND m.group_id=gt.group_id AND m.status='finished'
GROUP BY g.name, p.post_title
ORDER BY g.name, pts DESC;
