{{-- Control for one field. $f: Alpine variable holding the field; $c: extra argument (', item' inside a repeater item); $nested: inside an item. --}}
<template x-if="{{ $f }}.type === 'text' || {{ $f }}.type === 'url'">
    <input type="text" class="tr-input" :value="value({{ $f }}{{ $c }})" :placeholder="placeholder({{ $f }}{{ $c }}) || ({{ $f }}.type === 'url' ? 'https://… /page #ancre' : '')"
           :maxlength="{{ $f }}.max ?? ({{ $f }}.type === 'url' ? 2048 : 255)" @input="setValue({{ $f }}, $event.target.value{{ $c }})">
</template>

<template x-if="{{ $f }}.type === 'textarea'">
    <textarea rows="4" class="tr-input" :value="value({{ $f }}{{ $c }})" :placeholder="placeholder({{ $f }}{{ $c }})" @input="setValue({{ $f }}, $event.target.value{{ $c }})"></textarea>
</template>

<template x-if="{{ $f }}.type === 'number'">
    <input type="number" class="tr-input" :value="value({{ $f }}{{ $c }})" :min="{{ $f }}.min" :max="{{ $f }}.max"
           @input="setValue({{ $f }}, $event.target.value === '' ? null : Number($event.target.value){{ $c }})">
</template>

<template x-if="{{ $f }}.type === 'toggle'">
    <button type="button" role="switch" :aria-checked="!!value({{ $f }}{{ $c }})" @click="setValue({{ $f }}, !value({{ $f }}{{ $c }}){{ $c }})"
            class="relative inline-flex h-5 w-9 items-center rounded-full transition"
            :class="value({{ $f }}{{ $c }}) ? 'bg-indigo-600' : 'bg-zinc-300'">
        <span class="inline-block size-4 rounded-full bg-white shadow transition" :class="value({{ $f }}{{ $c }}) ? 'translate-x-4.5' : 'translate-x-0.5'"></span>
    </button>
</template>

{{-- Options shown as visual samples (radius, shadow, border, space); the value name stays under each. --}}
<template x-if="{{ $f }}.type === 'select' && {{ $f }}.preview">
    <div class="flex flex-wrap gap-1.5">
        <template x-for="option in {{ $f }}.options" :key="option.value">
            <button type="button" :aria-pressed="isSelectedOption({{ $f }}, option{{ $c }})" @click="setValue({{ $f }}, option.value{{ $c }})" :title="option.label"
                    class="flex w-11 flex-col items-center gap-1 rounded-md border border-zinc-200 bg-white pt-2 pb-1 transition hover:border-indigo-300 aria-pressed:border-indigo-500 aria-pressed:bg-indigo-50">
                <span class="flex size-7 items-center justify-center" aria-hidden="true">
                    <span :style="sampleStyle({{ $f }}.preview, option.value)"></span>
                </span>
                <span class="text-[10px] leading-none text-zinc-500" x-text="option.label"></span>
            </button>
        </template>
    </div>
</template>

<template x-if="{{ $f }}.type === 'select' && !{{ $f }}.preview && {{ $f }}.options.length <= 6">
    <div class="tr-seg">
        <template x-for="option in {{ $f }}.options" :key="option.value">
            <button type="button" :aria-pressed="isSelectedOption({{ $f }}, option{{ $c }})" @click="setValue({{ $f }}, option.value{{ $c }})" x-text="option.label"></button>
        </template>
    </div>
</template>

<template x-if="{{ $f }}.type === 'select' && !{{ $f }}.preview && {{ $f }}.options.length > 6">
    <select class="tr-input" @change="setValue({{ $f }}, {{ $f }}.options[$event.target.selectedIndex].value{{ $c }})">
        <template x-for="option in {{ $f }}.options" :key="option.value">
            <option :value="option.value" :selected="isSelectedOption({{ $f }}, option{{ $c }})" x-text="option.label"></option>
        </template>
    </select>
</template>

<template x-if="{{ $f }}.type === 'color'">
    <div class="flex flex-wrap items-center gap-1.5">
        <button type="button" x-show="!{{ $f }}.required" @click="setValue({{ $f }}, null{{ $c }})" :title="t.none"
                class="relative size-6 overflow-hidden rounded-full border border-zinc-300 bg-white"
                :class="value({{ $f }}{{ $c }}) === null ? 'ring-2 ring-indigo-500 ring-offset-1' : ''">
            <span class="absolute inset-0 m-auto h-px w-7 rotate-45 bg-rose-500"></span>
        </button>
        <template x-for="color in {{ $f }}.palette" :key="color">
            <button type="button" @click="setValue({{ $f }}, color{{ $c }})" :title="color"
                    class="size-6 rounded-full border border-zinc-900/10"
                    :class="value({{ $f }}{{ $c }}) === color ? 'ring-2 ring-indigo-500 ring-offset-1' : ''"
                    :style="`background: ${swatch(color)}`"></button>
        </template>
        <label x-show="{{ $f }}.allow_custom && !simple" class="relative size-6 cursor-pointer overflow-hidden rounded-full border border-zinc-300"
               :class="String(value({{ $f }}{{ $c }}) ?? '').startsWith('#') ? 'ring-2 ring-indigo-500 ring-offset-1' : ''"
               :style="String(value({{ $f }}{{ $c }}) ?? '').startsWith('#') ? `background: ${value({{ $f }}{{ $c }})}` : 'background: conic-gradient(red, yellow, lime, aqua, blue, magenta, red)'"
               :title="t.custom">
            <input type="color" class="absolute inset-0 cursor-pointer opacity-0"
                   :value="String(value({{ $f }}{{ $c }}) ?? '').startsWith('#') ? value({{ $f }}{{ $c }}) : '#000000'"
                   @input="setValue({{ $f }}, $event.target.value{{ $c }})">
        </label>
    </div>
