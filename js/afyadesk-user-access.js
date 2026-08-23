(function () {
    const hierarchyLabels = ['County', 'Sub County', 'Ward', 'Community Health Unit'];

    function ready(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback);
            return;
        }
        callback();
    }

    function normalize(text) {
        return (text || '').replace(/\s+/g, ' ').trim();
    }

    function splitPath(text) {
        return normalize(text)
            .split('>')
            .map((part) => normalize(part))
            .filter(Boolean)
            .filter((part) => !/^root entity$/i.test(part));
    }

    function getSelectLabel(select) {
        const field = select.closest('.afyadesk-access-field') || select.closest('.form-field');
        return field ? normalize(field.querySelector('label, .form-label, .col-form-label')?.textContent) : '';
    }

    function shouldEnhance(select) {
        const label = getSelectLabel(select);
        return /service area/i.test(label) || select.closest('.afyadesk-entity-field');
    }

    function readOptions(select) {
        return Array.from(select.options || [])
            .map((option) => ({
                id: option.value,
                label: normalize(option.textContent),
                path: splitPath(option.textContent),
                disabled: option.disabled,
            }))
            .filter((option) => option.id !== '' && option.id !== '-1' && option.path.length > 0 && !option.disabled);
    }

    function buildControl(label, index) {
        const wrapper = document.createElement('label');
        wrapper.className = 'afyadesk-cascade-field';
        wrapper.innerHTML = `
            <span>${label}</span>
            <select data-afyadesk-level="${index}">
                <option value="">Select ${label}</option>
            </select>
        `;
        return wrapper;
    }

    function setOptions(select, values, placeholder) {
        const previous = select.value;
        select.innerHTML = '';
        const empty = document.createElement('option');
        empty.value = '';
        empty.textContent = placeholder;
        select.appendChild(empty);

        values.forEach((value) => {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = value;
            select.appendChild(option);
        });

        if (values.includes(previous)) {
            select.value = previous;
        }
    }

    function selectedPathFromEntity(select) {
        const selected = select.options[select.selectedIndex];
        return selected ? splitPath(selected.textContent) : [];
    }

    function updateOriginalSelect(select, options, selectedPath) {
        const target = options.find((option) => {
            if (option.path.length !== selectedPath.length) {
                return false;
            }
            return selectedPath.every((part, index) => option.path[index] === part);
        });

        if (!target) {
            return;
        }

        select.value = target.id;
        select.dispatchEvent(new Event('change', { bubbles: true }));
        if (window.jQuery) {
            window.jQuery(select).trigger('change');
        }
    }

    function updatePreview(container, selectedPath, includeLower, accessLevel) {
        const preview = container.querySelector('[data-afyadesk-access-preview]');
        if (!preview) {
            return;
        }

        const areaText = selectedPath.length ? selectedPath.join(' > ') : 'No service area selected yet';
        const scopeText = includeLower ? 'including lower levels' : 'selected level only';
        const levelText = accessLevel ? `${accessLevel} for ` : '';
        preview.textContent = `This user will access: ${levelText}${areaText} (${scopeText}).`;
    }

    function findIncludeLower(scope) {
        return scope.querySelector('select[name$="is_recursive"], input[name$="is_recursive"]');
    }

    function findAccessLevel(scope) {
        return scope.querySelector('select[name*="profiles_id"]');
    }

    function buildCascade(select) {
        if (select.dataset.afyadeskEnhanced === '1') {
            return;
        }

        const options = readOptions(select);
        if (options.length < 2) {
            return;
        }

        select.dataset.afyadeskEnhanced = '1';
        const scope = select.closest('.afyadesk-access-scope') || select.closest('form') || document.body;

        const panel = document.createElement('div');
        panel.className = 'afyadesk-cascade';
        panel.innerHTML = `
            <div class="afyadesk-cascade__top">
                <div>
                    <p class="afyadesk-kicker">Guided service area selector</p>
                    <h4>Choose the exact access boundary</h4>
                </div>
                <label class="afyadesk-cascade-search">
                    <span>Search area</span>
                    <input type="search" placeholder="Type county, ward, or CHU..." data-afyadesk-area-search>
                </label>
            </div>
            <div class="afyadesk-cascade__grid"></div>
            <p class="afyadesk-access-preview" data-afyadesk-access-preview>This user will access: No service area selected yet.</p>
            <p class="afyadesk-validation-message" data-afyadesk-validation hidden></p>
        `;

        const grid = panel.querySelector('.afyadesk-cascade__grid');
        hierarchyLabels.forEach((label, index) => grid.appendChild(buildControl(label, index)));

        const host = select.closest('.afyadesk-access-field') || select.parentElement;
        host.insertAdjacentElement('afterend', panel);

        const cascadeSelects = Array.from(panel.querySelectorAll('select[data-afyadesk-level]'));
        const searchInput = panel.querySelector('[data-afyadesk-area-search]');
        const includeLower = findIncludeLower(scope);
        const accessLevel = findAccessLevel(scope);

        function getChosenPath() {
            return cascadeSelects.map((field) => field.value).filter(Boolean);
        }

        function valuesForLevel(level, currentPath) {
            const values = new Set();
            options.forEach((option) => {
                if (option.path.length <= level) {
                    return;
                }
                const matchesParents = currentPath.every((part, index) => option.path[index] === part);
                if (matchesParents) {
                    values.add(option.path[level]);
                }
            });
            return Array.from(values).sort((a, b) => a.localeCompare(b));
        }

        function refresh(fromLevel) {
            let currentPath = [];
            for (let level = 0; level < cascadeSelects.length; level++) {
                const field = cascadeSelects[level];
                if (level >= fromLevel) {
                    field.value = '';
                }
                setOptions(field, valuesForLevel(level, currentPath), `Select ${hierarchyLabels[level]}`);
                if (field.value) {
                    currentPath.push(field.value);
                }
            }
            const selectedPath = getChosenPath();
            if (selectedPath.length) {
                updateOriginalSelect(select, options, selectedPath);
            }
            updatePreview(
                panel,
                selectedPath,
                includeLower ? ['1', 'Yes', 'yes', 'true'].includes(String(includeLower.value)) : false,
                accessLevel ? normalize(accessLevel.options[accessLevel.selectedIndex]?.textContent) : ''
            );
        }

        function hydrateFromOriginal() {
            const path = selectedPathFromEntity(select);
            cascadeSelects.forEach((field, level) => {
                const currentPath = cascadeSelects.slice(0, level).map((previous) => previous.value).filter(Boolean);
                setOptions(field, valuesForLevel(level, currentPath), `Select ${hierarchyLabels[level]}`);
                if (path[level]) {
                    field.value = path[level];
                }
            });
            updatePreview(panel, getChosenPath(), includeLower ? ['1', 'Yes', 'yes', 'true'].includes(String(includeLower.value)) : false, '');
        }

        cascadeSelects.forEach((field, level) => {
            field.addEventListener('change', () => refresh(level + 1));
        });

        [includeLower, accessLevel, select].filter(Boolean).forEach((field) => {
            field.addEventListener('change', () => refresh(99));
        });

        searchInput.addEventListener('input', () => {
            const term = normalize(searchInput.value).toLowerCase();
            if (term.length < 2) {
                return;
            }
            const match = options.find((option) => option.path.join(' > ').toLowerCase().includes(term));
            if (!match) {
                return;
            }
            match.path.forEach((part, level) => {
                if (cascadeSelects[level]) {
                    cascadeSelects[level].value = part;
                    refresh(level + 1);
                }
            });
        });

        hydrateFromOriginal();
        refresh(99);
    }

    function validateAccessForms() {
        document.querySelectorAll('form').forEach((form) => {
            const entitySelect = Array.from(form.querySelectorAll('select')).find(shouldEnhance);
            const profileSelect = form.querySelector('select[name*="profiles_id"]');
            if (!entitySelect || !profileSelect) {
                return;
            }

            form.addEventListener('submit', (event) => {
                const panel = form.querySelector('.afyadesk-cascade');
                const message = form.querySelector('[data-afyadesk-validation]');
                const validProfile = profileSelect.value && profileSelect.value !== '0';
                const validEntity = entitySelect.value && entitySelect.value !== '-1';

                if (validProfile && validEntity) {
                    return;
                }

                event.preventDefault();
                if (message) {
                    message.hidden = false;
                    message.textContent = 'Choose both an access level and a service area before saving this user.';
                }
                if (panel) {
                    panel.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            });
        });
    }

    function enhanceUserList() {
        if (!/\/front\/user\.php/i.test(window.location.pathname)) {
            return;
        }

        document.querySelectorAll('.search-container, .tab_cadre_fixehov, .table-responsive').forEach((container) => {
            if (container.dataset.afyadeskListEnhanced === '1') {
                return;
            }
            const table = container.querySelector('table');
            if (!table) {
                return;
            }
            container.dataset.afyadeskListEnhanced = '1';
            const tools = document.createElement('div');
            tools.className = 'afyadesk-user-list-tools';
            tools.innerHTML = `
                <label>
                    <span>Filter users</span>
                    <input type="search" placeholder="Search login, access level, or service area..." data-afyadesk-user-filter>
                </label>
            `;
            container.insertBefore(tools, table);
            const input = tools.querySelector('input');
            input.addEventListener('input', () => {
                const term = normalize(input.value).toLowerCase();
                table.querySelectorAll('tbody tr').forEach((row) => {
                    row.hidden = term.length > 0 && !row.textContent.toLowerCase().includes(term);
                });
            });
        });
    }

    ready(() => {
        document.querySelectorAll('select').forEach((select) => {
            if (shouldEnhance(select)) {
                buildCascade(select);
            }
        });
        validateAccessForms();
        enhanceUserList();
    });
})();
