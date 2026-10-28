<?php

declare(strict_types=1);

namespace Osmium\Services\OsmiumLinks\Models;

use Osmium\Core\Library\OsmiumPDO;
use Osmium\Core\Models\Model;

/**
 * The external links an admin manages. A link is soft-deleted first and only removed for good from the bin.
 */
class Link extends Model
{
    protected ?string $sqlDir = __DIR__ . '/sql';

    public function __construct(OsmiumPDO $database)
    {
        parent::__construct(database: $database, tableName: 'links');
    }

    /** Every live link: ungrouped first, then by group, display order and name */
    public function listAll(): array
    {
        $this->database->query($this->loadSqlFile('link-list-all.sql'));

        return $this->database->resultset();
    }

    /** Created, changed or deleted in the last 24 hours, newest first, capped at 10 */
    public function listRecent(): array
    {
        $this->database->query($this->loadSqlFile('link-list-recent.sql'));

        return $this->database->resultset();
    }

    public function listDeleted(): array
    {
        $this->database->query($this->loadSqlFile('link-list-deleted.sql'));

        return $this->database->resultset();
    }

    public function getById(int $id): ?array
    {
        $this->database->query($this->loadSqlFile('link-get-by-id.sql'));
        $this->database->bind(param: ':id', value: $id);
        $result = $this->database->single();

        return $result ?: null;
    }

    /** Like getById() but also finds soft-deleted links, for restore and permanent delete */
    public function getAnyById(int $id): ?array
    {
        $this->database->query($this->loadSqlFile('link-get-any.sql'));
        $this->database->bind(param: ':id', value: $id);
        $result = $this->database->single();

        return $result ?: null;
    }

    public function create(string $name, string $url, ?string $description, ?string $groupName, int $sortOrder): int
    {
        $this->database->query($this->loadSqlFile('link-create.sql'));
        $this->bindDetails(name: $name, url: $url, description: $description, groupName: $groupName, sortOrder: $sortOrder);
        $this->database->execute();

        return (int) $this->database->lastInsertId();
    }

    public function update(int $id, string $name, string $url, ?string $description, ?string $groupName, int $sortOrder): void
    {
        $this->database->query($this->loadSqlFile('link-update.sql'));
        $this->database->bind(param: ':id', value: $id);
        $this->bindDetails(name: $name, url: $url, description: $description, groupName: $groupName, sortOrder: $sortOrder);
        $this->database->execute();
    }

    /** Set or clear (null) the logo file name on its own, so saving details never touches the logo */
    public function setLogo(int $id, ?string $logoFilename): void
    {
        $this->database->query($this->loadSqlFile('link-set-logo.sql'));
        $this->database->bind(param: ':id', value: $id);
        $this->database->bind(param: ':logo_filename', value: $logoFilename);
        $this->database->execute();
    }

    public function softDelete(int $id): void
    {
        $this->database->query($this->loadSqlFile('link-soft-delete.sql'));
        $this->database->bind(param: ':id', value: $id);
        $this->database->execute();
    }

    public function restore(int $id): void
    {
        $this->database->query($this->loadSqlFile('link-restore.sql'));
        $this->database->bind(param: ':id', value: $id);
        $this->database->execute();
    }

    public function hardDelete(int $id): void
    {
        $this->database->query($this->loadSqlFile('link-hard-delete.sql'));
        $this->database->bind(param: ':id', value: $id);
        $this->database->execute();
    }

    /** @return int How many links were removed */
    public function purgeDeleted(): int
    {
        $this->database->query($this->loadSqlFile('link-purge-deleted.sql'));
        $this->database->execute();

        return $this->database->rowCount();
    }

    private function bindDetails(string $name, string $url, ?string $description, ?string $groupName, int $sortOrder): void
    {
        $this->database->bind(param: ':name', value: $name);
        $this->database->bind(param: ':url', value: $url);
        $this->database->bind(param: ':description', value: $description);
        $this->database->bind(param: ':group_name', value: $groupName);
        $this->database->bind(param: ':sort_order', value: $sortOrder);
    }
}
