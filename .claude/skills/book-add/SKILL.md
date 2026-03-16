---
name: book-add
description: Add a book to the vault — searches Goodreads for synopsis and cover, handles series linking, and indexes. Use when user mentions reading, finishing, or wanting to read a book.
user-invocable: true
argument-hint: "<title>" [--author "name"] [--rating S/A/B/C/D/E/F/DNF] [--status tbr/growing/mature] [--series "series name"]
---

# Add a Book to the Vault

## 1. Parse arguments

Extract from $ARGUMENTS:
- **title** (required) — the book title
- **--author** (optional) — author name. If not provided, ask.
- **--rating** (optional) — S/A/B/C/D/E/F/DNF
- **--status** (optional) — tbr, growing, or mature
- **--series** (optional) — series name this book belongs to

## 2. Search Goodreads

Use WebSearch: `"{title}" "{author}" site:goodreads.com`

Identify the most likely Goodreads book page URL from results.

## 3. Fetch book data from Goodreads

Use WebFetch on the Goodreads URL to extract:
- **Synopsis/description**
- **Cover image URL**
- **Series name and order** (if listed)

## 4. Determine status

1. If `--status` was explicitly provided, use it
2. If `--rating` was provided but no `--status`, set to `mature`
3. Default: `tbr`

## 5. Handle series

If the book is part of a series (from `--series` flag or detected from Goodreads):

**Check for existing series document:**
```bash
_scripts/vault-cli/bin/vault search "{series name}"
```

**If no series doc exists — create one:**
- Filename: `YYYYMMDD-HHMMSS-{series-slug}.md`
- Use `_templates/book-series.md` template
- domain: `personal`, subdomain: `books`, status: `growing`
- Index with `db:upsert-doc` and `db:set-tags`

**Link the book to the series:**
- Book's `links.parent` → series ID
- Series' `links.children` → book ID
- Book's `series` field → series ID
- Set `series_order` from Goodreads data

## 6. Create the book document

**Filename:** `YYYYMMDD-HHMMSS-{title-slug}.md`
**Directory:** `mkdir -p vault/personal/books/{YYYY}/{MM}/`

YAML header includes all standard fields PLUS book-specific:
- `author`, `series`, `series_order`, `rating`, `cover_url`
- `reads`: if status is `mature`, include today's date; otherwise `[]`

**Body:**
- If cover_url exists: `![Cover]({cover_url})` before Synopsis
- `## Synopsis` with Goodreads description
- `## Thoughts` with placeholder

## 7. Index in database

Each as a separate Bash call:

```bash
_scripts/vault-cli/bin/vault db:upsert-doc --id "{id}" --title "{title}" --domain "personal" --subdomain "books" --file-path "{path}" --created "{ts}" --modified "{ts}" --status "{status}" --confidence "high" --summary "{title} by {author}"
```

```bash
_scripts/vault-cli/bin/vault db:set-tags --id "{id}" --tags "{genre-tags}"
```

```bash
_scripts/vault-cli/bin/vault db:upsert-book --id "{id}" --author "{author}" [--series-id "{sid}"] [--series-order {n}] [--rating "{rating}"] [--cover-url "{url}"]
```

If series, add bidirectional links:
```bash
_scripts/vault-cli/bin/vault db:add-link --source "{book_id}" --target "{series_id}" --type "parent"
```
```bash
_scripts/vault-cli/bin/vault db:add-link --source "{series_id}" --target "{book_id}" --type "children"
```

If mature, log the read:
```bash
_scripts/vault-cli/bin/vault db:add-read --id "{id}" --date "{today}"
```

Add Goodreads source:
```bash
_scripts/vault-cli/bin/vault db:add-source --id "{id}" --url "{goodreads_url}" --title "Goodreads" --accessed "{today}"
```

## 8. Commit and push

```bash
git add vault/personal/books/ _index/vault.db
```
```bash
git commit -m "[personal/books] create: {title} by {author}"
```
```bash
git push
```

## 9. Report

Tell the user what was created: title, author, status, rating, series info, file path.

## Rules
- Follow all CLAUDE.md rules
- No compound bash commands
- Cover image MUST be embedded if cover_url is present
- Series links are always bidirectional
