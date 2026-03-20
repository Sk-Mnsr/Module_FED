<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import FormSection from '@/components/FormSection.vue';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Typologies de dépenses', href: '/typologies' },
    { title: 'Créer', href: '#' },
];

const form = useForm({
    type: '',
    libelle: '',
    description: '',
});

const submit = () => {
    form.post('/typologies', { preserveScroll: true });
};
</script>

<template>
    <Head title="Créer une typologie" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <h1 class="text-3xl font-bold text-gray-900">Créer une typologie</h1>

            <form @submit.prevent="submit" class="flex flex-col gap-6">
                <FormSection :columns="2">
                    <div>
                        <Label for="type" class="text-base font-medium text-gray-700">Type</Label>
                        <Input id="type" v-model="form.type" type="text" required class="mt-1.5 border-gray-300" placeholder="OPEX" />
                        <InputError :message="form.errors.type" />
                    </div>

                    <div>
                        <Label for="libelle" class="text-base font-medium text-gray-700">Libellé</Label>
                        <Input id="libelle" v-model="form.libelle" type="text" required class="mt-1.5 border-gray-300" />
                        <InputError :message="form.errors.libelle" />
                    </div>

                    <div class="md:col-span-2">
                        <Label for="description" class="text-base font-medium text-gray-700">Description</Label>
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="3"
                            class="mt-1.5 w-full rounded-md border border-gray-300 px-3 py-2 text-base focus-visible:border-gray-400 focus-visible:ring-1 focus-visible:ring-gray-400"
                        />
                        <InputError :message="form.errors.description" />
                    </div>
                </FormSection>

                <div class="flex justify-end gap-2">
                    <Button type="button" variant="outline" @click="router.visit('/typologies')">Annuler</Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Création...' : 'Créer' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
