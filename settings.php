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
 * Configuración del plugin local_dragndrop.
 *
 * @package    local_dragndrop
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $url = new moodle_url('/local/dragndrop/index.php');
    $settings = new admin_settingpage('local_dragndrop', get_string('pluginname', 'local_dragndrop'));
    $settings->add(new admin_setting_heading('local_dragndrop_header',
        '',
        html_writer::link($url, get_string('openwithdragndrop', 'local_dragndrop'), ['class' => 'btn btn-primary'])));
    $ADMIN->add('localplugins', $settings);
}
