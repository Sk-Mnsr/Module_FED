<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Users } from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Monétique', href: '/monetique/coficarte' },
    { title: 'Apporteurs', href: '/monetique/agence/apporteurs' },
];

defineProps<{
    apporteurs: { id: number; nom: string; code: string | null; telephone: string | null; email: string | null; actif: boolean }[];
}>();

const form = useForm({ nom: '', telephone: '', email: '' });

const submit = () => {
    form.post('/monetique/agence/apporteurs', { preserveScroll: true, onSuccess: () => form.reset() });
};

const desactiver = (id: number) => {
    if (!confirm('Désactiver cet apporteur ?')) return;
    router.delete(`/monetique/agence/apporteurs/${id}`, { preserveScroll: true });
};
</script>

<template>
    <Head title="Apporteurs d’affaires" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6 max-w-3xl mx-auto">
            <div class="flex items-center gap-3">
                <div class="p-3 bg-violet-100 text-violet-700 rounded-xl">
                    <Users class="h-6 w-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Apporteurs d’affaires</h1>
                    <p class="text-sm text-gray-500 mt-1">Rattachés à votre agence (ventes Coficarte).</p>
                </div>
            </div>

            <form class="bg-white border rounded-xl p-6 space-y-3" @submit.prevent="submit">
                <div>
                    <Label>Nom</Label>
                    <Input v-model="form.nom" class="border-gray-300" />
                    <InputError :message="form.errors.nom" />
                </div>
                <div>
                    <Label>Téléphone</Label>
                    <Input v-model="form.telephone" class="border-gray-300" />
                </div>
                <div>
                    <Label>Email</Label>
                    <Input v-model="form.email" type="email" class="border-gray-300" />
                </div>
                <Button type="submit" class="bg-violet-600" :disabled="form.processing">Ajouter</Button>
            </form>

            <div class="space-y-2">
                <div v-for="a in apporteurs" :key="a.id" class="flex justify-between items-center border rounded-lg p-3 bg-white">
                    <div>
                        <p class="font-medium">
                            {{ a.nom }}
                            <span v-if="a.code" class="ml-2 text-xs font-mono text-violet-700">({{ a.code }})</span>
                        </p>
                        <p class="text-xs text-gray-500">{{ a.telephone || '—' }} — {{ a.email || '—' }}</p>
                    </div>
                    <Button v-if="a.actif" type="button" variant="outline" size="sm" @click="desactiver(a.id)">Désactiver</Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
