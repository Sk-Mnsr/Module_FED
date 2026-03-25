<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { LockOpen, FileText, Lock } from 'lucide-vue-next';

const props = defineProps<{
    appelOffre: any;
}>();

const keyInput = ref('');

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Appels d\'Offres', href: '/appel-offres' },
    { title: props.appelOffre.reference, href: `/appel-offres/${props.appelOffre.id}` },
    { title: 'Session d\'ouverture', href: '#' },
];

const now = new Date();
const isClosed = now > new Date(props.appelOffre.date_limite_soumission);
const isOpened = props.appelOffre.is_plis_ouverts;

const form = useForm({});

const openSession = () => {
    if (!keyInput.value) {
        alert("Veuillez saisir la clé d'ouverture reconstituée.");
        return;
    }
    if (confirm('Confirmez-vous l\'ouverture officielle des plis ? Le Procès-Verbal sera généré et les offres seront déverrouillées pour le comité.')) {
        form.transform((data) => ({
            ...data,
            cle_ouverture: keyInput.value
        })).post(`/appel-offres/${props.appelOffre.id}/start-evaluation`, {
            preserveScroll: true,
            onError: (errors) => {
                if (errors.cle_ouverture) {
                    alert(errors.cle_ouverture);
                }
            }
        });
    }
};
</script>

<template>
    <Head title="Session d'ouverture des plis" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-8 p-6 2xl:p-12 max-w-7xl 2xl:max-w-none mx-auto w-full">
            <div class="flex items-start justify-between">
                <div>
                    <h1 class="text-3xl 2xl:text-5xl font-bold text-gray-900">Session d'ouverture des plis</h1>
                    <p class="text-gray-500 2xl:text-xl mt-2 italic">Appel d'offres : {{ appelOffre.reference }} - {{ appelOffre.objet }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm flex flex-col items-center text-center">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center mb-4"
                         :class="isOpened ? 'bg-green-100 text-green-600' : 'bg-orange-100 text-orange-600'">
                        <LockOpen v-if="isOpened" class="h-8 w-8" />
                        <Lock v-else class="h-8 w-8" />
                    </div>
                    <h2 class="text-xl font-semibold mb-2">{{ isOpened ? 'Plis ouverts' : 'Plis scellés' }}</h2>
                    <p class="text-gray-600 mb-6">
                        {{ isOpened 
                            ? 'La session d\'ouverture a déjà eu lieu. Les membres du comité peuvent consulter et évaluer les offres.' 
                            : 'Les offres soumises sont cryptées et inaccessibles avant l\'ouverture officielle de la session par le président du comité ou le responsable.' 
                        }}
                    </p>

                    <div v-if="!isOpened" class="w-full mt-4 mb-4">
                        <label class="block text-sm font-medium text-gray-700 text-left mb-1">Clé reconstituée du comité</label>
                        <input v-model="keyInput" type="text" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-center text-lg font-mono uppercase tracking-widest p-2" placeholder="Ex: A1B2C3D4" />
                        <p class="text-xs text-gray-500 text-left mt-2">Veuillez saisir les fragments de clé fournis par les membres présents.</p>
                    </div>

                    <Button v-if="!isOpened" @click="openSession" :disabled="!isClosed || form.processing" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white">
                        <span v-if="!isClosed">Attente de la date limite</span>
                        <span v-else-if="form.processing">Ouverture en cours...</span>
                        <span v-else class="flex items-center gap-2"><LockOpen class="h-4 w-4" /> Procéder à l'ouverture officielle</span>
                    </Button>
                    <div v-else class="w-full space-y-3">
                        <a :href="`/appel-offres/${appelOffre.id}/pv-ouverture`" target="_blank">
                            <Button variant="outline" class="w-full flex items-center justify-center gap-2 border-indigo-200 text-indigo-700 hover:bg-indigo-50">
                                <FileText class="h-4 w-4" /> Télécharger le PV d'ouverture
                            </Button>
                        </a>
                    </div>
                </div>

                <div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm">
                    <h2 class="text-xl font-semibold mb-4 border-b pb-2">Offres reçues ({{ appelOffre.offres?.length || 0 }})</h2>
                    <ul class="space-y-3">
                        <li v-for="(offre, index) in appelOffre.offres" :key="offre.id" class="flex items-center justify-between p-3 bg-gray-50 rounded-md border border-gray-100">
                            <div>
                                <p class="font-medium text-gray-900">
                                    {{ isOpened ? offre.nom_fournisseur || `Fournisseur #${offre.id}` : `Offre anonyme #${Number(index) + 1}` }}
                                </p>
                                <p class="text-xs text-gray-500">Soumise le {{ new Date(offre.created_at).toLocaleString('fr-FR') }}</p>
                            </div>
                            <span :class="['px-2 py-1 text-xs font-medium rounded-full', isOpened ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-600']">
                                {{ isOpened ? 'Déverrouillée' : 'Verrouillée' }}
                            </span>
                        </li>
                        <li v-if="!appelOffre.offres?.length" class="text-center text-gray-500 py-4">
                            Aucune offre n'a été soumise pour le moment.
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
