# Structured data presentation

Authoritative for dense key-value lists, entity summaries, dynamic schema records, and record detail
panels across web and mobile clients. It complements [mobile responsiveness](MOBILE_RESPONSIVENESS.md),
[typography](TYPOGRAPHY.md), and [rendered UI integrity](RENDERED_UI_INTEGRITY.md).

## Compact density vs. visual bloat

Complex domain records—such as legal cases, financial transactions, user accounts, and CRM profiles—contain
many interdependent attributes. Rendering each attribute as an oversized metric card with massive typography
rapidly exhausts vertical screen real estate, forcing users to scroll excessively to assemble a mental
model of the record.

Group related record attributes inside a compact **structured data panel** (`.data-summary-panel` or
`.entity-data-grid`) with subtle background framing, unified borders, and high contrast. Dense
presentation must enhance, not compromise, scannability:
- maintain clear visual hierarchy between field labels and field values;
- avoid unnecessary whitespace gaps between adjacent data pairs;
- lock typography to legible, compact scales (`0.74rem`–`0.85rem`);
- support optional semantic icons to accelerate visual indexing.

## Adaptive row spanning (Full-width vs. Grid-column)

Data panels organize attributes within a responsive grid:

```css
.data-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 240px), 1fr));
  gap: 0.45rem 0.65rem;
}
```

Never force every field into identical column widths. Apply **adaptive row spanning** (`grid-column: 1 / -1`)
whenever a field's semantic type or rendered content exceeds normal column capacity.

### Promotion criteria

Promote an item to a full-width row (`.data-item--full` or `grid-column: 1 / -1`) when either condition is met:

1. **Semantic type condition:**
   The field's schema or domain definition denotes multi-line narrative text (e.g. `textarea`, structured
   notes, legal arguments, user comments, or multiline addresses). Constraining narrative prose inside a
   `240px` column produces an uneven, vertically bloated layout that misaligns adjacent grid rows.

2. **Content length threshold (dynamic promotion):**
   Even when typed as scalar text or a single-choice option, promote any field whose formatted display
   string exceeds **40–45 characters** (or approximately 3–4 average words) to a full-width row.
   This guarantees long titles or unexpected data lengths do not cause awkward mid-word breaks or
   excessive line wrapping in narrow grid tracks.

Standard scalar fields (numbers, currencies, dates, booleans, short status badges, and compact codes)
remain in the standard auto-fitting grid columns.

## Layout hierarchy: Stacked vs. Inline

The spatial relationship between a field's label and its value must change depending on available width.

### 1. Stacked layout in standard grid columns (`flex-direction: column`)

Inside standard multi-column tracks (`240px`–`320px`), always stack the label above the value:

```html
<div class="data-item">
  <div class="data-label">
    <svg class="data-icon">...</svg>
    <span>Current Monthly Rent</span>
  </div>
  <div class="data-val text-tabular">12,000,000 IRR</div>
</div>
```

```css
.data-item {
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: stretch;
  gap: 0.22rem;
  padding: 0.4rem 0.65rem;
  min-width: 0;
}

.data-label {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.74rem;
  color: var(--text-muted);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.data-val {
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--text-primary);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
```

**Rationale:** Placing label and value side-by-side (inline) in a `240px` column starves the value of
horizontal space. A descriptive label like "Current Monthly Rent:" consumes over 60% of the column width,
leaving inadequate space for the value and triggering premature wrapping or truncation. Stacking provides
100% of the column width to the label and 100% to the value.

### 2. Inline layout in full-width rows (`flex-direction: row`)

In full-width rows spanning the entire container (`grid-column: 1 / -1`), arrange the icon, label, and value
horizontally on a single line on desktop and tablet viewports:

```html
<div class="data-item data-item--full">
  <div class="data-label">
    <svg class="data-icon">...</svg>
    <span>Claimed Eviction Reason:</span>
  </div>
  <div class="data-val">Other non-statutory justifications outside Article 7</div>
</div>
```

```css
.data-item--full {
  grid-column: 1 / -1;
  display: flex;
  flex-direction: row;
  justify-content: flex-start;
  align-items: center;
  gap: 0.5rem;
  text-align: start;
}

.data-item--full .data-label {
  flex-shrink: 0;
  font-size: 0.82rem;
  display: inline-flex;
  align-items: center;
  line-height: 1.5;
}

.data-item--full .data-val {
  flex-grow: 1;
  text-align: start;
  white-space: normal;
  line-height: 1.5;
  overflow: visible;
}
```

**Rationale:** Stacking a label and value across a full-width row wastes an entire vertical line merely
for a short heading. An inline layout preserves vertical compactness while providing high narrative
flow.

On small mobile viewports (`≤ 640px`), full-width items collapse gracefully back to a stacked column:

