<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { CheckCircle, Trophy, FileText, Download, Edit } from 'lucide-vue-next';

const props = defineProps<{
    appelOffre: any;
    offres: any[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Appels d\'Offres', href: '/appel-offres' },
    { title: props.appelOffre.reference, href: `/appel-offres/${props.appelOffre.id}` },
    { title: 'Tableau Comparatif', href: '#' },
];

const form = useForm({});

const attribuerMarche = (offreId: number) => {
    if (confirm('Voulez-vous vraiment attribuer le marché à cette offre ? L\'appel d\'offres sera clôturé et le PV d\'attribution généré.')) {
        alert('Attribution enregistrée ! Le statut passera à "Attribué".');
    }
};
</script>

<template>
    <Head title="Tableau Comparatif & Classement" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6 max-w-6xl mx-auto">
            <div class="flex items-start justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Tableau Comparatif et Classement</h1>
                    <p class="text-gray-500 mt-1">Appel d'offres : {{ appelOffre.reference }} - {{ appelOffre.objet }}</p>
                </div>
                <div class="flex gap-2">
                    <a :href="`/appel-offres/${appelOffre.id}/pv-evaluation`" target="_blank">
                        <Button variant="outline" class="flex items-center gap-2 border-indigo-200 text-indigo-700 hover:bg-indigo-50">
                            <Download class="h-4 w-4" /> Exporter le PV d'Évaluation
                        </Button>
                    </a>
                </div>
            </div>

            <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50 border-b">
                            <tr>
                                <th class="px-6 py-4 text-center w-20">Rang</th>
                                <th class="px-6 py-4">Fournisseur</th>
                                <th class="px-6 py-4 text-center">Score Global</th>
                                <th class="px-6 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(offre, index) in offres" :key="offre.id" 
                                :class="{'bg-yellow-50 border-b-2 border-yellow-200': index === 0, 'border-b hover:bg-gray-50': index !== 0}">
                                <td class="px-6 py-4 text-center font-bold">
                                    <div v-if="index === 0" class="flex items-center justify-center text-yellow-600">
                                        <Trophy class="h-6 w-6" />
                                    </div>
                                    <div v-else class="text-gray-500 text-lg">{{ index + 1 }}</div>
                                </td>
                                <td class="px-6 py-4 font-medium text-gray-900">
                                    {{ offre.nom_fournisseur || `Fournisseur #${offre.id}` }}
                                    <span v-if="index === 0" class="ml-2 text-xs font-semibold text-yellow-700 bg-yellow-100 px-2 py-1 rounded-full">1er Choix</span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="text-xl font-bold" :class="index === 0 ? 'text-green-600' : 'text-gray-700'">
                                        {{ Number(offre.note_calculee).toFixed(2) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right flex items-center justify-end gap-2">
                                    <!-- Add button to view evaluation details for logic completeness -->
                                    <Link :href="`/offres/${offre.id}/evaluations/create`">
                                        <Button variant="ghost" class="text-gray-600 hover:text-indigo-600 hover:bg-indigo-50 flex items-center gap-1">
                                            <Edit class="h-4 w-4" /> Voir/Noter
                                        </Button>
                                    </Link>
                                    
                                    <Button v-if="appelOffre.statut === 'en_evaluation'" @click="attribuerMarche(offre.id)" class="bg-green-600 hover:bg-green-700 text-white flex items-center gap-2">
                                        <CheckCircle class="h-4 w-4" /> Attribuer
                                    </Button>
                                    <span v-else-if="appelOffre.statut === 'attribue'" class="text-gray-500 italic">Terminé</span>
                                </td>
                            </tr>
                            <tr v-if="offres.length === 0">
                                <td colspan="4" class="px-6 py-8 text-center text-gray-500">Aucune offre n'a encore été évaluée pour cet appel d'offres.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
