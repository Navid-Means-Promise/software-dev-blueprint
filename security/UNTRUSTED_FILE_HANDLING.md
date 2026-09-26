# Untrusted file handling

Authoritative for accepting, storing, and returning user-supplied files and media. The parent
resource and every representation still follow
[authorization and policy decisions](../architecture/AUTHORIZATION.md); this document owns the byte,
metadata, storage, and retrieval boundary.

## Acceptance boundary

Treat every upload as untrusted bytes, including images, documents, recordings, archives, and files
produced by browser media APIs. Authorize the parent resource before accepting bytes. Apply request
and per-file size limits before expensive parsing, and allow only the media types required by the
bounded use case.

Detect type from content; never trust the supplied filename, extension, or `Content-Type`. Map an
accepted type to a server-owned extension and reject mismatches or ambiguous formats. Type detection
is one layer, not proof that content is safe. Add malware scanning, archive expansion limits,
verified decoding/re-encoding, or content disarm/reconstruction when the accepted format and product
risk require them.

## Storage and metadata

Generate an opaque storage name and keep the original filename as bounded display metadata only.
Store bytes in a non-executable, non-public namespace outside the web root or in private object
storage. The application process receives only the storage permissions its use cases require;
uploaded content never becomes executable application source or server configuration.

Canonicalize every derived local path/key and prove it remains inside the configured storage root.
Client-controlled path segments, traversal components, and symlink escapes are rejected. Object
stores apply the same rule through server-owned bucket/key prefixes and private access policy.

## Authorized retrieval

A retrieval request carries a domain record identifier, not a storage path. The server loads that
record, re-applies the resource policy, resolves its server-owned storage key, and emits an
allowlisted response type with `X-Content-Type-Options: nosniff`. Sanitize `Content-Disposition` and
render inline only the types the product has deliberately approved. Missing bytes return a stable
application error without exposing filesystem, bucket, or provider details.

## Metadata/object consistency

Define consistency between storage and canonical metadata. If bytes are written before the database
record commits, delete them when validation or persistence fails. If remote/object storage or the
chosen flow cannot be atomic, use an idempotent staged/finalized protocol plus bounded orphan
cleanup. Retries must converge without duplicating objects or attaching one object to the wrong
resource.

## Required tests

Tests cover:

- oversize, truncated, empty, and multi-file request bounds;
- claimed/detected type mismatch and malformed accepted formats;
- unsafe display filenames, traversal, and storage-root escape attempts;
- unauthenticated and cross-principal upload/download access;
- inline-versus-download headers and `nosniff` behavior;
- missing, quarantined, and rejected objects;
- persistence failure cleanup and orphan recovery; and
- retry and concurrent-finalization behavior.

## Rules

- Authorize the parent resource before accepting bytes and every record again before retrieval.
- Trust detected and validated content, never client filename, extension, type claim, or path.
- Store bytes privately under opaque server-owned names and expose them only through bounded use
  cases.
- Define and test how metadata and stored objects converge after failure, retry, and concurrency.

## Standards basis

- [OWASP File Upload Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html)
  — allowlisted validation, generated storage names, private storage, and defense in depth
