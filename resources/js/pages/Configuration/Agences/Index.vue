<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import DataTable from '@/components/DataTable.vue';
import { MapPin, Pencil, Trash2, Plus } from 'lucide-vue-next';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';

type ChefCandidate = { id: number; name: string; email: string; agence_id: number | null };

type ZoneRow = {
    id: number;
    nom: string;
    code: string | null;
    responsables: Array<{ id: number; name: string; email: string }>;
    agences_count: number;
};

type UserMini = { id: number; name: string; email: string };

const props = defineProps<{
    agences: {
        data: Array<any>;
        current_page: number;
        per_page: number;
        total: number;
        last_page?: number;
    };
    sort: string;
    direction: 'asc' | 'desc';
    chefCandidates: ChefCandidate[];
    zones: ZoneRow[];
    responsableCandidates: UserMini[];
    /** Code Flex de l’entité siège (pas de chef d’agence). */
    siegeAgenceCode: string;
}>();

const isSiegeCode = (code: string | number | null | undefined) =>
    code !== null && code !== undefined && String(code) === String(props.siegeAgenceCode);

const listQuery = () => ({
    sort: props.sort,
    direction: props.direction,
    per_page: props.agences.per_page,
});

const onPageChange = (page: number) => {
    router.get('/agences', { ...listQuery(), page }, { preserveState: true, replace: true });
};

const onItemsPerPageChange = (perPage: number) => {
    router.get('/agences', { ...listQuery(), per_page: perPage, page: 1 }, { preserveState: true, replace: true });
};

const onSort = (column: string, direction: 'asc' | 'desc') => {
    router.get(
        '/agences',
        {
            sort: column,
            direction,
            per_page: props.agences.per_page,
            page: 1,
        },
        { preserveState: true, replace: true },
    );
};

const breadcrumbs = [
    { title: 'Configuration', href: '#' },
    { title: 'Entités', href: '/agences' },
];

const columns = [
    { key: 'code', title: 'Code', sortable: true },
    { key: 'nom', title: 'Nom', sortable: true },
    { key: 'zone', title: 'Zone', sortable: false },
    { key: 'chef_agence', title: "Chef d'agence", sortable: false },
    { key: 'actions', title: 'Actions' },
];

const showModal = ref(false);
const isEditing = ref(false);
const form = ref({
    id: null as number | null,
    code: '',
    nom: '',
    chef_agence_user_id: '' as number | '',
    zone_id: '' as number | '',
});

const showChefField = computed(() => form.value.code !== '' && !isSiegeCode(form.value.code));

watch(
    () => form.value.code,
    (code) => {
        if (isSiegeCode(code)) {
            form.value.chef_agence_user_id = '';
        }
    },
);

const openCreateModal = () => {
    isEditing.value = false;
    form.value = { id: null, code: '', nom: '', chef_agence_user_id: '', zone_id: '' };
    showModal.value = true;
};

const openEditModal = (agence: any) => {
    isEditing.value = true;
    form.value = {
        id: agence.id,
        code: agence.code,
        nom: agence.nom,
        chef_agence_user_id: agence.chef_agence_user_id ?? '',
        zone_id: agence.zone_id ?? '',
    };
    showModal.value = true;
};

const deleteAgence = (id: number) => {
    if (confirm('Supprimer cette agence ?')) {
        router.delete(`/agences/${id}`, { preserveScroll: true });
    }
};

const formPayload = () => {
    const c = form.value.chef_agence_user_id;
    const z = form.value.zone_id;
    const siege = isSiegeCode(form.value.code);
    return {
        code: form.value.code,
        nom: form.value.nom,
        chef_agence_user_id: siege ? null : c === '' || c === null ? null : Number(c),
        zone_id: z === '' || z === null ? null : Number(z),
    };
};

const submitForm = () => {
    const payload = formPayload();
    if (isEditing.value) {
        router.put(`/agences/${form.value.id}`, payload, {
            onSuccess: () => (showModal.value = false),
        });
    } else {
        router.post('/agences', payload, {
            onSuccess: () => (showModal.value = false),
        });
    }
};

/* ——— Zones ——— */
const showZoneModal = ref(false);
const zoneEditing = ref(false);
const zoneForm = ref({
    id: null as number | null,
    nom: '',
    code: '',
    responsable_user_ids: [] as number[],
});

const openZoneCreate = () => {
    zoneEditing.value = false;
    zoneForm.value = { id: null, nom: '', code: '', responsable_user_ids: [] };
    showZoneModal.value = true;
};

const openZoneEdit = (z: ZoneRow) => {
    zoneEditing.value = true;
    zoneForm.value = {
        id: z.id,
        nom: z.nom,
        code: z.code ?? '',
        responsable_user_ids: z.responsables.map((u) => u.id),
    };
    showZoneModal.value = true;
};

const toggleZoneResponsable = (userId: number) => {
    const ids = zoneForm.value.responsable_user_ids;
    const i = ids.indexOf(userId);
    if (i === -1) {
        ids.push(userId);
    } else {
        ids.splice(i, 1);
    }
};

const responsableChecked = (userId: number) => zoneForm.value.responsable_user_ids.includes(userId);

