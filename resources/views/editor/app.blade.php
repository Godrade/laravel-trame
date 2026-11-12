<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>{{ $config['title'] }} · Trame</title>
    <link rel="stylesheet" href="{{ $assets['css'] }}">
    <script>window.TrameEditor = @json($config);</script>
    <script type="module" src="{{ $assets['js'] }}"></script>
</head>
<body class="h-full overflow-hidden bg-zinc-100 font-sans text-zinc-900 antialiased">
<div x-data="trameEditor" class="flex h-full flex-col" @keydown.window="onKey($event)">

    {{-- Loading / failure --}}
    <div x-show="!ready" class="flex h-full items-center justify-center text-sm text-zinc-500">
        <span x-show="!loadError" x-text="t.loading"></span>
        <span x-show="loadError" x-cloak class="text-rose-600" x-text="t.load_failed + ' : ' + loadError"></span>
    </div>

    <template x-if="ready">
    <div class="flex h-full flex-col">

        {{-- ============================================================ Top bar --}}
        <header class="flex h-12 shrink-0 items-center gap-2 border-b border-zinc-200 bg-white px-2">
            <a :href="cfg.returnUrl" class="tr-btn-ghost" :title="t.back">
                <svg class="size-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.8 4.2a.75.75 0 0 1 0 1.06L8.06 10l4.74 4.74a.75.75 0 1 1-1.06 1.06l-5.27-5.27a.75.75 0 0 1 0-1.06l5.27-5.27a.75.75 0 0 1 1.06 0Z"/></svg>
                <span x-text="t.back"></span>
            </a>
            <div class="min-w-0">
                <div class="truncate text-sm font-semibold" x-text="cfg.title"></div>
            </div>

            <div class="mx-auto flex items-center gap-3">
                {{-- Locales --}}
                <div class="tr-seg" x-show="defs.locales.length > 1">
                    <template x-for="code in defs.locales" :key="code">
                        <button type="button" class="uppercase" :aria-pressed="locale === code" @click="locale = code" x-text="code"></button>
                    </template>
                </div>

                {{-- Breakpoints --}}
                <div class="tr-seg">
                    <template x-for="bp in breakpoints" :key="bp">
                        <button type="button" :aria-pressed="breakpoint === bp" @click="breakpoint = bp" :title="t.breakpoints[bp]">
                            <svg x-show="bp === 'base'" class="mx-auto size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="7" y="2.5" width="10" height="19" rx="2"/><path d="M11 18.5h2"/></svg>
                            <svg x-show="bp === 'md'" class="mx-auto size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="2.5" width="16" height="19" rx="2"/><path d="M11 18.5h2"/></svg>
                            <svg x-show="bp === 'lg'" class="mx-auto size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2.5" y="4" width="19" height="12.5" rx="1.5"/><path d="M8 20h8M12 16.5V20"/></svg>
                        </button>
                    </template>
                </div>

                {{-- Simple / advanced mode (only with the "trame.advanced" gate) --}}
                <div class="tr-seg" x-show="canAdvanced" :title="t.mode_help">
                    <button type="button" :aria-pressed="mode === 'simple'" @click="setMode('simple')" x-text="t.mode_simple"></button>
                    <button type="button" :aria-pressed="mode === 'advanced'" @click="setMode('advanced')" x-text="t.mode_advanced"></button>
                </div>

                <div class="flex items-center">
                    <button type="button" class="tr-icon-btn" :disabled="!history.length" @click="undo()" :title="t.undo + ' (⌘Z)'">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 14 4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 0 11H11"/></svg>
                    </button>
                    <button type="button" class="tr-icon-btn" :disabled="!future.length" @click="redo()" :title="t.redo + ' (⇧⌘Z)'">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 14 5-5-5-5"/><path d="M20 9H9.5a5.5 5.5 0 0 0 0 11H13"/></svg>
                    </button>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <button type="button" x-show="errorCount" x-cloak class="rounded-full bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-700"
                        @click="panel = 'structure'" x-text="t.errors_count.replace(':count', errorCount)"></button>
                <span class="hidden text-xs text-zinc-500 lg:inline" x-text="status"></span>
                <span x-show="readOnly" x-cloak class="rounded-full bg-sky-100 px-2.5 py-0.5 text-xs font-medium text-sky-800" :title="t.read_only_banner" x-text="t.read_only_badge"></span>
                <template x-if="!templateMode && !readOnly">
                    <div class="flex items-center gap-2">
                        <button type="button" class="tr-btn-ghost text-xs" x-show="publishedAt" @click="unpublish()" x-text="t.unpublish"></button>
                        <button type="button" class="tr-btn-outline" :disabled="saving || !dirty" @click="save()" x-text="saving ? t.saving : t.save"></button>
                        <button type="button" class="tr-btn-primary" :disabled="publishing || saving || (!dirty && publishedAt && !hasUnpublished)" @click="publish()" x-text="t.publish"></button>
                    </div>
                </template>
                <template x-if="templateMode">
                    <div class="flex items-center gap-2">
                        <button type="button" class="tr-btn-outline" @click="leaveTemplate()" x-text="t.back_to_page"></button>
                        <button type="button" class="tr-btn-primary" x-show="!readOnly" :disabled="saving || !dirty" @click="saveTemplateContent()" x-text="saving ? t.saving : t.save_template"></button>
                    </div>
                </template>
            </div>
        </header>

        {{-- Read-only banner: trame.view without trame.edit --}}
        <div x-show="readOnly" x-cloak class="flex items-center gap-2 border-b border-sky-200 bg-sky-50 px-4 py-1.5 text-sm text-sky-900">
            <span x-text="t.read_only_banner"></span>
        </div>

        {{-- Template edit mode banner --}}
        <div x-show="templateMode" x-cloak class="flex items-center gap-2 border-b border-amber-200 bg-amber-50 px-4 py-1.5 text-sm text-amber-900">
            <span class="text-amber-500">◆</span>
            <span x-text="t.editing_template.replace(':name', templateMode?.name ?? '')"></span>
            <span x-show="templateMode?.is_global" class="text-xs text-amber-700" x-text="'· ' + t.template_global_help"></span>
        </div>

        <div class="flex min-h-0 flex-1">

            {{-- ======================================================= Left panel --}}
            <aside class="flex w-72 shrink-0 flex-col border-e border-zinc-200 bg-white">
                <nav class="flex border-b border-zinc-200 px-2">
                    <template x-for="tab in ['add', 'structure', 'templates']" :key="tab">
                        <button type="button" @click="panel = tab"
                                class="-mb-px border-b-2 px-3 py-2.5 text-sm font-medium transition"
                                :class="panel === tab ? 'border-indigo-600 text-zinc-900' : 'border-transparent text-zinc-500 hover:text-zinc-800'"
                                x-text="t['tab_' + tab]"></button>
                    </template>
                </nav>

                <div class="min-h-0 flex-1 overflow-y-auto p-3">

                    {{-- Add --}}
                    <div x-show="panel === 'add'" class="space-y-5">
                        <section class="space-y-2">
                            <h3 class="tr-heading" x-text="t.sections"></h3>
                            <div class="grid grid-cols-3 gap-2">
                                <template x-for="preset in presets" :key="preset.key">
                                    <button type="button" @click="addSection(preset, selectedId)" :title="preset.spans.join(' / ')"
                                            draggable="true" @dragstart="dragStart($event, { kind: 'section', spans: preset.spans })" @dragend="dragEnd()"
                                            class="flex h-10 cursor-grab gap-0.5 rounded-md border border-zinc-200 p-1.5 transition hover:border-indigo-400 hover:bg-indigo-50">
                                        <template x-for="(span, i) in preset.spans" :key="i">
                                            <span class="rounded-sm bg-zinc-300" :style="`flex-grow: ${span}`"></span>
                                        </template>
                                    </button>
                                </template>
                            </div>
                        </section>

                        <template x-for="category in [...new Set(paletteBlocks.map(b => b.category))]" :key="category">
                            <section class="space-y-2">
                                <h3 class="tr-heading" x-text="t.categories?.[category] ?? category"></h3>
                                <div class="grid grid-cols-2 gap-2">
                                    <template x-for="block in paletteBlocks.filter(b => b.category === category)" :key="block.type">
                                        <button type="button" @click="addBlock(block.type)"
                                                draggable="true" @dragstart="dragStart($event, { kind: 'block', type: block.type })" @dragend="dragEnd()"
                                                class="flex cursor-grab items-center gap-2 rounded-md border border-zinc-200 px-2 py-2 text-start text-sm transition hover:border-indigo-400 hover:bg-indigo-50">
                                            <span class="flex size-6 shrink-0 items-center justify-center rounded bg-zinc-100 text-xs font-semibold text-zinc-600 uppercase" x-text="block.label.slice(0, 1)"></span>
                                            <span class="truncate" x-text="block.label"></span>
                                        </button>
                                    </template>
                                </div>
                            </section>
                        </template>

                        <p class="text-xs leading-relaxed text-zinc-500" x-text="t.add_block_help"></p>
                        <p class="text-xs leading-relaxed text-zinc-500" x-text="t.inline_help"></p>
                    </div>

                    {{-- Structure --}}
                    <div x-show="panel === 'structure'" class="space-y-px">
                        <template x-for="item in tree" :key="item.id">
                            <button type="button" @click="select(item.id, true)"
                                    class="flex w-full items-center gap-1.5 rounded px-1.5 py-1 text-start text-sm transition"
                                    :class="selectedId === item.id ? 'bg-indigo-50 text-indigo-900' : 'text-zinc-700 hover:bg-zinc-100'"
                                    :style="`padding-inline-start: ${0.375 + item.depth * 0.875}rem`">
                                <span class="size-2 shrink-0 rounded-sm"
                                      :class="{ section: 'bg-indigo-500', row: 'bg-sky-500', column: 'bg-emerald-500', block: 'bg-orange-500', global: 'bg-amber-500' }[item.kind]"></span>
                                <span class="truncate" x-text="item.label"></span>
                                <span x-show="item.error" class="ms-auto size-2 shrink-0 rounded-full bg-rose-500"></span>
                            </button>
                        </template>
                        <p x-show="!tree.length" class="text-sm text-zinc-500" x-text="t.empty_text"></p>
                    </div>

                    {{-- Templates --}}
                    <div x-show="panel === 'templates'" class="space-y-4">
                        <p x-show="!templates.length" class="text-sm leading-relaxed text-zinc-500" x-text="t.no_templates"></p>
                        <template x-for="kind in ['section', 'row', 'block']" :key="kind">
                            <section class="space-y-1.5" x-show="templatesOf(kind).length">
                                <h3 class="tr-heading" x-text="t[kind]"></h3>
                                <template x-for="template in templatesOf(kind)" :key="template.id">
                                    <div class="group flex cursor-grab items-center gap-2 rounded-md border border-zinc-200 px-2 py-1.5"
                                         draggable="true" @dragstart="dragStart($event, { kind: 'template', id: template.id })" @dragend="dragEnd()">
                                        <span x-show="template.is_global" class="text-amber-500" :title="t.global">◆</span>
                                        <span class="min-w-0 flex-1 truncate text-sm" x-text="template.name"></span>
                                        <button type="button" class="tr-btn-ghost px-1.5 py-0.5 text-xs" @click="insertTemplate(template)" x-text="t.insert" x-show="!templateMode"></button>
                                        <button type="button" class="tr-btn-ghost px-1.5 py-0.5 text-xs" @click="editTemplate(template)" x-text="t.edit" x-show="!templateMode && !simple"></button>
                                        <button type="button" class="tr-icon-btn size-6 opacity-0 group-hover:opacity-100" @click="deleteTemplate(template)" :title="t.delete" x-show="!readOnly && !simple">✕</button>
                                    </div>
                                </template>
                            </section>
                        </template>
                    </div>
                </div>
            </aside>

            {{-- ========================================================== Canvas --}}
            <main x-ref="stage" class="relative min-w-0 flex-1 overflow-hidden bg-zinc-200/70">
                <div class="absolute inset-6 flex justify-center">
                    <div class="overflow-hidden rounded-md bg-white shadow-lg ring-1 ring-zinc-900/5"
                         :style="`width: ${frameWidth * scale}px; height: ${frameHeight * scale}px`">
                        <iframe x-ref="frame" :src="frameSrc" title="Preview"
                                class="origin-top-left border-0 bg-white"
                                :style="`width: ${frameWidth}px; height: ${frameHeight}px; transform: scale(${scale})`"></iframe>
                    </div>
                </div>

                <div x-show="frameReady && !doc.sections.length" x-cloak class="absolute inset-0 flex items-center justify-center p-6">
                    <div class="w-full max-w-md space-y-4 rounded-xl bg-white p-6 text-center shadow-lg ring-1 ring-zinc-900/5">
                        <div>
                            <h2 class="text-base font-semibold" x-text="t.empty_title"></h2>
                            <p class="mt-1 text-sm text-zinc-500" x-text="t.empty_text"></p>
                        </div>
                        <div class="grid grid-cols-3 gap-2">
                            <template x-for="preset in presets" :key="preset.key">
                                <button type="button" @click="addSection(preset)"
                                        class="flex h-12 gap-1 rounded-md border border-zinc-200 p-2 transition hover:border-indigo-400 hover:bg-indigo-50">
                                    <template x-for="(span, i) in preset.spans" :key="i">
                                        <span class="rounded-sm bg-zinc-300" :style="`flex-grow: ${span}`"></span>
                                    </template>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
            </main>

            {{-- ===================================================== Right panel --}}
            <aside class="flex w-80 shrink-0 flex-col border-s border-zinc-200 bg-white">
                <template x-if="!selected">
                    <div class="p-6 text-center">
                        <p class="text-sm font-medium" x-text="t.nothing_selected"></p>
                        <p class="mt-1 text-xs leading-relaxed text-zinc-500" x-text="t.nothing_selected_help"></p>
                    </div>
                </template>

                <template x-if="selected">
                    <div class="flex min-h-0 flex-1 flex-col">
                        {{-- Header + actions --}}
                        <div class="flex items-center gap-1 border-b border-zinc-200 px-3 py-2">
                            <span class="size-2.5 rounded-sm"
                                  :class="{ section: 'bg-indigo-500', row: 'bg-sky-500', column: 'bg-emerald-500', block: 'bg-orange-500', global: 'bg-amber-500' }[selected.kind]"></span>
                            <span class="me-auto truncate text-sm font-semibold" x-text="kindLabel(selected)"></span>
                            <button type="button" class="tr-icon-btn" x-show="selected.parentId" @click="selectParent()" :title="t.select_parent">↖</button>
                            <button type="button" class="tr-icon-btn" :disabled="selected.position === 0" @click="move(selectedId, -1)" :title="t.move_up">↑</button>
                            <button type="button" class="tr-icon-btn" :disabled="selected.position === selected.list.length - 1" @click="move(selectedId, 1)" :title="t.move_down">↓</button>
                            <button type="button" class="tr-icon-btn" @click="duplicate(selectedId)" :title="t.duplicate">⧉</button>
                            <button type="button" class="tr-icon-btn" x-show="canTemplate(selected) && !readOnly && !simple" @click="openTemplateDialog(selectedId)" :title="t.save_as_template">◆</button>
                            <button type="button" class="tr-icon-btn hover:text-rose-600" @click="remove(selectedId)" :title="t.delete">✕</button>
                        </div>

                        <div class="min-h-0 flex-1 space-y-5 overflow-y-auto p-3">
                            <template x-for="message in nodeErrors" :key="message">
                                <p class="rounded-md bg-rose-50 px-2.5 py-1.5 text-xs text-rose-700" x-text="message"></p>
                            </template>

                            {{-- Global reference --}}
                            <template x-if="selected.kind === 'global'">
                                <div class="space-y-3">
                                    <p class="text-sm font-medium" x-text="templateName(selected.node.ref)"></p>
                                    <p class="text-xs leading-relaxed text-zinc-500" x-text="t.global_help"></p>
                                    <button type="button" class="tr-btn-primary w-full" x-show="!templateMode && !simple" @click="editTemplate(templates.find(t => t.id === selected.node.ref))" x-text="t.edit_template"></button>
                                    <button type="button" class="tr-btn-outline w-full" x-show="!simple" @click="detach(selectedId)" x-text="t.detach"></button>
                                </div>
                            </template>

                            {{-- Section: add row --}}
                            <template x-if="selected.kind === 'section'">
                                <div class="space-y-2">
                                    <h3 class="tr-heading" x-text="t.add_row"></h3>
                                    <div class="grid grid-cols-3 gap-2">
                                        <template x-for="preset in presets" :key="preset.key">
                                            <button type="button" @click="addRow(preset)" class="flex h-8 gap-0.5 rounded-md border border-zinc-200 p-1.5 hover:border-indigo-400 hover:bg-indigo-50">
                                                <template x-for="(span, i) in preset.spans" :key="i">
                                                    <span class="rounded-sm bg-zinc-300" :style="`flex-grow: ${span}`"></span>
                                                </template>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            {{-- Row: layout presets --}}
                            <template x-if="selected.kind === 'row'">
                                <div class="space-y-2">
                                    <h3 class="tr-heading" x-text="t.layout"></h3>
                                    <div class="grid grid-cols-3 gap-2">
                                        <template x-for="preset in presets" :key="preset.key">
                                            <button type="button" @click="applyPreset(selectedId, preset)" class="flex h-8 gap-0.5 rounded-md border border-zinc-200 p-1.5 hover:border-indigo-400 hover:bg-indigo-50">
                                                <template x-for="(span, i) in preset.spans" :key="i">
                                                    <span class="rounded-sm bg-zinc-300" :style="`flex-grow: ${span}`"></span>
                                                </template>
                                            </button>
                                        </template>
                                    </div>
                                    <button type="button" class="tr-btn-outline w-full" @click="addColumn(selectedId)" x-text="t.add_column"></button>
                                </div>
                            </template>

                            {{-- Column: span for the current breakpoint --}}
                            <template x-if="selected.kind === 'column' && !simple">
                                <div class="space-y-2">
                                    <div class="flex items-center justify-between">
                                        <h3 class="tr-heading" x-text="t.column_width + ' · ' + t.breakpoints[breakpoint]"></h3>
                                        <button type="button" class="text-xs text-indigo-600 hover:underline"
                                                x-show="breakpoint !== 'base' && !spanIsInherited()" @click="setSpan(null)" x-text="t.reset"></button>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <input type="range" min="1" max="12" step="1" class="flex-1 accent-indigo-600"
                                               :value="spanValue()" @input="setSpan($event.target.value)">
                                        <span class="w-12 text-end text-sm tabular-nums" x-text="spanValue() + '/12'"></span>
                                    </div>
                                    <p x-show="spanIsInherited()" class="text-xs text-zinc-400" x-text="t.inherited"></p>
                                </div>
                            </template>

                            {{-- Fields --}}
                            <template x-for="field in panelFields" :key="selectedId + ':' + scopeOf(field) + ':' + field.name">
                                <div class="space-y-1.5" :class="field.group && 'border-t border-zinc-100 pt-4'">
                                    <h3 x-show="field.group" class="tr-heading" x-text="field.group"></h3>
                                    <div class="flex items-center justify-between gap-2">
                                        <label class="tr-label" x-text="field.label + (field.required ? ' *' : '')"></label>
                                        <span x-show="field.translatable" class="rounded bg-zinc-100 px-1.5 py-0.5 text-[10px] font-semibold text-zinc-500 uppercase" x-text="locale"></span>
                                        <span x-show="field.responsive && !simple" class="ms-auto flex items-center gap-2">
                                            <button type="button" class="text-xs text-indigo-600 hover:underline"
                                                    x-show="breakpoint !== 'base' && !isInherited(field)" @click="setValue(field, null)" x-text="t.reset"></button>
                                            <span class="rounded bg-zinc-100 px-1.5 py-0.5 text-[10px] font-semibold text-zinc-500" x-text="t.breakpoints[breakpoint]"></span>
                                        </span>
                                    </div>
                                    <p x-show="isInherited(field)" class="text-xs text-zinc-400" x-text="t.inherited"></p>
                                    <p x-show="field.help" class="text-xs leading-relaxed text-zinc-500" x-text="field.help"></p>

                                    {{-- Simple mode does not overwrite a value set per screen in the advanced mode. --}}
                                    <p x-show="isLockedResponsive(field)" class="rounded-md bg-zinc-50 px-2.5 py-1.5 text-xs text-zinc-500" x-text="t.per_screen_locked"></p>
                                    <template x-if="!isLockedResponsive(field)">
                                        <div>
                                            @include('trame::editor.partials.field-control', ['f' => 'field', 'c' => '', 'nested' => false])
                                        </div>
                                    </template>

                                    <template x-for="message in fieldErrors(field)" :key="message">
                                        <p class="text-xs text-rose-600" x-text="message"></p>
                                    </template>
                                </div>
                            </template>

                            {{-- Visibility per breakpoint --}}
                            <template x-if="canHide">
                                <div class="space-y-1.5 border-t border-zinc-100 pt-4">
                                    <h3 class="tr-heading" x-text="t.visibility"></h3>
                                    <p class="text-xs text-zinc-500" x-text="t.hide_on"></p>
                                    <div class="tr-seg">
                                        <template x-for="bp in breakpoints" :key="bp">
                                            <button type="button" :aria-pressed="isHidden(bp)" @click="toggleHidden(bp)" x-text="t.breakpoints[bp]"></button>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </aside>
        </div>

        {{-- ====================================================== Media modal --}}
        <div x-show="media.open" x-cloak class="fixed inset-0 z-40 flex items-center justify-center bg-zinc-900/40 p-6" @click.self="media.open = false">
            <div class="flex max-h-full w-full max-w-3xl flex-col overflow-hidden rounded-xl bg-white shadow-2xl">
                <div class="flex items-center gap-3 border-b border-zinc-200 px-4 py-3">
                    <h2 class="me-auto text-sm font-semibold" x-text="t.media_library"></h2>
                    <label class="tr-btn-primary cursor-pointer" x-show="!readOnly">
                        <span x-text="media.uploading ? t.uploading : t.upload"></span>
                        <input type="file" accept="image/jpeg,image/png,image/gif,image/webp,image/avif" multiple class="hidden"
                               @change="upload([...$event.target.files]); $event.target.value = ''" :disabled="media.uploading">
                    </label>
                    <button type="button" class="tr-icon-btn" @click="media.open = false" :title="t.close">✕</button>
                </div>
                <div class="min-h-0 flex-1 overflow-y-auto p-4">
                    <p x-show="!media.loading && !media.items.length" class="py-10 text-center text-sm text-zinc-500" x-text="t.no_media"></p>
                    <div class="grid grid-cols-3 gap-3 sm:grid-cols-4">
                        <template x-for="item in media.items" :key="item.id">
                            <button type="button" @click="pickMedia(item)" class="group overflow-hidden rounded-md border border-zinc-200 text-start hover:border-indigo-500">
                                <img :src="item.url" :alt="item.name" class="aspect-square w-full bg-zinc-50 object-cover" loading="lazy">
                                <span class="block truncate px-2 py-1 text-xs text-zinc-600" x-text="item.name"></span>
                            </button>
                        </template>
                    </div>
                </div>
                <div class="flex items-center justify-between border-t border-zinc-200 px-4 py-2 text-xs text-zinc-500" x-show="media.total > media.items.length || media.page > 1">
                    <button type="button" class="tr-btn-ghost" :disabled="media.page <= 1" @click="loadMedia(media.page - 1)" x-text="t.previous"></button>
                    <span x-text="media.page"></span>
                    <button type="button" class="tr-btn-ghost" :disabled="media.page * 30 >= media.total" @click="loadMedia(media.page + 1)" x-text="t.next"></button>
                </div>
            </div>
        </div>

        {{-- =================================================== Template modal --}}
        <div x-show="templateDialog.open" x-cloak class="fixed inset-0 z-40 flex items-center justify-center bg-zinc-900/40 p-6" @click.self="templateDialog.open = false">
            <form class="w-full max-w-sm space-y-4 rounded-xl bg-white p-5 shadow-2xl" @submit.prevent="saveTemplate()">
                <h2 class="text-sm font-semibold" x-text="t.save_as_template"></h2>
                <div class="space-y-1.5">
                    <label class="tr-label" x-text="t.template_name"></label>
                    <input type="text" class="tr-input" x-model="templateDialog.name" maxlength="120" required>
                </div>
                <label class="flex items-start gap-2">
                    <input type="checkbox" class="mt-0.5 accent-indigo-600" x-model="templateDialog.global">
                    <span>
                        <span class="block text-sm font-medium" x-text="t.template_global"></span>
                        <span class="block text-xs text-zinc-500" x-text="t.template_global_help"></span>
                    </span>
                </label>
                <div class="flex justify-end gap-2">
                    <button type="button" class="tr-btn-ghost" @click="templateDialog.open = false" x-text="t.cancel"></button>
                    <button type="submit" class="tr-btn-primary" x-text="t.confirm"></button>
                </div>
            </form>
        </div>

        {{-- ============================================================ Toast --}}
        <div x-show="toast" x-cloak x-transition.opacity class="fixed bottom-4 left-1/2 z-50 -translate-x-1/2">
            <div class="rounded-lg px-4 py-2 text-sm shadow-lg"
                 :class="{ success: 'bg-emerald-600 text-white', error: 'bg-rose-600 text-white', info: 'bg-zinc-900 text-white' }[toast?.tone ?? 'info']"
                 x-text="toast?.message"></div>
        </div>
    </div>
    </template>
</div>
</body>
</html>
