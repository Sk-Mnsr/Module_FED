<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAppearance } from '@/composables/useAppearance';
import { 
    Plus, Minus, AlertTriangle, ArrowUpRight, ArrowDownRight, History, 
    ChevronDown, Package, Layers, FolderTree, Search, RotateCcw, 
    Filter, CheckCircle, XCircle, TrendingUp, Eye, Moon, Sun, LibraryBig,
} from 'lucide-vue-next';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';

const { updateAppearance } = useAppearance();

function togglePageTheme() {
    if (typeof document === 'undefined') return;
    if (document.documentElement.classList.contains('dark')) {
        updateAppearance('light');
    } else {
        updateAppearance('dark');
    }
}


interface Article {
    id: number;
    code: string;
    description: string;
    stock_actuel: number;
    seuil_alerte: number;
    sous_categorie_id: number;
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
            };
        };
    };
    latest_movement?: {
        motif: string;
    };
    type_depense?: { nom_depense: string };
}

const props = defineProps<{
    articles: Article[];
}>();

const breadcrumbs = [
    { title: 'Gestion de Stock', href: '/stock' },
];

const activeFamilyId = ref<number | null>(null);
const activeCategoryId = ref<number | null>(null);
const activeSousCategoryId = ref<number | null>(null);

const families = computed(() => {
    const uniqueFamilies: any[] = [];
    props.articles.forEach(article => {
        const f = article.sous_categorie?.categorie?.famille;
        if (f && !uniqueFamilies.find(uf => uf.id === f.id)) {
            uniqueFamilies.push(f);
        }
    });
    return uniqueFamilies;
});

watch(families, (newFamilies) => {
    // Default to first family if not set
    if (activeFamilyId.value === null && newFamilies.length > 0) {
        activeFamilyId.value = newFamilies[0].id;
    }
}, { immediate: true });

const activeFamilyData = computed(() => {
    return hierarchicalData.value.find(f => f.id === activeFamilyId.value) || null;
});

const activeCategoryData = computed(() => {
    if (!activeFamilyData.value) return null;
    return activeFamilyData.value.categories.find((c: any) => c.id === activeCategoryId.value) || null;
});

const activeSousCategoryData = computed(() => {
    if (!activeCategoryData.value) return null;
    return activeCategoryData.value.sousCategories.find((sc: any) => sc.id === activeSousCategoryId.value) || null;
});



const searchQuery = ref('');
const statusFilter = ref('tous');

const filteredArticles = computed(() => {
    return props.articles.filter(article => {
        const matchesSearch = article.description.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
                            article.code.toLowerCase().includes(searchQuery.value.toLowerCase());
        
        let matchesStatus = true;
        if (statusFilter.value === 'sains') {
            matchesStatus = article.stock_actuel > article.seuil_alerte;
        } else if (statusFilter.value === 'alerte') {
            matchesStatus = article.stock_actuel <= article.seuil_alerte && article.stock_actuel > 0;
        } else if (statusFilter.value === 'rupture') {
            matchesStatus = article.stock_actuel <= 0;
        }

        return matchesSearch && matchesStatus;
    });
});

const hierarchicalData = computed(() => {
    const familles: any[] = [];

    filteredArticles.value.forEach(article => {
        const famille = article.sous_categorie?.categorie?.famille;
        const categorie = article.sous_categorie?.categorie;
        const sousCategorie = article.sous_categorie;

        if (!famille || !categorie || !sousCategorie) return;

        let f = familles.find(f => f.id === famille.id);
        if (!f) {
            f = { 
                ...famille, 
                categories: [], 
                stats: {
                    totalQty: 0,
                    sains: 0,
                    alerte: 0,
                    rupture: 0,
                    totalRefs: 0
                }
            };
            familles.push(f);
        }

        let c = f.categories.find((cat: any) => cat.id === categorie.id);
        if (!c) {
            c = { ...categorie, sousCategories: [] };
            f.categories.push(c);
        }

        let sc = c.sousCategories.find((scat: any) => scat.id === sousCategorie.id);
        if (!sc) {
            sc = { ...sousCategorie, articles: [] };
            c.sousCategories.push(sc);
        }

        sc.articles.push(article);
        
        // Update family stats
        f.stats.totalQty += article.stock_actuel;
        f.stats.totalRefs++;
        if (article.stock_actuel <= 0) {
            f.stats.rupture++;
        } else if (article.stock_actuel <= article.seuil_alerte) {
            f.stats.alerte++;
        } else {
            f.stats.sains++;
        }
    });

    return familles;
});

watch(activeFamilyId, (newId) => {
    if (newId) {
        const family = hierarchicalData.value.find(f => f.id === newId);
        if (family && family.categories.length > 0) {
            activeCategoryId.value = family.categories[0].id;
        } else {
            activeCategoryId.value = null;
        }
    } else {
        activeCategoryId.value = null;
    }
}, { immediate: true });

watch(activeCategoryId, (newId) => {
    if (newId) {
        const family = hierarchicalData.value.find(f => f.id === activeFamilyId.value);
        if (family) {
            const category = family.categories.find((c: any) => c.id === newId);
            if (category && category.sousCategories.length > 0) {
                activeSousCategoryId.value = category.sousCategories[0].id;
            } else {
                activeSousCategoryId.value = null;
            }
        } else {
            activeSousCategoryId.value = null;
        }
    } else {
        activeSousCategoryId.value = null;
    }
}, { immediate: true });

