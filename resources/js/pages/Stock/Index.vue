<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { 
    Plus, Minus, AlertTriangle, ArrowUpRight, ArrowDownRight, History, 
    ChevronDown, Package, Layers, FolderTree, Search, RotateCcw, 
    Filter, CheckCircle, XCircle, TrendingUp, Eye
} from 'lucide-vue-next';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';


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
    if (article.stock_actuel <= 0) return 'bg-red-100 text-red-800';
    if (article.stock_actuel <= article.seuil_alerte) return 'bg-amber-100 text-amber-800';
    return 'bg-green-100 text-green-800';
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
        <div class="flex flex-col gap-6 p-6 max-w-7xl mx-auto">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 tracking-tight text-purple-900">Gestion des Stocks</h1>
                    <div class="flex items-center gap-2 mt-1">
                        <p class="text-sm text-gray-500 italic">Suivi et mouvements d'inventaire par Famille</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <Button variant="default" @click="openMovementModal(null, 'entree')" class="bg-emerald-600 hover:bg-emerald-700 flex items-center gap-2 shadow-lg shadow-emerald-200">
                        <Plus class="h-4 w-4" /> Nouvelle Entrée
                    </Button>
                    <Link href="/stock/movements">
                        <Button variant="outline" class="flex items-center gap-2 hover:bg-gray-50 border-gray-200">
                            <History class="h-4 w-4" /> Historique
                        </Button>
                    </Link>
                </div>
            </div>

            <!-- Onglets des Familles -->
            <div class="flex flex-col gap-2">
                <div class="flex items-center gap-1 overflow-x-auto pb-2 scrollbar-none border-b border-gray-200">
                    <button 
                        v-for="famille in families" 
                        :key="famille.id"
                        @click="activeFamilyId = famille.id"
                        :class="[
                            'px-6 py-2.5 rounded-t-lg font-bold text-sm transition-all whitespace-nowrap border-x border-t -mb-[1px]',
                            activeFamilyId === famille.id 
                                ? 'bg-white border-gray-200 text-purple-700 shadow-[0_-2px_10px_rgba(0,0,0,0.05)]' 
                                : 'bg-gray-50/50 border-transparent text-gray-500 hover:bg-gray-100 hover:text-gray-700'
                        ]"
                    >
                        {{ famille.nom }}
                    </button>
                </div>
            </div>

            <!-- Dashboard Global -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                <div class="bg-indigo-600 rounded-2xl p-5 text-white shadow-xl flex flex-col justify-between relative overflow-hidden group">
                    <div class="relative z-10">
                        <p class="text-xs font-semibold opacity-70 uppercase tracking-widest">Total Articles</p>
                        <p class="text-4xl font-black mt-2 tracking-tight">{{ globalStats.totalArticles }}</p>
                    </div>
                    <div class="mt-4 flex items-center text-xs opacity-80 font-medium relative z-10">
                        <Layers class="h-3.5 w-3.5 mr-1.5" />
                        Références uniques
                    </div>
                    <Layers class="absolute -right-4 -bottom-4 h-24 w-24 opacity-10 transform -rotate-12 group-hover:scale-110 transition-transform duration-500" />
                </div>

                <div class="bg-emerald-600 rounded-2xl p-5 text-white shadow-xl flex flex-col justify-between relative overflow-hidden group">
                    <div class="relative z-10">
                        <p class="text-xs font-semibold opacity-70 uppercase tracking-widest">Stock Physique</p>
                        <p class="text-4xl font-black mt-2 tracking-tight">{{ globalStats.totalQty }}</p>
                    </div>
                    <div class="mt-4 flex items-center text-xs opacity-80 font-medium relative z-10">
                        <TrendingUp class="h-3.5 w-3.5 mr-1.5" />
                        Unités totales
                    </div>
                    <Package class="absolute -right-4 -bottom-4 h-24 w-24 opacity-10 transform rotate-12 group-hover:scale-110 transition-transform duration-500" />
                </div>

                <div class="bg-amber-500 rounded-2xl p-5 text-white shadow-xl flex flex-col justify-between relative overflow-hidden group">
                    <div class="relative z-10">
                        <p class="text-xs font-semibold opacity-70 uppercase tracking-widest">En Alerte</p>
                        <p class="text-4xl font-black mt-2 tracking-tight">{{ globalStats.alerte }}</p>
                    </div>
                    <div class="mt-4 flex items-center text-xs opacity-80 font-medium relative z-10">
                        <AlertTriangle class="h-3.5 w-3.5 mr-1.5" />
                        Articles sous le seuil
                    </div>
                    <AlertTriangle class="absolute -right-4 -bottom-4 h-24 w-24 opacity-10 transform -rotate-12 group-hover:scale-110 transition-transform duration-500" />
                </div>

                <div class="bg-rose-600 rounded-2xl p-5 text-white shadow-xl flex flex-col justify-between relative overflow-hidden group">
                    <div class="relative z-10">
                        <p class="text-xs font-semibold opacity-70 uppercase tracking-widest">En Rupture</p>
                        <p class="text-4xl font-black mt-2 tracking-tight">{{ globalStats.rupture }}</p>
                    </div>
                    <div class="mt-4 flex items-center text-xs opacity-80 font-medium relative z-10">
                        <XCircle class="h-3.5 w-3.5 mr-1.5" />
                        Stock épuisé
                    </div>
                    <Minus class="absolute -right-4 -bottom-4 h-24 w-24 opacity-10 transform rotate-12 group-hover:scale-110 transition-transform duration-500" />
                </div>

                <div class="bg-slate-700 rounded-2xl p-5 text-white shadow-xl flex flex-col justify-between relative overflow-hidden group lg:hidden xl:flex">
                    <div class="relative z-10">
                        <p class="text-xs font-semibold opacity-70 uppercase tracking-widest">Familles</p>
                        <p class="text-4xl font-black mt-2 tracking-tight">{{ globalStats.famillesCount }}</p>
                    </div>
                    <div class="mt-4 flex items-center text-xs opacity-80 font-medium relative z-10">
                        <FolderTree class="h-3.5 w-3.5 mr-1.5" />
                        Groupes logiques
                    </div>
                    <FolderTree class="absolute -right-4 -bottom-4 h-24 w-24 opacity-10 transform -rotate-12 group-hover:scale-110 transition-transform duration-500" />
                </div>
            </div>

            <!-- Filtres et Recherche -->
            <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm space-y-4">
                <div class="flex flex-col md:flex-row gap-4">
                    <div class="relative flex-1">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                        <Input 
                            v-model="searchQuery" 
                            placeholder="Rechercher par nom, code..." 
                            class="pl-10 h-10 border-gray-200 focus-visible:ring-purple-500 rounded-lg shadow-sm"
                        />
                    </div>
                    <div class="flex gap-2">
                        <div class="relative w-[200px]">
                            <Filter class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400 pointer-events-none" />
                            <select 
                                v-model="statusFilter"
                                class="w-full h-10 pl-10 pr-4 rounded-lg border border-gray-200 bg-white text-sm appearance-none focus:outline-none focus:ring-2 focus:ring-purple-500 shadow-sm"
                            >
                                <option value="tous">Tous les statuts</option>
                                <option value="sains">État Optimal</option>
                                <option value="alerte">En Alerte</option>
                                <option value="rupture">En Rupture</option>
                            </select>
                            <ChevronDown class="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400 pointer-events-none" />
                        </div>
                        <Button variant="secondary" @click="resetFilters" class="h-10 flex items-center gap-2 bg-gray-50 hover:bg-gray-100 border border-gray-200 text-gray-700 rounded-lg">
                            <RotateCcw class="h-4 w-4" /> Réinitialiser
                        </Button>
                    </div>
                </div>

                <div class="flex items-center gap-4 text-sm">
                    <span class="text-gray-500 font-semibold whitespace-nowrap">Filtres rapides :</span>
                    <div class="flex flex-wrap gap-2">
                        <button 
                            @click="statusFilter = 'tous'"
                            :class="['px-4 py-1.5 rounded-full text-xs font-semibold transition-all shadow-sm border', statusFilter === 'tous' ? 'bg-purple-100 text-purple-700 border-purple-200 ring-2 ring-purple-500/10' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50 hover:border-gray-300']"
                        >
                            Tous
                        </button>
                        <button 
                            @click="statusFilter = 'sains'"
                            :class="['px-4 py-1.5 rounded-full text-xs font-semibold transition-all shadow-sm border', statusFilter === 'sains' ? 'bg-emerald-100 text-emerald-700 border-emerald-200 ring-2 ring-emerald-500/10' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50 hover:border-gray-300']"
                        >
                            <span class="flex items-center gap-1.5"><CheckCircle class="h-3.5 w-3.5" /> Optimal</span>
                        </button>
                        <button 
                            @click="statusFilter = 'alerte'"
                            :class="['px-4 py-1.5 rounded-full text-xs font-semibold transition-all shadow-sm border', statusFilter === 'alerte' ? 'bg-amber-100 text-amber-700 border-amber-200 ring-2 ring-amber-500/10' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50 hover:border-gray-300']"
                        >
                            <span class="flex items-center gap-1.5"><AlertTriangle class="h-3.5 w-3.5" /> Alerte</span>
                        </button>
                        <button 
                            @click="statusFilter = 'rupture'"
                            :class="['px-4 py-1.5 rounded-full text-xs font-semibold transition-all shadow-sm border', statusFilter === 'rupture' ? 'bg-rose-100 text-rose-700 border-rose-200 ring-2 ring-rose-500/10' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50 hover:border-gray-300']"
                        >
                            <span class="flex items-center gap-1.5"><XCircle class="h-3.5 w-3.5" /> Rupture</span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="space-y-4">
                <div v-if="hierarchicalData.length === 0" class="text-center py-12 bg-white rounded-lg border border-dashed border-gray-300">
                    <Package class="mx-auto h-12 w-12 text-gray-400" />
                    <h3 class="mt-2 text-sm font-semibold text-gray-900">Aucun article trouvé</h3>
                    <p class="mt-1 text-sm text-gray-500">Commencez par ajouter des articles dans la configuration.</p>
                </div>

                <div v-else v-for="famille in hierarchicalData.filter(f => f.id === activeFamilyId)" :key="famille.id" class="overflow-hidden bg-white border border-gray-200 rounded-lg shadow-sm">
                    <div class="p-4 space-y-6">
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

                                <div v-if="activeFamilyData?.categories?.length > 0" class="pt-4 border-t border-gray-100 flex flex-col gap-4">
                                    
                                    <!-- Onglets des Catégories -->
                                    <div class="flex items-center gap-2 overflow-x-auto pb-2 scrollbar-none border-b border-gray-100">
                                        <button 
                                            v-for="categorie in activeFamilyData.categories" 
                                            :key="categorie.id"
                                            @click="activeCategoryId = categorie.id"
                                            :class="[
                                                'px-4 py-2 rounded-t-md font-semibold text-sm transition-all whitespace-nowrap border-x border-t -mb-[1px]',
                                                activeCategoryId === categorie.id 
                                                    ? 'bg-blue-50 border-blue-100 text-blue-700' 
                                                    : 'bg-white border-transparent text-gray-500 hover:bg-gray-50 hover:text-gray-700'
                                            ]"
                                        >
                                            <div class="flex items-center gap-1.5">
                                                <Layers class="h-3.5 w-3.5" :class="activeCategoryId === categorie.id ? 'text-blue-600' : 'text-gray-400'" />
                                                {{ categorie.nom }}
                                            </div>
                                        </button>
                                    </div>

                                    <!-- Section Sous-catégories et Table -->
                                    <div v-if="activeCategoryData" class="flex flex-col gap-4">
                                        
                                        <!-- Onglets des Sous-Catégories -->
                                        <div v-if="activeCategoryData.sousCategories?.length > 0" class="flex items-center gap-2 overflow-x-auto pb-2 scrollbar-none border-b border-gray-100">
                                            <button 
                                                v-for="sousCategorie in activeCategoryData.sousCategories" 
                                                :key="sousCategorie.id"
                                                @click="activeSousCategoryId = sousCategorie.id"
                                                :class="[
                                                    'px-4 py-1.5 rounded-full text-xs font-semibold transition-all whitespace-nowrap border',
                                                    activeSousCategoryId === sousCategorie.id 
                                                        ? 'bg-amber-50 border-amber-200 text-amber-700 shadow-sm' 
                                                        : 'bg-white border-gray-200 text-gray-600 hover:bg-gray-50 hover:border-gray-300'
                                                ]"
                                            >
                                                <div class="flex items-center gap-1.5">
                                                    <Package class="h-3 w-3" :class="activeSousCategoryId === sousCategorie.id ? 'text-amber-600' : 'text-gray-400'" />
                                                    {{ sousCategorie.nom }}
                                                </div>
                                            </button>
                                        </div>

                                        <!-- Table des articles de la sous-catégorie active -->
                                        <div v-if="activeSousCategoryData" class="overflow-hidden border border-gray-200 rounded-lg shadow-sm bg-white">
                                            <table class="min-w-full divide-y divide-gray-200">
                                                <thead class="bg-gray-50/80">
                                                    <tr>
                                                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Code</th>
                                                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Article</th>
                                                        <th scope="col" class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Stock</th>
                                                        <th scope="col" class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Seuil</th>
                                                        <th scope="col" class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Statut</th>
                                                        <th scope="col" class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="bg-white divide-y divide-gray-100">
                                                    <tr v-for="article in activeSousCategoryData.articles" :key="article.id" class="hover:bg-gray-50/80 transition-colors">
                                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 font-mono">{{ article.code }}</td>
                                                        <td class="px-4 py-3 text-sm text-gray-900 font-semibold">{{ article.description }}</td>
                                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-center align-middle">
                                                            <div class="flex items-center justify-center gap-1">
                                                                <span class="font-bold text-base" :class="article.stock_actuel <= article.seuil_alerte ? 'text-red-600' : 'text-gray-900'">
                                                                    {{ article.stock_actuel }}
                                                                </span>
                                                                <AlertTriangle v-if="article.stock_actuel <= article.seuil_alerte" class="h-3.5 w-3.5 text-red-500 animate-pulse" />
                                                            </div>
                                                        </td>
                                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 text-center">{{ article.seuil_alerte }}</td>
                                                        <td class="px-4 py-3 whitespace-nowrap text-center">
                                                            <span :class="['inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium', getStockStatusClass(article)]">
                                                                {{ getStockStatusLabel(article) }}
                                                            </span>
                                                        </td>
                                                        <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-medium">
                                                            <div class="flex items-center justify-end gap-2 font-normal">
                                                                <Button size="sm" variant="ghost" class="h-8 w-8 p-0 text-gray-400 hover:text-blue-600 hover:bg-blue-50" @click="openViewModal(article)">
                                                                    <Eye class="h-4 w-4" />
                                                                </Button>
                                                                <Button size="sm" variant="outline" class="h-8 px-2.5 text-emerald-700 bg-emerald-50 border-emerald-200 hover:bg-emerald-100" @click="openMovementModal(article, 'entree')">
                                                                    <Plus class="h-3.5 w-3.5 mr-1" /> Entrée
                                                                </Button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div v-else class="text-center py-8 text-gray-500 text-sm italic">
                                            Aucun article ou sous-catégorie disponible.
                                        </div>
                                    </div>
                                    <div v-else class="text-center py-8 text-gray-500 text-sm italic">
                                        Aucune catégorie disponible.
                                    </div>
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
