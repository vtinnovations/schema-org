# Schema.org Structured Data

*English version · [Deutsch (Standardsprache)](README.md)*

Contao bundle that builds structured data as JSON-LD for every front end page
and injects it as one connected `@graph` immediately before `</head>`. It
targets search engines and AI answer engines that read structured data for rich
results and citations.

## Contents

- [Project overview](#project-overview)
- [Status](#status)
- [Supported versions](#supported-versions)
- [System requirements](#system-requirements)
- [Installation](#installation)
- [Activating a licence](#activating-a-licence)
- [Back end](#back-end)
- [Configuration](#configuration)
- [Front end output](#front-end-output)
- [Feature status](#feature-status)
- [Permissions](#permissions)
- [Licence and entitlement behaviour](#licence-and-entitlement-behaviour)
- [Security model](#security-model)
- [Operational safety](#operational-safety)
- [Runtime directories](#runtime-directories)
- [External communication](#external-communication)
- [Logging](#logging)
- [Deployment](#deployment)
- [Clearing the cache](#clearing-the-cache)
- [Tests](#tests)
- [Troubleshooting](#troubleshooting)
- [Known limitations](#known-limitations)
- [Licence and copyright](#licence-and-copyright)
- [Further documents](#further-documents)

## Project overview

The bundle builds a single cross-linked JSON-LD graph per page view. Its nodes
reference each other through `@id` instead of emitting several independent
`<script>` blocks.

Depending on the configuration and the installed Contao bundles, it contributes:

| Node | Source |
|---|---|
| `Organization` or `LocalBusiness` | Website root (address, logo, contact details, profile URLs) |
| `WebSite` with `SearchAction` | Website root (optional, with a search URL) |
| `WebPage` and its subtypes | Page (title, description, image, `speakable`) |
| `BreadcrumbList` | Page trail of the current page |
| `NewsArticle`, `Article`, `BlogPosting` | News detail page |
| `Event` | Event detail page |
| `FAQPage` with `Question`/`Answer` | FAQ reader page |
| Custom nodes | Hand-written JSON-LD per page or record |

Injection deliberately happens through the response rather than `TL_HEAD`:
custom `fe_page` templates frequently drop the `TL_HEAD` insert tag, which would
silently swallow the output.

## Status

Ready for production use. Version 2.0.0 is a complete reimplementation of the
licence handling; the structured-data output is unchanged from 1.x.

**Upgrading from 1.x:** the previous local licence store is not reused. An
existing key is adopted server-side the first time the settings screen is
opened and has to be activated once more. Until then the bundle emits no
structured data.

## Supported versions

| Component | Supported |
|---|---|
| Contao | 5.3 and newer (`^5.3`, older versions excluded) |
| PHP | 8.2, 8.3, 8.4 (`^8.2`) |
| Symfony | 6.4 and 7.x |
| Doctrine DBAL | 3.6 and newer, and 4.x |

## System requirements

- Contao Managed Edition or an equivalent Symfony application running Contao 5.3+
- PHP extensions: `json`, `sodium` (required), `intl` (recommended)
- Outbound HTTPS access to the licence service
- A writable `var/` directory
- The installation reachable over HTTPS under a real host name

`sodium` is mandatory: without it no licence can be verified and the bundle
stays inactive.

## Installation

Add the package `vtinnovations/schema-org` in the Contao Manager, or on the
command line:

```bash
composer require vtinnovations/schema-org
```

The package has to be reachable through Packagist or a repository configured in
`composer.json`.

Then update the database and clear the cache:

```bash
vendor/bin/contao-console contao:migrate
vendor/bin/contao-console cache:clear
```

The migration creates the additional fields in `tl_page`, `tl_news`,
`tl_calendar_events` and `tl_faq`.

### File system permissions

The bundle creates its own working directory below `var/` and needs write access
there for both the web server and the CLI user. It is created with restrictive
permissions (directory `0700`, files `0600`), must not be reachable through the
web server and does not belong in version control.

Restrictive `open_basedir` or `umask` settings have to allow access to `var/`.

## Activating a licence

The licence is managed under **System → Settings** in the
**Schema.org Licence management** section. This is the only place in the back
end where licence data is visible or can be changed.

1. On the website root page, enter the domain the website is served under in
   the **Domain name** field.
2. Clear the cache so the change takes effect.
3. Open **System → Settings**, enter the licence key and choose
   **Verify and activate licence**.

After a successful activation the section shows the masked key, the package, the
term, the active host name, all licensed host names and the licence version.

The other buttons:

- **Update licence** – fetches the current state from the licence service. If
  the call fails, the existing licence stays in place unchanged.
- **Remove licence** – requires the confirmation box and returns the
  installation to the unlicensed state immediately.

The full key is not shown in the back end after activation and is never written
back into the entry field.

Detailed description: [Documentation/Licensing.en.md](Documentation/Licensing.en.md)

## Back end

| Location | Content |
|---|---|
| **Schema.org → Schema.org** | Preview of the generated JSON-LD per page, with links to the Google Rich Results Test and the schema.org validator |
| **System → Settings** | **Schema.org Licence management** section |
| **Site structure → Website root** | **Schema.org / Structured data** section (site-wide values) |
| **Site structure → Other page** | **Schema.org / Structured data** section (per-page overrides) |
| **News, Events, FAQ** | **Schema.org** section per record |

The **Schema.org** module is display only: it generates no data, stores nothing
and contains no licence controls. Without a valid licence it shows a notice
pointing at the settings.

## Configuration

### Website root – site-wide values

| Field | Effect |
|---|---|
| Disable Schema.org completely | Switches the output off for the entire website |
| Organization type | `Organization`, `Local business` or no output |
| Organization name | Empty: the website root title is used |
| Logo | Emitted as `logo`/`image` |
| sameAs (profile URLs) | Social, Wikipedia or profile URLs |
| Telephone, email | Also emitted as a `ContactPoint` |
| Address, region, country code | `PostalAddress` |
| Latitude and longitude | `GeoCoordinates`, only meaningful for a local business |
| Opening hours, price range | Only meaningful for a local business |
| Output WebSite node | Enables the `WebSite` node |
| Search URL (SearchAction) | Enables the search box markup |

### Other pages – overrides

| Field | Effect |
|---|---|
| Disable schema for this page | Suppresses the entire output on this page |
| WebPage type | A more specific page type, for example contact page or FAQ page |
| Speakable CSS selectors | Marks read-aloud regions |
| Custom JSON-LD | Additional nodes for the graph |

### News, events, FAQ

| Record | Fields |
|---|---|
| News item | Disable schema, article type, author (name and URL), speakable, custom JSON-LD |
| Event | Disable schema, event status, attendance mode, location, custom JSON-LD |
| FAQ | Exclude from FAQPage |

Custom JSON-LD is expected without `@context`; a supplied `@context` is removed.
Invalid JSON is ignored silently and never breaks the page.

## Front end output

Output happens only when all of the following are true:

- a valid licence for the requested host name,
- a front end request with a resolved page,
- the response is HTML and contains `</head>`,
- neither the website nor the page is disabled,
- at least one node was produced.

If any condition fails, Contao behaves exactly as it would without this bundle.
No content is altered, removed or redirected.

## Feature status

| Feature | Status |
|---|---|
| JSON-LD output in the front end | Available |
| Organization / LocalBusiness | Available |
| WebSite with SearchAction | Available |
| WebPage with speakable markup | Available |
| BreadcrumbList | Available |
| NewsArticle / Article / BlogPosting | Conditional (requires `contao/news-bundle`) |
| Event | Conditional (requires `contao/calendar-bundle`) |
| FAQPage | Conditional (requires `contao/faq-bundle`) |
| Custom JSON-LD per page and record | Available |
| Back end preview with validator links | Available |
| Licence management in the back end | Available |
| Daily licence refresh | Available (through the Contao cron) |
| Licence-service initiated update | Available |
| Interface in German and English | Available |
| Separate Free and Pro tiers | Not applicable (the product has one tier) |

## Permissions

| Area | Requirement |
|---|---|
| **Schema.org** module | Access to the back end module in the user group |
| **Schema.org Licence management** section | Access to the **Settings** module, which Contao restricts to administrators by default |
| Fields on pages and records | Contao treats these fields as protected: non-administrators need them listed among the allowed fields in their group |

Every licence-changing action verifies the module permission first and the
Contao request token second, before anything happens.

## Licence and entitlement behaviour

The product is distributed as a permanently free edition but requires an issued
and activated licence. There is no anonymous mode, no trial that starts by
itself and no licence that can be produced locally.

| State | Visible behaviour |
|---|---|
| No licence activated | No structured data; management and configuration remain usable |
| Licence active | Full output for the licensed host names |
| Licence does not cover this installation | No output; the section says so |
| Licence removed | Immediately back to the unlicensed behaviour |

Binding is per exact host name. `example.com` and `www.example.com` are
different hosts; both have to be licensed if the site answers on both.

Stored configuration survives every state. Activating a licence again later
leaves all settings exactly as they were.

## Security model

- Licence decisions are made server-side. Output is checked independently at
  several points rather than unlocked in one central place.
- Licence data lives outside the public directory with restrictive file
  permissions.
- Received licence data is checked for authenticity and integrity before it is
  adopted. Locally altered data is detected and results in the unlicensed state.
- Communication with the licence service uses fixed HTTPS addresses with
  certificate verification, no redirects, and bounded timeouts and response
  sizes.
- The public endpoint for service-initiated updates accepts cryptographically
  authenticated requests only. Claims of origin such as `Origin`, `Referer` or
  the source address do not count as proof.
- Replayed or outdated updates are refused, and an older licence cannot replace
  a newer one.
- The package contains no signing keys and no reusable credentials.
- There is no environment variable and no setting that switches the check off.

Detailed description: [Documentation/Security.en.md](Documentation/Security.en.md)

No protection mechanism is impossible to bypass. The measures described make
tampering harder and detectable; they are not a guarantee.

## Operational safety

- Licence state changes run under a lock and take effect only after the result
  has been read back and verified. If that check fails, the previous state is
  restored.
- An unreachable licence service, a timeout or a malformed answer never changes
  a working licence.
- Failures in individual node contributors are contained; a page is never served
  incomplete or aborted because of them.
- The bundle deletes, moves and overwrites no content, files or settings of the
  installation.

## Runtime directories

| Path | Content |
|---|---|
| `var/schema-org/` | Licence state and the bundle's own bookkeeping |

The directory must be writable, private and covered by backups. With several
application servers it has to live on shared storage so that locking and replay
protection apply installation-wide.

## External communication

The bundle communicates exclusively with the V&T Innovations licence service at
`www.v-t.one` over HTTPS. The addresses are compiled in and cannot be redirected
through configuration.

| Trigger | Direction | Data transferred |
|---|---|---|
| Activate or update a licence | Installation → licence service | Product identity, host name, licence key |
| Usage signal | Installation → licence service | Product name and host name, per page view that produced output, after the response was sent |
| Session signal | Installation → licence service | Host name and licence key, once per authenticated back end session when the licence section is first opened |
| Initiated update | Licence service → installation | Updated licence data to the bundle's public endpoint |

Signals run after the response has been sent and affect neither delivery nor
licence validity. A failure has no consequences and is not retried within the
same session.

For firewalls: outbound, `www.v-t.one` must be reachable over HTTPS. Inbound,
the bundle's public update endpoint must remain reachable. Its exact path can be
determined in the installation:

```bash
vendor/bin/contao-console debug:router | grep vtinnovations
```

## Logging

Only operational values are logged: the operation, a technical request
identifier, the HTTP status, the duration, a result category and the applied
licence version.

Licence keys, checksums, signatures, transferred data, response bodies and
anything derived from them are not logged. The licence key appears neither in
browser output nor in error messages or diagnostics.

## Deployment

```bash
composer install --no-dev --optimize-autoloader
vendor/bin/contao-console contao:migrate --no-interaction
vendor/bin/contao-console cache:clear --env=prod
vendor/bin/contao-console cache:warmup --env=prod
```

Also make sure that:

- `var/schema-org/` is writable and is not copied between installations,
- the Contao cron runs (web based or `contao:cron`),
- outbound HTTPS connections are permitted,
- behind a reverse proxy, Symfony's `trusted_proxies` and `trusted_hosts` are
  set correctly, because the installation's host name is derived from them.

Licence state is bound to the host name. Cloning an installation to another
domain does not carry the licence over; it has to be issued for the new host
name.

## Clearing the cache

```bash
vendor/bin/contao-console cache:clear
vendor/bin/contao-console cache:clear --env=prod
```

The Contao Manager offers the same through its function for clearing the
application cache.

## Tests

The package ships a test suite. With the development dependencies installed it
runs under PHPUnit:

```bash
composer install
vendor/bin/phpunit
```

For environments without development dependencies there is a standalone run
that needs nothing but PHP:

```bash
composer test-standalone
```

Producing a distributable package including its checks and a SHA-256 manifest:

```bash
composer build-release
```

The build aborts when the test suite fails or the package carries no valid
verification material.

## Troubleshooting

| Observation | Cause and remedy |
|---|---|
| No JSON-LD in the source | Check the licence; root page or page disabled; the response is not HTML or contains no `</head>` |
| "No domain is configured" | Enter the domain name on the root page and clear the cache |
| "This licence is not issued for any domain configured on this installation" | The licensed host name and the root page domain do not match exactly, for example `example.com` versus `www.example.com` |
| "The licence server could not be reached" | Allow outbound HTTPS to `www.v-t.one`; the existing licence stays in place |
| Activation fails despite a correct key | Check `sodium`, check the system clock, check write access to `var/` |
| Output disappears after a move or domain change | The licence is host-bound and has to be issued for the new host name |
| News, events or FAQ produce no nodes | The corresponding Contao bundle is not installed, or the record is not published |
| Custom JSON-LD does not appear | Invalid JSON is ignored; check the input and omit `@context` |

## Known limitations

- Installations served under an IP address or a single-label host name such as
  `localhost` cannot be licensed and emit no structured data.
- Binding is per exact host name; subdomains and the `www` form are separate
  hosts.
- If no root page carries a domain name, the current host of the request as
  validated by Symfony is used. Setting the domain name gives predictable
  behaviour.
- With several application servers and no shared `var/`, locking and replay
  protection apply per server only.
- On licensed pages a usage signal is sent per page view after the response has
  been delivered. On high-traffic sites this should be considered during
  capacity planning.
- Custom JSON-LD is not validated against schema.org.
- The back end preview cannot build an absolute URL for some page types and
  therefore cannot display them.
- There is exactly one licence tier. This product makes no distinction between
  Free and Pro.

## Licence and copyright

Package: `vtinnovations/schema-org`
Licence: `LGPL-3.0-or-later`
Copyright: V&T Innovations

The software licence under the LGPL and the product licensing are two different
things: the LGPL governs the rights to the source code, while the licence key
enables structured-data output for a particular host name.

## Further documents

- [Deutsch (Standardsprache)](README.md)
- [Documentation/Licensing.en.md](Documentation/Licensing.en.md) – licence management in detail
- [Documentation/Security.en.md](Documentation/Security.en.md) – security model
