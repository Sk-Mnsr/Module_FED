<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { formatCardNumberDisplay } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Banknote, FileText, Package, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Monétique', href: '/monetique/coficarte' },
    { title: 'Approvisionnement CC', href: '/monetique/agence/approvisionnement-cc' },
];

type CC = { id: number; name: string; email: string | null };
type Carte = { id: number; numero_carte: string; reference_facture: string; prix_vente: number };

const props = withDefaults(
    defineProps<{
        chargeClientele?: CC[];
        cartes?: Carte[];
    }>(),
    {
        chargeClientele: () => [],
        cartes: () => [],
    },
);

const assignToUserId = ref<number | ''>('');
const selected = ref<number[]>([]);

const toggle = (id: number) => {
    const i = selected.value.indexOf(id);
    if (i === -1) {
        selected.value = [...selected.value, id];
    } else {
        selected.value = selected.value.filter((x) => x !== id);
    }
};

const allSelected = computed(
    () => props.cartes.length > 0 && selected.value.length === props.cartes.length,
);

const toggleAll = () => {
    const ids = props.cartes.map((c) => c.id);
    selected.value = allSelected.value ? [] : [...ids];
};

const form = useForm({
    assign_to_user_id: null as number | null,
    coficarte_card_ids: [] as number[],
});

const submit = () => {
    if (assignToUserId.value === '' || assignToUserId.value === null) {
        return;
    }
    form.assign_to_user_id = Number(assignToUserId.value);
    form.coficarte_card_ids = [...selected.value];
    if (form.coficarte_card_ids.length === 0) {
        return;
    }
    form.post('/monetique/agence/approvisionnement-cc', { preserveScroll: true });
};

const formatCfa = (n: number) => `${n.toLocaleString('fr-FR')} F CFA`;

const selectClass =
    'mt-1.5 flex h-11 w-full max-w-xl rounded-md border border-gray-300 bg-white px-3 text-sm shadow-sm ' +
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2';
</script>

<template>
    <Head title="Approvisionnement CC - Chef d'agence" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-8 p-6 max-w-5xl mx-auto w-full pb-28">
            <button
                type="button"
                class="inline-flex items-center gap-2 text-sm font-medium text-violet-700 hover:text-violet-900 w-fit -ml-1"
                @click="router.visit('/monetique/coficarte')"
            >
                ← Coficarte
            </button>

            <div class="flex items-start gap-4">
                <div class="p-3.5 bg-emerald-100 text-emerald-900 rounded-2xl shadow-sm shrink-0">
                    <Users class="h-7 w-7" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Approvisionnement des chargés de clientèle</h1>
                    <p class="text-sm text-gray-600 mt-1 max-w-2xl leading-relaxed">
                        Sélectionnez un <strong class="font-medium text-gray-800">chargé de clientèle</strong>, puis les cartes du
                        <strong class="font-medium text-gray-800">pool agence</strong> (non encore affectées) à lui attribuer.
                    </p>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white shadow-sm p-6 space-y-2">
                <Label for="cc" class="text-sm font-semibold text-gray-800">Chargé de clientèle destinataire</Label>
                <select id="cc" v-model="assignToUserId" :class="selectClass">
                    <option value="">— Sélectionner —</option>
                    <option v-for="u in chargeClientele" :key="u.id" :value="u.id">
                        {{ u.name }}{{ u.email ? ` — ${u.email}` : '' }}
                    </option>
                </select>
                <p v-if="chargeClientele.length === 0" class="text-sm text-amber-800 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2 mt-2">
                    Aucun utilisateur « Chargé de clientèle » sur votre agence. Paramétrez les comptes dans la configuration.
                </p>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                <div class="px-5 py-4 bg-gradient-to-r from-emerald-50/90 to-white border-b border-emerald-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="flex items-start gap-3">
                        <div class="p-2 rounded-lg bg-white border border-emerald-100 text-emerald-700 shrink-0">
                            <Package class="h-5 w-5" />
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-900">
                                Pool agence — <span class="tabular-nums text-emerald-800">{{ cartes.length }}</span> carte(s)
                                disponible(s)
                            </p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                <span class="tabular-nums font-medium text-gray-700">{{ selected.length }}</span> sélectionnée(s)
                            </p>
                        </div>
                    </div>
                    <Button v-if="cartes.length" type="button" variant="outline" size="sm" class="bg-white shrink-0" @click="toggleAll">
                        {{ allSelected ? 'Tout désélectionner' : 'Tout sélectionner' }}
                    </Button>
                </div>

                <div v-if="!cartes.length" class="px-6 py-14 text-center text-sm text-gray-500">
                    Aucune carte dans le pool agence (non affectée à un CC).
                </div>

                <div v-else class="overflow-x-auto max-h-[min(52vh,480px)] overflow-y-auto">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 z-10 bg-gray-100/95 backdrop-blur border-b border-gray-200">
                            <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                <th class="w-12 px-4 py-3"></th>
                                <th class="px-4 py-3">Carte</th>
                                <th class="px-4 py-3">Facture</th>
                                <th class="px-4 py-3 text-right">Prix affiché</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr
                                v-for="c in cartes"
                                :key="c.id"
                                class="hover:bg-emerald-50/25 transition-colors"
                                :class="selected.includes(c.id) ? 'bg-emerald-50/40' : ''"
                            >
                                <td class="px-4 py-3 align-middle">
                                    <Checkbox
                                        :checked="selected.includes(c.id)"
                                        class="cursor-pointer"
                                        @update:checked="() => toggle(c.id)"
                                    />
                                </td>
                                <td class="px-4 py-3 font-mono font-semibold text-gray-900 tabular-nums">
                                    {{ formatCardNumberDisplay(c.numero_carte) }}
                                </td>
                                <td class="px-4 py-3 text-gray-600">
                                    <span class="inline-flex items-center gap-1">
                                        <FileText class="h-3.5 w-3.5 text-gray-400 shrink-0" />
                                        {{ c.reference_facture }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right font-medium text-emerald-800 tabular-nums">
                                    <span class="inline-flex items-center justify-end gap-1">
                                        <Banknote class="h-3.5 w-3.5 opacity-70" />
                                        {{ formatCfa(c.prix_vente) }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div
                class="sticky bottom-0 z-10 -mx-2 px-2 py-4 bg-white/95 backdrop-blur border-t border-gray-200 sm:border-0 sm:bg-transparent sm:static sm:px-0 flex flex-col sm:flex-row sm:justify-end gap-2"
            >
                <Button
                    class="bg-emerald-700 hover:bg-emerald-800 w-full sm:w-auto min-w-[220px]"
                    :disabled="
                        assignToUserId === '' || selected.length === 0 || chargeClientele.length === 0 || form.processing
                    "
                    @click="submit"
                >
                    Valider l’approvisionnement
                    <span v-if="selected.length" class="ml-1 tabular-nums opacity-90">({{ selected.length }})</span>
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
