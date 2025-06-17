CREATE TABLE IF NOT EXISTS files (
    id CHAR(32) NOT NULL PRIMARY KEY,
    mime TEXT NOT NULL,
    extension TEXT NOT NULL,
    size BIGINT NOT NULL,
    title TEXT,
    password TEXT,
    uploaded_at TIMESTAMP NOT NULL DEFAULT UTC_TIMESTAMP,
    expires_at TIMESTAMP,
    views BIGINT NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS file_metadata (
    id CHAR(32) NOT NULL PRIMARY KEY REFERENCES files(id) ON DELETE CASCADE,
    width BIGINT,
    height BIGINT,
    duration BIGINT,
    line_count BIGINT
);