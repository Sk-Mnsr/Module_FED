<script setup lang="ts">
import DataTable from '@/components/DataTable.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Calendar,
    LayoutList,
    ListPlus,
    Megaphone,
    Store,
    Target,
} from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Monétique', href: '/monetique/coficarte' },
    { title: 'Campagnes', href: '/monetique/campagnes' },
];

type Ag = { id: number; nom: string; code: string };
type Camp = {
    id: number;
    nom: string;
    agence: string;
    objectif_ventes: number;
    objectif_montant_recharges: number;
    date_debut: string;
    date_fin: string;
    active: boolean;
};

type Paginated = { data: Camp[]; current_page: number; per_page: number; total: number };

const props = withDefaults(
    defineProps<{
        campagnes?: Paginated;
        agences?: Ag[];
    }>(),
    {
        campagnes: () => ({ data: [], current_page: 1, per_page: 15, total: 0 }),
        agences: () => [],
    },
);

const form = useForm({
    nom: '',
    description: '',
    agence_id: '' as number | '',
    objectif_ventes: '' as number | '',
    objectif_montant_recharges: '' as number | '',
    date_debut: '',
    date_fin: '',
});

const submit = () => {
    form.transform((d) => ({
        ...d,
        agence_id: d.agence_id === '' ? null : Number(d.agence_id),
        objectif_ventes: Number(d.objectif_ventes),
        objectif_montant_recharges: Number(d.objectif_montant_recharges),
    })).post('/monetique/campagnes', { preserveScroll: true, onSuccess: () => form.reset() });
};

const formatCfa = (n: number) => `${n.toLocaleString('fr-FR')} F CFA`;

const selectClass =
    'mt-1.5 flex h-10 w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm ' +
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-500 focus-visible:ring-offset-2';

const columns = [
    { key: 'nom', title: 'Campagne' },
    { key: 'agence', title: 'Périmètre' },
    { key: 'objectif_ventes', title: 'Obj. ventes' },
    { key: 'objectif_montant_recharges', title: 'Obj. recharges' },
    { key: 'periode', title: 'Période' },
    { key: 'active', title: 'Statut' },
];

const onPageChange = (page: number) => {
    router.get('/monetique/campagnes', { per_page: props.campagnes.per_page, page }, { preserveState: true, replace: true });
};

const onItemsPerPageChange = (perPage: number) => {
    router.get('/monetique/campagnes', { per_page: perPage, page: 1 }, { preserveState: true, replace: true });
};
</script>

