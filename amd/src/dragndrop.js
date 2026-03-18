/**
 * Módulo AMD para drag and drop de categorías (estilo jQuery UI Sortable).
 *
 * @module     local_dragndrop/dragndrop
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery'], function($) {
    'use strict';

    var ajaxUrl = '';
    var sessKey = '';

    /**
     * Inicializa el drag and drop en las listas de categorías.
     *
     * @param {string} url URL del endpoint AJAX
     * @param {string} sesskey Sesskey de Moodle
     */
    function init(url, sesskey) {
        ajaxUrl = url;
        sessKey = sesskey;

        $(document).ready(function() {
            $('.course-block .sortable-list').each(function() {
                initSortable($(this));
            });
        });
    }

    /**
     * Inicializa sortable en un contenedor (soporta anidamiento).
     *
     * @param {jQuery} $list Contenedor ul.sortable-list
     */
    function initSortable($list) {
        if ($list.hasClass('sortable-initialized')) {
            return;
        }
        $list.addClass('sortable-initialized');

        $list.find('> .sortable-item').each(function() {
            var $item = $(this);
            makeItemDraggable($item);
            var $nested = $item.children('ul.sortable-list');
            if ($nested.length) {
                initSortable($nested);
            }
        });

        makeListDroppable($list);
    }

    /**
     * Hace un item arrastrable (HTML5 DnD).
     *
     * @param {jQuery} $item Elemento li.sortable-item
     */
    function makeItemDraggable($item) {
        $item.attr('draggable', 'true');
        $item.on('dragstart', function(e) {
            var id = $(this).data('categoryid');
            var ctx = $(this).data('contextid');
            e.originalEvent.dataTransfer.setData('text/plain', id + ',' + ctx);
            e.originalEvent.dataTransfer.effectAllowed = 'move';
            $(this).addClass('dragging');
        });
        $item.on('dragend', function() {
            $(this).removeClass('dragging');
            $('.sortable-list').removeClass('drag-over');
            $('.sortable-item').removeClass('drop-target');
        });
    }

    /**
     * Hace una lista receptora de drops.
     *
     * @param {jQuery} $list Contenedor ul
     */
    function makeListDroppable($list) {
        $list.on('dragover', function(e) {
            e.preventDefault();
            e.originalEvent.dataTransfer.dropEffect = 'move';
            $(this).addClass('drag-over');
        });
        $list.on('dragleave', function(e) {
            if (!$(this).find(e.relatedTarget).length) {
                $(this).removeClass('drag-over');
            }
        });
        $list.on('drop', function(e) {
            e.preventDefault();
            $(this).removeClass('drag-over');
            var data = e.originalEvent.dataTransfer.getData('text/plain');
            if (!data) return;
            var parts = data.split(',');
            var catId = parseInt(parts[0], 10);
            var ctxId = parseInt(parts[1], 10);

            var $dropped = $('.sortable-item.dragging');
            if (!$dropped.length) return;

            var $targetList = $(this);
            var $targetItem = $(e.target).closest('.sortable-item');
            var newParent = 0;
            var $insertBefore = null;

            if ($targetItem.length && $targetItem[0] !== $dropped[0]) {
                if ($targetItem.parent()[0] === $targetList[0]) {
                    newParent = getTopParentId($targetList);
                    $insertBefore = $targetItem;
                } else {
                    var $parentItem = $targetItem.closest('.sortable-item');
                    newParent = $parentItem.data('categoryid');
                    var $parentList = $parentItem.children('ul.sortable-list').first();
                    if ($parentList.length) {
                        $insertBefore = $targetItem;
                        $targetList = $parentList;
                    }
                }
            }

            if (newParent === 0) {
                newParent = getTopParentId($targetList);
            }

            var siblings = [];
            $targetList.children('.sortable-item').each(function() {
                if (this !== $dropped[0]) {
                    siblings.push($(this).data('categoryid'));
                    if ($insertBefore && $(this)[0] === $insertBefore[0]) {
                        siblings.push(catId);
                    }
                }
            });
            if (siblings.indexOf(catId) < 0) {
                siblings.push(catId);
            }

            var sortorder = siblings.indexOf(catId);
            var insertBeforeIsChild = $insertBefore && $insertBefore.length &&
                $insertBefore.parent()[0] === $targetList[0];

            saveMove(catId, ctxId, newParent, sortorder, function() {
                $dropped.detach();
                if (insertBeforeIsChild) {
                    $insertBefore.before($dropped);
                } else {
                    $targetList.append($dropped);
                }
                initSortable($targetList);
            }, function() {
                // Revert on error
                window.location.reload();
            });
        });
    }

    /**
     * Obtiene el ID del padre para una lista (top category si es raíz, o categoría padre).
     *
     * @param {jQuery} $list
     * @return {number}
     */
    function getTopParentId($list) {
        var $parentItem = $list.closest('.sortable-item');
        if ($parentItem.length) {
            return $parentItem.data('categoryid');
        }
        var $courseBlock = $list.closest('.course-block');
        var topId = $courseBlock.data('topcategoryid');
        return topId ? parseInt(topId, 10) : 0;
    }

    /**
     * Guarda el movimiento vía AJAX.
     *
     * @param {number} categoryId
     * @param {number} contextId
     * @param {number} newParent
     * @param {number} sortorder
     * @param {function} onSuccess
     * @param {function} onError
     */
    function saveMove(categoryId, contextId, newParent, sortorder, onSuccess, onError) {
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                sesskey: sessKey,
                action: 'move',
                categoryid: categoryId,
                contextid: contextId,
                newparent: newParent,
                sortorder: sortorder
            },
            dataType: 'json'
        }).done(function(response) {
            if (response && response.success) {
                if (typeof onSuccess === 'function') {
                    onSuccess();
                }
            } else {
                if (typeof onError === 'function') {
                    onError();
                }
            }
        }).fail(function() {
            if (typeof onError === 'function') {
                onError();
            }
        });
    }

    return {
        init: init
    };
});
