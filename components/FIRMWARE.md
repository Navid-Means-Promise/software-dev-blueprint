# Firmware

Authoritative for the `firmware/` module — the ESP-family device that is, in effect, another client.
It is the one place on the stack that is **C++17 on ESP-IDF** rather than TypeScript, and it
implements the [semantic device contract](../architecture/CONTRACTS.md) rather than importing any
shared package.

## What it is

An **ESP-IDF** (C++17, CMake) project for an ESP-family microcontroller. It renders the device's
physical output — lights, sound, motion, whatever the product's device does — from _semantic intent_
received over the wire. It shares no code with the TypeScript packages; it shares the **contract**,
which is documented normatively in the project's `docs/` and is authoritative over both the firmware
and the server twin.

## The engine boundary

The heart of the firmware is a small, **allocation-free** engine that:

- separates the device's modes explicitly (e.g. `Off`, `Ambient`, `Select`) — one mode, one clearly
  defined output;
- computes each frame **locally** from the current mode and its parameters, at the device's own clock;
- enforces a **physical safety ceiling** (brightness, current, temperature) that protects the
  hardware regardless of what the wire asks for.

Allocation-free matters on a microcontroller: the render path does no dynamic allocation, so it cannot
fragment the heap or stall mid-animation. The engine is the stable core; transport and provisioning
wrap around it without changing it.

_Reference implementation:_ Mystery's `MysteryLightEngine` separates `Off`, `Ambient`, and `Dice`
modes, lights exactly `face - 1` in dice mode, computes ambient frames locally, and holds a brightness
ceiling that protects the diffuser and power supply.

## Why semantic, not streamed

The device owns its frames. The client sends _what it means_ — a mode and parameters — and the engine
produces the animation on-device. This keeps timing correct under network jitter, keeps the link
cheap, and lets the [server twin](SERVER.md) and unit tests reproduce the device's behavior from the
same contract. Streaming per-frame data to the device is the rejected anti-pattern
([design patterns](../principles/DESIGN_PATTERNS.md)).

## Around the engine

Transport (Wi-Fi provisioning, the HTTP/wire endpoint that receives semantic commands) wraps the
engine without reaching into its render path. When provisioning and networking are added, the engine
boundary does not move — new code parses the wire and hands the engine a mode, nothing more.

## Rules

- C++17 on ESP-IDF; the firmware implements the documented contract and imports no shared package.
- The render engine is allocation-free, mode-separated, and computes frames locally.
- A physical safety ceiling is enforced in the engine, below any wire command.
- The contract in `docs/` is the single source; firmware and the server twin both conform to it.
- Device-behavior changes update the contract document first, then the firmware and the twin.
