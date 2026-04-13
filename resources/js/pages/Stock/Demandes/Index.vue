<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { 
    Plus, 
    Search, 
    Clock, 
    CheckCircle2, 
    XCircle, 
    Truck, 
    ArrowRight,
    Package,
    User
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Demande {
    id: number;
    user: { name: string };
    status: string;
    motif: string;
    date_demande: string;
    items_count: number;
}

interface Props {
    demandes: {
        data: Demande[];
        links: any[];
    };
    isResponsableStock: boolean;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Approvisionnement',
        href: '#',
    },
    {
        title: 'Liste des demandes',
        href: '#',
    },
];

const pendingDemandes = computed(() => {
    return props.demandes.data.filter(d => d.status === 'en_attente');
});

const getStatusConfig = (status: string) => {
    switch (status) {
        case 'en_attente':
            return { label: 'En attente', color: 'bg-amber-100 text-amber-700 border-amber-200', icon: Clock };
        case 'approuvee':
            return { label: 'Approuvée', color: 'bg-blue-100 text-blue-700 border-blue-200', icon: CheckCircle2 };
        case 'rejetee':
            return { label: 'Rejetée', color: 'bg-rose-100 text-rose-700 border-rose-200', icon: XCircle };
        case 'livree':
            return { label: 'Livrée', color: 'bg-emerald-100 text-emerald-700 border-emerald-200', icon: Truck };
        default:
            return { label: status, color: 'bg-gray-100 text-gray-700 border-gray-200', icon: Clock };
    }
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};
</script>

<template>
    <Head title="Liste des Demandes d'Approvisionnement" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Demandes d'approvisionnement</h1>
                    <p class="text-sm text-gray-500 mt-1">
                        {{ isResponsableStock ? 'Gérez toutes les demandes de stock entrantes.' : 'Suivez l\'état de vos demandes de fournitures.' }}
                    </p>
                </div>
                
                <Button v-if="!isResponsableStock" as-child class="bg-blue-600 hover:bg-blue-700">
                    <Link href="/demandes-approvisionnement/create" class="flex items-center gap-2">
                        <Plus class="h-4 w-4" /> Nouvelle demande
                    </Link>
                </Button>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50/50 border-b border-gray-100">
                                <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">N° Demande</th>
                                <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Date</th>
                                <th v-if="isResponsableStock" class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Demandeur</th>
                                <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Motif</th>
                                <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Articles</th>
                                <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider text-center">Statut</th>
                                <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="demande in demandes.data" :key="demande.id" class="hover:bg-gray-50/50 transition-colors group">
                                <td class="px-6 py-4">
                                    <span class="font-mono text-sm font-semibold text-blue-600">REQ-{{ String(demande.id).padStart(5, '0') }}</span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-col">
                                        <span class="text-sm text-gray-900 font-medium">{{ formatDate(demande.date_demande).split(' ')[0] }}</span>
                                        <span class="text-xs text-gray-500">{{ formatDate(demande.date_demande).split(' ')[1] }}</span>
                                    </div>
                                </td>
                                <td v-if="isResponsableStock" class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <div class="h-8 w-8 rounded-full bg-blue-50 flex items-center justify-center text-blue-600">
                                            <User class="h-4 w-4" />
                                        </div>
                                        <span class="text-sm font-medium text-gray-700">{{ demande.user.name }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="text-sm text-gray-600 truncate max-w-xs">{{ demande.motif || 'Aucun motif précisé' }}</p>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <Badge variant="outline" class="bg-gray-50 text-gray-600 font-semibold">
                                        {{ demande.items_count }} article(s)
                                    </Badge>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex justify-center">
                                        <Badge :class="['px-3 py-1 border flex items-center gap-1.5', getStatusConfig(demande.status).color]">
                                            <component :is="getStatusConfig(demande.status).icon" class="h-3.5 w-3.5" />
                                            {{ getStatusConfig(demande.status).label }}
                                        </Badge>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end items-center gap-2">
                                        <Button 
                                            v-if="isResponsableStock && demande.status === 'en_attente'" 
                                            size="sm" 
                                            class="bg-amber-500 hover:bg-amber-600 text-white shadow-sm"
                                            @click="router.visit(`/demandes-approvisionnement/${demande.id}`)"
                                        >
                                            Traiter
                                        </Button>
                                        <Button 
                                            variant="ghost" 
                                            size="sm" 
                                            class="hover:bg-blue-50 hover:text-blue-600"
                                            @click="router.visit(`/demandes-approvisionnement/${demande.id}`)"
                                        >
                                            Détails <ArrowRight class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="demandes.data.length === 0">
                                <td :colspan="isResponsableStock ? 7 : 6" class="px-6 py-12 text-center text-gray-500 italic">
                                    <div class="flex flex-col items-center gap-2">
                                        <Package class="h-10 w-10 text-gray-300" />
                                        <span>Aucune demande trouvée</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination simple - à compléter si besoin via Laravel links -->
                <div v-if="demandes.links.length > 3" class="px-6 py-4 border-t border-gray-100 bg-gray-50/30 flex justify-end gap-2">
                   <!-- Intégration pagination standard Inertia/Laravel -->
                </div>
            </div>
        </div>
    </AppLayout>
</template>
