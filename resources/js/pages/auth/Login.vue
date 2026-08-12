<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import { Form, Head } from '@inertiajs/vue3';
import { LoaderCircle, LogIn } from 'lucide-vue-next';

defineProps<{
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
}>();
</script>

<template>
    <Head title="Connexion" />

    <div class="relative flex min-h-dvh w-full overflow-hidden bg-white">
        <!-- Panneau visuel plein écran (desktop) -->
        <aside
            class="relative hidden w-[52%] shrink-0 overflow-hidden lg:block"
            aria-hidden="true"
        >
            <img
                src="/login.jpeg"
                alt=""
                class="absolute inset-0 h-full w-full object-cover object-center animate-login-image"
            />
            <div
                class="absolute inset-0 bg-gradient-to-t from-[#c40000]/75 via-[#c40000]/25 to-transparent"
            />
            <div class="absolute inset-y-0 right-0 w-24 bg-gradient-to-l from-white to-transparent" />

            <div
                class="absolute bottom-0 left-0 right-0 z-10 p-10 xl:p-14 animate-login-fade"
            >
                <p
                    class="max-w-md text-3xl font-semibold leading-tight tracking-tight text-white xl:text-4xl"
                >
                    Compagnie Financière Africaine
                </p>
                <p class="mt-3 max-w-sm text-sm leading-relaxed text-white/85">
                    Accédez à vos modules métier dans un environnement sécurisé.
                </p>
            </div>
        </aside>

        <!-- Formulaire -->
        <main
            class="relative flex w-full flex-1 flex-col justify-center px-6 py-10 sm:px-10 lg:px-14 xl:px-20"
        >
            <!-- Bandeau mobile avec image -->
            <div
                class="relative -mx-6 mb-8 h-40 overflow-hidden sm:-mx-10 sm:h-48 lg:hidden"
            >
                <img
                    src="/login.jpeg"
                    alt=""
                    class="absolute inset-0 h-full w-full object-cover object-top"
                />
                <div class="absolute inset-0 bg-gradient-to-t from-white via-white/40 to-transparent" />
            </div>

            <div class="mx-auto w-full max-w-md animate-login-panel">
                <div class="mb-8">
                    <img
                        src="/logo_Cofina.png"
                        alt="Cofina"
                        class="mb-8 h-14 w-auto object-contain sm:h-16"
                    />
                    <h1 class="text-3xl font-semibold tracking-tight text-neutral-900">
                        Connectez-vous
                    </h1>
                    <p class="mt-2 text-sm leading-relaxed text-neutral-500">
                        Entrez votre email et votre mot de passe pour accéder à
                        l'application.
                    </p>
                </div>

                <div
                    v-if="status"
                    class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700"
                >
                    {{ status }}
                </div>

                <Form
                    v-bind="store.form()"
                    :reset-on-success="['password']"
                    v-slot="{ errors, processing }"
                    class="flex flex-col gap-5"
                >
                    <div class="grid gap-2">
                        <Label
                            for="email"
                            class="text-sm font-medium text-neutral-700"
                        >
                            Email
                        </Label>
                        <Input
                            id="email"
                            type="email"
                            name="email"
                            required
                            autofocus
                            :tabindex="1"
                            autocomplete="email"
                            placeholder="votre.email@cofinacorp.com"
                            class="h-12 rounded-xl border-neutral-200 bg-neutral-50/80 px-4 text-neutral-900 shadow-none transition focus-visible:border-primary focus-visible:bg-white focus-visible:ring-primary/20"
                        />
                        <InputError :message="errors.email" />
                    </div>

                    <div class="grid gap-2">
                        <div class="flex items-center justify-between gap-3">
                            <Label
                                for="password"
                                class="text-sm font-medium text-neutral-700"
                            >
                                Mot de passe
                            </Label>
                            <TextLink
                                v-if="canResetPassword"
                                :href="request()"
                                class="text-xs font-medium text-primary hover:text-primary/80"
                                :tabindex="5"
                            >
                                Mot de passe oublié ?
                            </TextLink>
                        </div>
                        <Input
                            id="password"
                            type="password"
                            name="password"
                            required
                            :tabindex="2"
                            autocomplete="current-password"
                            placeholder="••••••••"
                            class="h-12 rounded-xl border-neutral-200 bg-neutral-50/80 px-4 text-neutral-900 shadow-none transition focus-visible:border-primary focus-visible:bg-white focus-visible:ring-primary/20"
                        />
                        <InputError :message="errors.password" />
                    </div>

                    <Label
                        for="remember"
                        class="flex cursor-pointer items-center gap-2.5 text-sm text-neutral-500"
                    >
                        <Checkbox
                            id="remember"
                            name="remember"
                            :tabindex="3"
                        />
                        <span>Se souvenir de moi</span>
                    </Label>

                    <Button
                        type="submit"
                        class="mt-2 h-12 w-full rounded-xl bg-primary text-base font-medium text-white shadow-lg shadow-primary/25 transition hover:bg-primary/90 hover:shadow-primary/35"
                        :tabindex="4"
                        :disabled="processing"
                        data-test="login-button"
                    >
                        <LoaderCircle
                            v-if="processing"
                            class="mr-2 h-4 w-4 animate-spin"
                        />
                        <LogIn v-else class="mr-2 h-4 w-4" />
                        {{ processing ? 'Connexion…' : 'Connexion' }}
                    </Button>
                </Form>

                <p class="mt-10 text-center text-xs text-neutral-400">
                    © {{ new Date().getFullYear() }} Cofina — Tous droits réservés
                </p>
            </div>
        </main>
    </div>
</template>

<style scoped>
@keyframes login-fade {
    from {
        opacity: 0;
        transform: translateY(12px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes login-panel {
    from {
        opacity: 0;
        transform: translateX(16px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes login-image {
    from {
        transform: scale(1.06);
        opacity: 0.7;
    }
    to {
        transform: scale(1);
        opacity: 1;
    }
}

.animate-login-fade {
    animation: login-fade 0.7s ease-out 0.25s both;
}

.animate-login-panel {
    animation: login-panel 0.55s ease-out both;
}

.animate-login-image {
    animation: login-image 1.1s ease-out both;
}
</style>
