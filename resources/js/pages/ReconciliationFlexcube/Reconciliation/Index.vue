<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowRight, ImageIcon, Search, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Input } from '@/components/ui/input';

type PartenaireOption = {
    id: number;
    identifiant: string;
    nom: string;
    icone_url: string | null;
};

const props = defineProps<{
    partenaires: PartenaireOption[];
}>();

const breadcrumbs = [
    { title: 'Reconciliation Flexcube', href: '/reconciliation-flexcube' },
    { title: 'Reconciliation', href: '/reconciliation-flexcube/reconciliation' },
];

const search = ref('');
const selectingId = ref<number | null>(null);

const filtered = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) {
        return props.partenaires;
    }

    return props.partenaires.filter(
        (p) =>
            p.nom.toLowerCase().includes(q)
            || p.identifiant.toLowerCase().includes(q),
    );
});

function choisir(partenaire: PartenaireOption) {
    selectingId.value = partenaire.id;
    router.visit(`/reconciliation-flexcube/reconciliation/${partenaire.id}`);
}
</script>

<template>
    <Head title="Reconciliation — Choisir un partenaire" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="relative flex min-h-0 flex-1 flex-col overflow-hidden">
            <div
                class="pointer-events-none absolute inset-x-0 top-0 h-72 bg-gradient-to-b from-cyan-50 via-slate-50/80 to-transparent"
                aria-hidden="true"
            />
            <div
                class="pointer-events-none absolute -top-24 right-0 size-80 rounded-full bg-cyan-200/20 blur-3xl"
                aria-hidden="true"
            />

            <div class="relative flex w-full flex-col gap-8 px-4 py-6 sm:px-6 lg:px-8 xl:px-10">
                <header class="flex flex-col gap-6 border-b border-slate-200/80 pb-8 md:flex-row md:items-end md:justify-between">
                    <div class="space-y-3">
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-cyan-800">
                            Étape 1 · Partenaire
                        </p>
                        <h1 class="text-3xl font-bold tracking-tight text-slate-900 md:text-4xl">
                            Reconciliation
                        </h1>
                        <p class="max-w-lg text-base text-slate-600">
                            Sélectionnez le partenaire pour lequel vous souhaitez réconcilier les flux Flexcube.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <div class="rounded-xl border border-slate-200 bg-white/90 px-4 py-2.5 text-sm shadow-xs backdrop-blur-sm">
                            <span class="font-semibold text-slate-900">{{ partenaires.length }}</span>
                            <span class="text-slate-500">
                                partenaire{{ partenaires.length > 1 ? 's' : '' }}
                            </span>
                        </div>
                        <Link
                            href="/reconciliation-flexcube/partenaires"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-medium text-slate-700 shadow-xs transition hover:border-cyan-300 hover:text-cyan-900"
                        >
                            <Users class="size-4" />
                            Gérer
                        </Link>
                    </div>
                </header>

                <div class="relative max-w-md">
                    <Search class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-slate-400" />
                    <Input
                        v-model="search"
                        type="search"
                        placeholder="Rechercher par nom ou identifiant…"
                        class="h-11 border-slate-200 bg-white pl-10 shadow-xs"
                    />
                </div>

                <div
                    v-if="filtered.length === 0"
                    class="rounded-2xl border border-dashed border-slate-300 bg-white/70 px-6 py-16 text-center"
                >
                    <div class="mx-auto mb-3 flex size-12 items-center justify-center rounded-full bg-slate-100 text-slate-500">
                        <Users class="size-5" />
                    </div>
                    <p class="text-sm font-medium text-slate-800">
                        <template v-if="partenaires.length === 0">Aucun partenaire enregistré</template>
                        <template v-else>Aucun résultat</template>
                    </p>
                    <p class="mt-1 text-sm text-slate-500">
                        <template v-if="partenaires.length === 0">
                            Créez d’abord un partenaire dans le référentiel.
                        </template>
                        <template v-else>
                            Essayez un autre libellé ou identifiant.
                        </template>
                    </p>
                    <Link
                        v-if="partenaires.length === 0"
                        href="/reconciliation-flexcube/partenaires"
                        class="mt-4 inline-flex text-sm font-medium text-cyan-800 hover:underline"
                    >
                        Aller aux partenaires
                    </Link>
                </div>

                <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <button
                        v-for="(p, index) in filtered"
                        :key="p.id"
                        type="button"
                        class="group relative flex flex-col gap-5 overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 text-left shadow-xs transition-all duration-200 hover:-translate-y-0.5 hover:border-cyan-300 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500/40 disabled:opacity-60"
                        :style="{ animationDelay: `${index * 40}ms` }"
                        :disabled="selectingId !== null"
                        @click="choisir(p)"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex size-14 items-center justify-center overflow-hidden rounded-2xl border border-slate-100 bg-slate-50 ring-1 ring-slate-100">
                                <img
                                    v-if="p.icone_url"
                                    :src="p.icone_url"
                                    :alt="p.nom"
                                    class="size-full object-contain p-1.5"
                                />
                                <ImageIcon v-else class="size-6 text-slate-400" />
                            </div>
                            <span
                                class="inline-flex size-9 items-center justify-center rounded-full bg-slate-100 text-slate-500 transition-colors group-hover:bg-cyan-800 group-hover:text-white"
                            >
                                <ArrowRight class="size-4" />
                            </span>
                        </div>

                        <div class="min-w-0 space-y-1">
                            <h2 class="truncate text-lg font-semibold text-slate-900">
                                {{ p.nom }}
                            </h2>
                            <p class="font-mono text-xs tracking-wide text-slate-500 uppercase">
                                {{ p.identifiant }}
                            </p>
                        </div>

                        <p class="text-xs font-medium text-cyan-800 opacity-0 transition-opacity group-hover:opacity-100">
                            {{ selectingId === p.id ? 'Ouverture…' : 'Continuer →' }}
                        </p>
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
