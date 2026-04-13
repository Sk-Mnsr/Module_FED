<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { 
    Dialog, 
    DialogContent, 
    DialogHeader, 
    DialogTitle, 
    DialogFooter, 
    DialogTrigger 
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import { 
    Package, 
    ChevronRight,
    X,
    FolderTree,
    Link as LinkIcon,
    AlertTriangle
} from 'lucide-vue-next';

interface Article {
    id: number;
    description: string;
    code: string;
    stock_actuel: number;
    sous_categorie?: {
        id: number;
        nom: string;
        categorie_id: number;
        categorie?: {
            id: number;
            nom: string;
            famille_id: number;
            famille?: {
                id: number;
                nom: string;
            }
        }
    }
}

interface Props {
    articles: Article[];
    modelValue: number | string | null;
    placeholder?: string;
    designation?: string;
}

const props = withDefaults(defineProps<Props>(), {
    placeholder: 'Lier à un article...',
});

const emit = defineEmits<{
    'update:modelValue': [value: number | null];
}>();

const isOpen = ref(false);

const selection = ref({
    famille_id: null as number | null,
    categorie_id: null as number | null,
    sous_categorie_id: null as number | null,
    article_id: null as number | null,
});

// Sync selection when modal opens
watch(isOpen, (val) => {
    if (val) {
        if (props.modelValue) {
            const art = props.articles.find(a => a.id === Number(props.modelValue));
            if (art) {
                selection.value = {
                    famille_id: art.sous_categorie?.categorie?.famille_id || null,
                    categorie_id: art.sous_categorie?.categorie_id || null,
                    sous_categorie_id: art.sous_categorie?.id || null,
                    article_id: art.id,
                };
            }
        } else {
            resetSelection();
        }
    }
});

const families = computed(() => {
    const map = new Map();
    props.articles.forEach(a => {
        const f = a.sous_categorie?.categorie?.famille;
        if (f) map.set(f.id, f);
    });
    return Array.from(map.values()).sort((a, b) => a.nom.localeCompare(b.nom));
});

const categories = computed(() => {
    if (!selection.value.famille_id) return [];
    const map = new Map();
    props.articles.forEach(a => {
        const c = a.sous_categorie?.categorie;
        if (c && c.famille_id === selection.value.famille_id) {
            map.set(c.id, c);
        }
    });
    return Array.from(map.values()).sort((a, b) => a.nom.localeCompare(b.nom));
});

const sousCategories = computed(() => {
    if (!selection.value.categorie_id) return [];
    const map = new Map();
    props.articles.forEach(a => {
        const sc = a.sous_categorie;
        if (sc && sc.categorie_id === selection.value.categorie_id) {
            map.set(sc.id, sc);
        }
    });
    return Array.from(map.values()).sort((a, b) => a.nom.localeCompare(b.nom));
});

const articlesFiltered = computed(() => {
    if (!selection.value.sous_categorie_id) return [];
    return props.articles.filter(a => a.sous_categorie?.id === selection.value.sous_categorie_id)
        .sort((a, b) => a.description.localeCompare(b.description));
});

// Cascade clear
watch(() => selection.value.famille_id, () => {
    if (isOpen.value && !props.modelValue) {
        selection.value.categorie_id = null;
        selection.value.sous_categorie_id = null;
        selection.value.article_id = null;
    }
});
watch(() => selection.value.categorie_id, () => {
    if (isOpen.value && !props.modelValue) {
        selection.value.sous_categorie_id = null;
        selection.value.article_id = null;
    }
});
watch(() => selection.value.sous_categorie_id, () => {
    if (isOpen.value && !props.modelValue) {
        selection.value.article_id = null;
    }
});

const currentArticle = computed(() => {
    if (!props.modelValue) return null;
    return props.articles.find(a => a.id === Number(props.modelValue)) || null;
});

const previewArticle = computed(() => {
    if (!selection.value.article_id) return null;
    return props.articles.find(a => a.id === selection.value.article_id) || null;
});

const handleConfirm = () => {
    emit('update:modelValue', selection.value.article_id);
    isOpen.value = false;
};

const clearMapping = () => {
    emit('update:modelValue', null);
    resetSelection();
};

const resetSelection = () => {
    selection.value = {
        famille_id: null,
        categorie_id: null,
        sous_categorie_id: null,
        article_id: null,
    };
};
</script>

<template>
    <div class="space-y-2">
        <!-- Badge Display of Mapping -->
        <div v-if="currentArticle" class="flex flex-col gap-2 p-3 bg-blue-50/50 border border-blue-100 rounded-lg group relative max-w-full overflow-hidden">
            <div class="flex items-start justify-between gap-2">
                <div class="flex items-start gap-3 min-w-0">
                    <div class="p-2 bg-blue-100 text-blue-600 rounded-md shrink-0">
                        <Package class="h-4 w-4" />
                    </div>
                    <div class="flex flex-col min-w-0">
                        <span class="text-sm font-bold text-gray-900 leading-tight truncate">{{ currentArticle.description }}</span>
                        <span class="text-xs text-gray-400 font-mono mt-0.5">{{ currentArticle.code }}</span>
                        <div class="flex flex-wrap items-center gap-1 mt-1">
                            <Badge variant="outline" class="text-[9px] h-4 px-1 bg-white border-blue-200 text-blue-600 uppercase">
                                {{ currentArticle.sous_categorie?.categorie?.famille?.nom }}
                            </Badge>
                            <ChevronRight class="h-2 w-2 text-gray-300" />
                            <Badge variant="outline" class="text-[9px] h-4 px-1 bg-white border-blue-200 text-blue-600 uppercase">
                                {{ currentArticle.sous_categorie?.nom }}
                            </Badge>
                        </div>
                    </div>
                </div>
                <Button variant="ghost" size="icon" @click="clearMapping" class="h-6 w-6 text-gray-400 hover:text-red-500 shrink-0">
                    <X class="h-3 w-3" />
                </Button>
            </div>
            <Button variant="outline" size="sm" @click="isOpen = true" class="w-full text-xs h-8 bg-white hover:bg-blue-50 border-blue-200 font-bold">
                Modifier le lien
            </Button>
        </div>

        <Dialog v-model:open="isOpen">
            <DialogTrigger as-child v-if="!currentArticle">
                <Button variant="outline" size="sm" class="w-full justify-start text-xs font-bold border-dashed border-gray-300 hover:border-blue-400 hover:text-blue-600 h-10 bg-gray-50/50">
                    <LinkIcon class="h-3.5 w-3.5 mr-2 opacity-50" />
                    {{ placeholder }}
                </Button>
            </DialogTrigger>

            <DialogContent class="sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 text-xl font-bold">
                        <LinkIcon class="h-5 w-5 text-blue-600" />
                        Liaison d'article
                    </DialogTitle>
                    <p v-if="designation" class="text-sm text-gray-500">
                        Pour l'article demandé : <Badge class="bg-amber-100 text-amber-700 font-bold hover:bg-amber-100 border-none">{{ designation }}</Badge>
                    </p>
                </DialogHeader>

                <div class="space-y-4 py-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <Label>Famille <span class="text-red-500">*</span></Label>
                            <select v-model="selection.famille_id" class="w-full h-10 px-3 rounded-md border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 font-medium bg-white">
                                <option :value="null" disabled>Choisir...</option>
                                <option v-for="f in families" :key="f.id" :value="f.id">{{ f.nom }}</option>
                            </select>
                        </div>
                        <div class="space-y-1.5">
                            <Label>Catégorie <span class="text-red-500">*</span></Label>
                            <select v-model="selection.categorie_id" :disabled="!selection.famille_id" class="w-full h-10 px-3 rounded-md border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 font-medium bg-white">
                                <option :value="null" disabled>Choisir...</option>
                                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.nom }}</option>
                            </select>
                        </div>
                        <div class="space-y-1.5">
                            <Label>Sous-Catégorie <span class="text-red-500">*</span></Label>
                            <select v-model="selection.sous_categorie_id" :disabled="!selection.categorie_id" class="w-full h-10 px-3 rounded-md border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 font-medium bg-white">
                                <option :value="null" disabled>Choisir...</option>
                                <option v-for="sc in sousCategories" :key="sc.id" :value="sc.id">{{ sc.nom }}</option>
                            </select>
                        </div>
                        <div class="space-y-1.5">
                            <Label>Article final <span class="text-red-500">*</span></Label>
                            <select v-model="selection.article_id" :disabled="!selection.sous_categorie_id" class="w-full h-10 px-3 rounded-md border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 font-medium bg-white">
                                <option :value="null" disabled>Choisir...</option>
                                <option v-for="a in articlesFiltered" :key="a.id" :value="a.id">{{ a.description }} ({{ a.code }})</option>
                            </select>
                        </div>
                    </div>

                    <!-- Preview of selected article -->
                    <div v-if="previewArticle" class="p-4 bg-blue-50 rounded-xl border border-blue-100 animate-in fade-in slide-in-from-top-2">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="p-2 bg-blue-600 text-white rounded-lg">
                                    <Package class="h-5 w-5" />
                                </div>
                                <div>
                                    <p class="text-sm font-black text-gray-900">{{ previewArticle.description }}</p>
                                    <p class="text-[10px] text-blue-600 font-bold uppercase tracking-widest">{{ previewArticle.code }}</p>
                                </div>
                            </div>
                            <Badge :class="['font-black px-2 py-0.5', previewArticle.stock_actuel > 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700']">
                                {{ previewArticle.stock_actuel }} en stock
                            </Badge>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="ghost" @click="isOpen = false">Annuler</Button>
                    <Button 
                        @click="handleConfirm" 
                        :disabled="!selection.article_id"
                        class="bg-blue-600 hover:bg-blue-700 px-8 font-bold"
                    >
                        Confirmer la liaison
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
