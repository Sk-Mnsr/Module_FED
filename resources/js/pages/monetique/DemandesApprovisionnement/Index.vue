<script setup lang="ts">
import DataTable from '@/components/DataTable.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ArrowRightLeft, ClipboardList, Eye, Inbox, XCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Monétique', href: '/monetique/coficarte' },
    { title: 'Demandes agences', href: '/monetique/demandes-approvisionnement' },
];

type Row = {
    id: number;
    agence_nom: string;
    agence_code: string;
    chef_nom: string;
    quantite_demandee: number;
    quantite_livree: number;
    cloture_partielle: boolean;
    can_create_transfer: boolean;
    commentaire: string | null;
    reponse_monetique?: string | null;
    status: string;
    created_at: string;
    transfer_id: number | null;
    bon_numero: string | null;
    transfer_statut: string | null;
};

type Paginated = {
    data: Row[];
    current_page: number;
    per_page: number;
    total: number;
};

const props = withDefaults(defineProps<{ demandes?: Paginated }>(), {
    demandes: () => ({ data: [], current_page: 1, per_page: 20, total: 0 }),
});

const statusMeta: Record<string, { label: string; detail: string; badge: string }> = {
    en_attente: {
        label: 'En attente',
        detail: 'En attente de traitement par la monétique',
        badge: 'bg-amber-50 text-amber-950 border-amber-200',
    },
    transfert_en_cours: {
        label: 'En transfert',
        detail: 'Transfert envoyé, en attente de réception',
        badge: 'bg-sky-50 text-sky-950 border-sky-200',
    },
    partielle: {
        label: 'Partielle',
        detail: 'Livraison partielle, une suite reste possible',
        badge: 'bg-teal-50 text-teal-950 border-teal-200',
    },
    acceptee: {
        label: 'Satisfaite',
        detail: 'Demande satisfaite et clôturée',
        badge: 'bg-emerald-50 text-emerald-950 border-emerald-200',
    },
    refusee: {
        label: 'Refusée',
        detail: 'Demande refusée',
        badge: 'bg-rose-50 text-rose-950 border-rose-200',
    },
    annulee: {
        label: 'Annulée',
        detail: 'Annulée par l’agence',
        badge: 'bg-gray-100 text-gray-800 border-gray-200',
    },
};

const statusBadge = (s: string) => statusMeta[s]?.badge ?? 'bg-gray-50 text-gray-800 border-gray-200';
const statusLabel = (s: string) => statusMeta[s]?.label ?? s;

function rowStatusBadge(item: Row): string {
    if (item.status === 'acceptee' && item.cloture_partielle) {
        return 'bg-indigo-50 text-indigo-950 border-indigo-200';
    }
    return statusBadge(item.status);
}

function rowStatusLabel(item: Row): string {
    if (item.status === 'acceptee' && item.cloture_partielle) {
        return 'Reliquat';
    }
    return statusLabel(item.status);
}

function rowStatusDetail(item: Row): string {
    if (item.status === 'acceptee' && item.cloture_partielle) {
        return 'Clôturée avec un reliquat non livré';
    }
    return statusMeta[item.status]?.detail ?? rowStatusLabel(item);
}

const columns = [
    { key: 'id', title: 'N°' },
    { key: 'agence', title: 'Agence' },
    { key: 'chef', title: 'Chef' },
    { key: 'demandee', title: 'Demandé' },
    { key: 'livree', title: 'Livré' },
    { key: 'date', title: 'Date' },
    { key: 'statut', title: 'Statut' },
    { key: 'detail', title: 'Détail' },
    { key: 'actions', title: '' },
];

const page = usePage();
const flash = computed(() => page.props.flash as { success?: string; error?: string } | undefined);

const tableRows = computed(() =>
    props.demandes.data.map((d) => ({
        ...d,
        agence: d.agence_nom,
        chef: d.chef_nom,
        quantite: d.quantite_demandee,
        date: d.created_at,
        statut: d.status,
    })),
);

