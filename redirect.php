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
 * Redirige la vista estándar de categorías a la vista con drag and drop cuando es solo navegación.
 *
 * Se invoca desde {@see local_dragndrop_after_config()} (sin editar config.php).
 * Para forzar la vista Moodle clásica: ?standardview=1 (usa el enlace «Volver a la vista estándar»).
 *
 * @package    local_dragndrop
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Si la petición es GET a managecategories/category.php sin acciones de edición/mover/borrar,
 * redirige a local/dragndrop/index.php con los mismos parámetros de contexto (courseid, cmid…).
 */
function local_dragndrop_redirect_if_managecategories(): void {
    if (PHP_SAPI === 'cli') {
        return;
    }

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
        return;
    }

    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($uri, 'managecategories/category.php') === false) {
        return;
    }

    if (!empty($_GET['standardview'])) {
        return;
    }

    $blockkeys = [
        'edit', 'delete', 'confirm', 'moveup', 'movedown', 'moveupcontext', 'movedowncontext',
        'tocontext', 'left', 'right', 'move', 'moveto',
    ];
    foreach ($blockkeys as $key) {
        if (isset($_GET[$key]) && $_GET[$key] !== '') {
            return;
        }
    }

    $passthrough = ['courseid', 'cmid', 'cat', 'category', 'cpage'];
    $params = array_intersect_key($_GET, array_flip($passthrough));
    $params = array_filter($params, function ($v) {
        return $v !== '' && $v !== null;
    });

    $url = new moodle_url('/local/dragndrop/index.php', $params);
    redirect($url);
    exit;
}
