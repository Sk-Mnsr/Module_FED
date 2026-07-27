<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAppearance } from '@/composables/useAppearance';
import {
    Plus,
    AlertTriangle,
    ArrowUpRight,
    ArrowDownRight,
    History,
    Package,
    Layers,
    FolderTree,
    Search,
    RotateCcw,
    CheckCircle,
    XCircle,
    TrendingUp,
    Eye,
    Moon,
    Sun,
    LibraryBig,
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

// Si le filtre retire la famille active, basculer sur la première encore visible
watch(hierarchicalData, (data) => {
    if (data.length === 0) return;
    if (!data.find((f) => f.id === activeFamilyId.value)) {
        activeFamilyId.value = data[0].id;
    }
});

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
        <div class="flex min-h-0 flex-1 flex-col gap-4 p-4 lg:p-6">
            <header class="flex shrink-0 flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Inventaire</p>
                    <h1 class="text-2xl font-semibold tracking-tight text-foreground lg:text-3xl">Gestion des stocks</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Suivi des niveaux et mouvements par famille.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        class="relative"
                        title="Basculer le thème clair / sombre"
                        @click="togglePageTheme"
                    >
                        <Sun class="size-4 scale-100 rotate-0 transition-all dark:scale-0 dark:-rotate-90" />
                        <Moon class="absolute size-4 scale-0 rotate-90 transition-all dark:scale-100 dark:rotate-0" />
                    </Button>
                    <Button type="button" @click="openMovementModal(null, 'entree')">
                        <Plus class="mr-1.5 size-4" />
                        <span class="hidden sm:inline">Nouvelle entrée</span>
                        <span class="sm:hidden">Entrée</span>
                    </Button>
                    <Button as-child variant="outline">
                        <Link href="/stock/movements" class="inline-flex items-center gap-2">
                            <History class="size-4" />
                            Historique
                        </Link>
                    </Button>
                </div>
            </header>

            <!-- KPI sobres (cliquables pour filtrer) -->
            <div class="grid shrink-0 grid-cols-2 gap-3 lg:grid-cols-5">
                <button
                    type="button"
                    class="rounded-xl border border-border bg-card px-4 py-3 text-left shadow-sm transition-colors hover:bg-muted/40"
                    @click="statusFilter = 'tous'"
                >
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Articles</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums text-foreground">{{ globalStats.totalArticles }}</p>
                    <p class="mt-0.5 text-xs text-muted-foreground">Références</p>
                </button>
                <div class="rounded-xl border border-border bg-card px-4 py-3 shadow-sm">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Stock physique</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums text-foreground">{{ globalStats.totalQty }}</p>
                    <p class="mt-0.5 text-xs text-muted-foreground">Unités totales</p>
                </div>
                <button
                    type="button"
                    class="rounded-xl border border-border bg-card px-4 py-3 text-left shadow-sm transition-colors hover:bg-amber-50/60"
                    :class="statusFilter === 'alerte' ? 'ring-1 ring-amber-400' : ''"
                    @click="statusFilter = statusFilter === 'alerte' ? 'tous' : 'alerte'"
                >
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-amber-700">En alerte</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums text-amber-800">{{ globalStats.alerte }}</p>
                    <p class="mt-0.5 text-xs text-muted-foreground">Sous le seuil</p>
                </button>
                <button
                    type="button"
                    class="rounded-xl border border-border bg-card px-4 py-3 text-left shadow-sm transition-colors hover:bg-red-50/60"
                    :class="statusFilter === 'rupture' ? 'ring-1 ring-red-400' : ''"
                    @click="statusFilter = statusFilter === 'rupture' ? 'tous' : 'rupture'"
                >
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-red-700">En rupture</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums text-red-800">{{ globalStats.rupture }}</p>
                    <p class="mt-0.5 text-xs text-muted-foreground">Stock épuisé</p>
                </button>
                <div class="col-span-2 rounded-xl border border-border bg-card px-4 py-3 shadow-sm lg:col-span-1">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Familles</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums text-foreground">{{ globalStats.famillesCount }}</p>
                    <p class="mt-0.5 text-xs text-muted-foreground">Groupes</p>
                </div>
            </div>

            <!-- Recherche + statut (une seule rangée) -->
            <div class="flex shrink-0 flex-col gap-3 rounded-xl border border-border bg-card p-3 shadow-sm sm:flex-row sm:items-center sm:p-4">
                <div class="relative min-w-0 flex-1">
                    <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        v-model="searchQuery"
                        placeholder="Rechercher par nom, code…"
                        class="h-10 pl-10"
                    />
                </div>
                <div class="flex flex-wrap items-center gap-1.5">
                    <button
                        v-for="opt in [
                            { value: 'tous', label: 'Tous' },
                            { value: 'sains', label: 'Optimal', icon: 'ok' },
                            { value: 'alerte', label: 'Alerte', icon: 'warn' },
                            { value: 'rupture', label: 'Rupture', icon: 'x' },
                        ]"
                        :key="opt.value"
                        type="button"
                        :class="[
                            'inline-flex items-center gap-1 rounded-md border px-2.5 py-1.5 text-xs font-medium transition-colors',
                            statusFilter === opt.value
                                ? 'border-foreground/20 bg-foreground text-background'
                                : 'border-border bg-background text-muted-foreground hover:bg-muted/60 hover:text-foreground',
                        ]"
                        @click="statusFilter = opt.value"
                    >
                        <CheckCircle v-if="opt.icon === 'ok'" class="size-3.5" />
                        <AlertTriangle v-else-if="opt.icon === 'warn'" class="size-3.5" />
                        <XCircle v-else-if="opt.icon === 'x'" class="size-3.5" />
                        {{ opt.label }}
                    </button>
                    <Button
                        v-if="hasActiveFilters"
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="h-8 gap-1.5"
                        @click="resetFilters"
                    >
                        <RotateCcw class="size-3.5" />
                        Réinitialiser
                    </Button>
                </div>
            </div>

            <div class="flex min-h-0 flex-1 flex-col gap-4">
                <div
                    v-if="hierarchicalData.length === 0"
                    class="flex min-h-[min(20rem,calc(100dvh-22rem))] flex-1 flex-col justify-center rounded-xl border border-dashed border-border bg-muted/20 px-6 py-12 text-center"
                >
                    <h3 class="text-base font-semibold text-foreground">Aucun article trouvé</h3>
                    <p class="mx-auto mt-2 max-w-md text-sm text-muted-foreground">
                        <template v-if="hasActiveFilters">
                            Aucun résultat pour ces critères. Élargissez la recherche ou réinitialisez les filtres.
                        </template>
                        <template v-else>
                            Créez des articles et rattachez-les à une famille dans la configuration.
                        </template>
                    </p>
                    <div class="mt-6 flex flex-col items-center justify-center gap-2 sm:flex-row">
                        <Button
                            v-if="hasActiveFilters"
                            type="button"
                            variant="outline"
                            @click="resetFilters"
                        >
                            <RotateCcw class="mr-2 size-4" />
                            Réinitialiser les filtres
                        </Button>
                        <Button as-child>
                            <Link href="/articles" class="inline-flex items-center gap-2">
                                <LibraryBig class="size-4" />
                                Ouvrir les articles
                            </Link>
                        </Button>
                    </div>
                </div>

                <div
                    v-else
                    class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-xl border border-border bg-card shadow-sm"
                >
                    <!-- Navigation hiérarchique compacte -->
                    <div class="shrink-0 space-y-2 border-b border-border p-3 sm:p-4">
                        <div
                            class="flex gap-1 overflow-x-auto pb-0.5 scrollbar-none"
                            role="tablist"
                            aria-label="Familles"
                        >
                            <button
                                v-for="famille in hierarchicalData"
                                :key="famille.id"
                                type="button"
                                role="tab"
                                :aria-selected="activeFamilyId === famille.id ? 'true' : 'false'"
                                :class="[
                                    'shrink-0 rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                                    activeFamilyId === famille.id
                                        ? 'bg-foreground text-background'
                                        : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                                ]"
                                @click="activeFamilyId = famille.id"
                            >
                                {{ famille.nom }}
                            </button>
                        </div>

                        <div
                            v-if="activeFamilyData?.categories?.length"
                            class="flex gap-1 overflow-x-auto scrollbar-none"
                        >
                            <button
                                v-for="categorie in activeFamilyData.categories"
                                :key="categorie.id"
                                type="button"
                                :class="[
                                    'inline-flex shrink-0 items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-medium transition-colors',
                                    activeCategoryId === categorie.id
                                        ? 'bg-muted text-foreground ring-1 ring-border'
                                        : 'text-muted-foreground hover:bg-muted/60 hover:text-foreground',
                                ]"
                                @click="activeCategoryId = categorie.id"
                            >
                                <Layers class="size-3.5 opacity-70" />
                                {{ categorie.nom }}
                            </button>
                        </div>

                        <div
                            v-if="activeCategoryData?.sousCategories?.length"
                            class="flex gap-1 overflow-x-auto scrollbar-none"
                        >
                            <button
                                v-for="sousCategorie in activeCategoryData.sousCategories"
                                :key="sousCategorie.id"
                                type="button"
                                :class="[
                                    'inline-flex shrink-0 items-center gap-1.5 rounded-md border px-2.5 py-1 text-xs font-medium transition-colors',
                                    activeSousCategoryId === sousCategorie.id
                                        ? 'border-border bg-background text-foreground'
                                        : 'border-transparent text-muted-foreground hover:border-border hover:bg-muted/40',
                                ]"
                                @click="activeSousCategoryId = sousCategorie.id"
                            >
                                <Package class="size-3 opacity-70" />
                                {{ sousCategorie.nom }}
                            </button>
                        </div>
                    </div>

                    <div class="flex min-h-0 flex-1 flex-col p-3 sm:p-4">
                        <div v-if="!activeFamilyData?.categories?.length" class="py-8 text-center text-sm text-muted-foreground">
                            Aucune catégorie disponible.
                        </div>
                        <div v-else-if="!activeCategoryData" class="py-8 text-center text-sm text-muted-foreground">
                            Aucune catégorie disponible.
                        </div>
                        <div v-else-if="!activeSousCategoryData" class="py-8 text-center text-sm text-muted-foreground">
                            Aucun article ou sous-catégorie disponible.
                        </div>
                        <div
                            v-else
                            class="min-h-0 flex-1 overflow-auto rounded-lg border border-border"
                        >
                            <table class="min-w-full divide-y divide-border md:min-w-[640px]">
                                <thead class="sticky top-0 z-10 bg-muted/90 backdrop-blur-sm">
                                    <tr>
                                        <th scope="col" class="px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground sm:px-4">Code</th>
                                        <th scope="col" class="px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground sm:px-4">Article</th>
                                        <th scope="col" class="px-3 py-2.5 text-center text-xs font-semibold uppercase tracking-wider text-muted-foreground sm:px-4">Stock</th>
                                        <th scope="col" class="px-3 py-2.5 text-center text-xs font-semibold uppercase tracking-wider text-muted-foreground sm:px-4">Seuil</th>
                                        <th scope="col" class="px-3 py-2.5 text-center text-xs font-semibold uppercase tracking-wider text-muted-foreground sm:px-4">Statut</th>
                                        <th scope="col" class="px-3 py-2.5 text-right text-xs font-semibold uppercase tracking-wider text-muted-foreground sm:px-4">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border bg-card">
                                    <tr
                                        v-for="article in activeSousCategoryData.articles"
                                        :key="article.id"
                                        class="transition-colors hover:bg-muted/40"
                                    >
                                        <td class="whitespace-nowrap px-3 py-2.5 font-mono text-sm text-muted-foreground sm:px-4">
                                            {{ article.code }}
                                        </td>
                                        <td class="px-3 py-2.5 text-sm font-medium text-foreground sm:px-4">
                                            {{ article.description }}
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-2.5 text-center sm:px-4">
                                            <div class="flex items-center justify-center gap-1">
                                                <span
                                                    class="text-base font-semibold tabular-nums"
                                                    :class="article.stock_actuel <= article.seuil_alerte ? 'text-red-600' : 'text-foreground'"
                                                >
                                                    {{ article.stock_actuel }}
                                                </span>
                                                <AlertTriangle
                                                    v-if="article.stock_actuel <= article.seuil_alerte"
                                                    class="size-3.5 shrink-0 text-red-500"
                                                />
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-2.5 text-center text-sm tabular-nums text-muted-foreground sm:px-4">
                                            {{ article.seuil_alerte }}
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-2.5 text-center sm:px-4">
                                            <span
                                                :class="[
                                                    'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium',
                                                    getStockStatusClass(article),
                                                ]"
                                            >
                                                {{ getStockStatusLabel(article) }}
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-2.5 text-right sm:px-4">
                                            <div class="flex items-center justify-end gap-1.5">
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    class="size-8 p-0 text-muted-foreground"
                                                    @click="openViewModal(article)"
                                                >
                                                    <Eye class="size-4" />
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    class="h-8"
                                                    @click="openMovementModal(article, 'entree')"
                                                >
                                                    <Plus class="mr-1 size-3.5" />
                                                    Entrée
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
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
