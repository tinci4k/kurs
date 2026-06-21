# kurskc.hr — WordPress projekt

## O projektu
Web stranica za lokalno sportsko udruženje iz Koprivnice koje organizira malonogometne turnire:
- **KC liga** — glavna liga
- **Pan ljetna liga** — ljetna natjecanja

## Tehnički stack
- **WordPress** lokalno via Local by Flywheel
- **Lokalni URL**: http://kurshr.local
- **Tema**: `wp-content/themes/kurskc` (vlastita tema od nule)
- **PHP**: 8.1+
- **DB**: `local` (root/root)

## Struktura teme (`wp-content/themes/kurskc/`)
```
kurskc/
├── assets/
│   ├── css/main.css      — svi stilovi
│   └── js/main.js        — mobilni meni i interakcija
├── inc/
│   ├── post-types.php    — CPT: momcad, utakmica, vijest + taksonomije liga/sezona
│   ├── enqueue.php       — enqueue CSS/JS
│   └── nav-menus.php     — registracija izbornika
├── template-parts/
│   ├── content.php       — kartica posta
│   └── content-none.php  — prazan rezultat
├── functions.php
├── header.php / footer.php
├── index.php / page.php / single.php / 404.php
└── style.css             — theme header
```

## Prilagođeni tipovi objava (CPT)
| Slug | Naziv | Taksonomije |
|------|-------|-------------|
| `momcad` | Momčadi | liga, sezona |
| `utakmica` | Utakmice | liga, sezona |
| `vijest` | Vijesti | — |

## Taksonomije
- `liga` — KC liga, Pan ljetna liga (hierarchical)
- `sezona` — npr. 2025/26, Ljeto 2026 (flat)

## Razvojne napomene
- WP_DEBUG je uključen, log se piše u `wp-content/debug.log`
- CSS koristi CSS varijable (`:root`) — mijenjaj boje samo tamo
- Primarne boje: `--color-primary: #1a3a6b`, `--color-accent: #e8b84b`
