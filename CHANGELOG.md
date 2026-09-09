# Changelog

## 1.1.1 - 2026-09-09

- Fix: an unrecognised Return Fees value (any custom text such as `restocking` or `10% restocking fee`) mapped to `https://schema.org/FreeReturn` in the new inline Offer policy, advertising free returns on a store that charges them. It now maps to `https://schema.org/ReturnFeesCustomerResponsibility`, matching the behaviour of the pre-1.1.0 top-level node.
- `ReturnPolicyProvider` now delegates its return-fee mapping to `Helper\Config::getReturnFeesSchemaUrl()` so the inline and top-level policies cannot drift apart again.
- Fix: the return and shipping country codes are upper-cased, so a lower-case admin entry such as `de` emits `DE` instead of an invalid `addressCountry`.
- Added unit coverage for the fee and return-method mappings, the country fallback chain, and the merchant fields surviving a missing product attribute.

## 1.1.0 - 2026-09-09

- New: every product Offer carries an inline `hasMerchantReturnPolicy` (`MerchantReturnPolicy`) and `shippingDetails` (`OfferShippingDetails`), the three fields Search Console reports as missing on the Merchant listings report. Virtual and downloadable products get a `MerchantReturnNotPermitted` policy and a zero-rate, zero-day shipping entry instead.
- New: the Brand name chain ends with the store name (`general/store_information/name`) so every Product node carries a typed `Brand` object even when the brand attribute and Default Brand Name are both empty.
- Fix: a product whose final price is 0 (free downloadable, free virtual, or a zero-priced simple) no longer drops its whole `offers` key. The price 0 short-circuit in `ProductProvider::buildOffer()` is gone and negative prices clamp to 0.
- Fix: `ReturnPolicyProvider` no longer emits its detached top-level `MerchantReturnPolicy` node while the inline Offer field is active, so the graph never carries the policy twice.
- New admin settings under Structured Data: Enable Merchant Listing Offer Fields (master toggle), Emit Inline Return Policy, Return Applicable Country, Return Method, Emit Inline Shipping Details, Default Shipping Rate, Handling Time Min/Max, Transit Time Min/Max, Shipping Destination Country, and Fall Back Brand to Store Name.
- Per-line Delivery Methods entries still win over the new shipping defaults, and the whole feature switches off in one field to restore the pre-1.1.0 Offer shape.
- DI: `ProductProvider`'s `merchantFieldsBuilder` argument is wired explicitly in `di.xml` (Magento DI does not inject optional constructor parameters).

## 1.0.17 - 2026-08-13

- Re-release of 1.0.16; no code changes.

## 1.0.16 - 2026-08-13

- Fix: the offer builder no longer fabricates a `shippingDetails` stub (hardcoded `US` destination, free rate, 0-1 day handling) when no delivery methods are configured; the field is omitted instead.
- Fix: the inline return-policy `applicableCountry` resolves from `general/country/default` instead of a hardcoded `US`.
- Fix: the configured `default_brand` is now used as the brand fallback when the brand attribute and `brand` product data are empty (new `Config::getDefaultBrand()`).
- Fix: configured delivery methods are emitted as `shippingDetails` inside the product offer (new shared `ShippingDetailsBuilder`) instead of a detached top-level Offer node that Google cannot associate with the product. `handlingTime` is included per line via the extended `Label | Handling Min | Handling Max | Transit Min | Transit Max | Cost` format; the legacy 4-part format still parses unchanged.
- New: `BlogPostProvider` emits `BlogPosting` JSON-LD on Mageplaza_Blog post pages (optional integration, no hard Mageplaza class references); `BlogDetector` falls back to raw data keys for magic-getter post models.
- DI: `ProductProvider`'s `scopeConfig` argument is now explicitly wired in `di.xml` (Magento DI does not inject optional constructor parameters).

## 1.0.15

- Replaced typographic characters (em dashes, curly quotes, ellipsis) with plain ASCII punctuation. No functional changes.

## 1.0.14 - 2026-07-24

- Fix: the Product node was dropped entirely on stores without a `gender` product attribute. `ProductProvider` called `$product->getAttributeText('gender')` (and the configurable brand attribute) unguarded; when the attribute code does not exist on the store, Magento's `getAttribute()` returns `false` and the call crashes with `Call to a member function getSource() on false`. The provider-level try/catch swallowed the error, so every product page silently lost the whole Product/Offer/AggregateRating node while dependent nodes (Review `itemReviewed`, SaleEvent offer) kept referencing it. Missing attributes are now treated as empty values.
- Added `AbstractProvider::safeAttributeText()` and routed all unguarded `getAttributeText()` calls (`ProductProvider` brand + audience, `BrandProvider`) through it.
- Added unit regression test covering a product whose store has no `gender` attribute.

## 1.0.13 - 2026-07-07

- Code cleanup: removed redundant inline comments and docblocks from the PHP source. No functional changes.

## 1.0.12 - 2026-06-18

- Rewrote README to match gold template structure: added Quick Answer block, Who Is It For section, grouped Key Features, full Configuration table sourced from system.xml, FAQ section, and Quick Links table. Updated canonical to the live product page. Removed old commercemarketplace.adobe.com links and replaced them with the live product page URL.

## 1.0.10 - 2026-05-13

- Fix BlogDetector dropping every post on blog modules whose post table uses an active-flag column other than `is_active`. The detector hard-coded `addFieldToFilter('is_active', 1)`, which raised `SQLSTATE[42S22]: Column not found: 'is_active' in 'where clause'` on tables exposing `enabled` or `status` instead. The exception was swallowed by the surrounding try/catch and silently produced zero structured-data entries (and zero rows for the XML sitemap blog contributor that consumes this detector).
- `BlogDetector::resolveActiveColumn()` now introspects the collection's main table via `describeTable()` and picks whichever of `is_active`, `enabled`, or `status` actually exists. When none are present, the filter is skipped rather than producing an invalid query - better to over-include than to drop every post.
- `ResourceConnection` is now injected into `BlogDetector` to support the schema lookup.
