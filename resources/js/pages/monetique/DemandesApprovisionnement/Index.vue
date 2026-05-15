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
import { Head, router, useForm } from '@inertiajs/vue3';
import { Building2, ClipboardList, Inbox, Package, User } from 'lucide-vue-next';
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

const statusMeta: Record<string, { label: string; badge: string }> = {
    en_attente: {
        label: 'En attente',
        badge: 'bg-amber-50 text-amber-950 border-amber-200',
    },
    transfert_en_cours: {
        label: 'Transfert en attente de réception',
        badge: 'bg-sky-50 text-sky-950 border-sky-200',
    },
    partielle: {
        label: 'Livraison partielle — suite possible',
        badge: 'bg-teal-50 text-teal-950 border-teal-200',
    },
    acceptee: {
        label: 'Satisfaite / clôturée',
        badge: 'bg-emerald-50 text-emerald-950 border-emerald-200',
    },
    refusee: {
        label: 'Refusée',
        badge: 'bg-rose-50 text-rose-950 border-rose-200',
    },
    annulee: {
        label: 'Annulée par l’agence',
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
        return 'Clôturée (reliquat non livré)';
    }
    return statusLabel(item.status);
}

const columns = [
    { key: 'id', title: 'N°' },
    { key: 'agence', title: 'Agence' },
    { key: 'chef', title: 'Chef d’agence' },
    { key: 'quantite', title: 'Quantité' },
    { key: 'date', title: 'Demandé le' },
    { key: 'statut', title: 'Statut' },
    { key: 'detail', title: 'Détail' },
    { key: 'actions', title: 'Actions' },
];

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
        <div class="flex flex-col gap-6 p-6 w-full">
            <div class="flex items-start gap-4">
                <div class="p-3.5 bg-violet-100 text-violet-700 rounded-2xl shadow-sm shrink-0">
                    <ClipboardList class="h-7 w-7" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Demandes des agences</h1>
                    <p class="text-sm text-gray-600 mt-1 max-w-2xl leading-relaxed">
                        Répondez par un <strong class="font-medium text-gray-800">refus motivé</strong> ou par un ou
                        plusieurs <strong class="font-medium text-gray-800">transferts depuis le stock siège</strong>. Vous
                        pouvez livrer moins que la quantité demandée, puis compléter plus tard ou clôturer la demande après
                        le prochain transfert réceptionné.
                    </p>
                    <p v-if="demandes.total" class="text-xs text-gray-500 mt-2">
                        {{ demandes.total }} demande(s) affichée(s) sur votre périmètre.
                    </p>
                </div>
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
                        <span class="inline-flex items-center gap-1.5 text-sm text-gray-900">
                            <Building2 class="h-3.5 w-3.5 text-violet-500 shrink-0" />
                            <span>
                                {{ item.agence_nom }}
                                <span v-if="item.agence_code" class="text-gray-500 text-xs">({{ item.agence_code }})</span>
                            </span>
                        </span>
                    </template>

                    <template #item.chef="{ item }">
                        <span class="inline-flex items-center gap-1.5 text-sm text-gray-700">
                            <User class="h-3.5 w-3.5 text-gray-400 shrink-0" />
                            {{ item.chef_nom }}
                        </span>
                    </template>

                    <template #item.quantite="{ item }">
                        <div class="text-sm text-gray-800 tabular-nums">
                            <div class="inline-flex items-center gap-1.5 font-semibold">
                                <Package class="h-3.5 w-3.5 text-gray-400" />
                                {{ item.quantite_demandee }} demandée(s)
                            </div>
                            <p class="text-xs text-gray-500 mt-0.5">{{ item.quantite_livree }} livrée(s) (réception validée)</p>
                        </div>
                    </template>

                    <template #item.date="{ item }">
                        <span class="text-sm text-gray-600 tabular-nums whitespace-nowrap">{{ item.created_at }}</span>
                    </template>

                    <template #item.statut="{ item }">
                        <span
                            :class="['inline-flex rounded-md border px-2 py-1 text-xs font-semibold', rowStatusBadge(item)]"
                        >
                            {{ rowStatusLabel(item) }}
                        </span>
                    </template>

                    <template #item.detail="{ item }">
                        <div class="max-w-xs text-sm text-gray-700">
                            <p v-if="item.commentaire" class="line-clamp-2" :title="item.commentaire ?? undefined">
                                {{ item.commentaire }}
                            </p>
                            <p v-else class="text-gray-400 italic">—</p>
                            <p v-if="item.bon_numero" class="text-xs font-mono text-violet-700 mt-1">
                                Bon {{ item.bon_numero }}
                            </p>
                        </div>
                    </template>

                    <template #item.actions="{ item }">
                        <div class="flex flex-wrap gap-2 justify-end">
                            <Button
                                v-if="item.can_create_transfer"
                                type="button"
                                size="sm"
                                class="bg-violet-600 hover:bg-violet-700"
                                @click="creerTransfert(item.id)"
                            >
                                {{ item.status === 'partielle' ? 'Compléter (transfert)' : 'Créer le transfert' }}
                            </Button>
                            <Button
                                v-if="item.status === 'en_attente'"
                                type="button"
                                size="sm"
                                variant="outline"
                                class="border-rose-200 text-rose-800 hover:bg-rose-50"
                                @click="refuser(item.id)"
                            >
                                Refuser
                            </Button>
                            <Button
                                v-if="item.transfer_id"
                                type="button"
                                size="sm"
                                variant="outline"
                                @click="router.visit(`/monetique/transferts/${item.transfer_id}`)"
                            >
                                Voir transfert
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
