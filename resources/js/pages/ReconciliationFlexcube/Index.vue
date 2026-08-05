<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { GitCompare, History, Users } from 'lucide-vue-next';

const breadcrumbs = [
    { title: 'Reconciliation Flexcube', href: '/reconciliation-flexcube' },
];
</script>

<template>
    <Head title="Reconciliation Flexcube" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-start gap-3">
                <div class="rounded-lg bg-cyan-50 p-2 text-cyan-700 dark:bg-cyan-950/40 dark:text-cyan-300">
                    <GitCompare class="size-6" />
                </div>
                <div>
                    <h1 class="text-xl font-semibold text-foreground">Reconciliation Flexcube</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Module de réconciliation des écritures et soldes Flexcube.
                    </p>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    href="/reconciliation-flexcube/reconciliation"
                    class="group rounded-xl border border-border bg-card p-5 shadow-sm transition hover:border-cyan-300 hover:shadow-md"
                >
                    <div class="flex items-center gap-3">
                        <div class="rounded-lg bg-cyan-50 p-2 text-cyan-700 group-hover:bg-cyan-100">
                            <GitCompare class="size-5" />
                        </div>
                        <div>
                            <h2 class="font-semibold text-foreground">Reconciliation</h2>
                            <p class="mt-0.5 text-sm text-muted-foreground">
                                Choisir un partenaire et lancer la réconciliation.
                            </p>
                        </div>
                    </div>
                </Link>

                <Link
                    href="/reconciliation-flexcube/historique"
                    class="group rounded-xl border border-border bg-card p-5 shadow-sm transition hover:border-cyan-300 hover:shadow-md"
                >
                    <div class="flex items-center gap-3">
                        <div class="rounded-lg bg-cyan-50 p-2 text-cyan-700 group-hover:bg-cyan-100">
                            <History class="size-5" />
                        </div>
                        <div>
                            <h2 class="font-semibold text-foreground">Historique</h2>
                            <p class="mt-0.5 text-sm text-muted-foreground">
                                Runs passés et fichiers Excel générés.
                            </p>
                        </div>
                    </div>
                </Link>

                <Link
                    href="/reconciliation-flexcube/partenaires"
                    class="group rounded-xl border border-border bg-card p-5 shadow-sm transition hover:border-cyan-300 hover:shadow-md"
                >
                    <div class="flex items-center gap-3">
                        <div class="rounded-lg bg-cyan-50 p-2 text-cyan-700 group-hover:bg-cyan-100">
                            <Users class="size-5" />
                        </div>
                        <div>
                            <h2 class="font-semibold text-foreground">Partenaires</h2>
                            <p class="mt-0.5 text-sm text-muted-foreground">
                                Identifiant, nom et icône des partenaires.
                            </p>
                        </div>
                    </div>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
