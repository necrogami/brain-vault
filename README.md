# Brain Vault

A file-system-native knowledge management system powered by AI. No app, no SaaS, no proprietary format — just markdown files, a SQLite index, and an AI agent that manages it all through conversation.

You think. You talk. The vault remembers.

---

## Why This Exists

Most knowledge management tools ask you to be your own librarian. You organize, you tag, you file, you link. Eventually the overhead kills the habit, and your ideas rot in an inbox you never process.

Brain Vault flips this. You dump ideas into a conversation — half-formed, messy, stream-of-consciousness — and your AI agent does the filing. It picks the right domain, generates structured metadata, links related ideas together, indexes everything into a queryable database, and commits every change to git. You get a knowledge graph that grows with you, without the busywork.

The design is deliberately simple:

- **Markdown files are the source of truth.** Every idea is a file you can read, edit, grep, or move with standard tools. No lock-in, no proprietary database.
- **SQLite is a derived cache.** It makes searching fast, but you can blow it away and rebuild from the files anytime.
- **Git is the safety net.** Every change is committed and pushed. Full audit trail, full history, zero risk of data loss.
- **The AI is the operator, not the platform.** The vault works without AI — it's just files. The AI makes it frictionless.

---

## How It Works

The core loop:

```
You say something → AI files it → SQLite indexes it → Git versions it
```

Every document gets a structured YAML header (status, priority, tags, links, confidence, effort) and a body that follows a template based on its type. The AI maintains bidirectional links between related ideas, forming a knowledge graph. It surfaces overdue tasks, stale ideas, and orphaned thoughts in daily briefings.

The system tracks ideas through a lifecycle:

```
seed → growing → mature → closed / built / migrated
```

Seeds are raw captures. Growing means actively developed. Mature is fully fleshed out. Nothing gets deleted — ideas close with a reason, and git preserves everything.

---

## What's Inside

```
brain/
├── CLAUDE.md              # The AI's operating manual (start here to understand the system)
├── .claude/
│   ├── skills/            # Slash commands (/capture, /metrics, /weekly-review, etc.)
│   └── agents/            # Specialized agents (book-cataloger, research-enricher, etc.)
├── _registry/
│   └── domains.yml        # Canonical list of domains and subdomains
├── _templates/            # Document body templates by type
│   ├── fleeting.md        # Quick thoughts
│   ├── project.md         # Longer-term initiatives
│   ├── research.md        # Investigations and comparisons
│   ├── decision.md        # Choices and trade-offs
│   ├── journal.md         # Daily reflections
│   ├── books.md           # Book tracking with ratings and series
│   ├── book-series.md     # Series overview documents
│   ├── note.md            # General notes
│   └── default.md         # Fallback template
├── _scripts/
│   └── vault-cli/         # PHP CLI tool for database operations
│       └── bin/vault      # The vault command
├── _index/
│   ├── schema.sql         # SQLite schema definition
│   └── vault.db           # The queryable index (derived, rebuildable)
└── vault/                 # Your ideas live here
    ├── tech/              # Technology, software, engineering
    ├── business/          # Business ideas, strategy, ventures
    ├── personal/          # Journal, goals, reflections
    ├── creative/          # Writing, worldbuilding, design, music
    ├── entertainment/     # Books, movies, TV, games, anime
    ├── music/             # Concerts, albums, discoveries
    ├── travel/            # Trips, destinations, itineraries
    ├── food/              # Restaurants, recipes, drinks
    ├── fitness/           # Workouts, sports, martial arts
    ├── education/         # Courses, certifications, languages
    ├── home/              # Improvement, gardening, DIY, pets
    ├── social/            # Events, networking, gifts, family
    ├── hobbies/           # Photography, collections, outdoors
    ├── career/            # Networking, skills, conferences
    └── meta/              # Standalone TODOs, vault reviews
```

Each domain contains subdomains, and documents are stored by date: `vault/{domain}/{subdomain}/{YYYY}/{MM}/`. Directories are created on-demand — no empty folder scaffolding.

---

## Getting Started

### Prerequisites

