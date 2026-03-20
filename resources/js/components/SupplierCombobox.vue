<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Search, X, ChevronDown } from 'lucide-vue-next';

interface Supplier {
    id: number;
    nom: string;
    categorie?: string | null;
}

interface Props {
    suppliers: Supplier[];
    modelValue: number | string | null;
    placeholder?: string;
    required?: boolean;
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    placeholder: 'Rechercher un fournisseur...',
    required: false,
    disabled: false,
});

const emit = defineEmits<{
    'update:modelValue': [value: number | null];
}>();

const searchQuery = ref('');
const isOpen = ref(false);

const selectedSupplier = computed(() => {
    if (!props.modelValue) return null;
    return props.suppliers.find(s => s.id === Number(props.modelValue)) || null;
});

const filteredSuppliers = computed(() => {
    const query = searchQuery.value.toLowerCase().trim();
    if (!query) return props.suppliers.slice(0, 20);
    
    return props.suppliers.filter(s => {
        const nom = s.nom?.toLowerCase() || '';
        const cat = s.categorie?.toLowerCase() || '';
        return nom.includes(query) || cat.includes(query);
    });
});

const selectSupplier = (supplier: Supplier) => {
    emit('update:modelValue', supplier.id);
    isOpen.value = false;
    searchQuery.value = '';
};

const clearSelection = () => {
    emit('update:modelValue', null);
    searchQuery.value = '';
};

watch(() => props.modelValue, (newVal) => {
    if (!newVal) {
        searchQuery.value = '';
    }
});

const handleBlur = () => {
    setTimeout(() => {
        isOpen.value = false;
    }, 200);
};

const displayValue = computed(() => {
    if (selectedSupplier.value) {
        return `${selectedSupplier.value.nom}${selectedSupplier.value.categorie ? ` (${selectedSupplier.value.categorie})` : ''}`;
    }
    return '';
});
</script>

<template>
    <div class="relative w-full">
        <div class="relative">
            <Input
                v-model="searchQuery"
                :placeholder="selectedSupplier ? displayValue : placeholder"
                type="text"
                :disabled="disabled"
                @focus="isOpen = true"
                @blur="handleBlur"
                class="h-10 w-full pr-10 text-sm font-medium"
                :class="{ 'placeholder:text-gray-900': selectedSupplier }"
            />
            
            <div class="absolute right-0 top-0 flex h-full items-center pr-2">
                <Button
                    v-if="selectedSupplier && !disabled"
                    type="button"
                    variant="ghost"
                    size="icon"
                    @click="clearSelection"
                    class="h-7 w-7 text-gray-400 hover:text-gray-600"
                >
                    <X class="h-4 w-4" />
                </Button>
                <div v-else class="flex h-7 w-7 items-center justify-center text-gray-400">
                    <ChevronDown class="h-4 w-4" />
                </div>
            </div>

            <!-- Listbox -->
            <div
                v-if="isOpen && !disabled"
                class="absolute z-[100] mt-1 max-h-60 w-full overflow-auto rounded-md border border-gray-200 bg-white shadow-xl"
            >
                <div v-if="filteredSuppliers.length === 0" class="p-3 text-center text-sm text-gray-500">
                    Aucun fournisseur trouvé
                </div>
                <div
                    v-for="supplier in filteredSuppliers"
                    :key="supplier.id"
                    @mousedown="selectSupplier(supplier)"
                    class="flex cursor-pointer flex-col px-4 py-2 hover:bg-blue-50 transition-colors"
                    :class="{ 'bg-blue-50 font-bold text-blue-700': selectedSupplier?.id === supplier.id }"
                >
                    <span class="text-sm uppercase font-bold">{{ supplier.nom }}</span>
                    <span v-if="supplier.categorie" class="text-xs text-gray-500">{{ supplier.categorie }}</span>
                </div>
            </div>
        </div>
    </div>
</template>
