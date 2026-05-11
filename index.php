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
 * Vista de categorías de preguntas con drag and drop por curso.
 *
 * @package    local_dragndrop
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/editlib.php');
require_once(__DIR__ . '/lib.php');

$courseid = optional_param('courseid', 0, PARAM_INT);

require_login();

$pageurl = new moodle_url('/local/dragndrop/index.php');
if ($courseid) {
    $pageurl->param('courseid', $courseid);
}

$PAGE->set_url($pageurl);
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('pluginname', 'local_dragndrop'));
$PAGE->set_heading(get_string('pluginname', 'local_dragndrop'));

// Verificar que el usuario puede gestionar categorías.
if ($courseid) {
    $course = get_course($courseid);
    $context = context_course::instance($courseid);
    require_capability('moodle/question:managecategory', $context);
} else {
    $course = null;
}

$courses = local_dragndrop_get_courses_with_categories($courseid);

if (empty($courses)) {
    $PAGE->set_url(new moodle_url('/local/dragndrop/index.php'));
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('nocourses', 'local_dragndrop'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$PAGE->requires->css('/local/dragndrop/styles.css');
$PAGE->requires->js('/local/dragndrop/js/sortable.min.js', true);
$PAGE->requires->js_call_amd('local_dragndrop/dragndrop', 'init', [
    $CFG->wwwroot . '/local/dragndrop/ajax.php',
    sesskey(),
]);

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('pluginname', 'local_dragndrop'));

$toolbarhtml = '';
$selectorcourses = local_dragndrop_get_manageable_courses_for_selector();
if (count($selectorcourses) > 0) {
    $courseoptions = [0 => get_string('allcourses', 'local_dragndrop')];
    foreach ($selectorcourses as $c) {
        $courseoptions[$c->id] = format_string($c->fullname);
    }
    $selecturl = new moodle_url('/local/dragndrop/index.php');
    $toolbarhtml .= $OUTPUT->single_select(
        $selecturl,
        'courseid',
        $courseoptions,
        $courseid,
        null,
        null,
        ['label' => get_string('selectcourse', 'local_dragndrop')]
    );
}

// Enlace a vista estándar si estamos en un curso (standardview evita redirección automática al drag n drop).
if ($courseid) {
    $standardurl = new moodle_url('/question/bank/managecategories/category.php', [
        'courseid' => $courseid,
        'standardview' => 1,
    ]);
    $toolbarhtml .= html_writer::link($standardurl, get_string('backtostandard', 'local_dragndrop'), [
        'class' => 'btn btn-secondary btn-sm local-dragndrop-back-standard',
    ]);
}

if ($toolbarhtml !== '') {
    echo html_writer::div($toolbarhtml, 'local-dragndrop-toolbar');
}

foreach ($courses as $courseobj) {
    $blockattr = ['data-courseid' => $courseobj->id];
    if (!empty($courseobj->topcategoryid)) {
        $blockattr['data-topcategoryid'] = $courseobj->topcategoryid;
    }
    if (!empty($courseobj->questioncontextid)) {
        $blockattr['data-sortable-group'] = 'ctx-' . $courseobj->questioncontextid;
    }
    $addcategoryurl = new moodle_url('/question/bank/managecategories/category.php', [
        'courseid' => $courseobj->id,
        'edit' => 0,
    ]);
    $toolbar = html_writer::div(
        html_writer::link($addcategoryurl, get_string('addcategory', 'question'), ['class' => 'btn btn-primary btn-sm']),
        'local-dragndrop-course-actions'
    );
    echo html_writer::div(
        html_writer::tag('h3', html_writer::link(
            new moodle_url('/local/dragndrop/index.php', ['courseid' => $courseobj->id]),
            format_string($courseobj->fullname)
        ), ['class' => 'course-header']) .
        $toolbar .
        local_dragndrop_render_category_tree($courseobj->categories),
        'course-block',
        $blockattr
    );
}

if ($courseid) {
    $systemcontext = context_system::instance();
    if (has_capability('moodle/question:managecategory', $systemcontext)) {
        list($systemcategories, $systemtopid) = local_dragndrop_get_categories_tree($systemcontext->id);
        $systemblockattr = [
            'data-courseid' => SITEID,
            'data-system-block' => '1',
            'data-topcategoryid' => $systemtopid,
            'data-sortable-group' => 'ctx-' . $systemcontext->id,
        ];
        $systemaddurl = new moodle_url('/question/bank/managecategories/category.php', [
            'courseid' => SITEID,
            'edit' => 0,
        ]);
        $systemtoolbar = html_writer::div(
            html_writer::link($systemaddurl, get_string('addcategory', 'question'), ['class' => 'btn btn-primary btn-sm']),
            'local-dragndrop-course-actions'
        );
        echo html_writer::div(
            html_writer::tag('h3', get_string('systemquestioncategories', 'local_dragndrop'), ['class' => 'course-header']) .
            $systemtoolbar .
            local_dragndrop_render_category_tree($systemcategories),
            'course-block course-block-system',
            $systemblockattr
        );
    }
}

echo $OUTPUT->footer();
