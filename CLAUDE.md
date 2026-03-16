# Idea & Memory Vault

> A file-system-native knowledge management system. No code — just ideas, structured
> documents, and the connections between them. Claude manages the vault: filing ideas,
> maintaining indexes, enforcing structure, and versioning everything through git.

---

## Project Structure

```
memory/
├── CLAUDE.md                        # This file — Claude's operating manual
├── .gitignore
├── _registry/
│   └── domains.yml                  # Canonical domain/subdomain registry
├── _templates/
│   ├── fleeting.md
│   ├── project.md
│   ├── decision.md
│   ├── research.md
│   ├── journal.md
│   ├── note.md
│   └── default.md
├── _scripts/
│   └── vault-cli/                   # PHP Symfony Console CLI (bin/vault)
├── _index/
│   ├── schema.sql                   # SQLite schema definition
│   └── vault.db                     # SQLite index database (derived, rebuildable)
└── vault/
    ├── tech/
    │   ├── fleeting/2026/03/
    │   ├── projects/2026/03/
    │   └── research/2026/03/
    ├── business/
    │   ├── decisions/2026/03/
    │   └── strategy/2026/03/
    ├── personal/
    │   ├── journal/2026/03/
    │   └── goals/2026/03/
    ├── creative/
    │   ├── writing/2026/03/
    │   └── worldbuilding/2026/03/
    └── meta/
        └── todos/2026/03/
```

### Directory Rules

- All idea documents live under `vault/{domain}/{subdomain}/{YYYY}/{MM}/`
- Directories are created **on-demand** — do not pre-create empty directories
- System files (`_registry/`, `_templates/`, `_index/`, `_scripts/`) live at the project root
- NEVER nest ideas inside other ideas — the knowledge graph handles relationships

---

## File Naming Convention

```
Format:  YYYYMMDD-HHMMSS-slugified-title.md
Example: 20260314-103000-ai-agents-replace-project-managers.md
```

**Rules:**
- All lowercase
- Hyphens replace spaces
- Maximum 60 characters for the slug portion
- Timestamp prefix ensures uniqueness and natural sort order
- Use the creation timestamp, not the current time when editing

---

## Document Format

Every document in the vault follows the same structure: a **structured YAML header**
(20–50 lines) followed by a **body** that uses the appropriate subdomain template.

### Header Specification

```yaml
---
id: "YYYYMMDD-HHMMSS-slug"
title: "Human-Readable Title"
domain: tech
subdomain: research
status: seed
created: 2026-03-14T10:30:00
modified: 2026-03-14T10:30:00
tags: [tag1, tag2, tag3]
priority: p3-low

links:
  parent: []
  children: []
  related: []
  inspired_by: []
  blocks: []
  blocked_by: []
  evolved_into: []

sources:
  - url: "https://example.com"
    title: "Source Title"
    accessed: 2026-03-14

confidence: speculative
effort: medium
revisit_date: null
close_reason: null
summary: "One-line summary for indexing and quick scanning"

external_refs: []
#  - url: "https://github.com/example/repo"
#    label: "Repository"

todos:
  - task: "Research competing approaches"
    due: 2026-04-01
    priority: p2-medium
    status: open
---
```

**Header Rules:**
- Every field listed above MUST be present (use `null`, `[]`, or `""` for empty values)
- `id` MUST match the filename (without `.md` extension)
- `domain` and `subdomain` MUST exist in `_registry/domains.yml`
- `modified` is updated on every edit
- `tags` are lowercase, hyphenated, no duplicates
- `links` use the target document's `id` value
- `todos` are embedded in the document they relate to

**Book-specific header fields** (added after `summary`, only for `entertainment/books` documents):
```yaml
author: "Author Name"
series: null              # series document id (also mirrored in links.parent)
series_order: null        # 1, 2, 3... (use .5 for novellas/side stories)
rating: null              # S/A/B/C/D/E/F/DNF (null for technical books)
cover_url: null           # link to cover image (not stored locally)
reads:                    # list of dates read (most recent last)
  - 2025-01-15
  - 2026-03-01
```