const globalStats = computed(() => {
    const stats = {
        totalArticles: props.articles.length,
        totalQty: 0,
        alerte: 0,
        rupture: 0,
        familles: new Set<number>()
    };

    props.articles.forEach(article => {
        stats.totalQty += article.stock_actuel;
        if (article.stock_actuel <= 0) {
            stats.rupture++;
        } else if (article.stock_actuel <= article.seuil_alerte) {
            stats.alerte++;
        }
        
        const familleId = article.sous_categorie?.categorie?.famille?.id;
        if (familleId) stats.familles.add(familleId);
    });

    return {
        ...stats,
        famillesCount: stats.familles.size
    };
});

const resetFilters = () => {
    searchQuery.value = '';
    statusFilter.value = 'tous';
};

const hasActiveFilters = computed(
    () => searchQuery.value.trim() !== '' || statusFilter.value !== 'tous',
);

const showModal = ref(false);
const showViewModal = ref(false);
const selectedArticle = ref<Article | null>(null);
const selectedArticleView = ref<Article | null>(null);

const movementForm = ref({
    type: 'entree' as 'entree' | 'sortie' | 'correction',
    famille_id: null as number | null,
    categorie_id: null as number | null,
    sous_categorie_id: null as number | null,
    article_id: null as number | null,
    quantite: 1,
    motif: '',
    destinataire: '',
});

// Cascade selection helpers
const modalCategories = computed(() => {
    if (!movementForm.value.famille_id) return [];
    const cats: any[] = [];
    props.articles.forEach(a => {
        const c = a.sous_categorie?.categorie;
        if (c?.famille_id === movementForm.value.famille_id && !cats.find(uc => uc.id === c.id)) {
            cats.push(c);
        }
    });
    return cats;
});

const modalSousCategories = computed(() => {
    if (!movementForm.value.categorie_id) return [];
    const scats: any[] = [];
    props.articles.forEach(a => {
        const sc = a.sous_categorie;
        if (sc?.categorie_id === movementForm.value.categorie_id && !scats.find(usc => usc.id === sc.id)) {
            scats.push(sc);
        }
    });
    return scats;
});

const modalArticles = computed(() => {
    if (!movementForm.value.sous_categorie_id) return [];
    return props.articles.filter(a => a.sous_categorie_id === movementForm.value.sous_categorie_id);
});

// Watchers for cascade clearing
watch(() => movementForm.value.famille_id, () => {
    movementForm.value.categorie_id = null;
    movementForm.value.sous_categorie_id = null;
    movementForm.value.article_id = null;
});
watch(() => movementForm.value.categorie_id, () => {
    movementForm.value.sous_categorie_id = null;
    movementForm.value.article_id = null;
});
watch(() => movementForm.value.sous_categorie_id, () => {
    movementForm.value.article_id = null;
});
watch(() => movementForm.value.article_id, (newId: number | null) => {
    if (newId) {
        selectedArticle.value = props.articles.find(a => a.id === newId) || null;
    } else if (!isModalLocked.value) {
        selectedArticle.value = null;
    }
});

const isModalLocked = ref(false);

const openMovementModal = (article: Article | null, type: 'entree' | 'sortie' | 'correction') => {
    selectedArticle.value = article;
    isModalLocked.value = !!article;
    
    movementForm.value = {
        type: type,
        famille_id: article?.sous_categorie?.categorie?.famille_id || null,
        categorie_id: article?.sous_categorie?.categorie?.id || null,
        sous_categorie_id: article?.sous_categorie?.id || null,
        article_id: article?.id || null,
        quantite: 1,
        motif: '',
        destinataire: '',
    };
    showModal.value = true;
};

const submitMovement = () => {
    if (!movementForm.value.article_id) return;

    router.post('/stock/movements', movementForm.value, {
        onSuccess: () => {
            showModal.value = false;
        }
    });
};

const getStockStatusClass = (article: Article) => {
    if (article.stock_actuel <= 0) {
        return 'bg-red-100 text-red-800 dark:bg-red-950/80 dark:text-red-200';
    }
    if (article.stock_actuel <= article.seuil_alerte) {
        return 'bg-amber-100 text-amber-900 dark:bg-amber-950/80 dark:text-amber-200';
    }
    return 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950/70 dark:text-emerald-200';
};

const getStockStatusLabel = (article: Article) => {
    if (article.stock_actuel <= 0) return 'Rupture';
    if (article.stock_actuel <= article.seuil_alerte) return 'Alerte';
    return 'Optimal';
};

const openViewModal = (article: Article) => {
    selectedArticleView.value = article;
    showViewModal.value = true;
};
</script>

