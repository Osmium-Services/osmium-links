<?php

declare(strict_types=1);

/**
 * Grid (card) layout for the Links list, rendered by Osmium\Modules\Admin\Core\ListViewGrid. Columns use the
 * same vocabulary as lists/links.php but nothing is shared with it. The actions are a copy of the list's for now.
 */
return [
    'columns' => 5,
    'imageHeight' => 90,
    'image' => ['key' => 'logoHtml', 'type' => 'raw'],
    'title' => ['key' => 'name', 'type' => 'text', 'strong' => true, 'sub' => ['key' => 'url', 'style' => 'muted']],
    'meta' => [
        ['key' => 'groupName', 'type' => 'text', 'small' => true],
    ],
    'actionsAt' => 'bottom-end',
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
];
