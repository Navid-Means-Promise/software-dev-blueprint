# Deployment and HTTP caching

Authoritative for how web assets and documents are delivered, cached, versioned, and verified across
deployment environments. The governing idea: **HTML responses revalidate immediately; content-hashed
assets cache immutably; unhashed assets carry deterministic cache-busting tokens; and deployments verify
synchronization before handoff.**

## Maintenance-gated release handoff

A release that can make application code and persistence temporarily incompatible uses an explicit
**maintenance lock** as its fail-closed handoff boundary:

1. Activate the lock before publishing the first incompatible file. The normal application entry
   point checks one local marker before loading application dependencies or opening persistence and
   returns a small `503 Service Unavailable` response with `Cache-Control: no-store` and a bounded
   `Retry-After` value.
2. Publish the release while the lock blocks normal dynamic requests. Static maintenance assets, if
   any, remain independent of the changing application bundle.
3. Apply versioned migrations and deployment-managed records through the deployment control plane,
   then verify the required migration version and integrity constraints.
4. Record the live revision, release the lock, and run exact public healthchecks. If verification
   fails, restore the lock and fail the deployment; do not declare a degraded release successful.

A failed or interrupted release leaves the lock active when compatibility is uncertain. A documented
rerun/recovery operation resumes the deployment; deleting a stale lock manually is not the normal
repair path. Concurrent deployers must not allow one release to clear another release's lock.

## Migration control on hosts without remote command execution

Migrations remain deployment operations, never ordinary page-request work. When a hosting platform
cannot execute a remote CLI command, deployment automation may call a narrow HTTPS **deployment
update hook** after activating maintenance mode. This is a control-plane transport, not application
routing:

- accept `POST` only over HTTPS and authenticate a high-entropy deployment credential with a
  constant-time comparison;
- reject unknown fields, bound the request/response size and execution time, emit `no-store`, and
  return generic failures for unauthenticated callers;
- expose only the fixed update use case—never SQL, filenames, generic commands, or storage CRUD;
- make migration and deployment-managed seed operations restart-safe and idempotent, with explicit
  concurrency behavior;
- keep the hook reachable while the maintenance lock blocks the normal application, and cover the
  credential independently from browser sessions, CSRF tokens, and user authorization;
- keep request-path startup read-only: it verifies the migration version and fails clearly rather
  than invoking the hook or repairing schema.

The deployer invokes the hook automatically, so restricted hosting does not turn a manual migration
step into an easy-to-forget release prerequisite.

## Fresh initialization and destructive reset

Fresh installation may provision deployment-configured principals and other required records after
schema migration. Creating a principal and assigning authority does not authenticate a browser
session; the normal identity-verification flow still gates login. Deployment-managed records use a
stable logical key or UUID. Numeric surrogate IDs remain private relational details: even if a fresh
database intentionally reserves a deterministic row ID, dependent seeds resolve the logical key
rather than hard-coding that number.

Never infer that a database is disposable from an unexpected ID, migration version, environment
name, or record shape. A destructive reset is a separate, explicit operation with all of these
guards:

- an unmistakable command flag and exact confirmation value;
- an independently configured server-side enable switch that defaults off;
- a verified, consistent backup created before removal and retained outside version control;
- maintenance mode for the whole backup/reset/reinitialize interval; and
- deterministic tests proving normal deployment preserves existing records and reset recovery can
  open the backup.

## The HTTP cache-control matrix

Browsers and intermediate caches (CDNs, edge proxies, shared-hosting web servers) follow `Cache-Control`
headers. When servers emit default, unconfigured, or overly aggressive cache headers, users receive
stale client logic or broken stylesheet pairs while developers see correct behavior on un-cached origins.
Every web deployment must configure an explicit three-tier caching policy:

