# local_altlogin — Alternate Moodle login page

A small `local` plugin that replaces the site login page with one that sends people
straight to a chosen OAuth 2 provider, and keeps a saml2-style backdoor open so you can
always reach the standard Moodle login form.

Built for the `learn-dev.escoffieronline.com` environment (Moodle 4.5 / PHP 8.2).

## What it does

* Point `$CFG->alternateloginurl` at this plugin (there is a checkbox for that) and every
  visit to `/login/index.php` arrives here instead.
* **Redirect mode** (default) bounces the visitor straight to the configured OAuth 2
  issuer via `auth/oauth2/login.php`.
* **Chooser mode** shows a small branded page with the provider button(s) instead — handy
  while you are still deciding which provider to standardise on.
* **The bypass**: `?alt=off` skips all of it and hands you the stock login form, the same
  way `?saml=off` works in `auth_saml2`.

## Installation

```bash
# from the Moodle web root
git clone git@github.com:<org>/moodle-altlogin.git local/altlogin
php admin/cli/upgrade.php --non-interactive
```

Or drop the contents of this repo into `local/altlogin/` and visit
Site administration → Notifications.

## Configuration

Site administration → Plugins → Local plugins → **Alternate login page**.

| Setting | Default | What it does |
|---|---|---|
| Use this page as the site login page | off | Writes `$CFG->alternateloginurl`. Unticking clears it again — but only if it still points here, so it will not stomp on saml2 or shibboleth. |
| Behaviour | Redirect straight to the provider | Redirect vs. chooser page. |
| OAuth 2 provider | None | The issuer to use. Only issuers that are enabled and available for login are listed — set them up under Server → OAuth 2 services first. |
| Bypass parameter | `alt` | The parameter name for the backdoor. `alt` gives you `?alt=off`. |
| List every identity provider | off | Chooser mode only: list every IdP the site advertises (oauth2, saml2, CAS…) rather than just the configured one. |
| Show a link to the site account login | off | Puts a visible link to the bypass on the chooser page. Leave off to keep it unadvertised. |
| Page heading / Intro text | site name / empty | Cosmetics for the chooser page. |

### Doing it in `config.php` instead

```php
$CFG->alternateloginurl = 'https://learn-dev.escoffieronline.com/local/altlogin/index.php';
```

If you set it here rather than in the database, the checkbox in the admin UI cannot turn
it off — `config.php` wins. Remove the line to disable.

## Getting back in

Three independent ways, in order of how much has to still be working:

```
https://learn-dev.escoffieronline.com/local/altlogin/index.php?alt=off
https://learn-dev.escoffieronline.com/login/index.php?loginredirect=0
```

```bash
php local/altlogin/cli/wire.php --disable     # clears alternateloginurl entirely
php local/altlogin/cli/wire.php --status      # prints the current wiring and both bypass URLs
```

The first is this plugin's bypass. The second is Moodle core's own — it works even if this
plugin is misconfigured, because core checks it *before* redirecting to the alternate URL.
The third works when you cannot reach the site at all.

The bypass parameter is checked before anything else in `index.php`, before the provider
lookup and before the login state check, so a broken or deleted OAuth issuer can never
block it.

## Safety rails

* **Failed logins do not redirect.** When core bounces a failed attempt back to the
  alternate login URL it appends `errorcode`; seeing that, this page shows the chooser with
  the error rather than throwing you at the provider again.
* **Loop breaker.** More than three redirects within thirty seconds pauses the automatic
  redirect for that session and shows the chooser with the bypass URL.
* **Unavailable issuer.** If the configured issuer is deleted or disabled, the page falls
  back to the chooser instead of erroring. Site admins see why; nobody else does.

## Security note

The bypass is a URL parameter only — anyone who guesses it reaches the normal Moodle login
form, which is the same form the site would show with this plugin uninstalled. That is
deliberate and matches `auth_saml2`. It is not an authentication bypass, but it does mean
password login stays reachable; if you need to genuinely close that off, disable the
`manual` auth plugin or restrict it, rather than relying on this page hiding it.

## Requirements

Moodle 4.5+ (`2024100700`). Uses `auth_oauth2` and core's `\core\oauth2` API; no database
tables of its own and no personal data.

## Licence

GNU GPL v3 or later.
