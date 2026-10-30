# Changelog

All notable changes to this extension are documented here. The format
is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [1.1.6] - 2026-10-30

### Changed
- Released under the MIT License: this module is free and open-source software.

## [1.1.5] - 2026-10-05

### Added
- Pause/play button (44px, labelled) whenever autoplay is on; autoplay also pauses on hover and focus.
- Keyboard support: Left/Right arrow keys move slides while the carousel has focus.

### Fixed
- Adding a new slide now creates it; previously "Slide saved." was shown but nothing was stored.
- The slide form no longer crashes after a rejected save; entered values and images are kept.
- The slide form also accepts image values stored as JSON or as a single array instead of failing.
- First slide image is eager with fetchpriority high at every width, so the active slide is no longer blank while the image lazy-loads.
- Pagination dots have 44px tap targets with a visible focus ring.
- A slider with one slide renders static and centred, without arrows, dots, drag or autoplay.
- Saving a slider with an identifier that is already used shows a clear message.
- New slides and sliders default to Active.
- Clearer help text for Auto-inject on CMS Home Page (uses the homepage_hero slider) and the widget Slider Identifier.
