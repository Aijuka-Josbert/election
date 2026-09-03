# Change log — `feature/dual-voting-mode-and-fixes`

This branch is an incremental hardening of the existing election app, not a
from-scratch rewrite. Each commit is small and reviewable on its own —
`git log --oneline main..feature/dual-voting-mode-and-fixes`.

## What's done

**Security**

- Removed the live DB password and Google OAuth client secret from tracked
  `config/config.php` (they were committed in plaintext to a public repo).
  Now read from env vars / untracked `config/config.local.php`.
- CSRF tokens added to the vote form(s) and the admin settings form (neither
  had any before).

**Two voting workflows**

- Admin-selectable `voting_mode`: `rating` (existing multi-step 1–5 ballot,
  unchanged behavior) or `simple` (new — one contestant per category, one
  "Vote Now" submit).
- Every vote row is stamped with the mode it was cast under
  (`votes.mode`). Results/leaderboards filter by that stamp, so switching
  the admin setting later never reinterprets or blends older ballots into
  the new mode's results.

**Concurrency / duplicate-vote protection**

- Both ballot transactions take `SELECT ... FOR UPDATE` on the voter's user
  row before inserting, closing a real race: two concurrent simple-mode
  submissions picking different contestants in the same category didn't
  collide on the votes table's existing unique key, so both could commit.
- Double-submit / network-retry resubmission is handled by the same lock +
  `has_voted` check — no separate idempotency-token layer needed.

**Auditability**

- New `admin_audit_log` table + `log_admin_action()` helper.
- Settings page logs `voting_mode`, `voting_open`, and `results_public`
  changes with admin id/email/IP/timestamp.
- Settings page also warns the admin if votes already exist under a
  different mode than the one currently selected.

**Bug fixes**

