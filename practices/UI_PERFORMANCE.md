# UI performance

Authoritative for animation and rendering budgets. On this stack these are **acceptance criteria**
enforced from the first commit of any animated surface, not a later optimization pass
([development principles](../principles/DEVELOPMENT_PRINCIPLES.md) §6).

## The rule

Perpetual HTML effects may animate **only `transform` and/or `opacity`.** A GPU canvas
(WebGL/WebGPU) is the explicit exception: it must own its render loop, adapt its pixel and frame
budgets, and stop whenever it is not visible or motion has been reduced.

## Requirements for compositor (HTML/CSS) effects

1. Do not animate SVG internals. Put moving mist, motes, rays, and glints in pointer-inert HTML
   layers, not inside an animating SVG.
2. Infinite keyframes change **only** `transform` and `opacity` — never filters, colors, gradient
   positions, size, layout coordinates, or shadows.
3. No `filter`, animated `drop-shadow`, or `backdrop-filter`. Bake the look into static gradients and
   shadows.
4. No JavaScript intervals or reactive re-renders for cosmetics. Generate deterministic particle
   placement once and let CSS animate it. Cosmetic animation never drives
   [store](../architecture/STATE_MANAGEMENT.md) updates.
5. Pause or remove effects that cannot be seen. Nothing animates behind an opaque covering screen.
6. Keep interactive bounds motionless. Ambience is `pointer-events: none` and stays outside hit
   targets, so it never moves a button under the user's finger.
7. Respect `prefers-reduced-motion`: remove ambient movement while preserving meaning (a state
   change, a result) that the user still needs.

## Requirements for a GPU canvas

8. A WebGL/WebGPU renderer caps device pixel ratio and drawing-buffer area, **adapts quality** when
   it misses its frame budget, handles context loss, disposes GPU resources, respects reduced motion,
   and retains an HTML fallback aligned to the canvas bounds.
9. Pause the render loop outside the viewport and while the document is hidden. A detected **software**
   renderer gets one high-quality static frame instead of spending CPU on perpetual shading.
10. Keep animation time **inside** the renderer. Interior frames must not cause UI-framework state
    updates or DOM re-renders; the scene does not react to pointer movement with layout work.
11. A scene with nothing to animate renders **on demand only** — when built, resized, or interacted
    with — and never holds a loop open. This is what makes an expensive one-off scene affordable.
12. Load split GPU-renderer code and the startup-critical content concurrently behind the shared
    [readiness gate](../components/SHARED_UI.md). Prefer code-native loader art so the readiness screen
    adds no extra network request.
13. Keep a deliberately-split large chunk (e.g. a 3D engine shared by two renderers) under a
    **consciously set** bundle budget that acts as a review guardrail. Crossing it triggers a new
    bundle inspection; it is never silenced. See [monorepo](../components/MONOREPO.md).

## Acceptance method

- Test at real mobile widths (e.g. ~393 px and ~320 px) in **every supported locale** and both text
  directions; horizontal layout must not overflow.
- Under CPU throttling (e.g. 6×) at a realistic device scale factor, a settled sample averages a high
  frame rate (target ≥ 55 FPS) with no frame over ~33 ms outside deliberate, transient transitions.
- Resting paint-flashing shows no continuous repaint.
- Focus targets, clicks, scrolling, and screen-reader labels stay stable during ambience.
- **Record the numbers.** Keep a dated performance baseline (average FPS, worst frame, buffer size,
  conditions) so a later change has a regression reference. Distinguish a hardware-GPU measurement
  from a software-renderer measurement — the latter validates the *fallback and lifecycle*, not
  animated-shader performance, and the two must never be reported as the same thing.
- Real GPU/thermal behavior is ultimately confirmed on a physical device, not only in a desktop
  browser or emulator.

## Rules

- Perpetual HTML animation is `transform`/`opacity` only; a GPU canvas is the adaptive, pausable
  exception with a static fallback.
- Reduced motion removes ambience while preserving meaning.
- Cosmetic animation stays inside its renderer and never triggers re-renders or store writes.
- Every animated surface has a dated, condition-labelled performance baseline.
