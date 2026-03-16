---
name: export
description: Export a vault document as clean markdown or styled HTML, stripped of YAML frontmatter and with wiki-links resolved.
user-invocable: true
argument-hint: <doc-id> [--format md|html]
---

# Export

Export a vault document in a clean, shareable format.

## Steps

1. Parse $ARGUMENTS for doc-id (required) and --format (default: md).

2. Find and read the document. Search by ID in the database if needed:
   ```
   sqlite3 _index/vault.db "SELECT file_path FROM documents WHERE id = '{doc-id}'"
   ```

3. Strip the YAML frontmatter — remove everything between the opening and closing `---` delimiters.

4. Resolve wiki-links — replace any `[[document-id]]` references with the target document's title:
   ```
   sqlite3 _index/vault.db "SELECT title FROM documents WHERE id = '{linked-id}'"
   ```

5. For `--format md` (default):
   - Present the clean markdown in the conversation
   - Offer to write it to a file if the user wants

6. For `--format html`:
   - Generate a self-contained styled HTML page with:
     - Clean typography: system font stack, 1.6 line-height, max-width 720px, centered
     - Document title as `<h1>`
     - Summary as subtitle paragraph
     - Tags as small styled badges/pills
     - If it's a book document: cover image, author name, rating badge
     - Body content rendered from markdown to HTML
     - Minimal, elegant styling — no frameworks, just inline CSS
   - Write the HTML to `/tmp/vault-export-{doc-id}.html`
   - Tell the user the file path

## Rules
- This is a read-only export — never modify the source document
- Always resolve wiki-links to human-readable titles
- HTML output must be fully self-contained (inline CSS, no external dependencies)
