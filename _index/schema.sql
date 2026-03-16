-- Idea & Memory Vault — SQLite Schema
-- ════════════════════════════════════
-- This schema defines the queryable index over all vault documents.
-- Source of truth is always the markdown files. This DB is a derived cache.
--
-- Rebuild: sqlite3 _index/vault.db < _index/schema.sql
-- Then re-parse all vault/**/*.md frontmatter to populate.

-- Drop existing tables for clean rebuild
DROP TABLE IF EXISTS documents_fts;
DROP TABLE IF EXISTS todos;
DROP TABLE IF EXISTS sources;
DROP TABLE IF EXISTS links;
DROP TABLE IF EXISTS tags;
DROP TABLE IF EXISTS documents;

-- ────────────────────────────────────
-- Core document metadata
-- ────────────────────────────────────
CREATE TABLE documents (
    id            TEXT PRIMARY KEY,        -- "20260314-103000-slug"
    title         TEXT NOT NULL,
    domain        TEXT NOT NULL,
    subdomain     TEXT NOT NULL,
    status        TEXT NOT NULL DEFAULT 'seed',
    priority      TEXT DEFAULT 'p3-low',
    confidence    TEXT DEFAULT 'speculative',
    effort        TEXT DEFAULT 'medium',
    summary       TEXT,
    file_path     TEXT NOT NULL UNIQUE,    -- relative path from vault root
    created_at    DATETIME NOT NULL,
    modified_at   DATETIME NOT NULL,
    revisit_date  DATE,
    close_reason  TEXT
);

-- ────────────────────────────────────
-- Normalized tags (many-to-many)
-- ────────────────────────────────────
CREATE TABLE tags (
    doc_id  TEXT NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
    tag     TEXT NOT NULL,
    PRIMARY KEY (doc_id, tag)
);

-- ────────────────────────────────────
-- Knowledge graph edges
-- ────────────────────────────────────
CREATE TABLE links (
    source_id   TEXT NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
    target_id   TEXT NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
    link_type   TEXT NOT NULL,    -- parent, child, related, inspired_by, blocks, blocked_by, evolved_into
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (source_id, target_id, link_type)
);

-- ────────────────────────────────────
-- External sources / references
-- ────────────────────────────────────
CREATE TABLE sources (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    doc_id        TEXT NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
    url           TEXT,
    title         TEXT,
    accessed_date DATE
);

