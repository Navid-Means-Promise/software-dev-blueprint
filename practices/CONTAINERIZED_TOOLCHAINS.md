# Containerized toolchains

Authoritative for development toolchains that are too invasive, platform-sensitive, legacy, or
specialized to install directly on a workstation. This practice applies to firmware SDKs and may
also apply to native compilers, document generators, emulators, database clients, and similar build
tools. It does not require containerizing ordinary repository dependencies already isolated and
locked by their package manager.

## The guarantee

A project-owned container workflow must provide a **repeatable build environment** and protect the
host from toolchain side effects. A fresh compatible machine can obtain the declared tools and run
the same project commands without installing those tools globally.

Use stronger terms only when they have been demonstrated:

- **Repeatable environment:** the image, tools, and dependency resolutions are immutable or
  integrity-checked inputs.
- **Hermetic build:** the build can access only declared inputs and does not resolve undeclared
  dependencies from the network or host.
- **Reproducible artifact:** independent builds from the same declared inputs produce byte-for-byte
  identical outputs.

A digest-pinned image supports repeatability; it does not alone prove hermeticity or identical
artifacts. Record residual inputs such as CPU architecture, external downloads, timestamps, paths,
and network access rather than overclaiming the guarantee.

## When a container is required

Use a committed OCI-compatible `Containerfile` and runner when a toolchain:

- installs compilers, SDKs, Python packages, system libraries, udev rules, or conflicting runtime
  versions outside the repository;
- depends on a narrow operating-system or architecture combination;
- is unsupported or legacy but still required by a supported target; or
- must behave identically on developer machines and CI.

Keep a normal package-manager workflow for Node dependencies already governed by the root runtime,
exact package-manager version, and lockfile. A container is a boundary for a named risk, not a goal
in itself.

## Immutable inputs and dependency resolution

- Pin a trusted, fully qualified base image by immutable manifest digest; retain a human-readable tag
  beside the digest. Update it through a reviewed dependency change so security fixes are not frozen
  indefinitely.
- Keep the pinned SDK/runtime on an upstream-supported release line. A still-resolvable digest is not
  acceptable evidence of maintenance after that line reaches end of life; migrate and revalidate the
  complete target matrix before support ends.
- Pin downloaded SDKs and source repositories by immutable commit or content digest. A mutable tag or
  branch may be displayed as a version label but is not the integrity control. Verify archives
  before extraction and reject a mismatch.
- Commit package/component lockfiles when they contain portable registry or Git resolutions. Keep
  target-dependent locks separate. Generated dependency directories and download caches are not
  source and stay untracked.
- Pin compatibility packages and version patches. Apply a patch only to the expected upstream
  content and fail when its preimage or postcondition does not match.
- Minimize inherited packages to the target's actual build needs. Remove an unused native SDK or
  development-header family only after clean builds of the full target matrix. Install security
  updates at reviewed exact versions; do not hide a mutable `upgrade`/`dist-upgrade` inside the
  image build.
- Separate **dependency update/setup** from the normal **build**. The update path may use the network
  to populate a verified cache and intentionally update locks; a normal build consumes existing
  locks and must not silently select newer dependencies.
- Never execute an unverified network response as code (for example, `curl ... | sh`). Installer
  artifacts are downloaded, integrity-checked, then executed from a known path.

## Host isolation

Run development containers rootless and as the invoking user where supported. Preserve the runtime's
default confinement and add `no-new-privileges` and dropped capabilities when the toolchain permits.
Do not use `--privileged`, the host PID/network namespace, a container-engine socket, or a device
mount for compilation. Flashing or hardware tests are separate opt-in commands that expose only the
specific device they require.

Mount the repository source read-only. Add narrowly scoped writable mounts only for declared build
outputs and disposable caches. Never mount the host home directory, credential stores, SSH agent,
cloud configuration, or unrelated paths. Canonicalize and validate every user-overridable host path
before creating or mounting it; output paths stay inside the repository and cache paths stay inside
one documented user-data root.

