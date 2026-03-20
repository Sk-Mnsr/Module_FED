<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { LayoutGrid, FileText, User, Clock, CheckCircle, AlertCircle } from 'lucide-vue-next';

interface Props {
    stats: {
        total: number;
        enCours: number;
        cloturees: number;
        enAttente: number;
    };
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center gap-2">
                <LayoutGrid class="h-6 w-6 text-gray-500" />
                <h1 class="text-3xl font-bold text-gray-900">Dashboard</h1>
            </div>


            <div class="grid gap-4 md:grid-cols-4">
                <div class="rounded-lg border border-gray-200 bg-white p-4">
                    <div class="flex items-center gap-3">
                        <div class="rounded-full bg-red-100 p-2 text-red-600">
                            <User class="h-5 w-5" />
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Demandes Total</p>
                            <p class="text-2xl font-semibold text-gray-900">{{ stats.total }}</p>
                        </div>
                    </div>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-4">
                    <div class="flex items-center gap-3">
                        <div class="rounded-full bg-orange-100 p-2 text-orange-600">
                            <Clock class="h-5 w-5" />
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Demandes En Cours</p>
                            <p class="text-2xl font-semibold text-gray-900">{{ stats.enCours }}</p>
                        </div>
                    </div>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-4">
                    <div class="flex items-center gap-3">
                        <div class="rounded-full bg-green-100 p-2 text-green-600">
                            <CheckCircle class="h-5 w-5" />
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Demandes Clôturées</p>
                            <p class="text-2xl font-semibold text-gray-900">{{ stats.cloturees }}</p>
                        </div>
                    </div>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-4">
                    <div class="flex items-center gap-3">
                        <div class="rounded-full bg-blue-100 p-2 text-blue-600">
                            <AlertCircle class="h-5 w-5" />
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">En Attente</p>
                            <p class="text-2xl font-semibold text-gray-900">{{ stats.enAttente }}</p>
                        </div>
                    </div>
                </div>
            </div>

              <div class="rounded-lg border border-gray-200 bg-white p-6">
                <h2 class="text-lg font-semibold text-gray-800">Accès rapide</h2>
                <p class="mt-1 text-sm text-gray-600">
                    Utilisez le menu de gauche pour accéder aux modules, ou démarrez une demande.
                </p>

                <div class="mt-4 flex flex-wrap gap-3">
                    <Link href="/feds/create" class="inline-flex items-center gap-2 rounded-md bg-red-600 px-4 py-2 text-white hover:bg-red-700">
                        <FileText class="h-4 w-4" />
                        Nouvelle FED
                    </Link>
                    <Link href="/feds" class="inline-flex items-center gap-2 rounded-md border border-gray-300 px-4 py-2 text-gray-700 hover:bg-gray-50">
                        Mes demandes
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>