<script setup lang="ts">
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import FormSection from '@/components/FormSection.vue';
import { Plus, Trash2 } from 'lucide-vue-next';

const props = defineProps<{
    appelOffre: any;
    comite?: any;
    users: any[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Appels d\'Offres',
        href: '/appel-offres',
    },
    {
        title: props.appelOffre.reference,
        href: `/appel-offres/${props.appelOffre.id}`,
    },
    {
        title: 'Gestion du Comité',
        href: '#',
    },
];

const isEdit = !!props.comite;

const form = useForm({
    nom: props.comite?.nom || `Comité d'évaluation - ${props.appelOffre.reference}`,
    membres: props.comite?.membres?.map((m: any) => ({
        user_id: m.id,
        role: m.pivot.role,
    })) || [
        { user_id: '', role: 'president' }
    ],
});

const roles = [
    { value: 'president', label: 'Président du comité' },
    { value: 'secretaire', label: 'Secrétaire' },
    { value: 'membre', label: 'Membre évaluateur' },
];

const addMembre = () => {
    form.membres.push({ user_id: '', role: 'membre' });
};

const removeMembre = (index: number) => {
    form.membres.splice(index, 1);
};

const submit = () => {
    if (isEdit) {
        form.put(`/comites/${props.comite.id}`, { preserveScroll: true });
    } else {
        form.post(`/appel-offres/${props.appelOffre.id}/comites`, { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Gestion du Comité" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6 max-w-4xl mx-auto">
            <div class="flex items-center justify-between mb-2">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">
                        {{ isEdit ? 'Modifier le comité' : 'Créer le comité d\'évaluation' }}
                    </h1>
                    <p class="text-gray-500 mt-1">Appel d'offres : {{ appelOffre.reference }} - {{ appelOffre.objet }}</p>
                </div>
            </div>

            <form @submit.prevent="submit" class="flex flex-col gap-6">
                <FormSection title="Détails du Comité" :columns="1">
                    <div>
                        <Label for="nom" class="text-base font-medium text-gray-700">Nom du comité</Label>
                        <Input id="nom" v-model="form.nom" type="text" class="mt-1.5 border-gray-300" required :disabled="isEdit" />
                        <InputError :message="form.errors.nom" />
                    </div>
                </FormSection>

                <FormSection title="Membres du Comité" :columns="1">
                    <div class="flex items-center justify-between mb-4">
                        <p class="text-sm text-gray-600">Sélectionnez les utilisateurs qui participeront à l'évaluation.</p>
                        <Button type="button" variant="outline" size="sm" @click="addMembre" class="p-2 h-8 w-8">
                            <Plus class="h-4 w-4" />
                        </Button>
                    </div>

                    <div v-for="(membre, index) in form.membres" :key="index" class="flex items-end gap-4 mb-4 bg-gray-50 p-4 rounded-md border border-gray-200">
                        <div class="flex-1">
                            <Label :for="`user-${index}`" class="text-sm font-medium text-gray-700">Utilisateur</Label>
                            <select
                                :id="`user-${index}`"
                                v-model="membre.user_id"
                                class="mt-1.5 flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-sm text-gray-900"
                                required
                            >
                                <option value="" disabled>-- Sélectionner --</option>
                                <option v-for="user in users" :key="user.id" :value="user.id">
                                    {{ user.name }} ({{ user.email }})
                                </option>
                            </select>
                            <InputError :message="form.errors[`membres.${index}.user_id` as keyof typeof form.errors]" />
                        </div>
                        <div class="flex-1">
                            <Label :for="`role-${index}`" class="text-sm font-medium text-gray-700">Rôle</Label>
                            <select
                                :id="`role-${index}`"
                                v-model="membre.role"
                                class="mt-1.5 flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-sm text-gray-900"
                                required
                            >
                                <option v-for="role in roles" :key="role.value" :value="role.value">
                                    {{ role.label }}
                                </option>
                            </select>
                            <InputError :message="form.errors[`membres.${index}.role` as keyof typeof form.errors]" />
                        </div>
                        <div v-if="form.membres.length > 1">
                            <Button type="button" variant="ghost" class="text-red-600 hover:text-red-700 hover:bg-red-50 p-2 h-9 w-9" @click="removeMembre(index)" title="Retirer">
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </FormSection>

                <div class="flex justify-end gap-2 mt-4">
                    <Link :href="`/appel-offres/${appelOffre.id}`">
                        <Button type="button" variant="outline">Annuler</Button>
                    </Link>
                    <Button type="submit" :disabled="form.processing" class="bg-blue-600 hover:bg-blue-700 text-white">
                        {{ form.processing ? 'Enregistrement...' : 'Enregistrer le comité' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
