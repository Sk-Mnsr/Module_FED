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
import { LoaderCircle, Mail, Lock, LogIn } from 'lucide-vue-next';

defineProps<{
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
}>();
</script>

<template>
    <Head title="Connexion" />

    <div class="relative h-screen w-full bg-slate-50 overflow-hidden" >
    <!-- style="background-image:url('/bg2.png')"; -->
        <!-- Panneau rouge à gauche -->
        <div class="absolute left-0 top-0 h-full w-1/2 md:w-[45%] lg:w-[45%] rounded-br-[100px] md:rounded-br-[150px] bg-red-500 z-0 transition-all duration-300"></div>

        <!-- Conteneur du formulaire, centré sur tout l'écran pour chevaucher le panneau -->
        <div class="absolute inset-0 z-10 flex h-full w-full items-center justify-center p-6 overflow-y-auto">
            <div class="w-full max-w-2xl rounded-3xl bg-white p-10 shadow-2xl m-auto">
                <div class="mb-6 flex flex-col items-start gap-4">
                    <img src="/logo_Cofina.png" alt="Cofina" class="h-16 object-contain" />
                    <div>
                        <h1 class="text-3xl font-semibold text-gray-900">Connectez-vous</h1>
                        <p class="text-sm text-gray-500">
                            Entrez votre email et votre mot de passe pour accéder à l'application.
                        </p>
                    </div>
                </div>

                <div
                    v-if="status"
                    class="mb-6 rounded-lg border border-green-200 bg-green-50/50 p-4 text-center text-sm font-medium text-green-700"
                >
                    {{ status }}
                </div>

                <Form
                    v-bind="store.form()"
                    :reset-on-success="['password']"
                    v-slot="{ errors, processing }"
                    class="flex flex-col gap-6"
                >
                    <div class="grid gap-5">
                        <div class="grid gap-2">
                            <Label for="email" class="text-sm font-medium text-gray-700">Email</Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                required
                                autofocus
                                :tabindex="1"
                                autocomplete="email"
                                placeholder="Entrer email"
                                class="h-11 border-gray-200"
                            />
                            <InputError :message="errors.email" />
                        </div>

                        <div class="grid gap-2">
                            <div class="flex items-center justify-between">
                                <Label for="password" class="text-sm font-medium text-gray-700">Mot de passe</Label>
                                <TextLink
                                    v-if="canResetPassword"
                                    :href="request()"
                                    class="text-xs text-red-600 hover:text-red-700"
                                    :tabindex="5"
                                >
                                    Mot de passe oublié?
                                </TextLink>
                            </div>
                            <Input
                                id="password"
                                type="password"
                                name="password"
                                required
                                :tabindex="2"
                                autocomplete="current-password"
                                placeholder="Password"
                                class="h-11 border-gray-200"
                            />
                            <InputError :message="errors.password" />
                        </div>

                        <div class="flex items-center justify-between pt-1">
                            <Label for="remember" class="flex cursor-pointer items-center space-x-2.5 text-sm text-gray-500">
                                <Checkbox id="remember" name="remember" :tabindex="3" />
                                <span>Se souvenir de moi</span>
                            </Label>
                        </div>

                        <div class="flex justify-end">
                            <Button
                                type="submit"
                                class="h-10 rounded-md bg-red-600 px-6 text-white hover:bg-red-700"
                                :tabindex="4"
                                :disabled="processing"
                                data-test="login-button"
                            >
                                <template v-if="!processing">
                                    <LogIn class="mr-2 h-4 w-4" />
                                </template>
                                <LoaderCircle v-else class="mr-2 h-4 w-4 animate-spin" />
                                {{ processing ? 'Connexion...' : 'Connexion' }}
                            </Button>
                        </div>
                    </div>
                </Form>
            </div>
        </div>
    </div>
</template>
