# Mobile Responsiveness & Small Screens

Authoritative for viewport meta definitions, responsive layouts, horizontal overflow prevention,
small-screen constraints, touch ergonomics, and automated mobile regression testing.

## Support small viewports by default

Web interfaces must render correctly and legibly across device viewports down to narrow mobile dimensions.
Supporting mobile is not an optional afterthought or a desktop layout scaled down; it is a foundational
architectural invariant.

### Viewport baseline matrix

Every responsive web application must verify layout geometry against the following baseline mobile viewports:
- **360 px**: Narrow and budget Android devices (e.g., Galaxy A-series).
- **375 px**: Compact iOS viewports (e.g., iPhone SE / mini).
- **390 px**: Standard modern iOS viewports (e.g., iPhone 12/13/14/15/16).
- **412 px**: Standard modern Android viewports (e.g., Galaxy S-series, Pixel).
- **640 px – 768 px**: Tablets, foldables, and landscape devices.
- **900 px – 1100 px**: Laptops, desktop monitors, and split-screen windows.

Every screen and layout must render without horizontal scrollbars, clipped text, or overlapping interactive
elements across all baseline widths.

## Viewport meta configuration

Every HTML document and layout template must declare the standard responsive viewport meta tag inside `<head>`:

```html
<meta name="viewport" content="width=device-width, initial-scale=1.0">
```

Do not disable user zooming (`user-scalable=no` or `maximum-scale=1.0`) unless explicitly required by a native
WebView shell container; restricting zoom harms accessibility for visually impaired users.

## The Zero-Overflow Principle

Horizontal scrolling at the document root level (`document.documentElement.scrollWidth > window.innerWidth`)
is strictly forbidden.

1. **Do not use `overflow-x: hidden` on `body` as a band-aid**:
   Applying `overflow-x: hidden` to `<body>` or `<html>` conceals the scrollbar but does **not** solve layout
   overflow. In mobile WebKit (iOS Safari) and mobile Chromium, elements wider than the viewport still cause
   touch-drag horizontal displacement, sever content on the edges, and break RTL layout alignment.
2. **Structural containment**:
   Every element must be structurally contained within the screen width. Its bounding client rect must satisfy:
   - In LTR: `rect.left >= 0` and `rect.right <= window.innerWidth`
   - In RTL: `rect.left >= 0` (no negative left displacement) and `rect.right <= window.innerWidth`

## Flexbox and Grid containment mechanics

The two most frequent causes of accidental mobile overflow are unconstrained flex items and rigid grid column
minima.

### 1. The Flexbox `min-width: 0` requirement

By CSS specification, flex items have a default `min-width: auto`. This means a flex item cannot shrink
smaller than its content or its longest unbreakable line of text. If a flex item contains a row of links,
buttons, or long text, it will force the flex container (and frequently the entire page header or body) to
expand beyond the screen width.

**Rule**: Any flex item that wraps text, navigation links, or complex nested components must explicitly declare
`min-width: 0` (or `max-width: 100%`) to enable shrinkage:

```css
/* WRONG: expands parent to width of all child links */
.site-nav {
  display: flex;
  width: 100%;
}

/* CORRECT: permits flex child to shrink within container */
.site-nav {
  display: flex;
  width: 100%;
  min-width: 0;
  max-width: 100%;
}
```

### 2. Defensive Grid `minmax` sizing

Never define CSS Grid tracks with a static minimum pixel value that can exceed small-screen widths.
For example, on a 360 px viewport with 16 px padding on each side (32 px total), the available container
width is only **328 px**. A track defined as `minmax(340px, 1fr)` or `minmax(320px, 1fr)` with gap will instantly
break out of the screen.

**Rule**: Use `min(100%, <target-width>)` inside `minmax()`:

```css
/* WRONG: overflows viewports narrower than 320px + padding */
.card-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
  gap: 1.5rem;
}

/* CORRECT: safely shrinks to single full-width column on narrow screens */
.card-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 280px), 1fr));
  gap: 1.5rem;
}
```

### 3. Container padding budget