<template>
    <Head title="Campagnes Coficarte" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-8 p-6 max-w-6xl mx-auto w-full">
            <div class="flex items-start gap-4">
                <div class="p-3.5 bg-violet-100 text-violet-700 rounded-2xl shadow-sm shrink-0">
                    <Megaphone class="h-7 w-7" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Campagnes commerciales</h1>
                    <p class="text-sm text-gray-600 mt-1 max-w-2xl">
                        Créez des campagnes avec objectifs ventes et recharges ; elles s’affichent dans le
                        <strong class="font-medium text-gray-800">pilotage</strong> pour le suivi du mois.
                    </p>
                </div>
            </div>

            <div class="grid xl:grid-cols-5 gap-8 items-start">
                <!-- Formulaire -->
                <div class="xl:col-span-2 rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100 bg-violet-50/50 flex items-center gap-2">
                        <ListPlus class="h-5 w-5 text-violet-700" />
                        <h2 class="font-semibold text-gray-900">Nouvelle campagne</h2>
                    </div>
                    <form class="p-6 space-y-4" @submit.prevent="submit">
                        <div>
                            <Label for="nom" class="text-xs font-medium text-gray-600">Nom</Label>
                            <Input id="nom" v-model="form.nom" class="mt-1.5 border-gray-300" placeholder="Ex. Campagne Ramadan" />
                            <InputError :message="form.errors.nom" />
                        </div>
                        <div>
                            <Label for="agence_id" class="text-xs font-medium text-gray-600">Agence (vide = toutes)</Label>
                            <select id="agence_id" v-model="form.agence_id" :class="selectClass">
                                <option value="">Toutes les agences</option>
                                <option v-for="a in agences" :key="a.id" :value="a.id">{{ a.nom }} ({{ a.code }})</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <Label for="ov" class="text-xs font-medium text-gray-600">Objectif ventes</Label>
                                <Input id="ov" v-model.number="form.objectif_ventes" type="number" min="0" class="mt-1.5 border-gray-300" />
                                <InputError :message="form.errors.objectif_ventes" />
                            </div>
                            <div>
                                <Label for="om" class="text-xs font-medium text-gray-600">Objectif recharges (FCFA)</Label>
                                <Input id="om" v-model.number="form.objectif_montant_recharges" type="number" min="0" class="mt-1.5 border-gray-300" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <Label for="dd" class="text-xs font-medium text-gray-600">Début</Label>
                                <Input id="dd" v-model="form.date_debut" type="date" class="mt-1.5 border-gray-300" />
                                <InputError :message="form.errors.date_debut" />
                            </div>
                            <div>
                                <Label for="df" class="text-xs font-medium text-gray-600">Fin</Label>
                                <Input id="df" v-model="form.date_fin" type="date" class="mt-1.5 border-gray-300" />
                                <InputError :message="form.errors.date_fin" />
                            </div>
                        </div>
                        <Button type="submit" class="w-full sm:w-auto bg-violet-600 hover:bg-violet-700" :disabled="form.processing">
                            Créer la campagne
                        </Button>
                    </form>
                </div>

                <!-- Liste -->
                <div class="xl:col-span-3 rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden min-w-0">
                    <div class="px-5 py-4 border-b border-gray-100 bg-gray-50/80 flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <LayoutList class="h-5 w-5 text-gray-600" />
                            <h2 class="font-semibold text-gray-900">Campagnes enregistrées</h2>
                        </div>
                        <p v-if="campagnes.total" class="text-xs text-gray-500">{{ campagnes.total }} au total</p>
                    </div>
                    <DataTable
                        v-if="campagnes.data.length"
                        :headers="columns"
                        :items="campagnes.data"
                        :show-select="false"
                        :current-page="campagnes.current_page"
                        :items-per-page="campagnes.per_page"
                        :total-items="campagnes.total"
                        :on-page-change="onPageChange"
                        :on-items-per-page-change="onItemsPerPageChange"
                    >
                        <template #item.nom="{ item }">
                            <span class="flex items-center gap-2 font-medium text-gray-900">
                                <Target class="h-4 w-4 text-violet-500 shrink-0" />
                                {{ item.nom }}
                            </span>
                        </template>
                        <template #item.agence="{ item }">
                            <span class="inline-flex items-center gap-1.5 text-sm text-gray-700">
                                <Store class="h-3.5 w-3.5 text-gray-400 shrink-0" />
                                {{ item.agence }}
                            </span>
                        </template>
                        <template #item.objectif_ventes="{ item }">
                            <span class="tabular-nums text-sm font-medium text-gray-800">{{ item.objectif_ventes }}</span>
                        </template>
                        <template #item.objectif_montant_recharges="{ item }">
                            <span class="text-sm text-emerald-700 font-medium tabular-nums">{{
                                formatCfa(item.objectif_montant_recharges)
                            }}</span>
                        </template>
                        <template #item.periode="{ item }">
                            <span class="inline-flex items-center gap-1 text-xs text-gray-600 tabular-nums whitespace-nowrap">
                                <Calendar class="h-3.5 w-3.5 shrink-0" />
                                {{ item.date_debut }} → {{ item.date_fin }}
                            </span>
                        </template>
                        <template #item.active="{ item }">
                            <span
                                :class="[
                                    'inline-flex rounded-md border px-2 py-0.5 text-xs font-semibold',
                                    item.active
                                        ? 'bg-emerald-50 text-emerald-900 border-emerald-200'
                                        : 'bg-gray-100 text-gray-600 border-gray-200',
                                ]"
                            >
                                {{ item.active ? 'Active' : 'Inactive' }}
                            </span>
                        </template>
                    </DataTable>
                    <div v-else class="p-12 text-center text-sm text-gray-500">Aucune campagne pour l’instant.</div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
