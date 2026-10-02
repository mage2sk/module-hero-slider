# Magento 2 Hero Slider

Panth Hero Slider adds an admin-managed image carousel to a Magento 2 storefront. Slides are grouped into sliders, each slider has an identifier and a store-view assignment, and each slide carries a desktop image, an optional mobile image, a link, and a call-to-action button with its own colours. The carousel is rendered by Splide.js in a center-focused layout that shows several slides at once on desktop and one slide with pagination dots below a configurable breakpoint.

By default the module injects the slider with identifier `homepage_hero` into the content container of the CMS home page; it can also be placed on any page through a widget or layout XML. Views and clicks are counted per slide and shown in the admin. The template ships its own CSS and does not depend on theme JavaScript, so it works on both Hyva and Luma themes.

Product page: [kishansavaliya.com/magento-2-hero-slider.html](https://kishansavaliya.com/magento-2-hero-slider.html)

## Features

- Sliders ("Manage Sliders") with a unique identifier, an admin name, an active flag and a store-view assignment; a default slider "Home Page Hero" (`homepage_hero`, all store views) is created by a data patch.
- Slides ("Manage Slides") with title, link URL, sort order, active flag, button label, button background and text colours, desktop image, mobile image and alt text.
- Center-focused loop carousel (Splide.js 4.1.4, `focus: center`) with configurable slides per page, autoplay, autoplay interval, arrows and mobile breakpoint.
- Responsive artwork: a `<picture>` element serves the mobile image for viewports up to 767px wide and falls back to the desktop image when no mobile image is set.
- Automatic placement on the CMS home page (`cms_index_index` layout handle), switchable in configuration.
- Widget "Panth Hero Slider" for CMS pages, blocks and widget instances, and a block class for layout XML.
- Per-slide view and click tracking via `navigator.sendBeacon` to a POST endpoint that requires the storefront form key (read from the `form_key` cookie, as Magento does for cached pages); counts are bucketed by day, store view, event type and device type (desktop, tablet, mobile). Views are only counted for the active slide. Each slide is counted at most once per event type per session, and requests are rate limited per IP address.
- Admin analytics: "Views (30d)", "Clicks (30d)" and "CTR (30d)" columns on the slides grid, and a "Performance" panel on the slide edit page with 30-day and 7-day totals, a "By device" table and a "Daily trend" list.
- Daily cron job that deletes statistics older than the configured retention period.
- Slide images are uploaded to `pub/media/panth/heroslider/slide/`; allowed extensions are jpg, jpeg, gif, png and webp, the uploaded file must also have a matching image MIME type, the form limits uploads to 4 MB, and file names are checked against the `Panth_Core` upload extension policy. When a file with the same name already exists, the new file is saved with a numeric suffix.
- JSON-LD `ItemList` of `ImageObject` entries for the rendered slides, and a static copy of the first slide marked `fetchpriority="high"` for the initial paint.
- Splide is loaded through RequireJS when it is present (Luma) and through a plain script tag otherwise (Hyva).

## Compatibility

| Platform | Versions |
|---|---|
| Magento Open Source | 2.4.4, 2.4.5, 2.4.6, 2.4.7, 2.4.8 (as published on the product page) |
| Adobe Commerce | 2.4.4, 2.4.5, 2.4.6, 2.4.7, 2.4.8 (as published on the product page) |
| PHP | 8.1, 8.2, 8.3, 8.4 (`~8.1.0||~8.2.0||~8.3.0||~8.4.0` in `composer.json`) |
| Themes | Hyva and Luma |

Composer constraints on Magento packages: `magento/framework` ^103.0, `magento/module-backend` ^102.0, `magento/module-cms` ^104.0, `magento/module-config` ^101.2, `magento/module-media-storage` ^100.4, `magento/module-store` ^101.0, `magento/module-ui` ^101.0, `magento/module-widget` ^101.2.

## Requirements

- Magento Open Source or Adobe Commerce 2.4.4 to 2.4.8.
- PHP 8.1, 8.2, 8.3 or 8.4.
- `mage2kishan/module-core` ^1.0.17 (`Panth_Core`); it provides the shared admin menu parent and the upload extension policy used by the image upload controller.
- Splide.js 4.1.4 (MIT) is bundled in `view/frontend/web/splide/` together with its license and served from the store's static files; no CDN is contacted.
- Magento cron must be running for the statistics pruning job.

## Installation

```bash
composer require mage2kishan/module-hero-slider
bin/magento module:enable Panth_Core Panth_HeroSlider
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f
bin/magento cache:flush
```

`setup:di:compile` is only needed in production mode. `setup:static-content:deploy` is required because the module ships `view/frontend/web/css/hero-slider.css`.

Check that the module is enabled:

```bash
bin/magento module:status Panth_HeroSlider
```

`setup:upgrade` creates the database tables and runs the `CreateDefaultSlider` data patch, which inserts the "Home Page Hero" slider (`homepage_hero`) assigned to all store views and attaches any slide without a slider to it.

## Configuration

Admin path: Stores > Configuration > Panth Infotech > Hero Slider. This module labels the shared `panth` tab "Panth Infotech"; `Panth_Core` labels the same tab "Panth Extensions", so the tab name shown depends on which definition Magento loads last. The section is also reachable from the sidebar item "Panth Infotech" > "Hero Slider" > "Configuration". Viewing the section requires the ACL resource `Panth_HeroSlider::config`.

### General

| Setting | Default | What it does |
|---|---|---|
| Enable Hero Slider | Yes | Master switch. When No, every slider block renders nothing. |
| Autoplay | Yes | Advances slides automatically. |
| Autoplay Interval (ms) | 22000 | Time each slide is shown before advancing. Shown only when Autoplay is Yes. |
| Slides Per Page (Desktop) | 3 | Number of slides visible at once above the mobile breakpoint. |
| Show Arrows (Desktop) | Yes | Shows previous/next arrows above the mobile breakpoint. Arrows are always hidden below it. |
| Mobile Breakpoint (px) | 1025 | Below this viewport width the carousel shows one slide per page with pagination dots and no arrows. |
| Auto-inject on CMS Home Page | Yes | Renders the `homepage_hero` slider at the top of the home page content container. Set to No to place the slider manually. |
| Check Active and Store View for Layout Slider ID | No | When Yes, a `slider_id` block argument from layout XML is only rendered if the slider is active and assigned to the current store view or All Store Views. No keeps the earlier behaviour: a layout `slider_id` is rendered without these checks. |

### Analytics

| Setting | Default | What it does |
|---|---|---|
| Track Views and Clicks | Yes | Enables the tracking script in the storefront template and the tracking endpoint. When No, the endpoint ignores incoming events. Counts are stored per slide, day, store view, event type and device type; no personal data is recorded. |
| Retention (days) | 365 | Statistics rows older than this are deleted by the daily cron job. Shown only when tracking is Yes; default scope only. |
| Tracking Requests Per IP Per Minute | 60 | Tracking requests above this number from one IP address within a minute are answered with `429` and not counted. 0 turns the limit off. |

Config paths: `panth_heroslider/general/enabled`, `panth_heroslider/general/autoplay`, `panth_heroslider/general/interval`, `panth_heroslider/general/per_page`, `panth_heroslider/general/show_arrows`, `panth_heroslider/general/mobile_breakpoint`, `panth_heroslider/general/auto_inject_homepage`, `panth_heroslider/general/validate_layout_slider_id`, `panth_heroslider/analytics/enabled`, `panth_heroslider/analytics/retention_days`, `panth_heroslider/analytics/rate_limit_per_minute`. All fields except `retention_days` can be set at default, website and store-view scope. Invalid or empty numeric values fall back to 22000, 3 and 1025 respectively.

With the defaults the module is active immediately after installation: once at least one active slide is assigned to the `homepage_hero` slider, the carousel appears on the home page.

### Managing sliders

Sidebar: "Panth Infotech" > "Hero Slider" > "Manage Sliders" (ACL `Panth_HeroSlider::sliders`). The grid lists ID, Name, Identifier, Store Views, Active and Updated, with a keyword search (name, identifier) and a mass "Delete" action. The form ("Slider" fieldset) has:

- Name (required) - admin label only.
- Identifier (required) - lowercase letters, digits, hyphen or underscore; other characters are stripped on save. Used by the widget and layout XML. Unique in the database.
- Store Views - multiselect; choose "All Store Views" or specific store views. Selecting "All Store Views" replaces any other selection.
- Active - inactive sliders are not resolved on the storefront.

### Managing slides

Sidebar: "Panth Infotech" > "Hero Slider" > "Manage Slides" (ACL `Panth_HeroSlider::slides`). The grid lists ID, Desktop Image, Title, Slider Group, Link URL, Button Label, Sort, Active, Views (30d), Clicks (30d), CTR (30d) and Updated, with a keyword search (title, button label, image alt text, link URL) and mass "Delete", "Enable" and "Disable" actions. The form has three fieldsets:

- "Slide": Slider Group (required), Title (admin only) (required), Link URL (absolute or relative; only relative paths and the http, https, mailto and tel schemes are accepted; an empty or rejected value renders `#`), Active, Sort Order (ascending; ties are ordered by ID).
- "Call-to-action button": Button Label (default "SHOP NOW"), Button Background Color (default `#09090C`), Button Text Color (default `#FFFFFF`). Colors must be a hex code, a color name or an `rgb()`/`hsl()` value; other values are rejected on save and replaced by the default on the storefront.
- "Artwork": Desktop Image (required; jpg, jpeg, png, gif, webp; up to 4 MB; the form recommends about 1280 x 720), Mobile Image (optional; used below 768px; falls back to the desktop image), Image Alt Text (falls back to the title).

The slide edit page also shows the "Performance" panel described under Features. Deleting a slider sets `slider_id` of its slides to NULL; deleting a slide removes its statistics rows.

## Usage

### Automatic home page placement

With "Auto-inject on CMS Home Page" set to Yes, `view/frontend/layout/cms_index_index.xml` adds the block `panth.heroslider.home` (`Panth\HeroSlider\Block\Slider`, template `Panth_HeroSlider::slider.phtml`) to the `content` container with `before="-"`, using the identifier `homepage_hero`.

### Widget

Insert the widget "Panth Hero Slider" (`panth_heroslider_widget`) in a CMS page, CMS block or widget instance. It has one parameter:

| Parameter | Required | Description |
|---|---|---|
| Slider Identifier (`slider_identifier`) | Yes | Identifier of the slider to render, for example `homepage_hero`. |

The widget class is `Panth\HeroSlider\Block\Widget\Slider`, which extends the storefront block and uses the same template.

### Layout XML

```xml
<referenceContainer name="content">
    <block class="Panth\HeroSlider\Block\Slider"
           name="hero.category.promo"
           template="Panth_HeroSlider::slider.phtml"
           before="-">
        <arguments>
            <argument name="slider_identifier" xsi:type="string">category_promo</argument>
        </arguments>
    </block>
</referenceContainer>
```

A `slider_id` argument (integer) is also accepted and takes precedence over `slider_identifier`. By default a `slider_id` is rendered without checking that the slider is active or assigned to the current store view; set "Check Active and Store View for Layout Slider ID" to Yes to apply the same checks as for identifiers. When neither argument is given the block uses `homepage_hero`.

### Storefront behaviour

- The block resolves the slider by identifier for the current store view; the slider must be active and linked to "All Store Views" or to the current store view. Only active slides are loaded, ordered by sort order then ID.
- Nothing is output when the module is disabled, the slider cannot be resolved, or it has no active slides.
- The block adds the cache tags `panth_hero_slider`, `panth_hero_slider_<id>` and `panth_hero_slide_<id>` to the page, so saving or deleting a slider or slide refreshes the cached pages that show it.
- The Splide core stylesheet and script (version 4.1.4) are served from the module's static files (`Panth_HeroSlider::splide/splide-core.min.css` and `Panth_HeroSlider::splide/splide.min.js`, license in `splide/LICENSE`); the module's own `hero-slider.css` is added on every frontend page by `view/frontend/layout/default.xml`. Visual tokens such as radius, gap, shadows and arrow colour are CSS custom properties on `.panth-hero` and can be overridden in theme CSS.
- Tracking posts form data (`slide_id`, `type`, `device`, `form_key`) to `panth_heroslider/track/event`. The endpoint accepts POST only (GET returns 404) and answers `403` when the form key does not match the session form key registered from the `form_key` cookie. Otherwise it answers `204`, ignores all events when the module or "Track Views and Clicks" is disabled, answers `429` when the per-IP limit for the current minute is used up, ignores unknown or inactive slides, invalid event or device values and repeated events for the same slide and event type in the same session, and increments `panth_hero_slider_stat` with an `INSERT ... ON DUPLICATE KEY UPDATE` keyed by slide, UTC date, store view, event type and device type. The device class is decided in the browser: below 768px "mobile", below 1025px "tablet", otherwise "desktop".

### Cron

| Job | Schedule | Class | Action |
|---|---|---|---|
| `panth_heroslider_prune_stats` | `23 3 * * *` | `Panth\HeroSlider\Cron\PruneStats` | When "Track Views and Clicks" is Yes, deletes rows from `panth_hero_slider_stat` whose `event_date` is older than "Retention (days)" and logs the number removed. |

### Templates

- `view/frontend/templates/slider.phtml` - the carousel markup, the Splide loader and the tracking script. Override it in a theme at `Panth_HeroSlider/templates/slider.phtml`.
- `view/adminhtml/templates/slide/analytics.phtml` - the "Performance" panel on the slide edit page.

## Developer Notes

- Module name: `Panth_HeroSlider`; Composer package: `mage2kishan/module-hero-slider` (version 1.0.11); PSR-4 namespace: `Panth\HeroSlider\`.
- Loads after `Magento_Backend`, `Magento_Cms`, `Magento_Store`, `Magento_Ui` and `Panth_Core`.
- Routes: frontend and admin front name `panth_heroslider`.
- Service contracts: `Api\Data\SlideInterface`, `Api\Data\SliderInterface`, `Api\SlideRepositoryInterface` (`save`, `getById`, `delete`, `deleteById`), `Api\SliderRepositoryInterface` (`save`, `getById`, `getByIdentifier`, `delete`, `deleteById`), with preferences to `Model\Slide`, `Model\Slider`, `Model\SlideRepository` and `Model\SliderRepository` in `etc/di.xml`. No `webapi.xml` is shipped.
- `Block\Slider` public methods: `getResolvedSliderId`, `isEnabled`, `getSlides`, `getSliderId`, `getDesktopSrc`, `getMobileSrc`, `getButtonLabel`, `getButtonBg`, `getButtonColor`, `getAlt`, `getLinkUrl`, `getSplideConfig`, `getSplideConfigJson`, `getSplideJsUrl`, `getSplideCssUrl` (local static file URLs), `isAnalyticsEnabled`, `getTrackEndpointUrl`, `getJsonLd`, `getJsonLdJson`.
- `Model\Config` exposes typed getters for every config path (constants `XPATH_*`).
- `Model\StatTracker`: `track`, `getTotals`, `getDeviceBreakdown`, `getTimeline`, `pruneOlderThan`.
- `Model\TrackGuard`: per-IP rate limit (cache counter per minute) and per-session de-duplication used by the tracking endpoint.
- `Model\ResourceModel\Slider::getIdByIdentifier(string $identifier, int $storeId)` performs the storefront lookup; `lookupStoreIds` and `saveStoreLinks` manage the store table.
- `Model\ImageUploader` is configured in `etc/di.xml` with temporary path `panth/heroslider/tmp/slide`, final path `panth/heroslider/slide` and the allowed extension list.
- Admin grids use `Model\ResourceModel\Slide\Grid\Collection` and `Model\ResourceModel\Slider\Grid\Collection`; custom columns live in `Ui\Component\Listing\Column` (`ImagePreview`, `SlideActions`, `SliderActions`, `SliderStores`, `StatColumn`).
- ACL resources: `Panth_HeroSlider::sliders` ("Hero Sliders"), `Panth_HeroSlider::slider_save`, `Panth_HeroSlider::slider_delete`, `Panth_HeroSlider::slides` ("Hero Slides"), `Panth_HeroSlider::slide_save`, `Panth_HeroSlider::slide_delete`, `Panth_HeroSlider::config` ("Panth Hero Slider Config").
- Database tables (`etc/db_schema.xml`): `panth_hero_slider_slider`, `panth_hero_slider_slider_store` (cascade delete), `panth_hero_slider_slide` (`slider_id` set to NULL on slider delete), `panth_hero_slider_stat` (cascade delete on slide).
- No plugins, observers or console commands are declared.

## Uninstallation

```bash
bin/magento module:disable Panth_HeroSlider
composer remove mage2kishan/module-hero-slider
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

The four `panth_hero_slider_*` tables, the `panth_heroslider/*` rows in `core_config_data`, the `CreateDefaultSlider` entry in `patch_list` and uploaded images under `pub/media/panth/heroslider/` remain after these steps and must be removed manually if no longer wanted. Widget instances and CMS content that reference the widget also remain.

## Support

- Product page: [kishansavaliya.com/magento-2-hero-slider.html](https://kishansavaliya.com/magento-2-hero-slider.html)
- Contact form: [kishansavaliya.com/contact](https://kishansavaliya.com/contact)
- Email: kishansavaliyakb@gmail.com
- Bug reports: [GitHub issues](https://github.com/mage2sk/module-hero-slider/issues)

## License

Proprietary, as declared in `composer.json`. The package is published on Packagist and can be installed with Composer; see the product page for the terms of use.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Links

- Website: [kishansavaliya.com](https://kishansavaliya.com)
- All extensions: [kishansavaliya.com/magento-extensions.html](https://kishansavaliya.com/magento-extensions.html)
- GitHub: [mage2sk/module-hero-slider](https://github.com/mage2sk/module-hero-slider)
- Packagist: [mage2kishan/module-hero-slider](https://packagist.org/packages/mage2kishan/module-hero-slider)
