<script setup lang="ts">
import { computed } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type ChampDef = {
    code: string;
    libelle: string;
    type: 'texte' | 'liste' | 'numerique' | 'date' | 'table' | string;
    obligatoire?: boolean;
    visible?: boolean;
    gris?: boolean;
    valeur_defaut?: string | null;
    ecran_code?: string | null;
    options?: {
        max_length?: number | null;
        min?: number | string | null;
        max?: number | string | null;
        decimales?: number | null;
        valeurs?: Array<{
            code: string;
            libelle: string;
            actif?: boolean;
            ordre?: number;
        }>;
        table_code?: string;
        colonne_valeur?: string;
        colonne_libelle?: string;
        colonnes_desactivees?: string[];
    };
    valeurs?: Array<{ code: string; libelle: string }>;
};

const props = withDefaults(
    defineProps<{
        champ: ChampDef;
        modelValue: string | number | null;
        inputClass?: string;
        error?: string | null;
    }>(),
    {
        inputClass: 'h-10 w-full rounded-md border border-input bg-background px-3 text-sm',
        error: null,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string | number | null];
}>();

const isSelect = computed(() => props.champ.type === 'liste' || props.champ.type === 'table');

const listeOptions = computed(() => {
    if (props.champ.valeurs?.length) return props.champ.valeurs;
    return (props.champ.options?.valeurs ?? []).filter((v) => v.actif !== false);
});

const step = computed(() => {
    const d = props.champ.options?.decimales;
    if (d === null || d === undefined) return '0.01';
    if (d === 0) return '1';
    return `0.${'0'.repeat(Math.max(0, d - 1))}1`;
});

const onInput = (e: Event) => {
    const target = e.target as HTMLInputElement | HTMLSelectElement;
    emit('update:modelValue', target.value === '' ? null : target.value);
};
</script>

<template>
    <div v-if="champ.visible !== false" class="space-y-1.5">
        <Label>
            {{ champ.libelle }}
            <span v-if="champ.obligatoire" class="text-destructive">*</span>
            <span
                v-if="champ.type === 'table' && champ.options?.table_code"
                class="ml-1 font-mono text-[10px] text-muted-foreground"
            >
                ← {{ champ.options.table_code }}
            </span>
        </Label>

        <select
            v-if="isSelect"
            :value="modelValue ?? ''"
            :disabled="champ.gris"
            :class="inputClass"
            :required="champ.obligatoire"
            @change="onInput"
        >
            <option value="">— Sélectionner —</option>
            <option v-for="v in listeOptions" :key="v.code" :value="v.code">
                {{ v.libelle }}
            </option>
        </select>

        <Input
            v-else-if="champ.type === 'numerique'"
            :model-value="modelValue ?? ''"
            type="number"
            :step="step"
            :min="champ.options?.min ?? undefined"
            :max="champ.options?.max ?? undefined"
            :disabled="champ.gris"
            :required="champ.obligatoire"
            :class="inputClass"
            @update:model-value="(v) => emit('update:modelValue', v === '' ? null : v)"
        />

        <Input
            v-else-if="champ.type === 'date'"
            :model-value="modelValue ?? ''"
            type="date"
            :disabled="champ.gris"
            :required="champ.obligatoire"
            :class="inputClass"
            @update:model-value="(v) => emit('update:modelValue', v === '' ? null : v)"
        />

        <Input
            v-else
            :model-value="modelValue ?? ''"
            type="text"
            :maxlength="champ.options?.max_length ?? undefined"
            :disabled="champ.gris"
            :required="champ.obligatoire"
            :class="inputClass"
            @update:model-value="(v) => emit('update:modelValue', v === '' ? null : v)"
        />

        <p v-if="error" class="text-xs text-destructive">{{ error }}</p>
        <p
            v-else-if="champ.type === 'table' && !listeOptions.length"
            class="text-xs text-muted-foreground"
        >
            Aucune valeur — vérifiez la table source et les colonnes.
        </p>
    </div>
</template>