const submitZone = () => {
    const payload = {
        nom: zoneForm.value.nom,
        code: zoneForm.value.code.trim() === '' ? null : zoneForm.value.code.trim(),
        responsable_user_ids: zoneForm.value.responsable_user_ids,
    };
    if (zoneEditing.value && zoneForm.value.id !== null) {
        router.put(`/zones/${zoneForm.value.id}`, payload, {
            onSuccess: () => (showZoneModal.value = false),
        });
    } else {
        router.post('/zones', payload, {
            onSuccess: () => (showZoneModal.value = false),
        });
    }
};

const deleteZone = (z: ZoneRow) => {
    const msg =
        z.agences_count > 0
            ? `Supprimer la zone « ${z.nom} » ? Les ${z.agences_count} agence(s) rattachées n’auront plus de zone (non supprimées).`
            : `Supprimer la zone « ${z.nom} » ?`;
    if (confirm(msg)) {
        router.delete(`/zones/${z.id}`, { preserveScroll: true });
    }
};

const formatResponsables = (list: ZoneRow['responsables']) => {
    if (!list?.length) {
        return '—';
    }
    return list.map((u) => u.name).join(', ');
};
</script>

<template>
    <Head title="Gestion des Entités" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-10 p-6">
            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-2">
                        <MapPin class="h-5 w-5 text-purple-600" />
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">Zones</h2>
                            <p class="text-sm text-gray-500">
                                Chaque agence peut être rattachée à une zone. Définissez les
                                <strong class="font-medium text-gray-700">responsables de zone</strong> (une ou plusieurs personnes).
                            </p>
                        </div>
                    </div>
                    <Button class="shrink-0 bg-purple-600 hover:bg-purple-700" @click="openZoneCreate">
                        <Plus class="mr-2 h-4 w-4" />
                        Nouvelle zone
                    </Button>
                </div>

                <div v-if="zones.length === 0" class="rounded-lg border border-dashed border-gray-200 bg-gray-50/80 py-10 text-center text-sm text-gray-600">
                    Aucune zone. Créez-en une pour classer vos agences et désigner les responsables régionaux.
                </div>
                <div v-else class="overflow-x-auto rounded-lg border border-gray-100">
                    <table class="w-full min-w-[640px] text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <th class="px-4 py-3">Nom</th>
                                <th class="px-4 py-3">Code</th>
                                <th class="px-4 py-3">Responsables</th>
                                <th class="px-4 py-3 text-right tabular-nums">Agences</th>
                                <th class="px-4 py-3 w-28 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="z in zones" :key="z.id" class="hover:bg-gray-50/80">
                                <td class="px-4 py-3 font-medium text-gray-900">{{ z.nom }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ z.code || '—' }}</td>
                                <td class="px-4 py-3 text-gray-700 max-w-md">{{ formatResponsables(z.responsables) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-gray-700">{{ z.agences_count }}</td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex justify-end gap-1">
                                        <button
                                            type="button"
                                            class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900"
                                            @click="openZoneEdit(z)"
                                        >
                                            <Pencil class="h-4 w-4" />
                                        </button>
                                        <button
                                            type="button"
                                            class="inline-flex items-center justify-center rounded-md p-2 text-red-600 hover:bg-red-50 hover:text-red-700"
                                            @click="deleteZone(z)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section>
                <div class="mb-4 flex items-center justify-between">
                    <h1 class="text-2xl font-bold text-gray-900">Agences (entités)</h1>
                    <Button class="bg-purple-600 hover:bg-purple-700" @click="openCreateModal">
                        <Plus class="mr-2 h-4 w-4" />
                        Nouvelle entité
                    </Button>
                </div>

                <DataTable
                    :headers="columns"
                    :items="props.agences.data"
                    :current-page="props.agences.current_page"
                    :items-per-page="props.agences.per_page"
                    :total-items="props.agences.total"
                    :show-select="false"
                    :on-page-change="onPageChange"
                    :on-items-per-page-change="onItemsPerPageChange"
                    :on-sort="onSort"
                    :server-sort-column="props.sort"
                    :server-sort-direction="props.direction"
                >
                    <template #item.zone="{ item }">
                        <span class="text-sm text-gray-700">
                            {{ item.zone?.nom ?? '—' }}
                            <span v-if="item.zone?.code" class="text-gray-400 tabular-nums"> ({{ item.zone.code }})</span>
                        </span>
                    </template>
                    <template #item.chef_agence="{ item }">
                        <span v-if="isSiegeCode(item.code)" class="text-sm text-gray-500 italic">
                            Siège (niveau directions)
                        </span>
                        <span v-else class="text-sm text-gray-700">
                            {{ item.chef_agence?.name ?? '—' }}
                        </span>
                    </template>
                    <template #item.actions="{ item }">
                        <div class="flex items-center gap-1">
                            <button
                                type="button"
                                class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900"
                                @click="openEditModal(item)"
                            >
                                <Pencil class="h-4 w-4" />
                            </button>
                            <button
                                type="button"
                                class="inline-flex items-center justify-center rounded-md p-2 text-red-600 hover:bg-red-50 hover:text-red-700"
                                @click="deleteAgence(item.id)"
                            >
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>
                    </template>
                </DataTable>
            </section>
        </div>

        <Dialog :open="showModal" @update:open="showModal = $event">
            <DialogContent class="sm:max-w-lg max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{{ isEditing ? "Modifier l'entité" : 'Ajouter une entité' }}</DialogTitle>
                </DialogHeader>
                <form class="space-y-4 py-4" @submit.prevent="submitForm">
                    <div class="space-y-2">
                        <Label for="code">Code</Label>
                        <Input id="code" v-model="form.code" required placeholder="Ex: AGC001" />
                    </div>
                    <div class="space-y-2">
                        <Label for="nom">Nom</Label>
                        <Input id="nom" v-model="form.nom" required placeholder="Nom de l'agence" />
                    </div>
                    <div class="space-y-2">
                        <Label for="zone_id">Zone</Label>
                        <select
                            id="zone_id"
                            v-model="form.zone_id"
                            class="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-sm shadow-sm focus-visible:border-purple-500 focus-visible:ring-2 focus-visible:ring-purple-500/20 focus-visible:outline-none"
                        >
                            <option value="">— Aucune zone —</option>
                            <option v-for="z in zones" :key="z.id" :value="z.id">
                                {{ z.nom }}<template v-if="z.code"> ({{ z.code }})</template>
                            </option>
                        </select>
                        <p class="text-xs text-gray-500">Rattachement régional pour pilotage et organismes de zone.</p>
                    </div>
                    <div v-if="showChefField" class="space-y-2">
                        <Label for="chef_agence_user_id">Chef d'agence</Label>
                        <select
                            id="chef_agence_user_id"
                            v-model="form.chef_agence_user_id"
                            class="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-sm shadow-sm focus-visible:border-purple-500 focus-visible:ring-2 focus-visible:ring-purple-500/20 focus-visible:outline-none"
                        >
                            <option value="">Aucun (à désigner plus tard)</option>
                            <option v-for="u in chefCandidates" :key="u.id" :value="u.id">
                                {{ u.name }} — {{ u.email }}
                            </option>
                        </select>
                        <p class="text-xs text-gray-500">
                            Seuls les utilisateurs avec le rôle « Chef d'agence CA » sont listés. Un même utilisateur ne peut être chef
                            que d'une seule agence.
                        </p>
                    </div>
                    <p
                        v-else-if="form.code !== '' && isSiegeCode(form.code)"
                        class="rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-600"
                    >
                        Le siège (code {{ siegeAgenceCode }}) regroupe les directions : il n’y a pas de chef d’agence sur cette entité.
                    </p>

                    <DialogFooter>
                        <Button type="button" variant="outline" @click="showModal = false">Annuler</Button>
                        <Button type="submit" class="bg-purple-600 hover:bg-purple-700">Enregistrer</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog :open="showZoneModal" @update:open="showZoneModal = $event">
            <DialogContent class="sm:max-w-lg max-h-[min(90vh,720px)] overflow-y-auto flex flex-col">
                <DialogHeader>
                    <DialogTitle>{{ zoneEditing ? 'Modifier la zone' : 'Nouvelle zone' }}</DialogTitle>
                </DialogHeader>
                <form class="flex flex-col gap-4 py-4 min-h-0" @submit.prevent="submitZone">
                    <div class="space-y-2">
                        <Label for="zone_nom">Nom de la zone</Label>
                        <Input id="zone_nom" v-model="zoneForm.nom" required placeholder="Ex. Zone Dakar" />
                    </div>
                    <div class="space-y-2">
                        <Label for="zone_code">Code <span class="font-normal text-gray-400">(optionnel)</span></Label>
                        <Input id="zone_code" v-model="zoneForm.code" placeholder="Ex. Z-DKR" />
                    </div>
                    <div class="flex min-h-0 flex-1 flex-col gap-2">
                        <Label>Responsables de zone</Label>
                        <p class="text-xs text-gray-500">Sélectionnez une ou plusieurs personnes (tous les utilisateurs de l’application).</p>
                        <div
                            class="max-h-52 overflow-y-auto rounded-md border border-gray-200 bg-gray-50/50 p-2 text-sm space-y-1"
                        >
                            <label
                                v-for="u in responsableCandidates"
                                :key="u.id"
                                class="flex cursor-pointer items-start gap-2 rounded px-2 py-1.5 hover:bg-white"
                            >
                                <input
                                    type="checkbox"
                                    class="mt-1 rounded border-gray-300"
                                    :checked="responsableChecked(u.id)"
                                    @change="toggleZoneResponsable(u.id)"
                                />
                                <span>
                                    <span class="font-medium text-gray-900">{{ u.name }}</span>
                                    <span class="block text-xs text-gray-500 break-all">{{ u.email }}</span>
                                </span>
                            </label>
                        </div>
                    </div>
                    <DialogFooter class="sm:justify-end">
                        <Button type="button" variant="outline" @click="showZoneModal = false">Annuler</Button>
                        <Button type="submit" class="bg-purple-600 hover:bg-purple-700">Enregistrer</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
