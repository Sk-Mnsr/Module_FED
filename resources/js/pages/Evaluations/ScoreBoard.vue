<script setup lang="ts">
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import FormSection from '@/components/FormSection.vue';
import { computed } from 'vue';

const props = defineProps<{
    offre: any;
}>();

const appelOffre = props.offre.appel_offre;
const criteres = appelOffre.criteres || [];

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Appels d\'Offres',
        href: '/appel-offres',
    },
    {
        title: appelOffre.reference,
        href: `/appel-offres/${appelOffre.id}`,
    },
    {
        title: `Évaluation de l'offre #${props.offre.id}`,
        href: '#',
    },
];

const form = useForm({
    evaluations: criteres.map((c: any) => ({
        critere_appel_offre_id: c.id,
        note: 0,
        commentaire: '',
    }))
});

const maxPossibleScore = computed(() => {
    return criteres.reduce((sum: number, c: any) => sum + (c.note_maximale * c.ponderation), 0);
});

const currentScore = computed(() => {
    return form.evaluations.reduce((sum: number, evalItem: any, index: number) => {
        const critere = criteres[index];
        return sum + (Number(evalItem.note) * critere.ponderation);
    }, 0);
});

const submit = () => {
    form.post(`/offres/${props.offre.id}/evaluations`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Évaluation de l'offre" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6 max-w-5xl mx-auto">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Grille d'évaluation</h1>
                    <p class="text-gray-500 mt-1">Offre de <strong>{{ offre.nom_fournisseur || offre.user_id }}</strong> pour l'Appel d'Offres <strong>{{ appelOffre.reference }}</strong></p>
                </div>
                <div class="text-right bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <p class="text-sm text-blue-800 uppercase tracking-widest font-semibold mb-1">Score Total Estimé</p>
                    <p class="text-3xl font-bold text-blue-600">{{ currentScore }} <span class="text-lg text-blue-400">/ {{ maxPossibleScore }}</span></p>
                </div>
            </div>

            <form @submit.prevent="submit" class="flex flex-col gap-6">
                <FormSection title="Critères de notation" :columns="1">
                    <div class="overflow-x-auto rounded-md border border-gray-200 mt-2">
                        <table class="w-full text-sm text-left">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-50 border-b">
                                <tr>
                                    <th class="px-4 py-3 w-1/3">Critère</th>
                                    <th class="px-4 py-3 text-center">Type</th>
                                    <th class="px-4 py-3 text-center">Pondération</th>
                                    <th class="px-4 py-3 text-center">Note Max</th>
                                    <th class="px-4 py-3 w-32">Note Attribuée</th>
                                    <th class="px-4 py-3 w-1/3">Commentaire (Optionnel)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(critere, index) in criteres" :key="critere.id" class="border-b hover:bg-gray-50">
                                    <td class="px-4 py-3 font-medium text-gray-900">{{ critere.nom }}</td>
                                    <td class="px-4 py-3 text-center capitalize">{{ critere.type }}</td>
                                    <td class="px-4 py-3 text-center">{{ critere.ponderation }}</td>
                                    <td class="px-4 py-3 text-center">{{ critere.note_maximale }}</td>
                                    <td class="px-4 py-3">
                                        <!-- @ts-ignore -->
                                        <Input
                                            :id="`note-${index}`"
                                            v-model.number="form.evaluations[index].note"
                                            type="number"
                                            min="0"
                                            :max="critere.note_maximale"
                                            step="0.5"
                                            class="w-full border-gray-300 text-center"
                                            required
                                        />
                                        <InputError :message="form.errors[`evaluations.${index}.note` as keyof typeof form.errors]" />
                                    </td>
                                    <td class="px-4 py-3">
                                        <Input
                                            :id="`comm-${index}`"
                                            v-model="form.evaluations[index].commentaire"
                                            type="text"
                                            placeholder="Justification..."
                                            class="w-full border-gray-300"
                                        />
                                        <InputError :message="form.errors[`evaluations.${index}.commentaire` as keyof typeof form.errors]" />
                                    </td>
                                </tr>
                                <tr v-if="criteres.length === 0">
                                    <td colspan="6" class="px-4 py-6 text-center text-gray-500">Aucun critère défini pour cet appel d'offres.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </FormSection>

                <div class="flex justify-end gap-2 mt-4">
                    <Link :href="`/appel-offres/${appelOffre.id}`">
                        <Button type="button" variant="outline">Annuler</Button>
                    </Link>
                    <Button type="submit" :disabled="form.processing || criteres.length === 0" class="bg-indigo-600 hover:bg-indigo-700 text-white">
                        {{ form.processing ? 'Enregistrement...' : 'Valider mon évaluation' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