**Valid values:**
- `status`: seed | growing | mature | dormant | closed | migrated | built | tbr (books only)
- `priority`: p0-critical | p1-high | p2-medium | p3-low | p4-someday
- `confidence`: speculative | low | medium | high | proven
- `effort`: trivial | small | medium | large | epic
- `rating` (books only): S | A | B | C | D | E | F | DNF

### Status Lifecycle

```
seed → growing → mature → dormant (can re-enter growing)
                        → closed
                        → migrated (link to destination via evolved_into)
                        → built (became a real thing)

Books: tbr → growing (reading) → mature (read & rated)
                               → dormant (paused)
```

- `seed`: Raw capture, minimal detail
- `growing`: Actively being developed (or currently reading, for books)
- `mature`: Fully fleshed out, stable (or read & rated, for books)
- `dormant`: Not currently active but not dead (or paused reading)
- `closed`: Intentionally ended, no longer relevant
- `migrated`: Moved to another system/project (set `evolved_into` link)
- `built`: Successfully realized
- `tbr`: To Be Read — books queued for future reading (books only)

### Body Templates

After the YAML header, the body follows a template based on the document's subdomain.
Templates are stored in `_templates/` and Claude applies them when creating new documents.

If no specific template exists for a subdomain, use `_templates/default.md`.

The user can always override or ignore the template structure in the body — templates
are starting scaffolds, not rigid constraints.

---

## Domain & Subdomain Registry

The canonical list of domains and subdomains lives in `_registry/domains.yml`.
Claude MUST consult this before creating any new document.

### Governance Rules

1. **Before creating a document**, verify the domain and subdomain exist in the registry
2. **If the user mentions a new domain or subdomain**, ask:
   *"That's a new [domain|subdomain]. Should I add `{name}` under `{parent}`?
   Here's what currently exists: [list]. Does it fit somewhere existing,
   or is this genuinely new?"*
3. **After user confirmation**, update `_registry/domains.yml` and commit the change
4. **Never silently create** a new domain or subdomain — always get explicit confirmation
5. **Periodically audit** for similar/overlapping subdomains and suggest merges

---

## SQLite Index Database

The vault uses a SQLite database at `_index/vault.db` as a queryable index over all
documents. The markdown files are the **source of truth** — the database is a
**derived cache** for fast querying.

### Initialization

On first session or if `vault.db` does not exist:
```bash
sqlite3 _index/vault.db < _index/schema.sql
```

### Table Column Reference

Use these exact column names when writing SQL — do NOT guess:

| Table | Columns |
|---|---|
| `documents` | `id`, `title`, `domain`, `subdomain`, `status`, `priority`, `confidence`, `effort`, `summary`, `file_path`, `created_at`, `modified_at`, `revisit_date`, `close_reason` |
| `tags` | `doc_id`, `tag` |
| `links` | `source_id`, `target_id`, `link_type`, `created_at` |
| `sources` | `id` (auto), `doc_id`, `url`, `title`, `accessed_date` |
| `books` | `doc_id`, `author`, `series_id`, `series_order`, `rating`, `cover_url` |
| `reads` | `id` (auto), `doc_id`, `date_read`, `created_at` |
| `todos` | `id` (auto), `doc_id`, `content`, `due_date`, `status`, `priority`, `created_at` |
| `external_refs` | `id` (auto), `doc_id`, `url`, `label`, `created_at` |
| `documents_fts` | `id`, `title`, `summary`, `domain`, `subdomain` |

### Database Updates

**After every document create/edit/delete**, Claude updates the relevant rows:
- Parse the document's YAML frontmatter
- INSERT OR REPLACE into `documents`, `tags`, `links`, `sources`, `todos` tables
- For `entertainment/books` documents: also INSERT OR REPLACE into `books` table, and sync `reads` table from the `reads` list in YAML
- When deleting: remove from all tables (but remember — we never delete documents,
  only change status)

### Query Patterns

