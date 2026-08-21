# Licensing

*English version · [Deutsch (Standardsprache)](Lizenzierung.md) · [Back to the overview](../README.en.md)*

This document describes licence management from an administrator's point of
view.

## Principle

Schema.org Structured Data is distributed as a permanently free edition but
requires a licence issued by V&T Innovations and activated in the installation.
Without a valid licence the bundle emits no structured data; Contao itself is
unaffected.

Deliberately absent:

- anonymous operation without a key,
- a trial period the installation starts by itself,
- a licence that can be produced or imported locally,
- a setting or environment variable that switches the check off.

A licence applies to one or more precisely named host names.

## Requirements

| Requirement | Meaning |
|---|---|
| PHP extension `sodium` | Without it no licence can be verified |
| Domain name on the website root | Determines which host name is activated |
| Outbound HTTPS to `www.v-t.one` | Needed for activation and refresh |
| Writable `var/` directory | Where the licence state is kept |
| Access to **System → Settings** | Restricted to administrators by default in Contao |

Installations served under an IP address or a single-label host name such as
`localhost` cannot be licensed.

## Activating a licence

1. Open **Site structure**, edit the website root and enter the domain the website
   is served under in **Domain name**.
2. Clear the cache.
3. Open **System → Settings** and scroll to the
   **Schema.org Licence management** section.
4. Enter the key in the **Licence key** field.
5. Choose **Verify and activate licence**.

The request runs server-side. The browser never contacts the licence service and
does not see the key again after submitting it.

On success Contao reports "The licence was activated." and the section shows:

| Value | Meaning |
|---|---|
| Key | Masked, first and last characters only |
| Package | The issued licence tier |
| Term | Perpetual for this product |
| Active for | The host name the installation is currently licensed under |
| Licensed hosts | Every host name the licence covers |
| Licence version | Sequential version of the licence state |

## Updating a licence

**Update licence** fetches the current state from the licence service, for
example after a renewal or after further host names were added. It uses the
stored key; a different key entered in the field replaces it.

If the call fails, the existing licence stays in place unchanged. An outage of
the licence service never costs the installation its licence.

## Removing a licence

**Remove licence** requires the
**Yes, remove the licence from this installation** box to be ticked. The licence
state is then deleted and the installation immediately behaves like an
unlicensed one.

Configuration on pages and records is preserved in full. After activating again,
all settings are available exactly as before.

## Automatic refresh

The bundle contacts the licence service once a day through the Contao cron, if
the stored state is older than twelve hours. Changes therefore take effect
without anyone re-entering the key.

This requires a running Contao cron, either through the built-in web mechanism
or through:

```bash
vendor/bin/contao-console contao:cron
```

In addition, the licence service can send an update to the installation on its
own. The bundle provides a public endpoint for this that accepts
cryptographically authenticated requests only. Its path can be determined in the
installation:

```bash
vendor/bin/contao-console debug:router | grep vtinnovations
```

If the endpoint is blocked by a firewall or a WAF, activation and the daily
refresh keep working; only service-initiated updates fail to reach the
installation.

## States

| State | Visible behaviour | Shown in the section |
|---|---|---|
| No licence activated | No structured data | "No licence activated." |
| Licence active | Full output for the licensed host names | "Licence active." with details |
| Licence does not cover this installation | No structured data | A notice that the licence is not issued for any configured domain |
| Licence data altered afterwards | No structured data | A notice that the stored licence does not authorise this installation |
| Key from version 1.x found | No structured data until activation | A notice with a button for the one-off activation |

No other licence tiers exist in this product. There is no trial period, no Pro
tier and no expiry with a fallback to a reduced tier.

## Entitlements

| Function | Without a licence | With an active licence |
|---|---|---|
| Structured data in the front end | Not available | Available |
| Preview in the **Schema.org** module | Not available | Available |
| Configuration fields on pages and records | Available | Available |
| Licence section in the settings | Available | Available |

Configuration and management always stay reachable, so that a licence can be
entered at all and stored values are never lost.

## Host name binding

The check compares host names character by character. Case, a trailing dot, a
port and internationalised spellings are normalised first, without changing
which host is meant.

Consequently:

- `example.com` and `www.example.com` are different hosts.
- `shop.example.com` is not covered by a licence for `example.com`.
- Wildcards such as `*.example.com` are not accepted.

An installation counts as licensed when at least one domain name configured on a
website root matches a licensed host name exactly. In the front end the bundle
additionally checks that the host actually being served is licensed.

If no website root carries a domain name, the current host of the request as
validated by Symfony through `trusted_hosts` and `trusted_proxies` is used.
Setting the domain name gives predictable behaviour.

## Upgrading from version 1.x

The previous local licence state comes from a different mechanism and is not
reused. The first time the settings screen is opened, the bundle adopts any key
found there server-side, removes the old store and shows the
**Activate the key found from the previous version** button.

Until that one-off activation the installation emits no structured data.

## Data transferred

| Trigger | Data transferred |
|---|---|
| Activation and refresh | Product identity, host name, licence key |
| Usage signal | Product name and host name, per page view that produced output |
| Session signal | Host name and licence key, once per authenticated back end session when the licence section is first opened |

All signals run server-side, after the response has been delivered, and have no
effect on page rendering. No website content, no visitor data and no user
accounts are transferred.

## Messages and their meaning

| Message | Meaning |
|---|---|
| "The licence was activated." | Activation succeeded, output is active |
| "The licence was updated." | The new state was adopted |
| "The licence was removed …" | The installation is unlicensed again |
| "The licence server could not be reached. Nothing was changed." | Network or service problem; the previous state is untouched |
| "This licence is not issued for any domain configured on this installation." | The licensed host name and the root page domain do not match exactly |
| "No domain is configured on any root page …" | The domain name on the root page is missing |
| "The licence could not be activated …" | The key was refused or the answer was unusable |
| "Tick the confirmation box …" | Removal attempted without the confirmation |

The messages are intentionally general and name no verification details.
Operational specifics go to the application log.

## Backup, restore and migration

The licence state lives under `var/schema-org/`. For operations this means:

- Include the directory in backups.
- Do not put it under version control.
- Do not copy it between installations: the state is bound to host names and is
  not accepted on another domain.
- With several application servers, use shared storage.

When moving to another domain, the licence has to be issued for the new host
name and activated there again.

## Troubleshooting

| Observation | What to do |
|---|---|
| The section is missing from the settings | Clear the cache; check access to the **Settings** module |
| Activation fails immediately | Check the key for whitespace and stray characters |
| Activation reports a missing domain | Enter the domain name on the root page and clear the cache |
| Activation reports no match for the domain | Compare the licensed host name and the root page domain character by character |
| Activation aborts for no visible reason | Check `sodium`, the system clock and write access to `var/` |
| Front end shows nothing despite an active licence | Compare the requested host with the licensed hosts; check the disable switches on the root page and the page |
| Licence invalid after a server move | Have the new host name licensed |

## Further documents

- [Back to the overview](../README.en.md)
- [Security model](Security.en.md)
- [Deutsch (Standardsprache)](Lizenzierung.md)
