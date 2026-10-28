<?php

declare(strict_types=1);

namespace Osmium\Services\OsmiumLinks\Controllers;

use Osmium\Modules\Admin\Core\AdminController;
use Osmium\Modules\Admin\Services\OsmiumAdmin;
use Osmium\Services\OsmiumLinks\Models\Link;
use Osmium\Services\OsmiumLinks\Models\LinkText;

/**
 * Links controller - manages the list of external links a theme shows.
 *
 * Routes:
 *   - index()  → /admin/links/
 *   - action() → /admin/links/action/   (AJAX CRUD; multipart when a logo is sent)
 */
class LinksController extends AdminController
{
    private const RECORD_TYPE = 'link';
    private const LOGO_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp', 'image/avif' => 'avif'];
    private const LOGO_MAX_DIMENSION = 400;

    // =========================================================================
    // Public Actions
    // =========================================================================

    public function index(): void
    {
        $model = new Link($this->osmium->dataSource);

        $this->data['admin']['isAdmin'] = $this->isAdmin();
        $this->data['admin']['lists'] = require __DIR__ . '/../lists/links.php';
        $this->data['admin']['links'] = \array_map($this->formatLink(...), $model->listAll());
        $this->data['admin']['recentLinks'] = \array_map($this->formatRecentLink(...), $model->listRecent());
        $this->data['admin']['deletedLinks'] = \array_map($this->formatDeletedLink(...), $model->listDeleted());

        $this->setView('links/index.phtml');
    }

