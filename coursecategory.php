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
 * Alta/edición/borrado de carpetas de taxonomía del curso (no son categorías del banco).
 *
 * @package    local_dragndrop
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->libdir . '/formslib.php');
require_once(__DIR__ . '/lib.php');

$courseid = required_param('courseid', PARAM_INT);
$id = optional_param('id', 0, PARAM_INT);
$delete = optional_param('delete', 0, PARAM_INT);

$pageparams = ['courseid' => $courseid];
if ($id) {
    $pageparams['id'] = $id;
}
$PAGE->set_url(new moodle_url('/local/dragndrop/coursecategory.php', $pageparams));

$course = get_course($courseid);
$coursecontext = context_course::instance($courseid);
require_login($course);
require_capability('moodle/question:managecategory', $coursecontext);

if (!local_dragndrop_course_taxonomy_tables_ready()) {
    throw new moodle_exception('taxonomy_tables_missing', 'local_dragndrop');
}

// Tras require_login($course) no usar $PAGE->set_course(): ya está fijado y provoca error de tema.
$PAGE->set_context($coursecontext);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('taxonomy_folder_form_title', 'local_dragndrop'));
$PAGE->set_heading(format_string($course->fullname));

$returl = local_dragndrop_question_categories_index_url($courseid);

if ($delete && confirm_sesskey()) {
    $cat = $DB->get_record('local_dragndrop_course_cat', ['id' => $delete, 'courseid' => $courseid], '*',
        IGNORE_MISSING);
    if ($cat) {
        $child = $DB->record_exists('local_dragndrop_course_cat', ['parent' => $delete, 'courseid' => $courseid]);
        $hasquiz = $DB->record_exists('local_dragndrop_quiz_place', ['categoryid' => $delete, 'courseid' => $courseid]);
        if ($child || $hasquiz) {
            \core\notification::error(get_string('taxonomy_folder_not_empty', 'local_dragndrop'));
            redirect($returl);
        }
        $DB->delete_records('local_dragndrop_course_cat', ['id' => $delete, 'courseid' => $courseid]);
    }
    redirect($returl);
}

/** Formulario carpeta curso. */
class local_dragndrop_course_folder_form extends moodleform {

    /**
     * Definición del formulario.
     */
    public function definition(): void {
        global $DB;

        $mform = $this->_form;
        $custom = $this->_customdata;

        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);
        $mform->setDefault('courseid', $custom['courseid']);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'name', get_string('name'), ['size' => 60]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('required'), 'required');

        $parents = [0 => get_string('taxonomy_root_folder', 'local_dragndrop')];
        $opts = $DB->get_records('local_dragndrop_course_cat', ['courseid' => $custom['courseid']], 'sortorder');
        foreach ($opts as $row) {
            if (!empty($custom['id']) && (int) $row->id === (int) $custom['id']) {
                continue;
            }
            $parents[(int) $row->id] = format_string($row->name, true,
                ['context' => context_course::instance($custom['courseid'])]);
        }
        $mform->addElement('select', 'parent', get_string('taxonomy_parent_folder', 'local_dragndrop'), $parents);
        $mform->setDefault('parent', 0);

        $this->add_action_buttons(true, get_string('savechanges'));
    }
}

$record = null;
if ($id) {
    $record = $DB->get_record('local_dragndrop_course_cat', ['id' => $id, 'courseid' => $courseid], '*',
        MUST_EXIST);
}

$form = new local_dragndrop_course_folder_form(new moodle_url('/local/dragndrop/coursecategory.php'),
    ['courseid' => $courseid, 'id' => $id]);

if ($record) {
    $data = [
        'courseid' => $courseid,
        'id' => $record->id,
        'name' => $record->name,
        'parent' => (int) $record->parent,
    ];
    $form->set_data((object) $data);
} else {
    $form->set_data((object) ['courseid' => $courseid, 'id' => 0, 'parent' => 0]);
}

if ($form->is_cancelled()) {
    redirect($returl);
}

if ($fromform = $form->get_data()) {
    $now = time();

    if (!empty($fromform->id)) {
        $existing = $DB->get_record('local_dragndrop_course_cat',
            ['id' => (int) $fromform->id, 'courseid' => $courseid], '*', MUST_EXIST);
        $parent = (int) $fromform->parent;
        if ($parent === (int) $fromform->id) {
            throw new moodle_exception('taxonomy_cycle', 'local_dragndrop');
        }
        if ($parent > 0 && local_dragndrop_cc_is_ancestor_of((int) $fromform->id, $parent, $courseid)) {
            throw new moodle_exception('taxonomy_cycle', 'local_dragndrop');
        }
        $DB->update_record('local_dragndrop_course_cat', (object) [
            'id' => (int) $fromform->id,
            'parent' => $parent,
            'name' => $fromform->name,
            'questioncategoryid' => null,
            'timemodified' => $now,
        ]);
    } else {
        $maxsort = (int) $DB->get_field_sql(
            'SELECT MAX(sortorder) FROM {local_dragndrop_course_cat} WHERE courseid = ? AND parent = ?',
            [$courseid, (int) $fromform->parent]
        );
        $DB->insert_record('local_dragndrop_course_cat', (object) [
            'courseid' => $courseid,
            'parent' => (int) $fromform->parent,
            'name' => $fromform->name,
            'sortorder' => $maxsort + 1,
            'questioncategoryid' => null,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }
    redirect($returl);
}

echo $OUTPUT->header();
echo html_writer::div(html_writer::link($returl, get_string('backtodragtree', 'local_dragndrop')), 'mb-3');
if ($record) {
    $delurl = new moodle_url('/local/dragndrop/coursecategory.php',
        ['courseid' => $courseid, 'delete' => $record->id, 'sesskey' => sesskey()]);
    echo html_writer::div(
        html_writer::link($delurl, get_string('delete'), ['class' => 'text-danger']),
        'mb-2'
    );
}
$form->display();
echo $OUTPUT->footer();