<template>
    <Head title="Gestion de Stock" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex min-h-0 w-full max-w-none flex-1 flex-col gap-5 px-4 pb-6 pt-3 sm:px-6 lg:px-8">
            <header class="flex shrink-0 flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0 space-y-1">
                    <p class="text-xs font-semibold uppercase tracking-wider text-purple-600 dark:text-purple-400">Inventaire</p>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-neutral-50 sm:text-3xl">Gestion des stocks</h1>
                    <p class="max-w-3xl text-sm text-gray-500 dark:text-neutral-400 lg:max-w-none">Suivi et mouvements par famille, avec filtres rapides sur les niveaux de stock.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        class="shrink-0 border-gray-200 bg-white text-gray-700 hover:bg-gray-50 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-200 dark:hover:bg-neutral-800 relative"
                        title="Basculer le thème clair / sombre"
                        @click="togglePageTheme"
                    >
                        <Sun class="h-4 w-4 scale-100 rotate-0 transition-all dark:scale-0 dark:-rotate-90" />
                        <Moon class="absolute h-4 w-4 scale-0 rotate-90 transition-all dark:scale-100 dark:rotate-0" />
                    </Button>
                    <Button
                        variant="default"
                        class="bg-emerald-600 hover:bg-emerald-700 flex items-center gap-2 shadow-md shadow-emerald-600/20 dark:shadow-emerald-900/40"
                        @click="openMovementModal(null, 'entree')"
                    >
                        <Plus class="h-4 w-4 shrink-0" />
                        <span class="hidden sm:inline">Nouvelle entrée</span>
                        <span class="sm:hidden">Entrée</span>
                    </Button>
                    <Link href="/stock/movements">
                        <Button variant="outline" class="flex items-center gap-2 border-gray-200 bg-white hover:bg-gray-50 dark:border-neutral-700 dark:bg-neutral-900 dark:hover:bg-neutral-800 dark:text-neutral-100">
                            <History class="h-4 w-4 shrink-0" />
                            Historique
                        </Button>
                    </Link>
                </div>
            </header>

            <!-- Familles : bandeau compact, scroll horizontal sur mobile -->
            <div class="flex shrink-0 flex-col gap-1">
                <div
                    class="-mx-1 flex gap-1 overflow-x-auto px-1 pb-2 scrollbar-none border-b border-gray-200 dark:border-neutral-800"
                    role="tablist"
                    aria-label="Familles"
                >
                    <button
                        v-for="famille in families"
                        :key="famille.id"
                        type="button"
                        role="tab"
                        :aria-selected="activeFamilyId === famille.id ? 'true' : 'false'"
                        @click="activeFamilyId = famille.id"
                        :class="[
                            'shrink-0 rounded-full px-4 py-1.5 text-xs font-semibold transition-all sm:text-sm',
                            activeFamilyId === famille.id
                                ? 'bg-purple-600 text-white shadow-sm dark:bg-purple-500'
                                : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-700',
                        ]"
                    >
                        {{ famille.nom }}
                    </button>
                </div>
            </div>

            <!-- KPI : pleine largeur, plus confortable sur grands écrans -->
            <div class="grid shrink-0 grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5 xl:gap-4">
                <div
                    class="relative flex min-h-[4.75rem] items-center gap-3 overflow-hidden rounded-xl bg-gradient-to-br from-indigo-600 to-indigo-700 px-4 py-3 text-white shadow-md sm:min-h-[5rem] sm:flex-col sm:items-stretch sm:gap-0 sm:py-3"
                >
                    <div class="relative z-10 min-w-0 flex-1 sm:flex-none">
                        <p class="text-[10px] font-bold uppercase tracking-wider opacity-80">Total articles</p>
                        <p class="text-xl font-bold tabular-nums leading-tight sm:mt-0.5 sm:text-2xl xl:text-3xl">{{ globalStats.totalArticles }}</p>
                        <p class="mt-0.5 hidden text-[11px] opacity-75 sm:block">Références uniques</p>
                    </div>
                    <Layers class="relative z-10 h-8 w-8 shrink-0 opacity-40 sm:absolute sm:right-2 sm:top-1/2 sm:h-12 sm:w-12 sm:-translate-y-1/2 sm:opacity-20" />
                </div>

                <div
                    class="relative flex min-h-[4.75rem] items-center gap-3 overflow-hidden rounded-xl bg-gradient-to-br from-emerald-600 to-emerald-700 px-4 py-3 text-white shadow-md sm:min-h-[5rem] sm:flex-col sm:items-stretch sm:gap-0 sm:py-3"
                >
                    <div class="relative z-10 min-w-0 flex-1 sm:flex-none">
                        <p class="text-[10px] font-bold uppercase tracking-wider opacity-80">Stock physique</p>
                        <p class="text-xl font-bold tabular-nums leading-tight sm:mt-0.5 sm:text-2xl xl:text-3xl">{{ globalStats.totalQty }}</p>
                        <p class="mt-0.5 hidden text-[11px] opacity-75 sm:block">Unités totales</p>
                    </div>
                    <Package class="relative z-10 h-8 w-8 shrink-0 opacity-40 sm:absolute sm:right-2 sm:top-1/2 sm:h-12 sm:w-12 sm:-translate-y-1/2 sm:opacity-20" />
                </div>

                <div
                    class="relative flex min-h-[4.75rem] items-center gap-3 overflow-hidden rounded-xl bg-gradient-to-br from-amber-500 to-orange-600 px-4 py-3 text-white shadow-md sm:min-h-[5rem] sm:flex-col sm:items-stretch sm:gap-0 sm:py-3"
                >
                    <div class="relative z-10 min-w-0 flex-1 sm:flex-none">
                        <p class="text-[10px] font-bold uppercase tracking-wider opacity-80">En alerte</p>
                        <p class="text-xl font-bold tabular-nums leading-tight sm:mt-0.5 sm:text-2xl xl:text-3xl">{{ globalStats.alerte }}</p>
                        <p class="mt-0.5 hidden text-[11px] opacity-75 sm:block">Sous le seuil</p>
                    </div>
                    <AlertTriangle class="relative z-10 h-8 w-8 shrink-0 opacity-40 sm:absolute sm:right-2 sm:top-1/2 sm:h-12 sm:w-12 sm:-translate-y-1/2 sm:opacity-20" />
                </div>

                <div
                    class="relative flex min-h-[4.75rem] items-center gap-3 overflow-hidden rounded-xl bg-gradient-to-br from-rose-600 to-red-700 px-4 py-3 text-white shadow-md sm:min-h-[5rem] sm:flex-col sm:items-stretch sm:gap-0 sm:py-3"
                >
                    <div class="relative z-10 min-w-0 flex-1 sm:flex-none">
                        <p class="text-[10px] font-bold uppercase tracking-wider opacity-80">En rupture</p>
                        <p class="text-xl font-bold tabular-nums leading-tight sm:mt-0.5 sm:text-2xl xl:text-3xl">{{ globalStats.rupture }}</p>
                        <p class="mt-0.5 hidden text-[11px] opacity-75 sm:block">Stock épuisé</p>
                    </div>
                    <Minus class="relative z-10 h-8 w-8 shrink-0 opacity-40 sm:absolute sm:right-2 sm:top-1/2 sm:h-12 sm:w-12 sm:-translate-y-1/2 sm:opacity-20" />
                </div>

                <div
                    class="relative col-span-2 flex min-h-[4.75rem] items-center gap-3 overflow-hidden rounded-xl bg-gradient-to-br from-slate-700 to-slate-900 px-4 py-3 text-white shadow-md sm:col-span-1 sm:min-h-[5rem] sm:flex-col sm:items-stretch sm:gap-0 sm:py-3"
                >
                    <div class="relative z-10 min-w-0 flex-1 sm:flex-none">
                        <p class="text-[10px] font-bold uppercase tracking-wider opacity-80">Familles</p>
                        <p class="text-xl font-bold tabular-nums leading-tight sm:mt-0.5 sm:text-2xl xl:text-3xl">{{ globalStats.famillesCount }}</p>
                        <p class="mt-0.5 hidden text-[11px] opacity-75 sm:block">Groupes logiques</p>
                    </div>
                    <FolderTree class="relative z-10 h-8 w-8 shrink-0 opacity-40 sm:absolute sm:right-2 sm:top-1/2 sm:h-12 sm:w-12 sm:-translate-y-1/2 sm:opacity-20" />
                </div>
            </div>

            <!-- Filtres -->
            <div class="shrink-0 space-y-3 rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-neutral-800 dark:bg-neutral-900 sm:p-4">
                <div class="flex flex-col gap-3 md:flex-row md:items-center">
                    <div class="relative min-w-0 flex-1">
                        <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400 dark:text-neutral-500" />
                        <Input
                            v-model="searchQuery"
                            placeholder="Rechercher par nom, code…"
                            class="h-10 rounded-lg border-gray-200 pl-10 shadow-sm focus-visible:ring-purple-500 dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-100 dark:placeholder:text-neutral-500"
                        />
                    </div>
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <div class="relative min-w-[12rem] flex-1 sm:flex-initial sm:w-52">
                            <Filter class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400 dark:text-neutral-500" />
                            <select
                                v-model="statusFilter"
                                class="h-10 w-full rounded-lg border border-gray-200 bg-white py-2 pl-10 pr-9 text-sm shadow-sm appearance-none focus:outline-none focus:ring-2 focus:ring-purple-500 dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-100"
                            >
                                <option value="tous">Tous les statuts</option>
                                <option value="sains">État optimal</option>
                                <option value="alerte">En alerte</option>
                                <option value="rupture">En rupture</option>
                            </select>
                            <ChevronDown class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400 dark:text-neutral-500" />
                        </div>
                        <Button
                            type="button"
                            variant="secondary"
                            class="h-10 shrink-0 gap-2 rounded-lg border border-gray-200 bg-gray-50 text-gray-700 hover:bg-gray-100 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200 dark:hover:bg-neutral-700"
                            @click="resetFilters"
                        >
                            <RotateCcw class="h-4 w-4" />
                            Réinitialiser
                        </Button>
                    </div>
                </div>

                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3">
                    <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-neutral-400">Filtres rapides</span>
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            type="button"
                            @click="statusFilter = 'tous'"
                            :class="[
                                'inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-semibold transition-all border',
                                statusFilter === 'tous'
                                    ? 'border-purple-300 bg-purple-100 text-purple-800 dark:border-purple-700 dark:bg-purple-950/60 dark:text-purple-200'
                                    : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300 dark:hover:bg-neutral-800',
                            ]"
                        >
                            Tous
                        </button>
                        <button
                            type="button"
                            @click="statusFilter = 'sains'"
                            :class="[
                                'inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-semibold transition-all border',
                                statusFilter === 'sains'
                                    ? 'border-emerald-300 bg-emerald-100 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-200'
                                    : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300 dark:hover:bg-neutral-800',
                            ]"
                        >
                            <CheckCircle class="h-3.5 w-3.5" />
                            Optimal
                        </button>
                        <button
                            type="button"
                            @click="statusFilter = 'alerte'"
                            :class="[
                                'inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-semibold transition-all border',
                                statusFilter === 'alerte'
                                    ? 'border-amber-300 bg-amber-100 text-amber-900 dark:border-amber-700 dark:bg-amber-950/60 dark:text-amber-200'
                                    : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300 dark:hover:bg-neutral-800',
                            ]"
                        >
                            <AlertTriangle class="h-3.5 w-3.5" />
                            Alerte
                        </button>
                        <button
                            type="button"
                            @click="statusFilter = 'rupture'"
                            :class="[
                                'inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-semibold transition-all border',
                                statusFilter === 'rupture'
                                    ? 'border-rose-300 bg-rose-100 text-rose-800 dark:border-rose-800 dark:bg-rose-950/60 dark:text-rose-200'
                                    : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300 dark:hover:bg-neutral-800',
                            ]"
                        >
                            <XCircle class="h-3.5 w-3.5" />
                            Rupture
                        </button>
                    </div>
                </div>
            </div>

            <div class="flex min-h-0 flex-1 flex-col gap-4">
                <div
                    v-if="hierarchicalData.length === 0"
                    class="flex min-h-[min(24rem,calc(100dvh-22rem))] flex-1 flex-col justify-center rounded-2xl border border-dashed border-gray-300 bg-gradient-to-b from-white to-gray-50/80 px-6 py-12 text-center dark:border-neutral-700 dark:from-neutral-900 dark:to-neutral-950/80 sm:py-16"
                >
                    <h3 class="text-base font-semibold text-gray-900 dark:text-neutral-100">Aucun article trouvé</h3>
                    <p class="mx-auto mt-2 max-w-md text-sm text-gray-500 dark:text-neutral-400">
                        <template v-if="hasActiveFilters">
                            Aucun résultat pour ces critères. Essayez d’élargir la recherche ou réinitialisez les filtres.
                        </template>
                        <template v-else>
                            Commencez par créer des articles et les rattacher à une famille dans la configuration.
                        </template>
                    </p>
                    <div class="mt-6 flex flex-col items-center justify-center gap-2 sm:flex-row">
                        <Button
                            v-if="hasActiveFilters"
                            type="button"
                            variant="outline"
                            class="w-full border-gray-200 sm:w-auto dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100"
                            @click="resetFilters"
                        >
                            <RotateCcw class="mr-2 h-4 w-4" />
                            Réinitialiser les filtres
                        </Button>
                        <Link href="/articles" class="w-full sm:w-auto">
                            <Button
                                type="button"
                                class="w-full gap-2 bg-purple-600 hover:bg-purple-700 dark:bg-purple-600 dark:hover:bg-purple-500 sm:w-auto"
                            >
                                <LibraryBig class="h-4 w-4" />
                                Ouvrir les articles (configuration)
                            </Button>
                        </Link>
                    </div>
                </div>

                <div
                    v-else
                    v-for="famille in hierarchicalData.filter(f => f.id === activeFamilyId)"
                    :key="famille.id"
                    class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-900"
                >
                    <div class="flex min-h-0 flex-1 flex-col p-3 sm:p-4">
                        <!-- Dashboard de la Famille -->
                        <!-- <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                                    <div class="bg-blue-600 rounded-xl p-4 text-white shadow-lg relative overflow-hidden group hover:scale-[1.02] transition-transform">
                                        <div class="relative z-10">
                                            <p class="text-xs font-medium opacity-80 uppercase tracking-wider">Quantité en Stock</p>
                                            <p class="text-3xl font-bold mt-1">{{ famille.stats.totalQty }}</p>
                                            <p class="text-[10px] mt-2 opacity-70 italic">Unités physiques</p>
                                        </div>
                                        <Package class="absolute -right-2 -bottom-2 h-16 w-16 opacity-20 transform rotate-12 group-hover:scale-110 transition-transform" />
                                    </div>

                                    <div class="bg-emerald-500 rounded-xl p-4 text-white shadow-lg relative overflow-hidden group hover:scale-[1.02] transition-transform">
                                        <div class="relative z-10">
                                            <p class="text-xs font-medium opacity-80 uppercase tracking-wider">État Optimal</p>
                                            <p class="text-3xl font-bold mt-1">{{ famille.stats.sains }}</p>
                                            <p class="text-[10px] mt-2 opacity-70 italic">Articles bien approvisionnés</p>
                                        </div>
                                        <Package class="absolute -right-2 -bottom-2 h-16 w-16 opacity-20 transform -rotate-12 group-hover:scale-110 transition-transform" />
                                    </div>

                                    <div class="bg-amber-500 rounded-xl p-4 text-white shadow-lg relative overflow-hidden group hover:scale-[1.02] transition-transform">
                                        <div class="relative z-10">
                                            <p class="text-xs font-medium opacity-80 uppercase tracking-wider">En Alerte</p>
                                            <p class="text-3xl font-bold mt-1">{{ famille.stats.alerte }}</p>
                                            <p class="text-[10px] mt-2 opacity-70 italic">Sous le seuil d'alerte</p>
                                        </div>
                                        <AlertTriangle class="absolute -right-2 -bottom-2 h-16 w-16 opacity-20 transform rotate-12 group-hover:scale-110 transition-transform" />
                                    </div>

                                    <div class="bg-rose-500 rounded-xl p-4 text-white shadow-lg relative overflow-hidden group hover:scale-[1.02] transition-transform">
                                        <div class="relative z-10">
                                            <p class="text-xs font-medium opacity-80 uppercase tracking-wider">En Rupture</p>
                                            <p class="text-3xl font-bold mt-1">{{ famille.stats.rupture }}</p>
                                            <p class="text-[10px] mt-2 opacity-70 italic">Stock épuisé</p>
                                        </div>
                                        <Minus class="absolute -right-2 -bottom-2 h-16 w-16 opacity-20 transform rotate-12 group-hover:scale-110 transition-transform" />
                                    </div>

                                    <div class="bg-slate-500 rounded-xl p-4 text-white shadow-lg relative overflow-hidden group hover:scale-[1.02] transition-transform">
                                        <div class="relative z-10">
                                            <p class="text-xs font-medium opacity-80 uppercase tracking-wider">Références</p>
                                            <p class="text-3xl font-bold mt-1">{{ famille.stats.totalRefs }}</p>
                                            <p class="text-[10px] mt-2 opacity-70 italic">Articles uniques</p>
                                        </div>
                                        <Layers class="absolute -right-2 -bottom-2 h-16 w-16 opacity-20 transform -rotate-12 group-hover:scale-110 transition-transform" />
                                    </div>
                                </div> -->

                                <div v-if="activeFamilyData?.categories?.length > 0" class="flex min-h-0 flex-1 flex-col gap-3 border-t border-gray-100 pt-3 dark:border-neutral-800 sm:pt-4">

                                    <!-- Onglets des Catégories -->
                                    <div class="-mx-0.5 flex gap-1 overflow-x-auto px-0.5 pb-2 scrollbar-none border-b border-gray-100 dark:border-neutral-800">
                                        <button
                                            v-for="categorie in activeFamilyData.categories"
                                            :key="categorie.id"
                                            type="button"
                                            @click="activeCategoryId = categorie.id"
                                            :class="[
                                                'shrink-0 rounded-lg px-3 py-1.5 text-xs font-semibold transition-all sm:text-sm',
                                                activeCategoryId === categorie.id
                                                    ? 'bg-blue-600 text-white shadow-sm dark:bg-blue-500'
                                                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-700',
                                            ]"
                                        >
                                            <div class="flex items-center gap-1.5">
                                                <Layers class="h-3.5 w-3.5 shrink-0 opacity-80" />
                                                {{ categorie.nom }}
                                            </div>
                                        </button>
                                    </div>

                                    <!-- Section Sous-catégories et Table -->
                                    <div v-if="activeCategoryData" class="flex min-h-0 flex-1 flex-col gap-3">

                                        <!-- Onglets des Sous-Catégories -->
                                        <div v-if="activeCategoryData.sousCategories?.length > 0" class="-mx-0.5 flex gap-1 overflow-x-auto border-b border-gray-100 px-0.5 pb-2 dark:border-neutral-800">
                                            <button
                                                v-for="sousCategorie in activeCategoryData.sousCategories"
                                                :key="sousCategorie.id"
                                                type="button"
                                                @click="activeSousCategoryId = sousCategorie.id"
                                                :class="[
                                                    'shrink-0 rounded-full border px-3 py-1 text-xs font-semibold transition-all',
                                                    activeSousCategoryId === sousCategorie.id
                                                        ? 'border-amber-400 bg-amber-100 text-amber-900 dark:border-amber-600 dark:bg-amber-950/50 dark:text-amber-100'
                                                        : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50 dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-300 dark:hover:bg-neutral-800',
                                                ]"
                                            >
                                                <div class="flex items-center gap-1.5">
                                                    <Package class="h-3 w-3 shrink-0" />
                                                    {{ sousCategorie.nom }}
                                                </div>
                                            </button>
                                        </div>

                                        <!-- Table des articles -->
                                        <div v-if="activeSousCategoryData" class="-mx-px min-h-0 flex-1 overflow-auto rounded-lg border border-gray-200 dark:border-neutral-700">
                                            <table class="min-w-full divide-y divide-gray-200 dark:divide-neutral-700 md:min-w-[640px]">
                                                <thead class="sticky top-0 z-10 bg-gray-50/95 shadow-sm backdrop-blur-sm dark:bg-neutral-950/95">
                                                    <tr>
                                                        <th scope="col" class="px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-neutral-400 sm:px-4">Code</th>
                                                        <th scope="col" class="px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-neutral-400 sm:px-4">Article</th>
                                                        <th scope="col" class="px-3 py-2.5 text-center text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-neutral-400 sm:px-4">Stock</th>
                                                        <th scope="col" class="px-3 py-2.5 text-center text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-neutral-400 sm:px-4">Seuil</th>
                                                        <th scope="col" class="px-3 py-2.5 text-center text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-neutral-400 sm:px-4">Statut</th>
                                                        <th scope="col" class="px-3 py-2.5 text-right text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-neutral-400 sm:px-4">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-100 bg-white dark:divide-neutral-800 dark:bg-neutral-900">
                                                    <tr v-for="article in activeSousCategoryData.articles" :key="article.id" class="transition-colors hover:bg-gray-50/90 dark:hover:bg-neutral-800/60">
                                                        <td class="whitespace-nowrap px-3 py-2.5 font-mono text-sm text-gray-500 dark:text-neutral-400 sm:px-4">{{ article.code }}</td>
                                                        <td class="px-3 py-2.5 text-sm font-semibold text-gray-900 dark:text-neutral-100 sm:px-4">{{ article.description }}</td>
                                                        <td class="whitespace-nowrap px-3 py-2.5 text-center align-middle sm:px-4">
                                                            <div class="flex items-center justify-center gap-1">
                                                                <span
                                                                    class="text-base font-bold tabular-nums"
                                                                    :class="article.stock_actuel <= article.seuil_alerte ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-neutral-100'"
                                                                >
                                                                    {{ article.stock_actuel }}
                                                                </span>
                                                                <AlertTriangle v-if="article.stock_actuel <= article.seuil_alerte" class="h-3.5 w-3.5 shrink-0 animate-pulse text-red-500" />
                                                            </div>
                                                        </td>
                                                        <td class="whitespace-nowrap px-3 py-2.5 text-center text-sm text-gray-500 tabular-nums dark:text-neutral-400 sm:px-4">{{ article.seuil_alerte }}</td>
                                                        <td class="whitespace-nowrap px-3 py-2.5 text-center sm:px-4">
                                                            <span :class="['inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium', getStockStatusClass(article)]">
                                                                {{ getStockStatusLabel(article) }}
                                                            </span>
                                                        </td>
                                                        <td class="whitespace-nowrap px-3 py-2.5 text-right text-sm font-medium sm:px-4">
                                                            <div class="flex items-center justify-end gap-1.5 font-normal sm:gap-2">
                                                                <Button size="sm" variant="ghost" class="h-8 w-8 p-0 text-gray-400 hover:bg-blue-50 hover:text-blue-600 dark:hover:bg-blue-950/40 dark:hover:text-blue-300" @click="openViewModal(article)">
                                                                    <Eye class="h-4 w-4" />
                                                                </Button>
                                                                <Button size="sm" variant="outline" class="h-8 px-2.5 border-emerald-200 bg-emerald-50 text-emerald-800 hover:bg-emerald-100 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200 dark:hover:bg-emerald-950/70" @click="openMovementModal(article, 'entree')">
                                                                    <Plus class="mr-1 h-3.5 w-3.5" /> Entrée
                                                                </Button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div v-else class="py-8 text-center text-sm italic text-gray-500 dark:text-neutral-400">
                                            Aucun article ou sous-catégorie disponible.
                                        </div>
                                    </div>
                                    <div v-else class="py-8 text-center text-sm italic text-gray-500 dark:text-neutral-400">
                                        Aucune catégorie disponible.
                                    </div>
                                </div>
                    </div>
                </div>
            </div>

        <!-- Modal Mouvement Amélioré -->
        <Dialog :open="showModal" @update:open="showModal = $event">
            <DialogContent class="sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 text-xl font-bold">
                        <ArrowUpRight v-if="movementForm.type === 'entree'" class="text-green-600 h-6 w-6" />
                        <ArrowDownRight v-if="movementForm.type === 'sortie'" class="text-red-600 h-6 w-6" />
                        {{ movementForm.type === 'entree' ? 'Nouvelle Entrée' : (movementForm.type === 'sortie' ? 'Nouvelle Sortie' : 'Correction de Stock') }}
                    </DialogTitle>
                    <p class="text-sm text-gray-500">
                        {{ isModalLocked ? 'Article sélectionné' : 'Sélectionnez un article pour enregistrer un mouvement' }}
                    </p>
                </DialogHeader>

                <form @submit.prevent="submitMovement" class="space-y-4 py-4">
                    <!-- Cascade Selection -->
                    <div v-if="!isModalLocked" class="grid grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <Label>Famille <span class="text-red-500">*</span></Label>
                            <select v-model="movementForm.famille_id" class="w-full h-10 px-3 rounded-md border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-purple-500">
                                <option :value="null" disabled>Choisir...</option>
                                <option v-for="f in families" :key="f.id" :value="f.id">{{ f.nom }}</option>
                            </select>
                        </div>
                        <div class="space-y-1.5">
                            <Label>Catégorie <span class="text-red-500">*</span></Label>
                            <select v-model="movementForm.categorie_id" :disabled="!movementForm.famille_id" class="w-full h-10 px-3 rounded-md border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 disabled:opacity-50">
                                <option :value="null" disabled>Choisir...</option>
                                <option v-for="c in modalCategories" :key="c.id" :value="c.id">{{ c.nom }}</option>
                            </select>
                        </div>
                        <div class="space-y-1.5">
                            <Label>Sous-Catégorie <span class="text-red-500">*</span></Label>
                            <select v-model="movementForm.sous_categorie_id" :disabled="!movementForm.categorie_id" class="w-full h-10 px-3 rounded-md border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 disabled:opacity-50">
                                <option :value="null" disabled>Choisir...</option>
                                <option v-for="sc in modalSousCategories" :key="sc.id" :value="sc.id">{{ sc.nom }}</option>
                            </select>
                        </div>
                        <div class="space-y-1.5">
                            <Label>Article <span class="text-red-500">*</span></Label>
                            <select v-model="movementForm.article_id" :disabled="!movementForm.sous_categorie_id" class="w-full h-10 px-3 rounded-md border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 disabled:opacity-50">
                                <option :value="null" disabled>Choisir...</option>
                                <option v-for="a in modalArticles" :key="a.id" :value="a.id">{{ a.description }} ({{ a.code }})</option>
                            </select>
                        </div>
                    </div>

                    <div v-else class="p-4 bg-purple-50 rounded-lg border border-purple-100 mb-2">
                        <div class="flex items-center gap-3">
                            <Package class="h-8 w-8 text-purple-600" />
                            <div>
                                <p class="text-sm font-bold text-gray-900">{{ selectedArticle?.description }}</p>
                                <p class="text-xs text-purple-600 font-medium">{{ selectedArticle?.code }} • Stock: {{ selectedArticle?.stock_actuel }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="quantite">Quantité <span class="text-red-500">*</span></Label>
                        <Input id="quantite" type="number" v-model="movementForm.quantite" min="1" required class="h-10" />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="motif">Description de l'article</Label>
                        <textarea 
                            id="motif" 
                            v-model="movementForm.motif" 
                            rows="2"
                            class="w-full p-3 rounded-md border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 font-medium"
                            placeholder="Détails supplémentaires ou description spécifique..."
                        ></textarea>
                    </div>

                    <div v-if="movementForm.type === 'sortie' && selectedArticle && movementForm.quantite > selectedArticle.stock_actuel" class="p-3 bg-red-50 text-red-700 text-xs rounded-md border border-red-100 flex items-start gap-2">
                        <AlertTriangle class="h-4 w-4 mt-0.5" />
                        <span>Attention: La quantité demandée dépasse le stock disponible ({{ selectedArticle.stock_actuel }}).</span>
                    </div>

                    <DialogFooter class="pt-2">
                        <Button type="button" variant="ghost" @click="showModal = false">Annuler</Button>
                        <Button type="submit" :disabled="!movementForm.article_id" :class="movementForm.type === 'entree' ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700'" class="px-8 shadow-lg">
                            Enregistrer le mouvement
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Modal Détails Article -->
        <Dialog :open="showViewModal" @update:open="showViewModal = $event">
            <DialogContent class="sm:max-w-[500px] p-0 overflow-hidden border-none shadow-2xl">
                <div class="bg-gradient-to-br from-blue-600 to-indigo-700 p-8 text-white relative">
                    <div class="relative z-10 flex items-center gap-4">
                        <div class="h-16 w-16 bg-white/20 backdrop-blur-md rounded-2xl flex items-center justify-center shadow-inner">
                            <Package class="h-8 w-8 text-white" />
                        </div>
                        <div>
                            <h2 class="text-2xl font-black tracking-tight leading-tight">{{ selectedArticleView?.description }}</h2>
                            <p class="text-blue-100 text-sm font-bold uppercase tracking-widest mt-1 opacity-80">{{ selectedArticleView?.code }}</p>
                        </div>
                    </div>
                    <Layers class="absolute -right-8 -bottom-8 h-40 w-40 text-white/5 transform -rotate-12" />
                </div>

                <div class="p-8 space-y-8 bg-white">
                    <div v-if="selectedArticleView?.latest_movement?.motif" class="p-4 bg-amber-50 rounded-xl border border-amber-100 group">
                        <p class="text-[10px] font-black text-amber-600 uppercase tracking-widest leading-none mb-2">Description / Dernier motif</p>
                        <p class="text-sm font-medium text-amber-900 leading-relaxed italic">
                            "{{ selectedArticleView?.latest_movement?.motif }}"
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-6">
                        <div class="space-y-1">
                            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Stock Actuel</p>
                            <div class="flex items-baseline gap-2">
                                <span class="text-4xl font-black text-gray-900 tracking-tighter">{{ selectedArticleView?.stock_actuel }}</span>
                                <span class="text-xs font-bold text-gray-500">unités</span>
                            </div>
                        </div>
                        <div class="space-y-1">
                            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Seuil d'Alerte</p>
                            <div class="flex items-baseline gap-2">
                                <span class="text-4xl font-black text-gray-900 tracking-tighter">{{ selectedArticleView?.seuil_alerte }}</span>
                                <span class="text-xs font-bold text-gray-500">unités</span>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4 pt-6 border-t border-gray-100">
                        <div class="flex items-center justify-between group">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 flex items-center justify-center rounded-xl bg-gray-50 text-gray-400">
                                    <FolderTree class="h-5 w-5" />
                                </div>
                                <div>
                                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest leading-none mb-1">Hiérarchie</p>
                                    <p class="text-sm font-bold text-gray-900">
                                        {{ selectedArticleView?.sous_categorie?.categorie?.famille?.nom }}
                                        <span class="text-gray-300 mx-1">/</span>
                                        {{ selectedArticleView?.sous_categorie?.nom }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div v-if="selectedArticleView?.type_depense" class="flex items-center justify-between group">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 flex items-center justify-center rounded-xl bg-gray-50 text-gray-400">
                                    <TrendingUp class="h-5 w-5" />
                                </div>
                                <div>
                                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest leading-none mb-1">Type de Dépense</p>
                                    <p class="text-sm font-bold text-gray-900">{{ selectedArticleView?.type_depense?.nom_depense }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between group">
                            <div class="flex items-center gap-3">
                                <div :class="['h-10 w-10 flex items-center justify-center rounded-xl', (selectedArticleView?.stock_actuel || 0) <= (selectedArticleView?.seuil_alerte || 0) ? 'bg-amber-50 text-amber-600' : 'bg-emerald-50 text-emerald-600']">
                                    <CheckCircle v-if="(selectedArticleView?.stock_actuel || 0) > (selectedArticleView?.seuil_alerte || 0)" class="h-5 w-5" />
                                    <AlertTriangle v-else class="h-5 w-5" />
                                </div>
                                <div>
                                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest leading-none mb-1">Statut Inventaire</p>
                                    <p :class="['text-sm font-black', (selectedArticleView?.stock_actuel || 0) <= (selectedArticleView?.seuil_alerte || 0) ? 'text-amber-600' : 'text-emerald-600']">
                                        {{ (selectedArticleView?.stock_actuel || 0) <= 0 ? 'Rupture Totale' : ((selectedArticleView?.stock_actuel || 0) <= (selectedArticleView?.seuil_alerte || 0) ? 'Action Requise (Seuil atteint)' : 'Niveau de stock optimal') }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter class="px-8 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-between gap-4">
                    <p class="text-[10px] font-bold text-gray-400 italic">Dernière mise à jour : {{ new Date().toLocaleDateString() }}</p>
                    <Button type="button" variant="default" @click="showViewModal = false" class="bg-gray-900 hover:bg-black px-8 font-bold">Fermer</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Ajout d'une taille de bouton extra-small si non définie par défaut */
.h-7 {
    height: 1.75rem;
}
.px-2 {
    padding-left: 0.5rem;
    padding-right: 0.5rem;
}
</style>
