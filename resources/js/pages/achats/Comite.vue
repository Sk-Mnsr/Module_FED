<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Achats', href: '/feds/achats' },
    { title: 'Comité', href: '#' },
];
</script>

<template>
    <Head title="Comité" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <h1 class="text-3xl font-bold text-gray-900">Comité</h1>
            <p class="text-gray-600">Gestion des comités d'achats. (À venir)</p>
        </div>
    </AppLayout>
</template>
