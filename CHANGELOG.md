# Changelog

## 1.0.4 - 2026-05-13

- Fix the remaining PHP 8.4 implicit-nullable deprecation in `setItems()` on both grid collections (`Slider\Grid\Collection` and `Slide\Grid\Collection`). Parameter `$items` is now explicitly typed as `?array`. The 1.0.3 patch fixed `setSearchCriteria()` but missed the matching `setItems()` method directly below it, which Magento exercises through a separate code path (`cache:flush`) and would still trigger a fatal exception on Magento 2.4.9 / PHP 8.4 with default error reporting.
- Full-module sweep confirms no remaining implicit-nullable parameters of any type.

## 1.0.3 - 2026-05-13

- Fix PHP 8.4 implicit-nullable deprecation in `setSearchCriteria()` on both grid collections (`Slider\Grid\Collection` and `Slide\Grid\Collection`). Parameter `$searchCriteria` is now explicitly typed as `?\Magento\Framework\Api\SearchCriteriaInterface`, unblocking installs on Magento 2.4.9 / PHP 8.4 where the framework error handler converts this deprecation into a fatal exception.
