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
rendered.

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

1. **Identification of valid cursive join points**:
   Insert Keshide only after characters that naturally connect to the following letter in Arabic/Persian
   script. Never insert Keshide after non-connecting characters (`ا`, `د`, `ذ`, `ر`, `ز`, `ژ`, `و`, `آ`)
   or whitespace.

2. **Calligraphic priority scoring**:
   Prioritize elongation points according to classical aesthetic hierarchy:
   - High priority: Medial and initial `ش` and `س` before final or middle characters (e.g. `مشـاوره`, `توسـط`).
   - Medium priority: Connecting ascenders and descenders like `قـ`, `کـ`, `گـ`, `پـ`, `تـ`, `بـ`.
   - Balanced distribution: Distribute multiple tatweels across eligible words rather than concentrating
     extreme elongation on a single glyph.

3. **Rendered geometry measurement**:
   Do not guess character counts. Use an offscreen canvas context (`ctx.measureText`) configured with the
   active computed font (`window.getComputedStyle`) after `document.fonts.ready` resolves. Measure the
   widest reference line and calculate the exact pixel deficit for shorter lines.

4. **SEO integrity, screen readers, and semantic text preservation**:
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

5. **Progressive enhancement**:
   - Provide pre-balanced static markup with baseline Keshides so initial render paints symmetrically without layout
     shifts (CLS) before JavaScript executes.
   - Attach runtime equalization declaratively via `[data-persian-align]`.
   - Debounce window resize handlers to recalculate when viewport dimensions or font scale factors change.

## Acceptance

- Computed styles resolve to the intended interface and display families in every supported locale.
- Mixed-language labels declare their own language and direction and use that language's face.
- Local font requests succeed without contacting a font CDN; licenses and provenance are present.
- Narrow mobile layouts verify single- and multi-line constrained labels after fonts load, in both
  RTL and LTR.
- Persian and Arabic line equalization uses baseline Keshide extension rather than exaggerated inter-word
  whitespace gaps (`space-between`).
- SEO and crawler indexing remain unaffected: canonical text is preserved via `.sr-only` / `aria-label` and visual
  Keshide lines are isolated behind `aria-hidden="true"`.
- User clipboard operations strip tatweels so copied text pastes cleanly into external applications.
- Dynamic Keshide insertion runs only after `document.fonts.ready` and preserves `data-original-text` attributes.

