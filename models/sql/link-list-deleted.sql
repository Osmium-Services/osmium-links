SELECT L.id, L.name, L.logo_filename, L.deleted_at, CL.user_name AS changed_by, CU.avatar AS changed_by_avatar
FROM {TABLE} AS L
LEFT JOIN {PREFIX}changelog AS CL ON CL.id = (SELECT MAX(id) FROM {PREFIX}changelog WHERE record_type = 'link' AND record_id = L.id)
LEFT JOIN {PREFIX}users AS CU ON CU.id = CL.user_id
WHERE L.deleted_at IS NOT NULL
ORDER BY L.deleted_at DESC
