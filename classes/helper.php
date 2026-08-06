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

namespace local_altlogin;

use core\oauth2\api as oauth2api;
use core\oauth2\issuer;
use moodle_url;

/**
 * Shared logic for the alternate login page.
 *
 * @package    local_altlogin
 * @copyright  2026 Auguste Escoffier School of Culinary Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {

    /** @var string Value the bypass parameter must hold to disable the redirect (saml2 convention). */
    const BYPASS_VALUE = 'off';

    /** @var string Fallback bypass parameter name when the setting is empty. */
    const DEFAULT_BYPASS_PARAM = 'alt';

    /** @var int How many redirects within LOOP_WINDOW before we stop auto-redirecting. */
    const LOOP_THRESHOLD = 3;

    /** @var int Seconds over which LOOP_THRESHOLD redirects count as a loop. */
    const LOOP_WINDOW = 30;

    /** @var int Seconds the "just logged out" marker survives. */
    const LOGOUT_MARKER_TTL = 300;

    /**
     * URL of this plugin's login page.
     *
     * @return moodle_url
     */
    public static function page_url(): moodle_url {
        return new moodle_url('/local/altlogin/index.php');
    }

    /**
     * Name of the query parameter that turns the redirect off, e.g. 'alt' for ?alt=off.
     *
     * @return string
     */
    public static function bypass_param_name(): string {
        $name = trim((string)get_config('local_altlogin', 'bypassparam'));
        $name = clean_param($name, PARAM_ALPHANUMEXT);
        return $name !== '' ? $name : self::DEFAULT_BYPASS_PARAM;
    }

    /**
     * The backdoor URL: this page with the bypass parameter set.
     *
     * Hitting it hands the visitor straight to the stock Moodle login form.
     *
     * @return moodle_url
     */
    public static function bypass_url(): moodle_url {
        return new moodle_url(self::page_url(), [self::bypass_param_name() => self::BYPASS_VALUE]);
    }

    /**
     * Where the bypass sends people: the core login form, with the alternate login
     * redirect disabled for this session via core's own loginredirect parameter.
     *
     * @return moodle_url
     */
    public static function core_login_url(): moodle_url {
        return new moodle_url('/login/index.php', ['loginredirect' => 0]);
    }

    /**
     * Whether the current request asked for the backdoor.
     *
     * @return bool
     */
    public static function bypass_requested(): bool {
        $value = optional_param(self::bypass_param_name(), '', PARAM_ALPHANUMEXT);
        return \core_text::strtolower($value) === self::BYPASS_VALUE;
    }

    /**
     * OAuth 2 issuers that can currently be used to log in, as id => display name.
     *
     * @return array
     */
    public static function get_issuer_choices(): array {
        $choices = [];
        foreach (oauth2api::get_all_issuers(true) as $issuer) {
            if ($issuer->is_available_for_login()) {
                $choices[$issuer->get('id')] = $issuer->get_display_name();
            }
        }
        return $choices;
    }

    /**
     * The issuer chosen in the plugin settings, if it is still usable.
     *
     * @return issuer|null
     */
    public static function get_selected_issuer(): ?issuer {
        $issuerid = (int)get_config('local_altlogin', 'issuerid');
        if (!$issuerid) {
            return null;
        }
        try {
            $issuer = new issuer($issuerid);
        } catch (\dml_missing_record_exception $e) {
            return null;
        }
        return $issuer->is_available_for_login() ? $issuer : null;
    }

    /**
     * The auth_oauth2 entry point for an issuer, carrying the session key it requires.
     *
     * @param issuer $issuer
     * @param string $wantsurl Where the user was heading before they hit the login page.
     * @return moodle_url
     */
    public static function issuer_login_url(issuer $issuer, string $wantsurl = ''): moodle_url {
        return new moodle_url('/auth/oauth2/login.php', [
            'id' => $issuer->get('id'),
            'wantsurl' => $wantsurl !== '' ? $wantsurl : '/',
            'sesskey' => sesskey(),
        ]);
    }

    /**
     * Identity providers to offer on the chooser page.
     *
     * With the "show all providers" setting off this is just the configured issuer;
     * with it on it is every enabled identity provider on the site (oauth2, saml2, cas...),
     * exactly as the stock login page would list them.
     *
     * @param string $wantsurl
     * @return array List of ['url' => string, 'name' => string, 'iconurl' => string]
     */
    public static function get_providers(string $wantsurl = ''): array {
        if (!empty(get_config('local_altlogin', 'showallidps'))) {
            return self::get_all_identity_providers();
        }

        $issuer = self::get_selected_issuer();
        if (!$issuer) {
            return [];
        }

        $icon = $issuer->get('image');
        return [[
            'url' => self::issuer_login_url($issuer, $wantsurl)->out(false),
            'name' => $issuer->get_display_name(),
            'iconurl' => $icon ? (string)$icon : '',
        ]];
    }

    /**
     * Every identity provider the site's enabled auth plugins advertise.
     *
     * @return array
     */
    protected static function get_all_identity_providers(): array {
        global $OUTPUT;

        $providers = \auth_plugin_base::get_identity_providers(get_enabled_auth_plugins());
        $providers = \auth_plugin_base::prepare_identity_providers_for_output($providers, $OUTPUT);

        $result = [];
        foreach ($providers as $provider) {
            // out(false) rather than casting — the template escapes, and a cast would
            // hand it a URL whose ampersands are already entities.
            $url = $provider['url'];
            $result[] = [
                'url' => $url instanceof moodle_url ? $url->out(false) : (string)$url,
                'name' => $provider['name'],
                'iconurl' => isset($provider['iconurl']) ? (string)$provider['iconurl'] : '',
            ];
        }
        return $result;
    }

    /**
     * Record that we are about to auto-redirect, and report whether we are stuck in a loop.
     *
     * A misconfigured or failing provider bounces the user back to the login page, which
     * lands them here again; without this the browser would ping-pong forever.
     *
     * @return bool True if too many redirects happened too quickly.
     */
    public static function note_redirect(): bool {
        global $SESSION;

        $now = time();
        $state = $SESSION->local_altlogin_redirects ?? null;
        if (!is_array($state) || ($now - (int)($state['first'] ?? 0)) > self::LOOP_WINDOW) {
            $state = ['first' => $now, 'count' => 0];
        }
        $state['count']++;
        $SESSION->local_altlogin_redirects = $state;

        return $state['count'] > self::LOOP_THRESHOLD;
    }

    /**
     * Forget the redirect counter — called once a visitor lands on the chooser page.
     */
    public static function reset_redirect_counter(): void {
        global $SESSION;
        unset($SESSION->local_altlogin_redirects);
    }

    /**
     * Human readable version of the errorcode core appends when it bounces a failed
     * login attempt back to the alternate login URL.
     *
     * @param int $errorcode
     * @return string Empty when the code is not one we recognise.
     */
    public static function error_message(int $errorcode): string {
        switch ($errorcode) {
            case 1:
                return get_string('cookiesnotenabled');
            case 2:
                return get_string('invalidusername');
            case 3:
                return get_string('invalidlogin');
            case 4:
                return get_string('sessionerroruser', 'error');
            default:
                return $errorcode ? get_string('invalidlogin') : '';
        }
    }

    /**
     * Name of the cookie that marks a session as having just been logged out.
     *
     * A cookie rather than a session flag because logging out is precisely the moment
     * the session is destroyed — there is nowhere else to leave a note for ourselves.
     *
     * @return string
     */
    public static function logout_marker_name(): string {
        global $CFG;
        return 'MOODLEALTLOGOUT_' . $CFG->sessioncookie;
    }

    /**
     * Leave a note that the visitor has just logged out, so the next page view here
     * offers the provider instead of silently signing them straight back in.
     */
    public static function set_logout_marker(): void {
        global $CFG;

        if (headers_sent()) {
            return;
        }
        setcookie(self::logout_marker_name(), '1', [
            'expires' => time() + self::LOGOUT_MARKER_TTL,
            'path' => !empty($CFG->sessioncookiepath) ? $CFG->sessioncookiepath : '/',
            'domain' => !empty($CFG->sessioncookiedomain) ? $CFG->sessioncookiedomain : '',
            'secure' => is_moodle_cookie_secure(),
            'httponly' => !empty($CFG->cookiehttponly),
            // Lax so the cookie survives the trip back from the identity provider.
            'samesite' => 'Lax',
        ]);
        $_COOKIE[self::logout_marker_name()] = '1';
    }

    /**
     * Whether the visitor arrived here straight from a logout.
     *
     * @return bool
     */
    public static function logout_marker_present(): bool {
        return !empty($_COOKIE[self::logout_marker_name()]);
    }

    /**
     * Drop the logout marker, so the next visit auto-redirects as normal.
     */
    public static function clear_logout_marker(): void {
        global $CFG;

        if (!self::logout_marker_present()) {
            return;
        }
        unset($_COOKIE[self::logout_marker_name()]);
        if (headers_sent()) {
            return;
        }
        setcookie(self::logout_marker_name(), '', [
            'expires' => time() - HOURSECS,
            'path' => !empty($CFG->sessioncookiepath) ? $CFG->sessioncookiepath : '/',
            'domain' => !empty($CFG->sessioncookiedomain) ? $CFG->sessioncookiedomain : '',
            'secure' => is_moodle_cookie_secure(),
            'httponly' => !empty($CFG->cookiehttponly),
            'samesite' => 'Lax',
        ]);
    }

    /**
     * The admin-configured landing page for someone who has just logged out.
     *
     * Typically the identity provider's own site, so the visitor carries on from
     * somewhere that makes sense rather than staring at a sign-in page they did not
     * ask for — and can sign out of the provider from there if they want to.
     *
     * @return moodle_url|null Null when nothing is configured.
     */
    public static function logout_redirect_url(): ?moodle_url {
        $url = trim((string)get_config('local_altlogin', 'logoutredirecturl'));
        if ($url === '') {
            return null;
        }
        return new moodle_url($url);
    }

    /**
     * Where the browser ends up after a logout.
     *
     * Also what gets handed to the provider as post_logout_redirect_uri when single
     * logout is on — the URL that has to be registered at the provider, because Entra ID
     * and friends reject anything they have not been told about.
     *
     * @return moodle_url
     */
    public static function post_logout_redirect_url(): moodle_url {
        return self::logout_redirect_url() ?? new moodle_url(self::page_url(), ['loggedout' => 1]);
    }

    /**
     * The end-session endpoint discovered for the selected issuer, for display in the
     * settings page so an admin can see whether an override is needed.
     *
     * @return string Empty when the issuer publishes none.
     */
    public static function detected_end_session_endpoint(): string {
        $issuer = self::get_selected_issuer();
        return $issuer ? (string)$issuer->get_endpoint_url('end_session') : '';
    }

    /**
     * The provider's end-session endpoint, if single logout is switched on and one exists.
     *
     * OpenID Connect discovery stores every "*_endpoint" key the provider publishes, so
     * for Entra ID and Keycloak this is already in the database. Google publishes no
     * end-session endpoint at all, which is what the manual override is for.
     *
     * @return moodle_url|null
     */
    public static function single_logout_url(): ?moodle_url {
        if (empty(get_config('local_altlogin', 'singlelogout'))) {
            return null;
        }

        $endpoint = trim((string)get_config('local_altlogin', 'endsessionurl'));
        if ($endpoint === '') {
            $issuer = self::get_selected_issuer();
            $endpoint = $issuer ? (string)$issuer->get_endpoint_url('end_session') : '';
        }
        if ($endpoint === '') {
            return null;
        }

        $url = new moodle_url($endpoint);
        $url->param('post_logout_redirect_uri', self::post_logout_redirect_url()->out(false));
        return $url;
    }

    /**
     * Whether $CFG->alternateloginurl currently points at this plugin.
     *
     * @return bool
     */
    public static function is_wired_up(): bool {
        global $CFG;
        return !empty($CFG->alternateloginurl)
            && rtrim($CFG->alternateloginurl, '/') === rtrim(self::page_url()->out(false), '/');
    }
}
