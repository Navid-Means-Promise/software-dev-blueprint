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

## Acceptance

- Computed styles resolve to the intended interface and display families in every supported locale.
- Mixed-language labels declare their own language and direction and use that language's face.
- Local font requests succeed without contacting a font CDN; licenses and provenance are present.
- Narrow mobile layouts verify single- and multi-line constrained labels after fonts load, in both
  RTL and LTR.