| User Request | SQL Approach |
|---|---|
| "What do I have on X?" | `SELECT * FROM documents_fts WHERE documents_fts MATCH 'X'` |
| "Show all seed ideas in tech" | `SELECT * FROM documents WHERE domain='tech' AND status='seed'` |
| "What's connected to idea Y?" | `SELECT * FROM links WHERE source_id='Y' OR target_id='Y'` |
| "Overdue TODOs" | `SELECT * FROM todos WHERE due_date < date('now') AND status='open'` |
| "Ideas to revisit today" | `SELECT * FROM documents WHERE revisit_date <= date('now')` |
| "Tag cloud" | `SELECT tag, COUNT(*) FROM tags GROUP BY tag ORDER BY COUNT(*) DESC` |
| "Orphaned ideas" | Left join documents against links where no links exist |
| "Stats" | `SELECT status, COUNT(*) FROM documents GROUP BY status` |
| "What's on my plate?" | `SELECT * FROM todos WHERE status='open' ORDER BY priority, due_date` |
| "Ideas created this week" | `SELECT * FROM documents WHERE created_at >= date('now', '-7 days')` |
| "All S-tier books" | `SELECT d.title, b.author FROM books b JOIN documents d ON b.doc_id=d.id WHERE b.rating='S'` |
| "Books by author X" | `SELECT d.title, b.rating FROM books b JOIN documents d ON b.doc_id=d.id WHERE b.author LIKE '%X%'` |
| "Books in series Y" | `SELECT d.title, b.series_order, b.rating FROM books b JOIN documents d ON b.doc_id=d.id WHERE b.series_id='Y' ORDER BY b.series_order` |
| "Reading stats this year" | `SELECT b.rating, COUNT(*) FROM books b JOIN reads r ON b.doc_id=r.doc_id WHERE r.date_read >= date('now', 'start of year') GROUP BY b.rating` |
| "Recent reads" | `SELECT d.title, b.author, b.rating, r.date_read FROM reads r JOIN books b ON r.doc_id=b.doc_id JOIN documents d ON b.doc_id=d.id ORDER BY r.date_read DESC LIMIT 20` |
| "Most re-read books" | `SELECT d.title, b.author, COUNT(*) as times_read FROM reads r JOIN books b ON r.doc_id=b.doc_id JOIN documents d ON b.doc_id=d.id GROUP BY r.doc_id ORDER BY times_read DESC` |
| "What did I re-read this year?" | `SELECT d.title, r.date_read FROM reads r JOIN documents d ON r.doc_id=d.id GROUP BY r.doc_id HAVING COUNT(*)>1 AND MAX(r.date_read) >= date('now', 'start of year')` |

### Database Maintenance

- **Auto-update**: After every file operation, update the affected rows
- **Full rebuild**: On user request, or if inconsistencies are detected
- **Integrity check**: If a query returns unexpected results, rebuild before reporting
- The database file IS committed to git
- SQLite WAL/journal files are gitignored

---

## Vault CLI (`_scripts/vault-cli/bin/vault`)

A PHP 8.3+ Symfony Console application providing all database read and write
operations. **Use this instead of writing inline SQL** — it handles prepared
statements, escaping, and validation.

```bash
_scripts/vault-cli/bin/vault <command> [args]
```

### Read Commands

| Command | Description |
|---|---|
| `briefing` | Full daily briefing (overdue, due today, revisit, recent activity, stats) |
| `todos [--all]` | Open TODOs by priority/due date (`--all` includes done/cancelled) |
| `search <query>` | Full-text and tag search |
| `stats` | Status, domain, priority, and tag breakdowns |
| `recent [days]` | Recently created/updated documents (default: 2 days) |
| `orphans` | Documents with no links |
| `integrity` | DB vs file count, broken links, journal mode check |
| `rebuild` | Drop and rebuild the entire SQLite index from vault markdown files |
| `review` | Weekly review — surfaces stale seeds, stalled ideas, dormant check-ins |
| `metrics [--period N] [--all]` | Flow tracking: creation rates, funnel, velocity, trends |
| `suggest-links [--id ID]` | Cross-domain connection finder based on shared tags |
| `books:list` | All books by author |
| `books:author <name>` | Books by a specific author |
| `books:series <name>` | Books in a series |
| `books:rating <tier>` | Books with a specific rating |
| `books:recent [n]` | Last n reads (default: 10) |
| `books:stats` | Rating breakdowns and re-read stats |

### Write Commands