- **PHP 8.3+** with the `pdo_sqlite` extension
- **SQLite 3**
- **Git**
- **Claude Code** (or another AI coding agent — see [Adapting for Other Agents](#adapting-for-other-agents))

### Setup

1. **Clone the repo:**
   ```bash
   git clone https://github.com/necrogami/brain-vault.git brain
   cd brain
   ```

2. **Install CLI dependencies:**
   ```bash
   cd _scripts/vault-cli
   composer install
   cd ../..
   ```

3. **Initialize the vault:**
   ```bash
   _scripts/vault-cli/bin/vault init
   ```
   This validates your environment (PHP, SQLite, git), creates the database, and rebuilds the index from any existing vault files.

4. **Start a conversation:**
   ```bash
   claude
   ```
   The AI reads `CLAUDE.md`, runs the daily briefing, and waits for you. Start talking.

### First Steps

Try these to get a feel for the system:

- **Capture an idea:** *"I have an idea about using SQLite as an application file format"*
- **Quick thought:** *"Quick thought: what if CI pipelines had a sleep mode?"*
- **Add a book:** *"I just finished reading Project Hail Mary"*
- **Check your vault:** *"What do I have on AI?"*
- **See your briefing:** Just start a new conversation — it runs automatically.

---

## Usage

### Capturing Ideas

Say what's on your mind. The AI determines the domain, generates metadata, creates the file, indexes it, and commits.

```
You: I think there's a business opportunity in offering SQLite consulting
     for teams migrating off heavy ORMs

AI:  Creates vault/business/fleeting/2026/03/20260315-143000-sqlite-consulting-orm-migration.md
     with full YAML header, tags, and body scaffold. Commits and pushes.
     "Want to flesh this out more, or capture it as a seed?"
```

Every idea gets a status (`seed` by default), priority, confidence level, and effort estimate. You can always come back and develop it later.

### Searching and Querying

```
You: What do I have on databases?
You: Show me all seed ideas in tech
You: What's connected to the SQLite consulting idea?
You: What's overdue?
You: Tag cloud
```

The AI queries the SQLite index and presents results with titles, statuses, summaries, and link counts.

### Linking Ideas

Ideas form a knowledge graph through typed links:

| Link Type | Meaning |
|---|---|
| `parent` / `children` | Hierarchical (project has features) |
| `related` | Thematic connection |
| `inspired_by` | Origin tracking |
| `blocks` / `blocked_by` | Dependency tracking |
| `evolved_into` | Idea metamorphosis |

```
You: Link the SQLite consulting idea to my database research
You: The consulting idea is a child of my side-projects list
```

Links are always bidirectional — the AI updates both documents.

### Media Tracking

The vault has first-class tracking for **books**, **movies**, **TV shows**, and **games** — each with dedicated database tables, CLI commands, and an S/A/B/C/D/E/F tier rating system.

**Books:**
```
You: I just finished Iron Prince by Bryce O'Connor
You: Started reading Dune
```
The AI searches Goodreads for synopses and covers, handles series linking, and tracks reading history with re-read support.

**Movies:**
```
You: I watched Blade Runner 2049
You: Rate it S-tier
```
Tracks director, year, genre, runtime, poster, and watch history.

**TV Shows:**
```
You: I'm watching Severance, 2 seasons in
You: Finished Breaking Bad, S-tier
```
Tracks creator, seasons watched vs total, and show status (watching/completed/dropped).

**Games:**
```
You: I'm playing Elden Ring on PC, about 60 hours in
You: Finished Hades, A-tier
```
Tracks developer, platform, hours played, play sessions, and backlog.

### Daily Briefing

Every new conversation starts with a briefing:

```
════════════════════════════════════════════════════
  VAULT BRIEFING — 2026-03-15
════════════════════════════════════════════════════

  OVERDUE (2)
  ─────────────────
  • [p2-medium] Research competing approaches (due: 2026-03-10) → "SQLite Consulting"
  ...

  RECENT ACTIVITY (last 48h)
  ─────────────────
  • 3 ideas created
  • 1 ideas updated
  • 0 TODOs completed

  VAULT STATS
  ─────────────────
  • Total: 42 | Active: 28 | Seeds: 12 | Dormant: 3 | Closed: 8
  • Orphaned ideas (>7 days, no links): 2
════════════════════════════════════════════════════
```

### Weekly Review

Run `/weekly-review` to surface ideas that need attention. The system uses rule-based staleness detection:

- Seeds untouched for 14 days — triage: grow, merge, or close
- Growing ideas untouched for 21 days — still active?
- Dormant ideas untouched for 90 days — reactivate or close?

The review is conversational — you work through each item with the AI.

### Skills and Agents

The vault comes with slash commands (skills) and specialized agents:

**Skills:**
| Command | What It Does |
|---|---|
| `/capture` | Quick-capture an idea with auto-detected domain |
| `/metrics` | Vault flow metrics with analytical commentary |
| `/weekly-review` | Surface stale ideas for triage |
| `/suggest-links` | Find cross-domain connections |
| `/book-add` | Add a book with Goodreads lookup |
| `/export` | Export a document as clean markdown or styled HTML |
| `/monthly-digest` | Generate a narrative monthly digest |

**Agents:**
| Agent | What It Does |
|---|---|
| `book-cataloger` | Bulk-import a reading list |
| `research-enricher` | Deep-research an idea via web search |
| `vault-reviewer` | Automated weekly review triage |

---

## The CLI

The vault includes a PHP CLI tool at `_scripts/vault-cli/bin/vault` that handles all database operations. The AI uses it automatically, but you can run commands directly too.

### Read Commands

```bash
vault init                  # Validate environment, create DB, rebuild index
vault briefing              # Daily briefing
vault todos                 # Open TODOs by priority and due date
vault search "query"        # Full-text and tag search
vault stats                 # Status, domain, priority breakdowns
vault recent                # Recently created/modified documents
vault orphans               # Documents with no links
vault review                # Weekly review — stale idea detection
vault metrics               # Flow tracking, velocity, trends
vault suggest-links         # Cross-domain connection finder
vault integrity             # Database vs file consistency check
vault rebuild               # Full database rebuild from markdown files
```

### Book Commands

```bash
vault books:list            # All books by author
vault books:author "Name"   # Books by a specific author
vault books:series "Name"   # Books in a series
vault books:rating S        # Books with a specific rating
vault books:recent          # Last 10 reads
vault books:stats           # Rating breakdowns and re-read stats
```

### Movie Commands

```bash
vault movies:list           # All movies by director
vault movies:director "Name" # Movies by a specific director
vault movies:rating S       # Movies with a specific rating
vault movies:recent         # Last 10 watches
vault movies:stats          # Rating breakdowns and re-watch stats
```

### TV Commands

```bash
vault tv:list               # All TV shows by creator
vault tv:watching           # Currently watching shows
vault tv:rating S           # TV shows with a specific rating
vault tv:stats              # Rating breakdowns, watching/completed/dropped
```

### Game Commands

```bash
vault games:list            # All games by developer
vault games:playing         # Currently playing
vault games:backlog         # Games in the backlog
vault games:platform "PC"   # Games by platform
vault games:rating S        # Games with a specific rating
vault games:stats           # Rating breakdowns, platform stats, total hours
```

### Write Commands

The CLI also has `db:*` write commands (`db:upsert-doc`, `db:set-tags`, `db:add-link`, etc.) used by the AI to keep the database in sync. You generally won't need these directly — the AI handles it.

---

## Customization

### Domains and Subdomains

Edit `_registry/domains.yml` to add your own knowledge domains. The default set covers tech, business, personal, creative, and meta — but the structure is yours to shape.

```yaml
domains:
  cooking:
    description: "Recipes, techniques, meal planning"
    subdomains:
      - name: recipes
        description: "Individual recipes and variations"
      - name: techniques
        description: "Cooking methods and skills"
```

The AI will respect the registry and ask before creating documents in unregistered domains.

### Templates

Add or modify templates in `_templates/` to change how new documents are scaffolded. Templates are starting points, not constraints — the body of any document can be freely edited.

### The Operating Manual

`CLAUDE.md` is the AI's complete operating manual. It defines every behavior: how documents are structured, how the lifecycle works, how links are maintained, when to commit, what to surface in briefings. If you want to change how the AI manages your vault, this is the file to edit.

---

## Adapting for Other Agents

Brain Vault is currently built around Claude Code, but the architecture is agent-agnostic. The core system is just files, a SQLite database, and a CLI tool — any AI agent that can read files and run shell commands can operate it.

The key integration point is the operating manual (`CLAUDE.md`). To support another agent:

1. Create an equivalent instruction file for that agent (e.g., `GEMINI.md`, `AGENTS.md`, `CURSOR.md`)
2. Adapt the instructions to the agent's capabilities and tool-calling conventions
3. The vault structure, CLI, templates, and schema stay the same

This is on the roadmap. Contributions welcome.

---

## Design Principles

- **Files over databases.** Markdown is the source of truth. The database is a convenience.
- **Convention over configuration.** File naming, directory structure, and YAML headers follow strict conventions so tooling stays simple.
- **Git everything.** Every change is committed. Every commit is pushed. The vault has full history from day one.
- **Never delete.** Ideas close with a reason. Git preserves the record. Nothing is lost.
- **AI as operator.** The AI handles the tedious parts (filing, linking, indexing, committing) so you can focus on thinking.
- **No lock-in.** Walk away from the AI and you still have a perfectly usable directory of markdown files with structured metadata.

---

## License

This project is licensed under the [GNU Affero General Public License v3.0](LICENSE).

Brain Vault is a personal tool. You're welcome to use it at work to organize your own thinking — that's exactly what it's for. The AGPL ensures that if anyone tries to build a hosted service or commercial product around it, they must open-source their version.
