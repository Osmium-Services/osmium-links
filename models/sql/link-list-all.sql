SELECT id, name, url, description, logo_filename, group_name, sort_order
FROM {TABLE}
WHERE deleted_at IS NULL
ORDER BY (group_name IS NULL OR group_name = '') DESC, group_name ASC, sort_order ASC, name ASC
