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

defined('MOODLE_INTERNAL') || die();

/**
 * Objeto de categorías que usa nuestra lista (con icono engranaje y borrar).
 *
 * @package    local_dragndrop
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_category_object extends \qbank_managecategories\question_category_object {

    /**
     * Usa nuestra lista con ítem personalizado (engranaje, borrar).
     */
    public function initialize($page, $contexts, $currentcat, $defaultcategory, $todelete, $addcontexts): void {
        $lastlist = null;
        foreach ($contexts as $context) {
            $this->editlists[$context->id] = new question_category_list(
                'ul', '', true, $this->pageurl, $page, 'cpage',
                defined('QUESTION_PAGE_LENGTH') ? QUESTION_PAGE_LENGTH : 25,
                $context
            );
            $this->editlists[$context->id]->lastlist =& $lastlist;
            if ($lastlist !== null) {
                $lastlist->nextlist =& $this->editlists[$context->id];
            }
            $lastlist =& $this->editlists[$context->id];
        }

        $count = 1;
        $paged = false;
        foreach ($this->editlists as $key => $list) {
            list($paged, $count) = $this->editlists[$key]->list_from_records($paged, $count);
        }
        $this->catform = new \qbank_managecategories\form\question_category_edit_form($this->pageurl,
                ['contexts' => $contexts, 'currentcat' => $currentcat ?? 0]);
        if (!$currentcat) {
            $this->catform->set_data(['parent' => $defaultcategory]);
        }
    }
}
