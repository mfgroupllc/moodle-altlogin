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
 * Checkbox that also points $CFG->alternateloginurl at this plugin.
 *
 * Follows the same pattern as auth_shibboleth's WAYF setting: ticking the box writes
 * the alternate login URL, unticking it clears the value again — but only if it is
 * still ours, so we never stomp on a setting another plugin or an admin put there.
 *
 * @package    local_altlogin
 * @copyright  2026 Auguste Escoffier School of Culinary Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_enable extends \admin_setting_configcheckbox {

    /**
     * Save the checkbox, then sync $CFG->alternateloginurl to match it.
     *
     * @param string $data
     * @return string Empty string on success, error message otherwise.
     */
    public function write_setting($data) {
        $result = parent::write_setting($data);
        if ($result !== '') {
            return $result;
        }

        $ourl = helper::page_url()->out(false);
        $current = (string)get_config('core', 'alternateloginurl');

        if ((string)$data === (string)$this->yes) {
            if (rtrim($current, '/') !== rtrim($ourl, '/')) {
                set_config('alternateloginurl', $ourl);
            }
        } else if (rtrim($current, '/') === rtrim($ourl, '/')) {
            set_config('alternateloginurl', '');
        }

        return '';
    }

    /**
     * Read the effective state from $CFG->alternateloginurl rather than our own
     * setting, so the box never claims to be on when something else owns the URL.
     *
     * @return string
     */
    public function get_setting() {
        return helper::is_wired_up() ? $this->yes : $this->no;
    }
}
