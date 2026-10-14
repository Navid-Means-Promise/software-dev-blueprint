# UI performance

Authoritative for animation continuity and rendering budgets. On this stack these are **acceptance
criteria** enforced from the first commit of any animated surface, not a later optimization pass
([development principles](../principles/DEVELOPMENT_PRINCIPLES.md) §6).

## The rule

Perpetual HTML effects may animate **only `transform` and/or `opacity`.** A GPU canvas
(WebGL/WebGPU) is the explicit exception: it must own its render loop, adapt its pixel and frame
budgets, and stop whenever it is not visible or motion has been reduced. State-driven animation
passes through one transition gate and hands off from the rendered pose without exposing a wrong or
stale frame.

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

## State-transition continuity

State-driven GUI animation is a handoff between visual timelines, not a reason to replace one
animation abruptly. Every animated surface has one shared, typed transition gate/coordinator for
state changes. Renderers and CSS selectors do not independently switch timelines around it.

The gate must:

1. Capture the rendered transform/opacity of every coordinated layer immediately before the state
   update is rendered.
2. Cancel any older handoff and invalidate its completion callback when a newer state arrives.
3. Pause the destination timeline at its known first frame, then interpolate from the captured pose
   using compositor-safe properties (`transform` and/or `opacity`) with a deliberate, bounded
   easing and duration.
4. Resume the destination timeline only after all coordinated handoff animations reach their target;
   restart it from that same first frame so the completion cannot flash or jump to a different frame.
5. Treat the initial render as a static baseline, and bypass the handoff while reduced motion is
   enabled. The destination state still renders and remains understandable.
6. Distinguish destination timelines from the gate's own handoff animations. Pause, resume, cancel,
   and clean up only animations the coordinator owns; unrelated animation lifecycles stay intact.

No state change may expose a stale frame, jump to an unrelated point in a cycle, or leave parallel
layers out of phase. Rapid state changes are expected input and must converge to the latest state.
The coordinator owns cancellation and cleanup so an abandoned transition cannot resume an obsolete
timeline or retain an animation object.

Acceptance tests exercise every state edge (including rapid reversals), verify that the transition
uses only the compositor budget, assert that the latest state wins, and confirm a static/reduced-
motion path without waiting on wall-clock sleeps.

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

## Rendering-path policy and inspection

An optional rich scene that has a complete non-GPU path declares one production policy in a shared,
typed configuration: `automatic` selects the GPU path only from the centralized hardware-capability
profile, while `force-fallback` always selects the non-GPU path. Record the policy per scene so an
unfinished renderer can remain available for development without accidentally becoming a
production path. Components and renderers consume the resolved path; they do not repeat capability
detection or read environment flags themselves.

Keep launch-only inspection overrides separate from production policy. Provide one development
entry point that makes optional GPU scenes inspectable with pointer camera controls and another that
forces every fallback path. Inspection controls request frames only while input changes the camera;
they do not turn a settled scene into a perpetual render loop. Validate override values at startup,
exclude them from ordinary production launches, and exercise both entries in browser acceptance
tests. This makes the expensive and fallback paths continuously reviewable even while production
deliberately ships only one of them.

## Reusable rendering-architecture techniques

Beyond the performance budget, these design techniques keep a rich or 3D renderer **reusable and
accessible** across projects — they are what let one renderer serve more than one product.

- **Decouple the renderer from the domain.** A renderer takes a typed _settings_ object (image URL,
  colour palette, supported effects, intensity) rather than a domain entity, and validates its effect
  inputs. Any product that can produce those settings can reuse the renderer unchanged.
- **Share one implementation for related visuals, and one module for a heavy dependency.** Cutting
  several 3D objects from one shared material — and importing a heavy 3D library through one shared
  module — lets the bundler hoist that library into a **single chunk** every renderer imports, rather
  than duplicating it. This is [DRY](../principles/DEVELOPMENT_PRINCIPLES.md) meeting the chunk budget.
- **Keep HTML controls real over a 3D scene.** Where an interactive control sits on a decorative 3D
  scene, keep the accessible HTML element (localized label, keyboard focus, screen-reader name) and
  let pointer events fall through to the canvas, which raycasts the scene beneath. Selection is then
  identical whether or not the scene rendered, and accessibility never depends on the renderer.
