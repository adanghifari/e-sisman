document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-table-row-toggle]');

    if (!toggle) {
        return;
    }

    const target = toggle.dataset.tableRowToggle;
    const expanded = toggle.getAttribute('aria-expanded') === 'true';
    const table = toggle.closest('table') ?? document;

    toggle.setAttribute('aria-expanded', String(!expanded));
    toggle.querySelector('svg')?.classList.toggle('rotate-90', !expanded);

    table.querySelectorAll('[data-table-row-target]').forEach((row) => {
        if (row.dataset.tableRowTarget === target) {
            row.classList.toggle('hidden', expanded);
        }
    });
});

document.addEventListener('alpine:init', () => {
    Alpine.data('accessGroupPermissionTree', (initialPermissionIds, bundles, wire) => {
        const unique = (values) => Array.from(new Set(values.map((value) => Number(value))));
        const mapFrom = (values) => Object.fromEntries(unique(values).map((value) => [value, true]));

        return {
            selected: unique(initialPermissionIds),
            selectedMap: mapFrom(initialPermissionIds),
            bundles,

            has(permissionId) {
                return this.selectedMap[Number(permissionId)] === true;
            },

            allSelected(permissionIds) {
                return permissionIds.length > 0 && permissionIds.every((permissionId) => this.has(permissionId));
            },

            partiallySelected(permissionIds) {
                return !this.allSelected(permissionIds) && permissionIds.some((permissionId) => this.has(permissionId));
            },

            togglePermission(permissionId) {
                permissionId = Number(permissionId);

                if (this.has(permissionId)) {
                    this.setSelected(this.selected.filter((selectedId) => selectedId !== permissionId));
                    this.enforceReadDependencies();
                    this.sync();

                    return;
                }

                this.setSelected([...this.selected, permissionId]);
                this.includeReadForPermission(permissionId);
                this.sync();
            },

            toggleBundle(bundleKey) {
                const bundle = this.bundle(bundleKey);

                if (!bundle) {
                    return;
                }

                if (this.allSelected(bundle.permissionIds)) {
                    this.setSelected(this.selected.filter((permissionId) => !bundle.permissionIds.includes(permissionId)));
                    this.enforceReadDependencies();
                    this.sync();

                    return;
                }

                this.setSelected([...this.selected, ...bundle.permissionIds]);
                this.sync();
            },

            toggleActionGroup(bundleKey, actionKey) {
                const bundle = this.bundle(bundleKey);
                const action = bundle?.actions.find((item) => item.key === actionKey);

                if (!bundle || !action) {
                    return;
                }

                if (this.allSelected(action.permissionIds)) {
                    this.setSelected(this.selected.filter((permissionId) => !action.permissionIds.includes(permissionId)));
                    this.enforceReadDependencies();
                    this.sync();

                    return;
                }

                const readIds = actionKey === 'read' ? [] : bundle.readPermissionIds;
                this.setSelected([...this.selected, ...action.permissionIds, ...readIds]);
                this.sync();
            },

            includeReadForPermission(permissionId) {
                const bundle = this.bundles.find((item) => item.permissionIds.includes(permissionId));
                const action = bundle?.actions.find((item) => item.permissionIds.includes(permissionId));

                if (!bundle || !action || action.key === 'read') {
                    return;
                }

                this.setSelected([...this.selected, ...bundle.readPermissionIds]);
            },

            enforceReadDependencies() {
                this.bundles.forEach((bundle) => {
                    const nonReadIds = bundle.actions
                        .filter((action) => action.key !== 'read')
                        .flatMap((action) => action.permissionIds);

                    if (nonReadIds.some((permissionId) => this.has(permissionId))) {
                        this.setSelected([...this.selected, ...bundle.readPermissionIds]);
                    }
                });
            },

            bundle(bundleKey) {
                return this.bundles.find((item) => item.key === bundleKey);
            },

            sync() {
                wire.set('permissionIds', this.selected, false);
            },

            setSelected(permissionIds) {
                this.selected = unique(permissionIds);
                this.selectedMap = mapFrom(this.selected);
            },
        };
    });
});
