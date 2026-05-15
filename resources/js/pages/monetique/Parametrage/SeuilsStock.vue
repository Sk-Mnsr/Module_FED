<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { Building2, Info, Sliders, Target, Warehouse } from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Monétique', href: '/monetique/coficarte' },
    { title: 'Seuils & objectifs', href: '/monetique/parametrage/seuils-stock' },
];

type SeuilAgence = {
    id: number | null;
    agence_id: number;
    agence_nom: string;
    min_cards: number;
    objectif_nb_ventes_mois: number;
    objectif_montant_recharges_mois: number;
};

const props = defineProps<{
    min_stock_central: number;
    objectif_nb_ventes_central: number;
    objectif_montant_recharges_central: number;
    seuils_agences: SeuilAgence[];
}>();

const form = useForm({
    min_stock_central: props.min_stock_central,
    objectif_nb_ventes_central: props.objectif_nb_ventes_central,
    objectif_montant_recharges_central: props.objectif_montant_recharges_central,
    agences: props.seuils_agences.map((s) => ({
        agence_id: s.agence_id,
        min_cards: s.min_cards,
        objectif_nb_ventes_mois: s.objectif_nb_ventes_mois,
        objectif_montant_recharges_mois: s.objectif_montant_recharges_mois,
    })),
});

const submit = () => {
    form.put('/monetique/parametrage/seuils-stock', { preserveScroll: true });
};
</script>

<template>
    <Head title="Seuils & objectifs" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6 pb-28 max-w-5xl mx-auto w-full">
            <div class="flex items-start gap-4">
                <div class="p-3.5 bg-violet-100 text-violet-700 rounded-2xl shadow-sm shrink-0">
                    <Sliders class="h-7 w-7" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Seuils d’alerte & objectifs (mois en cours)</h1>
                    <p class="text-sm text-gray-600 mt-1 max-w-2xl">
                        <strong>Seuils</strong> : alerte stock carte lorsque l’on passe sous le minimum.
                        <strong class="font-semibold text-gray-800">Objectifs</strong> : cibles mensuelles reseau (siège) et par
                        agence pour les ventes encaissées (nombre de cartes vendues) et le montant des recharges encaissées
                        (FCFA). Toute valeur à <strong>0</strong> désactive l’objectif ou le seuil selon la ligne.
                    </p>
                </div>
            </div>

            <div
                class="flex gap-3 rounded-xl border border-amber-200/80 bg-amber-50/90 px-4 py-3.5 text-sm text-amber-950"
                role="status"
            >
                <Info class="h-5 w-5 shrink-0 text-amber-700 mt-0.5" />
                <p>
                    Les <strong>objectifs au siège</strong> s’appliquent au <strong>réseau entier</strong> sur le mois civil en
                    cours (agrégat pilotage). Les colonnes par agence ciblent uniquement l’activité de chaque entité. Les
                    indicateurs utilisés sont les mêmes que sur la page <strong>Pilotage</strong> (ventes / recharges
                    <strong>encaissées</strong>).
                </p>
            </div>

            <form class="rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden flex flex-col" @submit.prevent="submit">
                <div class="p-6 border-b border-gray-100 bg-gradient-to-br from-violet-50/80 to-white space-y-6">
                    <div class="flex items-center gap-2 mb-1">
                        <Warehouse class="h-5 w-5 text-violet-700" />
                        <h2 class="font-semibold text-gray-900">Stock siège (central)</h2>
                    </div>
                    <div class="grid sm:grid-cols-1 md:grid-cols-3 gap-5">
                        <div>
                            <Label for="central" class="text-xs font-medium text-gray-600">Seuil minimum cartes au siège</Label>
                            <Input
                                id="central"
                                v-model.number="form.min_stock_central"
                                type="number"
                                min="0"
                                class="mt-1.5 border-gray-300 shadow-sm"
                            />
                        </div>
                    </div>

                    <div class="rounded-xl border border-teal-100 bg-teal-50/50 p-5 space-y-4">
                        <div class="flex items-center gap-2">
                            <Target class="h-5 w-5 text-teal-700" />
                            <h3 class="font-semibold text-gray-900 text-sm">Objectifs réseau (mois en cours)</h3>
                        </div>
                        <p class="text-xs text-gray-600">
                            Comparés aux totaux globaux du pilotage (toutes agences confondues).
                        </p>
                        <div class="grid sm:grid-cols-2 gap-5">
                            <div>
                                <Label for="obj-v-central" class="text-xs font-medium text-gray-600">Objectif ventes (nb cartes)</Label>
                                <Input
                                    id="obj-v-central"
                                    v-model.number="form.objectif_nb_ventes_central"
                                    type="number"
                                    min="0"
                                    class="mt-1.5 border-gray-300 shadow-sm"
                                />
                            </div>
                            <div>
                                <Label for="obj-r-central" class="text-xs font-medium text-gray-600"
                                    >Objectif recharges (FCFA / mois)</Label
                                >
                                <Input
                                    id="obj-r-central"
                                    v-model.number="form.objectif_montant_recharges_central"
                                    type="number"
                                    min="0"
                                    step="1"
                                    class="mt-1.5 border-gray-300 shadow-sm tabular-nums"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/60 flex items-center gap-2 flex-wrap">
                    <Building2 class="h-5 w-5 text-gray-600" />
                    <h2 class="font-semibold text-gray-900">Par agence</h2>
                    <span class="text-xs text-gray-500 ml-auto">{{ form.agences.length }} ligne(s)</span>
                </div>

                <div class="overflow-x-auto max-h-[min(58vh,520px)] overflow-y-auto">
                    <table class="w-full text-sm min-w-[720px]">
                        <thead class="sticky top-0 z-10 bg-gray-100/95 backdrop-blur border-b border-gray-200 shadow-sm">
                            <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                <th class="px-4 py-3">Agence</th>
                                <th class="px-4 py-3 text-right w-36">Seuil min. (cartes)</th>
                                <th class="px-4 py-3 text-right w-36">Objectif ventes (mois)</th>
                                <th class="px-4 py-3 text-right w-44">Objectif recharges (FCFA)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="(row, i) in form.agences" :key="row.agence_id" class="hover:bg-violet-50/40 transition-colors">
                                <td class="px-4 py-2.5 text-gray-900 font-medium">
                                    {{
                                        seuils_agences.find((s) => s.agence_id === row.agence_id)?.agence_nom
                                            ?? `Agence #${row.agence_id}`
                                    }}
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    <Input
                                        v-model.number="form.agences[i].min_cards"
                                        type="number"
                                        min="0"
                                        class="inline-block w-28 border-gray-300 text-right tabular-nums"
                                    />
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    <Input
                                        v-model.number="form.agences[i].objectif_nb_ventes_mois"
                                        type="number"
                                        min="0"
                                        class="inline-block w-28 border-gray-300 text-right tabular-nums"
                                    />
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    <Input
                                        v-model.number="form.agences[i].objectif_montant_recharges_mois"
                                        type="number"
                                        min="0"
                                        step="1"
                                        class="inline-block w-36 border-gray-300 text-right tabular-nums"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="sticky bottom-0 border-t border-gray-200 bg-gray-50/95 backdrop-blur px-6 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <p class="text-xs text-gray-500">Enregistrez après modification (seuils + objectifs).</p>
                    <Button type="submit" class="bg-violet-600 hover:bg-violet-700 min-w-[200px]" :disabled="form.processing">
                        {{ form.processing ? 'Enregistrement…' : 'Enregistrer' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
