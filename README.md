# HireCraft

Explainable artisan–job matching, budget-compatibility and job-feasibility
decision-support system for Ghana, built as a BSc IT final-year Design
Science Research project.

HireCraft lets a customer describe a job — with photos of the work needed —
then ranks verified artisans against it with **SmartMatch** — a transparent
scoring engine that shows *why* each artisan was ranked where they were
(skill, location, budget, availability, experience, trust, rating and
portfolio), alongside a budget-compatibility check and a job-feasibility
read, instead of a black-box recommendation. Customers can also compare
2–3 shortlisted artisans side by side, and artisans can build a photo
portfolio, browse and apply to publicly-listed jobs (two-sided matching),
and see the "SmartMatch score" of jobs suggested to them. A "was this
helpful?" prompt on every results page, with an admin CSV export, captures
the evaluation data this project's Chapter 4 needs.

This is a plain PHP 8 + MySQL/MariaDB + HTML/CSS/JS application. It has
**no Composer dependency and no framework** — it runs on any shared host or
local XAMPP install with nothing to install beyond PHP and MySQL/MariaDB.

All data in the demo (artisans, customers, job history, reviews) is
clearly-labelled **synthetic sample data** for demonstration purposes, not
findings from the requirements study — see the design document for that.

## Requirements

- PHP 8.1 or newer, with the `pdo_mysql` extension (bundled with PHP by default)
- MySQL 5.7+/8.0+ or MariaDB 10.4+
- A browser. No Node, no Composer, no build step.

## Quick start (XAMPP / local MySQL)

1. **Get the files onto your server.** For XAMPP, copy (or clone) this
   `webapp/` folder's *contents* into `htdocs/hirecraft/`, so
   `htdocs/hirecraft/public/index.php` exists. If you're using the PHP
   built-in server instead (see below), you can leave the folder wherever
   you like.

2. **Create the database.** Open phpMyAdmin (or the `mysql` CLI) and create
   an empty database, then import the schema:

   ```
   mysql -u root -p -e "CREATE DATABASE hirecraft CHARACTER SET utf8mb4"
   mysql -u root -p hirecraft < database/schema.sql
   ```

   (In phpMyAdmin: create a database named `hirecraft`, then use its
   **Import** tab to load `database/schema.sql`.)

3. **Configure the database connection**, if your setup differs from the
   XAMPP defaults (`root` with no password on `127.0.0.1:3306`). The app
   reads its config from environment variables with sane local defaults —
   see `config/config.php`. You don't need to create a `.env` file; on a
   typical XAMPP install the defaults just work. If you need to override
   something (e.g. a MySQL root password, or a different host), set the
   relevant environment variable before running PHP, or edit
   `config/config.php` directly:

   | Variable       | Default     | Purpose                              |
   |----------------|-------------|---------------------------------------|
   | `HC_DB_HOST`   | `127.0.0.1` | MySQL host                            |
   | `HC_DB_PORT`   | `3306`      | MySQL port                            |
   | `HC_DB_NAME`   | `hirecraft` | Database name                         |
   | `HC_DB_USER`   | `root`      | MySQL user                            |
   | `HC_DB_PASS`   | *(empty)*   | MySQL password                        |
   | `HC_BASE_URL`  | *(auto-detected)* | Only set this to override auto-detection (e.g. behind a reverse proxy that rewrites paths) |
   | `HC_DEBUG`     | `true`      | Shows PHP errors; set to `0` in prod  |

   > **Note on subfolder installs (e.g. XAMPP's `htdocs/hirecraft/`):** the
   > app auto-detects its own base URL from the request, so
   > `http://localhost/hirecraft/public/` works without setting
   > `HC_BASE_URL` by hand — every link and asset path is generated with
   > the right prefix automatically. Only set `HC_BASE_URL` yourself if
   > you're behind something (like a reverse proxy) that rewrites the path
   > in a way auto-detection can't see.

   > **Note:** if you connect to MySQL over TCP (`127.0.0.1`) and get an
   > access-denied error even with the right password, try setting
   > `HC_DB_HOST=localhost` instead — this makes PHP use the local MySQL
   > socket, which avoids some `root@127.0.0.1` account/auth-plugin
   > mismatches that a few local MySQL installs have out of the box.

4. **Seed demo data.** From the `webapp/` folder, run:

   ```
   php database/seed.php
   ```

   This populates 28 fully-profiled artisans across 5 trades (carpentry,
   electrical, masonry, painting, plumbing) and 24 areas in 9 cities across
   Ghana (Kumasi, Accra, Takoradi, Tamale, Cape Coast, Sunyani, Ho,
   Koforidua and more), 780 historical completed jobs with reviews (so
   trust scores and ratings are backed by real rows) and one sample photo
   per portfolio item, price benchmarks, the initial matching-weights
   configuration, and one demo customer with a publicly-listed job already
   posted and matched. Re-running it wipes and re-seeds everything
   (including previously-uploaded job/portfolio photo files), so it's safe
   to use whenever you want a clean demo state.

5. **Run it.**

   - **XAMPP / Apache:** start Apache and MySQL from the XAMPP control
     panel, then visit `http://localhost/hirecraft/public/`. (An
     `.htaccess` file under `public/` handles clean URLs — make sure
     Apache's `mod_rewrite` is enabled and `AllowOverride All` is set for
     the folder, which is the XAMPP default.)
   - **PHP's built-in server** (no Apache needed — good for quick local
     testing): from the `webapp/` folder, run:

     ```
     php -S localhost:8080 -t public public/router.php
     ```

     then visit `http://localhost:8080/`.

