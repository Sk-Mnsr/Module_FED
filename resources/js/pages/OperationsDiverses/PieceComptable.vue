<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';

const breadcrumbs = [
    { title: 'Opérations diverses', href: '/operations-diverses/piece-comptable' },
    { title: 'Pièce comptable', href: '/operations-diverses/piece-comptable' },
];
</script>

<template>
    <Head title="Pièce comptable — OD" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <p class="text-sm text-muted-foreground">
                Saisie et gestion des pièces comptables OD — contenu métier à compléter.
            </p>
        </div>
    </AppLayout>
</template>
