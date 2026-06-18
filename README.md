<!-- SEO Meta -->
<!--
  Title: Magento 2 Structured Data Extension: JSON-LD Schema.org for Google Rich Results (Hyva + Luma) | Panth Infotech
  Description: Panth Structured Data emits one JSON-LD block per page covering Product, Offer/AggregateOffer, BreadcrumbList, Organization, WebSite, ItemList, Review, FAQPage, Article, VideoObject, MerchantReturnPolicy, and more. Full product-type coverage (simple, configurable, bundle, grouped). Strips Magento's native duplicate markup. Theme-agnostic, FPC-friendly. Works on Magento 2.4.4 to 2.4.8 and PHP 8.1 to 8.4. Built by Top Rated Plus Magento developer Kishan Savaliya.
  Keywords: magento 2 structured data, magento 2 json-ld, magento 2 schema.org, magento 2 rich results, magento 2 product schema, magento 2 breadcrumb schema, magento 2 organization schema, magento 2 aggregateoffer, magento 2 faqpage schema, hyva structured data, luma structured data, magento google rich results, panth structured data, magento 2 seo schema extension
  Author: Kishan Savaliya (Panth Infotech)
  Canonical: https://kishansavaliya.com/magento-2-structured-data.html
-->

# Magento 2 Structured Data Extension: JSON-LD Schema.org for Google Rich Results (Hyva + Luma)