Containers on desktop typically apply 1.5 rem to 2.5 rem horizontal padding. On mobile screens (`< 640px`),
generous padding consumes scarce screen real estate.

**Rule**: Reduce container horizontal padding to `1rem` (16 px) on screens `< 640px`:

```css
.container {
  width: 100%;
  max-width: 1240px;
  margin-inline: auto;
  padding-inline: 1.5rem;
  box-sizing: border-box;
}

@media (max-width: 640px) {
  .container {
    padding-inline: 1rem;
  }
}
```

### 4. Multi-column vertical alignment and baseline lock

In multi-column grid or flex layouts (such as adjacent form fields, metric cards, or pricing tiers),
child elements (labels, titles, captions) often vary in text length across localized strings or descriptive
phrasing. A multi-line label pushes adjacent inputs or primary values downward, creating jagged,
staggered baselines that disrupt visual rhythm and order.

**Rule**: Primary interactive controls (inputs, buttons) and key metric values across adjacent columns
must maintain locked vertical alignment regardless of label or header line counts. Apply one of three
canonical patterns:

1. **Flex column with anchored controls (`margin-top: auto`)**:
   Set `.form-group` or the column card to a flex column (`display: flex; flex-direction: column;`).
   Apply `min-height` on the label to accommodate the maximum expected line count, or apply `margin-top: auto`
   to the control wrapper. This anchors inputs or actions to a shared horizontal baseline across the row:

   ```css
   .form-group {
     display: flex;
     flex-direction: column;
     height: 100%;
   }
   .form-group .form-label {
     min-height: 2.8rem; /* Accommodates up to 2 lines of text */
   }
   .form-group .input-wrapper {
     margin-top: auto;
   }
   ```

2. **CSS Subgrid (`grid-template-rows: subgrid`)**:
   For multi-track components where labels, inputs, and hints must align independently across
   sibling columns:

   ```css
   .form-grid {
     display: grid;
     grid-template-columns: repeat(auto-fit, minmax(min(100%, 250px), 1fr));
     grid-template-rows: auto auto auto;
   }
   .form-grid > .form-group {
     display: grid;
     grid-row: span 3;
     grid-template-rows: subgrid;
   }
   ```

3. **Stat and metric cards header lock**:
   In multi-column stat or metric summary strips, wrap titles in an explicit header container with a
   consistent `min-height`, or push the metric value down via `margin-top: auto`, so that primary numbers
   sit on an identical horizontal baseline across every card.

### 5. Container containment and internal element bleed

Zero-overflow applies at the **component and container level**, not merely the viewport root (`window.innerWidth`).
Dynamic numeric outputs, localized strings, or badge clusters within cards, grid tracks, and table cells
frequently exceed their parent column width if unconstrained:
- Multi-column cells and metric cards must declare defensive containment (`min-width: 0; overflow: hidden;`
  or explicit flex wrapping) so dynamic content cannot bleed across column dividers into adjacent sibling content.
- Never apply global or rigid `white-space: nowrap` to containers holding multi-part text or translated strings.
  When content cannot fit on a single line within narrow tracks, stack sub-values vertically via flex columns
  or apply fluid typography (`clamp()`).

### 6. Structured data panels

For dense record panels, apply [structured data presentation](STRUCTURED_DATA_PRESENTATION.md) to the
baseline viewport and internal-containment checks here. Include populated panels in the mobile audit
so the data composition is exercised.

## Navigation and interactive ergonomics

Small-screen usability requires thumb-friendly touch targets and clean navigational state transitions.

### 1. Collapsible mobile navigation

Desktop horizontal navigation bars must collapse below tablet/mobile breakpoints (`≤ 900px` or `≤ 768px`)
into an accessible drawer, modal sheet, or slide-down menu triggered by a hamburger button:
- The toggle button must provide proper ARIA attributes: `aria-label`, `aria-expanded="false|true"`, and
  `aria-controls="<nav-id>"`.
- The menu must close automatically when a navigation link is clicked, when the user clicks outside, or
  when the Escape key is pressed.
- Do not leave horizontal navigation links in an unstyled horizontal scroll track without clear visual affordances
  or scroll-snap boundaries.

### 2. Touch target sizing

