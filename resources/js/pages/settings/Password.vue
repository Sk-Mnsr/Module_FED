<script setup lang="ts">
import PasswordController from '@/actions/App/Http/Controllers/Settings/PasswordController';
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { edit } from '@/routes/user-password';
import { Form, Head, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type BreadcrumbItem } from '@/types';
import { AlertTriangle, CheckCircle2, KeyRound, Shield } from 'lucide-vue-next';

const page = usePage<{ flash?: { warning?: string } }>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Paramètres',
        href: edit().url,
    },
];

const passwordInput = ref<HTMLInputElement | null>(null);
const currentPasswordInput = ref<HTMLInputElement | null>(null);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Mot de passe" />

        <SettingsLayout>
            <div class="space-y-8">
                <Alert
                    v-if="page.props.flash?.warning"
                    class="border-amber-200 bg-amber-50 text-amber-950 [&>svg]:text-amber-600"
                >
                    <AlertTriangle class="size-4 shrink-0" />
                    <AlertTitle>Action requise</AlertTitle>
                    <AlertDescription>
                        {{ page.props.flash.warning }}
                    </AlertDescription>
                </Alert>

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
                                <KeyRound class="h-6 w-6" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <HeadingSmall
                                    title="Mot de passe"
                                    description="Choisissez un mot de passe solide. Surtout si votre compte a été récemment créé ou modifié par un administrateur."
                                />
                            </div>
                        </div>
                    </div>

                    <div class="px-6 py-6 md:px-8 md:py-8">
                        <div
                            class="mb-8 flex gap-3 rounded-lg border border-violet-100 bg-violet-50/50 px-4 py-3 text-sm text-gray-700"
                        >
                            <Shield class="mt-0.5 h-4 w-4 shrink-0 text-violet-600" />
                            <ul class="list-inside list-disc space-y-1 marker:text-violet-400">
                                <li>Minimum <strong>8 caractères</strong></li>
                                <li>Privilégiez lettres, chiffres et signes (ex. ! ? %)</li>
                                <li>Évitez le mot de passe temporaire fourni par l’équipe IT</li>
                            </ul>
                        </div>

                        <Form
                            v-bind="PasswordController.update.form()"
                            :options="{
                                preserveScroll: true,
                            }"
                            reset-on-success
                            :reset-on-error="[
                                'password',
                                'password_confirmation',
                                'current_password',
                            ]"
                            class="space-y-6"
                            v-slot="{ errors, processing, recentlySuccessful }"
                        >
                            <div class="space-y-2">
                                <Label for="current_password" class="text-base font-medium text-gray-800">
                                    Mot de passe actuel
                                </Label>
                                <Input
                                    id="current_password"
                                    ref="currentPasswordInput"
                                    name="current_password"
                                    type="password"
                                    class="h-11 border-gray-300 bg-white shadow-sm focus-visible:border-violet-400 focus-visible:ring-violet-400/20"
                                    autocomplete="current-password"
                                    placeholder="Saisissez votre mot de passe actuel"
                                />
                                <InputError :message="errors.current_password" />
                            </div>

                            <div class="space-y-2">
                                <Label for="password" class="text-base font-medium text-gray-800">
                                    Nouveau mot de passe
                                </Label>
                                <Input
                                    id="password"
                                    ref="passwordInput"
                                    name="password"
                                    type="password"
                                    class="h-11 border-gray-300 bg-white shadow-sm focus-visible:border-violet-400 focus-visible:ring-violet-400/20"
                                    autocomplete="new-password"
                                    placeholder="Nouveau mot de passe (min. 8 caractères)"
                                />
                                <InputError :message="errors.password" />
                            </div>

                            <div class="space-y-2">
                                <Label for="password_confirmation" class="text-base font-medium text-gray-800">
                                    Confirmer le mot de passe
                                </Label>
                                <Input
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    type="password"
                                    class="h-11 border-gray-300 bg-white shadow-sm focus-visible:border-violet-400 focus-visible:ring-violet-400/20"
                                    autocomplete="new-password"
                                    placeholder="Répétez le nouveau mot de passe"
                                />
                                <InputError :message="errors.password_confirmation" />
                            </div>

                            <div class="flex flex-col gap-4 pt-2 sm:flex-row sm:items-center sm:justify-between">
                                <Button
                                    type="submit"
                                    :disabled="processing"
                                    data-test="update-password-button"
                                    class="h-11 bg-violet-600 px-8 text-base font-medium shadow-sm hover:bg-violet-700"
                                >
                                    {{ processing ? 'Enregistrement…' : 'Enregistrer le mot de passe' }}
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
                                        Mot de passe mis à jour.
                                    </p>
                                </Transition>
                            </div>
                        </Form>
                    </div>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