Do not inherit host proxy variables, credentials, or ambient environment by default. If network
access or a proxy is required during setup, pass it explicitly to that operation and document the
trust implication. Build secrets use the engine's ephemeral secret mount and never a build argument,
image layer, committed file, or general environment dump.

On SELinux hosts, prefer project-owned mount locations and the narrow sharing label required by that
location. Do not recursively relabel the home directory or disable container separation merely to
make a broad mount work.

## Project interface

The repository root owns stable package-manager scripts; contributors do not need to memorize engine
flags or invoke SDK commands directly. For a subsystem named `firmware`, expose the applicable subset:

```text
firmware:container:image       build every supported target image
firmware:container:setup       populate integrity-checked external assets
firmware:container:verify      assert the documented runtime isolation guardrails
firmware:build:<target>        compile one target with its isolated toolchain
firmware:build:all             compile the complete supported target matrix
firmware:container:shell:*     optional diagnostic shell with source still read-only
firmware:collect               validate and stage release/flasher artifacts
```

Use the same vocabulary for other subsystems (`android:container:image`,
`android:container:setup`, `android:container:verify`, and `android:build:debug`/`release`) rather
than hiding a host SDK invocation behind a generic build script.

The committed runner resolves repository paths from its own location, uses target-specific build
directories, cleans failed staging areas, replaces caches atomically, propagates failures, and has a
usage error for unsupported targets. Docker- and Podman-compatible image syntax is preferred;
projects may standardize on rootless Podman for execution when that is their tested security model.

## Verification and release evidence

- Build every supported target from a clean output directory. Also test a cold setup and a warm
  locked build; if hermeticity is claimed, the warm build passes with networking disabled.
- Test runner guardrails: rejected paths, unsupported targets, checksum failure, and no shared build
  directory between incompatible targets.
- Produce artifact sizes and cryptographic checksums. Keep generated binaries out of source control
  unless the repository explicitly distributes them.
- Release automation records the source revision, target, lock/toolchain identities, command, and
  artifact digest. Use platform-generated signed provenance and verify it when the product's release
  risk requires supply-chain attestation; a local metadata file must not be described as trusted
  provenance.
- Scan maintained image/package inputs on a defined cadence and whenever their locks change. An
  immutable pin provides an audit trail, not an exemption from updates. Follow the isolated
  [security-scanning and SBOM module](../security/README.md); never expose the engine socket merely
  to scan an image.

## Rules

- Containerize invasive or platform-sensitive toolchains; do not mutate the workstation to make a
  build pass.
- Pin and verify every externally obtained build input, and separate updates from locked builds.
- Source is read-only; only explicit output and cache directories are writable.
- Compilation has no hardware privileges or ambient host secrets.
- State exactly which repeatability, hermeticity, and reproducibility properties have been tested.

## Standards basis

- [OCI Image Format](https://github.com/opencontainers/image-spec/blob/main/spec.md) — portable,
  content-addressed image structure and digests
- [Docker build best practices](https://docs.docker.com/build/building/best-practices/) — trusted
  minimal bases, digest pinning, ephemeral containers, and controlled cache refresh
- [Podman run](https://docs.podman.io/en/latest/markdown/podman-run.1.html) and
  [build](https://docs.podman.io/en/stable/markdown/podman-build.1.html) references — rootless user
  namespaces, confinement, mounts, proxy propagation, and build secrets
- [Reproducible Builds: `SOURCE_DATE_EPOCH`](https://reproducible-builds.org/docs/source-date-epoch/) —
  normalized time as one prerequisite for identical artifacts
- [SLSA build track](https://slsa.dev/spec/v1.2/build-track-basics) — provenance and increasing build
  platform assurance; use its level names only when every requirement is met
- [ESP-IDF component locks](https://docs.espressif.com/projects/idf-component-manager/en/latest/reference/dependencies_lock.html) —
  exact managed-component resolution and portable-lock version-control guidance
