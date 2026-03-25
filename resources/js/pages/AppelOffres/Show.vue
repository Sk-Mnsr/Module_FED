<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Eye, Edit, Trash2, Send, Users, FileText } from 'lucide-vue-next';

interface AppelOffre {
    id: number;
    reference: string;
    objet: string;
    description: string;
    date_lancement?: string | null;
    date_limite_soumission: string;
    statut: string;
    type_publication: string;
    dao_path?: string | null;
    cahier_charges_path?: string | null;
    fournisseurs?: any[];
    criteres: any[];
    comite?: any;
    creator?: any;
}

const props = defineProps<{
    appelOffre: AppelOffre;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Appels d\'Offres',
        href: '/appel-offres',
    },
    {
        title: props.appelOffre.reference,
        href: '#',
    },
];

const statusLabel = (status: string) => {
    switch (status) {
        case 'brouillon': return 'Brouillon';
        case 'publie': return 'Publié';
        case 'cloture': return 'Clôturé';
        case 'en_evaluation': return 'En évaluation';
        case 'attribue': return 'Attribué';
        default: return status;
    }
};

const statusBadge = (status: string) => {
    switch (status) {
        case 'brouillon': return 'bg-gray-100 text-gray-700';
        case 'publie': return 'bg-blue-100 text-blue-700';
        case 'cloture': return 'bg-orange-100 text-orange-700';
        case 'en_evaluation': return 'bg-purple-100 text-purple-700';
        case 'attribue': return 'bg-green-100 text-green-700';
        default: return 'bg-gray-100 text-gray-700';
    }
};

const publier = () => {
    if (confirm('Voulez-vous vraiment publier cet appel d\'offres ? Cette action est irréversible.')) {
        router.post(`/appel-offres/${props.appelOffre.id}/publish`, {}, {
            preserveScroll: true,
        });
    }
};

</script>

