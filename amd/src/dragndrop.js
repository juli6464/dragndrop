/**
 * Drag and drop con SortableJS - listas anidadas.
 * https://sortablejs.github.io/Sortable/
 *
 * @module     local_dragndrop/dragndrop
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery'], function($) {
    'use strict';

    var ajaxUrl = '';
    var sessKey = '';

    function init(url, sesskey) {
        ajaxUrl = url;
        sessKey = sesskey;

        $(document).ready(function() {
            var Sortable = window.Sortable;
            if (!Sortable) {
                return;
            }

            var nestedSortables = document.querySelectorAll('.course-block ul.dragndrop-categories');
            for (var i = 0; i < nestedSortables.length; i++) {
                new Sortable(nestedSortables[i], {
                    group: 'nested',
                    animation: 150,
                    fallbackOnBody: true,
                    swapThreshold: 0.65,
                    handle: '.handle-icon',
                    draggable: 'li.sortable-item',
                    onEnd: function(evt) {
                        var item = evt.item;
                        var newParent = evt.to;
                        var newIndex = evt.newIndex;
                        var catId = parseInt(item.getAttribute('data-categoryid'), 10);
                        var parentId = getParentId(newParent);
                        saveMove(catId, parentId, newIndex, function() {
                            $(newParent).removeClass('sortable-list-empty');
                            if (evt.from && evt.from.children.length === 0) {
                                $(evt.from).addClass('sortable-list-empty');
                            }
                        }, function() {
                            window.location.reload();
                        });
                    }
                });
            }
        });
    }

    function getParentId(listEl) {
        var parentItem = listEl.closest('li.sortable-item');
        if (parentItem) {
            return parseInt(parentItem.getAttribute('data-categoryid'), 10);
        }
        var block = listEl.closest('.course-block');
        var topId = block ? block.getAttribute('data-topcategoryid') : null;
        return topId ? parseInt(topId, 10) : 0;
    }

    function saveMove(categoryId, newParent, sortorder, onSuccess, onError) {
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            cache: false,
            data: {
                sesskey: sessKey,
                action: 'move',
                categoryid: categoryId,
                newparent: newParent,
                sortorder: sortorder
            },
            dataType: 'json'
        }).done(function(response) {
            if (response && response.success === true) {
                onSuccess();
            } else {
                onError();
            }
        }).fail(function() {
            onError();
        });
    }

    return { init: init };
});
