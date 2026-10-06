# Structured data presentation

Authoritative for dense key-value lists, entity summaries, dynamic schema records, and record detail
panels across web and mobile clients. It complements [mobile responsiveness](MOBILE_RESPONSIVENESS.md),
[typography](TYPOGRAPHY.md), [internationalization](I18N.md), and
[rendered UI integrity](RENDERED_UI_INTEGRITY.md).

## Typed fields and presentation ownership

A field descriptor supplies a stable key, a localized label, a checked type discriminator, and a value
validated against that type. One presentation mapping selects the localized formatter, optional unit
and semantic icon, text direction, and narrative/multiline layout hint. The renderer consumes that
projection; it does not infer a field's type from its contents or change canonical values to fit a box.
Formatting and layout policy belong in presentation code, outside the domain core.

- Define field variants and their value types through the project's
  [single-source contract](../architecture/CONTRACTS.md). Presentation metadata does not create a
  parallel schema or replace authoritative validation.
- When forward compatibility permits an unknown type, decode it into an explicit checked fallback
  variant. Render its safe text representation or a localized unsupported-value message. Invalid data
  still follows the boundary's validation policy; do not silently cast it to a known field type.
- Keep missing, empty, zero, and false values distinct. Select fields display the localized option label;
  numbers, amounts, units, and dates use the active locale and the field's semantic type.
- Labels and ordinary values remain text: use escaped template output or text nodes. Optional icons
  resolve through the established UI icon system; record-supplied strings do not become raw SVG/HTML.
  Decorative icons are hidden from assistive technology.
