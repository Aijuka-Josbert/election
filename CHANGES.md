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

**Schema**
- `database/migrations/2026_08_30_mode_stamping_and_audit.sql` — adds
  `votes.mode` and `admin_audit_log`. Optional to run by hand: the app
  applies the same changes defensively on first use
  (`ensure_votes_mode_column()`, `ensure_audit_log_table()`), matching the
  existing `ensure_settings_table()` pattern already in the codebase.

## Deliberately not done in this pass

The scope of a full production rebuild (centralized mode *service* layer,
route restructuring, rate limiting, ballot-secrecy separation, admin CRUD
for categories/contestants beyond what already exists, automated test
suite, full README rewrite) is real but large — flagged here rather than
claimed as finished. See the conversation this branch came from for the
prioritized list of what's next.

## Operational notes

- **Rotate the DB password and Google OAuth client secret** — the ones
  removed from `config.php` were live and already exposed publicly.
- **Don't switch `voting_mode` mid-election casually.** It's safe (older
  ballots are preserved and excluded from the new mode's results, and the
  admin gets a warning if this has happened), but voters mid-session will
  still submit against whichever mode was active when they loaded the
  page — plan mode changes for before voting opens where possible.
