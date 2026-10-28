<?php

declare(strict_types=1);

namespace Osmium\Services\OsmiumLinks\Models;

use Osmium\Core\Library\OsmiumPDO;

/**
 * What a theme calls to show the links.
 *
 *     $links = new \Osmium\Services\OsmiumLinks\Models\Links($this->osmium->dataSource);
 *     $all = $links->all();
 *     $byGroup = $links->grouped();
 *
 * A link is an array with name, url, description, logo (site-relative path or null) and group (or null).
 */
class Links
{
    public function __construct(private OsmiumPDO $database)
    {
    }

    /**
     * Every live link in display order, or only those in one group
     *
     * @return list<array{name: string, url: string, description: ?string, logo: ?string, group: ?string}>
     */
    public function all(?string $group = null): array
    {
        $rows = (new Link($this->database))->listAll();
        $links = [];
        foreach ($rows as $row) {
            $link = $this->present($row);
            $wrongGroup = $group !== null && $link['group'] !== $group;
            if ($wrongGroup) continue;
            $links[] = $link;
        }

        return $links;
    }

    /**
     * Links by group name, ungrouped links first under the key ''
     *
     * @return array<string, list<array{name: string, url: string, description: ?string, logo: ?string, group: ?string}>>
     */
    public function grouped(): array
    {
        $groups = [];
        foreach ($this->all() as $link) {
            $groups[(string) $link['group']][] = $link;
        }

        return $groups;
    }

    private function present(array $row): array
    {
        $hasLogo = !empty($row['logo_filename']);
        $group = (string) ($row['group_name'] ?? '');

        return [
            'name' => (string) $row['name'],
            'url' => (string) $row['url'],
            'description' => $row['description'] ?? null,
            'logo' => $hasLogo ? LinkText::LOGO_DIR . $row['logo_filename'] : null,
            'group' => $group !== '' ? $group : null,
        ];
    }
}
