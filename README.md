# UMU Varsity Ball — Election Voting System

PHP/MySQL election voting app for the UMU Varsity Ball: Google-OAuth login
restricted to a university email domain, two voting workflows (rating and
one-click), category/contestant management, live results, and PDF
certificates for winners.

## Requirements

- PHP 8.0+ with `pdo_mysql`
- MySQL 5.7+/MariaDB 10.3+ (InnoDB)
- Composer (for the Google API client under `vendor/`)
- A Google Cloud OAuth 2.0 client ID (Web application type)

## Install

```bash
git clone <this repo>
cd election
composer install
```

## Database setup

Import the base schema:

```bash
mysql -u <user> -p <database> < umu_vote.sql
```

Then run the migrations in `database/migrations/`, in filename order:

```bash
mysql -u <user> -p <database> < database/migrations/2026_08_30_mode_stamping_and_audit.sql
```

Running migrations by hand is optional but recommended for visibility —
the app also applies the same schema changes defensively on first use
(see `ensure_votes_mode_column()`, `ensure_active_column()`,
`ensure_audit_log_table()`, `ensure_rate_limits_table()` in
`includes/helpers.php`), the same pattern the app already used for
`app_settings` before this branch. Either way, nothing here destroys
existing data — every migration is additive (new column/table) and safe
to run more than once.

## Configuration

Real credentials must never go into `config/config.php` — it's tracked in
git. Copy the example local-override file and fill in real values:

```bash
cp config/config.local.php.example config/config.local.php
```

Edit `config/config.local.php`:

```php
return [
    'db' => [
        'host' => 'localhost',
        'name' => 'umu_vote',
        'user' => 'your_db_user',
        'pass' => 'your_db_password',
        'charset' => 'utf8mb4',
    ],
    'google' => [
        'client_id' => 'xxxx.apps.googleusercontent.com',
        'client_secret' => 'xxxx',
    ],
];
```

Alternatively, set `DB_HOST` / `DB_NAME` / `DB_USER` / `DB_PASS` /
`GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` as environment variables —
`config/config.php` reads both and `config.local.php` (if present) wins.

In the Google Cloud Console, add this app's callback URL as an authorized
redirect URI for your OAuth client — see `google-callback.php` and the
`redirect_uri` logic in `config/config.php`.

### Application settings (event title, dates, voting mode, etc.)

Everything election-specific — event date, voting open/closed, the voting
window, voting mode, whether results are public — is stored in the
database (`app_settings` table) and edited from **Admin → Settings**, not
hardcoded in PHP. `config/config.php` only holds infrastructure config
(DB connection, OAuth keys, upload paths).

Admin access is granted by email: add the address to
`config['app']['admin_emails']` in `config/config.php` (or
`config.local.php`).

## Running locally

Point a PHP dev server or your local Apache/Nginx vhost at the repo root,
e.g.:

```bash
php -S localhost:8000
```

Then visit `/login.php` to sign in with a Google account on the allowed
domain (`config['app']['allowed_domain']`).

## Creating an election

1. **Admin → Categories** — add categories, each assigned `male`,
   `female`, or `all` (applies to both genders — e.g. "Best Dressed").
2. **Admin → Contestants** — add contestants with a photo, gender, and
   optional bio.
3. **Admin → Settings** — set the event date, choose a voting mode (see
   below), set the voting window (or leave blank + toggle "voting open"
   manually), and decide whether results are public before voting closes.

## Voting modes

Two ballot workflows, switchable per-election from **Admin → Settings**:

- **Rating** (default) — voters rate every contestant 1–5 in every
  category, one submit at the end.
- **Simple** — voters pick one contestant per category (and, for an
  `all`-gender category, one male pick *and* one female pick — same as
  rating mode treats both genders independently) and submit once.

Both modes share the same `vote.php` URL, the same auth/CSRF/rate-limit
protection, and the same `votes` table — there's no separate URL or route
per mode, so there's no risk of one page thinking a different mode is
active than another page does. Every vote row is stamped with the mode it
was cast under (`votes.mode`), so switching this setting mid-election
never reinterprets or blends older ballots into the new mode's results —
see `get_leaderboard()` in `includes/helpers.php`.

**Switch modes before voting opens where possible.** It's safe either way
(older ballots are preserved and excluded from the new mode's results,
and Settings warns you if this has already happened), but a voter who has
the vote page open mid-session will still submit against whichever mode
was active when they loaded it.

## Results & certificates

- **Results** (`results.php`) are visible to admins always, and to
  everyone once **Admin → Settings → Make results visible to everyone**
  is on.
- **Certificates** (`certificate.php?gender=male|female`) generate a PDF
  for the current overall winner. Same visibility rule as results.

## Ballot secrecy

