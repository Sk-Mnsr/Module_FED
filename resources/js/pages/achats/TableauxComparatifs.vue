<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Achats', href: '/feds/achats' },
    { title: 'Tableaux comparatifs', href: '#' },
];
</script>

<template>
    <Head title="Tableaux comparatifs" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <h1 class="text-3xl font-bold text-gray-900">Tableaux comparatifs</h1>
            <p class="text-gray-600">Gérer et consulter les tableaux comparatifs des offres. (À venir)</p>
        </div>
    </AppLayout>
</template>
