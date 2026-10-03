# Changelog

All notable changes to this extension are documented here. The format
is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [1.3.2] - 2026-10-03

### Fixed
- With "Remove Native Markup" enabled, native JSON-LD is now removed from target blocks that contain nothing but that JSON-LD (for example the final price block), so product pages no longer carry duplicate structured data.