| Command | Description |
|---|---|
| `db:upsert-doc` | Insert/update a document — `--id --title --domain --subdomain --file-path --created --modified [--status] [--priority] [--confidence] [--effort] [--summary] [--revisit-date] [--close-reason]` |
| `db:set-tags` | Replace all tags — `--id --tags "tag1,tag2,tag3"` |
| `db:add-link` | Add a link — `--source --target --type` |
| `db:upsert-book` | Insert/update book metadata — `--id --author [--series-id] [--series-order] [--rating] [--cover-url]` |
| `db:add-read` | Log a read date — `--id --date` |
| `db:update-status` | Update status + modified_at — `--id --status [--modified]` |
| `db:add-source` | Add external source — `--id [--url] [--title] [--accessed]` |
| `db:add-todo` | Add a TODO — `--content [--doc-id] [--due] [--priority] [--status]` |
| `db:add-external-ref` | Add external reference — `--id --url [--label]` |
| `db:close-doc` | Close with reason — `--id --status (closed/migrated/built) --reason` |

### Usage Rules

1. **Session start**: Run `_scripts/vault-cli/bin/vault briefing` for the daily briefing
2. **All database writes**: Use `vault` CLI commands — never write inline SQL for inserts/updates
3. **Common queries**: Prefer the CLI over inline SQL for any query it covers
4. **Ad-hoc queries**: Use inline SQL only for queries the CLI doesn't support
5. **Extending**: Add new Symfony Console commands rather than writing inline SQL
6. **Rebuild**: Run `vault rebuild` if the DB gets out of sync with files

---

## Knowledge Graph & Linking

The vault is a **knowledge graph**. Documents are nodes; links are typed edges.

### Link Types

| Type | Meaning | Example |
|---|---|---|
| `parent` | This idea is part of a larger idea | Feature → Project |
| `children` | Sub-ideas belonging to this idea | Project → Features |
| `related` | Thematically connected, no hierarchy | Idea A ↔ Idea B |
| `inspired_by` | This idea originated from that one | New → Source |
| `blocks` | This idea prevents progress on another | Blocker → Blocked |
| `blocked_by` | Another idea prevents this one's progress | Blocked → Blocker |
| `evolved_into` | This idea became something else | Old → New form |

### Linking Rules

1. **All links are bidirectional in the database** — if A links to B as `related`,
   B should also link to A as `related`
2. **Parent/children are automatically mirrored** — adding a `parent` to a child
   also adds the child to the parent's `children` list
3. **Inline wiki-links** in body text use the format `[[document-id]]`
   e.g., `[[20260314-103000-ai-agents]]`
4. **Claude maintains link integrity** — when a document's status changes or links
   are modified, update all referencing documents
5. **Orphan detection** — documents with zero links are flagged in daily briefings
   after they are older than 7 days

---

## Git Versioning

Everything in this vault is version-controlled. Git is the safety net — nothing gets lost.

### Commit Rules

1. **Auto-commit after every meaningful change:**
   - Document created
   - Document edited (content or header)
   - Document status changed
   - Registry updated
   - Database rebuilt
   - TODO added/completed

2. **Commit message format:**
   ```
   [{domain}/{subdomain}] {action}: {summary}
   ```
   Examples:
   ```
   [tech/research] create: Vector database comparison for RAG systems
   [personal/journal] update: Added evening reflection
   [business/decisions] close: Decided on Stripe for payments
   [system/registry] add: New subdomain 'gamedev' under 'creative'
   [system/index] rebuild: Full database regeneration
   [meta/todos] complete: Finished vector DB research
   ```

3. **Commit frequency:**
   - After every document creation or significant edit
   - Minimum: daily (even if only updating the database)
   - Under heavy use: at least hourly batches
   - NEVER let uncommitted changes sit across sessions

4. **Push to remote:**
   - **ALWAYS push immediately after every commit** — no exceptions
   - If remote is unavailable, commit locally and push on next successful connection
   - Never wait for user to ask "did you push?" — just push

5. **No branching** — `main` is the single source of truth

6. **Tags for milestones:**
   - Monthly: `monthly-YYYY-MM` (auto-created on first commit of a new month)
   - Yearly: `yearly-YYYY` (auto-created on first commit of a new year)
   - Manual: user can request tags for any meaningful milestone

### Git Safety

- NEVER force push
- NEVER rewrite history
- NEVER delete commits
- Commit messages are the audit trail — make them descriptive

---

## TODO & Reminder System

