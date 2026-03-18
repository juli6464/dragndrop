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
 * Funciones del plugin local_dragndrop.
 *
 * @package    local_dragndrop
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Obtiene cursos con sus categorías de preguntas.
 *
 * @param int $courseid Si se especifica, solo devuelve ese curso (si el usuario tiene acceso).
 * @return array Lista de cursos con propiedad 'categories' (árbol de categorías).
 */
function local_dragndrop_get_courses_with_categories(int $courseid = 0): array {
    global $DB;

    $courses = [];

    if ($courseid) {
        $course = $DB->get_record('course', ['id' => $courseid], '*', IGNORE_MISSING);
        if (!$course || $course->id == SITEID) {
            return [];
        }
        $context = context_course::instance($courseid);
        if (!has_capability('moodle/question:managecategory', $context)) {
            return [];
        }
        $courselist = [$course];
    } else {
        // Usar get_courses y filtrar por capacidad (evita problemas de parámetros SQL).
        $allcourses = get_courses();
        $courselist = [];
        foreach ($allcourses as $c) {
            if ($c->id == SITEID) {
                continue;
            }
            $context = context_course::instance($c->id);
            if (has_capability('moodle/question:managecategory', $context)) {
                $courselist[] = $c;
            }
        }
    }

    foreach ($courselist as $course) {
        $context = context_course::instance($course->id);
        list($categories, $topcategoryid) = local_dragndrop_get_categories_tree($context->id);
        $courses[] = (object)[
            'id' => $course->id,
            'fullname' => $course->fullname,
            'shortname' => $course->shortname,
            'categories' => $categories,
            'topcategoryid' => $topcategoryid,
        ];
    }

    return $courses;
}

/**
 * Obtiene el árbol de categorías para un contexto.
 *
 * @param int $contextid ID del contexto.
 * @return array [árbol de categorías, topcategoryid].
 */
function local_dragndrop_get_categories_tree(int $contextid): array {
    global $DB;

    $statuscondition = "AND qv.status <> :status";
    $params = [
        'contextid' => $contextid,
        'status' => \core_question\local\bank\question_version_status::QUESTION_STATUS_HIDDEN,
    ];

    $sql = "SELECT c.id, c.parent, c.name, c.contextid, c.info, c.infoformat, c.idnumber, c.sortorder,
                    (SELECT COUNT(1)
                       FROM {question} q
                       JOIN {question_versions} qv ON qv.questionid = q.id
                       JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                      WHERE q.parent = 0 AND qv.status <> :status2
                        AND c.id = qbe.questioncategoryid
                        AND (qv.version = (SELECT MAX(v.version)
                                            FROM {question_versions} v
                                            JOIN {question_bank_entries} be ON be.id = v.questionbankentryid
                                           WHERE be.id = qbe.id AND v.status <> :status3)
                           )
                    ) AS questioncount
              FROM {question_categories} c
             WHERE c.contextid = :contextid AND c.parent <> 0
             ORDER BY c.parent, c.sortorder, c.name";
    $params['status2'] = $params['status'];
    $params['status3'] = $params['status'];

    $all = $DB->get_records_sql($sql, $params);

    $byparent = [];
    foreach ($all as $cat) {
        $pid = $cat->parent;
        if (!isset($byparent[$pid])) {
            $byparent[$pid] = [];
        }
        $byparent[$pid][] = $cat;
    }

    $topcat = question_get_top_category($contextid, true);
    $topid = $topcat ? $topcat->id : 0;

    return [local_dragndrop_build_tree($byparent, $topid), $topid];
}

/**
 * Construye el árbol recursivo de categorías.
 *
 * @param array $byparent Mapa parent_id => [hijos].
 * @param int $parentid ID del padre.
 * @return array
 */
function local_dragndrop_build_tree(array $byparent, int $parentid): array {
    $result = [];
    if (!isset($byparent[$parentid])) {
        return $result;
    }
    foreach ($byparent[$parentid] as $cat) {
        $cat->children = local_dragndrop_build_tree($byparent, $cat->id);
        $result[] = $cat;
    }
    return $result;
}

/**
 * Comprueba si una categoría es descendiente de otra (para evitar ciclos al mover).
 *
 * @param int $potentialparent Id de la categoría en la que queremos mover.
 * @param int $categoryid Id de la categoría que se mueve.
 * @return bool True si potentialparent está dentro del árbol de categoryid.
 */
