<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import ArchivageDateClassifier from '@/components/ArchivageDateClassifier.vue';

const breadcrumbs = [
    { title: 'Opérations diverses', href: '/operations-diverses/piece-comptable' },
    { title: 'Archivage', href: '/operations-diverses/archivage' },
];
</script>

<template>
    <Head title="Archivage — OD" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <ArchivageDateClassifier />
        </div>
    </AppLayout>
</template>