<template>
    <Head :title="appelOffre.reference" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-start justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">{{ appelOffre.objet }}</h1>
                    <p class="text-gray-500 mt-1">Référence : {{ appelOffre.reference }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <span :class="['inline-flex rounded-full px-3 py-1 text-sm font-medium', statusBadge(appelOffre.statut)]">
                        {{ statusLabel(appelOffre.statut) }}
                    </span>
                    <template v-if="appelOffre.statut === 'brouillon'">
                        <Link :href="`/appel-offres/${appelOffre.id}/edit`">
                            <Button variant="outline" class="flex items-center gap-2">
                                <Edit class="h-4 w-4" /> Modifier
                            </Button>
                        </Link>
                        <!-- Button to publish -->
                        <Button @click="publier" class="bg-blue-600 hover:bg-blue-700 text-white flex items-center gap-2">
                            <Send class="h-4 w-4" /> Publier
                        </Button>
                    </template>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Main Content -->
                <div class="lg:col-span-2 space-y-6">
                    <div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm">
                        <h2 class="text-xl font-semibold mb-4 border-b pb-2 flex items-center gap-2">
                            <FileText class="h-5 w-5 text-gray-500" /> Détails
                        </h2>
                        <div class="space-y-4">
                            <div>
                                <h3 class="font-medium text-gray-700">Description</h3>
                                <p class="text-gray-600 mt-1 whitespace-pre-wrap">{{ appelOffre.description }}</p>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <h3 class="font-medium text-gray-700">Date de lancement</h3>
                                    <p class="text-gray-600">{{ appelOffre.date_lancement ? new Date(appelOffre.date_lancement).toLocaleDateString('fr-FR') : '-' }}</p>
                                </div>
                                <div>
                                    <h3 class="font-medium text-gray-700">Date limite de soumission</h3>
                                    <p class="text-red-600 font-medium">{{ new Date(appelOffre.date_limite_soumission).toLocaleString('fr-FR') }}</p>
                                </div>
                                <div>
                                    <h3 class="font-medium text-gray-700">Type de publication</h3>
                                    <p class="text-gray-600 capitalize">{{ appelOffre.type_publication }}</p>
                                </div>
                                <div>
                                    <h3 class="font-medium text-gray-700">Créé par</h3>
                                    <p class="text-gray-600">{{ appelOffre.creator?.name || 'Inconnu' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm">
                        <h2 class="text-xl font-semibold mb-4 border-b pb-2">Critères d'évaluation</h2>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left">
                                <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2">Nom</th>
                                        <th class="px-4 py-2">Type</th>
                                        <th class="px-4 py-2 text-right">Pondération</th>
                                        <th class="px-4 py-2 text-right">Note Max</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="critere in appelOffre.criteres" :key="critere.id" class="border-b">
                                        <td class="px-4 py-2 font-medium text-gray-900">{{ critere.nom }}</td>
                                        <td class="px-4 py-2 capitalize">{{ critere.type }}</td>
                                        <td class="px-4 py-2 text-right">{{ critere.ponderation }}</td>
                                        <td class="px-4 py-2 text-right">{{ critere.note_maximale }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Sidebar Content -->
                <div class="space-y-6">

                    <!-- Documents Joints -->
                    <div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm">
                        <h2 class="text-xl font-semibold mb-4 border-b pb-2 flex items-center gap-2">
                            <FileText class="h-5 w-5 text-gray-500" /> Pièces jointes
                        </h2>
                        <div class="space-y-3">
                            <div v-if="appelOffre.dao_path" class="flex items-center justify-between p-2 bg-gray-50 rounded border">
                                <span class="text-sm font-medium text-gray-700">Dossier d'Appel d'Offres (DAO)</span>
                                <a :href="`/storage/${appelOffre.dao_path}`" target="_blank" class="text-indigo-600 hover:underline text-sm font-medium">Télécharger</a>
                            </div>
                            <div v-else class="text-sm text-gray-500 italic p-2">Aucun test DAO joint.</div>

                            <div v-if="appelOffre.cahier_charges_path" class="flex items-center justify-between p-2 bg-gray-50 rounded border">
                                <span class="text-sm font-medium text-gray-700">Cahier des charges</span>
                                <a :href="`/storage/${appelOffre.cahier_charges_path}`" target="_blank" class="text-indigo-600 hover:underline text-sm font-medium">Télécharger</a>
                            </div>
                            <div v-else class="text-sm text-gray-500 italic p-2">Aucun cahier des charges joint.</div>
                        </div>
                    </div>

                    <!-- Fournisseurs -->
                    <div v-if="appelOffre.fournisseurs && appelOffre.fournisseurs.length > 0" class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm">
                        <h2 class="text-xl font-semibold mb-4 border-b pb-2 flex items-center gap-2">
                            <Users class="h-5 w-5 text-gray-500" /> Fournisseurs invités
                        </h2>
                        <ul class="space-y-2">
                            <li v-for="f in appelOffre.fournisseurs" :key="f.id" class="text-sm flex flex-col p-2 bg-gray-50 rounded border border-gray-100">
                                <span class="font-medium text-gray-800">{{ f.nom }}</span>
                                <span class="text-xs text-gray-500">{{ f.contact_email || 'Email non renseigné' }} | {{ f.contact_telephone || '' }}</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Comité -->
                    <div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm">
                        <h2 class="text-xl font-semibold mb-4 border-b pb-2 flex items-center gap-2">
                            <Users class="h-5 w-5 text-gray-500" /> Comité d'évaluation
                        </h2>
                        
                        <div v-if="appelOffre.comite">
                            <p class="font-medium">{{ appelOffre.comite.nom }}</p>
                            <span class="text-sm text-gray-500">Statut: {{ appelOffre.comite.statut }}</span>
                            <!-- Button back to manage committee -->
                            <Link :href="`/comites/${appelOffre.comite.id}/edit`" class="block mt-4">
                                <Button variant="outline" class="w-full">Modifier le comité</Button>
                            </Link>
                        </div>
                        <div v-else class="text-center py-4">
                            <p class="text-gray-500 mb-4">Aucun comité associé.</p>
                            <Link :href="`/appel-offres/${appelOffre.id}/comites/create`" class="block">
                                <Button variant="outline" class="w-full">Créer un comité</Button>
                            </Link>
                        </div>
                    </div>
                    
                    <div v-if="appelOffre.statut === 'publie'" class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm text-center mb-6">
                        <h2 class="text-xl font-semibold mb-2">Déposer une offre</h2>
                        <p class="text-sm text-gray-500 mb-4">Cet appel d'offres est ouvert aux soumissions.</p>
                        <Link :href="`/appel-offres/${appelOffre.id}/offres/create`">
                            <Button class="w-full bg-green-600 hover:bg-green-700 text-white">Soumettre une offre</Button>
                        </Link>
                    </div>

                    <div v-if="appelOffre.statut !== 'brouillon'" class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm text-center">
                        <h2 class="text-xl font-semibold mb-2">Ouverture des plis</h2>
                        <p class="text-sm text-gray-500 mb-4">Gérer l'ouverture officielle des offres soumises.</p>
                        <Link :href="`/appel-offres/${appelOffre.id}/opening-session`">
                            <Button class="w-full bg-indigo-600 hover:bg-indigo-700 text-white">Aller à la session</Button>
                        </Link>
                    </div>

                    <div v-if="appelOffre.statut === 'en_evaluation' || appelOffre.statut === 'attribue'" class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm text-center mt-6">
                        <h2 class="text-xl font-semibold mb-2">Tableau Comparatif</h2>
                        <p class="text-sm text-gray-500 mb-4">Voir les scores et le classement des offres évaluées.</p>
                        <Link :href="`/appel-offres/${appelOffre.id}/compare`">
                            <Button class="w-full bg-purple-600 hover:bg-purple-700 text-white">Consulter le classement final</Button>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