Interactive elements (buttons, inputs, links, checkboxes) must meet minimum physical touch ergonomics:
- Minimum touch target: **44 × 44 px** (or 48 × 48 px where feasible).
- Maintain at least **8 px** clearance between adjacent tap targets to prevent accidental misclicks.

### 3. Full-width action stacks (CTA buttons)

On mobile viewports, side-by-side button pairs frequently wrap awkwardly or squeeze labels into multiple lines.
Stack primary and secondary call-to-action buttons vertically at `width: 100%` on screens `< 640px`:

```css
@media (max-width: 640px) {
  .action-group {
    flex-direction: column;
    align-items: stretch;
    gap: 0.75rem;
  }
  .action-group .btn {
    width: 100%;
    justify-content: center;
  }
}
```

### 4. Modals, dialog sheets, and virtual keyboard ergonomics

Modal dialogs, popovers, and bottom sheets on mobile viewports must never trap or clip content:
- **No rigid overflow hidden:** Never place `overflow: hidden` on a modal's primary content container
  or decorative frame on mobile viewports (`< 640px`). Doing so locks touch scrolling and cuts off
  action buttons or form inputs when the on-screen virtual keyboard (IME) expands.
- **Dynamic viewport bounds:** Constrain the dialog's maximum height defensively using dynamic viewport
  units: `max-height: calc(100dvh - 2rem);` (with fallback to `calc(100vh - 2rem)`).
- **Momentum scrolling:** Enable native momentum scrolling on the dialog container:
  `overflow-y: auto; -webkit-overflow-scrolling: touch;`.
- **Soft keyboard visibility:** Form inputs inside modals must remain reachable when focused. Avoid
  fixed vertical centering that forces the top or bottom of a tall dialog out of view when the
  viewport shrinks.
- **Defensive decorative borders:** Remove rigid or unscalable border ornaments (e.g. decorative frames
  with fixed borders) on mobile viewports to preserve critical vertical reading area.

### 5. Drawer-to-modal transition coordination

When an interactive control inside a collapsible mobile navigation drawer triggers a modal dialog
(such as an authentication or profile dialog):
- The mobile navigation drawer must immediately dismiss/close itself before or concurrently with the
  modal opening.
- Never leave a mobile navigation drawer open beneath an active modal backdrop; doing so traps keyboard
  focus, causes stacking-context conflicts, and intercepts touch events intended for the modal.

## Bidirectional layout (RTL / LTR) parity

Small-screen overflow issues often manifest differently in RTL scripts than in LTR scripts:
- In RTL, overflowing elements spill out to the **negative left** (`rect.left < 0`).
- Always use CSS Logical Properties (`padding-inline`, `margin-inline`, `border-inline-start`,
  `inset-inline-start`) rather than physical directions (`left`, `right`).
- Never apply directional borders (e.g. `border-left`) to grid or list items that wrap to a single column on
  mobile; replace them with bottom dividers or card separations in the mobile media query.

## Automated verification & regression prevention

Mobile responsiveness must be verified through automated testing, not manual visual spot-checking alone.
Every project with a web interface must incorporate a two-layer verification protocol:

### Layer 1: Deterministic template & CSS contract testing

Executed during unit tests (`./bin/test`):
1. **Meta Viewport Invariant**: Asserts that every HTML layout template contains a valid `<meta name="viewport">` tag.
2. **No Rigid Minmax**: Scans templates and CSS for rigid wide column declarations (`minmax(3[0-9]{2}px, ...)`)
   that lack `min(100%, ...)`.
3. **Flexbox Containment**: Verifies that primary navigation and content flex items declare `min-width: 0`.
4. **Multi-Column Alignment Invariant**: Verifies that adjacent `.form-group` or metric card classes in
   multi-column layouts define flex-column anchoring, subgrid, or label min-height constraints.
5. **Asset Cache-Busting Invariant**: Scans layout templates and asserts that all `<link rel="stylesheet">`
   and `<script src>` tags referencing local assets include deterministic cache-busting tokens
   (`?v=<hash>` or content-hashed filenames) to prevent stale asset retention on mobile browsers.