| Response type | `Cache-Control` header | Invalidation mechanism |
| ------------- | ---------------------- | ---------------------- |
| **HTML entry documents** (SSR, PHP templates, SPA `index.html`) | `no-cache, must-revalidate` | Browser revalidates on each navigation; fresh HTML immediately points to new assets. |
| **Content-hashed static assets** (`bundle.[hash].js`, `style.[hash].css`) | `public, max-age=31536000, immutable` | Filename changes whenever content changes; old files stay cached safely. |
| **Unhashed static assets** (`/assets/css/app.css`, `/assets/js/main.js`) | `public, max-age=3600, must-revalidate` | Query-string cache-busting token (`?v=<hash>`) invalidates browser cache on release. |

Never permit a web server (e.g. LiteSpeed, Nginx, Apache, Caddy) to emit multi-day or multi-week
unconditional `max-age` values on unhashed assets without explicit revalidation. Never allow HTML
documents to be cached without a revalidation check.

## Deterministic asset cache-busting

In server-rendered architectures (PHP, Node/Fastify SSR, Go templates) where static assets do not
pass through a content-hashing bundler manifest, static URLs must **never** be typed as bare literal
paths (e.g. `<link rel="stylesheet" href="/assets/css/app.css">`).

Implement a centralized asset URL resolver that appends a deterministic cache-busting token:

1. **Production mode:** Append the active deployment commit hash (read once from a local deployment
   state file such as `.deploy-commit`):
   ```html
   <link rel="stylesheet" href="/assets/css/app.css?v=5d2c8b02">
   <script src="/assets/js/app.js?v=5d2c8b02"></script>
   ```
2. **Local development mode:** When no deployment commit file exists, fall back to the file's
   modification timestamp (`filemtime` in PHP, `fs.statSync().mtimeMs` in Node) so local edits take
   effect immediately on page refresh without manual cache clearing.

This ensures that every release instantly forces all visiting browsers (especially mobile browsers
with aggressive persistent caches) to fetch the updated stylesheets and scripts.

## Deployment state synchronization

Deployment scripts and pipelines (Git-FTP, rsync, container deployments, object-storage uploads) must
maintain a clear record of the live revision:

- Record the deployed commit hash into `.deploy-commit` on the remote host during deployment.
- When merge commits or non-file-diff changes advance `HEAD`, the deployment pipeline must still update
  the recorded commit hash on the remote host even if no tracked source files changed. Failing to sync
  the commit marker prevents the asset resolver from emitting the new cache token.

## Post-deployment verification (smoke healthcheck)

A deployment is incomplete until its live endpoints are verified against the deployed environment.
Automated deployment tools must run a post-deployment healthcheck:

1. **Targeted routes:** Check the primary entry routes (e.g. `/`, primary forms, authentication,
   status/health endpoints) on the live production origin over HTTPS.
2. **Status code assertions:** Assert exact HTTP `200 OK` responses.
3. **Asset synchronization check:** Verify that the returned HTML includes the expected asset version
   token matching the deployed commit, and that the versioned asset URLs resolve with `200 OK`.
4. **Resilient timeout:** Use a bounded, realistic timeout (at least 15 seconds) to accommodate
   potential cold starts or CDN cache warmups without generating false-failure alarms.

## Rules

- HTML entry documents use `Cache-Control: no-cache, must-revalidate`; they are never cached
  unconditionally.
- Unhashed static assets carry a deterministic query-string token (`?v=<hash>`) linked to the
  deployment commit or content hash.
- Web server configuration explicitly declares cache policies for HTML, hashed assets, and unhashed
  assets.
- Deployments record the active revision on the host and run an automated live healthcheck before
  declaring completion.
- Releases with compatibility-sensitive writes use a fail-closed maintenance lock and restore it
  when post-release verification fails.
- Hosts without remote command execution use a narrow authenticated HTTPS deployment update hook;
  normal application requests never migrate or repair schema.
- Normal deployment never infers permission to erase persistence; destructive reset is explicit,
  independently enabled, confirmation-gated, and backup-first.
