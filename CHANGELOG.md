# Changelog

## 1.0.3 - 2026-05-13

- Fix PHP 8.4 implicit-nullable deprecation in `setSearchCriteria()` on both grid collections (`Slider\Grid\Collection` and `Slide\Grid\Collection`). Parameter `$searchCriteria` is now explicitly typed as `?\Magento\Framework\Api\SearchCriteriaInterface`, unblocking installs on Magento 2.4.9 / PHP 8.4 where the framework error handler converts this deprecation into a fatal exception.
