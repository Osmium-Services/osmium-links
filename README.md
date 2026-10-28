# Osmium Links

A list of external links (name, web address, short description, logo), managed under Content > Links and shown by a theme anywhere. Links can have an optional group, such as Brands or Suppliers.

```php
$links = new \Osmium\Services\OsmiumLinks\Models\Links($this->osmium->dataSource);
$all = $links->all();           // every link, ungrouped first
$brands = $links->all('Brands'); // one group
$byGroup = $links->grouped();   // group name => links; ungrouped under ''
```

Each link: `name`, `url`, `description`, `logo` (site-relative path or null), `group` (or null). The theme prints the markup and sets `rel` on the anchors. Logos are saved in `assets/images/links/`.
