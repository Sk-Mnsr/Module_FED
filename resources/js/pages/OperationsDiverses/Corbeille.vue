<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import OdActionIcon from '@/components/OdActionIcon.vue';
import OdConfirmDialog from '@/components/OdConfirmDialog.vue';
import {
    CalendarDays,
    Clock,
    Hash,
    RotateCcw,
    Trash2,
    User,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

type ClasseurRow = {
    id: number;
    nom_classeur: string;
    numero_batch: string;
    statut: string;
    date_valeur: string | null;
    maker_name: string | null;
    deleted_by_name: string | null;
    deleted_at: string | null;
    justificatifs_count: number;
    restaurer_url: string;
};

const props = defineProps<{
    classeurs?: ClasseurRow[];
}>();

const breadcrumbs = [
    { title: 'Opérations diverses', href: '/operations-diverses/piece-comptable' },
    { title: 'Corbeille', href: '/operations-diverses/corbeille' },
];

const page = usePage();
const flash = computed(() => page.props.flash as { success?: string; error?: string; warning?: string } | undefined);
const restoreEnCours = ref<number | null>(null);
const showRestoreConfirm = ref(false);
const restoreTarget = ref<ClasseurRow | null>(null);
const total = computed(() => props.classeurs?.length ?? 0);

const statutLabel: Record<string, string> = {
    brouillon: 'Brouillon',
    attente_validation: 'Attente validation',
    integre: 'Archivé',
};

function restaurer(c: ClasseurRow) {
    if (restoreEnCours.value !== null) return;
    restoreTarget.value = c;
    showRestoreConfirm.value = true;
}

function confirmerRestaurer() {
    const c = restoreTarget.value;
    if (!c || restoreEnCours.value !== null) return;
    restoreEnCours.value = c.id;
    router.post(
        c.restaurer_url,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                restoreEnCours.value = null;
                showRestoreConfirm.value = false;
                restoreTarget.value = null;
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
</script>

<template>
    <Head title="Corbeille OD — SuperAdmin" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 sm:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <div class="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <Trash2 class="size-5" />
                        </div>
                        <div>
                            <h1 class="text-xl font-semibold tracking-tight text-foreground">
                                Corbeille
                            </h1>
                            <p class="text-sm text-muted-foreground">
                                Intégrations OD supprimées — restauration SuperAdmin uniquement.
                            </p>
                        </div>
                    </div>
                </div>
                <div
                    class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700"
                >
                    {{ total }} élément{{ total > 1 ? 's' : '' }}
                </div>
            </div>

            <div
                v-if="flash?.success"
                class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
            >
                {{ flash.success }}
            </div>
            <div
                v-if="flash?.warning"
                class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
            >
                {{ flash.warning }}
            </div>
            <div
                v-if="flash?.error"
                class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
            >
                {{ flash.error }}
            </div>

            <section class="rounded-xl border border-border bg-card shadow-sm">
                <div v-if="!classeurs?.length" class="px-6 py-16 text-center">
                    <Trash2 class="mx-auto size-10 text-muted-foreground/40" />
                    <p class="mt-3 text-sm font-medium text-foreground">Corbeille vide</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Aucune intégration en corbeille pour le moment.
                    </p>
                </div>

                <ul v-else class="divide-y divide-border">
                    <li
                        v-for="c in classeurs"
                        :key="c.id"
                        class="flex flex-col gap-4 px-4 py-4 sm:px-6 lg:flex-row lg:items-center lg:justify-between"
                    >
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="rounded-full bg-red-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-red-700"
                                >
                                    Corbeille
                                </span>
                                <span
                                    class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-600"
                                >
                                    {{ statutLabel[c.statut] ?? c.statut }}
                                </span>
                                <h2 class="truncate text-base font-semibold text-foreground">
                                    {{ c.nom_classeur }}
                                </h2>
                            </div>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ c.justificatifs_count }} justificatif(s)
                            </p>
                            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs">
                                <span class="inline-flex items-center gap-1.5 text-muted-foreground">
                                    <Hash class="size-3.5 text-primary" />
                                    {{ c.numero_batch }}
                                </span>
                                <span class="inline-flex items-center gap-1.5 text-muted-foreground">
                                    <CalendarDays class="size-3.5 text-primary" />
                                    {{ dateFmt(c.date_valeur) }}
                                </span>
                                <span class="inline-flex items-center gap-1.5 text-muted-foreground">
                                    <User class="size-3.5 text-primary" />
                                    Maker : {{ c.maker_name ?? '—' }}
                                </span>
                                <span class="inline-flex items-center gap-1.5 text-muted-foreground">
                                    <Trash2 class="size-3.5 text-primary" />
                                    Supprimé par : {{ c.deleted_by_name ?? '—' }}
                                </span>
                                <span class="inline-flex items-center gap-1.5 text-muted-foreground">
                                    <Clock class="size-3.5 text-primary" />
                                    {{ horodatage(c.deleted_at) }}
                                </span>
                            </div>
                        </div>

                        <div
                            class="inline-flex shrink-0 items-center gap-0.5 self-end rounded-2xl border border-slate-200/90 bg-slate-50/90 p-1 shadow-sm dark:border-slate-700 dark:bg-slate-900/40 lg:self-center"
                            role="group"
                            aria-label="Actions"
                        >
                            <OdActionIcon
                                :icon="RotateCcw"
                                label="Restaurer"
                                variant="success"
                                :disabled="restoreEnCours === c.id"
                                :loading="restoreEnCours === c.id"
                                @click="restaurer(c)"
                            />
                        </div>
                    </li>
                </ul>
            </section>

            <OdConfirmDialog
                v-model:open="showRestoreConfirm"
                title="Restaurer"
                :description="
                    restoreTarget
                        ? `Voulez-vous vraiment restaurer « ${restoreTarget.nom_classeur} » ?`
                        : ''
                "
                confirm-label="Restaurer"
                variant="success"
                :loading="restoreEnCours !== null"
                @confirm="confirmerRestaurer"
            />
        </div>
    </AppLayout>
</template>
