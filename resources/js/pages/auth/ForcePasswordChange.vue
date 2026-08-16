<script setup lang="ts">
import PasswordController from '@/actions/App/Http/Controllers/Settings/PasswordController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { KeyRound, LogOut, Shield } from 'lucide-vue-next';
import { computed } from 'vue';
import { logout } from '@/routes';

const page = usePage();
const userName = computed(
    () => (page.props.auth as { user?: { name?: string } })?.user?.name ?? '',
);
</script>

<template>
    <Head title="Changer votre mot de passe" />

    <div class="relative flex min-h-dvh w-full overflow-hidden bg-white">
        <aside
            class="relative hidden w-[48%] shrink-0 overflow-hidden lg:block"
            aria-hidden="true"
        >
            <img
                src="/login.jpeg"
                alt=""
                class="absolute inset-0 h-full w-full object-cover object-center"
            />
            <div
                class="absolute inset-0 bg-gradient-to-t from-[#c40000]/80 via-[#c40000]/30 to-transparent"
            />
            <div class="absolute inset-y-0 right-0 w-24 bg-gradient-to-l from-white to-transparent" />

            <div class="absolute bottom-0 left-0 right-0 z-10 p-10 xl:p-14">
                <p class="max-w-md text-3xl font-semibold leading-tight tracking-tight text-white xl:text-4xl">
                    Première connexion
                </p>
                <p class="mt-3 max-w-sm text-sm leading-relaxed text-white/85">
                    Pour sécuriser votre compte, choisissez un nouveau mot de passe avant d’accéder aux modules.
                </p>
            </div>
        </aside>

        <main
            class="relative flex w-full flex-1 flex-col justify-center px-6 py-10 sm:px-10 lg:px-14 xl:px-20"
        >
            <div class="mx-auto w-full max-w-md">
                <img
                    src="/logo_Cofina.png"
                    alt="Cofina"
                    class="mb-8 h-14 w-auto object-contain sm:h-16"
                />

                <div class="mb-2 flex items-center gap-3">
                    <div
                        class="flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10 text-primary"
                    >
                        <KeyRound class="h-5 w-5" />
                    </div>
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight text-neutral-900 sm:text-3xl">
                            Nouveau mot de passe
                        </h1>
                        <p v-if="userName" class="text-sm text-neutral-500">
                            {{ userName }}
                        </p>
                    </div>
                </div>

                <p class="mt-4 text-sm leading-relaxed text-neutral-600">
                    Votre compte nécessite un changement de mot de passe avant de continuer.
                </p>

                <div
                    class="mt-6 mb-8 flex gap-3 rounded-lg border border-primary/20 bg-primary/5 px-4 py-3 text-sm text-gray-700"
                >
                    <Shield class="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                    <ul class="list-inside list-disc space-y-1 marker:text-primary/60">
                        <li>Minimum <strong>8 caractères</strong></li>
                        <li>Privilégiez lettres, chiffres et signes (ex. ! ? %)</li>
                        <li>Évitez le mot de passe temporaire fourni par l’équipe IT</li>
                    </ul>
                </div>

                <Form
                    v-bind="PasswordController.update.form()"
                    :options="{ preserveScroll: true }"
                    reset-on-success
                    :reset-on-error="['password', 'password_confirmation', 'current_password']"
                    class="space-y-5"
                    v-slot="{ errors, processing }"
                >
                    <div class="space-y-2">
                        <Label for="current_password">Mot de passe actuel</Label>
                        <Input
                            id="current_password"
                            name="current_password"
                            type="password"
                            required
                            class="h-11 border-gray-300 bg-white shadow-sm focus-visible:border-primary focus-visible:ring-primary/30"
                            autocomplete="current-password"
                            placeholder="Mot de passe temporaire"
                        />
                        <InputError :message="errors.current_password" />
                    </div>

                    <div class="space-y-2">
                        <Label for="password">Nouveau mot de passe</Label>
                        <Input
                            id="password"
                            name="password"
                            type="password"
                            required
                            class="h-11 border-gray-300 bg-white shadow-sm focus-visible:border-primary focus-visible:ring-primary/30"
                            autocomplete="new-password"
                            placeholder="min. 8 caractères"
                        />
                        <InputError :message="errors.password" />
                    </div>

                    <div class="space-y-2">
                        <Label for="password_confirmation">Confirmer le mot de passe</Label>
                        <Input
                            id="password_confirmation"
                            name="password_confirmation"
                            type="password"
                            required
                            class="h-11 border-gray-300 bg-white shadow-sm focus-visible:border-primary focus-visible:ring-primary/30"
                            autocomplete="new-password"
                            placeholder="Répétez le nouveau mot de passe"
                        />
                        <InputError :message="errors.password_confirmation" />
                    </div>

                    <Button
                        type="submit"
                        :disabled="processing"
                        class="h-11 w-full bg-primary text-base font-medium shadow-sm hover:bg-primary/90"
                    >
                        {{ processing ? 'Enregistrement…' : 'Enregistrer et continuer' }}
                    </Button>
                </Form>

                <div class="mt-8 border-t border-neutral-100 pt-6">
                    <Link
                        :href="logout()"
                        method="post"
                        as="button"
                        class="inline-flex items-center gap-2 text-sm text-neutral-500 transition hover:text-neutral-800"
                    >
                        <LogOut class="h-4 w-4" />
                        Se déconnecter
                    </Link>
                </div>
            </div>
        </main>
    </div>
</template>
