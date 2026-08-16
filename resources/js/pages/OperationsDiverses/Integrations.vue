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
import { computed, onMounted, onUnmounted, ref } from 'vue';

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
    odIntegrationConfigured?: boolean;
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

const fieldClass =
    'h-10 border-slate-300 bg-white text-slate-900 shadow-sm placeholder:text-slate-400 focus-visible:border-primary focus-visible:ring-primary/30 dark:border-slate-600 dark:bg-card dark:text-foreground dark:placeholder:text-slate-500';

const selectClass =
    'flex h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm focus-visible:outline-none focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/30 dark:border-slate-600 dark:bg-card dark:text-foreground';

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
    Object.entries(searchForm.value).forEach(([k, v]) => {
        if (v) params[k] = v;
    });
    router.get('/operations-diverses/integrations', params, {
        preserveScroll: true,
        preserveState: true,
    });
}

function resetSearch() {
    searchForm.value = { q: '', nom_classeur: '', numero_batch: '', user_id: '' };
    router.get('/operations-diverses/integrations', {}, { preserveScroll: true });
}

function supprimer(c: ClasseurRow) {
    if (supprimerEnCours.value !== null) return;
    if (
        !window.confirm(
            `Supprimer le brouillon « ${c.nom_classeur} » ? Cette action est irréversible.`,
        )
    ) {
        return;
    }
    supprimerEnCours.value = c.id;
    router.delete(c.supprimer_url, {
        preserveScroll: true,
        onFinish: () => {
            supprimerEnCours.value = null;
        },
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
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function onDocClick(e: MouseEvent) {
    const t = e.target as HTMLElement | null;
    if (t && !t.closest('[data-new-menu]')) {
        showNewMenu.value = false;
    }
}

onMounted(() => document.addEventListener('click', onDocClick));
onUnmounted(() => document.removeEventListener('click', onDocClick));
</script>

<template>
    <Head title="Mes brouillons — Intégrations OD" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 sm:p-6">
            <div
                v-if="flash?.success"
                class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200"
            >
                {{ flash.success }}
            </div>
            <div
                v-if="flash?.warning"
                class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200"
            >
                {{ flash.warning }}
            </div>

            <section class="overflow-hidden rounded-2xl border border-border/80 bg-card shadow-sm">
                <div
                    class="border-b border-border/80 bg-gradient-to-r from-primary/5 via-card to-transparent px-5 py-5 sm:px-6 dark:from-primary/10"
                >
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="flex items-start gap-3">
                            <div
                                class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary text-primary-foreground shadow-sm"
                            >
                                <FileSpreadsheet class="size-5" />
                            </div>
                            <div>
                                <h1 class="text-xl font-semibold tracking-tight text-foreground">
                                    Mes brouillons
                                </h1>
                                <p class="mt-1 max-w-xl text-sm text-muted-foreground">
                                    Intégrez vos classeurs (maker), puis le checker valide dans « En
                                    attente de validation ».
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <div
                                class="inline-flex items-center gap-2 rounded-full border border-amber-200 bg-amber-50 px-3 py-1.5 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200"
                            >
                                <span class="font-semibold tabular-nums">{{ total }}</span>
                                <span>brouillon(s)</span>
                            </div>

                            <div class="relative" data-new-menu>
                                <Button
                                    class="bg-primary text-primary-foreground hover:bg-primary/90"
                                    @click.stop="showNewMenu = !showNewMenu"
                                >
                                    <Plus class="size-4" />
                                    Nouvelle intégration
                                    <ChevronDown class="size-4 opacity-80" />
                                </Button>
                                <div
                                    v-if="showNewMenu"
                                    class="absolute right-0 z-20 mt-1.5 w-56 overflow-hidden rounded-xl border border-border bg-card shadow-lg"
                                >
                                    <Link
                                        href="/operations-diverses/piece-comptable"
                                        class="block px-4 py-2.5 text-sm transition hover:bg-primary/5"
                                        @click="showNewMenu = false"
                                    >
                                        Automatique (import CSV)
                                    </Link>
                                    <Link
                                        href="/operations-diverses/piece-comptable/manuelle"
                                        class="block border-t border-border px-4 py-2.5 text-sm transition hover:bg-primary/5"
                                        @click="showNewMenu = false"
                                    >
                                        Manuelle (saisie)
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recherche -->
                <div class="space-y-3 border-b border-border/80 p-5 sm:p-6">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <div class="relative flex-1">
                            <Search
                                class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400"
                            />
                            <Input
                                v-model="searchForm.q"
                                :class="[fieldClass, 'pl-9']"
                                placeholder="Rechercher classeur, batch, agent…"
                                @keydown.enter="applySearch"
                            />
                        </div>
                        <div class="flex shrink-0 gap-2">
                            <Button
                                variant="outline"
                                class="h-10 border-slate-300 text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-foreground"
                                @click="showFilters = !showFilters"
                            >
                                <SlidersHorizontal class="size-4" />
                                Filtres
                                <span
                                    v-if="hasActiveFilters"
                                    class="ml-0.5 size-1.5 rounded-full bg-primary"
                                />
                            </Button>
                            <Button
                                class="h-10 bg-primary text-primary-foreground hover:bg-primary/90"
                                @click="applySearch"
                            >
                                Rechercher
                            </Button>
                        </div>
                    </div>

                    <div
                        v-show="showFilters || hasActiveFilters"
                        class="grid gap-3 rounded-xl border border-slate-200 bg-slate-50/80 p-4 sm:grid-cols-2 lg:grid-cols-4 dark:border-slate-700 dark:bg-muted/20"
                    >
                        <Input
                            v-model="searchForm.nom_classeur"
                            placeholder="Nom du classeur"
                            :class="fieldClass"
                        />
                        <Input
                            v-model="searchForm.numero_batch"
                            placeholder="N° batch"
                            :class="fieldClass"
                        />
                        <select
                            v-if="canViewAllAgents"
                            v-model="searchForm.user_id"
                            :class="selectClass"
                        >
                            <option value="">Tous les agents</option>
                            <option
                                v-for="a in agents"
                                :key="a.id"
                                :value="String(a.id)"
                            >
                                {{ a.name }}
                            </option>
                        </select>
                        <Button
                            v-if="hasActiveFilters"
                            variant="ghost"
                            class="h-10 justify-start text-muted-foreground"
                            @click="resetSearch"
                        >
                            <X class="size-4" /> Effacer les filtres
                        </Button>
                    </div>
                </div>

                <!-- Liste -->
                <div class="px-5 py-4 sm:px-6">
                    <div class="mb-4">
                        <h2 class="text-sm font-semibold text-foreground">Brouillons en attente</h2>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            Cliquez « Intégrer » pour transmettre à la plateforme et désigner un
                            validateur du même pôle.
                        </p>
                    </div>

                    <div
                        v-if="!classeurs?.length"
                        class="flex flex-col items-center rounded-2xl border border-dashed border-slate-300 px-6 py-16 text-center dark:border-slate-600"
                    >
                        <div
                            class="mb-4 flex size-14 items-center justify-center rounded-2xl bg-primary/10 text-primary"
                        >
                            <FileText class="size-7" />
                        </div>
                        <p class="text-sm font-medium text-foreground">
                            Aucune intégration en brouillon
                        </p>
                        <p class="mt-1 max-w-sm text-xs text-muted-foreground">
                            Créez une intégration automatique ou manuelle pour commencer.
                        </p>
                        <div class="mt-5 flex flex-wrap justify-center gap-2">
                            <Link
                                href="/operations-diverses/piece-comptable"
                                class="inline-flex h-10 items-center gap-2 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                            >
                                <Plus class="size-4" /> Automatique
                            </Link>
                            <Link
                                href="/operations-diverses/piece-comptable/manuelle"
                                class="inline-flex h-10 items-center gap-2 rounded-md border border-primary/25 px-4 text-sm font-medium text-primary hover:bg-primary/5"
                            >
                                <Plus class="size-4" /> Manuelle
                            </Link>
                        </div>
                    </div>

                    <ul v-else class="space-y-3">
                        <li
                            v-for="c in classeurs"
                            :key="c.id"
                            class="group flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-primary/30 hover:shadow-md dark:border-slate-700 dark:bg-card lg:flex-row lg:items-center lg:justify-between lg:px-5"
                        >
                            <div class="flex min-w-0 flex-1 items-start gap-3.5">
                                <div
                                    class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                                >
                                    <FileText class="size-5" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span
                                            class="rounded-full bg-amber-100 px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-800 dark:bg-amber-950 dark:text-amber-200"
                                        >
                                            Brouillon
                                        </span>
                                        <h3 class="truncate text-base font-semibold text-foreground">
                                            {{ c.nom_classeur }}
                                        </h3>
                                    </div>
                                    <p class="mt-0.5 text-xs text-muted-foreground">
                                        {{ c.justificatifs_count }} justificatif(s)
                                    </p>

                                    <div class="mt-3 flex flex-wrap gap-x-5 gap-y-1.5 text-sm">
                                        <span
                                            class="inline-flex items-center gap-1.5 text-muted-foreground"
                                        >
                                            <Hash class="size-3.5 text-primary" />
                                            <span class="font-medium text-foreground">{{
                                                c.numero_batch
                                            }}</span>
                                        </span>
                                        <span
                                            class="inline-flex items-center gap-1.5 text-muted-foreground"
                                        >
                                            <CalendarDays class="size-3.5 text-primary" />
                                            {{ dateFmt(c.date_valeur) }}
                                        </span>
                                        <span
                                            v-if="canViewAllAgents"
                                            class="inline-flex items-center gap-1.5 text-muted-foreground"
                                        >
                                            <User class="size-3.5 text-primary" />
                                            {{ c.user_name }}
                                        </span>
                                        <span
                                            class="inline-flex items-center gap-1.5 text-muted-foreground"
                                        >
                                            <Clock class="size-3.5 text-primary" />
                                            {{ horodatage(c.created_at) }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex shrink-0 flex-wrap items-center gap-1.5 lg:justify-end">
                                <Button
                                    as-child
                                    variant="outline"
                                    size="sm"
                                    class="h-9 border-slate-300"
                                >
                                    <Link :href="c.resume_url" title="Voir le résumé">
                                        <FileSearch class="size-4" />
                                        Résumé
                                    </Link>
                                </Button>
                                <Button
                                    v-if="c.can_integrate"
                                    size="sm"
                                    class="h-9 bg-primary text-primary-foreground hover:bg-primary/90"
                                    :disabled="
                                        integrerEnCours ||
                                        supprimerEnCours === c.id ||
                                        !(eligibleCheckers?.length ?? 0)
                                    "
                                    :title="
                                        (eligibleCheckers?.length ?? 0)
                                            ? 'Intégrer et désigner un checker'
                                            : 'Aucun checker disponible dans votre pôle'
                                    "
                                    @click="ouvrirIntegrer(c)"
                                >
                                    <ShieldCheck class="size-4" />
                                    Intégrer
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-9 text-red-600 hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-950/40"
                                    :disabled="supprimerEnCours === c.id || integrerEnCours"
                                    :title="
                                        supprimerEnCours === c.id ? 'Suppression…' : 'Supprimer'
                                    "
                                    @click="supprimer(c)"
                                >
                                    <Trash2 class="size-4" />
                                    <span class="sr-only">Supprimer</span>
                                </Button>
                            </div>
                        </li>
                    </ul>
                </div>
            </section>

            <p
                v-if="(eligibleCheckers?.length ?? 0) === 0"
                class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200"
            >
                <strong>Intégration indisponible :</strong>
                aucun autre agent du pôle
                <strong>{{ checkerPole ?? 'Operations' }}</strong>
                n’est disponible comme validateur (checker).
            </p>

            <p
                v-if="odIntegrationConfigured === false"
                class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200"
            >
                Le service d’intégration n’est pas disponible. Contactez le support.
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
                        <label
                            for="checker-list"
                            class="mb-1.5 block text-sm font-medium text-foreground"
                        >
                            Validateur
                        </label>
                        <select
                            id="checker-list"
                            v-model="selectedCheckerId"
                            :class="selectClass"
                        >
                            <option value="">Choisir un agent…</option>
                            <option
                                v-for="ch in eligibleCheckers"
                                :key="ch.id"
                                :value="String(ch.id)"
                            >
                                {{ ch.name }}
                            </option>
                        </select>
                        <p
                            v-if="!(eligibleCheckers?.length ?? 0)"
                            class="mt-2 text-xs text-amber-700 dark:text-amber-300"
                        >
                            Aucun autre agent disponible dans votre pôle pour valider.
                        </p>
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            class="border-slate-300"
                            :disabled="integrerEnCours"
                            @click="showIntegrerModal = false"
                        >
                            Annuler
                        </Button>
                        <Button
                            class="bg-primary text-primary-foreground hover:bg-primary/90"
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
