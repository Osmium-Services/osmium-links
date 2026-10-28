SELECT L.id, L.name, L.created_at, L.updated_at, L.deleted_at, CL.user_name AS changed_by, CU.avatar AS changed_by_avatar
FROM {TABLE} AS L
LEFT JOIN {PREFIX}changelog AS CL ON CL.id = (SELECT MAX(id) FROM {PREFIX}changelog WHERE record_type = 'link' AND record_id = L.id)
LEFT JOIN {PREFIX}users AS CU ON CU.id = CL.user_id
WHERE (L.created_at >= NOW() - INTERVAL 24 HOUR OR L.updated_at >= NOW() - INTERVAL 24 HOUR OR L.deleted_at >= NOW() - INTERVAL 24 HOUR)
ORDER BY GREATEST(COALESCE(L.updated_at, L.created_at), L.created_at, COALESCE(L.deleted_at, L.created_at)) DESC, L.id DESC
LIMIT 10