```css
@media (max-width: 640px) {
  .data-item--full {
    flex-direction: column;
    align-items: stretch;
    gap: 0.25rem;
  }
}
```

## Bidirectional alignment and flex sizing

In bidirectional layouts (RTL and LTR):

1. **Explicit start alignment:**
   Declare CSS logical alignment (`text-align: start; justify-content: flex-start;`). Avoid default centered
   flex alignment on inline rows, which creates awkward gaps or misplaced colons when localized text direction
   changes.
2. **Defensive flex sizing:**
   - The label must declare `flex-shrink: 0;` so long narrative values do not crush or shrink the label text.
   - The value container must declare `flex-grow: 1; min-width: 0;` to allow proper wrapping without
     bleeding outside the container.
3. **Punctuation and colon placement:**
   Render the colon (`:`) as part of the label container (e.g. `<span>Label:</span>`) rather than a floating
   sibling node. In RTL languages (Persian, Arabic), appending the colon directly to the label string ensures
   it stays anchored to the end of the phrase rather than flipping incorrectly to the right side of the value.

## Optical vertical centering and font metrics

When an inline row contains icons, labels, and text values within a bordered box, mathematical CSS centering
(`align-items: center` with equal top and bottom padding) frequently fails the human eye.

### 1. The non-Latin / Persian font metric anomaly

Non-Latin web fonts (e.g. Vazirmatn, IRANSans, Arabic display faces) allocate substantial descender reserve
space at the bottom of their em-box to accommodate rare, deep descenders (e.g. isolated `ی`, `ع`, `ل`).
However, typical field labels and headings consist predominantly of glyphs that sit flat on the baseline
with ascenders rising upward (e.g. `د`, `ل`, `ا`, `ت`, `خ`, `ه`).

When rendered with mathematically symmetric padding (`padding: 0.45rem` top and bottom):
- The physical ink/glyph mass sits **1.5px to 2px higher** than the box's true visual center.
- The gap between the top border and the text appears visibly smaller than the gap between the text and
  the bottom border.
- The human eye perceives the label as awkwardly "floating high" (`کمی بالاتره`).

### 2. Asymmetric optical padding

Compensate for font descender reserves by applying **asymmetric optical vertical padding**:

```css
/* WRONG: mathematically equal, but visually biased towards the top border */
.data-item--full {
  padding: 0.45rem 0.75rem;
}

/* CORRECT: optical padding balances top and bottom visual clearance equally */
.data-item--full {
  padding: 0.54rem 0.75rem 0.38rem 0.75rem;
}
```

Measure the rendered bounding boxes in a headless browser audit: the distance from the top border to the
highest glyph peak must equal the distance from the lowest glyph base to the bottom border (within ±1px).

### 3. Inline font size harmonization

In an inline row (`[icon] [label:] [value]`), do not use widely disparate font sizes (e.g. label at
`0.74rem` and value at `0.85rem`). Because flexbox aligns child bounding boxes, disparate font sizes
displace their baselines, making the smaller label sit noticeably higher than the adjacent value.

**Rule:** In full-width inline rows, harmonize the label font size (e.g. `0.82rem`) with the value font size
(`0.85rem`) and enforce uniform `line-height: 1.5` across both elements so text flows across an unbroken
horizontal baseline.

## General applicability

These presentation rules apply universally across:
- **Dynamic schema entities:** EAV models, generic case files, configurable consultation wizards, and dynamic
  attribute builders where field types (`CaseFieldType`) and lengths vary at runtime.
- **Static / hardcoded entity cards:** User profiles, invoice summaries, shipping status panels, and KYC
  compliance screens.

## Acceptance

- [ ] Attribute grids use responsive auto-fit columns (`repeat(auto-fit, minmax(min(100%, 240px), 1fr))`).
- [ ] Multi-line narrative fields (e.g. `textarea`, descriptions) dynamically span the full width (`grid-column: 1 / -1`).
- [ ] Scalar fields whose formatted content exceeds 40–45 characters dynamically promote to full-width rows.
- [ ] Multi-column items stack label above value (`flex-direction: column`), avoiding cramped side-by-side wrapping.
- [ ] Full-width rows display icon, label, and value inline on desktop and tablet, collapsing to column on mobile (`≤ 640px`).
- [ ] Inline items declare explicit start alignment (`text-align: start`, `justify-content: flex-start`).
- [ ] Colons anchor to the label element to preserve correct punctuation in RTL scripts.
- [ ] Single-line bordered items apply asymmetric optical padding to ensure text sits in the exact visual vertical center.
- [ ] Label and value in inline rows share harmonized font sizes and matching line-heights for level baseline alignment.
- [ ] Automated rendered browser checks verify zero overflow and optical vertical clearance balance across supported viewports.