    public function action()
    {
        \header('Content-Type: application/json');

        $isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
        if (!$isPost) $this->admin->jsonError('Method not allowed');

        $input = $this->admin->auth->getJsonInput();
        $action = $input['action'] ?? '';
        $id = (int) ($input['id'] ?? 0);

        $csrfValid = $this->admin->auth->validateCsrfJson($input);
        if (!$csrfValid) $this->admin->jsonError('Invalid request token. Please refresh and try again.');

        $needsId = !\in_array($action, ['create', 'purge_deleted'], true);
        $missingParams = !$action || ($needsId && !$id);
        if ($missingParams) $this->admin->jsonError('Missing parameters');

        try {
            $result = match ($action) {
                'create' => $this->createLink($input),
                'update' => $this->updateLink(id: $id, input: $input),
                'delete' => $this->deleteLink($id),
                'restore' => $this->restoreLink($id),
                'permanently_delete' => $this->permanentlyDelete($id),
                'purge_deleted' => $this->purgeDeleted(),
                default => throw new \InvalidArgumentException('Unknown action'),
            };

            $this->admin->jsonSuccess($result);
        } catch (\Exception $e) {
            $this->admin->jsonError($e->getMessage());
        }
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    private function createLink(array $input): array
    {
        $details = $this->validateDetails($input);
        $logo = $this->validateLogo();
        $model = new Link($this->osmium->dataSource);

        $newId = $model->create(
            name: $details['name'],
            url: $details['url'],
            description: $details['description'],
            groupName: $details['groupName'],
            sortOrder: $details['sortOrder'],
        );

        $hasLogo = $logo !== null;
        if ($hasLogo) $model->setLogo(id: $newId, logoFilename: $this->saveLogo($logo));

        $this->logChange(action: 'Created', id: $newId, name: $details['name']);

        return ['success' => true, 'id' => $newId];
    }

    private function updateLink(int $id, array $input): array
    {
        $details = $this->validateDetails($input);
        $logo = $this->validateLogo();
        $model = new Link($this->osmium->dataSource);

        $existing = $model->getById($id);
        if (!$existing) throw new \InvalidArgumentException('Link not found');

        $model->update(
            id: $id,
            name: $details['name'],
            url: $details['url'],
            description: $details['description'],
            groupName: $details['groupName'],
            sortOrder: $details['sortOrder'],
        );

        $this->applyLogoChange(model: $model, existing: $existing, logo: $logo, remove: !empty($input['remove_logo']));
        $this->logChange(action: 'Updated', id: $id, name: $details['name']);

        return ['success' => true, 'id' => $id];
    }

    private function deleteLink(int $id): array
    {
        $model = new Link($this->osmium->dataSource);
        $link = $model->getById($id);
        if (!$link) throw new \InvalidArgumentException('Link not found');

        $model->softDelete($id);
        $this->logChange(action: 'Deleted', id: $id, name: $link['name']);

        return ['success' => true, 'id' => $id];
    }

    private function restoreLink(int $id): array
    {
        $model = new Link($this->osmium->dataSource);
        $link = $model->getAnyById($id);
        if (!$link) throw new \InvalidArgumentException('Link not found');

        $model->restore($id);
        $this->logChange(action: 'Restored', id: $id, name: $link['name']);

        return ['success' => true, 'id' => $id];
    }

    private function permanentlyDelete(int $id): array
    {
        $notAdmin = !$this->isAdmin();
        if ($notAdmin) throw new \Exception('Unauthorized');

        $model = new Link($this->osmium->dataSource);
        $link = $model->getAnyById($id);
        if (!$link) throw new \InvalidArgumentException('Link not found');

        $this->logChange(action: 'Permanently deleted', id: $id, name: $link['name']);
        $this->removeLogoFile($link['logo_filename'] ?? null);
        $model->hardDelete($id);

        return ['success' => true, 'id' => $id];
    }

    private function purgeDeleted(): array
    {
        $notAdmin = !$this->isAdmin();
        if ($notAdmin) throw new \Exception('Unauthorized');

        $model = new Link($this->osmium->dataSource);
        foreach ($model->listDeleted() as $link) {
            $this->removeLogoFile($link['logo_filename'] ?? null);
        }

        $count = $model->purgeDeleted();
        $this->admin->model->changelog->log(description: "Purged {$count} deleted link(s)", recordType: self::RECORD_TYPE, recordId: 0);

        return ['success' => true, 'count' => $count];
    }

    /**
     * @return array{name: string, url: string, description: ?string, groupName: ?string, sortOrder: int}
     */
    private function validateDetails(array $input): array
    {
        $name = \trim((string) ($input['name'] ?? ''));
        $url = \trim((string) ($input['url'] ?? ''));

        $requiredFieldsMissing = $name === '' || $url === '';
        if ($requiredFieldsMissing) throw new \InvalidArgumentException('Name and web address are required');

        $urlInvalid = !LinkText::isValidUrl($url);
        if ($urlInvalid) throw new \InvalidArgumentException('The web address must start with http:// or https://');

        $nameTooLong = \mb_strlen($name) > 150;
        if ($nameTooLong) throw new \InvalidArgumentException('Name must be 150 characters or fewer');

        $description = LinkText::nullIfEmpty((string) ($input['description'] ?? ''));
        $descriptionTooLong = $description !== null && \mb_strlen($description) > 255;
        if ($descriptionTooLong) throw new \InvalidArgumentException('Description must be 255 characters or fewer');

        $groupName = LinkText::nullIfEmpty((string) ($input['group_name'] ?? ''));
        $groupTooLong = $groupName !== null && \mb_strlen($groupName) > 100;
        if ($groupTooLong) throw new \InvalidArgumentException('Group must be 100 characters or fewer');

        return [
            'name' => $name,
            'url' => $url,
            'description' => $description,
            'groupName' => $groupName,
            'sortOrder' => \max(0, (int) ($input['sort_order'] ?? 0)),
        ];
    }

    /**
     * The uploaded logo checked and ready to save, or null when none was sent
     *
     * @return array{tmp_name: string, extension: string, mimeType: string}|null
     */
    private function validateLogo(): ?array
    {
        $file = $_FILES['logo'] ?? null;
        $noFileSent = $file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE;
        if ($noFileSent) return null;

        $uploadFailed = $file['error'] !== UPLOAD_ERR_OK;
        if ($uploadFailed) throw new \InvalidArgumentException('The logo did not upload');

        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $typeNotAllowed = !isset(self::LOGO_TYPES[$mimeType]);
        if ($typeNotAllowed) throw new \InvalidArgumentException('Logo must be a JPG, PNG, GIF, WebP or AVIF image');

        $maxUpload = OsmiumAdmin::getMaxUploadSize();
        $tooLarge = $file['size'] > $maxUpload['bytes'];
        if ($tooLarge) throw new \InvalidArgumentException('Logo is too large. Maximum ' . $maxUpload['formatted']);

        return ['tmp_name' => $file['tmp_name'], 'extension' => self::LOGO_TYPES[$mimeType], 'mimeType' => $mimeType];
    }

    /**
     * Saves the checked upload into the logo folder under a new random name and returns that name. A logo with a side
     * over LOGO_MAX_DIMENSION is scaled down to fit (never up); a smaller one is moved as uploaded.
     */
    private function saveLogo(array $logo): string
    {
        $dir = $_SERVER['DOCUMENT_ROOT'] . LinkText::LOGO_DIR;
        $dirMissing = !\is_dir($dir);
        if ($dirMissing) \mkdir(directory: $dir, permissions: 0755, recursive: true);

        $filename = \bin2hex(\random_bytes(8)) . '.' . $logo['extension'];
        $size = \getimagesize($logo['tmp_name']);
        $isImage = $size !== false;
        if (!$isImage) throw new \Exception('Failed to read the logo');

        $needsResize = \max($size[0], $size[1]) > self::LOGO_MAX_DIMENSION;
        $saved = $needsResize
            ? $this->saveResized(logo: $logo, size: $size, path: $dir . $filename)
            : \move_uploaded_file(from: $logo['tmp_name'], to: $dir . $filename);
        if (!$saved) throw new \Exception('Failed to save the logo');

        return $filename;
    }

    /**
     * @param array{0: int, 1: int} $size Width and height of the upload
     */
    private function saveResized(array $logo, array $size, string $path): bool
    {
        $gdName = $logo['extension'] === 'jpg' ? 'jpeg' : $logo['extension']; // GD's name for the format
        $decoder = 'imagecreatefrom' . $gdName;
        $encoder = 'image' . $gdName;
        $unsupported = !\function_exists($decoder) || !\function_exists($encoder);
        if ($unsupported) throw new \Exception("This server can't resize {$logo['extension']} images. Try JPG or PNG instead.");

        $source = $decoder($logo['tmp_name']);
        if (!$source) throw new \Exception('Failed to process the logo');

        $scale = self::LOGO_MAX_DIMENSION / \max($size[0], $size[1]);
        $width = (int) \round($size[0] * $scale);
        $height = (int) \round($size[1] * $scale);

        $resized = \imagecreatetruecolor(width: $width, height: $height);
        \imagealphablending(image: $resized, enable: false);
        \imagesavealpha(image: $resized, enable: true);
        \imagecopyresampled(
            dst_image: $resized,
            src_image: $source,
            dst_x: 0,
            dst_y: 0,
            src_x: 0,
            src_y: 0,
            dst_width: $width,
            dst_height: $height,
            src_width: $size[0],
            src_height: $size[1],
        );

        return $encoder($resized, $path);
    }

    private function applyLogoChange(Link $model, array $existing, ?array $logo, bool $remove): void
    {
        $hasNewLogo = $logo !== null;
        if ($hasNewLogo) {
            $model->setLogo(id: (int) $existing['id'], logoFilename: $this->saveLogo($logo));
            $this->removeLogoFile($existing['logo_filename'] ?? null);
            return;
        }

        $shouldRemove = $remove && !empty($existing['logo_filename']);
        if (!$shouldRemove) return;

        $model->setLogo(id: (int) $existing['id'], logoFilename: null);
        $this->removeLogoFile($existing['logo_filename']);
    }

    private function removeLogoFile(?string $filename): void
    {
        $hasFile = !empty($filename);
        if (!$hasFile) return;

        $path = $_SERVER['DOCUMENT_ROOT'] . LinkText::LOGO_DIR . \basename($filename);
        $exists = \file_exists($path);
        if ($exists) \unlink($path);
    }

    private function formatLink(array $row): array
    {
        $hasLogo = !empty($row['logo_filename']);

        return [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'url' => $row['url'],
            'description' => $row['description'] ?? '',
            'groupName' => $row['group_name'] ?? '',
            'sortOrder' => (int) $row['sort_order'],
            'hasLogo' => $hasLogo,
            'logoHtml' => $this->logoHtml($row['logo_filename'] ?? null),
        ];
    }

    private function formatRecentLink(array $row): array
    {
        $changed = \max((string) ($row['updated_at'] ?? ''), (string) ($row['deleted_at'] ?? ''), (string) $row['created_at']);

        return [
            'id' => (int) $row['id'],
            'deleted_at' => $row['deleted_at'] ?? null,
            'name' => $row['name'],
            'updatedDate' => \date('j M Y, g:ia', \strtotime($changed)),
            'changed_by' => $row['changed_by'] ?? null,
            'changed_by_avatar' => $row['changed_by_avatar'] ?? null,
        ];
    }

    private function formatDeletedLink(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'deletedDate' => \date('d M Y', \strtotime((string) $row['deleted_at'])),
            'changed_by' => $row['changed_by'] ?? null,
            'changed_by_avatar' => $row['changed_by_avatar'] ?? null,
        ];
    }

    private function logoHtml(?string $filename): string
    {
        $hasFile = !empty($filename);
        if (!$hasFile) return '';

        $src = \htmlspecialchars(LinkText::LOGO_DIR . \basename($filename));

        return '<img src="' . $src . '" alt="" height="28" style="max-width: 80px; object-fit: contain;">';
    }

    private function logChange(string $action, int $id, string $name): void
    {
        $this->admin->model->changelog->log(
            description: $action . ': ' . $name,
            recordType: self::RECORD_TYPE,
            recordId: $id,
        );
    }
}
