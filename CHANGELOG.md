# Changelog - Payplug Payments Module

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [4.3.0](https://github.com/payplug/payplug-magento2/releases/tag/v4.3.0) - 2025-03-19

### Features

- Support Magento 2.4.7
- Add a queue system feature 
- Synchronize orders status with Payplug servers post payment 
- Compatibility with php 8.1 and Magento 2.4.4 and above. (SMP-3032)

**[View diff](https://github.com/payplug/payplug-magento2/compare/v4.2.0...release/v4.3.0)**

### Added

- Add queue system payment feature [#246](https://github.com/payplug/payplug-magento2/pull/246)

### Removed

- Removed the unions type in the base code. [#246](https://github.com/payplug/payplug-magento2/pull/246)

### Fixed

- Fix the scripts for the 2.4.7 with the secureHtmlRenderer class [#253](https://github.com/payplug/payplug-magento2/pull/253)

## [4.2.0](https://github.com/payplug/payplug-magento2/releases/tag/4.2.0) - 2025-01-13

### Features

- Add deferred payment feature
- Update and optimize payment methods cards render
- Checkout render improvements
- A11Y improvements

**[View diff](https://github.com/payplug/payplug-magento2/compare/v4.1.0...v4.2.0)**

### Added

- Add deferred payment feature [#238](https://github.com/payplug/payplug-magento2/pull/238)
- Add Oney method payment title [#240](https://github.com/payplug/payplug-magento2/pull/240)
- Add alternative texts on payment methods logos for A11Y [#240](https://github.com/payplug/payplug-magento2/pull/240)

### Changed

- Move payment images to dedicated logos images folder [#240](https://github.com/payplug/payplug-magento2/pull/240)
- Optimize and normalize payment methods styles [#240](https://github.com/payplug/payplug-magento2/pull/240)
- Optimize and normalize payment methods logos [#240](https://github.com/payplug/payplug-magento2/pull/240)
- Update standard payment schemes A11Y [#240](https://github.com/payplug/payplug-magento2/pull/240)

### Removed

- Remove useless payment logos [#240](https://github.com/payplug/payplug-magento2/pull/240)

## [4.1.0](https://github.com/payplug/payplug-magento2/releases/tag/4.1.0) - 2024-10-02

### Features

- Remove Giropay and Sofort payment methods
- PHP 8.1 upgrade
- Security and controllers improvements
- Fix various escpaers
- Update and optimize payment methods logos

**[View diff](https://github.com/payplug/payplug-magento2/compare/v4.0.0...v4.1.0)**

### Added

- Add the new cb Logo to Magento 2 [#219](https://github.com/payplug/payplug-magento2/pull/219) [#233](https://github.com/payplug/payplug-magento2/pull/233)
- Add security via form key to multiple controllers along with php 8 [#230](https://github.com/payplug/payplug-magento2/pull/230)
- Add form key validation and php 8 to the Block/Adminhtml/Config/Logout.php controller [#230](https://github.com/payplug/payplug-magento2/pull/230)

### Changed

- Update and optimize payment cards logos [#219](https://github.com/payplug/payplug-magento2/pull/219) [#233](https://github.com/payplug/payplug-magento2/pull/233) [#234](https://github.com/payplug/payplug-magento2/pull/234) [#237](https://github.com/payplug/payplug-magento2/pull/237) 
- Replace escapeHtml by escapeUrl in formjs.phtml [#220](https://github.com/payplug/payplug-magento2/pull/220)
- Use an escapeHtmlAttr for the code in the ondemand template [#220](https://github.com/payplug/payplug-magento2/pull/220)
- Prevent using NamingConvention\true\string as string [#230](https://github.com/payplug/payplug-magento2/pull/230)
- Update apple pay controller to php 8 and fix argument must be of type string [#230](https://github.com/payplug/payplug-magento2/pull/230)
- Upgrade to php 8.1 and add security to Ipn, Simulation, SimulationCheckout.php and AsbtractPayment controler [#230](https://github.com/payplug/payplug-magento2/pull/230)
- Update Standard and PaymentReturn.php controllers, along with Cancel controller and all the relative depending classes [#230](https://github.com/payplug/payplug-magento2/pull/230)
- Update controllers classes to php 8.0+ and use the form key [#230](https://github.com/payplug/payplug-magento2/pull/230)
- Update InstallmentPlanAbort.php and subclasses to php 8 and add form key security [#230](https://github.com/payplug/payplug-magento2/pull/230)

### Removed

- Remove Sofort payment method [#235](https://github.com/payplug/payplug-magento2/pull/235)
- Remove Giropay payment method [#235](https://github.com/payplug/payplug-magento2/pull/235)
- Remove the old unused pictures [#219](https://github.com/payplug/payplug-magento2/pull/219) [#233](https://github.com/payplug/payplug-magento2/pull/233)

### Fixed

- Fix cardList escapeUrl [#220](https://github.com/payplug/payplug-magento2/pull/220)
- Fix escapers [#220](https://github.com/payplug/payplug-magento2/pull/220)
- Fix phtmls escapers and add strict types for php 8 [#220](https://github.com/payplug/payplug-magento2/pull/220)

## [4.0.0](https://github.com/payplug/payplug-magento2/releases/tag/4.0.0) - 2024-08-28

> **NOTE**
> This version goes directly from 1.27.4 to 4.0.0 to make a clean slate and avoid maintaining two logics as was done on previous versions (one in 1.27.X and the other one in 3.5.X).

### Features

- Optimize Oney payment load and script
- Fix bad redirection after 3DS payment failure
- Fix wrong return URL in case of pending file Oney
- Fix transaction stays indefinitely in payment review with 3DS integrated payment
- Php 8.1 support

**[View diff](https://github.com/payplug/payplug-magento2/compare/1.27.4...v4.0.0)**

### Added

- Add CHANGELOG.md [#211](https://github.com/payplug/payplug-magento2/pull/211)
- Add declare strict types along with real returns and php 8.0 [#209](https://github.com/payplug/payplug-magento2/pull/209)
- Add missing xml version declarations [#208](https://github.com/payplug/payplug-magento2/pull/208)
- Add secure-magenta.dalenys.com in CSP whitelist [#212](https://github.com/payplug/payplug-magento2/pull/212)
- Add strict returns and fix typos for CheckPayment class [#209](https://github.com/payplug/payplug-magento2/pull/209)
- Add the real exceptions throw to function declaration [#209](https://github.com/payplug/payplug-magento2/pull/209)

### Changed

- Factorize payment simulation Ajax calls [#208](https://github.com/payplug/payplug-magento2/pull/208)
- Get popin payment option navigation back [#208](https://github.com/payplug/payplug-magento2/pull/208)
- Optimize Oney popin script show/hide [#208](https://github.com/payplug/payplug-magento2/pull/208)
- Type more thoroughly the returns [#218](https://github.com/payplug/payplug-magento2/pull/218/files)
- Update and refacto Oney view handler script [#208](https://github.com/payplug/payplug-magento2/pull/208)
- Update oney popin script constructor [#208](https://github.com/payplug/payplug-magento2/pull/208)
- Update order status [#209](https://github.com/payplug/payplug-magento2/pull/209)
- Update payment standard method script indentation and declaration [#209](https://github.com/payplug/payplug-magento2/pull/209)
- Update Payplug Integrated Payments library call aliasing [#208](https://github.com/payplug/payplug-magento2/pull/208)

### Removed

- Remove Oney script head tag call [#208](https://github.com/payplug/payplug-magento2/pull/208)
- Remove residual comment - Update order status [#209](https://github.com/payplug/payplug-magento2/pull/209)

### Fixed

- Allow success order placement on oney pending payment [#218](https://github.com/payplug/payplug-magento2/pull/218/files)
- Prevent type error on order placements [#226](https://github.com/payplug/payplug-magento2/pull/226/commits/1878eb759e2d7f48c10b5a64959b6615a5ff1ec6)
- Fix Undefined array key after 3DS failed payment [#225](https://github.com/payplug/payplug-magento2/pull/225)
- Fix the use of phrases instead of string in php 8 [#217](https://github.com/payplug/payplug-magento2/pull/217)

## [1.27.4](https://github.com/payplug/payplug-magento2/releases/tag/1.27.4) - 2024-05-02

**[view diff](https://github.com/payplug/payplug-magento2/compare/1.27.3...1.27.4)**

## [1.27.3](https://github.com/payplug/payplug-magento2/releases/tag/1.27.3) - 2024-03-28

**[view diff](https://github.com/payplug/payplug-magento2/compare/1.27.2...1.27.3)**

## [1.27.2](https://github.com/payplug/payplug-magento2/releases/tag/1.27.2) - 2023-10-26

**[view diff](https://github.com/payplug/payplug-magento2/compare/1.27.1...1.27.2)**

## [1.27.1](https://github.com/payplug/payplug-magento2/releases/tag/1.27.1) - 2023-10-10

**[view diff](https://github.com/payplug/payplug-magento2/compare/1.27.0...1.27.1)**

## [1.27.0](https://github.com/payplug/payplug-magento2/releases/tag/1.27.0) - 2023-09-21

**[view diff](https://github.com/payplug/payplug-magento2/compare/1.26.0...1.27.0)**

## [1.26.0](https://github.com/payplug/payplug-magento2/releases/tag/1.26.0) - 2023-09-06

**[view diff](https://github.com/payplug/payplug-magento2/compare/1.25.0...1.26.0)**

## [1.25.0](https://github.com/payplug/payplug-magento2/releases/tag/1.25.0) - 2023-07-10

**[view diff](https://github.com/payplug/payplug-magento2/compare/1.24.1...1.25.0)**
