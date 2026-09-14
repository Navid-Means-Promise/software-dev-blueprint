# Deployment and HTTP caching

Authoritative for how web assets and documents are delivered, cached, versioned, and verified across
deployment environments. The governing idea: **HTML responses revalidate immediately; content-hashed
assets cache immutably; unhashed assets carry deterministic cache-busting tokens; and deployments verify
synchronization before handoff.**

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