- `certificate.php`: bogus `/Stonehenge` PDF font → `Helvetica-Bold`;
  "Download certificate" link no longer 403s for regular voters once
  results are public (matches `results.php`'s own visibility rule).
- Three separate, independently-drifting copies of the "is voting open"
  date/window calculation (`vote.php`, `admin/settings.php`, `results.php`)
  collapsed into one shared `voting_status_message()` helper.
- Three separate copies of the winner-ranking SQL (`vote.php`,
  `results.php`, `certificate.php`) collapsed into one shared, mode-aware
  `get_leaderboard()` helper.
- Regular voters (not just admins) now see the winners/results section
  after voting, once results are public.
- Winner announcements and certificate links reordered female-first, then
  male, per the requested order.

**Abuse protection**

- DB-backed fixed-window rate limiter (`rate_limit_allow()`), atomic
  across PHP-FPM workers via `ON DUPLICATE KEY UPDATE`, fails open on any
  DB error. Bucketed by authenticated user id where possible rather than
  raw IP — voters here are often behind shared campus WiFi NAT. Applied to
  vote submission, the OAuth callback, and admin settings saves.

**Safe category/contestant deletes**

- `categories`/`contestants` both had `votes ... ON DELETE CASCADE`
  foreign keys, so the old hard-delete admin actions would silently wipe
  historical votes with zero warning. Deletes now check for existing
  votes first and archive (`active = 0`) instead when any are found; a
  true hard delete only happens when nothing references the row. Added
  explicit Archive/Reactivate controls either way.
- Ballot-building queries in `vote.php` only offer `active = 1`
  categories/contestants; `get_leaderboard()`/`results.php` deliberately
  do **not** filter by `active`, so historical results stay correct even
  after something is archived.

**Ballot secrecy**

- New admin "Danger zone" action (`admin/settings.php`) nulls out
  `votes.user_id` for every vote once voting is closed — severs the
  voter↔choice link permanently with zero effect on tallies (dedup no
  longer needs that column once voting has ended). Requires typing
  `ANONYMIZE` to confirm, is logged, and is irreversible by design.
  Participation (`users.has_voted`) is untouched, so an admin can still
  see who voted, just not what they chose.

**More bugs found and fixed**

- Simple-mode ballot: an `all`-gender category rendered male and female
  contestant lists but every radio shared one `name` attribute, so
  picking a female contestant silently un-picked whichever male
  contestant was chosen (and vice versa) — only one winner total could
  ever be recorded per category, not one per gender. Radios are now
  scoped per category _and_ per gender.
- `admin/index.php` and `admin/stats.php` each had their own (4th and 5th)
  independent copy of the AVG(score)-only ranking SQL — now both use the
  shared `get_leaderboard()` helper, extended to also return full
  gender-sorted rankings for `stats.php`'s table, not just the #1 leader.
- `admin/index.php` now shows a clear status strip (voting open/closed +
  why, active mode, results public/private) — previously gave no
  indication of either.
- OAuth login CSRF: `login.php` never set a `state` parameter on the
  Google authorize URL. Without it, an attacker can capture their own
  authorization code and trick a victim into visiting
  `google-callback.php?code=<attacker's code>`, logging the victim in as
  the attacker. Fixed with a random per-session `state`, verified on
  return.
- `display_errors` is now explicitly forced off on the live host
  regardless of the shared hosting provider's `php.ini` defaults — errors
  are still logged, never shown to a voter's browser.
- Neither ballot form disabled its submit button on click, so an
  impatient double-click fired two overlapping requests (harmless
  server-side thanks to the row lock above, but confusing UX). Both now
  disable + relabel the button the instant they're submitted.

**Schema**

- `database/migrations/2026_08_30_mode_stamping_and_audit.sql` — adds
  `votes.mode` and `admin_audit_log`. Optional to run by hand: the app
  applies the same changes defensively on first use
  (`ensure_votes_mode_column()`, `ensure_audit_log_table()`), matching the
  existing `ensure_settings_table()` pattern already in the codebase.

## Deliberately not done in this pass

The scope of a full production rebuild (centralized mode _service_ class
hierarchy, URL/route restructuring, a full automated test suite, admin
CRUD for every conceivable election setting, ballot-secrecy beyond the
anonymization action above, a full multi-provider OAuth system) is real
but large — flagged here rather than claimed as finished.

Both voting modes deliberately share one `vote.php`/`results.php` URL
rather than getting separate routes — this was a design choice, not an
oversight: a single mode-aware entry point can't drift out of sync with
itself the way separate `/vote/rating` and `/vote/simple` routes could if
one were updated without the other.

## Round: certificate redesign, tie-breaking, hardening

- Certificate PDF generation rewritten using dompdf (HTML/CSS -> PDF)
  instead of hand-rolled PDF byte construction — fixes a real overlapping-
  text bug (two lines hardcoded to the same y-coordinate) and produces a
  properly designed certificate (decorative corners, embedded logo,
  dynamic institution name/colors). Requires `composer require dompdf/dompdf`.
- Image compression (GD-based resize + re-encode) applied to contestant
  photos and the new logo upload — verified against a real 583KB photo,
  reduced to 342KB with no visible quality loss.
- Logo can now be uploaded directly (JPG/PNG/WEBP), not just linked by URL.
- Deterministic tie-breaking: `get_leaderboard()` now has a reproducible
  sort order on ties (previously undefined which of two tied contestants
  a query returned "first") and explicit tie detection surfaced via
  `leaderboard_winner_label()` everywhere a winner is shown. Admins can
  record a manual decision via **Admin -> Tie Breaks**, logged and
  audited, which only applies while the same tie is still in effect.
- Admin idle timeout (10 min, server-enforced) with a return-to-where-
  you-were-after-login flow, protected against open-redirect.
- Font customization (curated Google Fonts list) applied site-wide.
- Root cause fixed for a `certificate.php` "Database connection is
  unavailable" report: `includes/session.php` was unconditionally
  resetting `$pdo` to null and re-requiring `db.php`, which silently
  no-ops if `db.php` was already loaded earlier in the request (as
  `certificate.php` does) — the reset stuck, the reload didn't happen.
- File upload hardening: `getimagesize()`-based real-image verification
  (independent of the MIME check already in place) plus
  `uploads/.htaccess` denying script execution outright.
- Rate limiter's increment-then-read replaced with a single atomic
  `LAST_INSERT_ID()`-based query.
- Proxy-aware IP resolution for audit logs / rate-limit buckets
  (`trusted_proxies` config, never blindly trusts `X-Forwarded-For`).
- Server-side input length limits on category/contestant/branding text
  fields, `robots.txt`, PCRE JIT warning silenced via `ini_set`.
- "Allow any email" toggle (skip the university-domain check) — off by
  default.

**Not done**: the full multi-OAuth-provider system (Facebook/GitHub/
generic OAuth2, per-provider admin config, unified callback) from the
original ask — a large, separate feature; implementing it hastily
risked regressing the Google login that already works. Needs its own
dedicated pass.

## Operational notes

- **Rotate the DB password and Google OAuth client secret** — the ones
  removed from `config.php` were live and already exposed publicly.
- **Don't switch `voting_mode` mid-election casually.** It's safe (older
  ballots are preserved and excluded from the new mode's results, and the
  admin gets a warning if this has happened), but voters mid-session will
  still submit against whichever mode was active when they loaded the
  page — plan mode changes for before voting opens where possible.
