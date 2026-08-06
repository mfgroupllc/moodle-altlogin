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
 * Alternate login page.
 *
 * Point $CFG->alternateloginurl at this file and every visit to /login/index.php
 * arrives here instead. Depending on the configured mode this either bounces the
 * visitor straight to an OAuth 2 provider or shows them a provider chooser.
 *
 * Adding the bypass parameter (?alt=off by default) skips all of that and hands
 * the visitor the stock Moodle login form.
 *
 * @package    local_altlogin
 * @copyright  2026 Auguste Escoffier School of Culinary Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_altlogin\helper;

$errorcode = optional_param('errorcode', 0, PARAM_INT);
$wantsurlparam = optional_param('wantsurl', '', PARAM_LOCALURL);
$loggedout = optional_param('loggedout', 0, PARAM_BOOL);

$PAGE->set_context(context_system::instance());
$PAGE->set_url(helper::page_url());
$PAGE->set_pagelayout('login');
$PAGE->set_cacheable(false);

// The backdoor. Checked before anything else so a broken provider can never block it.
if (helper::bypass_requested()) {
    helper::reset_redirect_counter();
    redirect(helper::core_login_url());
}

if (isloggedin() && !isguestuser()) {
    redirect(new moodle_url('/'));
}

// Core stashes the original destination in the session before redirecting here; an
// explicit wantsurl parameter (as core's login form uses under Behat) wins over it.
if ($wantsurlparam !== '') {
    $SESSION->wantsurl = (new moodle_url($wantsurlparam))->out(false);
}
$wantsurl = !empty($SESSION->wantsurl) ? $SESSION->wantsurl : '';

$issuer = helper::get_selected_issuer();
$autoredirect = get_config('local_altlogin', 'mode') !== 'chooser';
$notice = helper::error_message($errorcode);
$noticetype = 'danger';

// The cookie is set by our user_loggedout observer; the parameter is how the identity
// provider hands the visitor back after a single logout. Either one means "do not sign
// this person straight back in".
$loggedout = $loggedout || helper::logout_marker_present();

if ($errorcode) {
    // Something went wrong on the last attempt. Redirecting again would just hide it.
    $autoredirect = false;
} else if ($loggedout) {
    $autoredirect = false;
    $notice = get_string('loggedout', 'local_altlogin');
    $noticetype = 'info';
} else if (!$issuer) {
    $autoredirect = false;
    if (is_siteadmin()) {
        $notice = get_string('noissuerconfigured', 'local_altlogin');
    }
} else if ($autoredirect && helper::note_redirect()) {
    $autoredirect = false;
    $notice = get_string('redirectloop', 'local_altlogin', helper::bypass_url()->out(false));
}

if ($autoredirect) {
    redirect(helper::issuer_login_url($issuer, $wantsurl));
}

helper::reset_redirect_counter();
helper::clear_logout_marker();

$sitename = format_string($SITE->fullname);
$heading = trim((string)get_config('local_altlogin', 'heading'));
$intro = trim((string)get_config('local_altlogin', 'intro'));

$templatecontext = [
    'sitename' => $sitename,
    'heading' => $heading !== '' ? $heading : $sitename,
    'intro' => $intro !== '' ? format_text($intro, FORMAT_MARKDOWN, ['context' => $PAGE->context]) : '',
    'hasintro' => $intro !== '',
    'notice' => $notice,
    'hasnotice' => $notice !== '',
    'noticetype' => $noticetype,
    'providers' => helper::get_providers($wantsurl),
    'showlocallink' => !empty(get_config('local_altlogin', 'showlocallink')),
    'locallinkurl' => helper::bypass_url()->out(false),
    'forgoturl' => !empty($CFG->forgottenpasswordurl)
        ? $CFG->forgottenpasswordurl
        : (new moodle_url('/login/forgot_password.php'))->out(false),
];
$templatecontext['hasproviders'] = !empty($templatecontext['providers']);

$PAGE->set_title($sitename . ': ' . get_string('login'));
$PAGE->set_heading($sitename);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_altlogin/chooser', $templatecontext);
echo $OUTPUT->footer();
