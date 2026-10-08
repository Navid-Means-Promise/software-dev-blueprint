# Rendered UI integrity

Authoritative for proving that a web interface remains perceptible and operable after templates,
styles, application state, and authorization-specific views are composed in a real browser. It
complements [mobile responsiveness](MOBILE_RESPONSIVENESS.md), [UI performance](UI_PERFORMANCE.md),
and semantic accessibility checks; an automated visual audit alone is not a claim of WCAG
conformance.

## Audit states, not only routes

A route that passes in its default guest state can still fail when a menu opens, a privileged tab is
selected, or real data lengthens a card. Define a risk-based audit matrix across:

- representative principal capabilities: guest, ordinary account, each materially different
  operational role, and denied access;
- meaningful data states: empty, populated, long localized content, loading, validation failure,
  provider failure, and disabled controls;
- interaction states: default, hover, keyboard focus, active/selected, expanded drawers, open
  dialogs, and nested overlays;
- the supported viewport baselines, locales, text directions, and user color modes.

The matrix need not be the full Cartesian product. It must cover every distinct layout, palette,
stacking, and authorization composition. Enter protected states through the normal authenticated
application boundary and deterministic seed/setup APIs; never add an authentication bypass solely
for the audit.

## Contrast and non-color information

Target WCAG 2.2 Level AA unless the project records a stronger requirement:

- normal text and images of text have at least `4.5:1` contrast against the rendered background;
- large text has at least `3:1` contrast under the WCAG large-text definition;
- visual information required to identify a control or its state has at least `3:1` contrast against
  adjacent colors; and
- color is never the only means of communicating status, selection, validation, or an available
  action.

Text inside a button, tab, badge, or other control is still text: being interactive does not by
itself lower its required ratio to `3:1`. Apply exceptions for logos, decoration, incidental text,
and inactive controls only when the content actually meets the standard's exception.

Measure the computed foreground against the pixels that can really appear behind it. Account for
ancestor opacity, translucent layers, pseudo-elements, gradients, images, and state-specific styles.
An average gradient color can hide a failing stop; test the worst relevant region or use a rendered
pixel-aware tool. Critical foreground/background pairs belong together in the baseline stylesheet.
When they depend on custom properties supplied elsewhere, include usable literal fallbacks such as
`var(--control-text, #172033)` so a delayed, missing, or stale secondary stylesheet does not make
content disappear.

### Token hygiene and silent fallback defense

CSS custom properties fail silently at computed-value time: an unregistered, misspelled, or missing
property reference degrades to `inherit`. On elevated, inverted, or dark-surface containers (such as
chat bubbles, drawers, and status banners) rendered against a light application canvas, an invalid
variable reference silently inherits the root dark text color, collapsing the contrast ratio to near
`1:1` without generating a browser warning or runtime error.

Guard against silent fallback failures:

- enforce a canonical token prefix (e.g. `--c-*` or `--color-*`) repository-wide;
- provide explicit, accessible literal fallbacks on inverted or dark-mode surfaces where inherited
  canvas text colors would be imperceptible; and
- catch unregistered custom property references during build-time linting or startup token audits.

## Occlusion, clipping, and reachability

Every visible interactive control must retain a usable hit region and keyboard focus target. A
rendered-browser audit checks browser actionability or hit-tests multiple inset points against the
control and its descendants. A center-point test alone is insufficient: a small overlay can block
the primary target while leaving that one point clear, or an intentional child icon can occupy the
center.

Classify overlap by state:

- sticky headers, banners, drawers, and decorative layers must not cover unrelated controls;
- an active modal backdrop may intentionally block the page beneath it, but the background is then
  removed from pointer and keyboard interaction while the dialog remains reachable;
- opening or closing one layer leaves one unambiguous interaction owner—no stale invisible backdrop
  or competing stacking context; and
- fixed controls remain reachable after scrolling, zooming, and opening the virtual keyboard.

Positive width and height do not prove visibility. Check clipping ancestors, viewport intersection,
text truncation, scroll-container reachability, and whether another painted layer hides the target.
Intentional clipping and truncation require an accessible way to obtain the complete information.

Distinguish between true layer occlusion (unintended headers, sticky bars, or active dialogs blocking
a control) and **ancestor scroll clipping**. In scrollable streams, message feeds, and tall tables,
controls scrolled outside the visible client rect of an overflow ancestor (`overflow: auto`,
`overflow: scroll`, or `overflow: hidden`) naturally yield a hit on sibling or parent containers at
their viewport coordinates. An occlusion audit must check whether the hit test point lies within the
bounding rectangle of all clipping ancestors before asserting an occlusion failure, and scroll
targets into their visible container window before testing interactive reachability.

Use one canonical visually-hidden utility in the startup-critical stylesheet. It hides assistive
text visually without removing it from the accessibility tree and restores any focusable skip link
when focused. Do not wait for a secondary theme stylesheet to hide screen-reader-only content, and
do not use the utility to conceal interactive controls that remain in the tab order.

## Test architecture

Use established accessibility tooling for semantics, names, roles, relationships, focus, and the
rules it implements. Add a small project-owned browser audit only for geometry or composition
invariants that the established engine cannot express, such as app-specific layer occlusion. Custom
heuristics are regression detectors, not replacements for standards-based tooling or assistive-
technology review.

The audit must:

1. **fail closed on state discovery:** assert the presence and resolution of every declared route,
   navigation trigger, modal, or drawer in the matrix. Swallowing locator or navigation errors with
   silent skips (e.g. `.catch(() => null)` without assertion) is prohibited; an unreachable state is
   a hard failure;
2. **wait on concrete dynamic data hydration:** in client-rendered feeds, chat logs, and paginated
   views, wait explicitly for domain data nodes (e.g. messages of each sender type, populated list
   cards) before measuring contrast or geometry, preventing empty-DOM false negatives;
3. inspect computed/rendered state after fonts and startup-critical styles are ready;
4. report the route, principal capability, UI state, viewport, locale, selector, measured values,
   and occluding or clipping element;
5. retain a screenshot or equivalent artifact for a failure that cannot be understood from numbers;
6. fail on unexpected violations, while keeping any exception narrow, justified, and owned; and
7. run the smallest high-risk state set during normal checks and the complete declared matrix in its
   dedicated acceptance runner.

Static template and CSS checks remain useful for required attributes, canonical utilities, token
fallbacks, and prohibited declarations. They complement the rendered audit; neither layer may be
reported as evidence that the other passed.

## Rules

- Audit distinct rendered states across principals, data, interactions, viewports, locales, and
  directions—not merely one screenshot per route.
- Execute audit runners fail-closed: assert navigation targets, triggers, and dynamic data hydration
  explicitly; never swallow errors to skip states silently.
- Guard against silent custom-property fallback: enforce token namespace hygiene and provide
  accessible fallbacks on inverted or elevated surfaces.
- Apply WCAG text and non-text contrast thresholds to the correct kind of content and measure the
  actual rendered background.
- Prove controls are visible, reachable, and not unintentionally occluded; distinguish ancestor scroll
  clipping from layer occlusion.
- Keep screen-reader-only behavior in one startup-critical utility.
- Use standards-based accessibility tooling first; describe custom geometry checks as bounded
  regression evidence, never complete conformance.

## Standards basis

- [WCAG 2.2](https://www.w3.org/TR/WCAG22/) — Level AA text contrast, non-text contrast, use of
  color, focus, reflow, and content visibility requirements
- [Understanding SC 1.4.11: Non-text Contrast](https://www.w3.org/WAI/WCAG22/Understanding/non-text-contrast.html)
  — control and state-indicator contrast against adjacent colors
