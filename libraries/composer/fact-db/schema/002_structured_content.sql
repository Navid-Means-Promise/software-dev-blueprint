-- Legacy rows keep their exact immutable JSON and hash; new hashes cover all content fields.
ALTER TABLE factdb_propositions RENAME COLUMN statement_hash TO content_hash;
