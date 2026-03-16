---
name: suggest-links
description: Surface cross-domain connections and offer to link them. Use when looking for connections between ideas.
user-invocable: true
---

# Suggest Links

Run the vault's cross-domain connection finder and offer to apply suggestions.

## Steps

1. Run `_scripts/vault-cli/bin/vault suggest-links` and present the full output to the user.

2. If suggestions exist, work through each pair one at a time:
   - Show the two documents, their domains, and shared tags
   - Ask: "Link these as related? (yes/no/skip)"

3. For each "yes":
   - Read both documents
   - Add each other's ID to their `links.related` arrays in the YAML header
   - Run `_scripts/vault-cli/bin/vault db:add-link --source {id1} --target {id2} --type related`
   - Run `_scripts/vault-cli/bin/vault db:add-link --source {id2} --target {id1} --type related`

4. After all suggestions are processed:
   - Git add all changed files
   - Git commit with message `[system/links] update: Applied N suggested cross-domain links`
   - Git push

5. Report: "Linked N pairs, skipped M."

## Rules
- Follow all CLAUDE.md rules
- No compound bash commands
- Links are always bidirectional
