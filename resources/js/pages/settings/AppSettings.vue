<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { ref } from 'vue';
import { Settings, AlertCircle, CheckCircle2 } from 'lucide-vue-next';
import { type BreadcrumbItem } from '@/types';

interface AppSettingItem {
    key: string;
    value: string | null;
    label: string | null;
    description: string | null;
    type: string;
}

interface Props {
    settings: Record<string, AppSettingItem>;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Paramètres', href: '/settings/app' },
];

const formatAmount = (value?: number | null) => {
    if (value === null || value === undefined) return '-';
    // Formatage avec séparateur de milliers virgule
    const formattedNum = new Intl.NumberFormat('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(Number(value) || 0);
    return `${formattedNum} FCFA`;
};

const formatQuantity = (value?: number | null) => {
    if (value === null || value === undefined) return '—';
    return Math.round(value).toString();
};

const dgaThreshold = ref<number>(
    props.settings['fed_dga_threshold']?.value ? parseFloat(props.settings['fed_dga_threshold'].value) : 0
);

const saving = ref(false);
const saved = ref(false);

const save = () => {
    saving.value = true;
    router.put('/settings/app', {
        fed_dga_threshold: dgaThreshold.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            saved.value = true;
            setTimeout(() => { saved.value = false; }, 3000);
        },
        onFinish: () => { saving.value = false; },
    });
};
</script>

<template>
    <Head title="Paramètres de l'application" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-2xl p-6">
            <div class="mb-6 flex items-center gap-3">
                <Settings class="h-7 w-7 text-gray-700" />
                <h1 class="text-2xl font-bold text-gray-900">Paramètres de l'application</h1>
            </div>

            <form @submit.prevent="save" class="space-y-6">
                <!-- Seuil DGA -->
                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="mb-1 text-base font-bold text-gray-900">Seuil de Validation</h2>
                    <p class="mb-5 text-sm text-gray-500">
                        Définissez le seuil financier à partir duquel les FED doivent être validées par le DGA avant la génération du bon de commande.
                    </p>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Seuil de validation DGA (XOF)
                            </label>
                            <div class="relative">
                                <input
                                    v-model.number="dgaThreshold"
                                    type="number"
                                    min="0"
                                    step="100000"
                                    class="w-full rounded-md border border-gray-300 px-3 py-2 pr-16 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                    placeholder="ex: 10000000"
                                />
                                <span class="absolute right-3 top-2 text-sm text-gray-400">XOF</span>
                            </div>
                            <p class="mt-1.5 text-xs text-gray-500">
                                Valeur actuelle : <strong>{{ formatAmount(dgaThreshold) }}</strong>
                            </p>
                        </div>

                        <!-- Aperçu du comportement -->
                        <div class="rounded-md border border-blue-100 bg-blue-50 p-4 text-sm">
                            <p class="font-medium text-blue-900 mb-2">Comportement actuel :</p>
                            <ul class="space-y-1 text-blue-800">
                                <li v-if="dgaThreshold > 0">
                                    • FED &lt; {{ formatAmount(dgaThreshold) }} → <strong>BOC direct</strong> (DAF = validateur final)
                                </li>
                                <li v-if="dgaThreshold > 0">
                                    • FED ≥ {{ formatAmount(dgaThreshold) }} → <strong>Transmission au DGA</strong> pour validation finale
                                </li>
                                <li v-else class="text-amber-800">
                                    • Seuil désactivé (0) → Toutes les FED passent directement en BOC après le DAF
                                </li>
                            </ul>
                        </div>

                        <div v-if="dgaThreshold === 0" class="flex items-start gap-2 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                            <AlertCircle class="h-4 w-4 mt-0.5 flex-shrink-0" />
                            <p>Un seuil de <strong>0</strong> désactive complètement le passage par le DGA. Toutes les FED approuvées par le DAF seront directement en état "Bon de commande".</p>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-3">
                    <Transition name="fade">
                        <span v-if="saved" class="flex items-center gap-1 text-sm text-green-600">
                            <CheckCircle2 class="h-4 w-4" /> Paramètres sauvegardés
                        </span>
                    </Transition>
                    <Button type="submit" :disabled="saving" class="bg-indigo-600 hover:bg-indigo-700 text-white">
                        {{ saving ? 'Enregistrement...' : 'Enregistrer les paramètres' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.4s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
