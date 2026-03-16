---
name: metrics
description: Show vault flow metrics with analytical commentary. Use when user asks about stats, progress, or velocity.
user-invocable: true
argument-hint: [--period N] [--all]
---

# Metrics

Run vault metrics and add analytical commentary.

## Steps

1. Parse $ARGUMENTS for `--period N` and `--all` flags. Default: no flags (30-day period).

2. Run `_scripts/vault-cli/bin/vault metrics` with the appropriate flags and present the full output.

3. Add brief analytical commentary based on the data:
   - If seed count is growing faster than ideas being developed: "Lots of capturing but not much development — consider a review session"
   - If one domain dominates: "Your attention is heavily on {domain} this period"
   - If books read count is notable: "Strong reading month — {n} books finished"
   - If no ideas closed/built: "No ideas reached completion this period — any ready to close or ship?"
   - If conversion rate is low: "Seed-to-growing conversion is low — many ideas staying as seeds"

## Rules
- This is a read-only skill — no changes to the vault
- Present the raw metrics output first, then commentary
