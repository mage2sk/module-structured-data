# Changelog

## 1.0.12 - 2026-06-18

- Rewrote README to match gold template structure: added Quick Answer block, Who Is It For section, grouped Key Features, full Configuration table sourced from system.xml, FAQ section, and Quick Links table. Updated canonical to the live product page. Removed old commercemarketplace.adobe.com links and replaced them with the live product page URL.

## 1.0.10 - 2026-05-13

- Fix BlogDetector dropping every post on blog modules whose post table uses an active-flag column other than `is_active`. The detector hard-coded `addFieldToFilter('is_active', 1)`, which raised `SQLSTATE[42S22]: Column not found: 'is_active' in 'where clause'` on tables exposing `enabled` or `status` instead. The exception was swallowed by the surrounding try/catch and silently produced zero structured-data entries (and zero rows for the XML sitemap blog contributor that consumes this detector).
- `BlogDetector::resolveActiveColumn()` now introspects the collection's main table via `describeTable()` and picks whichever of `is_active`, `enabled`, or `status` actually exists. When none are present, the filter is skipped rather than producing an invalid query — better to over-include than to drop every post.
- `ResourceConnection` is now injected into `BlogDetector` to support the schema lookup.
