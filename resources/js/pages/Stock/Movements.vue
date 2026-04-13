<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import DataTable from '@/components/DataTable.vue';
import { History, ArrowLeft, ArrowUpRight, ArrowDownRight, Edit3 } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

interface Movement {
    id: number;
    type: 'entree' | 'sortie' | 'correction';
    quantite: number;
    motif: string;
    destinataire?: string;
    created_at: string;
    article: {
        description: string;
        code: string;
    };
    user: {
        name: string;
    };
}

interface Props {
    movements: {
        data: Movement[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
    };
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Gestion de Stock', href: '/stock' },
    { title: 'Historique des Mouvements', href: '/stock/movements' },
];

const columns = [
    { key: 'created_at', title: 'Date' },
    { key: 'article', title: 'Article' },
    { key: 'type', title: 'Type' },
    { key: 'quantite', title: 'Quantité' },
    { key: 'motif', title: 'Motif' },
    { key: 'destinataire', title: 'Destinataire' },
    { key: 'user', title: 'Utilisateur' },
];

const getMovementTypeClass = (type: string) => {
    if (type === 'entree') return 'bg-green-100 text-green-800';
    if (type === 'sortie') return 'bg-red-100 text-red-800';
    return 'bg-blue-100 text-blue-800';
};

const getMovementTypeLabel = (type: string) => {
    if (type === 'entree') return 'Entrée';
    if (type === 'sortie') return 'Sortie';
    return 'Correction';
};

const formatDate = (dateString: string) => {
    return new Intl.DateTimeFormat('fr-FR', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    }).format(new Date(dateString));
};
</script>

<template>
    <Head title="Historique des Mouvements" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <Link href="/stock">
                            <Button variant="ghost" size="sm" class="h-8 w-8 p-0">
                                <ArrowLeft class="h-4 w-4" />
                            </Button>
                        </Link>
                        <h1 class="text-3xl font-bold text-gray-900">Historique des Mouvements</h1>
                    </div>
                    <p class="text-sm text-gray-500 mt-1 ml-10">Consultez l'historique complet des entrées et sorties de stock.</p>
                </div>
            </div>

            <DataTable
                :headers="columns"
                :items="props.movements.data"
                :total-items="props.movements.total"
                :current-page="props.movements.current_page"
                :items-per-page="props.movements.per_page"
                :show-select="false"
                @page-change="(page) => $inertia.get('/stock/movements', { page }, { preserveState: true })"
            >
                <template #item.created_at="{ item }">
                    <span class="text-gray-600">{{ formatDate(item.created_at) }}</span>
                </template>

                <template #item.article="{ item }">
                    <div class="flex flex-col">
                        <span class="font-medium text-gray-900">{{ item.article.description }}</span>
                        <span class="text-xs text-gray-500">{{ item.article.code }}</span>
                    </div>
                </template>

                <template #item.type="{ item }">
                    <span :class="['inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium', getMovementTypeClass(item.type)]">
                        <ArrowUpRight v-if="item.type === 'entree'" class="mr-1 h-3 w-3" />
                        <ArrowDownRight v-if="item.type === 'sortie'" class="mr-1 h-3 w-3" />
                        <Edit3 v-if="item.type === 'correction'" class="mr-1 h-3 w-3" />
                        {{ getMovementTypeLabel(item.type) }}
                    </span>
                </template>

                <template #item.quantite="{ item }">
                    <span :class="['font-bold', item.type === 'entree' ? 'text-green-600' : (item.type === 'sortie' ? 'text-red-600' : 'text-blue-600')]">
                        {{ item.type === 'sortie' ? '-' : '+' }}{{ item.quantite }}
                    </span>
                </template>

                <template #item.motif="{ item }">
                    <span class="text-gray-600 italic">{{ item.motif || '-' }}</span>
                </template>

                <template #item.destinataire="{ item }">
                    <span class="text-gray-900 font-medium">{{ item.destinataire || '-' }}</span>
                </template>

                <template #item.user="{ item }">
                    <span class="text-gray-900">{{ item.user.name }}</span>
                </template>
            </DataTable>
        </div>
    </AppLayout>
</template>
