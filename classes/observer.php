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
 * Observadores de eventos del banco de preguntas.
 *
 * @package    local_dragndrop
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dragndrop;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../lib.php');

/**
 * Observador.
 */
class observer {

    /**
     * Comprueba tablas de taxonomía sin depender de funciones globales (evita fallos de resolución en namespace).
     *
     * @return bool
     */
    private static function course_taxonomy_tables_exist(): bool {
        global $DB;

        static $result = null;
        if ($result !== null) {
            return $result;
        }

        $dbman = $DB->get_manager();
        $result = $dbman->table_exists(new \xmldb_table('local_dragndrop_course_cat'))
            && $dbman->table_exists(new \xmldb_table('local_dragndrop_quiz_place'));

        return $result;
    }

    /**
     * Tras crear una pregunta.
     *
     * @param \core\event\question_created $event
     */
    public static function question_created(\core\event\question_created $event): void {
        self::enforce_category_depth((int) $event->other['categoryid']);
    }

    /**
     * Tras actualizar una pregunta (incluye cambios de categoría).
     *
     * @param \core\event\question_updated $event
     */
    public static function question_updated(\core\event\question_updated $event): void {
        self::enforce_category_depth((int) $event->other['categoryid']);
    }

    /**
     * Comprueba que la categoría cumple la profundidad mínima respecto al tope del banco.
     *
     * @param int $categoryid
     */
    protected static function enforce_category_depth(int $categoryid): void {
        global $CFG, $DB;

        require_once($CFG->libdir . '/questionlib.php');

        if ((int) get_config('local_dragndrop', 'enforce_question_depth') !== 1) {
            return;
        }

        $cat = $DB->get_record('question_categories', ['id' => $categoryid], 'id,contextid', IGNORE_MISSING);
        if (!$cat) {
            return;
        }

        $top = question_get_top_category((int) $cat->contextid, true);
        if (!$top) {
            return;
        }

        $min = \local_dragndrop_get_min_question_category_depth();
        $depth = \local_dragndrop_category_depth_below_top($categoryid, (int) $top->id);
        if ($depth > 0 && $depth < $min) {
            throw new \moodle_exception('errorquestiondepth', 'local_dragndrop', '', $min);
        }
    }

    /**
     * Elimina la fila de ubicación cuando se borra un módulo del curso.
     *
     * @param \core\event\course_module_deleted $event
     */
    public static function course_module_deleted(\core\event\course_module_deleted $event): void {
        global $DB;

        if (!self::course_taxonomy_tables_exist()) {
            return;
        }

        $cmid = (int) $event->objectid;
        $DB->delete_records('local_dragndrop_quiz_place', ['cmid' => $cmid]);
    }

    /**
     * Limpia taxonomía del curso al borrar el curso.
     *
     * @param \core\event\course_deleted $event
     */
    public static function course_deleted(\core\event\course_deleted $event): void {
        global $DB;

        if (!self::course_taxonomy_tables_exist()) {
            return;
        }

        $courseid = (int) $event->objectid;
        $DB->delete_records('local_dragndrop_quiz_place', ['courseid' => $courseid]);
        $DB->delete_records('local_dragndrop_course_cat', ['courseid' => $courseid]);
    }
}
