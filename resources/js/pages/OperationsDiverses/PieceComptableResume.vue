<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    ArrowLeft,
    CheckCircle2,
    ChevronDown,
    ChevronUp,
    Clock,
    Download,
    FileSpreadsheet,
    FileText,
    FolderArchive,
    Hash,
    CalendarDays,
    Eye,
    Pencil,
    ShieldCheck,
    Trash2,
    UserCheck,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

type Piece = {
    id: number | string;
    description: string | null;
    original_name: string;
    url: string;
    preview_url?: string | null;
    is_piece_comptable?: boolean;
};

type Classeur = {
    id: number;
    numero_batch: string;
    numero_piece: string | null;
    nom_classeur: string;
    date_valeur: string | null;
    statut: string;
    integrated_at: string | null;
    validated_at: string | null;
    user_name: string | null;
    integrated_by_name: string | null;
    assigned_checker_name: string | null;
    validated_by_name: string | null;
    fichier: string | null;
    can_integrate: boolean;
    can_validate_checker: boolean;
    integrer_url: string;
    valider_checker_url: string;
    modifier_url: string | null;
    supprimer_url: string | null;
    pieces: Piece[];
};

type Checker = { id: number; name: string };

type ApercuRow = {
    numero: string;
    code_agence: string;
    no_compte: string;
    sens: string;
    montant: number;
    code_operation: string;
    libelle_ecriture: string;
    date_de_valeur: string;
};

type Apercu = {
    rows: ApercuRow[];
    total_rows: number;
    nb_credit: number;
    nb_debit: number;
    total_credit: number;
    total_debit: number;
    difference: number;
    devise: string;
    error: string | null;
};

const props = defineProps<{
    classeur: Classeur;
    apercu: Apercu;
    eligibleCheckers?: Checker[];
    checkerPole?: string;
    odIntegrationConfigured?: boolean;
}>();

const breadcrumbs = [
    { title: 'Opérations diverses', href: '/operations-diverses/piece-comptable' },
    { title: 'Pièce comptable', href: '/operations-diverses/piece-comptable' },
    { title: 'Résumé', href: '#' },
];

const page = usePage();
const flash = computed(() => page.props.flash as { success?: string; error?: string; warning?: string } | undefined);
const flashSuccess = computed(() => flash.value?.success);
const flashWarning = computed(() => flash.value?.warning);

const isBrouillon = computed(() => props.classeur.statut === 'brouillon');
const isAttenteValidation = computed(() => props.classeur.statut === 'attente_validation');
const isIntegre = computed(() => props.classeur.statut === 'integre');
const processing = ref(false);
const deleting = ref(false);
const showIntegrerModal = ref(false);
const selectedCheckerId = ref('');
const apercuExpanded = ref(
    (props.apercu.rows?.length ?? 0) <= 5 && !props.apercu.error,
);
const hasMultipleApercuRows = computed(
    () => (props.apercu.total_rows ?? props.apercu.rows?.length ?? 0) > 1,
);

const selectClass =
    'flex h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm focus-visible:outline-none focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/30 dark:border-slate-600 dark:bg-card dark:text-foreground';

const statutLabel = computed(() => {
    if (isIntegre.value) return 'Archivé';
    if (isAttenteValidation.value) return 'Attente de validation';
    return 'Brouillon';
});

const statutClass = computed(() => {
    if (isIntegre.value) {
        return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300';
    }
    if (isAttenteValidation.value) {
        return 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300';
    }
    return 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300';
});

const difference = computed(() => props.apercu.difference ?? props.apercu.total_debit - props.apercu.total_credit);
const isEquilibre = computed(() => Math.abs(difference.value) < 0.01);

function montantFmt(v: number): string {
    if (v === null || v === undefined) {
        return '';
    }
    const decimals = Number.isInteger(v) ? 0 : 2;
    return new Intl.NumberFormat('fr-FR', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: 2,
    }).format(v);
}

function ouvrirIntegrer() {
    if (processing.value || !props.classeur.can_integrate) return;
    selectedCheckerId.value = '';
    showIntegrerModal.value = true;
}

function confirmerIntegrer() {
    if (processing.value || !props.classeur.can_integrate) return;
    if (!selectedCheckerId.value) {
        return;
    }
    processing.value = true;
    router.post(
        props.classeur.integrer_url,
        { assigned_checker_user_id: selectedCheckerId.value },
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
                showIntegrerModal.value = false;
            },
        },
    );
}

