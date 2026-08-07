<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Building2, Info, BarChart3, Sliders, Target, Warehouse } from 'lucide-vue-next';

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

const fieldClass =
    'mt-1.5 h-10 border-slate-300 bg-white text-slate-900 shadow-sm tabular-nums focus-visible:border-primary focus-visible:ring-primary/30 dark:border-slate-600 dark:bg-card dark:text-foreground';

const cellFieldClass =
    'inline-block h-9 w-28 border-slate-300 bg-white text-right text-sm tabular-nums shadow-sm focus-visible:border-primary focus-visible:ring-primary/30 dark:border-slate-600 dark:bg-card';

const submit = () => {
    form.put('/monetique/parametrage/seuils-stock', { preserveScroll: true });
};
</script>

<template>
    <Head title="Seuils & objectifs" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-5xl flex-col gap-4 p-4 pb-28 sm:p-6">
            <section
                class="overflow-hidden rounded-2xl border border-border/80 bg-card shadow-sm"
            >
                <div
                    class="border-b border-border/80 bg-gradient-to-r from-primary/5 via-card to-transparent px-5 py-5 sm:px-6 dark:from-primary/10"
                >
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="flex items-start gap-3">
                            <div
                                class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary text-primary-foreground shadow-sm"
                            >
                                <Sliders class="size-5" />
                            </div>
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-primary">
                                    Pilotage & paramètres
                                </p>
                                <h1 class="text-xl font-semibold tracking-tight text-foreground">
                                    Seuils d’alerte & objectifs
                                </h1>
                                <p class="mt-1 max-w-2xl text-sm text-muted-foreground">
                                    <strong class="text-foreground">Seuils</strong> : alerte stock carte sous le
                                    minimum.
                                    <strong class="text-foreground">Objectifs</strong> : cibles mensuelles réseau et
                                    par agence (ventes / recharges encaissées). Une valeur à
                                    <strong class="text-foreground">0</strong> désactive l’objectif ou le seuil.
                                </p>
                            </div>
                        </div>
                        <Button as-child variant="outline" class="border-slate-300">
                            <Link href="/monetique/pilotage" class="inline-flex items-center gap-2">
                                <BarChart3 class="size-4" />
                                Voir le pilotage
                            </Link>
                        </Button>
                    </div>
                </div>
            </section>

            <div
                class="flex gap-3 rounded-2xl border border-amber-200/80 bg-amber-50/90 px-4 py-3.5 text-sm text-amber-950"
                role="status"
            >
                <Info class="mt-0.5 size-5 shrink-0 text-amber-700" />
                <p>
                    Les <strong>objectifs au siège</strong> s’appliquent au <strong>réseau entier</strong> sur le mois
                    civil en cours (agrégat pilotage). Les colonnes par agence ciblent uniquement l’activité de chaque
                    entité — mêmes indicateurs que sur
                    <Link href="/monetique/pilotage" class="font-semibold text-primary underline-offset-2 hover:underline">
                        Pilotage
                    </Link>
                    (encaissées).
                </p>
            </div>

            <form
                class="flex flex-col overflow-hidden rounded-2xl border border-border/80 bg-card shadow-sm"
                @submit.prevent="submit"
            >
                <div
                    class="space-y-6 border-b border-border/80 bg-gradient-to-r from-primary/5 via-transparent to-transparent p-5 sm:p-6"
                >
                    <div class="flex items-center gap-2">
                        <div class="rounded-lg bg-primary p-2 text-primary-foreground shadow-sm">
                            <Warehouse class="size-4" />
                        </div>
                        <h2 class="font-semibold text-foreground">Stock siège (central)</h2>
                    </div>
                    <div class="max-w-sm">
                        <Label for="central" class="text-sm font-medium text-foreground">
                            Seuil minimum cartes au siège
                        </Label>
                        <Input
                            id="central"
                            v-model.number="form.min_stock_central"
                            type="number"
                            min="0"
                            :class="fieldClass"
                        />
                    </div>

                    <div class="space-y-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-border dark:bg-card">
                        <div class="flex items-center gap-2">
                            <div class="rounded-lg bg-primary p-2 text-primary-foreground shadow-sm">
                                <Target class="size-4" />
                            </div>
                            <div>
                                <h3 class="text-sm font-semibold text-foreground">
                                    Objectifs réseau (mois en cours)
                                </h3>
                                <p class="text-xs text-muted-foreground">
                                    Comparés aux totaux globaux du pilotage (toutes agences confondues).
                                </p>
                            </div>
                        </div>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <Label for="obj-v-central" class="text-sm font-medium text-foreground">
                                    Objectif ventes (nb cartes)
                                </Label>
                                <Input
                                    id="obj-v-central"
                                    v-model.number="form.objectif_nb_ventes_central"
                                    type="number"
                                    min="0"
                                    :class="fieldClass"
                                />
                            </div>
                            <div>
                                <Label for="obj-r-central" class="text-sm font-medium text-foreground">
                                    Objectif recharges (FCFA / mois)
                                </Label>
                                <Input
                                    id="obj-r-central"
                                    v-model.number="form.objectif_montant_recharges_central"
                                    type="number"
                                    min="0"
                                    step="1"
                                    :class="fieldClass"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    class="flex flex-wrap items-center gap-2 border-b border-border/80 bg-muted/30 px-5 py-4 sm:px-6"
                >
                    <Building2 class="size-5 text-primary" />
                    <h2 class="font-semibold text-foreground">Par agence</h2>
                    <span class="ml-auto text-xs text-muted-foreground">
                        {{ form.agences.length }} ligne{{ form.agences.length > 1 ? 's' : '' }}
                    </span>
                </div>

                <div class="max-h-[min(58vh,520px)] overflow-x-auto overflow-y-auto">
                    <table class="w-full min-w-[720px] text-sm">
                        <thead
                            class="sticky top-0 z-10 border-b border-border bg-muted/90 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground backdrop-blur-sm"
                        >
                            <tr>
                                <th class="px-4 py-3">Agence</th>
                                <th class="w-36 px-4 py-3 text-right">Seuil min. (cartes)</th>
                                <th class="w-36 px-4 py-3 text-right">Objectif ventes (mois)</th>
                                <th class="w-44 px-4 py-3 text-right">Objectif recharges (FCFA)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr
                                v-for="(row, i) in form.agences"
                                :key="row.agence_id"
                                class="transition-colors hover:bg-primary/5"
                            >
                                <td class="px-4 py-2.5 font-medium text-foreground">
                                    {{
                                        seuils_agences.find((s) => s.agence_id === row.agence_id)?.agence_nom ??
                                        `Agence #${row.agence_id}`
                                    }}
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    <Input
                                        v-model.number="form.agences[i].min_cards"
                                        type="number"
                                        min="0"
                                        :class="cellFieldClass"
                                    />
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    <Input
                                        v-model.number="form.agences[i].objectif_nb_ventes_mois"
                                        type="number"
                                        min="0"
                                        :class="cellFieldClass"
                                    />
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    <Input
                                        v-model.number="form.agences[i].objectif_montant_recharges_mois"
                                        type="number"
                                        min="0"
                                        step="1"
                                        :class="[cellFieldClass, 'w-36']"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    class="sticky bottom-0 flex flex-col gap-3 border-t border-border/80 bg-card/95 px-5 py-4 backdrop-blur sm:flex-row sm:items-center sm:justify-between sm:px-6"
                >
                    <p class="text-xs text-muted-foreground">
                        Enregistrez après modification (seuils + objectifs).
                    </p>
                    <Button type="submit" class="min-w-[200px]" :disabled="form.processing">
                        {{ form.processing ? 'Enregistrement…' : 'Enregistrer' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
