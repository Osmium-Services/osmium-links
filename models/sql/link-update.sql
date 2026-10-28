UPDATE {TABLE}
SET name = :name,
    url = :url,
    description = :description,
    group_name = :group_name,
    sort_order = :sort_order
WHERE id = :id