</template>

<template x-if="{{ $f }}.type === 'image'">
    <div class="space-y-2">
        <template x-if="value({{ $f }}{{ $c }}) !== null && mediaItem(value({{ $f }}{{ $c }}))">
            <div class="overflow-hidden rounded-md border border-zinc-200 bg-zinc-50">
                <img :src="mediaItem(value({{ $f }}{{ $c }})).url" :alt="mediaItem(value({{ $f }}{{ $c }})).name" class="max-h-40 w-full object-contain">
                <p class="truncate border-t border-zinc-200 px-2 py-1 text-xs text-zinc-500" x-text="mediaItem(value({{ $f }}{{ $c }})).name"></p>
            </div>
        </template>
        <div class="flex gap-2">
            <button type="button" class="tr-btn-outline flex-1" @click="openMedia({{ $f }}{{ $c }})" x-text="value({{ $f }}{{ $c }}) !== null ? t.change_image : t.choose_image"></button>
            <button type="button" class="tr-btn-ghost" x-show="value({{ $f }}{{ $c }}) !== null" @click="setValue({{ $f }}, null{{ $c }})" x-text="t.remove"></button>
        </div>
    </div>
</template>

@unless($nested)
<template x-if="{{ $f }}.type === 'rich_text'">
    <div x-data="richField({{ $f }})" class="overflow-hidden rounded-md border border-zinc-300 bg-white focus-within:border-indigo-500 focus-within:ring-2 focus-within:ring-indigo-500/20">
        <div class="flex flex-wrap gap-0.5 border-b border-zinc-200 bg-zinc-50 p-1">
            <template x-for="tool in [['bold', 'B', 'font-bold'], ['italic', 'I', 'italic'], ['h2', 'H2', ''], ['h3', 'H3', ''], ['bulletList', '•', ''], ['orderedList', '1.', ''], ['blockquote', '❝', '']]" :key="tool[0]">
                <button type="button" class="rounded px-2 py-0.5 text-xs text-zinc-700 hover:bg-zinc-200" :class="[tool[2], active[tool[0]] ? 'bg-zinc-200 text-zinc-900' : '']"
                        @click="run(tool[0])" x-text="tool[1]"></button>
            </template>
            <button type="button" class="rounded px-2 py-0.5 text-xs text-zinc-700 underline hover:bg-zinc-200" :class="active.link ? 'bg-zinc-200' : ''" @click="link()" x-text="t.link"></button>
        </div>
        <div x-ref="area" class="tr-prose"></div>
        <p x-show="placeholder({{ $f }}{{ $c }}) && !value({{ $f }}{{ $c }})" class="border-t border-zinc-100 px-2.5 py-1.5 text-xs text-zinc-400" x-text="placeholder({{ $f }}{{ $c }})"></p>
    </div>
</template>
@endunless

@unless($nested)
<template x-if="field.type === 'repeater'">
    <div class="space-y-2">
        <p x-show="!items(field).length" class="rounded-md border border-dashed border-zinc-300 px-3 py-4 text-center text-xs text-zinc-400" x-text="t.no_items"></p>

        <template x-for="(item, index) in items(field)" :key="item.id">
            <div class="rounded-md border border-zinc-200 bg-zinc-50/60">
                <div class="flex items-center gap-1 border-b border-zinc-200 py-1 ps-2.5 pe-1">
                    <span class="text-xs font-semibold text-zinc-500 tabular-nums" x-text="index + 1"></span>
                    <span class="min-w-0 flex-1 truncate ps-1 text-xs text-zinc-600" x-text="itemPreview(field, item)"></span>
                    <button type="button" class="tr-icon-btn size-6" :disabled="index === 0" @click="moveItem(field, item, -1)" :title="t.move_up">↑</button>
                    <button type="button" class="tr-icon-btn size-6" :disabled="index === items(field).length - 1" @click="moveItem(field, item, 1)" :title="t.move_down">↓</button>
                    <button type="button" class="tr-icon-btn size-6" @click="removeItem(field, item)" :title="t.delete">✕</button>
                </div>
                <div class="space-y-3 p-2.5">
                    <template x-for="sub in field.fields" :key="item.id + ':' + sub.name">
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between gap-2">
                                <label class="tr-label" x-text="sub.label + (sub.required ? ' *' : '')"></label>
                                <span x-show="sub.translatable" class="rounded bg-zinc-100 px-1.5 py-0.5 text-[10px] font-semibold text-zinc-500 uppercase" x-text="locale"></span>
                            </div>

                            @include('trame::editor.partials.field-control', ['f' => 'sub', 'c' => ', item', 'nested' => true])

                            <template x-for="message in itemFieldErrors(field, index, sub, item)" :key="message">
                                <p class="text-xs text-rose-600" x-text="message"></p>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </template>

        <button type="button" class="tr-btn-outline w-full" :disabled="!canAddItem(field)" @click="addItem(field)" x-text="t.add_item"></button>
    </div>
</template>
@endunless
