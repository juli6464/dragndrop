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
            function syncBranchToggle(li) {
                var $li = $(li);
                var collapsed = $li.hasClass('is-collapsed');
                var btn = $li.find('.dragndrop-tree-toggle');
                if (!btn.length) {
                    return;
                }
                btn.attr('aria-expanded', !collapsed);
                var tOpen = btn.attr('data-title-expanded');
                var tShut = btn.attr('data-title-collapsed');
                if (tOpen && tShut) {
                    btn.attr('title', collapsed ? tShut : tOpen);
                    btn.attr('aria-label', collapsed ? tShut : tOpen);
                }
            }

            $(document).on('click', '.dragndrop-tree-toggle', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var li = $(this).closest('li.sortable-item');
                li.toggleClass('is-collapsed');
                syncBranchToggle(li[0]);
            });

            var Sortable = window.Sortable;
            if (!Sortable) {
                return;
            }

            var nestedSortables = document.querySelectorAll('.course-block ul.dragndrop-categories');
            for (var i = 0; i < nestedSortables.length; i++) {
                var listEl = nestedSortables[i];
                var block = listEl.closest('.course-block');
                var groupName = block && block.getAttribute('data-sortable-group');
                if (!groupName) {
                    groupName = 'nested-default';
                }
                var isRootList = listEl.getAttribute('data-dragndrop-root') === '1';
                new Sortable(listEl, {
                    group: {
                        name: groupName,
                        pull: true,
                        put: true
                    },
                    animation: 0,
                    forceFallback: true,
                    fallbackOnBody: true,
                    fallbackTolerance: 12,
                    direction: 'vertical',
                    swapThreshold: 0.65,
                    invertSwap: false,
                    emptyInsertThreshold: isRootList ? 220 : 18,
                    dragoverBubble: true,
                    handle: '.handle-icon',
                    draggable: 'li.sortable-item',
                    onStart: function(evt) {
                        var b = evt.item.closest('.course-block');
                        if (b) {
                            b.classList.add('local-dragndrop-is-dragging');
                        }
                    },
                    onEnd: function(evt) {
                        var item = evt.item;
                        var blockEl = item.closest('.course-block');
                        window.requestAnimationFrame(function() {
                            if (blockEl) {
                                blockEl.classList.remove('local-dragndrop-is-dragging');
                            }
                            item.classList.remove('sortable-chosen', 'sortable-ghost', 'sortable-drag');
                            item.style.removeProperty('will-change');
                            document.body.style.removeProperty('user-select');
                            document.documentElement.style.removeProperty('cursor');
                        });
                        var parentList = item.parentNode;
                        if (!parentList || !parentList.classList || !parentList.classList.contains('dragndrop-categories')) {
                            parentList = evt.to;
                        }
                        var newIndex = evt.newIndex;
                        var catId = parseInt(item.getAttribute('data-categoryid'), 10);
                        var parentId = getParentId(parentList);
                        saveMove(catId, parentId, newIndex, function() {
                            $(parentList).removeClass('sortable-list-empty');
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
        if (!listEl || listEl.nodeType !== 1) {
            return 0;
        }
        var ul = listEl;
        if (ul.tagName !== 'UL' || !ul.classList.contains('dragndrop-categories')) {
            ul = listEl.closest('ul.dragndrop-categories');
        }
        if (!ul) {
            return 0;
        }
        if (ul.getAttribute('data-dragndrop-root') === '1') {
            var blockEl = ul.closest('.course-block');
            var topIdRoot = blockEl ? blockEl.getAttribute('data-topcategoryid') : null;
            return topIdRoot ? parseInt(topIdRoot, 10) : 0;
        }
        var parentItem = ul.closest('li.sortable-item');
        if (parentItem) {
            return parseInt(parentItem.getAttribute('data-categoryid'), 10);
        }
        var block = ul.closest('.course-block');
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
