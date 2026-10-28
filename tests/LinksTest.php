<?php

declare(strict_types=1);

namespace Tests\Services\OsmiumLinks;

use Osmium\Services\OsmiumLinks\Models\Link;
use Osmium\Services\OsmiumLinks\Models\LinkText;
use Osmium\Services\OsmiumLinks\Models\Links;
use Tests\Integration\DatabaseTestCase;

require_once __DIR__ . '/../models/LinkText.php';
require_once __DIR__ . '/../models/Link.php';
require_once __DIR__ . '/../models/Links.php';

/**
 * The URL rules, and what a theme gets from Links against the installed table (rolled back with each test; the
 * service must be installed, because creating the table here would commit the test's transaction)
 */
class LinksTest extends DatabaseTestCase
{
    public function test_only_web_addresses_are_valid(): void
    {
        $this->assertTrue(LinkText::isValidUrl('https://example.com/a?b=1'));
        $this->assertTrue(LinkText::isValidUrl('http://example.com'));
        $this->assertFalse(LinkText::isValidUrl('javascript:alert(1)'));
        $this->assertFalse(LinkText::isValidUrl('//example.com'));
        $this->assertFalse(LinkText::isValidUrl('ftp://example.com'));
        $this->assertFalse(LinkText::isValidUrl('https://'));
        $this->assertFalse(LinkText::isValidUrl('https://example.com/' . \str_repeat('a', 300)));
    }

    public function test_empty_text_becomes_null(): void
    {
        $this->assertNull(LinkText::nullIfEmpty('   '));
        $this->assertSame('Brands', LinkText::nullIfEmpty(' Brands '));
    }

    public function test_grouped_puts_ungrouped_first_and_orders_within_groups(): void
    {
        $link = new Link($this->db);
        $link->create(name: 'Zed', url: 'https://z.example', description: null, groupName: 'Brands', sortOrder: 2);
        $link->create(name: 'Alpha', url: 'https://a.example', description: 'First', groupName: 'Brands', sortOrder: 1);
        $link->create(name: 'Loose', url: 'https://l.example', description: null, groupName: null, sortOrder: 0);
        $link->create(name: 'Supplier', url: 'https://s.example', description: null, groupName: 'Suppliers', sortOrder: 0);

        $groups = (new Links($this->db))->grouped();

        $this->assertSame('', \array_key_first($groups));
        $this->assertSame(['Alpha', 'Zed'], \array_column($groups['Brands'], 'name'));
        $this->assertSame(['Supplier'], \array_column($groups['Suppliers'], 'name'));
        $this->assertContains('Loose', \array_column($groups[''], 'name'));
    }

    public function test_all_can_be_limited_to_one_group_and_skips_deleted_links(): void
    {
        $link = new Link($this->db);
        $keep = $link->create(name: 'Keep', url: 'https://k.example', description: null, groupName: 'Brands', sortOrder: 0);
        $gone = $link->create(name: 'Gone', url: 'https://g.example', description: null, groupName: 'Brands', sortOrder: 0);
        $link->create(name: 'Other', url: 'https://o.example', description: null, groupName: 'Suppliers', sortOrder: 0);
        $link->softDelete($gone);

        $brands = (new Links($this->db))->all('Brands');

        $this->assertContains('Keep', \array_column($brands, 'name'));
        $this->assertNotContains('Gone', \array_column($brands, 'name'));
        $this->assertNotContains('Other', \array_column($brands, 'name'));
        $this->assertSame($keep, (int) $link->getById($keep)['id']);
    }

    public function test_logo_is_a_site_relative_path_or_null(): void
    {
        $link = new Link($this->db);
        $id = $link->create(name: 'Logo', url: 'https://l.example', description: null, groupName: null, sortOrder: 0);
        $this->assertNull($this->logoOf('Logo'));

        $link->setLogo(id: $id, logoFilename: 'abc.png');

        $this->assertSame('/assets/images/links/abc.png', $this->logoOf('Logo'));
    }

    private function logoOf(string $name): ?string
    {
        foreach ((new Links($this->db))->all() as $link) {
            if ($link['name'] === $name) return $link['logo'];
        }

        return null;
    }
}
