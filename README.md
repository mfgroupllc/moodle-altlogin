# local_altlogin: Alternate Moodle login page

A Moodle local plugin that sends people directly to a chosen OAuth 2 provider or shows
a provider chooser, while keeping a saml2-style bypass available for reaching Moodle's
standard login form.

## What it does

* Point `$CFG->alternateloginurl` at this plugin (there is a checkbox for that) and every
  visit to `/login/index.php` arrives here instead.
* **Redirect mode** (default) bounces the visitor straight to the configured OAuth 2
  issuer via `auth/oauth2/login.php`.
* **Chooser mode** shows a small branded page with the provider button(s) instead — handy
  while you are still deciding which provider to standardize on.
* **The bypass**: `?alt=off` skips all of it and hands you the stock login form, the same
  way `?saml=off` works in `auth_saml2`.

## Installation

```bash
# from the Moodle web root
git clone git@github.com:mfgroupllc/moodle-altlogin.git local/altlogin
php admin/cli/upgrade.php --non-interactive
```

Or drop the contents of this repo into `local/altlogin/` and visit
Site administration → Notifications.

## Configuration

Site administration → Plugins → Local plugins → **Alternate login page**.

| Setting | Default | What it does |
|---|---|---|
| Use this page as the site login page | off | Writes `$CFG->alternateloginurl`. Unchecking clears it again — but only if it still points here, so it will not stomp on saml2 or shibboleth. |
| Behavior | Redirect straight to the provider | Redirect vs. chooser page. |
| OAuth 2 provider | None | The issuer to use. Only issuers that are enabled and available for login are listed — set them up under Server → OAuth 2 services first. |
| Bypass parameter | `alt` | The parameter name for the backdoor. `alt` gives you `?alt=off`. |
| List every identity provider | off | Chooser mode only: list every IdP the site advertises (oauth2, saml2, CAS…) rather than just the configured one. |
| Show a link to the site account login | off | Puts a visible link to the bypass on the chooser page. Leave off to keep it unadvertised. |
| Page heading / Intro text | site name / empty | Cosmetics for the chooser page. |

### Doing it in `config.php` instead

```php
$CFG->alternateloginurl = $CFG->wwwroot . '/local/altlogin/index.php';
```

If you set it here rather than in the database, the checkbox in the admin UI cannot turn
it off — `config.php` wins. Remove the line to disable.

## Getting back in

Three independent ways, in order of how much has to still be working:

```
https://moodle.example.com/local/altlogin/index.php?alt=off
https://moodle.example.com/login/index.php?loginredirect=0
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

## Logging out

Fronting Moodle with SSO breaks logout in a way that looks like a bug in Moodle but is not.
Moodle destroys its own session correctly, then sends you to the site home page. If
anything there needs a login you land back on the login page — which is this plugin —
which redirects you at the provider, whose *own* session cookie is still perfectly valid.
The provider issues a fresh code without prompting and you are back on the dashboard,
apparently never logged out at all.

This plugin handles it in three parts:

* **Always on.** An observer on `\core\event\user_loggedout` drops a cookie lasting sixty
  seconds, and the login page honors it by showing the sign-in page rather than
  redirecting. You stay logged out of Moodle. Clicking the provider button will still sign
  you back in without a prompt, because the provider session is untouched. The cookie is
  *not* set when one of the two settings below sends you off the site — there is no
  bounce-back to suppress in that case, and it would only cost you a click on the way in.
* **"Send people here after logging out".** A URL to land on instead — usually the identity
  provider's own site, so people carry on from somewhere familiar rather than staring at a
  sign-in page they did not ask for, and can sign out of the provider from there. Leave it
  empty to show this plugin's sign-in page.
* **Optional: "Also sign out of the provider".** After logout, sends the browser to the
  provider's OpenID Connect `end_session_endpoint` so the provider session ends too and the
  next sign-in asks for credentials. Only possible if the provider publishes one — see
  below.

### Before enabling single logout

1. **Register the post-logout redirect URI at the provider.** Entra ID rejects
   unregistered ones and logout will fail outright. The exact URL is printed in the plugin
   settings; it is
   `https://<site>/local/altlogin/index.php?loggedout=1`.
2. **Check an endpoint exists.** Moodle's OpenID Connect discovery saves every `*_endpoint`
   key the provider publishes, so for Entra ID and Keycloak it is already in the database
   and the settings page will show it. **Google publishes no end-session endpoint** — it has
   no RP-initiated logout — so single logout cannot work with a Google issuer, and the
   override field will not help.
3. Note that the redirect happens inside the logout event, which pre-empts the rest of
   `require_logout()`. Moodle's session is already destroyed by then; what gets skipped is
   any other auth plugin's `postlogout_hook()`. On a site whose only SSO is this one, that
   is nothing.

If logout starts failing after you enable this, turn the setting off — logout goes back to
Moodle's own behavior immediately.

### Providers with no end-session endpoint

Plenty of them exist — Google has never supported RP-initiated logout, and the miniOrange
OAuth server for WordPress does not publish one either. Check before assuming:

```bash
curl -s "<issuer base url>/.well-known/openid-configuration" | python3 -m json.tool | grep -i endpoint
```

No `end_session_endpoint` in the output means single logout is not available, period.
Use the post-logout redirect URL instead and let people sign out of the provider on its own
site. Do not try to substitute the provider's ordinary web logout URL — WordPress's, for
instance, needs a `_wpnonce` that Moodle cannot generate, and `wp_safe_redirect` drops any
`redirect_to` pointing at another domain.

## Safety rails

* **Failed logins do not redirect.** When core bounces a failed attempt back to the
  alternate login URL it appends `errorcode`; seeing that, this page shows the chooser with
  the error rather than throwing you at the provider again. **An expired session is
  excluded** — `errorcode=4` means the visitor sat still too long, not that anything was
  rejected, so they are redirected as normal and carry straight on if the provider session
  is still alive. (`AUTH_LOGIN_LOCKOUT` is also 4, but core rewrites it to 3 before
  redirecting, so the two cannot be confused here.)
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

Moodle 4.5 or 5.0 (`2024100700` minimum). Uses `auth_oauth2` and core's `\core\oauth2`
API; no database tables of its own and no personal data.

## License

GNU GPL v3 or later.
