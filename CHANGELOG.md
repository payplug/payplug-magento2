# Changelog - Payplug Payments Module

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [4.7.0](https://github.com/payplug/payplug-magento2/releases/tag/v4.7.0) - 2026-04-10

### ⚠ ACTION REQUIRED

This notice applies only to the **Standard payment method using authorization only mode**.

Authorization metadata storage on order payments has been improved.

**Impact:**  
If you rely on the `AutoCaptureDeferredPayments` CRON for automatic capture, some orders created in the last 7 days prior to upgrading to this version may have inconsistent authorization data, potentially preventing captures.

**Required action:**  
You must run a data realignment to synchronize authorization metadata from quote payments to order payments for the past 10 days:

```
bin/magento payplug:migrate-authorization-metadata
```

Failure to perform this action may result in missed captures, especially if no manual capture is performed via the Magento backend or API.

### Features

- Redirect 3D Secure for OneClick option (MAG-613)
- Add quote retrieval logic to TransactionDataBuilder with enhanced error handling (MAG-618)
- Add Bizum and Wero payment methods (MAG-603)
- Handle Wero cancel operation (forward from PaymentReturn to Cancel action) (MAG-603)
- Add custom metadata field to config and integrate metadata plugin in order placement process (MAG-612)
- Add multilingual translations including OAuth2 and Standard Auth labels (MAG-623)
- Create invoice message queue after order status is updated (MAG-652)
- Remove bizum whitelisted phone from codebase (MAG-666)
- Fix duplicate authorization when capturing with missing authorization flag (MAG-672)
- Add Scalapay payment method (MAG-661)
- Replace the iDEAL logo with the co-branded iDEAL/Wero version (MAG-619)
- Enable coupon code rollback by using OrderService cancellation (MAG-682)
- Add headless compatibility for cart rollback on Cancel / Payment Return (MAG-677)
- Add CLI to migrate authorization metadata from quote to order (MAG-683)
- Fix Scalapay country restriction logic (MAG-687)
- Fix cart rollback with Scalapay user cancellation (MAG-690)
- Change Scalapay payment title to reflect 3x/4x option (MAG-696)
- Fix order status persisting as “Pending Payment” after successful order cancellation (MAG-694)

**[View diff](https://github.com/payplug/payplug-magento2/compare/v4.6.3...v4.7.0)**

### Added

- Add quote retrieval logic to TransactionDataBuilder with enhanced error handling (MAG-618) [#7b3a0fbe](https://github.com/payplug/payplug-magento2/commit/7b3a0fbe264d5b7200c7375940e381b78c28b54a)
- Add Bizum and Wero payment methods (MAG-603) [#cbae5396](https://github.com/payplug/payplug-magento2/commit/cbae5396410fd59984c207650dbf4fb5468cd4e6)
- Handle Wero cancel operation (forward from PaymentReturn to Cancel action) (MAG-603) [#5892d8b3](https://github.com/payplug/payplug-magento2/commit/5892d8b3ecd2bab62524dbb8db870957d7d43c1c)
- Add custom metadata field to config and integrate metadata plugin in order placement process (MAG-612) [#fa857bb2](https://github.com/payplug/payplug-magento2/commit/fa857bb25f6124e181258ecd25a6ce235f9f9781)
- Add multilingual translations including OAuth2 and Standard Auth labels (MAG-623) [#5d278de8](https://github.com/payplug/payplug-magento2/commit/5d278de8da27bd0a1ed9262cdb6438c67a057f6a)
- Add Scalapay payment method (MAG-661) [#664c13c6](https://github.com/payplug/payplug-magento2/commit/664c13c626bbafe952a6982ba29f02edb4aa5262)
- Add headless compatibility for cart rollback on Cancel / Payment Return (MAG-677) [#15037601](https://github.com/payplug/payplug-magento2/commit/15037601192a368ee22e115f3fd7e0741ff0cd6a)
- Add CLI to migrate authorization metadata from quote to order (MAG-683) [#13dd906d](https://github.com/payplug/payplug-magento2/commit/13dd906de878ab7a2d82990fb471bf892b22d115)

### Changed

- Redirect 3D Secure for OneClick option (MAG-613) [#7f71ef6d](https://github.com/payplug/payplug-magento2/commit/7f71ef6db9d7fb8f7c5e8de6cda96efa2c7c06cc)
- Remove bizum whitelisted phone from codebase (MAG-666) [#dd87742f](https://github.com/payplug/payplug-magento2/commit/dd87742ff46600ae2e182e88174437319d5463e8)
- Replace the iDEAL logo with the co-branded iDEAL/Wero version (MAG-619) [#50701821](https://github.com/payplug/payplug-magento2/commit/507018218fe4e4030994367988b931432684c9df)
- Change Scalapay payment title to reflect 3x/4x option (MAG-696) [#59d2e99b](https://github.com/payplug/payplug-magento2/commit/59d2e99b82bd8cdb89e922a589f3b42989775f4d)

### Fixed

- Create invoice message queue after order status is updated (MAG-652) [#e9e47658](https://github.com/payplug/payplug-magento2/commit/e9e4765847c49db915129aa0f84ba212a337bd9f)
- Fix duplicate authorization when capturing with missing authorization flag (MAG-672) [#e9e47658](https://github.com/payplug/payplug-magento2/commit/0f49f6921d913eae6a3736dbc1a68c151dabf3ee)
- Fix coupon code rollback by using OrderService cancellation (MAG-682) [#1beb43d3](https://github.com/payplug/payplug-magento2/commit/1beb43d36fc0a4d310fc80c2533697901d860f54)
- Fix Scalapay country restriction logic (MAG-687) [#b1dcd985](https://github.com/payplug/payplug-magento2/commit/b1dcd9853e5b1f25204fd3cc730f0244b3919de7)
- Fix cart rollback with Scalapay user cancellation (MAG-690) [#ebcd1225](https://github.com/payplug/payplug-magento2/commit/ebcd122535103332ef8fbc0d2cb9f6b04634d2be)
- Fix order status persisting as “Pending Payment” after successful order cancellation (MAG-694) [#71188022](https://github.com/payplug/payplug-magento2/commit/7118802257a712b8a7ee753729d8a36a825231f1)

## [4.6.3](https://github.com/payplug/payplug-magento2/releases/tag/v4.6.3) - 2026-03-09

### Features

- Add full Magento Coding Standard ruleset compliance (MAG-601)
- Add React headless payment app example (MAG-549)
- Fix type casting for total amount and split count in installment plan generation (MAG-539)
- Fix undefined index error in PaymentConfigObserver (MAG-597)
- Fix double shipping rate calculation on configurable product (MAG-608)
- Update React components declaration to avoid PHP CS errors (MAG-617)
- Add GraphQL resolver for retrieving Payplug payment redirect URL on GraphQL Order type (V1) for Magento 2.4.6 compatibility (MAG-621)
- Update ApplePay order handling to reflect shipping method changes and add relevant order comment (MAG-629)
- Add null safe operator for `authorized_at` to prevent potential null reference error (MAG-628)
- Add Payplug Copyright comments (MAG-615)
- Update failure redirect URL to improve error handling and pass failure msg (MAG-656)
- Refactor cache key logic to support website-specific OAuth2 access token caching (MAG-665)
- Add customizable URL scope option for return/cancel success redirects and encode base64 all custom return urls (MAG-646)
- Refactor custom return URL encoding logic on place order (MAG-655)
- Fix website scope legacy login and refactor config fields behavior (MAG-650)
- Handle additional store scope type during OAuth connection validation (MAG-647)

**[View diff](https://github.com/payplug/payplug-magento2/compare/v4.6.2...v4.6.3)**

### Changed

- Refactor custom return URL encoding logic on place order (MAG-655) [#4aec1a07](https://github.com/payplug/payplug-magento2/commit/4aec1a07db77bda498a6e515bd69d91866383359)
- Update React components declaration to avoid PHP CS errors (MAG-617) [#9998c7c9](https://github.com/payplug/payplug-magento2/commit/9998c7c904f1d87b3f6a686173c7d96451673f80)

### Added

- Add full Magento Coding Standard ruleset compliance (MAG-601) [#ae4a9a48](https://github.com/payplug/payplug-magento2/commit/ae4a9a48026a1aa5135a64d4def3fe76e535ec04)
- Add React headless payment app example (MAG-549) [#8c342895](https://github.com/payplug/payplug-magento2/commit/8c34289593549c7953ac2a86927c1d81caaf6bb9)
- Add GraphQL resolver for retrieving Payplug payment redirect URL on GraphQL Order type (V1) for Magento 2.4.6 compatibility (MAG-621) [#f886d4c3](https://github.com/payplug/payplug-magento2/commit/f886d4c3235266b689569dc698d2a709b0f70fb5)
- Add Payplug Copyright comments (MAG-615) [#90990a4a](https://github.com/payplug/payplug-magento2/commit/90990a4aba8530509ea13580db2dd6eb761ef300)
- Add customizable URL scope option for return/cancel success redirects and encode base64 all custom return urls (MAG-646) [#a0418795](https://github.com/payplug/payplug-magento2/commit/a0418795960d19070e9b6d8da761377b28016050)

### Fixed

- Fix type casting for total amount and split count in installment plan generation (MAG-539) [#a7152128](https://github.com/payplug/payplug-magento2/commit/a7152128e56818d6a12f5aef47379dd81d4ce382)
- Fix undefined index error in PaymentConfigObserver (MAG-597) [#807de5db](https://github.com/payplug/payplug-magento2/commit/807de5dba8444858b57bce7037e28c774f9099d1)
- Fix double shipping rate calculation on configurable product (MAG-608) [#89f16080](https://github.com/payplug/payplug-magento2/commit/89f160809bdc2e7fa51552d2cb5e96b183510e5b)
- Fix ApplePay order handling to reflect shipping method changes and add relevant order comment (MAG-629) [#e0f1b726](https://github.com/payplug/payplug-magento2/commit/e0f1b7268d119d67d1ea86202517a214066befc1)
- Add null safe operator for `authorized_at` to prevent potential null reference error (MAG-628) [#8b4d4040](https://github.com/payplug/payplug-magento2/commit/8b4d40406da96410067853e1afde32b353cab929)
- Update failure redirect URL to improve error handling and pass failure msg (MAG-656) [#14d91ead](https://github.com/payplug/payplug-magento2/commit/14d91ead1439c3877c27b016d9f79accf0f1fc5f)
- Refactor cache key logic to support website-specific OAuth2 access token caching (MAG-665) [#2cdc5dc6](https://github.com/payplug/payplug-magento2/commit/2cdc5dc6420c4f274a557347b49b797c955e509d)
- Fix website scope legacy login and refactor config fields behavior (MAG-650) [#1ea1877f](https://github.com/payplug/payplug-magento2/commit/1ea1877f5d6ef18c2a59051ea1dd5f7baec8478e)
- Handle additional store scope type during OAuth connection validation (MAG-647) [#1e9cae78](https://github.com/payplug/payplug-magento2/commit/1e9cae7883f9dfb57e382accdf55237112bbde45)

## [4.6.2](https://github.com/payplug/payplug-magento2/releases/tag/v4.6.2) - 2026-01-26

### Features

- Fix exception when selecting PayPlug payment method in checkout (e.g., Hyvä Checkout) (MAG-590)

**[View diff](https://github.com/payplug/payplug-magento2/compare/v4.6.1...v4.6.2)**

### Fixed

- Fix exception when selecting PayPlug payment method in checkout (e.g., Hyvä Checkout) [#339](https://github.com/payplug/payplug-magento2/pull/339)

## [4.6.1](https://github.com/payplug/payplug-magento2/releases/tag/v4.6.1) - 2025-12-24

### Features

- Fix Cron auto-capture failing on carts with missing info (MAG-562)
- Fix OAUTH2 login UI with Legacy panel causing confusion (MAG-564)
- Fix error when configuring Payplug on a specific website scope (MAG-574)
- Improve ACL permission granularity for the Payplug admin section (MAG-575)
- Fix OAuth2 login redirection blank page during Unified Auth process (MAG-578)
- Anonymize personal data logged by Payplug module (MAG-581)
- Enable REDIRECT mode for integrated payment 3DS validation (MAG-584)
- Fix unclear error message in Legacy connection mode (MAG-585)
- Fix PayPlug payment status mismatch in Authorization-only mode (MAG-593)
- Fix module 4.6.0 incompatibility with Magento 2.4.8-p3 (MAG-598)
- Fix forced captures attempted 10 minutes after authorization expiration (MAG-599)

**[View diff](https://github.com/payplug/payplug-magento2/compare/v4.6.0...v4.6.1)**

### Changed

- Improve ACL permission granularity for the Payplug admin section (MAG-575) [#330](https://github.com/payplug/payplug-magento2/pull/330)
- Anonymize personal data logged by Payplug module (MAG-581) [#329](https://github.com/payplug/payplug-magento2/pull/329)
- Enable REDIRECT mode for integrated payment 3DS validation (MAG-584) [#327](https://github.com/payplug/payplug-magento2/pull/327)

### Fixed

- Fix Cron auto-capture failing on carts with missing info [#335](https://github.com/payplug/payplug-magento2/pull/335)
- Fix OAUTH2 login UI with Legacy panel causing confusion [#334](https://github.com/payplug/payplug-magento2/pull/334)
- Fix error when configuring Payplug on a specific website scope [#317](https://github.com/payplug/payplug-magento2/pull/317)
- Fix OAuth2 login redirection blank page during Unified Auth process [#328](https://github.com/payplug/payplug-magento2/pull/328)
- Fix unclear error message in Legacy connection mode [#326](https://github.com/payplug/payplug-magento2/pull/326)
- Fix PayPlug payment status mismatch in Authorization-only mode (MAG-593) [#337](https://github.com/payplug/payplug-magento2/pull/337)
- Fix module 4.6.0 incompatibility with Magento 2.4.8-p3 [#340](https://github.com/payplug/payplug-magento2/pull/340)
- Fix forced captures attempted 10 minutes after authorization expiration [#341](https://github.com/payplug/payplug-magento2/pull/341)

## [4.6.0](https://github.com/payplug/payplug-magento2/releases/tag/v4.6.0) - 2025-11-17

> **WARNING**
> Orders are no longer assigned to the **Payment Review** status by default after creation. They will now remain in **Pending Payment** until the next valid status update, regardless of the payment flow. This prevents premature triggering of business processes such as invoicing and shipping.

### Features

- Fix Apple pay button disabled after payment cancel (MAG-537)
- Create invoice after a transaction is valid only (MAG-521)
- Add invoice increment ID into payplug transaction metadata (MAG-484)
- Enable refunds for PPro transactions in the Magento Admin (MAG-468)
- Conditional display of APMs based on the user’s country (MAG-434)
- Set initial order status to Pending Payment instead of Payment Review (MAG-517)
- Unified authentication : restrict "live" mode when Merchant KYC is uncompleted (MAG-526)
- Missing magento transaction entity on Authorization Only orders (MAG-540)
- Fix transaction ID on refund. Check existing refund transaction durant IPN callback (MAG-542)
- Fix auto-capture cron marking orders as Failed Capture (MAG-546)
- Fix invoicing / refunding concurrency (IPN vs PaymentReturn) by moving logics to message queue system (MAG-541)
- Add enriched metadata on refund lines (MAG-467)
- Fix JS dependency missing for Installment Plan payment method (MAG-554)
- Fix order status on Payment Return with Autorization only mode (MAG-555)
- Fix order status (stays on payment_review state) when invoice is generated manually (MAG-571)

**[View diff](https://github.com/payplug/payplug-magento2/compare/v4.5.0...v4.6.0)**

### Added

- Add invoice increment ID into payplug transaction metadata [#291](https://github.com/payplug/payplug-magento2/pull/291)
- Enable refunds for PPro transactions in the Magento Admin [#292](https://github.com/payplug/payplug-magento2/pull/292)
- Add enriched metadata on refund lines [#307](https://github.com/payplug/payplug-magento2/pull/307)
- Missing magento transaction entity on Authorization Only orders [#311](https://github.com/payplug/payplug-magento2/pull/311)

### Changed

- Create invoice after a transaction is valid only [#290](https://github.com/payplug/payplug-magento2/pull/290)
- Conditional display of APMs based on the user’s country [#298](https://github.com/payplug/payplug-magento2/pull/298)
- Set initial order status to Pending Payment instead of Payment Review [#306](https://github.com/payplug/payplug-magento2/pull/306)

### Fixed

- Fix Apple pay button disabled after payment cancel [#305](https://github.com/payplug/payplug-magento2/pull/305)
- Fix checkout payment methods render on Safari [#304](https://github.com/payplug/payplug-magento2/pull/304)
- Unified authentication : restrict "live" mode when Merchant KYC is uncompleted [#310](https://github.com/payplug/payplug-magento2/pull/310)
- Fix transaction ID on refund. Check existing refund transaction durant IPN callback [#312](https://github.com/payplug/payplug-magento2/pull/312)
- Fix invoicing / refunding concurrency (IPN vs PaymentReturn) by moving logics to message queue system [#314](https://github.com/payplug/payplug-magento2/pull/314)
- Fix method parameter type [#314](https://github.com/payplug/payplug-magento2/pull/314/commits/0a0e667f7ba3e6b254a83ef010ffec5f0920c2b1)
- Fix auto-capture cron marking orders as Failed Capture [#313](https://github.com/payplug/payplug-magento2/pull/313)
- Fix JS dependency missing for Installment Plan payment method [#315](https://github.com/payplug/payplug-magento2/pull/315)
- Fix order status on Payment Return with Autorization only mode [#316](https://github.com/payplug/payplug-magento2/pull/316)
- Fix order status (stays on payment_review state) when invoice is generated manually [#319](https://github.com/payplug/payplug-magento2/pull/319)

## [4.5.0](https://github.com/payplug/payplug-magento2/releases/tag/v4.5.0) - 2025-09-03

### Features

- Implement OAuth2 redirect handling and securely store credentials
- Set up automatic JWT generation and refresh
- Integrate OAuth2 login UI (button)
- Sign every API request with the JWT token
- Support both legacy authentication and OAuth2 (dual mode)

**[View diff](https://github.com/payplug/payplug-magento2/compare/v4.4.0...v4.5.0)**

## [4.4.0](https://github.com/payplug/payplug-magento2/releases/tag/v4.4.0) - 2025-06-24

### Features

- Add Apple Pay button to product view page
- Add Apple Pay button to cart page
- Add Chrome and Firefox Apple button support
- Limit the module to PHP >=8.1 and <8.5
- Update payplug/payplug-php dependency version for PHP 8.4 compatibility

**[View diff](https://github.com/payplug/payplug-magento2/compare/v4.3.0...v4.4.0)**

### Added

- Add Apple Pay conditionnal render from configuration [#263](https://github.com/payplug/payplug-magento2/pull/263)
- Add Apple Pay button to product view page [#275](https://github.com/payplug/payplug-magento2/pull/275)
- Add Apple Pay button to cart page [#275](https://github.com/payplug/payplug-magento2/pull/275) [#252](https://github.com/payplug/payplug-magento2/pull/252) [#251](https://github.com/payplug/payplug-magento2/pull/251)
- Add Apple Pay workflowType parameter to place order fetch [#250](https://github.com/payplug/payplug-magento2/pull/250)
- Add Chrome and Firefox Apple button support [#272](https://github.com/payplug/payplug-magento2/pull/272)


### Changed

- Upgrade Apple Pay JS SDK version [#272](https://github.com/payplug/payplug-magento2/pull/272)
- Enable CB by default and expose brand selector in Apple Pay [#272](https://github.com/payplug/payplug-magento2/pull/272)
- Prevent invoice auto-generation on Apple Pay [#275](https://github.com/payplug/payplug-magento2/pull/275)
- Change payplug/payplug-php dependency version

### Fixed

- Fix apple pay payments on cart page when having configurable products in cart [#275](https://github.com/payplug/payplug-magento2/pull/275)

## [4.3.4](https://github.com/payplug/payplug-magento2/releases/tag/v4.3.4) - 2025-04-28

### Features

- Add Payplug information block on the back-office ondemand orders. (MAG-440)
- Allow to capture a deferred payment even with an order increment id in the metadata. (MAG-453)
- Fix cropped logo in Oney popin. (MAG-424)
- Fix the installment plan payment not redirecting to the failure page post checkout. (MAG-441)

**[View diff](https://github.com/payplug/payplug-magento2/compare/v4.3.3...v4.3.4)**

### Added

- Add Payplug information block on the back-office ondemand orders. [#269](https://github.com/payplug/payplug-magento2/pull/269)

### Changed

- Allow to capture a deferred payment even with an order increment id in the metadata. [#270](https://github.com/payplug/payplug-magento2/pull/270)

### Fixed

- Fix cropped logo in Oney popin. [#266](https://github.com/payplug/payplug-magento2/pull/266)
- Fix the installment plan payment not redirecting to the failure page post checkout. [#268](https://github.com/payplug/payplug-magento2/pull/268)

## [4.3.3](https://github.com/payplug/payplug-magento2/releases/tag/v4.3.3) - 2025-04-09

### Features

- Fix an SQL crash happening in some case when updating the order. (MAG-443)

**[View diff](https://github.com/payplug/payplug-magento2/compare/v4.3.2...v4.3.3)**

### Fixed

- Fix an SQL crash happening in some case when updating the order. (MAG-443). [#262](https://github.com/payplug/payplug-magento2/pull/262)

## [4.3.2](https://github.com/payplug/payplug-magento2/releases/tag/v4.3.2) - 2025-04-02

### Features

- Add a documentation about the crons usage in 4.3.0 and above. (MAG-433)
- Fix a case were the crons didn't unstucks payments from payment_review state. (SMP-3116)
- Fix the checkout redirecting to success page on non-authorized deferred payment. (MAG-436)
- Fix an error on creating an order from the back-office, due to null iso code. (MAG-437)

**[View diff](https://github.com/payplug/payplug-magento2/compare/v4.3.0...v4.3.2)**

### Added

- Add a documentation about the crons usage in 4.3.0 and above. [#257](https://github.com/payplug/payplug-magento2/pull/257)

### Fixed

- Fix a case were the crons didn't unstucks payments from payment_review state in production. [#257](https://github.com/payplug/payplug-magento2/pull/257)
- Prevent the manual order creation to crash if isoCode is null [#258](https://github.com/payplug/payplug-magento2/pull/258)
- Prevent successfully redirecting to the success page if the deferred payment isn't authorized. [#259](https://github.com/payplug/payplug-magento2/pull/259)

## [4.3.0](https://github.com/payplug/payplug-magento2/releases/tag/v4.3.0) - 2025-03-19

### Features

- Support Magento 2.4.7
- Add a queue system feature 
- Synchronize orders status with Payplug servers post payment 
- Compatibility with php 8.1 and Magento 2.4.4 and above. (SMP-3032)

**[View diff](https://github.com/payplug/payplug-magento2/compare/v4.2.0...v4.3.0)**

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
