@props([
    'documentLevels',
])

<div class="space-y-6" data-approval-flow-builder>
    <x-ui.page-header
        title="Approval Flow"
        description="Atur pihak yang masuk ke lembar pengesahan untuk setiap level dokumen."
    />

    <div class="grid gap-5 xl:grid-cols-[360px_minmax(0,1fr)]">
        <x-ui.panel title="Level Dokumen" description="Pilih level dokumen yang ingin disetting." class="h-fit xl:sticky xl:top-8">
            <div class="space-y-3">
                @foreach ($documentLevels as $levelKey => $level)
                    <button
                        type="button"
                        class="group w-full rounded-lg border border-slate-200 bg-white p-4 text-left transition hover:border-sky-200 hover:bg-sky-50 data-[active=true]:border-sky-300 data-[active=true]:bg-sky-50 data-[active=true]:shadow-sm"
                        data-level-option
                        data-level-key="{{ $levelKey }}"
                        data-level-name="{{ $level['name'] }}"
                        data-level-description="{{ $level['approval_description'] }}"
                        data-level-stages='@json($level['default_stages'])'
                    >
                        <span class="block font-semibold text-slate-900 group-data-[active=true]:text-sky-800">
                            {{ $level['name'] }}
                        </span>
                        <span class="mt-1 block text-sm leading-6 text-slate-500">
                            {{ $level['approval_description'] }}
                        </span>
                    </button>
                @endforeach
            </div>
        </x-ui.panel>

        <div class="space-y-5">
            <x-ui.empty-state
                title="Pilih Level Dokumen"
                description="Flow approval akan muncul setelah level dokumen dipilih."
                data-empty-state
            />

            <x-ui.panel class="hidden" data-flow-panel>
                <div class="flex flex-col gap-4 border-b border-slate-200 pb-5 md:flex-row md:items-start md:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-sky-700">Flow Aktif</p>
                        <h2 class="mt-1 text-xl font-bold text-slate-950" data-selected-level-title></h2>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500" data-selected-level-description></p>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <x-ui.action-button type="button" variant="secondary" data-add-stage>
                            <flux:icon name="plus" class="mr-2 size-4" />
                            Tambah Tahap
                        </x-ui.action-button>
                        <x-ui.action-button type="button" data-save-flow>
                            Simpan Pengaturan
                        </x-ui.action-button>
                    </div>
                </div>

                <form class="mt-5 space-y-4" data-stage-form>
                    <input type="hidden" name="document_level" data-selected-level-input>

                    <div class="space-y-4" data-stage-list></div>
                </form>
            </x-ui.panel>
        </div>
    </div>

    <template data-stage-template>
        <section class="rounded-lg border border-slate-200 bg-slate-50 p-4" data-stage-card>
            <div class="flex flex-col gap-4 md:flex-row md:items-start">
                <div class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-sky-100 text-base font-bold text-sky-700" data-stage-number></div>

                <div class="min-w-0 flex-1 space-y-4">
                    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                        <div>
                            <h3 class="font-semibold text-slate-950" data-stage-title></h3>
                            <p class="mt-1 text-sm text-slate-500">Pihak ini akan tampil sebagai penanda tangan pada lembar pengesahan.</p>
                        </div>

                        <x-ui.icon-button
                            icon="trash"
                            label="Hapus tahap"
                            variant="secondary"
                            data-remove-stage
                        />
                    </div>

                    <div class="grid gap-4 lg:grid-cols-[minmax(180px,240px)_minmax(0,1fr)]">
                        <x-ui.input
                            label="Nama Tahap"
                            name="stage_label[]"
                            placeholder="Contoh: Diperiksa oleh"
                            data-stage-label
                        />
                        <x-ui.input
                            label="Pihak Approval"
                            name="approver_party[]"
                            placeholder="Contoh: Manager QA / Kepala Departemen"
                            data-stage-party
                        />
                    </div>
                </div>
            </div>
        </section>
    </template>
</div>

@once
    <script>
        (() => {
            const builders = document.querySelectorAll('[data-approval-flow-builder]');

            builders.forEach((builder) => {
                const emptyState = builder.querySelector('[data-empty-state]');
                const flowPanel = builder.querySelector('[data-flow-panel]');
                const stageList = builder.querySelector('[data-stage-list]');
                const stageTemplate = builder.querySelector('[data-stage-template]');
                const selectedTitle = builder.querySelector('[data-selected-level-title]');
                const selectedDescription = builder.querySelector('[data-selected-level-description]');
                const selectedLevelInput = builder.querySelector('[data-selected-level-input]');
                const flowsByLevel = {};
                let activeLevelKey = null;

                const serializeStages = () => Array.from(stageList.querySelectorAll('[data-stage-card]')).map((card) => ({
                    label: card.querySelector('[data-stage-label]').value,
                    party: card.querySelector('[data-stage-party]').value,
                }));

                const persistActiveLevel = () => {
                    if (activeLevelKey) {
                        flowsByLevel[activeLevelKey] = serializeStages();
                    }
                };

                const refreshStages = () => {
                    stageList.querySelectorAll('[data-stage-card]').forEach((card, index) => {
                        const number = index + 1;
                        card.querySelector('[data-stage-number]').textContent = number;
                        card.querySelector('[data-stage-title]').textContent = `Tahap ${number}`;
                    });
                };

                const addStage = (label = '', party = '') => {
                    const fragment = stageTemplate.content.cloneNode(true);
                    const card = fragment.querySelector('[data-stage-card]');

                    card.querySelector('[data-stage-label]').value = label;
                    card.querySelector('[data-stage-party]').value = party;
                    stageList.appendChild(fragment);
                    refreshStages();
                };

                const renderStages = (stages) => {
                    stageList.innerHTML = '';

                    stages.forEach((stage) => addStage(stage.label, stage.party));
                };

                const getDefaultStages = (option) => JSON.parse(option.dataset.levelStages || '[]').map((label) => ({
                    label,
                    party: '',
                }));

                const selectLevel = (option) => {
                    persistActiveLevel();

                    activeLevelKey = option.dataset.levelKey;
                    selectedLevelInput.value = activeLevelKey;

                    builder.querySelectorAll('[data-level-option]').forEach((item) => {
                        item.dataset.active = String(item === option);
                    });

                    selectedTitle.textContent = option.dataset.levelName;
                    selectedDescription.textContent = option.dataset.levelDescription;

                    renderStages(flowsByLevel[activeLevelKey] ?? getDefaultStages(option));

                    emptyState.classList.add('hidden');
                    flowPanel.classList.remove('hidden');
                };

                builder.querySelectorAll('[data-level-option]').forEach((option) => {
                    option.addEventListener('click', () => selectLevel(option));
                });

                builder.querySelector('[data-add-stage]').addEventListener('click', () => {
                    addStage();
                    persistActiveLevel();
                });

                builder.querySelector('[data-save-flow]').addEventListener('click', () => {
                    persistActiveLevel();
                });

                stageList.addEventListener('click', (event) => {
                    const removeButton = event.target.closest('[data-remove-stage]');

                    if (! removeButton) {
                        return;
                    }

                    removeButton.closest('[data-stage-card]').remove();
                    refreshStages();
                    persistActiveLevel();
                });

                stageList.addEventListener('input', () => {
                    persistActiveLevel();
                });
            });
        })();
    </script>
@endonce
