<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

interface Props {
    title: string;
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Achats', href: '/achats/tdr' },
    { title: 'Analyse comparative', href: '#' },
];
</script>

<template>
    <Head :title="title" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <h1 class="text-3xl font-bold text-gray-900">{{ title }}</h1>
            <p class="text-gray-600">Comparer les offres (prix, délais, qualité, conformité) et préparer le tableau comparatif. (À venir)</p>
        </div>
    </AppLayout>
</template>
