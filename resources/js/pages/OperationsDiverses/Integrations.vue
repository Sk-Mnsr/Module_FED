<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    CalendarDays,
    ChevronDown,
    Clock,
    FileSearch,
    FileSpreadsheet,
    FileText,
    Hash,
    Plus,
    Search,
    ShieldCheck,
    SlidersHorizontal,
    Trash2,
    User,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

type ClasseurRow = {
    id: number;
    nom_classeur: string;
    numero_batch: string;
    date_valeur: string | null;
    user_name: string | null;
    created_at: string | null;
    justificatifs_count: number;
    can_integrate: boolean;
    resume_url: string;
    integrer_url: string;
    supprimer_url: string;
};

type Agent = { id: number; name: string };
type Checker = { id: number; name: string };

const props = defineProps<{
    classeurs?: ClasseurRow[];
    agents?: Agent[];
    filters?: { q?: string; nom_classeur?: string; numero_batch?: string; user_id?: string };
    canViewAllAgents?: boolean;
    eligibleCheckers?: Checker[];
    checkerPole?: string;
    comptableImportApiConfigured?: boolean;
}>();

const breadcrumbs = [
    { title: 'Opérations diverses', href: '/operations-diverses/piece-comptable' },
    { title: 'Intégration', href: '/operations-diverses/integrations' },
    { title: 'Mes brouillons', href: '/operations-diverses/integrations' },
];

const page = usePage();
const flash = computed(() => page.props.flash as { success?: string; error?: string; warning?: string } | undefined);
const supprimerEnCours = ref<number | null>(null);
const integrerEnCours = ref(false);
const showIntegrerModal = ref(false);
const integrerTarget = ref<ClasseurRow | null>(null);
const selectedCheckerId = ref('');
const showFilters = ref(false);
const showNewMenu = ref(false);

const searchForm = ref({
    q: props.filters?.q ?? '',
    nom_classeur: props.filters?.nom_classeur ?? '',
    numero_batch: props.filters?.numero_batch ?? '',
    user_id: props.filters?.user_id ? String(props.filters.user_id) : '',
});

const hasActiveFilters = computed(() => Object.values(searchForm.value).some(Boolean));
const total = computed(() => props.classeurs?.length ?? 0);

function applySearch() {
    const params: Record<string, string> = {};
    Object.entries(searchForm.value).forEach(([k, v]) => { if (v) params[k] = v; });
    router.get('/operations-diverses/integrations', params, { preserveScroll: true, preserveState: true });
}

function resetSearch() {
    searchForm.value = { q: '', nom_classeur: '', numero_batch: '', user_id: '' };
    router.get('/operations-diverses/integrations', {}, { preserveScroll: true });
}

function supprimer(c: ClasseurRow) {
    if (supprimerEnCours.value !== null) return;
    if (!window.confirm(`Supprimer le brouillon « ${c.nom_classeur} » ? Cette action est irréversible.`)) {
        return;
    }
    supprimerEnCours.value = c.id;
    router.delete(c.supprimer_url, {
        preserveScroll: true,
        onFinish: () => { supprimerEnCours.value = null; },
    });
}

function ouvrirIntegrer(c: ClasseurRow) {
    if (!c.can_integrate) {
        router.visit(c.resume_url);
        return;
    }
    integrerTarget.value = c;
    selectedCheckerId.value = '';
    showIntegrerModal.value = true;
}

