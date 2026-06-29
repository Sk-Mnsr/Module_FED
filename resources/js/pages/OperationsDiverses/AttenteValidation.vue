<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    CalendarDays,
    Clock,
    FileSearch,
    FileSpreadsheet,
    FileText,
    Hash,
    Search,
    ShieldCheck,
    SlidersHorizontal,
    User,
    UserCheck,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

type ClasseurRow = {
    id: number;
    nom_classeur: string;
    numero_batch: string;
    date_valeur: string | null;
    maker_name: string | null;
    checker_name: string | null;
    integrated_at: string | null;
    justificatifs_count: number;
    resume_url: string;
    can_validate: boolean;
    valider_checker_url: string;
};

const props = defineProps<{
    classeurs?: ClasseurRow[];
    filters?: { q?: string; nom_classeur?: string; numero_batch?: string };
    canViewAllAgents?: boolean;
    comptableImportApiConfigured?: boolean;
}>();

const breadcrumbs = [
    { title: 'Opérations diverses', href: '/operations-diverses/piece-comptable' },
    { title: 'Intégration', href: '/operations-diverses/integrations' },
    { title: 'En attente de validation', href: '/operations-diverses/attente-validation' },
];

const page = usePage();
const flash = computed(() => page.props.flash as { success?: string; error?: string; warning?: string } | undefined);
const validerEnCours = ref<number | null>(null);
const showFilters = ref(false);

const searchForm = ref({
    q: props.filters?.q ?? '',
    nom_classeur: props.filters?.nom_classeur ?? '',
    numero_batch: props.filters?.numero_batch ?? '',
});

const hasActiveFilters = computed(() => Object.values(searchForm.value).some(Boolean));
const total = computed(() => props.classeurs?.length ?? 0);
const aValider = computed(() => props.classeurs?.filter((c) => c.can_validate).length ?? 0);

function applySearch() {
    const params: Record<string, string> = {};
    Object.entries(searchForm.value).forEach(([k, v]) => { if (v) params[k] = v; });
    router.get('/operations-diverses/attente-validation', params, { preserveScroll: true, preserveState: true });
}

function resetSearch() {
    searchForm.value = { q: '', nom_classeur: '', numero_batch: '' };
    router.get('/operations-diverses/attente-validation', {}, { preserveScroll: true });
}

function valider(c: ClasseurRow) {
    if (validerEnCours.value !== null || !c.can_validate) return;
    if (!window.confirm(`Valider et archiver « ${c.nom_classeur} » ?`)) return;
    validerEnCours.value = c.id;
    router.post(c.valider_checker_url, {}, {
        preserveScroll: true,
        onFinish: () => { validerEnCours.value = null; },
    });
}

function dateFmt(iso: string | null): string {
    if (!iso) return '—';
    const [y, m, d] = iso.split('-');
    return `${d}/${m}/${y}`;
}

function horodatage(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('fr-FR', {
        day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
    });
}
</script>

