CREATE TABLE factdb_scopes (
    tenant_id TEXT NOT NULL,
    scope_id TEXT NOT NULL,
    revision INTEGER NOT NULL CHECK (revision >= 0),
    erased INTEGER NOT NULL DEFAULT 0 CHECK (erased IN (0, 1)),
    PRIMARY KEY (tenant_id, scope_id)
);
CREATE TABLE factdb_sources (
    tenant_id TEXT NOT NULL,
    scope_id TEXT NOT NULL,
    source_id TEXT NOT NULL,
    version INTEGER NOT NULL CHECK (version >= 1),
    record_json TEXT NOT NULL,
    record_hash TEXT NOT NULL,
    PRIMARY KEY (tenant_id, scope_id, source_id, version),
    FOREIGN KEY (tenant_id, scope_id) REFERENCES factdb_scopes ON DELETE CASCADE
);
CREATE TABLE factdb_heads (
    tenant_id TEXT NOT NULL,
    scope_id TEXT NOT NULL,
    source_id TEXT NOT NULL,
    version INTEGER,
    position INTEGER NOT NULL,
    PRIMARY KEY (tenant_id, scope_id, source_id),
    UNIQUE (tenant_id, scope_id, position),
    FOREIGN KEY (tenant_id, scope_id) REFERENCES factdb_scopes ON DELETE CASCADE,
    FOREIGN KEY (tenant_id, scope_id, source_id, version) REFERENCES factdb_sources
);
CREATE TABLE factdb_propositions (
    tenant_id TEXT NOT NULL,
    scope_id TEXT NOT NULL,
    id TEXT NOT NULL,
    statement_hash TEXT NOT NULL,
    record_json TEXT NOT NULL,
    record_hash TEXT NOT NULL,
    PRIMARY KEY (tenant_id, scope_id, id),
    UNIQUE (tenant_id, scope_id, statement_hash),
    FOREIGN KEY (tenant_id, scope_id) REFERENCES factdb_scopes ON DELETE CASCADE
);
CREATE TABLE factdb_claims (
    tenant_id TEXT NOT NULL,
    scope_id TEXT NOT NULL,
    id TEXT NOT NULL,
    proposition_id TEXT NOT NULL,
    source_id TEXT NOT NULL,
    source_version INTEGER NOT NULL,
    record_json TEXT NOT NULL,
    record_hash TEXT NOT NULL,
    PRIMARY KEY (tenant_id, scope_id, id),
    FOREIGN KEY (tenant_id, scope_id) REFERENCES factdb_scopes ON DELETE CASCADE,
    FOREIGN KEY (tenant_id, scope_id, proposition_id) REFERENCES factdb_propositions,
    FOREIGN KEY (tenant_id, scope_id, source_id, source_version) REFERENCES factdb_sources
);
CREATE TABLE factdb_evidence (
    tenant_id TEXT NOT NULL,
    scope_id TEXT NOT NULL,
    claim_id TEXT NOT NULL,
    source_id TEXT NOT NULL,
    source_version INTEGER NOT NULL,
    role TEXT NOT NULL CHECK (role IN ('support', 'context')),
    PRIMARY KEY (tenant_id, scope_id, claim_id, source_id, source_version),
    FOREIGN KEY (tenant_id, scope_id) REFERENCES factdb_scopes ON DELETE CASCADE,
    FOREIGN KEY (tenant_id, scope_id, claim_id) REFERENCES factdb_claims,
    FOREIGN KEY (tenant_id, scope_id, source_id, source_version) REFERENCES factdb_sources
);
CREATE INDEX factdb_evidence_source ON factdb_evidence (tenant_id, scope_id, source_id, source_version);
CREATE TABLE factdb_links (
    tenant_id TEXT NOT NULL,
    scope_id TEXT NOT NULL,
    from_claim TEXT NOT NULL,
    to_claim TEXT NOT NULL,
    kind TEXT NOT NULL CHECK (kind IN ('depends_on', 'supersedes', 'contradicts')),
    CHECK (from_claim <> to_claim),
    PRIMARY KEY (tenant_id, scope_id, from_claim, to_claim, kind),
    FOREIGN KEY (tenant_id, scope_id) REFERENCES factdb_scopes ON DELETE CASCADE,
    FOREIGN KEY (tenant_id, scope_id, from_claim) REFERENCES factdb_claims,
    FOREIGN KEY (tenant_id, scope_id, to_claim) REFERENCES factdb_claims
);
CREATE INDEX factdb_links_target ON factdb_links (tenant_id, scope_id, to_claim);
CREATE TABLE factdb_analyses (
    tenant_id TEXT NOT NULL,
    scope_id TEXT NOT NULL,
    source_id TEXT NOT NULL,
    source_version INTEGER NOT NULL,
    record_json TEXT NOT NULL,
    PRIMARY KEY (tenant_id, scope_id, source_id),
    FOREIGN KEY (tenant_id, scope_id) REFERENCES factdb_scopes ON DELETE CASCADE,
    FOREIGN KEY (tenant_id, scope_id, source_id, source_version) REFERENCES factdb_sources
);
CREATE TABLE factdb_operations (
    tenant_id TEXT NOT NULL,
    scope_id TEXT NOT NULL,
    id TEXT NOT NULL,
    record_json TEXT NOT NULL,
    record_hash TEXT NOT NULL,
    PRIMARY KEY (tenant_id, scope_id, id),
    FOREIGN KEY (tenant_id, scope_id) REFERENCES factdb_scopes ON DELETE CASCADE
);
CREATE TRIGGER factdb_sources_immutable BEFORE UPDATE ON factdb_sources
BEGIN SELECT RAISE(ABORT, 'factdb immutable source version'); END;
CREATE TRIGGER factdb_propositions_immutable BEFORE UPDATE ON factdb_propositions
BEGIN SELECT RAISE(ABORT, 'factdb immutable proposition'); END;
CREATE TRIGGER factdb_claims_immutable BEFORE UPDATE ON factdb_claims
BEGIN SELECT RAISE(ABORT, 'factdb immutable claim'); END;
CREATE TRIGGER factdb_operations_immutable BEFORE UPDATE ON factdb_operations
BEGIN SELECT RAISE(ABORT, 'factdb immutable operation'); END;
