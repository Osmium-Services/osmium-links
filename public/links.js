(function () {
    'use strict';

    const config = window.OsmiumLinksConfig || {};
    const actionUrl = config.basePath + 'links/action/';

    function showError(message) {
        const resultDiv = document.getElementById('linkResult');
        resultDiv.className = 'alert alert-danger';
        resultDiv.textContent = message;
        resultDiv.classList.remove('d-none');
    }

    function clearResult() {
        const resultDiv = document.getElementById('linkResult');
        resultDiv.className = 'alert d-none';
        resultDiv.textContent = '';
    }

    function openModal(link) {
        clearResult();

        const isEdit = Boolean(link);
        document.getElementById('linkModalTitle').textContent = isEdit ? 'Edit Link' : 'Add Link';
        document.getElementById('linkId').value = isEdit ? link.id : '';
        document.getElementById('linkName').value = isEdit ? link.name : '';
        document.getElementById('linkUrl').value = isEdit ? link.url : '';
        document.getElementById('linkDescription').value = isEdit ? link.description : '';
        document.getElementById('linkGroup').value = isEdit ? link.groupName : '';
        document.getElementById('linkSortOrder').value = isEdit ? link.sortOrder : 0;
        document.getElementById('linkLogo').value = '';
        document.getElementById('linkRemoveLogo').checked = false;
        document.getElementById('linkRemoveLogoWrap').classList.toggle('d-none', !(isEdit && link.hasLogo));

        new bootstrap.Modal(document.getElementById('linkModal')).show();
    }

    // Multipart, because the logo file travels with the details; the token goes in the form like any other field
    function buildForm() {
        const id = document.getElementById('linkId').value;
        const form = new FormData();
        form.append('csrf_token', window.csrfToken);
        form.append('action', id ? 'update' : 'create');
        form.append('id', id);
        form.append('name', document.getElementById('linkName').value);
        form.append('url', document.getElementById('linkUrl').value);
        form.append('description', document.getElementById('linkDescription').value);
        form.append('group_name', document.getElementById('linkGroup').value);
        form.append('sort_order', document.getElementById('linkSortOrder').value);
        form.append('remove_logo', document.getElementById('linkRemoveLogo').checked ? '1' : '');

        const logo = document.getElementById('linkLogo').files[0];
        if (logo) form.append('logo', logo);

        return form;
    }

    async function handleSubmit(e) {
        e.preventDefault();
        clearResult();

        const btn = document.getElementById('linkSaveButton');
        btn.disabled = true;

        try {
            const response = await fetch(actionUrl, { method: 'POST', body: buildForm() });
            const result = await response.json();

            if (result.success) {
                window.location.reload();
                return;
            }

            showError(result.error || 'Failed to save');
        } catch (error) {
            showError('An error occurred while saving');
        } finally {
            btn.disabled = false;
        }
    }

    function init() {
        document.getElementById('addLinkButton').addEventListener('click', function () {
            openModal(null);
        });

        // Row actions come from the ListView; Edit fires list:action with the row's id
        document.addEventListener('list:action', function (e) {
            if (e.detail.action !== 'edit') return;
            openModal(config.links[e.detail.id]);
        });

        document.getElementById('linkForm').addEventListener('submit', handleSubmit);
    }

    document.addEventListener('DOMContentLoaded', init);
})();