6. **Log in** with any of the seeded demo accounts (password for all:
   `Passw0rd!`):

   | Role     | Email                              |
   |----------|--------------------------------------|
   | Admin    | `admin@hirecraft.test`                |
   | Customer | `customer@hirecraft.test`             |
   | Artisan  | `kwame.boateng@hirecraft.test` (Kumasi) or `nii.ashong@hirecraft.test` (Accra) — 26 others across Ghana, see `database/seed_data.json` |

   Or register a new account from the homepage to try the full
   registration → profile setup → job posting → SmartMatch results →
   quotation → hire → tracking → review flow yourself.

## What to click through

- **As the demo customer:** dashboard → your posted job → "View SmartMatch
  results" to see ranked artisans with a "Why this match?" breakdown for
  each, plus the budget check and feasibility read above the results. Tick
  2–3 artisans and hit "Compare selected" for a side-by-side comparison
  table. Request a quotation to try the hiring flow, and answer the "Was
  this helpful?" prompt at the bottom of the results page. Post a new job
  from "My jobs" to try photo upload, the emergency-urgency option, and the
  "let artisans discover and apply" option. "Find an artisan" browses and
  filters the artisan directory directly (trade, skills, area, trust,
  rating, verification, experience, budget compatibility, emergency-ready,
  available today) rather than through a specific job. "Favorites" saves
  artisans you've starred from their profile page. Once hired, the job
  detail page has a "Message" button (a private thread per job/artisan
  pair), a "Report a problem"/dispute link, and — for multi-trade or
  complex jobs — a milestones tracker. The bell icon in the header shows
  notifications for every status change on your jobs.
- **As an artisan** (e.g. `kwame.boateng@hirecraft.test`): "Find jobs" to
  browse and apply to publicly-listed jobs ranked against your own profile
  (two-sided SmartMatch); "Job requests" to see and quote incoming
  requests (from either a customer's request or your own application);
  "Portfolio" to add photos of finished work; "Verifications" to submit an
  identity document, skill certificate or reference letter for admin
  review (raises your trust score and verification badge once approved);
  "My profile" for the trust score, verification level, skills, service
  areas, availability and pricing. Click any artisan's name from a
  customer view to see their public profile and portfolio at
  `/artisans/{id}`.
- **As the admin:** the dashboard's KPI cards and four Chart.js charts
  (jobs by status, jobs by trade, sign-ups over time, trust-score spread);
  the verification queue for artisan approval and, separately,
  "Verifications" for identity/skill/reference document review; the live
  matching-weights editor (with a what-if preview against a real posted
  job before you save a new configuration); the price-benchmark table
  that drives the budget check; "Reports" and "Disputes" for the safety
  queues; and "Evaluation" for the raw "was this helpful?" feedback data
  (with a CSV export) behind Chapter 4's user evaluation.

## Project structure

```
public/            Front controller (index.php), .htaccess, dev-server
                    router.php, and static assets (public/assets/)
  uploads/          Job and portfolio photos, written at runtime (job/*,
                    portfolio/*); .htaccess there disables PHP execution
                    and directory listing. Re-created empty by seed.php.
src/
  Controllers/      One controller per feature area (auth, profile, jobs,
                    hiring/tracking, admin)
  Engine/           SmartMatchEngine — a faithful PHP port of the design's
                    reference JS engine (design/engine/engine.js); this is
                    the scoring/explanation logic itself
  Repo/             Builds the plain-array inputs the engine expects from
                    the live database (Lookups, MatchData, Users)
  Support/          Router, Db (PDO wrapper), Auth, Response, View, helpers,
                    Uploads (validated photo/document upload handling),
                    Notifier (in-app notifications), TrustScorer (shared
                    trust-score/verification-level recompute)
views/              PHP view templates, grouped by feature area
public/assets/js/   Vendored third-party JS (chart.umd.js — Chart.js,
                    pinned, self-hosted rather than CDN-loaded so the
                    admin dashboard's charts work with no internet access)
database/
  schema.sql        Full database schema (38 tables)
  seed.php          Demo data generator (idempotent — safe to re-run)
  seed_data.json    The underlying reference data (trades, skills, areas,
                    synthetic artisan profiles) used by seed.php
  seed_assets/      Generated placeholder portfolio photos seed.php copies
                    into public/uploads/ so the photo features have
                    something to show out of the box
tests/
  EngineTest.php    25 reference tests for the SmartMatch engine itself —
                     run with `php tests/EngineTest.php`
config/config.php   Environment-variable-driven configuration
```

## Verifying the engine

The scoring engine has its own standalone test suite (independent of the
database or web server):

```
php tests/EngineTest.php
```

This checks scoring, explanations, budget assessment, feasibility, the
two-sided "recommend jobs to an artisan" view, and ranking-stability
sensitivity — all 25 should pass.

## Security notes for this demo build

This is a final-year project deliverable, not a production deployment.
Before putting it anywhere public, at minimum: set `HC_DEBUG=0`, use a
dedicated (non-root) MySQL user with only the privileges this app needs,
put a real password on that account, and serve over HTTPS. CSRF protection
(a session-bound double-submit token) is already enforced on every POST
route.
