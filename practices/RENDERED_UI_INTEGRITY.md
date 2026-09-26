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

1. wait on explicit application readiness and state assertions rather than arbitrary sleeps;
2. inspect computed/rendered state after fonts and startup-critical styles are ready;
3. report the route, principal capability, UI state, viewport, locale, selector, measured values,
   and occluding or clipping element;
4. retain a screenshot or equivalent artifact for a failure that cannot be understood from numbers;
5. fail on unexpected violations, while keeping any exception narrow, justified, and owned; and
6. run the smallest high-risk state set during normal checks and the complete declared matrix in its
   dedicated acceptance runner.

Static template and CSS checks remain useful for required attributes, canonical utilities, token
fallbacks, and prohibited declarations. They complement the rendered audit; neither layer may be
reported as evidence that the other passed.

## Rules

- Audit distinct rendered states across principals, data, interactions, viewports, locales, and
  directions—not merely one screenshot per route.
- Apply WCAG text and non-text contrast thresholds to the correct kind of content and measure the
  actual rendered background.
- Prove controls are visible, reachable, and not unintentionally occluded or clipped.
- Keep screen-reader-only behavior in one startup-critical utility.
- Use standards-based accessibility tooling first; describe custom geometry checks as bounded
  regression evidence, never complete conformance.

## Standards basis

- [WCAG 2.2](https://www.w3.org/TR/WCAG22/) — Level AA text contrast, non-text contrast, use of
  color, focus, reflow, and content visibility requirements
- [Understanding SC 1.4.11: Non-text Contrast](https://www.w3.org/WAI/WCAG22/Understanding/non-text-contrast.html)
  — control and state-indicator contrast against adjacent colors
