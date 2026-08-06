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
 * Admin settings.
 *
 * @package    local_altlogin
 * @copyright  2026 Auguste Escoffier School of Culinary Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use local_altlogin\helper;

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_altlogin', get_string('pluginname', 'local_altlogin'));
    $ADMIN->add('localplugins', $settings);

    if ($ADMIN->fulltree) {
        // Always show the escape hatch first — an admin who locked themselves out
        // will be reading this page from a bypassed session.
        $settings->add(new admin_setting_heading(
            'local_altlogin/bypassinfo',
            get_string('bypassheading', 'local_altlogin'),
            get_string('bypassinfo', 'local_altlogin', (object)[
                'bypassurl' => helper::bypass_url()->out(false),
                'coreurl' => helper::core_login_url()->out(false),
            ])
        ));

        $settings->add(new \local_altlogin\admin_setting_enable(
            'local_altlogin/enabled',
            get_string('enabled', 'local_altlogin'),
            get_string('enabled_desc', 'local_altlogin', helper::page_url()->out(false)),
            0
        ));

        $settings->add(new admin_setting_configselect(
            'local_altlogin/mode',
            get_string('mode', 'local_altlogin'),
            get_string('mode_desc', 'local_altlogin'),
            'redirect',
            [
                'redirect' => get_string('mode:redirect', 'local_altlogin'),
                'chooser' => get_string('mode:chooser', 'local_altlogin'),
            ]
        ));

        $issuers = [0 => get_string('issuernone', 'local_altlogin')] + helper::get_issuer_choices();
        $settings->add(new admin_setting_configselect(
            'local_altlogin/issuerid',
            get_string('issuer', 'local_altlogin'),
            get_string('issuer_desc', 'local_altlogin'),
            0,
            $issuers
        ));

        $settings->add(new admin_setting_configtext(
            'local_altlogin/bypassparam',
            get_string('bypassparam', 'local_altlogin'),
            get_string('bypassparam_desc', 'local_altlogin', helper::DEFAULT_BYPASS_PARAM),
            helper::DEFAULT_BYPASS_PARAM,
            PARAM_ALPHANUMEXT
        ));

        $settings->add(new admin_setting_configcheckbox(
            'local_altlogin/showallidps',
            get_string('showallidps', 'local_altlogin'),
            get_string('showallidps_desc', 'local_altlogin'),
            0
        ));

        $settings->add(new admin_setting_configcheckbox(
            'local_altlogin/showlocallink',
            get_string('showlocallink', 'local_altlogin'),
            get_string('showlocallink_desc', 'local_altlogin'),
            0
        ));

        $settings->add(new admin_setting_configtext(
            'local_altlogin/heading',
            get_string('heading', 'local_altlogin'),
            get_string('heading_desc', 'local_altlogin'),
            '',
            PARAM_TEXT
        ));

        $settings->add(new admin_setting_configtextarea(
            'local_altlogin/intro',
            get_string('intro', 'local_altlogin'),
            get_string('intro_desc', 'local_altlogin'),
            '',
            PARAM_RAW
        ));
    }
}