function local_dragndrop_is_descendant(int $potentialparent, int $categoryid): bool {
    global $DB;

    if ($potentialparent <= 0 || $potentialparent == $categoryid) {
        return false;
    }
    $current = $potentialparent;
    $visited = [];
    while ($current > 0 && !isset($visited[$current])) {
        if ($current == $categoryid) {
            return true;
        }
        $visited[$current] = true;
        $rec = $DB->get_record('question_categories', ['id' => $current], 'parent');
        $current = $rec ? (int) $rec->parent : 0;
    }
    return false;
}

/**
 * Renderiza el árbol de categorías como HTML con estructura sortable.
 * Usa icono engranaje para editar y papelera para eliminar.
 *
 * @param array $categories Árbol de categorías.
 * @param int $depth Profundidad actual.
 * @return string HTML.
 */
function local_dragndrop_render_category_tree(array $categories, int $depth = 0): string {
    global $OUTPUT;

    if (empty($categories)) {
        $placeholder = get_string('dropheresubcategory', 'local_dragndrop');
        return '<ul class="dragndrop-categories sortable-list sortable-list-empty" data-depth="' . $depth .
            '" data-placeholder="' . s($placeholder) . '"></ul>';
    }

    $html = '<ul class="dragndrop-categories sortable-list" data-depth="' . $depth . '">';
    foreach ($categories as $cat) {
        $context = context::instance_by_id($cat->contextid);
        $courseid = ($context->contextlevel == CONTEXT_COURSE)
            ? $context->instanceid
            : (($ctx = $context->get_course_context(false)) ? $ctx->instanceid : 0);

        $name = format_string($cat->name, true, ['context' => $context]);
        $qcount = isset($cat->questioncount) ? (int) $cat->questioncount : 0;
        $qcountstr = $qcount > 0 ? ' (' . $qcount . ')' : '';
        $questionbankurl = new moodle_url('/question/edit.php', [
            'courseid' => $courseid ?: $context->instanceid,
            'cat' => $cat->id . ',' . $cat->contextid,
        ]);
        if ($context->contextlevel == CONTEXT_MODULE) {
            $questionbankurl->param('cmid', $context->instanceid);
            $questionbankurl->remove_params('courseid');
        }

        $cid = $courseid ?: (($context->contextlevel == CONTEXT_COURSE) ? $context->instanceid : 0);

        // Editar: category.php?courseid=X&edit=Y
        $editurl = new moodle_url('/question/bank/managecategories/category.php', [
            'courseid' => $cid,
            'edit' => $cat->id,
        ]);

        // Eliminar: category.php?courseid=X&delete=Y&sesskey=Z (solo si se puede)
        $deletehtml = '';
        if ($cid && $cat->parent && class_exists('\qbank_managecategories\helper') &&
                !\qbank_managecategories\helper::question_is_only_child_of_top_category_in_context($cat->id)) {
            $deleteurl = new moodle_url('/question/bank/managecategories/category.php', [
                'courseid' => $cid,
                'delete' => $cat->id,
                'sesskey' => sesskey(),
            ]);
            $deletehtml = '<a href="' . s($deleteurl->out(false)) . '" class="btn-delete" title="' .
                s(get_string('delete')) . '">' . $OUTPUT->pix_icon('t/delete', get_string('delete'), 'core') . '</a>';
        }

        $editicon = $OUTPUT->pix_icon('i/settings', get_string('edit'), 'core');
        $editlink = '<a href="' . s($editurl->out(false)) . '" class="btn-edit" title="' .
            s(get_string('edit')) . '">' . $editicon . '</a>';

        $childrenhtml = local_dragndrop_render_category_tree($cat->children ?? [], $depth + 1);

        $html .= '<li class="sortable-item" data-categoryid="' . s($cat->id) . '" data-contextid="' . s($cat->contextid) . '">';
        $html .= '<div class="sortable-handle">';
        $html .= '<span class="handle-icon">⋮⋮</span>';
        $html .= '<a href="' . s($questionbankurl->out(false)) . '" class="category-name">' . s($name) . $qcountstr . '</a>';
        $html .= $editlink . $deletehtml;
        $html .= '</div>';
        $html .= $childrenhtml;
        $html .= '</li>';
    }
    $html .= '</ul>';
    return $html;
}
