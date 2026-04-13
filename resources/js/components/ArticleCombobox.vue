<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Search, X, ChevronDown, Package } from 'lucide-vue-next';

interface Article {
    id: number;
    description: string;
    code: string;
    stock_actuel: number;
}

interface Props {
    articles: Article[];
    modelValue: number | string | null;
    placeholder?: string;
    required?: boolean;
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    placeholder: 'Saisir ou rechercher un article...',
    required: false,
    disabled: false,
});

const emit = defineEmits<{
    'update:modelValue': [value: number | null];
}>();

const searchQuery = ref('');
const isOpen = ref(false);

const selectedArticle = computed(() => {
    if (!props.modelValue || !props.articles) return null;
    return props.articles.find(a => a.id === Number(props.modelValue)) || null;
});

const filteredArticles = computed(() => {
    if (!props.articles) return [];
    const query = searchQuery.value.toLowerCase().trim();
    if (!query) return props.articles.slice(0, 50);
    
    return props.articles.filter(a => {
        const desc = a.description?.toLowerCase() || '';
        const code = a.code?.toLowerCase() || '';
        return desc.includes(query) || code.includes(query);
    });
});

const selectArticle = (article: Article) => {
    emit('update:modelValue', article.id);
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
    if (selectedArticle.value) {
        return `${selectedArticle.value.code} - ${selectedArticle.value.description}`;
    }
    return '';
});
</script>

<template>
    <div class="relative w-full">
        <div class="relative">
            <Input
                v-model="searchQuery"
                :placeholder="selectedArticle ? displayValue : placeholder"
                type="text"
                :disabled="disabled"
                @focus="isOpen = true"
                @blur="handleBlur"
                class="h-10 w-full pr-10 text-sm font-medium border-gray-300 focus:ring-blue-500 focus:border-blue-500"
                :class="{ 'placeholder:text-gray-900': selectedArticle }"
            />
            
            <div class="absolute right-0 top-0 flex h-full items-center pr-2">
                <Button
                    v-if="selectedArticle && !disabled"
                    type="button"
                    variant="ghost"
                    size="icon"
                    @click="clearSelection"
                    class="h-7 w-7 text-gray-400 hover:text-gray-600"
                >
                    <X class="h-4 w-4" />
                </Button>
                <div v-else class="flex h-7 w-7 items-center justify-center text-gray-400">
                    <Search class="h-4 w-4" />
                </div>
            </div>

            <!-- Listbox -->
            <div
                v-if="isOpen && !disabled"
                class="absolute z-[100] mt-1 max-h-60 w-full overflow-auto rounded-md border border-gray-200 bg-white shadow-xl"
            >
                <div v-if="filteredArticles.length === 0" class="p-3 text-center text-sm text-gray-500">
                    Aucun article trouvé pour "{{ searchQuery }}"
                </div>
                <div
                    v-for="article in filteredArticles"
                    :key="article.id"
                    @mousedown="selectArticle(article)"
                    class="flex cursor-pointer flex-col px-4 py-3 hover:bg-blue-50 border-b border-gray-50 last:border-0 transition-colors"
                    :class="{ 'bg-blue-50 font-bold text-blue-700': selectedArticle?.id === article.id }"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-mono text-blue-600 bg-blue-50 px-1.5 py-0.5 rounded">{{ article.code }}</span>
                        <span :class="['text-[10px] font-bold px-1.5 py-0.5 rounded-full', article.stock_actuel > 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700']">
                            Dispo: {{ article.stock_actuel }}
                        </span>
                    </div>
                    <span class="text-sm mt-1 text-gray-900">{{ article.description }}</span>
                </div>
            </div>
        </div>
    </div>
</template>