function validerChecker() {
    if (processing.value || !props.classeur.can_validate_checker) return;
    if (!window.confirm('Valider et archiver cette intégration ?')) return;
    processing.value = true;
    router.post(
        props.classeur.valider_checker_url,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}

function supprimer() {
    if (deleting.value || !isBrouillon.value || !props.classeur.supprimer_url) {
        return;
    }
    if (
        !window.confirm(
            `Supprimer le brouillon « ${props.classeur.nom_classeur} » ? Cette action est irréversible.`,
        )
    ) {
        return;
    }
    deleting.value = true;
    router.delete(props.classeur.supprimer_url, {
        onFinish: () => {
            deleting.value = false;
        },
    });
}
</script>

<template>
    <Head title="Résumé de l’intégration — OD" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 sm:p-6">
            <div
                v-if="flashSuccess"
                class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200"
            >
                {{ flashSuccess }}
            </div>
            <div
                v-if="flashWarning"
                class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200"
            >
                {{ flashWarning }}
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
                                    Résumé de l’intégration
                                </h1>
                                <p class="mt-1 max-w-xl text-sm text-muted-foreground">
                                    Vérifiez l’aperçu puis intégrez (maker) ou validez (checker)
                                    selon votre rôle.
                                </p>
                            </div>
                        </div>
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold"
                            :class="statutClass"
                        >
                            <CheckCircle2 v-if="isIntegre" class="size-3.5" />
                            <Clock v-else-if="isAttenteValidation" class="size-3.5" />
                            {{ statutLabel }}
                        </span>
                    </div>
                </div>

                <!-- Batch + date -->
                <div class="grid gap-4 border-b border-border/80 p-5 sm:grid-cols-2 sm:p-6">
                    <div
                        class="rounded-xl border border-primary/20 bg-primary/5 p-4 dark:border-primary/30 dark:bg-primary/10"
                    >
                        <div
                            class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-wider text-primary"
                        >
                            <Hash class="size-3.5" /> Numéro batch
                        </div>
                        <p class="mt-2 text-lg font-bold tracking-wide text-foreground">
                            {{ classeur.numero_batch }}
                        </p>
                    </div>
                    <div
                        class="rounded-xl border border-slate-200 bg-slate-50/80 p-4 dark:border-slate-700 dark:bg-muted/20"
                    >
                        <div
                            class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300"
                        >
                            <CalendarDays class="size-3.5" /> Date valeur
                        </div>
                        <p class="mt-2 text-lg font-bold text-foreground">
                            {{ classeur.date_valeur ?? '—' }}
                        </p>
                    </div>
                </div>

                <!-- Métadonnées -->
                <div
                    class="grid gap-4 border-b border-border/80 px-5 py-4 text-sm sm:grid-cols-2 lg:grid-cols-4 sm:px-6"
                >
                    <div>
                        <p class="text-xs text-muted-foreground">Nom du classeur</p>
                        <p class="mt-0.5 font-medium text-foreground">{{ classeur.nom_classeur }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Fichier d’intégration</p>
                        <p class="mt-0.5 font-medium text-foreground">
                            {{ classeur.fichier ?? '—' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Maker</p>
                        <p class="mt-0.5 font-medium text-foreground">
                            {{ classeur.integrated_by_name ?? classeur.user_name ?? '—' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Checker désigné</p>
                        <p class="mt-0.5 font-medium text-foreground">
                            {{ classeur.assigned_checker_name ?? '—' }}
                        </p>
                    </div>
                    <div v-if="classeur.validated_by_name" class="sm:col-span-2 lg:col-span-4">
                        <p class="text-xs text-muted-foreground">Validé par</p>
                        <p class="mt-0.5 font-medium text-foreground">
                            {{ classeur.validated_by_name }}
                        </p>
                    </div>
                </div>

                <!-- Totaux -->
                <div class="grid gap-3 p-5 sm:grid-cols-2 lg:grid-cols-5 sm:p-6">
                    <div
                        class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-card"
                    >
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                            NB Crédit
                        </p>
                        <p class="mt-1 text-2xl font-bold tabular-nums text-foreground">
                            {{ apercu.nb_credit }}
                        </p>
                    </div>
                    <div
                        class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-card"
                    >
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                            NB Débit
                        </p>
                        <p class="mt-1 text-2xl font-bold tabular-nums text-foreground">
                            {{ apercu.nb_debit }}
                        </p>
                    </div>
                    <div
                        class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-900 dark:bg-emerald-950/30"
                    >
                        <p
                            class="text-[11px] font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-300"
                        >
                            Total Crédit
                        </p>
                        <p
                            class="mt-1 text-xl font-bold tabular-nums text-emerald-800 dark:text-emerald-200"
                        >
                            {{ montantFmt(apercu.total_credit) }} {{ apercu.devise }}
                        </p>
                    </div>
                    <div
                        class="rounded-xl border border-rose-200 bg-rose-50 p-4 dark:border-rose-900 dark:bg-rose-950/30"
                    >
                        <p
                            class="text-[11px] font-semibold uppercase tracking-wider text-rose-700 dark:text-rose-300"
                        >
                            Total Débit
                        </p>
                        <p class="mt-1 text-xl font-bold tabular-nums text-rose-800 dark:text-rose-200">
                            {{ montantFmt(apercu.total_debit) }} {{ apercu.devise }}
                        </p>
                    </div>
                    <div
                        class="rounded-xl border p-4"
                        :class="
                            isEquilibre
                                ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-900 dark:bg-emerald-950/30'
                                : 'border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/30'
                        "
                    >
                        <p
                            class="text-[11px] font-semibold uppercase tracking-wider"
                            :class="
                                isEquilibre
                                    ? 'text-emerald-700 dark:text-emerald-300'
                                    : 'text-amber-700 dark:text-amber-300'
                            "
                        >
                            Différence
                        </p>
                        <p
                            class="mt-1 text-xl font-bold tabular-nums"
                            :class="
                                isEquilibre
                                    ? 'text-emerald-800 dark:text-emerald-200'
                                    : 'text-amber-800 dark:text-amber-200'
                            "
                        >
                            {{ montantFmt(difference) }} {{ apercu.devise }}
                        </p>
                        <p
                            v-if="isEquilibre"
                            class="mt-0.5 text-[10px] font-medium text-emerald-700 dark:text-emerald-400"
                        >
                            Équilibré
                        </p>
                        <p
                            v-else
                            class="mt-0.5 text-[10px] font-medium text-amber-700 dark:text-amber-400"
                        >
                            Débit − Crédit
                        </p>
                    </div>
                </div>
            </section>

            <!-- Aperçu CSV -->
            <section class="overflow-hidden rounded-2xl border border-border/80 bg-card shadow-sm">
                <div
                    class="flex items-center justify-between border-b border-border/80 px-5 py-4 sm:px-6"
                    :class="
                        hasMultipleApercuRows && !apercu.error
                            ? 'cursor-pointer select-none hover:bg-muted/40'
                            : ''
                    "
                    @click="
                        hasMultipleApercuRows && !apercu.error
                            ? (apercuExpanded = !apercuExpanded)
                            : undefined
                    "
                >
                    <div>
                        <h2 class="text-sm font-semibold text-foreground">
                            Aperçu du fichier d’intégration
                        </h2>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            {{ apercu.total_rows }} écriture(s) au total
                            <span v-if="hasMultipleApercuRows && !apercuExpanded && !apercu.error">
                                — replié
                            </span>
                        </p>
                    </div>
                    <Button
                        v-if="hasMultipleApercuRows && !apercu.error"
                        type="button"
                        variant="outline"
                        size="sm"
                        class="h-8 shrink-0 gap-1 border-slate-300 text-xs"
                        @click.stop="apercuExpanded = !apercuExpanded"
                    >
                        <ChevronUp v-if="apercuExpanded" class="size-4" />
                        <ChevronDown v-else class="size-4" />
                        {{ apercuExpanded ? 'Replier' : 'Déplier' }}
                    </Button>
                </div>

                <div v-if="apercu.error" class="px-5 py-4 text-sm text-red-700 dark:text-red-300 sm:px-6">
                    {{ apercu.error }}
                </div>

                <div v-else-if="apercuExpanded" class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 text-slate-600 dark:bg-muted/40 dark:text-muted-foreground">
                            <tr>
                                <th class="px-3 py-2.5 font-semibold sm:px-4">Id</th>
                                <th class="px-3 py-2.5 font-semibold">Agence</th>
                                <th class="px-3 py-2.5 font-semibold">N° Compte</th>
                                <th class="px-3 py-2.5 font-semibold">Sens</th>
                                <th class="px-3 py-2.5 text-right font-semibold">Montant</th>
                                <th class="px-3 py-2.5 font-semibold">Code Op.</th>
                                <th class="px-3 py-2.5 font-semibold">Libellé écriture</th>
                                <th class="px-3 py-2.5 font-semibold sm:px-4">Date valeur</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr
                                v-for="(row, i) in apercu.rows"
                                :key="i"
                                class="hover:bg-muted/30"
                            >
                                <td class="px-3 py-2 sm:px-4">{{ row.numero || i + 1 }}</td>
                                <td class="px-3 py-2">{{ row.code_agence }}</td>
                                <td class="px-3 py-2 font-mono">{{ row.no_compte }}</td>
                                <td class="px-3 py-2">
                                    <span
                                        class="rounded px-1.5 py-0.5 text-[10px] font-semibold"
                                        :class="
                                            row.sens === 'C'
                                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                                                : 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300'
                                        "
                                    >
                                        {{
                                            row.sens === 'C'
                                                ? 'Crédit'
                                                : row.sens === 'D'
                                                  ? 'Débit'
                                                  : row.sens
                                        }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">
                                    {{ montantFmt(row.montant) }}
                                </td>
                                <td class="px-3 py-2">{{ row.code_operation }}</td>
                                <td class="px-3 py-2">{{ row.libelle_ecriture }}</td>
                                <td class="px-3 py-2 sm:px-4">{{ row.date_de_valeur }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p
                        v-if="apercu.total_rows > apercu.rows.length"
                        class="border-t border-border px-5 py-2 text-xs text-muted-foreground sm:px-6"
                    >
                        Aperçu limité aux {{ apercu.rows.length }} premières lignes.
                    </p>
                </div>
            </section>

            <!-- Pièces -->
            <section class="overflow-hidden rounded-2xl border border-border/80 bg-card shadow-sm">
                <div class="border-b border-border/80 px-5 py-4 sm:px-6">
                    <h2 class="text-sm font-semibold text-foreground">Pièces justificatives</h2>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        {{ classeur.pieces.length }} document(s) joint(s)
                    </p>
                </div>
                <ul class="divide-y divide-border">
                    <li
                        v-for="p in classeur.pieces"
                        :key="p.id"
                        class="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5 sm:px-6"
                    >
                        <div class="flex min-w-0 items-center gap-2.5 text-sm">
                            <div
                                class="flex size-8 shrink-0 items-center justify-center rounded-lg"
                                :class="
                                    p.is_piece_comptable
                                        ? 'bg-primary/10 text-primary'
                                        : 'bg-muted text-muted-foreground'
                                "
                            >
                                <FileText class="size-4" />
                            </div>
                            <div class="min-w-0">
                                <p class="truncate font-medium text-foreground">
                                    {{ p.description || p.original_name }}
                                </p>
                                <p class="truncate text-xs text-muted-foreground">
                                    {{ p.original_name }}
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <a
                                v-if="p.preview_url"
                                :href="p.preview_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
                                title="Visualiser"
                            >
                                <Eye class="size-3.5" /> Voir
                            </a>
                            <a
                                :href="p.url"
                                class="inline-flex items-center gap-1 text-xs font-medium text-muted-foreground hover:text-foreground"
                            >
                                <Download class="size-3.5" /> Télécharger
                            </a>
                        </div>
                    </li>
                    <li
                        v-if="!classeur.pieces.length"
                        class="px-5 py-8 text-center text-sm text-muted-foreground sm:px-6"
                    >
                        Aucune pièce jointe.
                    </li>
                </ul>
            </section>

            <!-- Actions -->
            <div
                class="sticky bottom-0 z-10 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-border/80 bg-card/95 px-5 py-4 shadow-sm backdrop-blur sm:px-6"
            >
                <Link
                    href="/operations-diverses/piece-comptable"
                    class="inline-flex items-center gap-1.5 text-sm text-muted-foreground transition hover:text-foreground"
                >
                    <ArrowLeft class="size-4" /> Nouvelle intégration
                </Link>

                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        v-if="isBrouillon"
                        href="/operations-diverses/integrations"
                        class="inline-flex h-9 items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 text-sm font-medium text-foreground hover:bg-slate-50 dark:border-slate-600 dark:bg-card dark:hover:bg-muted"
                    >
                        <FolderArchive class="size-4" /> Voir les brouillons
                    </Link>
                    <Link
                        v-else-if="isAttenteValidation"
                        href="/operations-diverses/attente-validation"
                        class="inline-flex h-9 items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 text-sm font-medium text-foreground hover:bg-slate-50 dark:border-slate-600 dark:bg-card dark:hover:bg-muted"
                    >
                        <UserCheck class="size-4" /> File d’attente
                    </Link>
                    <Link
                        v-else
                        href="/operations-diverses/archivage"
                        class="inline-flex h-9 items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 text-sm font-medium text-foreground hover:bg-slate-50 dark:border-slate-600 dark:bg-card dark:hover:bg-muted"
                    >
                        <FolderArchive class="size-4" /> Voir l’archivage
                    </Link>

                    <template v-if="isBrouillon">
                        <Link
                            v-if="classeur.modifier_url"
                            :href="classeur.modifier_url"
                            class="inline-flex h-9 items-center gap-1.5 rounded-md border border-primary/25 bg-white px-3 text-sm font-medium text-primary hover:bg-primary/5 dark:bg-card"
                        >
                            <Pencil class="size-4" /> Modifier
                        </Link>
                        <Button
                            v-if="classeur.supprimer_url"
                            type="button"
                            variant="outline"
                            class="h-9 border-red-200 text-red-700 hover:bg-red-50 hover:text-red-800 dark:border-red-900 dark:text-red-400 dark:hover:bg-red-950/40"
                            :disabled="deleting || processing"
                            @click="supprimer"
                        >
                            <Trash2 class="size-4" />
                            {{ deleting ? 'Suppression…' : 'Supprimer' }}
                        </Button>
                        <p
                            v-if="odIntegrationConfigured === false && classeur.can_integrate"
                            class="text-xs text-amber-700 dark:text-amber-300"
                        >
                            Le service d’intégration n’est pas disponible. Contactez le support.
                        </p>
                        <p
                            v-if="classeur.can_integrate && !(eligibleCheckers?.length ?? 0)"
                            class="text-xs text-amber-700 dark:text-amber-300"
                        >
                            Aucun autre agent du pôle
                            {{ checkerPole ?? 'Operations' }} disponible comme validateur.
                        </p>
                        <Button
                            v-if="classeur.can_integrate"
                            type="button"
                            class="h-9 bg-primary text-primary-foreground hover:bg-primary/90"
                            :disabled="processing || !(eligibleCheckers?.length ?? 0)"
                            :title="
                                (eligibleCheckers?.length ?? 0)
                                    ? 'Intégrer et désigner un checker'
                                    : 'Aucun checker disponible dans votre pôle'
                            "
                            @click="ouvrirIntegrer"
                        >
                            <ShieldCheck class="size-4" />
                            {{ processing ? 'Intégration…' : 'Intégrer (maker)' }}
                        </Button>
                    </template>

                    <template v-if="isAttenteValidation && classeur.can_validate_checker">
                        <Button
                            type="button"
                            class="h-9 bg-emerald-600 text-white hover:bg-emerald-700"
                            :disabled="processing"
                            @click="validerChecker"
                        >
                            <ShieldCheck class="size-4" />
                            {{ processing ? 'Validation…' : 'Valider et archiver (checker)' }}
                        </Button>
                    </template>
                    <p
                        v-if="isAttenteValidation && !classeur.can_validate_checker"
                        class="text-xs text-muted-foreground"
                    >
                        En attente de validation par
                        {{ classeur.assigned_checker_name ?? 'le checker désigné' }}.
                    </p>
                </div>
            </div>

            <Dialog v-model:open="showIntegrerModal">
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Intégrer le brouillon</DialogTitle>
                        <DialogDescription>
                            Choisissez votre validateur après enregistrement.
                        </DialogDescription>
                    </DialogHeader>
                    <div class="py-2">
                        <label
                            for="checker-resume"
                            class="mb-1.5 block text-sm font-medium text-foreground"
                        >
                            Validateur (checker)
                        </label>
                        <select
                            id="checker-resume"
                            v-model="selectedCheckerId"
                            :class="selectClass"
                        >
                            <option value="">Choisir un agent…</option>
                            <option
                                v-for="c in eligibleCheckers"
                                :key="c.id"
                                :value="String(c.id)"
                            >
                                {{ c.name }}
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
                            :disabled="processing"
                            @click="showIntegrerModal = false"
                        >
                            Annuler
                        </Button>
                        <Button
                            class="bg-primary text-primary-foreground hover:bg-primary/90"
                            :disabled="processing || !selectedCheckerId"
                            @click="confirmerIntegrer"
                        >
                            {{ processing ? 'Intégration…' : 'Confirmer l’intégration' }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
