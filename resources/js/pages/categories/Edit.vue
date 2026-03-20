<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import FormSection from '@/components/FormSection.vue';

interface Props {
    category: {
        id: number;
        categorie: string;
        code: string;
    };
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Catégories de dépenses', href: '/categories' },
    { title: 'Modifier', href: '#' },
];

const form = useForm({
    categorie: props.category.categorie,
    code: props.category.code,
});

const submit = () => {
    form.put(`/categories/${props.category.id}`, { preserveScroll: true });
};
</script>

<template>
    <Head title="Modifier une catégorie" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <h1 class="text-3xl font-bold text-gray-900">Modifier une catégorie</h1>

            <form @submit.prevent="submit" class="flex flex-col gap-6">
                <FormSection :columns="2">
                    <div>
                        <Label for="categorie" class="text-base font-medium text-gray-700">Catégorie</Label>
                        <Input id="categorie" v-model="form.categorie" type="text" required class="mt-1.5 border-gray-300" />
                        <InputError :message="form.errors.categorie" />
                    </div>

                    <div>
                        <Label for="code" class="text-base font-medium text-gray-700">Code</Label>
                        <Input id="code" v-model="form.code" type="text" required class="mt-1.5 border-gray-300" />
                        <InputError :message="form.errors.code" />
                    </div>
                </FormSection>

                <div class="flex justify-end gap-2">
                    <Button type="button" variant="outline" @click="router.visit('/categories')">Annuler</Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Mise à jour...' : 'Mettre à jour' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
