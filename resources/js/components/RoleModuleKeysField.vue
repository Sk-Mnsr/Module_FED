<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';
import { watch } from 'vue';

interface ModuleOption {
    key: string;
    label: string;
}

const props = defineProps<{
    modules: ModuleOption[];
    modelValue: string[];
    primaryModule: string;
    error?: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string[]];
}>();

const toggleModule = (key: string, checked: boolean) => {
    const next = new Set(props.modelValue);
    if (checked) {
        next.add(key);
    } else if (key !== props.primaryModule) {
        next.delete(key);
    }
    emit('update:modelValue', Array.from(next).sort());
};

const isChecked = (key: string) => props.modelValue.includes(key);

watch(
    () => props.primaryModule,
    (moduleKey) => {
        if (!moduleKey) {
            return;
        }
        if (!props.modelValue.includes(moduleKey)) {
            emit('update:modelValue', [...props.modelValue, moduleKey].sort());
        }
    },
);
</script>

<template>
    <div class="col-span-2 space-y-2">
        <Label>Modules accessibles *</Label>
        <p class="text-xs text-gray-500">
            Détermine les menus et routes ouverts pour ce rôle. Le module principal est toujours inclus.
        </p>
        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
            <label
                v-for="module in modules"
                :key="module.key"
                class="flex cursor-pointer items-center gap-2 rounded-md border border-gray-200 px-3 py-2 text-sm"
                :class="module.key === primaryModule ? 'bg-purple-50 border-purple-200' : 'bg-white'"
            >
                <input
                    type="checkbox"
                    :checked="isChecked(module.key)"
                    :disabled="module.key === primaryModule"
                    class="rounded border-gray-300"
                    @change="toggleModule(module.key, ($event.target as HTMLInputElement).checked)"
                />
                <span>
                    {{ module.label }}
                    <span v-if="module.key === primaryModule" class="text-xs text-purple-700">(principal)</span>
                </span>
            </label>
        </div>
        <InputError :message="error" />
    </div>
</template>
