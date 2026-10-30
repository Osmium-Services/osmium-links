<?php

declare(strict_types=1);

use Osmium\Modules\Admin\Core\ListView;

/**
 * Links lists, rendered by Osmium\Modules\Admin\Core\ListView. Row fields (logoHtml, groupName...) are prepared by
 * LinksController::formatLink() and formatRecentLink()/formatDeletedLink().
 */
require_once 'app/core/admin/services/ChangedByCell.php';

$changedByColumn = ['key' => 'changed_by', 'label' => '', 'type' => 'html', 'sortable' => false, 'render' => fn(array $row, array $context): string => \Osmium\Modules\Admin\Services\ChangedByCell::html($row, (string)($context['avatarUrl'] ?? ''))];

return [
    'active' => [
        'id' => 'osmium-links',
        'title' => 'Links',
        'noun' => ['link', 'links'],
        'endpoint' => 'links/action/',
        'note' => 'External links a theme can show, optionally in groups such as Brands or Suppliers. Ungrouped links come first.',
        'emptyText' => 'No links yet - use Add Link to create one.',
        'order' => null, // Server order: ungrouped first, then group, display order and name
        'grid' => require __DIR__ . '/../grids/links.php',
        'columns' => [
            ['key' => 'logoHtml', 'label' => '', 'type' => 'raw', 'sortable' => false, 'nowrap' => true],
            ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'strong' => true],
            ['key' => 'url', 'label' => 'Web address', 'type' => 'text', 'small' => true, 'fill' => true],
            ['key' => 'groupName', 'label' => 'Group', 'type' => 'text', 'nowrap' => true],
        ],
        'actions' => [
            ['label' => 'Edit', 'icon' => 'bx-edit-alt', 'event' => 'edit'],
            [
                'label' => 'Delete',
                'icon' => 'bx-trash',
                'action' => 'delete',
                'confirm' => 'Delete this link? It can be restored from Deleted Links.',
                'danger' => true,
            ],
        ],
    ],

    'recent' => [
        'id' => 'osmium-links-recent',
        'title' => 'Recent Changes',
        'noun' => ['link', 'links'],
        'icon' => 'bx-time-five',
        'rowClass' => static fn(array $row): string => empty($row['deleted_at']) ? '' : 'table-danger',
        'endpoint' => 'links/action/',
        'collapsible' => ['label' => 'Recent Changes ({count})'],
        'hideWhenEmpty' => false,
        'columns' => [
            $changedByColumn,
            ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'strong' => true, 'fill' => true],
            ['key' => 'updatedDate', 'label' => 'Updated', 'type' => 'text', 'small' => true, 'nowrap' => true],
        ],
    ],

    'deleted' => ListView::deletedPreset([
        'id' => 'osmium-links-deleted',
        'title' => 'Deleted Links',
        'noun' => ['item', 'items'],
        'endpoint' => 'links/action/',
        'hideWhenEmpty' => false,
        'headerButton' => [
            'label' => 'Purge All',
            'icon' => 'bx-trash',
            'class' => 'btn-outline-danger',
            'action' => 'purge_deleted',
            'confirm' => 'Permanently delete all deleted links? This cannot be undone!',
            'visible' => fn(array $rows, array $context): bool => (bool) $context['isAdmin'],
        ],
        'columns' => [
            $changedByColumn,
            ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'strong' => true, 'fill' => true],
            ['key' => 'deletedDate', 'label' => 'Deleted', 'type' => 'text', 'small' => true, 'nowrap' => true],
        ],
    ]),
];