While voting is open, `votes.user_id` is required — it's what the
one-vote-per-user protection (a unique key plus a row lock, see
Concurrency below) is built on. Once voting is closed, that's no longer
needed for anything: **Admin → Settings → Danger zone** can permanently
sever the voter↔choice link (`UPDATE votes SET user_id = NULL`) with zero
effect on any score, contestant, or category — results are provably
unaffected. This is irreversible, requires typing `ANONYMIZE` to confirm,
and is logged. Participation (`users.has_voted`) is untouched, so an
admin can still see *who* voted, just not *what* they chose.

## Safe deletes

Categories/contestants have `ON DELETE CASCADE` to `votes`. Deleting one
that already has votes recorded would silently destroy those historical
ballots — so **Admin → Categories/Contestants → Delete** checks for
existing votes first and archives (`active = 0`, hidden from new ballots,
kept in historical results) instead of deleting whenever any are found. A
true hard delete only happens when nothing references the row. Explicit
Archive/Reactivate controls are available either way.

## Concurrency & duplicate-vote protection

- A `UNIQUE(user_id, contestant_id, category_id)` key on `votes` is the
  database-level backstop.
- Each ballot submission takes `SELECT has_voted FROM users WHERE id = ?
  FOR UPDATE` before inserting, so two concurrent requests from the same
  voter (double-click, two tabs, a network retry) serialize on that
  voter's row — whichever commits first wins, the second is treated as a
  graceful "already voted" rather than a duplicate or a crash.
- The whole ballot insert is one transaction: either every category is
  recorded or none are.

## Rate limiting

A small MySQL-backed fixed-window limiter (`rate_limit_allow()` in
`includes/helpers.php`, atomic via `ON DUPLICATE KEY UPDATE`, so it's
correct across multiple PHP-FPM workers with no APCu/Redis dependency)
guards vote submission, the OAuth callback, and admin settings saves. It
fails **open** on any DB error — an outage in the limiter itself must
never be the reason a legitimate vote gets rejected. It's bucketed by
authenticated user id where possible rather than raw IP, since voters
here are often behind shared campus/hostel WiFi NAT.

## Security notes

- **CSRF**: every state-changing form (vote, admin settings, categories,
  contestants, users) requires a session-bound token (`csrf_field()` /
  `csrf_verify()`).
- **OAuth login CSRF**: `login.php` sets a random `state` parameter,
  verified in `google-callback.php` before exchanging the authorization
  code — prevents an attacker's authorization code from being used to log
  a victim in as the attacker.
- **SQL injection**: all queries use prepared statements; the one place
  a table name is interpolated into DDL (`ensure_active_column()`) is
  guarded by a hardcoded whitelist.
- **XSS**: all dynamic output goes through `h()` (an `htmlspecialchars()`
  wrapper).
- **Sessions**: HttpOnly + SameSite=Lax cookies, Secure flag on HTTPS,
  session ID regenerated on login.
- **Error handling**: `display_errors` is forced off on the live host
  regardless of the shared hosting provider's `php.ini` defaults — errors
  are still logged, never shown to a voter's browser.
- **Audit log**: sensitive admin actions (voting mode/window/results
  visibility changes, category/contestant archive/delete, ballot
  anonymization, rate-limit trips) are recorded in `admin_audit_log` with
  admin id/email/IP/timestamp.

Do not skip rotating your DB password and Google OAuth client secret if
they were ever committed to git history before this branch — see
`CHANGES.md`.

## Repository layout

```
/admin              admin dashboard, settings, categories, contestants, users, stats
/assets              css/js/images
/config              config.php (tracked, no secrets) + config.local.php (untracked)
/database/migrations SQL migrations (also self-applied defensively — see above)
/includes            shared PHP: auth, session, db, helpers (CSRF, rate limiting,
                      leaderboard, voting-window/mode logic, audit log)
vote.php             the ballot — both voting modes, one URL
results.php          public/admin results
certificate.php      PDF certificate generation
login.php / google-callback.php   Google OAuth
```

## Testing before you rely on this

There's no automated test suite yet. Before an election goes live, at
minimum manually verify:

- A full ballot submits correctly in both voting modes, including an
  `all`-gender category showing and recording both a male and a female
  pick in simple mode.
- Double-submitting (double-click, or two browser tabs) records exactly
  one vote, not zero and not two.
- Switching voting mode mid-testing doesn't corrupt or blend previous
  test votes into the new mode's leaderboard (check **Admin → Settings**
  for the "votes cast under a different mode" warning).
- A category/contestant that already has test votes archives instead of
  deletes when you try to remove it.
- Results/certificate visibility matches the `results_public` toggle for
  a non-admin account.

See `CHANGES.md` for the detailed list of what's been fixed and hardened
on this branch, and what's explicitly still out of scope.
