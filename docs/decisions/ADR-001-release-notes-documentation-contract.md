# ADR-001: Treat Release Notes as a Public Documentation Contract

## Status
Accepted

## Date
2026-05-29

## Context
The generated HTML documentation previously exposed a sidebar item called "Breaking Changes" while the section itself only covered v4.0.0. The package has since shipped releases through v6.0.2, including output-field semantics, JME native ephemeris migration, and expanded Vaasa/Yoga/Moon visibility payloads.

Consumers use the documentation to decide whether their integrations can upgrade safely. If the page only documents one historical breaking release, it can hide newer compatibility concerns such as current-time versus sunrise-based fields.

## Decision
Use one stable section, `#breaking-changes`, for release and upgrade notes so existing inbound links keep working. Rename the visible navigation label to "Release Notes" and make the section cover the full published release line from v1.0.0 through the latest tag.

The release list should be compact, responsive, and semantic:

- use an ordered list for published versions
- keep the latest release visibly marked
- keep version titles and integration notes short
- preserve dedicated upgrade-note subsections for v6.x, v5.x, and v4.0.0 compatibility notes

## Alternatives Considered

### Create a New `#release-notes` Anchor
This would match the visible section name more directly, but it would break existing links to `#breaking-changes`.

Rejected because documentation anchors are part of the public interface.

### Keep Only v4.0.0 Breaking Changes
This keeps the section shorter, but it omits newer compatibility-relevant releases and makes the docs look stale.

Rejected because current users need upgrade context for v5.x and v6.x.

### Put Every GitHub Release Body Inline
This would be comprehensive, but it would make the page too long and hard to scan.

Rejected because GitHub remains the source for full release bodies; this docs page should provide integration-focused summaries.

## Consequences
- Existing `#breaking-changes` links continue to work.
- The visible navigation now describes the broader section accurately.
- Future release docs should update the compact list and add upgrade subsections only when consumer behavior changes.
- The latest release marker must be moved when a new tag becomes latest.
