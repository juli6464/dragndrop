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
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Redirige la vista estándar de categorías a la vista amigable del plugin.
 * Incluir al final de config.php: require_once($CFG->dirroot.'/local/dragndrop/redirect.php');
 *
 * @package    local_dragndrop
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function local_dragndrop_redirect_if_managecategories(): void {
    global $CFG;

    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($uri, 'managecategories/category.php') === false) {
        return;
    }

    $allowed = ['courseid', 'cmid', 'cat', 'category', 'cpage', 'edit', 'delete', 'moveup', 'movedown',
        'moveupcontext', 'movedowncontext', 'tocontext', 'left', 'right', 'move', 'moveto', 'confirm', 'sesskey'];
    $params = array_intersect_key($_GET, array_flip($allowed));
    $params = array_filter($params, function ($v) {
        return $v !== '' && $v !== null;
    });

    // Redirigir a la vista con drag and drop (index.php), no a la vista con flechas (category.php).
    $url = new moodle_url($CFG->wwwroot . '/local/dragndrop/index.php', $params);
    redirect($url);
    exit;
}
