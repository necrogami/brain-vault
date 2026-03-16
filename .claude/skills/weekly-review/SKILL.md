---
name: weekly-review
description: Run the weekly vault review — surfaces stale seeds, stalled ideas, dormant items, and TBR books for conversational triage.
user-invocable: true
---

# Weekly Vault Review

## 1. Run the review report

```bash
_scripts/vault-cli/bin/vault review
```

Present the output to the user. The review surfaces:
- Stale seeds (>14 days)
- Stalled growing ideas (>21 days)
- Dormant check-ins (>90 days)
- TBR aging (>60 days)
- Manual revisits due

## 2. Triage each section

Work through each section with items. For each item, ask what to do:

### grow
- Update status to `growing`, update `modified` to now
- Run: `_scripts/vault-cli/bin/vault db:update-status --id "{id}" --status "growing"`

### merge
- Ask: "What idea should this merge into?"
- Search: `_scripts/vault-cli/bin/vault search "{query}"`
- Add `evolved_into` link from source to target
- Add `related` link from target to source
- Close source: `_scripts/vault-cli/bin/vault db:close-doc --id "{id}" --status "migrated" --reason "Merged into {target}"`

### close
- Ask: "Why are we closing this?"
- Run: `_scripts/vault-cli/bin/vault db:close-doc --id "{id}" --status "closed" --reason "{reason}"`

### defer
- Skip — no changes. User can say "defer all" to skip remaining in a section.

## 3. Commit changes

After each individual change:
```bash
git add {changed_file} _index/vault.db
```
```bash
git commit -m "[{domain}/{subdomain}] {action}: {summary}"
```
```bash
git push
```

## 4. Weekly snapshot

After triage is complete:
```bash
_scripts/vault-cli/bin/vault metrics --period 7
```

Present the output as the weekly snapshot.

## 5. Summary

Report: N items triaged, N closed, N grown, N merged, N deferred.

## Rules
- Follow all CLAUDE.md rules
- No compound bash commands
- Always update both YAML header and database
- Wait for user decision before applying any change
