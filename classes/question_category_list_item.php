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

namespace local_dragndrop;

use moodle_url;
use qbank_managecategories\helper;

/**
 * Ítem de categoría personalizado: icono engranaje (editar) y borrar visible.
 *
 * @package    local_dragndrop
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_category_list_item extends \qbank_managecategories\question_category_list_item {

    /**
     * Usa icono engranaje (settings) en lugar de lápiz (edit) para editar.
     */
    public function set_icon_html($first, $last, $lastitem): void {
        global $OUTPUT;
        $category = $this->item;
        $editurl = new moodle_url($this->parentlist->pageurl->params() + ['edit' => $category->id]);
        $action = get_string('editthiscategory', 'question');
        $this->icons['edit'] = '<a title="' . s($action) . '" href="' . $editurl->out(false) . '">' .
            $OUTPUT->pix_icon('i/settings', $action, 'core') . '</a> ';
        parent::set_icon_html($first, $last, $lastitem);
    }

    /**
     * Usa nuestro template con clase para el botón borrar.
     */
    public function item_html($extraargs = []): string {
        global $PAGE, $OUTPUT;
        $str = $extraargs['str'];
        $category = $this->item;

        $nodeparent = $PAGE->settingsnav->find('questionbank', \navigation_node::TYPE_CONTAINER);
        $questionbankurl = new moodle_url($nodeparent->action->out_omit_querystring(),
            $this->parentlist->pageurl->params());
        $questionbankurl->param('cat', $category->id . ',' . $category->contextid);

        $categoryname = format_string($category->name, true, ['context' => $this->parentlist->context]);
        $idnumber = null;
        if ($category->idnumber !== null && $category->idnumber !== '') {
            $idnumber = $category->idnumber;
        }
        $questioncount = ' (' . $category->questioncount . ')';
        $categorydesc = format_text($category->info, $category->infoformat,
            ['context' => $this->parentlist->context, 'noclean' => true]);

        $deleteurl = null;
        if ($category->parent && !helper::question_is_only_child_of_top_category_in_context($category->id)) {
            $deleteurl = new moodle_url($this->parentlist->pageurl, ['delete' => $this->id, 'sesskey' => sesskey()]);
        }

        $data = [
            'questionbankurl' => $questionbankurl,
            'categoryname' => $categoryname,
            'idnumber' => $idnumber,
            'questioncount' => $questioncount,
            'categorydesc' => $categorydesc,
            'deleteurl' => $deleteurl,
            'deletetitle' => $str->delete,
        ];

        return $OUTPUT->render_from_template('local_dragndrop/listitem', $data);
    }
}
