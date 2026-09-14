# Assets and content

Authoritative for how product content and its media assets are structured, located, and provenanced.
The governing idea: **content is validated data, and asset paths are generated, never typed by hand.**

## Content is data, not code

A product ships **content** — "one validated definition plus the assets it names." The definition is
a data structure validated against a schema (a [contract](../architecture/CONTRACTS.md)); the domain
validates it at construction so a malformed bundle fails early
([shared domain](../architecture/SHARED_DOMAIN.md)). Content is not compiled logic; it is data the
engine walks.

Give the concept **one name** and use it everywhere ([principles](../principles/DEVELOPMENT_PRINCIPLES.md)
§0). In Mystery that name is _story content_; whatever a project calls it, it is never referred to by
a second name, and never conflated with UI assets.

## Paths are generated from one layout definition

The on-disk asset tree, the build configuration that copies those assets, and the content that
references them all describe the same paths. If any of the three types a path as a literal, they can
drift. So **none of them do**: one layout definition is the single source, and every path is derived
from it. Move the layout, and the tree, the build, and the content all follow from the one change.

_Reference implementation:_ Mystery derives every asset path from a `StoryContentPaths` layout
definition, so the disk tree, Vite config, and content bundles cannot disagree.

## Placeholders are generated from the contract

Real media (narration, artwork) is expensive and arrives late. Do not block the loading, timing, and
layout paths on finished media: **generate placeholder assets from the content contract** so the app
exercises the _real_ readiness gate, the _real_ cue timeline, and the _real_ layout long before the
final assets exist. When the finished media lands, it drops into paths that already work.

## Prefer code-native visuals

Where a decorative surface can be produced in code (gradients, a procedural texture, a rendered scene)
instead of a fetched bitmap, prefer the code-native version: it adds no network request, carries no
license, and — if it obeys [UI performance](UI_PERFORMANCE.md) — nothing perpetually animates. A
themed, rich screen with zero image requests is a recurring
[candidate to propose upstream to the blueprint](../reuse/CROSS_PROJECT_SYNC.md).

An image-backed enhancement to a code-native surface retains the deterministic implementation as
its load-failure fallback and applies both through the same material/style owner. A missing optional
texture must not remove the object, change its interaction boundary, or create a second independently
maintained rendering path. The fetched asset improves detail; it does not become structural truth.

### Defensive presentation attributes on vector markup

Inline and embedded SVG icons must declare explicit geometric and color defaults directly on the `<svg>`
element in markup (`width`, `height`, `viewBox`, `fill`, `stroke`) rather than relying entirely on external
CSS classes:

```html
<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
  ...
</svg>
```

When external stylesheets are delayed, fail to load, or lag behind cached markup, unconstrained SVGs default
to natural `viewBox` coordinates (or 100% parent width) and black fills (`#000000`), producing massive unstyled
black blobs and severe Cumulative Layout Shift (CLS). Defensive presentation attributes ensure the icon
maintains its physical dimensions and color inheritance before CSS executes.

## The readiness gate is bounded

The shared [readiness gate](../components/SHARED_UI.md) preloads **startup-critical** assets only —
enough that nothing stalls mid-experience — and reports progress in every locale with a retry path. It
is not a license to download an unbounded catalog: a short experience may preload whole, but a longer
one defines a bounded startup bundle and prefetches later parts after the first interaction.

## Provenance is recorded

Every sourced or generated asset is **local** (never hotlinked), and its origin is recorded: the tool
or source, the prompt or search terms, license expectations, any edits, and review status. This
provenance lives in a project `docs/` pipeline file so an asset's right-to-use and reproducibility stay
clear later. Font-specific formats, licensing, and delivery rules live in
[typography](TYPOGRAPHY.md).

## Web asset caching and delivery

Asset delivery, HTTP `Cache-Control` headers, and deterministic cache-busting protocols for web assets
and templates are authoritative in [deployment and HTTP caching](DEPLOYMENT_AND_CACHING.md).

## Rules

- Content is schema-validated data, validated at construction, referred to by one name.
- All asset paths derive from one layout definition; no path is typed by hand in more than one place.
- Generate placeholders from the contract so real loading/timing paths run before final media exists.
- Prefer code-native visuals; keep every asset local; record each asset's provenance.
- The readiness gate loads only startup-critical assets; larger content prefetches after first use.
- Inline SVG icons define explicit defensive dimensions and fill/stroke fallbacks in markup.
- Web asset delivery obeys the HTTP caching and versioning matrix in deployment and HTTP caching.
