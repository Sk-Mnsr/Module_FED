<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { formatCardNumberDisplay } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { PackageMinus, UserSquare } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Monétique', href: '/monetique/coficarte' },
    { title: 'Délester vers le chef d’agence', href: '/monetique/cc/delester-chef-agence' },
];

type Carte = { id: number; numero_carte: string; reference_facture: string };

const props = withDefaults(defineProps<{ cartes?: Carte[] }>(), {
    cartes: () => [],
});

const selected = ref<number[]>([]);

const toggle = (id: number) => {
    const i = selected.value.indexOf(id);
    if (i === -1) {
        selected.value = [...selected.value, id];
    } else {
        selected.value = selected.value.filter((x) => x !== id);
    }
};

const allIds = computed(() => props.cartes.map((c) => c.id));
const allSelected = computed(
    () => props.cartes.length > 0 && selected.value.length === props.cartes.length,
);

const toggleAll = () => {
    selected.value = allSelected.value ? [] : [...allIds.value];
};

const form = useForm({
    coficarte_card_ids: [] as number[],
});

const submit = () => {
    form.coficarte_card_ids = [...selected.value];
    if (form.coficarte_card_ids.length === 0) {
        return;
    }
    if (
        !confirm(
            'Confirmer le délestage de ces cartes vers le stock agence ? Elles seront à nouveau disponibles pour le chef d’agence (pool non attribué).',
        )
    ) {
        return;
    }
    form.post('/monetique/cc/delester-chef-agence', { preserveScroll: true });
};
</script>

<template>
    <Head title="Coficarte — délester vers le chef d’agence" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-8 p-6 max-w-5xl mx-auto w-full pb-24">
            <button
                type="button"
                class="inline-flex items-center gap-2 text-sm font-medium text-violet-700 hover:text-violet-900 w-fit -ml-1"
                @click="router.visit('/monetique/coficarte')"
            >
                <span class="inline-flex items-center gap-1">
                    ← <span>Coficarte</span>
                </span>
            </button>

            <div class="flex items-start gap-4">
                <div class="p-3.5 bg-violet-100 text-violet-900 rounded-2xl shadow-sm shrink-0">
                    <UserSquare class="h-7 w-7" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Délester vers le chef d’agence</h1>
                    <p class="text-sm text-gray-600 mt-1 max-w-2xl leading-relaxed">
                        <strong class="font-medium text-gray-800">Délester</strong> : remettre dans le pool agence les cartes qui vous ont été attribuées.
                        Le chef d’agence pourra les réattribuer à un autre chargé de clientèle ou les renvoyer au siège.
                    </p>
                    <p v-if="cartes.length" class="text-xs text-gray-500 mt-2">
                        <PackageMinus class="inline h-3.5 w-3.5 -mt-0.5 mr-1" />
                        {{ cartes.length }} carte(s) à délester dans votre pochette
                    </p>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                <div class="px-5 py-4 bg-gradient-to-r from-violet-50/90 to-white border-b border-violet-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold text-gray-900">
                            {{ selected.length }} sélectionnée(s)
                            <span class="font-normal text-gray-500">/ {{ cartes.length }} au total</span>
                        </p>
                        <p class="text-xs text-gray-500 mt-0.5">Cochez les cartes à délester vers le stock agence.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button type="button" variant="outline" size="sm" class="bg-white" @click="toggleAll">
                            {{ allSelected ? 'Tout désélectionner' : 'Tout sélectionner' }}
                        </Button>
                        <Button
                            type="button"
                            class="bg-violet-600 hover:bg-violet-700"
                            size="sm"
                            :disabled="selected.length === 0 || form.processing"
                            @click="submit"
                        >
                            Enregistrer le délestage
                        </Button>
                    </div>
                </div>

                <div v-if="!cartes.length" class="px-6 py-16 text-center text-sm text-gray-500">
                    Aucune carte ne vous est actuellement attribuée.
                </div>

                <div v-else class="overflow-x-auto max-h-[min(60vh,560px)] overflow-y-auto">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 z-10 bg-gray-100/95 backdrop-blur border-b border-gray-200">
                            <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                <th class="w-12 px-4 py-3"></th>
                                <th class="px-4 py-3">Numéro</th>
                                <th class="px-4 py-3">Réf. facture</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr
                                v-for="c in cartes"
                                :key="c.id"
                                class="hover:bg-violet-50/30 transition-colors"
                                :class="selected.includes(c.id) ? 'bg-violet-50/50' : ''"
                            >
                                <td class="px-4 py-3 align-middle">
                                    <Checkbox
                                        :checked="selected.includes(c.id)"
                                        class="cursor-pointer"
                                        @update:checked="() => toggle(c.id)"
                                    />
                                </td>
                                <td class="px-4 py-3">
                                    <span class="font-mono font-semibold text-gray-900 tabular-nums">
                                        {{ formatCardNumberDisplay(c.numero_carte) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ c.reference_facture }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div
                class="sm:hidden fixed bottom-0 left-0 right-0 z-20 border-t border-gray-200 bg-white/95 backdrop-blur px-4 py-3 flex gap-2 justify-between items-center safe-area-pb"
            >
                <span class="text-xs text-gray-600">{{ selected.length }} cochée(s)</span>
                <Button
                    type="button"
                    size="sm"
                    class="bg-violet-600"
                    :disabled="selected.length === 0 || form.processing"
                    @click="submit"
                >
                    Délester
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