[![Magento 2.4.4 - 2.4.8](https://img.shields.io/badge/Magento-2.4.4%20--%202.4.8-orange?logo=magento&logoColor=white)](https://magento.com)
[![PHP 8.1 - 8.4](https://img.shields.io/badge/PHP-8.1%20--%208.4-blue?logo=php&logoColor=white)](https://php.net)
[![Hyva + Luma](https://img.shields.io/badge/Themes-Hyva%20%2B%20Luma-14b8a6)](https://www.hyva.io)
[![Live Demo & Details](https://img.shields.io/badge/Live%20Demo%20%26%20Details-magento--2--structured--data-0D9488?style=flat)](https://kishansavaliya.com/magento-2-structured-data.html)
[![Packagist](https://img.shields.io/badge/Packagist-mage2kishan%2Fmodule--structured--data-orange?logo=packagist&logoColor=white)](https://packagist.org/packages/mage2kishan/module-structured-data)
[![Upwork Top Rated Plus](https://img.shields.io/badge/Upwork-Top%20Rated%20Plus-14a800?logo=upwork&logoColor=white)](https://www.upwork.com/freelancers/~016dd1767321100e21)
[![Website](https://img.shields.io/badge/Website-kishansavaliya.com-0D9488)](https://kishansavaliya.com)

<p align="center">
  <img src="docs/images/hero-banner.png" alt="Magento 2 Structured Data JSON-LD extension for Google Rich Results, Hyva and Luma ready, built by Kishan Savaliya (Panth Infotech), Top Rated Plus on Upwork." width="100%" />
</p>

> **One JSON-LD block per page, zero duplicate schemas.** Panth Structured Data adds a single `<script type="application/ld+json">` to every page with a deduplicated `@graph` covering Product, Offer/AggregateOffer, BreadcrumbList, Organization, WebSite, ItemList, Review, FAQPage, Article, VideoObject, and MerchantReturnPolicy. It also removes Magento's built-in duplicate markup. Identical output on Hyva and Luma, no theme overrides needed.

**Product page:** [kishansavaliya.com/magento-2-structured-data.html](https://kishansavaliya.com/magento-2-structured-data.html)

---

## Quick Answer

**What is Panth Structured Data?** It is a Magento 2 JSON-LD extension that outputs schema.org structured data on every page so Google can show rich results (price, rating stars, breadcrumbs, FAQs, merchant listings) for your store.

**What does it add to my store?**

- A **single `@graph` JSON-LD block** per page with all applicable schema.org nodes, no duplicate scripts.
- **Full product type coverage** for simple, configurable, bundle, grouped, virtual, and downloadable products.
- **Organization, WebSite + SearchAction, BreadcrumbList, and ItemList** on non-product pages.
- **FAQPage auto-extraction**, Article, VideoObject, MerchantReturnPolicy, and specialist nodes like Pros/Cons, Energy Label, Certifications, and Sale Event.
- **Native markup removal** to stop Magento's built-in JSON-LD from clashing with this module's output.

**Which themes are supported?** Both **Hyva** and **Luma** (and any other Magento theme). The head block attaches through `head.additional` in standard layout XML, with no JS or RequireJS involved.

**What does it need?** Magento 2.4.4 to 2.4.8, PHP 8.1 to 8.4, and the free `mage2kishan/module-core` package.

---

## Need Custom Magento 2 Development?

> **Get a free quote for your project in 24 hours** for custom modules, Hyva themes, performance work, M1 to M2 migrations, and Adobe Commerce Cloud.

<p align="center">
  <a href="https://kishansavaliya.com/get-quote">
    <img src="https://img.shields.io/badge/Get%20a%20Free%20Quote%20%E2%86%92-Reply%20within%2024%20hours-DC2626?style=for-the-badge" alt="Get a Free Quote" />
  </a>
</p>

<table>
<tr>
<td width="50%" align="center">

### Kishan Savaliya
**Top Rated Plus on Upwork**

[![Hire on Upwork](https://img.shields.io/badge/Hire%20on%20Upwork-Top%20Rated%20Plus-14a800?style=for-the-badge&logo=upwork&logoColor=white)](https://www.upwork.com/freelancers/~016dd1767321100e21)

100% Job Success - 10+ Years Magento Experience
Adobe Certified - Hyva Specialist

</td>
<td width="50%" align="center">

### Panth Infotech Agency
**Magento Development Team**

[![Visit Agency](https://img.shields.io/badge/Visit%20Agency-Panth%20Infotech-14a800?style=for-the-badge&logo=upwork&logoColor=white)](https://www.upwork.com/agencies/1881421506131960778/)

Custom Modules - Theme Design - Migrations
Performance - SEO - Adobe Commerce Cloud

</td>
</tr>
</table>

**Visit our website:** [kishansavaliya.com](https://kishansavaliya.com) &nbsp;|&nbsp; **Get a quote:** [kishansavaliya.com/get-quote](https://kishansavaliya.com/get-quote)

---

## Table of Contents

- [Who Is It For](#who-is-it-for)
- [Key Features](#key-features)
- [Compatibility](#compatibility)
- [Installation](#installation)
- [Configuration](#configuration)
- [How It Works](#how-it-works)
- [Schema.org Nodes Emitted](#schemaorg-nodes-emitted)
- [FAQ](#faq)
- [Support](#support)
- [About Panth Infotech](#about-panth-infotech)
- [Quick Links](#quick-links)

---

## Who Is It For

- **Stores that want Google rich results** (price, rating stars, breadcrumbs, FAQ dropdowns, merchant listings) without writing any schema code by hand.
- **Merchants with large catalogs** that include configurable, bundle, and grouped products, where each type needs its own offer structure to validate correctly.
- **Hyva storefronts** that need a schema module with no jQuery, RequireJS, or Knockout dependency.
- **Stores already using Magento's native JSON-LD** and getting duplicate-schema warnings in Search Console, since this module removes the native output and replaces it with a clean single `@graph`.
- **Multi-store setups** where each store view needs its own Organization name, social profiles, and language-specific structured data.

---

## Key Features

### One Block, One Graph, Zero Duplicates

- **Single `<script type="application/ld+json">` per page** with a deduplicated `@graph` covering every applicable node.
- **Aggregator with deep-merge** so two providers contributing to the same `@id` produce one coherent node, not two conflicting objects.
- **Native markup removal plugin** strips Magento's built-in `application/ld+json` scripts from `product.info.main`, `breadcrumbs`, and `product.price.final`, and also removes `itemprop`/`itemscope`/`itemtype` microdata attributes from those blocks.
- **XSS-safe JSON payload** where every `</` is escaped to `<\/` so a product name with HTML can never break the script tag.

### Full Product Type Coverage

- **Simple and virtual/downloadable** emit `Product + Offer` with price, availability, itemCondition, seller, priceValidUntil, and shippingDetails.
- **Configurable** emits `Product + AggregateOffer` with lowPrice, highPrice, offerCount, and one child `Offer` per visible variant.
- **Bundle (dynamic price)** emits `Product + AggregateOffer` with lowPrice and highPrice from the option price range.
- **Bundle (fixed price)** emits `Product + Offer` with the fixed final price.
- **Grouped** emits `Product + AggregateOffer` with one child `Offer` per grouped SKU (name, price, availability, SKU, URL).
- **ProductGroup + hasVariant** (opt-in) adds richer variant modelling for configurable products via `variesBy` on color, size, and material.

### Identity Nodes

- **Organization** with name, URL, logo, legalName, phone, email, full PostalAddress, and a `sameAs` array from seven dedicated social-profile fields (Facebook, Twitter/X, Instagram, LinkedIn, YouTube, Pinterest, TikTok) plus a freeform additional URLs field.
- **WebSite** with `SearchAction` pointing at the store's catalogsearch URL, which makes the site eligible for Google Sitelinks Search Box.
- **Seller** merged into `#organization` by default, with an option to promote the type to LocalBusiness, Store, or OnlineStore.

### Content Nodes

- **BreadcrumbList** with full category hierarchy for products (Home, Category, Subcategory, Product), using configurable priority weights when enabled.
- **ItemList** on category pages, listing products with their position for rich carousel eligibility.
- **Review + AggregateRating** from Magento's approved product reviews, scaled to a 1-5 rating with ISO 8601 datePublished on each review.
- **FAQPage** auto-extracted from `<h2>Question?</h2><p>Answer.</p>` and `<h3>` patterns in product, category, and CMS descriptions. Needs at least two Q&A pairs.
- **Article** on CMS pages whose URL identifier starts with `blog/`, `news/`, or `articles/`, or whose meta keywords include `article`.
- **VideoObject** for product media gallery entries of type `external-video` (YouTube/Vimeo).

### Merchant and Pricing Extras

- **MerchantReturnPolicy** with applicableCountry, merchantReturnDays, returnMethod, and returnFees mapped to the correct `ReturnFeesEnumeration` URL.
- **Sale Event** (opt-in) adds `priceSpecification` with `validFrom` and `validThrough` on products with an active special price.
- **Delivery Methods** and **Payment Methods** added to Offer via admin text fields.
- **Multi-region shipping auto-detect** reads Magento's table-rate and flat-rate config when no manual delivery methods are set.
- **priceValidUntil** from `special_to_date`, falling back to an admin default date, then automatically to one year ahead.
- **Availability** with six levels: InStock, LimitedAvailability (below configurable threshold), OutOfStock, BackOrder, PreOrder, and Discontinued.

### Specialist Nodes (All Opt-in)

- **Brand** node using a configurable product attribute (default `manufacturer`) with a fallback default brand name.
- **Certifications** from a textarea attribute in `Authority | Name | ID` format, emitted as `hasCertification`.
- **Energy Efficiency Label (EU)** with grades A-G emitted as `hasEnergyConsumptionDetails`.
- **Pros/Cons** from two textarea attributes emitted as `positiveNotes` and `negativeNotes` ItemLists.
- **Custom Properties** - an admin JSON field deep-merged into the Product node for anything outside the built-in schemas.

### Production-Grade Implementation

- **Theme-agnostic** head block attached to `head.additional` via standard layout XML. No JS, no RequireJS, no `x-magento-init`. Same output on Hyva, Luma, Breeze, and any custom theme.
- **Fully cacheable** block, so FPC bakes the JSON-LD into the page head and providers only run on uncached renders.
- **ISO 8601 dates** on every date field, with strict `DateTimeImmutable` formatting.
- **Multi-select attribute safety** so a product with a multi-select brand or gender attribute never silently kills the whole Product node.
- **Store view scoped config** so every field works per-store in a multi-store install.

---

## Compatibility

| Requirement | Versions Supported |
|---|---|
| Magento Open Source | 2.4.4, 2.4.5, 2.4.6, 2.4.7, 2.4.8 |
| Adobe Commerce | 2.4.4, 2.4.5, 2.4.6, 2.4.7, 2.4.8 |
| Adobe Commerce Cloud | 2.4.4 to 2.4.8 |
| PHP | 8.1.x, 8.2.x, 8.3.x, 8.4.x |
| MySQL | 8.0+ |
| MariaDB | 10.4+ |
| Hyva Theme | 1.0+ (native support) |
| Luma Theme | Native support |
| Required Dependency | `mage2kishan/module-core` (free) |

Works standalone and composes cleanly with `Panth_AdvancedSEO`, `Panth_Faq`, and `Panth_Testimonials` when installed.

---

## Installation

### Composer Installation (Recommended)

```bash
composer require mage2kishan/module-structured-data
bin/magento module:enable Panth_Core Panth_StructuredData
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f
bin/magento cache:flush
```

### Manual Installation via ZIP

1. Download the latest release from [Packagist](https://packagist.org/packages/mage2kishan/module-structured-data) or from the [product page](https://kishansavaliya.com/magento-2-structured-data.html).
2. Extract the contents to `app/code/Panth/StructuredData/` in your Magento installation.
3. Make sure `Panth_Core` is installed too (required dependency).
4. Run the commands above starting from `bin/magento module:enable`.

### Verify Installation

```bash
bin/magento module:status Panth_StructuredData
# Expected: Module is enabled

# Every page now carries one JSON-LD block:
curl -ks https://your-store.test/ | grep -c 'application/ld+json'
# Expected: 1
```

After install, open:
```
Admin -> Stores -> Configuration -> Panth Extensions -> Structured Data
```

---

## Configuration

Go to **Stores -> Configuration -> Panth Extensions -> Structured Data**.

### Social Profiles (Organization sameAs)

| Setting | Group | Default | Description |
|---|---|---|---|
| Facebook URL | Social Profiles | (empty) | Full Facebook page URL added to Organization sameAs |
| Twitter (X) URL | Social Profiles | (empty) | Full Twitter/X profile URL |
| Instagram URL | Social Profiles | (empty) | Full Instagram profile URL |
| LinkedIn URL | Social Profiles | (empty) | Full LinkedIn company page URL |
| YouTube URL | Social Profiles | (empty) | Full YouTube channel URL |
| Pinterest URL | Social Profiles | (empty) | Full Pinterest profile URL |
| TikTok URL | Social Profiles | (empty) | Full TikTok profile URL |

### Organization Details

| Setting | Group | Default | Description |
|---|---|---|---|
| Legal Name | Organization Details | (empty) | `legalName` on the Organization node |
| Logo URL | Organization Details | (empty) | Full URL to the organization logo image |
| Phone | Organization Details | (empty) | Overrides Store Information phone for Organization contactPoint |
| Email | Organization Details | (empty) | Contact email for Organization contactPoint |
| Street Address | Organization Details | (empty) | Street line of the PostalAddress |
| City / Locality | Organization Details | (empty) | City for PostalAddress |
| State / Region | Organization Details | (empty) | Region for PostalAddress |
| Postal Code | Organization Details | (empty) | Postal code for PostalAddress |
| Country (ISO 3166-1 alpha-2) | Organization Details | (empty) | e.g. US, GB, DE |
| Additional sameAs URLs | Organization Details | (empty) | One URL per line, appended to the sameAs array |
| Founder @id Reference | Organization Details | (empty) | Optional Person @id cross-reference for the founder property |

Leave empty to fall back to core Magento Store Information.

### Structured Data (JSON-LD)

| Setting | Group | Default | Description |
|---|---|---|---|
| Enable Product | Structured Data | Yes | Emits Product + Offer on product pages |
| Enable Breadcrumb | Structured Data | Yes | Emits BreadcrumbList on all pages |
| Enable Organization | Structured Data | Yes | Emits Organization on every page |
| Enable WebSite / SiteLinks | Structured Data | Yes | Emits WebSite + SearchAction |
| Enable Auto-Extracted FAQ Schema | Structured Data | Yes | Scans descriptions for heading/paragraph Q&A pairs |
| Enable Article | Structured Data | Yes | Emits Article on blog-like CMS pages |
| Enable Review | Structured Data | Yes | Emits Review + AggregateRating on product pages with reviews |
| Enable VideoObject | Structured Data | Yes | Emits VideoObject for external-video gallery entries |
| Enable Brand | Structured Data | Yes | Emits Brand node using the brand attribute |
| Enable Seller | Structured Data | Yes | Emits Seller merged into the Organization node |
| Configurable: Multi-Offer | Structured Data | Yes | Emits one Offer per configurable child SKU |
| Remove Native Magento JSON-LD Markup | Structured Data | Yes | Strips Magento's native ld+json on product and breadcrumb blocks |
| Return Policy Days | Structured Data | 30 | merchantReturnDays - set to 0 to disable MerchantReturnPolicy |
| Brand Attribute Code | Structured Data | manufacturer | Product attribute used for Brand name |
| GTIN Attribute Code | Structured Data | (empty) | Product attribute for gtin/gtin13/ean - leave empty to omit |
| MPN Attribute Code | Structured Data | (empty) | Product attribute for Manufacturer Part Number - leave empty to omit |
| Enable Product List Schema (ItemList) | Structured Data | Yes | Emits ItemList on category pages |
| Accepted Payment Methods | Structured Data | (empty) | One method per line (Visa, Mastercard, PayPal) |
| Delivery Methods | Structured Data | (empty) | One method per line (Standard Shipping, Express) |
| Product Condition | Structured Data | New | Default itemCondition for all product Offers |
| Default Price Valid Until | Structured Data | (empty) | YYYY-MM-DD fallback when product has no special_to_date |
| Custom JSON-LD Properties (Product) | Structured Data | (empty) | JSON object deep-merged into the Product node on every PDP |
| Enable ProductGroup + hasVariant | Structured Data | No | Emits ProductGroup with variesBy for configurable products |
| Enable Pros/Cons | Structured Data | No | Emits positiveNotes / negativeNotes ItemLists |
| Pros Attribute Code | Structured Data | product_pros | Textarea attribute holding pros, one per line |
| Cons Attribute Code | Structured Data | product_cons | Textarea attribute holding cons, one per line |
| Enable Energy Efficiency Label (EU) | Structured Data | No | Emits hasEnergyConsumptionDetails with grades A-G |
| Energy Class Attribute Code | Structured Data | energy_class | Product attribute for energy grade |
| Enable Product Certifications | Structured Data | No | Emits hasCertification from a textarea attribute |
| Certification Attribute Code | Structured Data | certifications | Textarea, one Authority / Name / ID per line |
| Enable Sale Event / Special Price Details | Structured Data | Yes | Emits priceSpecification with validFrom and validThrough |
| Seller Business Type | Structured Data | Organization | Organization, LocalBusiness, Store, or OnlineStore |
| Default Brand Name | Structured Data | (empty) | Fallback brand when product has no brand attribute |
| Return Policy Type | Structured Data | Refund | Refund or exchange |
| Return Fees | Structured Data | free | free or a custom description |
| Limited Stock Threshold | Structured Data | 5 | Qty below this emits LimitedAvailability instead of InStock |

### Breadcrumbs

| Setting | Group | Default | Description |
|---|---|---|---|
| Enable Breadcrumb Priority | Breadcrumbs | No | When Yes, product breadcrumbs use category priority weights |
| Breadcrumb Format | Breadcrumbs | Longest (deepest) | Tiebreaker when priorities are equal: shortest or longest path |

Every field is store view scoped. After any change, flush caches:

```bash
bin/magento cache:flush config full_page
```

---

## How It Works

The module follows a pipeline pattern: admin config feeds a set of providers, an aggregator collects and merges their output, and the head block writes one JSON-LD script tag.

```
Admin -> Stores -> Configuration -> Panth Extensions -> Structured Data
        (toggles, attribute codes, social URLs, organization fields)
                        |
                Helper\Config reads every field
                        |
        Aggregator (pipeline of 24 providers)
          OrganizationProvider  -> Organization
          WebsiteProvider       -> WebSite + SearchAction
          BreadcrumbProvider    -> BreadcrumbList
          ProductProvider       -> Product + scalar Offer extras
          FaqExtractor          -> FAQPage (if Q&A pairs found)
          ReviewProvider        -> Review + AggregateRating
          VideoProvider         -> VideoObject (external-video entries)
          CmsArticleProvider    -> Article (blog-like CMS pages)
          ReturnPolicyProvider  -> MerchantReturnPolicy
          BrandProvider         -> Brand
          ProductListProvider   -> ItemList (category pages)
          PaymentMethodProvider -> Offer (acceptedPaymentMethod)
          DeliveryMethodProvider-> Offer (shippingDetails)
          MultiRegionShipping   -> Offer (multi-region shippingDetails)
          ConfigurableOffer     -> Product -> AggregateOffer
          GroupedOffer          -> Product -> AggregateOffer (grouped)
          BundleOffer           -> Product -> AggregateOffer (bundle)
          CustomProperties      -> Product + custom JSON
          EnergyLabel           -> Product + hasEnergyConsumption
          Certification         -> Product + hasCertification
          SaleEvent             -> Product + priceSpecification
          ProductGroup          -> ProductGroup + hasVariant
          ProsCons              -> Product + positiveNotes/negativeNotes
          SellerProvider        -> Organization (merged)
                        |
          Deep-merge by @id, deduplicate
                        |
        <script type="application/ld+json" data-panth-seo="jsonld">
          { "@context": "https://schema.org", "@graph": [...] }
        </script>

        RemoveNativeMarkupPlugin strips Magento's native ld+json
        and microdata from product.info.main, breadcrumbs,
        product.price.final
```

The aggregator does six things for each render:

1. **Applicability check** - each provider exposes `isApplicable()`. Product providers return false on CMS pages, FAQExtractor defers to `Panth_Faq` when that module is present, etc.
2. **Config gate** - reads the Yes/No admin toggle for each provider code. Disabled providers are skipped.
3. **Node collection** - calls `getJsonLd()` on each applicable, enabled provider.
4. **Deep-merge by @id** - two nodes with the same `@id` are merged key by key. Scalars from later providers win, arrays recurse, new keys are added. This is how `ProductProvider` and `ConfigurableOfferProvider` both contribute to one coherent Product node.
5. **Document shape** - one node in `@graph` gets inlined under `@context`; multiple nodes get wrapped in `@graph`.
6. **XSS safety** - every `</` in the JSON is replaced with `<\/`.

---

## Schema.org Nodes Emitted

| Node | When |
|---|---|
| `Organization` | Every page |
| `WebSite` + `SearchAction` | Every page (toggle) |
| `BreadcrumbList` | Product, category, CMS pages with 2+ items |
| `Product` + `Offer` | Simple, virtual, downloadable product pages |
| `Product` + `AggregateOffer` | Configurable, bundle, grouped product pages |
| `Review` + `AggregateRating` | Product pages with approved reviews |
| `MerchantReturnPolicy` | Product pages when return_policy_days is above 0 |
| `FAQPage` | Any page with 2 or more Q&A pairs in description |
| `Article` | Blog-like CMS pages |
| `VideoObject` | Product pages with external-video gallery entries |
| `ItemList` | Category pages with products |
| `Brand` | Product pages when brand attribute has a value |
| `ProductGroup` + `hasVariant` | Configurable products (opt-in) |

---

## FAQ

### Does it work on Hyva themes?

Yes. The head block attaches through `head.additional` in standard Magento layout XML. There is no JS, RequireJS, or `x-magento-init` involved. The same PHP-rendered `<script>` tag goes out on Hyva, Luma, Breeze, and any other Magento theme.

### Will the JSON-LD slow down my store?

No. The head block is marked `cacheable="true"` so FPC bakes the full JSON-LD payload alongside the rest of the `<head>`. Providers only execute on uncached renders; cached page hits serve the pre-rendered script tag with zero PHP overhead.

### Can I turn off individual schema types?

Yes. Every provider has its own Yes/No toggle under **Stores -> Configuration -> Panth Extensions -> Structured Data**. You can disable Product, Breadcrumb, Organization, WebSite, FAQ, Article, Review, VideoObject, Brand, Seller, ItemList, ProductGroup, Pros/Cons, Energy Label, Certifications, Sale Event, and native markup removal independently.

### Which product types are supported?

Simple, configurable, bundle (dynamic and fixed price), grouped, virtual, and downloadable products all produce correct structured data. Configurable and grouped products each emit one child `Offer` per variant or grouped SKU.

### Does it remove Magento's own JSON-LD?

Yes, when the "Remove Native Magento JSON-LD Markup" toggle is on (default Yes). A plugin on `AbstractBlock::afterToHtml` removes Magento's built-in `application/ld+json` scripts from `product.info.main`, `breadcrumbs`, and `product.price.final`. It also strips `itemprop`, `itemscope`, and `itemtype` microdata attributes from those blocks.

### Does it work with multi-store setups?

Yes. Every admin field is store view scoped, so each store view can have its own Organization data, social profiles, toggles, attribute codes, and language-specific content.

### Can I add custom schema properties?

Yes, three ways. First, use the "Custom JSON-LD Properties (Product)" admin field, which accepts a JSON object that gets deep-merged into every Product node. Second, populate standard Magento product attributes like `manufacturer`, `gender`, or `meta_description` for per-product overrides. Third, write a custom provider class implementing `StructuredDataProviderInterface` and register it in `di.xml`.

### Does it work alongside Panth_AdvancedSEO or Panth_Faq?

Yes. `FaqExtractor` defers to `Panth_Faq` on its own routes when that module is installed. `ReviewProvider` defers to `Panth_Testimonials` on the testimonials route. `Panth_AdvancedSEO` shares the master switch when present.

### Is Panth Core required?

Yes. `mage2kishan/module-core` is a free required dependency that Composer pulls in for you automatically.

---

## Support

| Channel | Contact |
|---|---|
| Product Page | [kishansavaliya.com/magento-2-structured-data.html](https://kishansavaliya.com/magento-2-structured-data.html) |
| Email | kishansavaliyakb@gmail.com |
| Website | [kishansavaliya.com](https://kishansavaliya.com) |
| WhatsApp | +91 84012 70422 |
| GitHub Issues | [github.com/mage2sk/module-structured-data/issues](https://github.com/mage2sk/module-structured-data/issues) |
| Upwork (Top Rated Plus) | [Hire Kishan Savaliya](https://www.upwork.com/freelancers/~016dd1767321100e21) |
| Upwork Agency | [Panth Infotech](https://www.upwork.com/agencies/1881421506131960778/) |

Response time: 1-2 business days.

### Need Custom Magento Development?

Looking for **custom Magento module development**, **Hyva theme work**, **store migrations**, or **performance tuning**? Get a free quote in 24 hours:

<p align="center">
  <a href="https://kishansavaliya.com/get-quote">
    <img src="https://img.shields.io/badge/%F0%9F%92%AC%20Get%20a%20Free%20Quote-kishansavaliya.com%2Fget--quote-DC2626?style=for-the-badge" alt="Get a Free Quote" />
  </a>
</p>

<p align="center">
  <a href="https://www.upwork.com/freelancers/~016dd1767321100e21">
    <img src="https://img.shields.io/badge/Hire%20Kishan-Top%20Rated%20Plus-14a800?style=for-the-badge&logo=upwork&logoColor=white" alt="Hire on Upwork" />
  </a>
  &nbsp;&nbsp;
  <a href="https://www.upwork.com/agencies/1881421506131960778/">
    <img src="https://img.shields.io/badge/Visit-Panth%20Infotech%20Agency-14a800?style=for-the-badge&logo=upwork&logoColor=white" alt="Visit Agency" />
  </a>
  &nbsp;&nbsp;
  <a href="https://kishansavaliya.com/magento-2-structured-data.html">
    <img src="https://img.shields.io/badge/View%20Product%20Page-magento--2--structured--data-0D9488?style=for-the-badge" alt="View Product Page" />
  </a>
</p>

---

## About Panth Infotech

Built and maintained by **Kishan Savaliya** ([kishansavaliya.com](https://kishansavaliya.com)), a **Top Rated Plus** Magento developer on Upwork with 10+ years of eCommerce experience.

**Panth Infotech** is a Magento 2 development agency that builds high quality, security focused extensions and themes for both Hyva and Luma storefronts. The extension suite covers SEO, performance, checkout, product presentation, customer engagement, and store management, with each module built to MEQP standards and tested across Magento 2.4.4 to 2.4.8.

Browse the full extension catalog on our [Magento extensions page](https://kishansavaliya.com/magento-extensions.html) or on [Packagist](https://packagist.org/packages/mage2kishan/).

---

## Quick Links

| Resource | Link |
|---|---|
| **Product Page** | [magento-2-structured-data.html](https://kishansavaliya.com/magento-2-structured-data.html) |
| **Packagist** | [mage2kishan/module-structured-data](https://packagist.org/packages/mage2kishan/module-structured-data) |
| **GitHub** | [mage2sk/module-structured-data](https://github.com/mage2sk/module-structured-data) |
| **Website** | [kishansavaliya.com](https://kishansavaliya.com) |
| **Free Quote** | [kishansavaliya.com/get-quote](https://kishansavaliya.com/get-quote) |
| **Upwork (Top Rated Plus)** | [Hire Kishan Savaliya](https://www.upwork.com/freelancers/~016dd1767321100e21) |
| **Upwork Agency** | [Panth Infotech](https://www.upwork.com/agencies/1881421506131960778/) |
| **Email** | kishansavaliyakb@gmail.com |
| **WhatsApp** | +91 84012 70422 |

---

<p align="center">
  <strong>Ready to get Google rich results for your Magento store?</strong><br/>
  <a href="https://kishansavaliya.com/magento-2-structured-data.html">
    <img src="https://img.shields.io/badge/%F0%9F%9A%80%20See%20Structured%20Data%20%E2%86%92-Product%20Page%20%26%20Details-DC2626?style=for-the-badge" alt="See Structured Data" />
  </a>
</p>

---

**SEO Keywords:** magento 2 structured data, magento 2 json-ld, magento 2 schema.org, magento 2 rich results, magento 2 product schema, magento 2 breadcrumb schema, magento 2 organization schema, magento 2 aggregateoffer, magento 2 faqpage schema, magento 2 review schema, magento 2 article schema, magento 2 videoobject, magento 2 merchantreturnpolicy, magento 2 itemlist schema, magento 2 seo schema extension, magento 2 google rich results, magento 2 sitelinks searchbox, magento 2 productgroup hasvariant, magento 2 pros cons schema, magento 2 energy label schema, magento 2 certifications schema, magento 2 sale event schema, magento 2 configurable product schema, magento 2 bundle product schema, magento 2 grouped product schema, magento 2 structured data extension, magento 2 rich snippets, magento 2 schema generator, hyva structured data, hyva json-ld extension, luma structured data, luma schema, mage2kishan structured data, panth structured data, panth infotech, kishan savaliya magento, magento 2.4.8 structured data, php 8.4 structured data, hire magento developer, top rated plus upwork, custom magento development, adobe commerce structured data