const refuseForm = useForm({ reponse_monetique: '' });
const refuserDialogOpen = ref(false);
const refuserTargetId = ref<number | null>(null);

const refuser = (id: number) => {
    refuserTargetId.value = id;
    refuseForm.reponse_monetique = '';
    refuseForm.clearErrors();
    refuserDialogOpen.value = true;
};

const onRefuserDialogOpenChange = (open: boolean) => {
    refuserDialogOpen.value = open;
    if (!open) {
        refuserTargetId.value = null;
        refuseForm.clearErrors();
    }
};

const submitRefus = () => {
    if (refuserTargetId.value === null) return;
    refuseForm.post(`/monetique/demandes-approvisionnement/${refuserTargetId.value}/refuser`, {
        preserveScroll: true,
        onSuccess: () => {
            onRefuserDialogOpenChange(false);
        },
    });
};

const creerTransfert = (id: number) => {
    router.visit(`/monetique/transferts/nouveau?supply_request_id=${id}`);
};

const listQuery = () => ({ per_page: props.demandes.per_page });

const onPageChange = (page: number) => {
    router.get('/monetique/demandes-approvisionnement', { ...listQuery(), page }, { preserveState: true, replace: true });
};

const onItemsPerPageChange = (perPage: number) => {
    router.get('/monetique/demandes-approvisionnement', { ...listQuery(), per_page: perPage, page: 1 }, {
        preserveState: true,
        replace: true,
    });
};
</script>

