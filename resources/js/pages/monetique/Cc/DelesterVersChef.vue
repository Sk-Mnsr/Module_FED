<script setup lang="ts">
import ExpirationBar from '@/components/ExpirationBar.vue';
import InputError from '@/components/InputError.vue';
import OdConfirmDialog from '@/components/OdConfirmDialog.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { formatCardNumberDisplay } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Inbox, Search, UserSquare } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Monétique', href: '/monetique/coficarte' },
    { title: 'Délester vers le chef d’agence', href: '/monetique/cc/delester-chef-agence' },
];

type Carte = {
    id: number;
    numero_carte: string;
    reference_facture: string;
    expiration?: string | null;
    date_expiration?: string | null;
};

const props = withDefaults(defineProps<{ cartes?: Carte[] }>(), {
    cartes: () => [],
});

const page = usePage();
const flash = computed(() => page.props.flash as { success?: string; error?: string } | undefined);

const selected = ref<number[]>([]);
const recherche = ref('');
const confirmerOuvert = ref(false);

const toggle = (id: number) => {
    const i = selected.value.indexOf(id);
    if (i === -1) {
        selected.value = [...selected.value, id];
    } else {
        selected.value = selected.value.filter((x) => x !== id);
    }
};

const cartesFiltrees = computed(() => {
    const q = recherche.value.trim().toLowerCase();
    if (!q) return props.cartes;
    const qCompact = q.replace(/\s/g, '');
    return props.cartes.filter((c) => {
        const numero = c.numero_carte.replace(/\s/g, '').toLowerCase();
        const facture = (c.reference_facture ?? '').toLowerCase();
        return numero.includes(qCompact) || facture.includes(q);
    });
});

const allSelected = computed(
    () => cartesFiltrees.value.length > 0 && cartesFiltrees.value.every((c) => selected.value.includes(c.id)),
);

const toggleAll = () => {
    const ids = cartesFiltrees.value.map((c) => c.id);
    if (allSelected.value) {
        selected.value = selected.value.filter((id) => !ids.includes(id));
        return;
    }
    selected.value = [...new Set([...selected.value, ...ids])];
};

const form = useForm({
    coficarte_card_ids: [] as number[],
});

const demanderConfirmation = () => {
    if (selected.value.length === 0) return;
    confirmerOuvert.value = true;
};

const submit = () => {
    form.coficarte_card_ids = [...selected.value];
    if (form.coficarte_card_ids.length === 0) return;
    form.post('/monetique/cc/delester-chef-agence', {
        preserveScroll: true,
        onSuccess: () => {
            selected.value = [];
            confirmerOuvert.value = false;
        },
        onFinish: () => {
            if (!form.processing) confirmerOuvert.value = false;
        },
    });
};
</script>

