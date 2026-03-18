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
 * Endpoint AJAX para actualizar el orden/padre de categorías al arrastrar y soltar.
 *
 * @package    local_dragndrop
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/questionlib.php');
require_once(__DIR__ . '/lib.php');

require_login();

header('Content-Type: application/json');

$sesskey = required_param('sesskey', PARAM_RAW);
if (!confirm_sesskey($sesskey)) {
    echo json_encode(['success' => false, 'error' => 'Invalid sesskey']);
    exit;
}

$action = optional_param('action', '', PARAM_ALPHA);

if ($action === 'move' || $action === 'reorder') {
    $categoryid = required_param('categoryid', PARAM_INT);
    $newparent = required_param('newparent', PARAM_INT);
    $newposition = optional_param('sortorder', optional_param('newposition', 0, PARAM_INT), PARAM_INT);

    $category = $DB->get_record('question_categories', ['id' => $categoryid], '*', MUST_EXIST);
    $context = context::instance_by_id($category->contextid);
    require_capability('moodle/question:managecategory', $context);

    $topcat = question_get_top_category($category->contextid, true);
    $effectiveparent = $newparent;
    if ($effectiveparent === 0 && $topcat) {
        $effectiveparent = $topcat->id;
    }

    if ($effectiveparent > 0) {
        if ($effectiveparent == $categoryid) {
            echo json_encode(['success' => false, 'error' => 'Cannot move category into itself']);
            exit;
        }
        $parentcat = $DB->get_record('question_categories', ['id' => $effectiveparent], '*', MUST_EXIST);
        if ($parentcat->contextid != $category->contextid) {
            echo json_encode(['success' => false, 'error' => 'Cannot move across contexts']);
            exit;
        }
        if (local_dragndrop_is_descendant($effectiveparent, $categoryid)) {
            echo json_encode(['success' => false, 'error' => 'Cannot move category into its own descendant']);
            exit;
        }
    }

    $DB->update_record('question_categories', (object)[
        'id' => $categoryid,
        'parent' => $effectiveparent,
        'sortorder' => max(0, $newposition),
    ]);

    $event = \core\event\question_category_moved::create_from_question_category_instance($category);
    $event->trigger();

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Unknown action']);
}
