# Changelog

All notable changes to this extension are documented here. The format
is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [1.3.3] - 2026-10-05

### Fixed
- With "Remove Native Magento JSON-LD Markup" enabled, product pages no longer carry a second Product entity: the schema.org microdata on the body tag and in theme blocks (Offer, AggregateRating, Review) is now removed on Hyva and Luma, while inline scripts and styles stay untouched.
- Product descriptions, CMS/blog article bodies and review text in JSON-LD are now plain text: HTML tags are removed and entities such as `&bull;` or `&amp;` are decoded.
- With "Enable Product" set to No, no partial Product node or Review nodes pointing at it are emitted any more.
- The "Enable Organization" help text now says the node is output on every storefront page.
