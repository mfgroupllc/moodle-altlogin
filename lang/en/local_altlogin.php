<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Strings for local_altlogin.
 *
 * @package    local_altlogin
 * @copyright  2026 Auguste Escoffier School of Culinary Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Alternate login page';

$string['bypassheading'] = 'Getting back in';
$string['bypassinfo'] = '<p>If the provider below stops working, these URLs always reach the standard Moodle login form:</p>
<ul>
<li><code>{$a->bypassurl}</code> — this plugin\'s bypass.</li>
<li><code>{$a->coreurl}</code> — Moodle\'s own bypass, works even if this plugin is broken.</li>
</ul>
<p>From the command line, <code>php local/altlogin/cli/wire.php --disable</code> clears the alternate login URL outright.</p>';

$string['enabled'] = 'Use this page as the site login page';
$string['enabled_desc'] = 'Sets <code>$CFG->alternateloginurl</code> to <code>{$a}</code>, so every visit to the normal login page arrives here. Unticking this clears the setting again. Has no effect if <code>alternateloginurl</code> is hard-coded in <code>config.php</code>.';

$string['mode'] = 'Behaviour';
$string['mode_desc'] = 'What happens when someone lands on the login page.';
$string['mode:redirect'] = 'Redirect straight to the provider';
$string['mode:chooser'] = 'Show a provider chooser page';

$string['issuer'] = 'OAuth 2 provider';
$string['issuer_desc'] = 'The provider people are sent to. Only issuers that are enabled and available for login appear here — configure them under Site administration &gt; Server &gt; OAuth 2 services.';
$string['issuernone'] = 'None selected';
$string['noissuerconfigured'] = 'No OAuth 2 provider is selected for the alternate login page, or the selected one is no longer available for login. Only site administrators see this message.';

$string['bypassparam'] = 'Bypass parameter';
$string['bypassparam_desc'] = 'The URL parameter that skips the redirect, in the style of the saml2 plugin\'s <code>?saml=off</code>. With the default value <code>{$a}</code>, the bypass URL is <code>?{$a}=off</code>.';

$string['showallidps'] = 'List every identity provider';
$string['showallidps_desc'] = 'On the chooser page, show all identity providers the site\'s auth plugins advertise instead of only the one selected above. Does not affect redirect mode.';

$string['showlocallink'] = 'Show a link to the site account login';
$string['showlocallink_desc'] = 'Adds a visible link to the bypass URL on the chooser page. Leave this off to keep the bypass unadvertised.';

$string['heading'] = 'Page heading';
$string['heading_desc'] = 'Shown at the top of the chooser page. Defaults to the site name.';

$string['intro'] = 'Intro text';
$string['intro_desc'] = 'Optional text shown above the provider buttons. Markdown is accepted.';

$string['logoutheading'] = 'Logging out';
$string['logoutinfo'] = '<p>Moodle ends its own session on logout and then sends people to the site home page. If anything there needs a login they arrive back here — and because the identity provider\'s session is still open, redirecting them at it signs them straight back in without a prompt. Logging out then looks like it did nothing.</p>
<p>This plugin always stops that: after a logout it offers the sign-in page rather than redirecting, so people stay logged out of Moodle. The settings below control where they go instead, and whether the provider\'s own session ends too.</p>';

$string['logoutredirecturl'] = 'Send people here after logging out';
$string['logoutredirecturl_desc'] = 'Where to land after logging out of Moodle — usually the identity provider\'s own site, so people carry on from somewhere familiar and can sign out of it from there if they want to. Leave empty to show this plugin\'s sign-in page instead. Also used as the return URL for single logout below.';

$string['singlelogout'] = 'Also sign out of the provider';
$string['singlelogout_desc'] = 'After logging out of Moodle, send the browser to the provider\'s end-session endpoint so the next sign-in asks for credentials again. <strong>Register this URL as a post-logout redirect URI at the provider first</strong> — Entra ID rejects unregistered ones and logout will fail: <code>{$a}</code>';

$string['endsessionurl'] = 'End-session endpoint override';
$string['endsessionurl_desc'] = 'Leave empty to use the endpoint discovered from the provider. Set this only if the provider publishes no <code>end_session_endpoint</code> — Google, for instance, has no sign-out endpoint at all, so single logout cannot work there. Currently detected: <code>{$a}</code>';

$string['endsessionnone'] = 'none — the selected provider publishes no end-session endpoint';

$string['loggedout'] = 'You have been logged out.';

$string['noproviders'] = 'No sign-in providers are available. Please contact the site administrator.';
$string['locallogin'] = 'Sign in with a site account';
$string['redirectloop'] = 'Sign-in was attempted several times in a row without success, so the automatic redirect has been paused. Use {$a} to reach the standard login form.';

$string['privacy:metadata'] = 'The Alternate login page plugin does not store any personal data.';
