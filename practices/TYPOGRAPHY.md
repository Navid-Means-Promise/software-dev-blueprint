# Typography

Authoritative for typeface selection, semantic font roles, delivery, and responsive text fitting.
Typography is application infrastructure: it must be consistent across the shared UI, correct for
every supported script, locally available, and testable.

## Select by language and role

Choose typefaces for each supported language after reviewing that script's legibility, shaping,
weight range, and product tone. Do not select one family for its Latin design and accept an
accidental system fallback for another script.

Define semantic CSS tokens once for interface/body text and display/literary text. Add a narrower
role such as engraved text only when its physical treatment genuinely needs different metrics.
Components consume those tokens; they never hardcode families or create competing stacks.

Text explicitly written in a locale other than the page locale carries the correct `lang` and `dir`
attributes and uses that locale's interface token. A language switch is the recurring example: its
label names the target language, so its typography follows the target rather than the current page.

Typeface choices remain project-specific. Record the chosen family for every language/role in the
project's design documentation; the blueprint defines the system, not one universal font pairing.

## Keep fonts local and reproducible

Never hotlink runtime fonts. Vendor them from an authoritative HTTPS source at a pinned revision and
retain the redistribution license, source URL, version or revision, and checksums. Follow the common
asset provenance policy in [assets and content](ASSETS_AND_CONTENT.md).

Choose webfont formats from the project's documented browser/WebView compatibility matrix. WOFF2 is
the default for modern targets; add WOFF or TTF only when a supported runtime requires it, because
redundant formats increase build and cache surface even when the browser downloads one. Prefer a
variable font when it replaces several static files without shipping unused axes or styles. Use
`font-display: swap`; load only the language and roles a screen actually renders.

## Fit constrained text from rendered geometry

When a decorative surface changes type size according to whether text wraps, measure rendered lines
after the active font loads. Character count is not a proxy for shaped width.