<template>
    <Head title="En attente de validation — OD" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div v-if="flash?.success" class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
                {{ flash.success }}
            </div>
            <div v-if="flash?.warning" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
                {{ flash.warning }}
            </div>

            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex items-start gap-3">
                    <div class="rounded-lg bg-muted p-2 text-muted-foreground">
                        <UserCheck class="size-6" />
                    </div>
                    <div>
                        <h1 class="text-xl font-semibold text-foreground">En attente de validation</h1>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Intégrations transmises à la plateforme, en attente de validation checker (4 yeux).
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <div class="inline-flex items-center gap-2 rounded-lg border border-blue-200/80 bg-blue-50/80 px-3 py-1.5 text-sm text-blue-800">
                        <span class="font-semibold">{{ total }}</span>
                        <span>en attente</span>
                    </div>
                    <div v-if="aValider > 0" class="inline-flex items-center gap-2 rounded-lg border border-violet-200/80 bg-violet-50/80 px-3 py-1.5 text-sm text-violet-800">
                        <span class="font-semibold">{{ aValider }}</span>
                        <span>à traiter par vous</span>
                    </div>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
                    <div class="relative flex-1">
                        <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            v-model="searchForm.q"
                            class="h-9 border-0 bg-muted/40 pl-9 shadow-none focus-visible:ring-1 focus-visible:ring-violet-400/40"
                            placeholder="Rechercher classeur, batch, maker…"
                            @keydown.enter="applySearch"
                        />
                    </div>
                    <div class="flex shrink-0 gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-9 border-violet-200 text-violet-800 hover:bg-violet-50 dark:border-violet-900 dark:text-violet-200"
                            @click="showFilters = !showFilters"
                        >
                            <SlidersHorizontal class="size-4" />
                            Filtres
                        </Button>
                        <Button size="sm" class="h-9 bg-violet-600 hover:bg-violet-700" @click="applySearch">
                            Rechercher
                        </Button>
                    </div>
                </div>

                <div v-show="showFilters || hasActiveFilters" class="grid gap-3 border-t border-border px-4 py-4 sm:grid-cols-2 lg:grid-cols-3">
                    <Input v-model="searchForm.nom_classeur" placeholder="Nom du classeur" class="h-9" />
                    <Input v-model="searchForm.numero_batch" placeholder="N° batch" class="h-9" />
                    <Button v-if="hasActiveFilters" variant="ghost" size="sm" class="h-9 justify-start" @click="resetSearch">
                        <X class="size-4" /> Effacer les filtres
                    </Button>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <div class="border-b border-border bg-gradient-to-r from-blue-50/90 to-transparent px-5 py-3 dark:from-blue-950/40 dark:to-transparent">
                    <h2 class="text-sm font-semibold text-foreground">File d’attente checker</h2>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        Seul le validateur désigné par le maker peut valider et archiver.
                    </p>
                </div>

                <div v-if="!classeurs?.length" class="flex flex-col items-center px-6 py-20 text-center">
                    <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-blue-50 dark:bg-blue-950/30">
                        <FileSpreadsheet class="size-8 text-blue-400/60" />
                    </div>
                    <p class="text-sm font-medium text-foreground">Aucune intégration en attente</p>
                    <p class="mt-1 max-w-sm text-xs text-muted-foreground">
                        Les intégrations apparaissent ici après transmission par un maker.
                    </p>
                </div>

                <div v-else class="divide-y divide-border">
                    <div
                        v-for="c in classeurs"
                        :key="c.id"
                        class="group flex flex-col gap-4 p-5 transition hover:bg-blue-50/30 dark:hover:bg-blue-950/10 lg:flex-row lg:items-center lg:justify-between"
                    >
                        <div class="flex min-w-0 flex-1 items-start gap-4">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-blue-100 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300">
                                <FileText class="size-5" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full bg-blue-100 px-2.5 py-0.5 text-[10px] font-semibold text-blue-800">
                                        Attente validation
                                    </span>
                                    <h3 class="truncate text-base font-semibold text-foreground">{{ c.nom_classeur }}</h3>
                                </div>
                                <p class="mt-0.5 text-xs text-muted-foreground">
                                    {{ c.justificatifs_count }} justificatif(s)
                                </p>

                                <div class="mt-3 flex flex-wrap gap-x-5 gap-y-1.5 text-sm">
                                    <span class="inline-flex items-center gap-1.5 text-muted-foreground">
                                        <Hash class="size-3.5 text-blue-500" />
                                        <span class="font-medium text-foreground">{{ c.numero_batch }}</span>
                                    </span>
                                    <span class="inline-flex items-center gap-1.5 text-muted-foreground">
                                        <CalendarDays class="size-3.5 text-blue-500" />
                                        {{ dateFmt(c.date_valeur) }}
                                    </span>
                                    <span class="inline-flex items-center gap-1.5 text-muted-foreground">
                                        <User class="size-3.5 text-blue-500" />
                                        Maker : {{ c.maker_name }}
                                    </span>
                                    <span v-if="canViewAllAgents" class="inline-flex items-center gap-1.5 text-muted-foreground">
                                        <UserCheck class="size-3.5 text-blue-500" />
                                        Checker : {{ c.checker_name }}
                                    </span>
                                    <span class="inline-flex items-center gap-1.5 text-muted-foreground">
                                        <Clock class="size-3.5 text-blue-500" />
                                        {{ horodatage(c.integrated_at) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="flex shrink-0 items-center gap-0.5 lg:justify-end">
                            <Button
                                as-child
                                variant="ghost"
                                size="icon"
                                class="size-8 text-muted-foreground hover:text-foreground"
                            >
                                <Link :href="c.resume_url" title="Résumé">
                                    <FileSearch class="size-4" />
                                    <span class="sr-only">Résumé</span>
                                </Link>
                            </Button>
                            <Button
                                v-if="c.can_validate"
                                size="icon"
                                class="size-8 bg-green-600 text-white hover:bg-green-700"
                                :disabled="validerEnCours === c.id"
                                :title="validerEnCours === c.id ? 'Validation…' : 'Valider et archiver'"
                                @click="valider(c)"
                            >
                                <ShieldCheck class="size-4" />
                                <span class="sr-only">Valider et archiver</span>
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
