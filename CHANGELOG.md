# Changelog - Payplug Payments Module

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [4.0.0](https://github.com/payplug/payplug-magento2/releases/tag/4.0.0) - 2024-08-27

> **NOTE**
> This version goes directly from 1.27.4 to 4.0.0 to make a clean slate and avoid maintaining two logics as was done on previous versions (one in 1.27.X and the other one in 3.5.X).

### Features

- Optimize Oney payment load and script
- Fix bad redirection after 3DS payment failure
- Fix wrong return URL in case of pending file Oney
- Fix transaction stays indefinitely in payment review with 3DS integrated payment

**[View diff](https://github.com/payplug/payplug-magento2/compare/1.27.4...4.0.0)**

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
- Fix Undefined array key after 3DS failed payment [#225](https://github.com/payplug/payplug-magento2/pull/225)


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