Recalculate after locale, font, or container-size changes. A one-line label may use a larger optical
size; a wrapped label keeps the smaller size and centers its complete line block. If the preferred
single-line size wraps, fit downward only to the largest size that remains one line. The semantic
text and accessible control stay real HTML even when the surrounding surface is image-backed or
rendered. When wrapped labels appear in multi-column rows, prevent staggered adjacent controls by applying
the baseline-lock rules in [mobile responsiveness](MOBILE_RESPONSIVENESS.md#4-multi-column-vertical-alignment-and-baseline-lock).

## Equalize Persian and Arabic lines with Keshide (PersianAligner)

Do not justify multi-line Persian or Arabic brand titles, epigraphs, or constrained multi-line labels
by applying Latin-style inter-word space distribution (e.g. `justify-content: space-between` or
expanded `word-spacing`). In cursive scripts, artificial whitespace gaps create visually disruptive
"rivers of white space" that tear connected words apart and contradict authentic scribal and typographic
tradition.

In Persian and Arabic typesetting, line equalization and edge justification are achieved through
**Keshide (Tatweel — `ـ`, Unicode `U+0640`)**, extending the horizontal baseline between connected
cursive letterforms rather than widening the spaces between words.

When an application requires equal-width multi-line blocks (such as branding lockups, poetic couplets,
or symmetrical badges), employ the `PersianAligner` pattern:

1. **Strict cursive letter whitelist (Never blacklist)**:
   Enforce an explicit two-sided character whitelist. A negative blacklist of non-connecting letters is strictly prohibited because it falsely classifies digits, punctuation, mathematical symbols, and abbreviations as connecting letterforms. A Keshide may ONLY be inserted between two genuinely connecting Persian/Arabic letters:
   - `c1` must strictly belong to the cursive connecting set (`ب`, `پ`, `ت`, `ث`, `ج`, `چ`, `ح`, `خ`, `س`, `ش`, `ص`, `ض`, `ط`, `ظ`, `ع`, `غ`, `ف`, `ق`, `ک`, `گ`, `ل`, `م`, `ن`, `ه`, `ی`, `ئ`).
   - `c2` must strictly belong to the set of letters accepting connection from the right (`آ`, `ا`, `ب`, `پ`, `ت`, `ث`, `ج`, `چ`, `ح`, `خ`, `د`, `ذ`, `ر`, `ز`, `ژ`, `س`, `ش`, `ص`, `ض`, `ط`, `ظ`, `ع`, `غ`, `ف`, `ق`, `ک`, `گ`, `ل`, `م`, `ن`, `و`, `ه`, `ی`, `ئ`).
   Never insert Keshide adjacent to digits (`۰-۹`, `0-9`), math operators (`+`, `-`), punctuation, dots (such as abbreviations like `م.ت`), or whitespace.

2. **Exclusion of numerical, algebraic, and financial data**:
   Keshide line equalization applies exclusively to calligraphic display text, brand lockups, epigraphs, and decorative banners. Never apply Keshide equalization to numerical figures, financial metrics, currency units, algebraic outputs, or dynamic calculator results. Numbers and units must remain clean, legible, and unelongated to prevent floating dash artifacts (e.g. `+۲.۱ـ`).

3. **Calligraphic priority scoring**:
   Prioritize elongation points according to classical aesthetic hierarchy:
   - High priority: Medial and initial `ش` and `س` before final or middle characters (e.g. `مشـاوره`, `توسـط`).
   - Medium priority: Connecting ascenders and descenders like `قـ`, `کـ`, `گـ`, `پـ`, `تـ`, `بـ`, `ثـ`, `حـ`, `جـ`, `چـ`, `خـ`.
   - Balanced distribution: Distribute multiple tatweels across eligible words rather than concentrating extreme elongation on a single glyph.

4. **Rendered geometry measurement**:
   Do not guess character counts. Use an offscreen canvas context (`ctx.measureText`) configured with the
   active computed font (`window.getComputedStyle`) after `document.fonts.ready` resolves. Measure the
   widest reference line and calculate the exact pixel deficit for shorter lines.

5. **SEO integrity, screen readers, and semantic text preservation**:
   Never expose elongated tatweel glyphs as the primary semantic text nodes to search engines, web crawlers,
   or assistive technologies. Injected tatweels (`ـ`) alter raw string tokens, which can distort search indexing,
   keyword extraction, and text-to-speech pronunciation if left unprotected. Follow the four-fold semantic
   preservation standard:
   - **Dual DOM architecture**: The container must encapsulate clean, unadorned Persian text inside an accessible
     screen-reader element (`.sr-only` / `.visually-hidden`) and/or `aria-label`. The visually equalized Keshide
     lines must be isolated inside an `aria-hidden="true"` presentation container so web crawlers and screen readers
     process only the canonical, unelongated wording.
   - **Data-attribute provenance**: Each line element must store its original unelongated string in a
     `data-original-text` attribute. This guarantees the runtime engine can re-evaluate layout geometry without
     compounding tatweel counts across viewport resizes.
   - **Clipboard normalization**: Intercept clipboard `copy` events across Keshide elements to strip Unicode `U+0640`
     from `clipboardData`. When users copy aligned titles or phrases, their clipboard receives authentic, clean
     Persian text suitable for search inputs and external documents.
   - **Metadata and structured data immunity**: Page `<title>`, `<meta name="description">`, OpenGraph tags,
     and Schema.org JSON-LD must strictly contain canonical, tatweel-free Persian strings.

6. **Progressive enhancement and generic line selectors**:
   - Provide pre-balanced static markup with baseline Keshides so initial render paints symmetrically without layout
     shifts (CLS) before JavaScript executes.
   - Attach runtime equalization declaratively via `[data-persian-align]`, `.persian-align`, or `[data-keshide-equalize]`.
   - Target generic line selectors (`.persian-align-line`, `[data-align-line]`, `.align-line`, or direct semantic child lines) rather than coupling the engine to hardcoded component classes.
   - Re-sync dynamically (`window.PersianAligner.run()`) when child DOM contents update at runtime, and debounce window resize handlers.

## Typographic justification for Persian and Arabic body copy

When formal prose, legal stipulations, FAQ answers, or editorial body copy requires edge justification, apply CSS justification paired with explicit right-alignment for the terminal line:

```css
.persian-justified,
.text-justify-fa {
  text-align: justify;
  text-justify: inter-word;
  text-align-last: right;
  -webkit-text-align-last: right;
  text-wrap: pretty;
}
```

This eliminates ragged outer margins across multi-line paragraphs while preventing the terminal line from stretching unnaturally across the column.

## Enforce semantic line breaking and inspect wrap boundaries

Never allow arbitrary browser wrapping to split syntactic units, compound phrases, or parenthetical qualifiers in constrained UI components (such as stat cards, badges, table cells, or form hints).

When a label or title wraps across lines:

1. **Protect atomic syntactic units**:
   Enclose parentheticals (e.g. `(ودیعه ثابت)`), value-unit pairings, or compound nouns in a non-breaking wrapper (`.text-nowrap` or non-breaking spaces `&nbsp;`). When width constrains the line, the entire syntactic unit must wrap together to the subsequent line rather than fracturing into dangling fragments or orphan words (e.g. `سقف جدید اجاره (ودیعه` / `ثابت)` is unacceptable; `سقف جدید اجاره` / `(ودیعه ثابت)` is correct).

2. **Deliberate structural line breaks**:
   Where a two-line layout is expected in compact containers, structure the break deliberately at the natural semantic boundary (e.g. `<span class="text-nowrap">عنوان اول</span><span class="text-nowrap">توضیح تکمیلی</span>`) and enforce uniform vertical line-box height (`min-height`) with centered alignment so adjacent sibling cards maintain a level baseline. Use CSS `text-wrap: balance` on headings to prevent single-word orphans.

3. **Mandatory visual inspection in rendered browser**:
   Automated overflow tests (`scrollWidth <= clientWidth`) only detect physical container bleed; they cannot evaluate syntactic line-break aesthetics. Every multi-line text container must be visually inspected across desktop, tablet, and mobile viewports to verify that line breaks occur at logical syntactic boundaries and produce balanced, legible layouts.

## Acceptance

- Computed styles resolve to the intended interface and display families in every supported locale.
- Mixed-language labels declare their own language and direction and use that language's face.
- Local font requests succeed without contacting a font CDN; licenses and provenance are present.
- Narrow mobile layouts verify single- and multi-line constrained labels after fonts load, in both
  RTL and LTR.
- Persian and Arabic line equalization uses baseline Keshide extension rather than exaggerated inter-word
  whitespace gaps (`space-between`).
- Keshide line equalization enforces a strict two-sided cursive letter whitelist; digits, punctuation, abbreviations, and math operators never receive tatweels.
- Numerical, financial, and algebraic data values are strictly excluded from Keshide elongation.
- Persian and Arabic body copy requiring edge justification enforces `text-align-last: right` to protect terminal lines.
- SEO and crawler indexing remain unaffected: canonical text is preserved via `.sr-only` / `aria-label` and visual
  Keshide lines are isolated behind `aria-hidden="true"`.
- User clipboard operations strip tatweels so copied text pastes cleanly into external applications.
- Dynamic Keshide insertion runs only after `document.fonts.ready`, supports generic line selectors, and preserves `data-original-text` attributes.

- Constrained multi-line titles, badges, and labels break only at intentional semantic boundaries; parentheticals and compound units never fracture across lines.
- Visual browser inspection verifies that no typographic orphans or awkwardly broken phrases exist across supported viewports.
