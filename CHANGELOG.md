# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-08-30

### Added

- Initial stable release of the `Mrsuner\\ApiKeys` implementation for Laravel 12 and 13.
- Prefix-routed developer keys, secure one-time plaintext delivery, abilities, per-key rate limits, rotation overlap, revocation, expiry, and asynchronous usage tracking.
- Standalone routes and automatic integration with `mrsuner/laravel-api-boilerplate`.

### Notes

- This implementation supersedes the archived `NrmlCo\\LaravelApiKeys` repository. It defines a new namespace and public contract and does not provide an in-place upgrade path from that legacy codebase.

[1.0.0]: https://github.com/mrsuner/laravel-api-keys/releases/tag/v1.0.0
