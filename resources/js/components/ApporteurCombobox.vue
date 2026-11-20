<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { ChevronDown, X } from 'lucide-vue-next';

type ApporteurOption = { id: number; nom: string; code: string | null };

const props = withDefaults(
    defineProps<{
        options: ApporteurOption[];
        modelValue: number | '' | null;
        id?: string;
        placeholder?: string;
        maxResults?: number;
    }>(),
    {
        id: 'apporteur',
        placeholder: 'Taper un nom ou un code…',
        maxResults: 50,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: number | ''];
}>();

const query = ref('');
const isOpen = ref(false);
const highlighted = ref(0);

// Recherche insensible à la casse et aux accents (ex. « seck » trouve « Séck »).
const normaliser = (s: string) =>
    s
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();

const libelle = (a: ApporteurOption) => (a.code ? `${a.nom} (${a.code})` : a.nom);

const selected = computed(() => props.options.find((a) => a.id === props.modelValue) ?? null);

const resultatsComplets = computed(() => {
    const q = normaliser(query.value);
    if (q === '') {
        return props.options;
    }

    return props.options.filter((a) => normaliser(`${a.nom} ${a.code ?? ''}`).includes(q));
});

const resultats = computed(() => resultatsComplets.value.slice(0, props.maxResults));

const tronque = computed(() => resultatsComplets.value.length > props.maxResults);

// Pendant la saisie on affiche la requête ; sinon le libellé de l’apporteur choisi.
const valeurAffichee = computed(() => {
    if (isOpen.value) {
        return query.value;
    }

    return selected.value ? libelle(selected.value) : '';
});

watch(query, () => {
    highlighted.value = 0;
});

watch(highlighted, async (index) => {
    await nextTick();
    document.getElementById(`${props.id}-option-${index}`)?.scrollIntoView({ block: 'nearest' });
});

function ouvrir() {
    isOpen.value = true;
    query.value = '';
    highlighted.value = 0;
}

function fermer() {
    isOpen.value = false;
    query.value = '';
}

function choisir(a: ApporteurOption) {
    emit('update:modelValue', a.id);
    fermer();
}

function effacer() {
    emit('update:modelValue', '');
    query.value = '';
    isOpen.value = false;
}

function onInput(event: Event) {
    isOpen.value = true;
    query.value = (event.target as HTMLInputElement).value;
}

function onKeydown(event: KeyboardEvent) {
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        isOpen.value = true;
        highlighted.value = Math.min(highlighted.value + 1, Math.max(resultats.value.length - 1, 0));
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        highlighted.value = Math.max(highlighted.value - 1, 0);
    } else if (event.key === 'Enter' && isOpen.value) {
        const choix = resultats.value[highlighted.value];
        if (choix) {
            event.preventDefault();
            choisir(choix);
        }
    } else if (event.key === 'Escape') {
        fermer();
    }
}
</script>

<template>
    <div class="relative">
        <Input
            :id="id"
            role="combobox"
            autocomplete="off"
            aria-autocomplete="list"
            :aria-expanded="isOpen"
            :aria-controls="`${id}-listbox`"
            :value="valeurAffichee"
            :placeholder="placeholder"
            class="h-10 border-gray-200 bg-white pr-16 text-sm"
            @focus="ouvrir"
            @blur="fermer"
            @input="onInput"
            @keydown="onKeydown"
        />

        <div class="absolute right-2 top-1/2 flex -translate-y-1/2 items-center gap-1">
            <button
                v-if="selected && !isOpen"
                type="button"
                class="rounded-full p-1 text-gray-400 hover:text-gray-600"
                title="Effacer l’apporteur"
                @mousedown.prevent
                @click="effacer"
            >
                <X class="h-4 w-4" />
            </button>
            <ChevronDown class="pointer-events-none h-4 w-4 text-gray-400" />
        </div>

        <ul
            v-if="isOpen"
            :id="`${id}-listbox`"
            role="listbox"
            class="absolute z-50 mt-1 max-h-64 w-full overflow-auto rounded-md border border-gray-200 bg-white py-1 text-sm shadow-lg"
        >
            <li v-if="resultats.length === 0" class="px-3 py-2 text-gray-500">Aucun apporteur trouvé</li>
            <li
                v-for="(a, i) in resultats"
                :id="`${id}-option-${i}`"
                :key="a.id"
                role="option"
                :aria-selected="i === highlighted"
                class="cursor-pointer px-3 py-2"
                :class="[
                    i === highlighted ? 'bg-violet-50 text-violet-900' : 'text-gray-900 hover:bg-gray-50',
                    a.id === modelValue ? 'font-medium' : '',
                ]"
                @mousedown.prevent
                @mouseenter="highlighted = i"
                @click="choisir(a)"
            >
                {{ libelle(a) }}
            </li>
            <li v-if="tronque" class="border-t border-gray-100 px-3 py-2 text-xs text-gray-500">
                {{ resultatsComplets.length }} résultats, affinez la saisie pour en voir plus.
            </li>
        </ul>
    </div>
</template>