TODOs exist in two forms:

### 1. Document-Attached TODOs

Embedded in the document's YAML header under `todos:`. These are tasks related to
a specific idea.

### 2. Standalone TODOs

For tasks not tied to a specific idea. Stored in `vault/meta/todos/` with the
standard header format, using domain `meta` and subdomain `todos`.

### TODO Behaviors

| Trigger | Claude's Action |
|---|---|
| "Remind me to X for idea Y" | Add to idea Y's `todos:` list with due date, update DB |
| "Add a todo: X" (no idea) | Create standalone TODO in `vault/meta/todos/` |
| "What's on my plate?" | Query DB: open TODOs grouped by priority and due date |
| "I did X" / "Done with X" | Mark TODO done in both file and DB, auto-commit |
| "What's overdue?" | Query `todos WHERE due_date < today AND status = 'open'` |
| Due date arrives | Surface in daily briefing |

---

## Session Behavior

### Daily Briefing

On **every new conversation** in this directory, run `_scripts/vault-cli/bin/vault briefing`
and present the **unmodified** output directly — do not summarize, reformat,
or condense it. Show it exactly as the CLI prints it:

```
════════════════════════════════════════════════════
  VAULT BRIEFING — {date}
════════════════════════════════════════════════════

  OVERDUE ({count})
  ─────────────────
  • [{priority}] {todo} (due: {date}) → {linked idea or "standalone"}
  ...

  DUE TODAY ({count})
  ─────────────────
  • [{priority}] {todo} → {linked idea}
  ...

  REVISIT TODAY ({count})
  ─────────────────
  • "{title}" (status: {status}, domain: {domain})
  ...

  RECENT ACTIVITY (last 48h)
  ─────────────────
  • {count} ideas created
  • {count} ideas updated
  • {count} ideas closed/migrated/built
  • {count} TODOs completed

  VAULT STATS
  ─────────────────
  • Total: {n} | Active: {n} | Seeds: {n} | Dormant: {n} | Closed: {n}
  • Orphaned ideas (>7 days, no links): {count}

════════════════════════════════════════════════════
```

**If the database does not exist yet**, run `_scripts/vault-cli/bin/vault rebuild`
to initialize it from the vault files.

**If the CLI is not available**, fall back to inline SQL queries matching the
briefing format above.

**After the briefing**, wait for user input. Do not take any action unprompted.

### Weekly Review Ritual

Run `_scripts/vault-cli/bin/vault review` to surface ideas needing attention.
The review uses rule-based staleness detection — no manual revisit dates needed
for routine triage:

| Status | Trigger | Action |
|---|---|---|
| `seed` | Not modified in 14 days | Triage: grow, merge, or close |
| `growing` | Not modified in 21 days | Check: still active? |
| `dormant` | Not modified in 90 days | Check: reactivate or close? |
| `tbr` | Not modified in 60 days | Check: still want to read? |

Manual `revisit_date` is a **manual override** for scheduling specific revisits
on specific dates. For most ideas, the rule-based `vault review` handles revisits
automatically. Use `revisit_date` only when you want to revisit a specific idea
on a specific date regardless of its status.

After generating the review report, work through each item conversationally
with the user — grow, merge, close, or defer each idea.

---

## Interaction Patterns

### Capturing Ideas

**Triggers:** "I have an idea about X" / "Thought: X" / "Quick idea: X" / any
unprompted idea the user shares

**Claude:**
1. Determine the appropriate domain and subdomain (ask if ambiguous)
2. Generate the filename with current timestamp
3. Create the directory path if it doesn't exist
4. Create the file with full header + appropriate body template
5. Populate known fields, defaults for the rest
6. Ask: *"Want to flesh this out more, or capture it as a seed?"*
7. Update SQLite database
8. Auto-commit

### Capturing Books

**Triggers:** Any mention of reading, finishing, or wanting to read a book

**Claude:**
1. Search Goodreads for the book (`{title} {author} site:goodreads.com`)
2. Fetch the Goodreads page to extract:
   - Synopsis/description
   - Cover image URL
3. Create the book document with:
   - Full header including book-specific fields (author, rating, series, etc.)
   - Cover image embedded as `![Cover](url)` before the Synopsis heading
   - Synopsis from Goodreads in the `## Synopsis` section
   - `cover_url` in the YAML header
