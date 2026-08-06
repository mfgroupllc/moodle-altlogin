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

/**
 * Event observers.
 *
 * @package    local_altlogin
 * @copyright  2026 Auguste Escoffier School of Culinary Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {

    /**
     * React to a user logging out.
     *
     * Moodle destroys its own session on logout and then sends the visitor to the site
     * home page. If anything there needs a login the visitor lands back on the login
     * page, which is this plugin, which would redirect them at the provider — and the
     * provider's own session is still alive, so it signs them straight back in without
     * a prompt. To the user, logout did nothing.
     *
     * So: leave a marker telling the login page not to auto-redirect this time, and,
     * if single logout is switched on, end the provider's session as well.
     *
     * @param \core\event\user_loggedout $event
     */
    public static function user_loggedout(\core\event\user_loggedout $event): void {
        global $SCRIPT;

        // Only meaningful for a browser mid-logout. Never interfere with CLI, ajax,
        // or web service requests, which have no browser to redirect and no user to
        // show a login page to.
        if (CLI_SCRIPT || (defined('AJAX_SCRIPT') && AJAX_SCRIPT) || (defined('WS_SERVER') && WS_SERVER)) {
            return;
        }
        if (headers_sent()) {
            return;
        }

        helper::set_logout_marker();

        // Everything below sends the browser to the provider, so restrict it to the
        // one script whose whole job is logging out.
        if ((string)$SCRIPT !== '/login/logout.php') {
            return;
        }

        // Single logout when the provider supports it, otherwise just hand the visitor
        // over to wherever the admin wants them to land.
        $url = helper::single_logout_url() ?? helper::logout_redirect_url();
        if ($url) {
            // Pre-empts the rest of require_logout(). Moodle's session is already gone
            // by this point; what is skipped is other auth plugins' postlogout_hook().
            redirect($url);
        }
    }
}