- **Render domain text into a texture, not an overlay.** A label that belongs _inside_ the scene (a
  title down a 3D spine) is painted into a canvas texture from the localized domain text, rather than
  floated as an HTML card over the canvas where it could never sit correctly in the 3D space.

_Reference implementation:_ Mystery's crystal-orb and library-bookcase renderers share one wood
material and one hoisted 3D-engine chunk, take typed settings rather than scene objects, project HTML
controls onto a raycast 3D bookcase, and paint book titles into spine textures from localized text.

## Automated compositor-budget enforcement

Every addition or change to a perpetual HTML animation carries a browser acceptance audit in the
same change. Run the audit from the animated surface's top-level host after each relevant state has
settled so state-specific descendants and pseudo-elements stay inside the inspected boundary.

The audit must:

1. Exercise every reachable state that mounts different animations, including transient loading,
   recording, thinking, offline, and error states rather than only the default state.
2. Inspect all perpetual CSS animations in the host subtree and reject SVG targets or keyframes that
   contain properties other than `transform` and `opacity`.
3. Assert the expected animation names or bounded counts in each state, so an empty, hidden, or
   incorrectly scoped audit cannot pass vacuously.
4. Cover every introduced or changed infinite animation in browser acceptance, scheduled through
   [pre-merge verification](../principles/WORKING_AGREEMENT.md#pre-merge-verification).

This deterministic property audit complements profiling; it does not replace it. Run paint-flashing
and frame-pacing inspection whenever an animated surface changes and periodically refresh its dated
performance baseline. Keep trace-based measurements out of the ordinary correctness gate when their
platform timing would make that gate nondeterministic.

## Acceptance method

### Coverage and budgets

- Test at real mobile widths (e.g. ~393 px and ~320 px) in **every supported locale** and both text
  directions; horizontal layout must not overflow. Apply the same performance criteria in each
  locale; fonts, text wrapping, and direction are scenario inputs, not different standards.
- Under CPU throttling (e.g. 6×) at a realistic device scale factor, a settled sample averages a high
  frame rate (target ≥ 55 FPS) with no frame over ~33 ms outside deliberate, transient transitions.
- Resting paint-flashing shows no continuous repaint.
- Focus targets, clicks, scrolling, and screen-reader labels stay stable during ambience.
- Every optional GPU scene is exercised once through its production policy, forced-fallback launch,
  and inspection launch.
- Real GPU/thermal behavior is ultimately confirmed on a physical device, not only in a desktop
  browser or emulator.

### Match the metric to the rendering path

Declare what a measurement proves before choosing its probe. Compositor animation can continue while
main-thread JavaScript is blocked; a delayed `requestAnimationFrame` callback therefore does not,
by itself, prove that a displayed animation froze. Conversely, smooth compositor motion does not
prove responsive JavaScript interactions. See Chrome's
[rendering-pipeline explanation](https://developer.chrome.com/blog/inside-browser-part3/).

| Subject | Required evidence | Limit of the claim |
| --- | --- | --- |
| Compositor HTML/CSS motion | Passive browser presentation feedback, or an equivalent validated frame-presentation signal, while the intended surface animates | Callback timing, paint counts, or frame submission alone do not establish presentation cadence |
| JavaScript-driven DOM/canvas updates or gestures | Application update/callback cadence and work duration; presentation evidence when claiming displayed FPS | `requestAnimationFrame` measures scheduling opportunities; it does not prove each callback produced a distinct displayed update |
| Interaction responsiveness | Representative input-to-response timing and main-thread work, including long tasks and garbage collection where relevant | Animation FPS alone cannot establish response latency |

Zero Paint events is a separate repaint result, not evidence of cheap compositing or smooth motion.
Large transparent surfaces, excess layers, clips, and overdraw can still cost rendering work when
only `transform`/`opacity` animate. Inspect those costs before changing the intended appearance or
timing. Validate visual continuity and effect coverage after an optimization.

### Validate the measurement

The measurement implementation is part of the verification system and must not pass vacuously:

1. Establish scenario readiness, including critical fonts/assets and the intended active animation
   or renderer. Check visibility, motion preference, lifecycle, and expected running animations
   before and after capture. Confirm the target actually changes through scoped content evidence or
   a separate bounded visual check. Browser-wide frame activity alone cannot prove the target
   updated; scope the signal where possible and record any remaining attribution limit.
2. Document the signal's meaning for the tested browser/version: event schema, clock, timestamp
   units, and intended display/frame stream. A completion or submission timestamp is not implicitly
   a presentation timestamp. Validate and explicitly select the intended stream; reject malformed,
   absent, insufficient, or unresolved ambiguous evidence. Never silently substitute callback FPS
   when presentation feedback is unavailable. Deduplicate repeated feedback for the same
   presentation without removing distinct frames or gaps.
3. Define readiness, warm-up, capture duration, sample count/window, and any deliberate-transition
   exclusions before capture. Keep every slow interval inside that window, including intervals
   coinciding with garbage collection. Do not search for a faster window or discard outliers to
   pass. Calculate average FPS from interval count divided by total elapsed seconds, and report the
   worst interval separately; averaging instantaneous FPS can conceal stalls.
4. Minimize observer work. For independent CSS motion, use passive capture with boundaries controlled
   outside the page; do not inject a per-frame JavaScript loop, style/layout reads, or screenshot
   polling solely to count frames. Check for probe-induced work with a minimally instrumented
   diagnostic comparison when introducing or changing a probe. Tracing itself has overhead; record
   capture categories/options and keep them consistent across compared runs.
5. Give metric parsers and sample selection deterministic fixture tests: regular pacing, a genuine
   long presentation gap, repeated/out-of-order feedback, malformed or insufficient data, ambiguous
   streams, and a later faster window. Cover delayed JavaScript with steady presentation separately
   from an actual presentation stall. Missing evidence must fail or remain explicitly unverified;
   it cannot become zero dropped frames or a passing default. Execute these tests through
   [pre-merge verification](../principles/WORKING_AGREEMENT.md#pre-merge-verification).

Browser trace events are version-dependent implementation details, not a portable Web API. When
using them, keep a versioned source or specification reference for their semantics and representative
fixtures so a browser upgrade cannot silently change the metric.

### Evidence and diagnosis

Keep a dated baseline with the candidate/base revisions, commands, scenario/state and locale,
measurement method/version, sample policy, average FPS, worst interval, budget violations, paint
counts, and applicable drawing-buffer size. Record build mode, browser/version, headless/headed mode,
viewport, device scale, target refresh rate, CPU-throttle setting/scope, launch/capture options, and
actual rendering backend/device status. An accelerated-layer label is not proof of hardware GPU
compositing.

Run timing profiles serially without competing test workers or known heavy workloads. Record host
CPU/load and relevant power/thermal conditions where observable, including before/after load when
diagnosing scheduling noise. CPU throttling is relative to the host and does not reproduce a phone's
CPU/GPU architecture; see the
[DevTools CPU-throttling reference](https://developer.chrome.com/docs/devtools/performance/reference#cpu-throttle).
Software-composited DOM animation can have a valid, explicitly labelled presentation baseline. For
a GPU canvas, software rendering verifies only its static fallback and lifecycle; animated-shader
claims require a run that rejects software rendering. Neither desktop path substitutes for physical
device GPU/thermal verification.

Retain raw traces and scenario metadata before parsing or asserting budgets, including failed
captures. Report failed attempts and the reason for a rerun; never retry until a passing run erases
the failure. Correlate slow intervals with script work, garbage collection, layout/paint, compositor
work, and host scheduling before assigning a cause. A JavaScript pause is real responsiveness
evidence even when presentation stays smooth. A failure in one locale identifies a scenario to
investigate; it does not establish language as the cause.

Compare revisions under the same probe, scenario, sampling policy, and controlled conditions. A
probe correction establishes a new baseline; old callback FPS and new presentation FPS cannot
quantify an animation speedup. Separate confirmed causes from hypotheses and unresolved attribution.
Keep the acceptance budgets when correcting a probe; if evidence is invalid or the host is
contended, resolve that uncertainty before claiming the animation is defective or reducing effects
to make a gate pass. A valid budget failure still requires implementation repair or
the renderer's declared adaptive policy.

## Rules

- Perpetual HTML animation is `transform`/`opacity` only; a GPU canvas is the adaptive, pausable
  exception with a static fallback.
- State-driven animation passes through one transition gate per animated surface; it captures the
  current pose, cancels stale handoffs, synchronizes coordinated layers, and never jumps to an
  unrelated frame.
- Reduced motion removes ambience while preserving meaning.
- Cosmetic animation stays inside its renderer and never triggers re-renders or store writes.
- Every animated surface has a dated, condition-labelled performance baseline.
