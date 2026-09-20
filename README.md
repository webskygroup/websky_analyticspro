# WebSky Analytics Pro 2.0

Commercial-grade first-party analytics for OpenCart 4.1.x. The extension uses OpenCart events and an isolated extension namespace; it does not patch core files.

## Included

- Visitor and session identifiers using secure first-party cookies plus local storage.
- Page views, product views, cart adds/removals, checkout start, purchase, search, login and signup events.
- Source, medium, campaign and referrer attribution, including Google, Instagram, Telegram, Bing, Facebook, referral and direct traffic.
- Event, session, customer, product and campaign tables with indexes for reporting.
- Admin dashboard for visitors, sessions, new/returning users, orders, revenue, conversion, products, pages, sources and journeys.
- CSV export for up to 50,000 events per export.
- Optional GA4 Measurement ID and API Secret settings.

## Installation

Upload `websky_analyticspro.ocmod.zip` through the OpenCart 4 Extension Installer, then open Extensions > Analytics > WebSky Analytics Pro and save the settings. The installer creates the tables and registers five events. After an update, refresh Modifications and theme caches.

## Data model

`oc_websky_analytics_event`, `oc_websky_analytics_session`, `oc_websky_analytics_customer`, `oc_websky_analytics_product`, and `oc_websky_analytics_campaign` are created on install and removed only on uninstall.

## Build

Run `build_package.ps1` from PowerShell. The output is `websky_analyticspro.ocmod.zip`.

## Privacy and retention

The extension stores pseudonymous visitor and session identifiers, not raw IP addresses. Configure retention in the database or scheduled cleanup policy for the store's privacy requirements before commercial deployment.
