<script setup lang="ts">
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

import InputError from '@/components/InputError.vue';
import SignatureInput from '@/components/SignatureInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { CheckCircle2, PenLine, UserCircle } from 'lucide-vue-next';

interface Props {
    mustVerifyEmail: boolean;
    status?: string;
}

defineProps<Props>();

const signatureInputRef = ref<InstanceType<typeof SignatureInput> | null>(null);
const signatureSaving = ref(false);
const signatureSaved = ref(false);

const saveSignature = () => {
    const signature = signatureInputRef.value?.getSignature();
    if (!signature) {
        alert('Veuillez dessiner ou téléverser votre signature.');
        return;
    }
    signatureSaving.value = true;
    signatureSaved.value = false;
    router.patch('/settings/profile/signature', { signature }, {
        preserveScroll: true,
        onFinish: () => { signatureSaving.value = false; },
        onSuccess: () => { signatureSaved.value = true; },
    });
};

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Paramètres',
        href: edit().url,
    },
];

const page = usePage();
const user = page.props.auth.user;
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Profil" />

        <SettingsLayout>
            <div class="flex flex-col space-y-8">
                <!-- Carte informations -->
                <div
                    class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm ring-1 ring-gray-100"
                >
                    <div
                        class="border-b border-gray-100 bg-gradient-to-r from-violet-50/80 to-white px-6 py-5 md:px-8"
                    >
                        <div class="flex items-start gap-4">
                            <div
                                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-violet-100 text-violet-700"
                            >
                                <UserCircle class="h-6 w-6" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <HeadingSmall
                                    title="Informations du compte"
                                    description="Ces données sont visibles dans l’application et peuvent être utilisées pour les exports ou la correspondance."
                                />
                            </div>
                        </div>
                    </div>

                    <div class="px-6 py-6 md:px-8 md:py-8">
                        <Form
                            v-bind="ProfileController.update.form()"
                            class="space-y-6"
                            v-slot="{ errors, processing, recentlySuccessful }"
                        >
                            <div class="space-y-2">
                                <Label for="name" class="text-base font-medium text-gray-800">
                                    Nom complet
                                </Label>
                                <Input
                                    id="name"
                                    class="h-11 border-gray-300 bg-white shadow-sm focus-visible:border-violet-400 focus-visible:ring-violet-400/20"
                                    name="name"
                                    :default-value="user.name"
                                    required
                                    autocomplete="name"
                                    placeholder="Prénom et nom"
                                />
                                <InputError :message="errors.name" />
                            </div>

                            <div class="space-y-2">
                                <Label for="email" class="text-base font-medium text-gray-800">
                                    Adresse e-mail
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    class="h-11 border-gray-300 bg-white shadow-sm focus-visible:border-violet-400 focus-visible:ring-violet-400/20"
                                    name="email"
                                    :default-value="user.email"
                                    required
                                    autocomplete="username"
                                    placeholder="prenom.nom@exemple.com"
                                />
                                <InputError :message="errors.email" />
                            </div>

                            <div v-if="mustVerifyEmail && !user.email_verified_at" class="rounded-lg border border-amber-100 bg-amber-50/80 px-4 py-3 text-sm text-amber-950">
                                <p>
                                    Votre adresse e-mail n’est pas encore vérifiée.
                                    <Link
                                        :href="send()"
                                        as="button"
                                        class="font-medium text-violet-700 underline decoration-violet-300 underline-offset-2 hover:text-violet-800"
                                    >
                                        Renvoyer l’e-mail de vérification
                                    </Link>
                                </p>
                                <p
                                    v-if="status === 'verification-link-sent'"
                                    class="mt-2 font-medium text-emerald-700"
                                >
                                    Un nouveau lien a été envoyé à votre adresse e-mail.
                                </p>
                            </div>

                            <div class="flex flex-col gap-4 pt-2 sm:flex-row sm:items-center">
                                <Button
                                    type="submit"
                                    :disabled="processing"
                                    data-test="update-profile-button"
                                    class="h-11 bg-violet-600 px-8 text-base font-medium shadow-sm hover:bg-violet-700"
                                >
                                    {{ processing ? 'Enregistrement…' : 'Enregistrer les modifications' }}
                                </Button>

                                <Transition
                                    enter-active-class="transition ease-out duration-200"
                                    enter-from-class="opacity-0 translate-y-1"
                                    enter-to-class="opacity-100 translate-y-0"
                                    leave-active-class="transition ease-in duration-150"
                                    leave-from-class="opacity-100"
                                    leave-to-class="opacity-0"
                                >
                                    <p
                                        v-show="recentlySuccessful"
                                        class="flex items-center gap-2 text-sm font-medium text-emerald-700"
                                    >
                                        <CheckCircle2 class="h-4 w-4 shrink-0" />
                                        Modifications enregistrées.
                                    </p>
                                </Transition>
                            </div>
                        </Form>
                    </div>
                </div>

                <!-- Carte signature -->
                <div
                    class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm ring-1 ring-gray-100"
                >
                    <div
                        class="border-b border-gray-100 bg-gradient-to-r from-violet-50/80 to-white px-6 py-5 md:px-8"
                    >
                        <div class="flex items-start gap-4">
                            <div
                                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-violet-100 text-violet-700"
                            >
                                <PenLine class="h-6 w-6" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <HeadingSmall
                                    title="Signature"
                                    description="Utilisée pour la soumission ou la validation des fiches de dépense (FED) lorsque la signature électronique est requise."
                                />
                            </div>
                        </div>
                    </div>

                    <div class="space-y-6 px-6 py-6 md:px-8 md:py-8">
                        <SignatureInput ref="signatureInputRef" />
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                            <Button
                                type="button"
                                :disabled="signatureSaving"
                                class="h-11 bg-violet-600 px-8 text-base font-medium shadow-sm hover:bg-violet-700"
                                @click="saveSignature"
                            >
                                {{ signatureSaving ? 'Enregistrement…' : 'Enregistrer ma signature' }}
                            </Button>
                            <Transition
                                enter-active-class="transition ease-out duration-200"
                                enter-from-class="opacity-0"
                                enter-to-class="opacity-100"
                                leave-active-class="transition ease-in duration-150"
                                leave-from-class="opacity-100"
                                leave-to-class="opacity-0"
                            >
                                <p
                                    v-show="signatureSaved"
                                    class="flex items-center gap-2 text-sm font-medium text-emerald-700"
                                >
                                    <CheckCircle2 class="h-4 w-4 shrink-0" />
                                    Signature enregistrée.
                                </p>
                            </Transition>
                        </div>
                        <p v-if="user?.has_signature" class="text-sm text-muted-foreground">
                            Une signature est déjà enregistrée. Dessinez ou importez-en une nouvelle pour la remplacer.
                        </p>
                    </div>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