- UI labels and content localization follow [internationalization](I18N.md).
- Read-only label-value pairs use semantic `<dl>/<dt>/<dd>` markup or an equivalent accessible
  relationship. Grouping each pair in a direct `<div>` child preserves description-list semantics.
  See the [HTML description-list specification](https://html.spec.whatwg.org/multipage/grouping-content.html#the-dl-element).

## Density and adaptive layout

Group related attributes in a compact panel with clear label-value hierarchy. Use the project's
semantic typography, spacing, and foreground/background tokens with usable literal fallbacks as
specified in [rendered UI integrity](RENDERED_UI_INTEGRITY.md#contrast-and-non-color-information).
Choose density for the supported scripts and text scaling; the blueprint prescribes no universal font
size, track width, or padding.

Standard grid tracks stack labels above values so each receives the track's available width. Narrative
or multiline fields span the panel's full row. Scalars may also span when their formatted label or
value cannot fit the standard track legibly. Character counts and word counts are only optional,
documented heuristics calibrated for the project's locales, fonts, and track widths; they cannot
guarantee fit.

When adjacent primary values must line up, apply the
[multi-column baseline-lock patterns](MOBILE_RESPONSIVENESS.md#4-multi-column-vertical-alignment-and-baseline-lock).

When runtime measurement drives promotion, measure the label and formatted value with their active
fonts against the intended standard track, including icons, gaps, and padding. Follow the
[font-ready fitting lifecycle](TYPOGRAPHY.md#fit-constrained-text-from-rendered-geometry) after changes
to locale, content, fonts, or container size. Measure against a stable standard-track reference so a
promoted row does not repeatedly demote and promote itself.

A full-width item uses an inline label and value only while its container provides enough width;
narrow containers stack them regardless of the viewport's device category. For multiline content,
align the label with the start of the value block or its first text baseline. Preserve meaningful
paragraphs and line breaks rather than collapsing narrative text into one line.

## Containment and complete information

Text-bearing grid and flex children remain shrinkable. Labels also need a bounded width: an
unrestricted `flex-shrink: 0` label can consume the entire inline row. Allow prose to wrap at semantic
boundaries; use emergency wrapping for otherwise unbreakable tokens. CSS
[`overflow-wrap: anywhere`](https://www.w3.org/TR/css-text-3/#overflow-wrap-property) preserves normal
break opportunities and supplies additional ones when a token cannot fit.

This example illustrates the containment mechanics. Class names, spacing, label-width limits, and
responsive thresholds are project choices:

```css
.data-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 15rem), 1fr));
  gap: 0.5rem;
  margin: 0;
}

.data-item {
  display: flex;
  flex-direction: column;
  min-width: 0;
  gap: 0.25rem;
}

.data-label,
.data-val,
.data-label > span {
  margin: 0;
  min-width: 0;
  overflow-wrap: anywhere;
  text-align: start;
}

.data-item--full {
  grid-column: 1 / -1;
  flex-direction: row;
  align-items: flex-start;
}

.data-item--full > .data-label {
  flex: 0 1 auto;
  max-inline-size: 40%;
}

.data-item--full > .data-val {
  flex: 1 1 0%;
}
```

The project's responsive rule switches the inline composition to a column, stretches both children,
and resets their flex basis and label-width limit. Do not hide structural overflow with clipping.

Prefer wrapping or expansion to truncation. If a summary intentionally truncates a label or value,
provide a visible disclosure that reveals the complete text and works with keyboard, touch, and
assistive technology. Keep the full semantic text available to assistive technology. A browser
`title` attribute or screen-reader-only text alone does not give every user access to the hidden part.

## Direction and alignment

Use logical start alignment and keep punctuation within its localized label. Label placement alone
does not resolve mixed-direction text; isolate the value. For unknown-direction text use
`<bdi dir="auto">`; for a field with a known direction, such as a telephone number or machine code,
use an explicit `dir="ltr"` or `dir="rtl"` on the isolated value. Auto direction follows the first
strong character, so digit-only values may need the field's explicit policy. See
[W3C guidance on inline bidi markup](https://www.w3.org/International/articles/inline-bidi-markup/).

Single-line inline rows may use visual centering; multiline rows retain their start alignment.
Compatible font sizes and line-heights alone do not guarantee matching text baselines:
[`align-items: baseline`](https://www.w3.org/TR/css-flexbox-1/#align-items-property) aligns baselines,
whereas `center` aligns boxes. Choose the alignment for the composition and verify text and icons
together. Optical padding follows the one
[typography calibration rule](TYPOGRAPHY.md#optical-alignment-in-bounded-single-line-elements).

## Verification

Extend the risk-based matrix in [rendered UI integrity](RENDERED_UI_INTEGRITY.md#test-architecture)
with authenticated populated record views where applicable and isolated component fixtures for the
field variants. Assert that the intended panel and representative fields are present; a guest view or
empty panel cannot prove data-layout behavior. Cover:

- missing, empty, zero, false, and ordinary populated values;
- every supported type, option-label and unit formatting, and permitted unknown-type fallbacks;
- short and long localized labels and values, multiline narratives, URLs, and unbroken tokens;
- RTL, LTR, and mixed-script values, including numbers with signs or punctuation;
- narrow, tablet, and desktop baseline viewports, plus narrow containers inside wider viewports;
- stacked and inline compositions, text scaling, content/locale updates, and container resizing.

After fonts and styles are ready, automate panel/item containment, absence of sibling collisions,
and disclosure reachability. Check the full text after activating disclosure and verify promotion
updates without oscillation. Intentional truncation may have a larger text `scrollWidth`; it is a
narrow documented exception only when the painted content remains contained and disclosure works.
Inspect semantic wrapping and optical balance visually or through a documented pixel-aware check.
Report state, selector, viewport/container dimensions, locale, direction, and measurements; retain a
screenshot for a visual failure. Choose actual application routes in each project's audit setup.

## Acceptance

- [ ] Field types and value formatting have one checked presentation mapping with explicit fallback
      behavior; canonical values and validation remain intact.
- [ ] Read-only pairs retain semantic relationships, safe text rendering, and localized labels/values.
- [ ] Compact tracks stack label and value; narrative fields span, and scalar promotion considers both
      rendered label and value with a stable measurement reference.
- [ ] Inline rows stack when their container becomes narrow; text children shrink and long tokens
      remain contained without concealing overflow.
- [ ] Full information is available through a visible, keyboard/touch accessible disclosure wherever
      truncation is intentional.
- [ ] Direction isolation, paragraph preservation, semantic wrapping, and font-specific optical
      alignment pass the declared rendered matrix.
- [ ] Tests enter populated states, assert panel presence, and cover every distinct type and layout;
      guest-only route checks are insufficient.
