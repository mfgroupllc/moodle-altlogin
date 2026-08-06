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
 * Turn the alternate login page on or off from the command line.
 *
 * The lifeline for when the configured provider breaks and nobody can reach the
 * admin settings page any more.
 *
 * @package    local_altlogin
 * @copyright  2026 Auguste Escoffier School of Culinary Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_altlogin\helper;

[$options, $unrecognised] = cli_get_params([
    'enable' => false,
    'disable' => false,
    'status' => false,
    'help' => false,
], [
    'h' => 'help',
]);

if ($unrecognised) {
    cli_error(get_string('cliunknowoption', 'admin', implode(PHP_EOL . '  ', $unrecognised)));
}

if ($options['help'] || (!$options['enable'] && !$options['disable'] && !$options['status'])) {
    cli_writeln(<<<EOT
Point \$CFG->alternateloginurl at the local_altlogin page, or clear it again.

Options:
  --enable    Route the site login page through local_altlogin.
  --disable   Clear the alternate login URL (only if it points at this plugin).
  --status    Report the current alternate login URL and the bypass URLs.
  -h, --help  Print this help.

Example:
  php local/altlogin/cli/wire.php --status
EOT);
    exit(0);
}

$ourl = helper::page_url()->out(false);
$current = (string)get_config('core', 'alternateloginurl');

if ($options['enable']) {
    set_config('alternateloginurl', $ourl);
    set_config('enabled', 1, 'local_altlogin');
    cli_writeln("Alternate login URL set to: {$ourl}");
} else if ($options['disable']) {
    if ($current !== '' && rtrim($current, '/') !== rtrim($ourl, '/')) {
        cli_writeln("Alternate login URL belongs to something else, leaving it alone: {$current}");
    } else {
        set_config('alternateloginurl', '');
        cli_writeln('Alternate login URL cleared. The standard login page is live again.');
    }
    set_config('enabled', 0, 'local_altlogin');
}

$current = (string)get_config('core', 'alternateloginurl');
cli_writeln('');
cli_writeln('Alternate login URL : ' . ($current !== '' ? $current : '(not set)'));
cli_writeln('This plugin\'s page  : ' . $ourl);
cli_writeln('Bypass URL          : ' . helper::bypass_url()->out(false));
cli_writeln('Core bypass URL     : ' . helper::core_login_url()->out(false));

exit(0);