### Layer 2: Headless browser overflow audit (`bin/test-mobile`)

Executed as a dedicated mobile test runner (`./bin/test-mobile`) using headless Chromium:
- Navigates to all key application routes (`/`, primary forms, tracking/detail pages, error pages).
- Emulates the baseline mobile viewports (360 px, 375 px, 390 px, 412 px).
- Evaluates DOM geometry via Chrome DevTools Protocol / CDP:
  - Asserts `document.documentElement.scrollWidth === window.innerWidth`.
  - Scans all visible DOM elements and asserts **0 elements** have `rect.left < -1` or `rect.right > window.innerWidth + 1`.
  - Verifies interactive states (e.g. mobile navigation drawer open/closed) maintain zero-overflow invariants.
  - Verifies modal open states (where applicable) maintain zero horizontal overflow, stay within dynamic
    viewport height bounds (`calc(100dvh - ...)`), and remain vertically scrollable.
  - Asserts vertical baseline alignment for multi-column form controls: adjacent inputs in the same row
    share identical top coordinates (`Math.abs(rectA.top - rectB.top) < 2`).
  - Asserts zero internal container overflow: multi-column cards, grid tracks, and data cells maintain
    `scrollWidth <= clientWidth + 1` with 0 bounding-box collisions across adjacent siblings. Intentional
    text truncation in a structured panel follows the narrow disclosure-backed exception in
    [structured data verification](STRUCTURED_DATA_PRESENTATION.md#verification).
- Fails the build (Exit code 1) with precise DOM element selectors and pixel amounts if any regression is introduced.

### Physical device testing & LAN reachability

Headless audits (`bin/test-mobile`) verify geometric invariants in the exercised states. Physical
mobile checks also exercise touch targets, soft-keyboard dismissal, and viewport transitions; thermal
and GPU checks follow [UI performance](UI_PERFORMANCE.md). Use the shared
[development runners and LAN verification](../components/MONOREPO.md#development-server-execution-and-lan-reachability)
with the [browser access configuration](../components/WEB_CLIENT.md#development-server-and-lan-reachability).

## Acceptance

- [ ] All pages include `<meta name="viewport" content="width=device-width, initial-scale=1.0">`.
- [ ] No horizontal scrolling occurs on any route at 360 px, 375 px, 390 px, or 412 px viewports.
- [ ] `body` does not use `overflow-x: hidden` to conceal layout overflows.
- [ ] Primary navigation collapses gracefully on mobile with accessible ARIA toggle controls.
- [ ] Touch targets meet the 44 × 44 px minimum threshold.
- [ ] Grid layouts utilize defensive minmax rules (`minmax(min(100%, 280px), 1fr)`).
- [ ] Both LTR and RTL directions maintain zero horizontal overflow.
- [ ] Adjacent inputs, action buttons, and metric values in multi-column rows maintain identical vertical alignment (locked baseline) regardless of label line wrapping.
- [ ] Child elements, cards, and grid columns maintain zero internal overflow and do not bleed into adjacent siblings; intentional text truncation follows the [structured data verification](STRUCTURED_DATA_PRESENTATION.md#verification) exception.
- [ ] Multi-line labels and badges break at intentional semantic boundaries without fractured parentheticals or orphan words (see [typography](TYPOGRAPHY.md#enforce-semantic-line-breaking-and-inspect-wrap-boundaries)).
- [ ] Structured data panels pass the dedicated narrow-viewport and direction matrix (see [structured data presentation](STRUCTURED_DATA_PRESENTATION.md)).
- [ ] Modals and dialog sheets on mobile use dynamic viewport bounds (`100dvh`) with vertical scrolling and zero `overflow: hidden` clipping during virtual keyboard activation.
- [ ] Triggering a modal from a mobile navigation drawer automatically dismisses the drawer.
- [ ] All HTML/template files pass the static asset cache-busting invariant check.
- [ ] Automated mobile test runner (`./bin/test-mobile`) executes cleanly with zero failures.
- [ ] The development workflow passes [LAN verification](../components/MONOREPO.md#verification)
      and the relevant physical-device interaction checks above.
