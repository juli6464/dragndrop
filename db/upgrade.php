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
 * Actualización del plugin.
 *
 * @package    local_dragndrop
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Actualiza la base de datos / configuración del plugin.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_dragndrop_upgrade($oldversion) {
    global $DB;

    if ($oldversion < 2025050500) {
        if (get_config('local_dragndrop', 'enforce_question_depth') === false) {
            set_config('enforce_question_depth', 1, 'local_dragndrop');
        }
        if (get_config('local_dragndrop', 'min_question_category_depth') === false) {
            set_config('min_question_category_depth', 3, 'local_dragndrop');
        }

        upgrade_plugin_savepoint(true, 2025050500, 'local', 'dragndrop');
    }

    if ($oldversion < 2025050510) {
        upgrade_plugin_savepoint(true, 2025050510, 'local', 'dragndrop');
    }

    if ($oldversion < 2025050820) {
        $dbman = $DB->get_manager();

        $table = new xmldb_table('local_dragndrop_course_cat');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('parent', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
            $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('questioncategoryid', XMLDB_TYPE_INTEGER, '10', null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('course_parent', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'parent', 'sortorder']);
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_dragndrop_quiz_place');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('categoryid', XMLDB_TYPE_INTEGER, '10', null);
            $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('course_cmid_uix', XMLDB_INDEX_UNIQUE, ['courseid', 'cmid']);
            $table->add_index('course_cat_sort', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'categoryid', 'sortorder']);
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2025050820, 'local', 'dragndrop');
    }

    return true;
}