<template>
    <Head title="Coficarte — délester vers le chef d’agence" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-5 p-6 w-full">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div class="flex items-start gap-3">
                    <div class="rounded-xl bg-violet-100 p-2.5 text-violet-900">
                        <UserSquare class="h-5 w-5" />
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-900">Délester vers le chef d’agence</h1>
                        <p class="mt-0.5 text-sm text-gray-600">
                            Remettez dans le pool agence les cartes qui vous sont attribuées. Le chef pourra les réattribuer ou les renvoyer au siège.
                        </p>
                    </div>
                </div>
                <p class="text-sm text-gray-500">{{ cartes.length }} carte(s) dans votre pochette</p>
            </div>

            <div
                v-if="flash?.success"
                class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
            >
                {{ flash.success }}
            </div>
            <div
                v-if="flash?.error"
                class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900"
            >
                {{ flash.error }}
            </div>

            <div class="grid items-start gap-5 xl:grid-cols-[20rem_minmax(0,1fr)]">
                <form
                    class="space-y-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm xl:sticky xl:top-4"
                    @submit.prevent="demanderConfirmation"
                >
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900">Délestage</h2>
                        <p class="mt-0.5 text-xs text-gray-500">Les cartes cochées reviennent au stock agence.</p>
                    </div>
                    <div
                        class="rounded-lg border px-3 py-2.5"
                        :class="selected.length ? 'border-violet-200 bg-violet-50' : 'border-gray-200 bg-gray-50'"
                    >
                        <p class="text-xs font-medium uppercase tracking-wide" :class="selected.length ? 'text-violet-800' : 'text-gray-500'">
                            Sélection
                        </p>
                        <p class="mt-0.5 text-sm font-semibold tabular-nums" :class="selected.length ? 'text-violet-950' : 'text-gray-700'">
                            {{ selected.length }} carte{{ selected.length > 1 ? 's' : '' }}
                        </p>
                        <p class="mt-1 text-xs leading-snug" :class="selected.length ? 'text-violet-800' : 'text-gray-500'">
                            {{
                                selected.length
                                    ? 'Ces cartes seront remises au chef d’agence.'
                                    : 'Cochez les cartes dans le tableau.'
                            }}
                        </p>
                    </div>
                    <InputError :message="form.errors.coficarte_card_ids" />
                    <Button
                        type="submit"
                        class="w-full bg-violet-600 hover:bg-violet-700"
                        :disabled="selected.length === 0 || form.processing"
                    >
                        {{ selected.length ? `Délester ${selected.length} carte${selected.length > 1 ? 's' : ''}` : 'Délester' }}
                    </Button>
                </form>

                <section class="min-w-0 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-4 py-3">
                        <h2 class="text-sm font-semibold text-gray-900">Votre pochette</h2>
                        <div class="flex items-center gap-2">
                            <div v-if="cartes.length" class="relative">
                                <Search class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
                                <Input
                                    v-model="recherche"
                                    placeholder="N° carte, facture…"
                                    class="h-8 w-44 border-gray-300 pl-8 text-sm"
                                />
                            </div>
                            <Button
                                v-if="cartesFiltrees.length"
                                type="button"
                                variant="outline"
                                size="sm"
                                class="bg-white"
                                @click="toggleAll"
                            >
                                {{ allSelected ? 'Tout retirer' : 'Tout sélectionner' }}
                            </Button>
                        </div>
                    </div>

                    <div v-if="!cartes.length" class="px-6 py-16 text-center">
                        <Inbox class="mx-auto mb-3 h-10 w-10 text-gray-300" />
                        <p class="text-sm text-gray-600">Aucune carte ne vous est actuellement attribuée.</p>
                    </div>
                    <div v-else-if="!cartesFiltrees.length" class="px-6 py-12 text-center text-sm text-gray-500">
                        Aucune carte ne correspond à la recherche.
                    </div>
                    <div v-else class="max-h-[min(70vh,720px)] overflow-auto">
                        <table class="min-w-full text-sm">
                            <thead class="sticky top-0 z-10 bg-gray-50">
                                <tr class="border-b border-gray-200 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                                    <th class="w-14 px-4 py-2.5">
                                        <Checkbox
                                            :checked="allSelected"
                                            :indeterminate="selected.length > 0 && !allSelected"
                                            class="size-5 cursor-pointer border-2 border-gray-400 bg-white data-[state=checked]:border-violet-600 data-[state=checked]:bg-violet-600 data-[state=indeterminate]:border-violet-600 data-[state=indeterminate]:bg-violet-600"
                                            aria-label="Tout sélectionner"
                                            @update:checked="toggleAll"
                                        />
                                    </th>
                                    <th class="px-4 py-2.5">N° carte</th>
                                    <th class="px-4 py-2.5">Facture</th>
                                    <th class="px-4 py-2.5">Expire</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="c in cartesFiltrees"
                                    :key="c.id"
                                    class="cursor-pointer border-b border-gray-100 last:border-0"
                                    :class="selected.includes(c.id) ? 'bg-violet-100' : 'hover:bg-gray-50'"
                                    @click="toggle(c.id)"
                                >
                                    <td
                                        class="border-l-4 px-4 py-3"
                                        :class="selected.includes(c.id) ? 'border-l-violet-600' : 'border-l-transparent'"
                                        @click.stop
                                    >
                                        <Checkbox
                                            :checked="selected.includes(c.id)"
                                            class="size-5 cursor-pointer border-2 border-gray-400 bg-white data-[state=checked]:border-violet-600 data-[state=checked]:bg-violet-600"
                                            :aria-label="`Sélectionner la carte ${c.numero_carte}`"
                                            @update:checked="() => toggle(c.id)"
                                        />
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2.5 font-mono font-medium tabular-nums text-gray-900">
                                        {{ formatCardNumberDisplay(c.numero_carte) }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2.5 text-gray-700">
                                        {{ c.reference_facture || '—' }}
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <div class="w-40">
                                            <ExpirationBar
                                                :expiration="c.expiration || '—'"
                                                :date-expiration="c.date_expiration || ''"
                                            />
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <OdConfirmDialog
                :open="confirmerOuvert"
                title="Délester ces cartes ?"
                :description="`${selected.length} carte${selected.length > 1 ? 's' : ''} retourneront dans le pool agence, disponibles pour le chef d’agence.`"
                confirm-label="Enregistrer le délestage"
                cancel-label="Retour"
                :loading="form.processing"
                @update:open="(v) => { if (!form.processing) confirmerOuvert = v }"
                @confirm="submit"
            />
        </div>
    </AppLayout>
</template>