function confirmerIntegrer() {
    if (!integrerTarget.value || integrerEnCours.value) return;
    if (!selectedCheckerId.value) {
        window.alert('Veuillez désigner un validateur (checker).');
        return;
    }
    integrerEnCours.value = true;
    router.post(
        integrerTarget.value.integrer_url,
        { assigned_checker_user_id: selectedCheckerId.value },
        {
            preserveScroll: true,
            onFinish: () => {
                integrerEnCours.value = false;
                showIntegrerModal.value = false;
                integrerTarget.value = null;
            },
        },
    );
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
    <Head title="Mes brouillons — Intégrations OD" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <!-- Flash -->
            <div v-if="flash?.success" class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
                {{ flash.success }}
            </div>
            <div v-if="flash?.warning" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
                {{ flash.warning }}
            </div>

            <!-- En-tête -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex items-start gap-3">
                    <div class="rounded-lg bg-muted p-2 text-muted-foreground">
                        <FileSpreadsheet class="size-6" />
                    </div>
                    <div>
                        <h1 class="text-xl font-semibold text-foreground">Mes brouillons</h1>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Vos classeurs en cours. Intégrez-les (maker), puis le checker désigné valide dans « En attente de validation ».
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <div class="inline-flex items-center gap-2 rounded-lg border border-amber-200/80 bg-amber-50/80 px-3 py-1.5 text-sm text-amber-800">
                        <span class="font-semibold">{{ total }}</span>
                        <span>brouillon(s)</span>
                    </div>

                    <div class="relative">
                        <Button
                            class="bg-violet-600 text-white hover:bg-violet-700 dark:bg-violet-600 dark:hover:bg-violet-500"
                            @click="showNewMenu = !showNewMenu"
                        >
                            <Plus class="size-4" />
                            Nouvelle intégration
                            <ChevronDown class="size-4" />
                        </Button>
                        <div
                            v-if="showNewMenu"
                            class="absolute right-0 z-10 mt-1 w-52 overflow-hidden rounded-lg border border-border bg-card shadow-lg"
                        >
                            <Link
                                href="/operations-diverses/piece-comptable"
                                class="block px-4 py-2.5 text-sm hover:bg-violet-50 dark:hover:bg-violet-950/30"
                                @click="showNewMenu = false"
                            >
                                Automatique (import CSV)
                            </Link>
                            <Link
                                href="/operations-diverses/piece-comptable/manuelle"
                                class="block border-t border-border px-4 py-2.5 text-sm hover:bg-violet-50 dark:hover:bg-violet-950/30"
                                @click="showNewMenu = false"
                            >
                                Manuelle (saisie)
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recherche -->
            <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
                    <div class="relative flex-1">
                        <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            v-model="searchForm.q"
                            class="h-9 border-0 bg-muted/40 pl-9 shadow-none focus-visible:ring-1 focus-visible:ring-violet-400/40"
                            placeholder="Rechercher classeur, batch, agent…"
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
                            <span v-if="hasActiveFilters" class="ml-0.5 size-1.5 rounded-full bg-violet-600" />
                        </Button>
                        <Button
                            size="sm"
                            class="h-9 bg-violet-600 hover:bg-violet-700"
                            @click="applySearch"
                        >
                            Rechercher
                        </Button>
                    </div>
                </div>

                <div v-show="showFilters || hasActiveFilters" class="grid gap-3 border-t border-border px-4 py-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Input v-model="searchForm.nom_classeur" placeholder="Nom du classeur" class="h-9" />
                    <Input v-model="searchForm.numero_batch" placeholder="N° batch" class="h-9" />
                    <select
                        v-if="canViewAllAgents"
                        v-model="searchForm.user_id"
                        class="flex h-9 rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="">Tous les agents</option>
                        <option v-for="a in agents" :key="a.id" :value="String(a.id)">{{ a.name }}</option>
                    </select>
                    <Button v-if="hasActiveFilters" variant="ghost" size="sm" class="h-9 justify-start" @click="resetSearch">
                        <X class="size-4" /> Effacer les filtres
                    </Button>
                </div>
            </div>

            <!-- Liste -->
            <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <div class="border-b border-border bg-gradient-to-r from-violet-50/90 to-transparent px-5 py-3 dark:from-violet-950/40 dark:to-transparent">
                    <h2 class="text-sm font-semibold text-foreground">Brouillons en attente</h2>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        Cliquez « Intégrer » pour transmettre à la plateforme et désigner un validateur du même pôle.
                    </p>
                </div>

                <div v-if="!classeurs?.length" class="flex flex-col items-center px-6 py-20 text-center">
                    <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-violet-50 dark:bg-violet-950/30">
                        <FileText class="size-8 text-violet-400/60" />
                    </div>
                    <p class="text-sm font-medium text-foreground">Aucune intégration en brouillon</p>
                    <p class="mt-1 max-w-sm text-xs text-muted-foreground">
                        Créez une intégration automatique ou manuelle pour commencer.
                    </p>
                    <div class="mt-5 flex flex-wrap justify-center gap-2">
                        <Link
                            href="/operations-diverses/piece-comptable"
                            class="inline-flex h-9 items-center gap-2 rounded-md bg-violet-600 px-4 text-sm font-medium text-white hover:bg-violet-700"
                        >
                            <Plus class="size-4" /> Automatique
                        </Link>
                        <Link
                            href="/operations-diverses/piece-comptable/manuelle"
                            class="inline-flex h-9 items-center gap-2 rounded-md border border-violet-200 px-4 text-sm font-medium text-violet-800 hover:bg-violet-50 dark:border-violet-900 dark:text-violet-200"
                        >
                            <Plus class="size-4" /> Manuelle
                        </Link>
                    </div>
                </div>

                <div v-else class="divide-y divide-border">
                    <div
                        v-for="c in classeurs"
                        :key="c.id"
                        class="group flex flex-col gap-4 p-5 transition hover:bg-violet-50/30 dark:hover:bg-violet-950/10 lg:flex-row lg:items-center lg:justify-between"
                    >
                        <!-- Infos principales -->
                        <div class="flex min-w-0 flex-1 items-start gap-4">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-violet-100 text-violet-700 dark:bg-violet-950/50 dark:text-violet-300">
                                <FileText class="size-5" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-[10px] font-semibold text-amber-800">
                                        Brouillon
                                    </span>
                                    <h3 class="truncate text-base font-semibold text-foreground">{{ c.nom_classeur }}</h3>
                                </div>
                                <p class="mt-0.5 text-xs text-muted-foreground">
                                    {{ c.justificatifs_count }} justificatif(s)
                                </p>

                                <div class="mt-3 flex flex-wrap gap-x-5 gap-y-1.5 text-sm">
                                    <span class="inline-flex items-center gap-1.5 text-muted-foreground">
                                        <Hash class="size-3.5 text-violet-500" />
                                        <span class="font-medium text-foreground">{{ c.numero_batch }}</span>
                                    </span>
                                    <span class="inline-flex items-center gap-1.5 text-muted-foreground">
                                        <CalendarDays class="size-3.5 text-violet-500" />
                                        {{ dateFmt(c.date_valeur) }}
                                    </span>
                                    <span v-if="canViewAllAgents" class="inline-flex items-center gap-1.5 text-muted-foreground">
                                        <User class="size-3.5 text-violet-500" />
                                        {{ c.user_name }}
                                    </span>
                                    <span class="inline-flex items-center gap-1.5 text-muted-foreground">
                                        <Clock class="size-3.5 text-violet-500" />
                                        {{ horodatage(c.created_at) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex shrink-0 flex-wrap items-center gap-1 lg:justify-end">
                            <Button
                                as-child
                                variant="ghost"
                                size="icon"
                                class="size-8 text-muted-foreground hover:text-foreground"
                            >
                                <Link :href="c.resume_url" title="Voir le résumé">
                                    <FileSearch class="size-4" />
                                    <span class="sr-only">Résumé</span>
                                </Link>
                            </Button>
                            <Button
                                v-if="c.can_integrate"
                                size="sm"
                                class="h-8 bg-violet-600 text-white hover:bg-violet-700 dark:bg-violet-600 dark:hover:bg-violet-500"
                                :disabled="integrerEnCours || supprimerEnCours === c.id || !(eligibleCheckers?.length ?? 0)"
                                :title="(eligibleCheckers?.length ?? 0) ? 'Intégrer et désigner un checker' : 'Aucun checker disponible dans votre pôle'"
                                @click="ouvrirIntegrer(c)"
                            >
                                <ShieldCheck class="size-4" />
                                Intégrer
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="size-8 text-red-600 hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-950/40"
                                :disabled="supprimerEnCours === c.id || integrerEnCours"
                                :title="supprimerEnCours === c.id ? 'Suppression…' : 'Supprimer'"
                                @click="supprimer(c)"
                            >
                                <Trash2 class="size-4" />
                                <span class="sr-only">Supprimer</span>
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <p
                v-if="(eligibleCheckers?.length ?? 0) === 0"
                class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200"
            >
                <strong>Intégration indisponible :</strong>
                aucun autre agent du pôle
                <strong>{{ checkerPole ?? 'Operations' }}</strong>
                n’est disponible comme validateur (checker).
                Un collègue du même pôle (rôle OPS ou Finance) doit exister dans l’application pour que vous puissiez intégrer.
            </p>

            <p
                v-if="comptableImportApiConfigured === false"
                class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200"
            >
                API plateforme non configurée : l’intégration échouera tant que l’URL d’import n’est pas renseignée.
            </p>

            <Dialog v-model:open="showIntegrerModal">
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Intégrer</DialogTitle>
                        <DialogDescription>
                            Choisissez votre validateur après enregistrement.
                        </DialogDescription>
                    </DialogHeader>
                    <div class="py-2">
                        <label for="checker-list" class="mb-1.5 block text-sm font-medium text-foreground">
                            Validateur
                        </label>
                        <select
                            id="checker-list"
                            v-model="selectedCheckerId"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option value="">Choisir un agent…</option>
                            <option v-for="ch in eligibleCheckers" :key="ch.id" :value="String(ch.id)">
                                {{ ch.name }}
                            </option>
                        </select>
                        <p v-if="!(eligibleCheckers?.length ?? 0)" class="mt-2 text-xs text-amber-700 dark:text-amber-300">
                            Aucun autre agent disponible dans votre pôle pour valider.
                        </p>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" :disabled="integrerEnCours" @click="showIntegrerModal = false">
                            Annuler
                        </Button>
                        <Button
                            class="bg-violet-600 text-white hover:bg-violet-700"
                            :disabled="integrerEnCours || !selectedCheckerId"
                            @click="confirmerIntegrer"
                        >
                            {{ integrerEnCours ? 'Intégration…' : 'Confirmer l’intégration' }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
