# Migration 030 — Widen `sessions.user_id` from `bigint` to `varchar(36)`

**File:** `database/migrations/20260624000002_widen_sessions_user_id_for_ulids.php`
**Date:** 2026-06-24
**Type:** Schema change, cross-feature gotcha

## Why this exists

Laravel's default `sessions` table (created in `0001_01_01_000000_create_users_table.php`) declares `user_id` as `bigint unsigned`, sized for the mechanic `users.id` (auto-increment integer). When the customer guard was introduced in the Customer Portal feature, the `customers.id` column became a 26-char ULID. The database session driver writes the auth identifier to `sessions.user_id`, and on the first customer login MySQL returned:

```
SQLSTATE[01000]: Warning: 1265 Data truncated for column 'user_id' at row 1
```

This is a **silent post-controller crash**: the dashboard query had already completed, but session save (in `StartSession::terminate`) failed and Laravel returned a 500 to the browser.

## What it does

Changes `sessions.user_id` to `varchar(36)`. The width covers:
- mechanic ints (auto-cast to string by the driver, max ~19 chars)
- customer ULIDs (26 chars)
- any future UUID-keyed guards (36 chars)

Nullable + indexed remain unchanged.

## Why widen instead of separate session tables per guard

Laravel ties one session driver to one `sessions` table. Adding a second guard with a different ID shape means either:
1. Widening the column (this migration)
2. Forking `DatabaseSessionHandler` to write to a per-guard table
3. Switching the customer guard to a non-DB driver (file, redis)

Option 1 is the lowest-blast-radius — no driver fork, mechanic sessions unaffected, ulid + bigint coexist as strings.

## Rollback

`unsignedBigInteger('user_id')->nullable()->change()`. Any extant customer sessions will fail to restore on the next read (column truncate on load is also blocked) and Laravel will start fresh. No data loss; users re-login.

## Gotcha for future guards

If a third guard is added with yet another ID shape (e.g., 64-char hashed IDs), increase the width again or move to per-guard tables. `app-map.md` carries a one-line gotcha pointing here.
