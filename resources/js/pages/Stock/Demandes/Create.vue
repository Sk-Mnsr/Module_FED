<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { Plus, Trash2, Package } from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Approvisionnement',
        href: '/demandes-approvisionnement',
    },
    {
        title: 'Nouvelle demande',
        href: '#',
    },
];

const form = useForm({
    motif: '',
    items: [
        { designation: '', quantite: 1 }
    ],
});

const addItem = () => {
    form.items.push({ designation: '', quantite: 1 });
};

const removeItem = (index: number) => {
    form.items.splice(index, 1);
};

const submit = () => {
    form.post('/demandes-approvisionnement');
};
</script>

<template>
    <Head title="Nouvelle Demande d'Approvisionnement" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6 max-w-4xl mx-auto">
            <div class="flex items-center gap-3 mb-8">
                <div class="p-3 bg-blue-100 text-blue-600 rounded-xl">
                    <Package class="h-6 w-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Nouvelle demande d'approvisionnement</h1>
                    <p class="text-sm text-gray-500 mt-1">Saisissez les articles dont vous avez besoin.</p>
                </div>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Motif de la demande -->
                <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm space-y-4">
                    <div class="flex items-center gap-2 mb-2">
                        <Label for="motif" class="text-sm font-semibold text-gray-700 uppercase tracking-wider">Motif de la demande</Label>
                    </div>
                    <textarea
                        id="motif"
                        v-model="form.motif"
                        rows="3"
                        placeholder="Ex: Besoin de fournitures pour le département IT..."
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 transition-all font-sans text-sm"
                    ></textarea>
                    <InputError :message="form.errors.motif" />
                </div>

                <!-- Articles demandés -->
                <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm space-y-6">
                    <div class="flex items-center justify-between">
                        <Label class="text-sm font-semibold text-gray-700 uppercase tracking-wider">Articles demandés</Label>
                        <Button type="button" variant="outline" size="sm" @click="addItem" class="flex items-center gap-2 hover:bg-blue-50 hover:text-blue-600 hover:border-blue-200 transition-colors">
                            <Plus class="h-4 w-4" /> Ajouter une ligne
                        </Button>
                    </div>

                    <div class="space-y-4">
                        <div v-for="(item, index) in form.items" :key="index" class="flex items-start gap-4 p-4 rounded-lg bg-gray-50 border border-gray-100 group relative">
                            <div class="flex-1 grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="md:col-span-2">
                                    <Label class="text-xs font-medium text-gray-500 mb-1 block">Désignation de l'article</Label>
                                    <Input
                                        v-model="item.designation"
                                        placeholder="Décrivez l'article (ex: Papier A4, Stylo bleu...)"
                                        class="w-full text-sm"
                                    />
                                    <InputError :message="form.errors[`items.${index}.designation`]" />
                                </div>
                                <div>
                                    <Label class="text-xs font-medium text-gray-500 mb-1 block">Quantité</Label>
                                    <Input
                                        v-model.number="item.quantite"
                                        type="number"
                                        min="1"
                                        class="w-full text-sm"
                                    />
                                    <InputError :message="form.errors[`items.${index}.quantite`]" />
                                </div>
                            </div>
                            
                            <Button
                                v-if="form.items.length > 1"
                                type="button"
                                variant="ghost"
                                size="icon"
                                @click="removeItem(index)"
                                class="text-gray-400 hover:text-red-500 hover:bg-red-50 mt-5"
                            >
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                    <Button type="button" variant="outline" @click="router.visit('/demandes-approvisionnement')" class="px-6">
                        Annuler
                    </Button>
                    <Button type="submit" class="px-8 bg-blue-600 hover:bg-blue-700 shadow-md transition-all" :disabled="form.processing">
                        {{ form.processing ? 'Envoi...' : 'Soumettre la demande' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
