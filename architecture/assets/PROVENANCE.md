# Architecture asset provenance

## `ai-agent-runtime-workflow.png`

- **Origin:** OpenAI built-in image generation through the Codex `imagegen` skill on 2026-08-16.
- **Source inputs:** No source or reference images.
- **License expectation:** Generated specifically for this repository with no supplied third-party
  visual material; use remains subject to the applicable OpenAI service terms.
- **Edits:** Copied from the generated PNG without post-processing.
- **Review status:** Reviewed for the eight unique stages, exact labels, arrow direction,
  clarification/prediction distinction, contrast, and absence of logos or watermarks. The Mermaid
  workflow in [`AI_SUBSYSTEM.md`](../AI_SUBSYSTEM.md#end-to-end-runtime-workflow) remains normative.

### Generation prompt

```text
Use case: infographic-diagram
Asset type: project architecture documentation image
Primary request: Create a polished, highly legible landscape 16:9 software architecture infographic that summarizes the canonical AI agent runtime workflow.
Scene/backdrop: clean dark navy background with subtle panel separation and generous empty space.
Style/medium: modern flat vector-like technical infographic, restrained and professional.
Composition/framing: eight numbered rounded cards in one unmistakable left-to-right flow that wraps neatly once if necessary. Use clear directional arrows. Keep every label horizontal.
Color palette: dark navy, white text, turquoise for trusted application stages, purple for the model stage, amber for validation/decisions.
Text (verbatim):
Title: "AI Agent Runtime Workflow"
Card 1 heading: "USER MESSAGE"
Card 1 body: "Typed text or voice transcript"
Card 2 heading: "TRUSTED SERVER"
Card 2 body: "Authenticate · derive scope · persist message"
Card 3 heading: "CONTEXT ASSEMBLY"
Card 3 body: "Recent conversation · canonical memory · domain state"
Card 4 heading: "MODEL"
Card 4 body: "Untrusted interpreter"
Card 5 heading: "VALIDATE"
Card 5 body: "Decode · authorize · bound reads"
Card 6 heading: "COMMIT"
Card 6 body: "Accepted canonical effects only"
Card 7 heading: "PERSIST"
Card 7 body: "Assistant reply · truthful receipts"
Card 8 heading: "DELIVER"
Card 8 body: "UI text · optional speech"
Loop label from MODEL back toward CONTEXT ASSEMBLY: "In-call retrieval"
Dotted loop label from PERSIST back toward CONTEXT ASSEMBLY: "Next user turn"
Small capsule on the dotted loop: "Short-lived predictive query — never on clarification"
Small callout below CONTEXT ASSEMBLY: "Clarification continuity: recent conversation"
Footer: "The model interprets. The application authorizes and commits."
Constraints: exact spelling and punctuation; large readable typography; strong contrast; consistent card sizes; arrows must not cross labels; all eight cards must appear exactly once; no extra stages.
Avoid: code snippets, paragraphs, decorative people, mascots, logos, watermarks, tiny text, gradients behind text, pseudo-3D, clutter, invented labels.
```
