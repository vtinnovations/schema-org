# Security model

*English version · [Deutsch (Standardsprache)](Sicherheit.md) · [Back to the overview](../README.en.md)*

This document describes the bundle's protective measures at a functional level.
It deliberately names no internal identifiers, file names, verification order or
procedural details.

## Scope

Covered are the parts of this bundle: structured-data output in the front end,
licence management in the back end, the exchange with the licence service and
the local licence state.

Not covered are the security of the Contao installation, the web server, the
database and the operating system.

## Access control

- The licence section lives in **System → Settings** and requires access to that
  Contao module, which is restricted to administrators by default.
- Every licence-changing action verifies the module permission first and the
  Contao request token second, server-side. Only then is the stored key read or
  a connection opened.
- Contao treats the configuration fields on pages and records as protected
  fields. Editors without administrator rights need them explicitly listed in
  their user group.
- The licence section needs no browser code. There is no JavaScript interface
  through which licence operations could be triggered, and no connection from
  the browser to the licence service.

## Entitlement enforcement

- The decision is made server-side only.
- It is evaluated at several independent points rather than unlocked in one
  central place. Calling the internal services directly does not bypass the
  check either.
- In the front end the bundle additionally checks that the host actually being
  served is licensed. An installation reachable under several names emits
  structured data only under the licensed ones.
- When the check fails, Contao behaves exactly as it would without this bundle.

## Protecting the licence state

- The licence state lives below `var/`, outside the public directory.
- Directory and files are created with restrictive permissions (`0700` and
  `0600` respectively).
- No path is derived from request data.
- Later changes to the stored state are detected at the next evaluation. The
  installation then falls back to the unlicensed state; it is not damaged and
  loses no configuration.

## Authenticity and integrity

- Data received from the licence service is verified completely before it is
  adopted. A partially verified answer is never stored.
- A licence document altered afterwards is refused even when accompanying check
  values were recomputed to match.
- An older licence cannot replace a newer one.
- The public material needed for verification ships with the package and is
  checked at runtime against a value carried alongside it, so a swapped package
  is noticed.
- The package contains no signing keys and no reusable credentials. There is no
  supported way to introduce your own verification material.

## Outbound communication

- The only destination is the V&T Innovations licence service at
  `www.v-t.one`. The addresses are compiled in and cannot be redirected through
  configuration, request data or responses.
- Connections use HTTPS with certificate and host name verification.
- Redirects are not followed.
- Timeouts and maximum response sizes are bounded, and the response type is
  checked before anything is parsed.
- Usage and session signals run only after the response has been delivered and
  have no effect on rendering or licence validity.

## Public endpoint

For service-initiated updates the bundle provides a publicly reachable endpoint.
It sits outside the back end login because the counterpart is a server, not a
signed-in person.

- Only cryptographically authenticated requests are accepted.
- Claims of origin such as `Origin`, `Referer`, the user agent or the source
  address do not count as proof.
- Replayed or outdated requests are refused. An unchanged repeat of the same
  request is reported as already processed and is not applied a second time.
- Requests are bounded in size, method and content type before they are
  evaluated.
- Refused requests receive a general answer with no verification details.
- The endpoint writes no program files and creates no paths from request data.

## Failure behaviour

Guaranteed behaviour, enforced in code:

- An unreachable licence service, a timeout or a malformed answer never changes
  a working licence.
- Missing or unverifiable prerequisites lead to the unlicensed state, never to
  access.
- Licence state changes run under a lock and take effect only after the result
  has been read back and verified. If that check fails, the previous state is
  restored.
- Failures in individual node contributors are contained; a page is never served
  incomplete because of them.
- The bundle deletes, moves and overwrites no content, files or settings of the
  installation.

Environment-dependent behaviour:

- Locking and replay protection are only effective when several application
  servers share the same `var/` directory.
- Reliable host name resolution requires correctly configured
  `trusted_proxies` and `trusted_hosts`.
- Restoring the previous state requires the file system to still be writable at
  the moment of failure.
- The daily refresh requires a running Contao cron.

## Logging

- Logged are the operation, a technical request identifier, the HTTP status, the
  duration, a result category and the applied licence version.
- Not logged are licence keys, checksums, signatures, transferred data, response
  bodies and any lengths or check values derived from them.
- The licence key appears neither in browser output nor in error messages,
  diagnostics or session data.
- Messages shown to administrators are general and name no verification details.

## Known limits

- Distributed source code can be read and modified. The measures described make
  tampering harder and detectable; they are not a guarantee and not a claim that
  they cannot be bypassed.
- Anyone with write access to the program code on the server can change its
  behaviour. Protecting the file system is an operational responsibility.
- Without shared storage, locking and replay protection apply per application
  server only.
- A badly skewed system clock can prevent licence operations.
- Usage signals let the licence service infer when a licensed installation is
  serving pages.

## Operational responsibilities

- Protect `var/` from web server access and include it in backups.
- Limit write access to the program directory to what is necessary.
- Serve the site over HTTPS with a valid certificate.
- Set `trusted_proxies` and `trusted_hosts` correctly.
- Keep Contao, PHP and the server environment up to date.

## Reporting security issues

Please report security-relevant observations through the contact channels listed
on `https://www.v-t.one` rather than publishing them.

## Further documents

- [Back to the overview](../README.en.md)
- [Licensing](Licensing.en.md)
- [Deutsch (Standardsprache)](Sicherheit.md)
