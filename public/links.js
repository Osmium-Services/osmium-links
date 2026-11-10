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

    // Logo drop zone: the chosen file stays in the hidden file input and is sent when the link is saved
    const zone = document.getElementById('linkLogoZone');
    const logoInput = document.getElementById('linkLogo');
    const removeBox = document.getElementById('linkRemoveLogo');
    const preview = zone.querySelector('.link-logo-preview');
    const empty = zone.querySelector('.link-logo-empty');
    const removeButton = zone.querySelector('.link-logo-remove');
    let objectUrl = null;

    function showLogo(src) {
        if (objectUrl && src !== objectUrl) {
            URL.revokeObjectURL(objectUrl);
            objectUrl = null;
        }
        preview.classList.toggle('d-none', !src);
        empty.classList.toggle('d-none', !!src);
        removeButton.classList.toggle('d-none', !src);
        if (src) preview.src = src;
    }

    function chooseLogo(file) {
        if (!file || !/^image\/(jpeg|png|gif|webp|avif)$/.test(file.type)) return;

        const transfer = new DataTransfer();
        transfer.items.add(file);
        logoInput.files = transfer.files;
        removeBox.checked = false;
        objectUrl = URL.createObjectURL(file);
        showLogo(objectUrl);
    }

    function initLogoZone() {
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(function (name) {
            zone.addEventListener(name, function (e) {
                e.preventDefault();
                e.stopPropagation();
            });
        });
        ['dragenter', 'dragover'].forEach(function (name) {
            zone.addEventListener(name, function () { zone.classList.add('drag-active'); });
        });
        ['dragleave', 'drop'].forEach(function (name) {
            zone.addEventListener(name, function () { zone.classList.remove('drag-active'); });
        });
        zone.addEventListener('drop', function (e) { chooseLogo(e.dataTransfer.files[0]); });
        zone.addEventListener('click', function (e) {
            if (!e.target.closest('.link-logo-remove')) logoInput.click();
        });
        logoInput.addEventListener('change', function () { chooseLogo(logoInput.files[0]); });
        removeButton.addEventListener('click', function (e) {
            e.stopPropagation();
            logoInput.value = '';
            removeBox.checked = true;
            showLogo('');
        });
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
        showLogo(isEdit && link.hasLogo ? link.logoUrl : '');

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
        initLogoZone();
    }

    document.addEventListener('DOMContentLoaded', init);
})();
