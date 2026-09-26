# Security scanning and SBOMs

Authoritative for repository, dependency, and project-owned container-image security inspection.
Security tooling is itself a supply-chain input and follows the same immutable, host-safe rules as
every other [containerized toolchain](../practices/CONTAINERIZED_TOOLCHAINS.md).

## Security document map

- [Session management](SESSION_MANAGEMENT.md) — lifetime, rotation, principal freshness, and backing
  stores
- [Untrusted file handling](UNTRUSTED_FILE_HANDLING.md) — upload validation, private storage,
  authorized retrieval, and failure consistency
- This document — repository, dependency, image, secret, and SBOM inspection

## Module boundary

Keep a project's security implementation under one dedicated directory such as
`tooling/security/`. That directory owns the runner, auditor `Containerfile`, entrypoint, immutable
tool pins, scanner configuration, exception policy, and detailed operating guide. Outside the module,
keep only:

- thin root `security:*` package scripts as its stable public interface;
- an ignored report-output entry; and
- concise documentation links.

Do not spread scanner internals through application packages, general build scripts, workspace
configuration, or unrelated development documentation. The blueprint follows the same rule: this
directory is authoritative for security-scanning policy, while other documents only link here where
the boundary is relevant.

## Scanner trust and updates

- Pin the scanner image by an immutable multi-platform digest and retain its readable release tag.
  Never run `latest`, a downloaded installer, or a self-updated binary as part of a security gate.
- Before a networked audit, compare the committed scanner version with the upstream project's latest
  stable release and print both values. An out-of-date tool produces a prominent warning; CI may
  fail according to a declared maximum-age policy.
- Updating the scanner is an explicit reviewed dependency change: change the version and digest,
  rebuild it, scan the new image with the previously trusted scanner, then run the complete audit.
  A scan must not silently replace the tool that defines its own result.
- Refresh vulnerability databases and policy/check bundles in a separate network-enabled command.
  Actual repository and image scans use the prepared cache with networking disabled. Record scanner
  and database metadata with the reports.

This separation matters because scanner registries and release channels can themselves be
compromised. Immutable pinning preserves the reviewed tool while the preflight still makes staleness
visible.

## Container-image boundary

Do not mount a Docker/Podman socket, container storage directory, or host root into the auditor.
Those mounts grant far more authority than reading one image. The host runner exports each explicitly
allow-listed project image as a temporary OCI image layout, mounts that layout read-only into the auditor,
and removes it after its reports are complete.

Run the auditor rootless as the invoking user, with a read-only root filesystem, dropped capabilities,
`no-new-privileges`, no devices, and no network during analysis. Give it only:

- one read-only repository or OCI image layout;
- one writable scanner database/cache directory under the documented user-data root; and
- one writable report directory inside the repository's ignored build output.

Scan the auditor image too. New project images are added to the explicit allow-list when their build
workflow is introduced; never discover and scan unrelated user images by a broad name or engine-wide
query.

## Required reports and gates

For every project image:

- write a complete machine-readable vulnerability report;
- write a human-readable policy report and fail the gate for the declared severities;
- generate a CycloneDX JSON SBOM containing OS and language packages; and
- retain the image identity/digest and scanner version beside the reports.

For the repository, scan supported lockfiles and manifests for known vulnerabilities, inspect
committed infrastructure/container configuration for misconfiguration, scan tracked source for
secrets, and generate a repository CycloneDX SBOM. Include development dependencies because build
and test tools execute code. Exclude generated dependency directories, build output, VCS internals,
test reports, and the security-report directory itself.

The default release gate is all fixed and unfixed `HIGH` and `CRITICAL` findings. A project may tune
that threshold based on threat model, but every suppression is narrow, documented with rationale and
expiry, and stored in the scanner's supported ignore format. Reports are CI artifacts rather than
committed source.

An SBOM is inventory, not proof of safety. A zero-finding scan means the selected databases and
detectors found no matching known issue; it does not establish absence of vulnerabilities or malicious
code.

## Root scripts

Expose stable commands at the repository root. Use `scan` for focused, offline checks and `audit` for
the complete networked release gate:

```text
security:container:image       build the digest-pinned auditor
security:container:verify      dynamically verify the auditor's containment controls
security:tools:check-updates   compare the committed tool with the latest stable release
security:db:update             refresh scanner databases/check bundles
security:scan:dependencies     scan supported manifests and lockfiles
security:scan:configuration    scan committed infrastructure configuration
security:scan:secrets          scan source for committed secrets
security:scan:repository       run every repository scanner
security:scan:containers       scan every explicitly declared project image
security:sbom:repository       generate the repository CycloneDX SBOM
security:sbom:containers       generate every declared image SBOM
security:sbom:all              generate repository and container CycloneDX SBOMs
security:audit                 preflight, isolation verification, database refresh, all scans, and SBOMs
```

The complete audit continues through all targets so every report is produced, then returns failure if
any target violates policy. Missing declared images, a stale/missing database, malformed reports, or
an unavailable update service fail closed unless an explicitly named offline workflow is being used.

Security scans and SBOM generation are deliberate release/cadence gates, not inner-loop compilation
steps. Do not add them to the default `check`, build, typecheck, lint, unit-test, or watch command.
Release CI runs the complete audit; focused offline commands are for diagnosis and targeted changes.

## Standards basis

- [Trivy image scanning](https://trivy.dev/docs/latest/guide/references/configuration/cli/trivy_image/) —
  OCI/archive input, severity filtering, machine-readable reports, and policy exit codes
- [Trivy SBOM](https://trivy.dev/docs/dev/guide/supply-chain/sbom/) — CycloneDX and SPDX generation
  for images and filesystems
- [Trivy databases](https://trivy.dev/docs/dev/configuration/db/) — separate database-only refresh and
  skip-update controls
- [Trivy 2026 security incident](https://github.com/aquasecurity/trivy/discussions/10425) — mutable
  release channels were affected while digest-pinned images were outside the affected resolution path
- [CycloneDX specification](https://cyclonedx.org/specification/overview/) — standardized component
  inventory and vulnerability-exchange model
