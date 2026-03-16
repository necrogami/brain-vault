---
name: monthly-digest
description: Generate a narrative monthly digest — what you thought about, created, closed, and read this month.
user-invocable: true
---

# Monthly Digest

Generate a conversational summary of vault activity for the current month.

## 1. Gather data

Query the database for the current month. Use sqlite3 for queries the CLI doesn't cover:

**Ideas created this month:**
```bash
sqlite3 _index/vault.db "SELECT id, title, domain, subdomain, status FROM documents WHERE created_at >= date('now', 'start of month') ORDER BY created_at"
```

**Ideas closed/built/migrated this month:**
```bash
sqlite3 _index/vault.db "SELECT id, title, status, close_reason FROM documents WHERE status IN ('closed', 'migrated', 'built') AND modified_at >= date('now', 'start of month')"
```

**Status changes (ideas that progressed):**
```bash
sqlite3 _index/vault.db "SELECT id, title, status, domain FROM documents WHERE modified_at >= date('now', 'start of month') AND modified_at != created_at AND status IN ('growing', 'mature')"
```

**Books read this month:**
```bash
sqlite3 _index/vault.db "SELECT d.title, b.author, b.rating FROM reads r JOIN books b ON r.doc_id = b.doc_id JOIN documents d ON b.doc_id = d.id WHERE r.date_read >= date('now', 'start of month') ORDER BY r.date_read"
```

**Top tags this month:**
```bash
sqlite3 _index/vault.db "SELECT t.tag, COUNT(*) as c FROM tags t JOIN documents d ON t.doc_id = d.id WHERE d.created_at >= date('now', 'start of month') GROUP BY t.tag ORDER BY c DESC LIMIT 10"
```

**Domain breakdown:**
```bash
sqlite3 _index/vault.db "SELECT domain, COUNT(*) as c FROM documents WHERE created_at >= date('now', 'start of month') GROUP BY domain ORDER BY c DESC"
```

## 2. Generate narrative

Write a conversational summary. Example tone:

> "This month you focused heavily on **business** with N new domain brainstorms. You captured X ideas total across Y domains. On the reading front, you finished Z books — notably {title} by {author} (rated S). You closed/built A ideas, including {notable one}. Your top tags were {tag1}, {tag2}, {tag3} — showing a strong interest in {theme}."

Include:
- **Opening**: overall volume and dominant domain
- **Notable highlights**: S-tier books, ideas that reached `built`, new domains explored
- **Reading**: books read with ratings
- **Closures**: what was closed and why (if close_reason exists)
- **Trends**: what tags/topics are emerging
- **Observation**: any patterns worth noting (e.g., "lots of seeds but few growing — might be time for a review")

## 3. Present

Output as formatted text in the conversation. This is NOT saved as a document — it's a live summary for the user to read.

## Rules
- Read-only — no changes to the vault
- Use actual data, don't fabricate numbers
- Keep it conversational, not robotic