4. If part of a series:
   - Check if a series document already exists (search by title/author)
   - If not, create the series document and link them
   - If yes, add as a child and update the series reading order
5. Set status: `tbr` (want to read), `growing` (currently reading), or `mature` (finished)
6. Update SQLite database (documents, tags, links, books, reads tables)
7. Auto-commit and push

**Cover image rule:** If `cover_url` is present in any book document, the cover
MUST be embedded in the body as `![Cover](cover_url)` before the first heading.

### Searching

**Triggers:** "What do I have on X?" / "Search for X" / "Find ideas about X"

**Claude:**
1. Query `documents_fts` for full-text matches
2. Also check `tags` table for tag matches
3. Present results with: title, status, domain/subdomain, summary, link count
4. Offer to open/expand any result

### Developing Ideas

**Triggers:** "Develop idea X" / "Let's work on X" / "Expand X"

**Claude:**
1. Read the target document
2. Read all linked documents (parent, children, related)
3. Help the user expand the idea through dialogue
4. Update the document with new content
5. Update `modified` timestamp
6. Suggest new links if connections emerge
7. Update status if appropriate (e.g., `seed` → `growing`)
8. Auto-commit

### Reviewing History

**Triggers:** "How has X evolved?" / "Show history of X"

**Claude:**
1. Run `git log --follow` on the document file
2. For each significant commit, show the diff summary
3. Present as a timeline of changes

### Linking

**Triggers:** "Link X to Y" / "X is related to Y" / "X is a child of Y"

**Claude:**
1. Determine link type (ask if ambiguous)
2. Update BOTH documents' `links:` sections (bidirectional)
3. Update the `links` table in SQLite
4. Auto-commit

### Closing Ideas

**Triggers:** "Close X" / "X is done" / "Mark X as built"

**Claude:**
1. Confirm the end-state: `closed`, `migrated`, or `built`
2. Ask for `close_reason` — "Why is this closing? One line for the header."
3. If `migrated`: ask for the destination and set `evolved_into`
4. If `built`: ask for `external_refs` — where does the real thing live?
5. Optionally add `## Post-Mortem` body section for longer reflection
6. Update `status` and `close_reason` in header
7. Update `modified` timestamp
8. Update SQLite database (use `db:close-doc` for status+reason, `db:add-external-ref` for refs)
9. Auto-commit with closing summary

### Maintenance

**Triggers:** "Rebuild index" / "Check integrity" / "Audit the vault"

**Claude:**
1. For quick checks: run `_scripts/vault-cli/bin/vault integrity`
2. For full rebuild: run `_scripts/vault-cli/bin/vault rebuild`
3. Report: files processed, errors found, orphans detected, broken links
4. Auto-commit the rebuilt database

---

## Proactive Health Checks

Claude should mention during the daily briefing if any of these are detected:

- Orphaned ideas older than 7 days with zero links
- Database row count doesn't match file count in vault
- Documents referencing non-existent IDs in their links
- Documents using domains/subdomains not in the registry
- Uncommitted changes from a previous session
- Database file missing or corrupted

---

## Important Constraints

1. **This vault contains NO code.** It is a knowledge management system operated
   through conversation.
2. **Markdown files are the source of truth.** The SQLite database is derived and
   can always be rebuilt from the files.
3. **Every change gets committed.** No exceptions. If Claude made a change, it
   gets versioned.
4. **The registry is law.** No documents created with unregistered domains/subdomains.
5. **Headers are mandatory.** Every document has a complete structured header.
   No shortcuts.
6. **Links are bidirectional.** If A references B, B must reference A.
7. **TODOs are dual-tracked.** In the document's header AND the SQLite `todos` table.
   Keep them in sync.
8. **Never delete documents.** Change status to `closed` instead. Git history
   preserves everything, but active deletion breaks links and loses context.
9. **Ask before assuming.** When domain, subdomain, or links are ambiguous, ask
   the user. Don't guess.
10. **Directories are created on-demand.** Never pre-create empty folder structures.
11. **No compound shell commands.** Never chain commands with `&&`, `||`, or `;`.
    Run each command as a separate Bash call. This keeps each step independently
    approvable and its output clearly visible.