-- ────────────────────────────────────
-- TODOs and follow-ups
-- ────────────────────────────────────
CREATE TABLE todos (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    doc_id      TEXT REFERENCES documents(id) ON DELETE CASCADE,  -- NULL = standalone
    content     TEXT NOT NULL,
    due_date    DATE,
    status      TEXT NOT NULL DEFAULT 'open',    -- open, done, cancelled
    priority    TEXT DEFAULT 'p3-low',
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ────────────────────────────────────
-- Book-specific metadata
-- ────────────────────────────────────
DROP TABLE IF EXISTS books;
CREATE TABLE books (
    doc_id        TEXT PRIMARY KEY REFERENCES documents(id) ON DELETE CASCADE,
    author        TEXT,
    series_id     TEXT REFERENCES documents(id),  -- link to series parent document
    series_order  REAL,                            -- REAL to allow 1.5 for novellas/side stories
    rating        TEXT,                            -- S/A/B/C/D/E/F/DNF (NULL for technical)
    cover_url     TEXT
);

-- ────────────────────────────────────
-- Book read dates (supports re-reads)
-- ────────────────────────────────────
DROP TABLE IF EXISTS reads;
CREATE TABLE reads (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    doc_id      TEXT NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
    date_read   DATE NOT NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ────────────────────────────────────
-- External references (outputs from ideas)
-- ────────────────────────────────────
DROP TABLE IF EXISTS external_refs;
CREATE TABLE external_refs (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    doc_id      TEXT NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
    url         TEXT NOT NULL,
    label       TEXT,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ────────────────────────────────────
-- Movie-specific metadata
-- ────────────────────────────────────
DROP TABLE IF EXISTS movies;
CREATE TABLE movies (
    doc_id        TEXT PRIMARY KEY REFERENCES documents(id) ON DELETE CASCADE,
    director      TEXT,
    year          INTEGER,                         -- release year
    genre         TEXT,                            -- primary genre
    runtime_min   INTEGER,                         -- runtime in minutes
    rating        TEXT,                            -- S/A/B/C/D/E/F/DNF
    poster_url    TEXT,
    imdb_id       TEXT                             -- for external lookups
);

-- ────────────────────────────────────
-- Movie watch dates (supports re-watches)
-- ────────────────────────────────────
DROP TABLE IF EXISTS watches;
CREATE TABLE watches (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    doc_id        TEXT NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
    date_watched  DATE NOT NULL,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ────────────────────────────────────
-- TV show-specific metadata
-- ────────────────────────────────────
DROP TABLE IF EXISTS tv_shows;
CREATE TABLE tv_shows (
    doc_id          TEXT PRIMARY KEY REFERENCES documents(id) ON DELETE CASCADE,
    creator         TEXT,                          -- showrunner/creator
    year_start      INTEGER,                       -- first aired
    year_end        INTEGER,                       -- NULL if ongoing
    genre           TEXT,
    total_seasons   INTEGER,
    seasons_watched INTEGER,                       -- progress tracking
    rating          TEXT,                          -- S/A/B/C/D/E/F/DNF
    poster_url      TEXT,
    imdb_id         TEXT
);

-- ────────────────────────────────────
-- Game-specific metadata
-- ────────────────────────────────────
DROP TABLE IF EXISTS games;
CREATE TABLE games (
    doc_id        TEXT PRIMARY KEY REFERENCES documents(id) ON DELETE CASCADE,
    developer     TEXT,
    publisher     TEXT,
    year          INTEGER,                         -- release year
    genre         TEXT,
    platform      TEXT,                            -- what you played it on
    hours_played  REAL,
    rating        TEXT,                            -- S/A/B/C/D/E/F/DNF
    cover_url     TEXT
);

-- ────────────────────────────────────
-- Game play sessions (supports tracking over time)
-- ────────────────────────────────────
DROP TABLE IF EXISTS play_sessions;
CREATE TABLE play_sessions (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    doc_id        TEXT NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
    date_played   DATE NOT NULL,
    hours         REAL,                            -- optional per-session hours
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_books_author    ON books(author);
CREATE INDEX idx_books_series    ON books(series_id);
CREATE INDEX idx_books_rating    ON books(rating);
CREATE INDEX idx_reads_doc       ON reads(doc_id);
CREATE INDEX idx_reads_date      ON reads(date_read);
CREATE INDEX idx_movies_director ON movies(director);
CREATE INDEX idx_movies_year     ON movies(year);
CREATE INDEX idx_movies_rating   ON movies(rating);
CREATE INDEX idx_watches_doc     ON watches(doc_id);
CREATE INDEX idx_watches_date    ON watches(date_watched);
CREATE INDEX idx_tv_creator      ON tv_shows(creator);
CREATE INDEX idx_tv_rating       ON tv_shows(rating);
CREATE INDEX idx_games_developer ON games(developer);
CREATE INDEX idx_games_platform  ON games(platform);
CREATE INDEX idx_games_rating    ON games(rating);
CREATE INDEX idx_play_sessions_doc  ON play_sessions(doc_id);
CREATE INDEX idx_play_sessions_date ON play_sessions(date_played);

-- ────────────────────────────────────
-- Full-text search index
-- ────────────────────────────────────
CREATE VIRTUAL TABLE documents_fts USING fts5(
    id,
    title,
    summary,
    domain,
    subdomain,
    content='documents',
    content_rowid='rowid'
);

-- Triggers to keep FTS in sync
CREATE TRIGGER documents_ai AFTER INSERT ON documents BEGIN
    INSERT INTO documents_fts(rowid, id, title, summary, domain, subdomain)
    VALUES (new.rowid, new.id, new.title, new.summary, new.domain, new.subdomain);
END;

CREATE TRIGGER documents_ad AFTER DELETE ON documents BEGIN
    INSERT INTO documents_fts(documents_fts, rowid, id, title, summary, domain, subdomain)
    VALUES ('delete', old.rowid, old.id, old.title, old.summary, old.domain, old.subdomain);
END;

CREATE TRIGGER documents_au AFTER UPDATE ON documents BEGIN
    INSERT INTO documents_fts(documents_fts, rowid, id, title, summary, domain, subdomain)
    VALUES ('delete', old.rowid, old.id, old.title, old.summary, old.domain, old.subdomain);
    INSERT INTO documents_fts(rowid, id, title, summary, domain, subdomain)
    VALUES (new.rowid, new.id, new.title, new.summary, new.domain, new.subdomain);
END;

-- ────────────────────────────────────
-- Performance indexes
-- ────────────────────────────────────
CREATE INDEX idx_documents_status     ON documents(status);
CREATE INDEX idx_documents_domain     ON documents(domain);
CREATE INDEX idx_documents_subdomain  ON documents(domain, subdomain);
CREATE INDEX idx_documents_revisit    ON documents(revisit_date);
CREATE INDEX idx_documents_created    ON documents(created_at);
CREATE INDEX idx_documents_modified   ON documents(modified_at);
CREATE INDEX idx_tags_tag             ON tags(tag);
CREATE INDEX idx_links_source        ON links(source_id);
CREATE INDEX idx_links_target        ON links(target_id);
CREATE INDEX idx_links_type          ON links(link_type);
CREATE INDEX idx_todos_status        ON todos(status, due_date);
CREATE INDEX idx_todos_doc           ON todos(doc_id);
CREATE INDEX idx_sources_doc         ON sources(doc_id);
CREATE INDEX idx_external_refs_doc   ON external_refs(doc_id);
