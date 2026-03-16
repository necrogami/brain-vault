---
name: capture
description: Capture an idea into the vault with auto-detected domain/subdomain, full YAML header, indexing, and git commit. Use when the user shares an idea, thought, or concept to save.
user-invocable: true
argument-hint: [idea text]
---

# Capture an Idea

## 1. Parse the idea

Extract the idea text from $ARGUMENTS. If empty, ask: "What's the idea?"

## 2. Auto-detect domain and subdomain

Analyze the idea text for domain signals. Use confidence threshold:

**High confidence (just do it):**
- Mentions code, API, architecture, tools, languages → `tech/fleeting`
- Mentions revenue, pricing, market, customers, startup → `business/fleeting`
- Mentions personal goals, habits, health, feelings → `personal/fleeting`
- Mentions story, world, characters, writing, music → `creative/fleeting`

**Low confidence (ask the user):**
- Ambiguous topics (e.g., "idea about building a game" could be `creative/projects` or `tech/projects`)
- Cross-domain topics
- Present the options: "This could go under {option1} or {option2}. Which fits better?"

For the subdomain, default to `fleeting` unless the idea clearly describes:
- A longer-term initiative → `projects`
- An investigation/comparison → `research`
- A choice with trade-offs → `decisions`

## 3. Generate the document

**Filename:** `YYYYMMDD-HHMMSS-slugified-title.md` using current timestamp. Slug max 60 chars, lowercase, hyphens.

**Create directory:** `mkdir -p vault/{domain}/{subdomain}/{YYYY}/{MM}/`

**Create the file** with full YAML header (every field from CLAUDE.md spec):

```yaml
---
id: "{filename without .md}"
title: "{title derived from idea text}"
domain: {domain}
subdomain: {subdomain}
status: seed
created: {current ISO timestamp}
modified: {current ISO timestamp}
tags: [{auto-detected tags from idea text, lowercase, hyphenated}]
priority: p3-low

links:
  parent: []
  children: []
  related: []
  inspired_by: []
  blocks: []
  blocked_by: []
  evolved_into: []

sources: []

confidence: speculative
effort: null
revisit_date: null
close_reason: null
summary: "{one-line summary}"

external_refs: []

todos: []
---
```

**Body:** Use the appropriate template from `_templates/` based on subdomain. Place the idea text in the main content section.

## 4. Index in the database

Run these commands (each as a separate Bash call):

```bash
_scripts/vault-cli/bin/vault db:upsert-doc --id "{id}" --title "{title}" --domain "{domain}" --subdomain "{subdomain}" --file-path "{relative_path}" --created "{created}" --modified "{modified}" --summary "{summary}"
```

```bash
_scripts/vault-cli/bin/vault db:set-tags --id "{id}" --tags "{comma-separated tags}"
```

## 5. Commit and push

```bash
git add vault/{domain}/{subdomain}/{YYYY}/{MM}/{filename} _index/vault.db
```

```bash
git commit -m "[{domain}/{subdomain}] create: {title}"
```

```bash
git push
```

## 6. Report

Tell the user:
- What was created (title, domain/subdomain, file path)
- Ask: "Want to flesh this out more, or leave it as a seed?"

## Rules
- Follow all CLAUDE.md rules
- No compound bash commands
- Every field in the YAML header must be present
- Tags are lowercase, hyphenated, no duplicates