<template>
    <Head title="Demandes d’approvisionnement" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-5 p-6 w-full">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div class="flex items-start gap-3">
                    <div class="rounded-xl bg-violet-100 p-2.5 text-violet-700">
                        <ClipboardList class="h-5 w-5" />
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-900">Demandes des agences</h1>
                        <p class="mt-0.5 text-sm text-gray-600">
                            Refusez avec un motif, ou livrez par un ou plusieurs transferts depuis le stock siège.
                        </p>
                    </div>
                </div>
                <p v-if="demandes.total" class="text-sm text-gray-500">{{ demandes.total }} demande(s)</p>
            </div>

            <div
                v-if="flash?.success"
                class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
            >
                {{ flash.success }}
            </div>
            <div
                v-if="flash?.error"
                class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900"
            >
                {{ flash.error }}
            </div>

            <div
                v-if="!demandes.data.length && demandes.total === 0"
                class="rounded-2xl border border-dashed border-gray-300 bg-gray-50/90 px-6 py-16 text-center"
            >
                <Inbox class="h-12 w-12 text-gray-300 mx-auto mb-4" />
                <h2 class="text-lg font-semibold text-gray-900">Aucune demande</h2>
                <p class="text-sm text-gray-600 mt-2 max-w-md mx-auto">
                    Les chefs d’agence verront ici leurs propres demandes ; la monétique centralise toutes les demandes
                    entrantes.
                </p>
            </div>

            <template v-else>
                <DataTable
                    :headers="columns"
                    :items="tableRows"
                    :show-select="false"
                    :current-page="demandes.current_page"
                    :items-per-page="demandes.per_page"
                    :total-items="demandes.total"
                    :on-page-change="onPageChange"
                    :on-items-per-page-change="onItemsPerPageChange"
                >
                    <template #item.id="{ item }">
                        <span class="font-mono text-sm font-semibold text-gray-900">#{{ item.id }}</span>
                    </template>

                    <template #item.agence="{ item }">
                        <span class="whitespace-nowrap text-sm text-gray-900">
                            {{ item.agence_nom }}
                            <span v-if="item.agence_code" class="text-xs text-gray-500">({{ item.agence_code }})</span>
                        </span>
                    </template>

                    <template #item.chef="{ item }">
                        <span class="whitespace-nowrap text-sm text-gray-700">{{ item.chef_nom }}</span>
                    </template>

                    <template #item.demandee="{ item }">
                        <span class="whitespace-nowrap text-sm font-medium tabular-nums text-gray-900">{{ item.quantite_demandee }}</span>
                    </template>

                    <template #item.livree="{ item }">
                        <span
                            class="whitespace-nowrap text-sm tabular-nums text-gray-700"
                            title="Réceptions validées"
                        >
                            {{ item.quantite_livree }}
                        </span>
                    </template>

                    <template #item.date="{ item }">
                        <span class="text-sm text-gray-600 tabular-nums whitespace-nowrap">{{ item.created_at }}</span>
                    </template>

                    <template #item.statut="{ item }">
                        <span
                            :class="['inline-flex whitespace-nowrap rounded-md border px-2 py-0.5 text-xs font-semibold', rowStatusBadge(item)]"
                            :title="rowStatusDetail(item)"
                        >
                            {{ rowStatusLabel(item) }}
                        </span>
                    </template>

                    <template #item.detail="{ item }">
                        <div class="max-w-[14rem] text-sm text-gray-700">
                            <p v-if="item.commentaire" class="truncate" :title="item.commentaire">
                                {{ item.commentaire }}
                            </p>
                            <p v-if="item.bon_numero" class="truncate font-mono text-xs text-violet-700" :title="item.bon_numero">
                                {{ item.bon_numero }}
                            </p>
                            <span v-if="!item.commentaire && !item.bon_numero" class="text-gray-400">—</span>
                        </div>
                    </template>

                    <template #item.actions="{ item }">
                        <div class="flex flex-nowrap items-center justify-end gap-1.5">
                            <Button
                                v-if="item.can_create_transfer"
                                type="button"
                                size="sm"
                                class="bg-violet-600 hover:bg-violet-700"
                                @click="creerTransfert(item.id)"
                            >
                                <ArrowRightLeft class="size-3.5" />
                                {{ item.status === 'partielle' ? 'Compléter' : 'Transfert' }}
                            </Button>
                            <Button
                                v-if="item.transfer_id"
                                type="button"
                                size="sm"
                                variant="outline"
                                class="bg-white"
                                @click="router.visit(`/monetique/transferts/${item.transfer_id}`)"
                            >
                                <Eye class="size-3.5" />
                                Voir
                            </Button>
                            <Button
                                v-if="item.status === 'en_attente'"
                                type="button"
                                size="sm"
                                variant="outline"
                                class="border-rose-200 bg-white text-rose-800 hover:bg-rose-50"
                                @click="refuser(item.id)"
                            >
                                <XCircle class="size-3.5" />
                                Refuser
                            </Button>
                        </div>
                    </template>
                </DataTable>
            </template>

            <Dialog :open="refuserDialogOpen" @update:open="onRefuserDialogOpenChange">
                <DialogContent class="sm:max-w-md border-rose-200/80 bg-rose-50/30">
                    <DialogHeader>
                        <DialogTitle class="text-rose-950">
                            Refus de la demande
                            <span v-if="refuserTargetId !== null" class="font-mono">#{{ refuserTargetId }}</span>
                        </DialogTitle>
                        <DialogDescription class="text-rose-900/80">
                            Le motif sera enregistré et visible côté agence.
                        </DialogDescription>
                    </DialogHeader>
                    <div class="space-y-2 py-1">
                        <Label for="motif-refus-demande" class="text-rose-950">Motif du refus</Label>
                        <textarea
                            id="motif-refus-demande"
                            v-model="refuseForm.reponse_monetique"
                            rows="4"
                            class="w-full rounded-lg border border-rose-200 bg-white px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-400/80"
                            placeholder="Expliquez la raison du refus…"
                        />
                        <p v-if="refuseForm.errors.reponse_monetique" class="text-xs text-rose-700">
                            {{ refuseForm.errors.reponse_monetique }}
                        </p>
                    </div>
                    <DialogFooter class="gap-2 sm:gap-0">
                        <Button type="button" variant="outline" class="bg-white" @click="onRefuserDialogOpenChange(false)">
                            Annuler
                        </Button>
                        <Button
                            type="button"
                            class="bg-rose-600 hover:bg-rose-700"
                            :disabled="refuseForm.processing"
                            @click="submitRefus"
                        >
                            Confirmer le refus
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
