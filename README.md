<!-- SEO Meta -->
<!--
  Title: Panth Hero Slider for Magento 2 | Center-Focused Homepage Carousel (Hyva + Luma) | Panth Infotech
  Description: Panth Hero Slider is a center-focused, autoplay homepage carousel for Magento 2. Three-up Splide.js slider with peek of neighbouring slides, per-slide CTA button styling, separate desktop/mobile artwork, and zero theme coupling — works on Hyva and Luma. Compatible with Magento 2.4.4 - 2.4.8 and PHP 8.1 - 8.4.
  Keywords: magento 2 hero slider, magento 2 homepage carousel, splide slider magento, hyva hero slider, luma hero slider, center focus carousel, magento 2.4.8 slider, panth hero slider
  Author: Kishan Savaliya (Panth Infotech)
  Canonical: https://github.com/mage2sk/module-hero-slider
-->

# Panth Hero Slider for Magento 2 — Center-Focused Homepage Carousel (Hyva + Luma) | Panth Infotech

[![Magento 2.4.4 - 2.4.8](https://img.shields.io/badge/Magento-2.4.4%20--%202.4.8-orange?logo=magento&logoColor=white)](https://magento.com)
[![PHP 8.1 - 8.4](https://img.shields.io/badge/PHP-8.1%20--%208.4-blue?logo=php&logoColor=white)](https://php.net)
[![Hyva Ready](https://img.shields.io/badge/Hyva-Ready-14b8a6?logo=alpinedotjs&logoColor=white)](https://hyva.io)
[![Luma Compatible](https://img.shields.io/badge/Luma-Compatible-f26322?logo=magento&logoColor=white)](https://magento.com)
[![Packagist](https://img.shields.io/badge/Packagist-mage2kishan%2Fmodule--hero--slider-orange?logo=packagist&logoColor=white)](https://packagist.org/packages/mage2kishan/module-hero-slider)
[![Upwork Top Rated Plus](https://img.shields.io/badge/Upwork-Top%20Rated%20Plus-14a800?logo=upwork&logoColor=white)](https://www.upwork.com/freelancers/~016dd1767321100e21)

> **A homepage hero that actually converts.** Panth Hero Slider is a production-grade center-focused carousel for Magento 2 — three slides visible at once with the active slide enlarged, peek of neighbouring slides on each side, autoplay with a generous read interval, and a single-slide responsive view below the mobile breakpoint. Per-slide button styling, separate desktop and mobile artwork, and zero theme dependency.

The slider ships its own Splide assets (loaded lazily from a public CDN) and a framework-agnostic stylesheet, so it renders identically on Hyva and Luma without touching theme code. The module auto-injects into the CMS home page out of the box; turn that off and place the block manually anywhere via a layout reference if you prefer.

---

## Key Features

- **Center-focus 3-up carousel.** Active slide enlarged, neighbour slides peek from the left and right.
- **Responsive breakpoint switch.** Below the configured pixel width: 1-slide-per-view + pagination dots, no arrows.
- **Per-slide CTA button.** Configurable label, background colour, text colour — overlaid on the artwork.
- **Separate desktop + mobile artwork.** `<picture>` with `<source media="(max-width: 767px)">` so mobile gets a properly-sized image.
- **No theme coupling.** Plain CSS classes, no Tailwind / Knockout / Alpine dependency. Works on Hyva and Luma.
- **Auto-injected on home page** by default; toggle off to place manually.
- **Admin grid + form** with image upload, drag-free sort order, mass enable / disable / delete.
- **Splide loaded lazily** after `DOMContentLoaded`, deduped across multiple sliders on the same page.
- **Hardened against receiver outage** for the install / heartbeat reporter (Panth_Core integration).

## Compatibility

| Component | Versions |
| --- | --- |
| Magento Open Source / Adobe Commerce | 2.4.4 → 2.4.8 |
| PHP | 8.1, 8.2, 8.3, 8.4 |
| Hyva Theme | 1.3.x → 1.4.x |
| Luma Theme | shipped with above Magento versions |

## Installation

```bash
composer require mage2kishan/module-hero-slider
bin/magento module:enable Panth_HeroSlider
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

That's all that is needed for the module to register; the slider renders on the home page once you add slides.

## Configuration

**Stores → Configuration → Panth Infotech → Hero Slider**

| Field | Default | Notes |
| --- | --- | --- |
| Enable Hero Slider | Yes | Master kill-switch. |
| Autoplay | Yes | Disable to require user interaction. |
| Autoplay Interval (ms) | `22000` | Time between slide transitions. |
| Slides Per Page (Desktop) | `3` | Active slide is the centre one. |
| Show Arrows (Desktop) | Yes | Hidden automatically on mobile. |
| Mobile Breakpoint (px) | `1025` | Below this width: 1 slide + pagination dots, no arrows. |
| Auto-inject on CMS Home Page | Yes | Disable to place the block manually via layout XML. |

## Managing Slides

**Content → Panth Infotech → Hero Slider → Manage Slides**

Each slide accepts:

- **Title** (admin label, never shown on the storefront).
- **Link URL** — destination when clicked. Absolute (`https://...`) or relative (`/path`).
- **Active** + **Sort Order**.
- **Button Label** — defaults to `SHOP NOW` if blank.
- **Button Background Color** + **Button Text Color** — hex codes; default `#09090C` / `#FFFFFF`.
- **Desktop Image** (required) — JPG, PNG, GIF, WebP, or SVG. Recommended ~ 1280 × 720.
- **Mobile Image** (optional) — used below the mobile breakpoint, falls back to desktop.
- **Image Alt Text** — used for both artworks.

## Manual Placement (when auto-inject is disabled)

Drop the block into any layout handle:

```xml
<page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd">
    <body>
        <referenceContainer name="content.top">
            <block class="Panth\HeroSlider\Block\Slider"
                   name="hero.slider"
                   template="Panth_HeroSlider::slider.phtml"/>
        </referenceContainer>
    </body>
</page>
```

The block renders nothing if no slides are active — safe to leave in place permanently.

## Schema

One additive table is created on `setup:upgrade`:

```text
panth_hero_slider_slide(
  entity_id, title, link_url,
  button_label, button_bg_color, button_text_color,
  image_desktop, image_mobile, image_alt,
  sort_order, is_active,
  created_at, updated_at
)
```

No destructive migrations across upgrades.

## Uninstall

```bash
bin/magento module:disable Panth_HeroSlider
composer remove mage2kishan/module-hero-slider
bin/magento setup:upgrade
```

## License

Proprietary. All rights reserved by Panth Infotech.

---

**Get a free quote in 24 hours:** [kishansavaliya.com/get-quote](https://kishansavaliya.com/get-quote)
**Hire on Upwork:** [Top Rated Plus profile](https://www.upwork.com/freelancers/~016dd1767321100e21)
