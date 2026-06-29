<script setup lang="ts">
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import InputError from '@/components/InputError.vue';
import SignatureInput from '@/components/SignatureInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import {
    BadgeCheck,
    CheckCircle2,
    Mail,
    PenLine,
    Shield,
    UserCircle,
} from 'lucide-vue-next';

interface Props {
    mustVerifyEmail: boolean;
    status?: string;
    savedSignature?: string | null;
    roles?: { slug: string; label: string }[];
}

const props = defineProps<Props>();

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
    { title: 'Paramètres', href: edit().url },
];

const page = usePage();
const user = computed(() => page.props.auth.user as {
    name: string;
    email: string;
    email_verified_at: string | null;
    has_signature?: boolean;
    fonction?: string | null;
} | null);

const roleLabels = computed(() => props.roles ?? []);

const initials = computed(() => {
    const parts = (user.value?.name ?? '').trim().split(/\s+/).filter(Boolean);
    if (parts.length >= 2) {
        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }
    return (parts[0]?.[0] ?? '?').toUpperCase();
});

const hasSignature = computed(() => props.savedSignature || user.value?.has_signature);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Profil" />

        <SettingsLayout>
            <div class="space-y-6">
                <!-- En-tête profil -->
                <div class="overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
                    <div class="bg-gradient-to-br from-violet-600/90 via-violet-700 to-violet-900 px-6 py-8 text-white md:px-8">
                        <div class="flex flex-col gap-5 sm:flex-row sm:items-center">
                            <div
                                class="flex size-16 shrink-0 items-center justify-center rounded-2xl bg-white/15 text-2xl font-semibold ring-2 ring-white/25 backdrop-blur-sm"
                            >
                                {{ initials }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <h2 class="truncate text-xl font-semibold tracking-tight md:text-2xl">
                                    {{ user?.name }}
                                </h2>
                                <p class="mt-1 flex items-center gap-2 truncate text-sm text-violet-100">
                                    <Mail class="size-4 shrink-0" />
                                    {{ user?.email }}
                                </p>
                                <div v-if="roleLabels.length" class="mt-3 flex flex-wrap gap-1.5">
                                    <span
                                        v-for="role in roleLabels"
                                        :key="role.slug"
                                        class="rounded-full bg-white/15 px-2.5 py-0.5 text-xs font-medium text-violet-50"
                                    >
                                        {{ role.label }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex shrink-0 flex-col gap-2 sm:items-end">
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold"
                                    :class="hasSignature
                                        ? 'bg-emerald-500/20 text-emerald-50 ring-1 ring-emerald-400/30'
                                        : 'bg-amber-500/20 text-amber-50 ring-1 ring-amber-400/30'"
                                >
                                    <BadgeCheck v-if="hasSignature" class="size-3.5" />
                                    <PenLine v-else class="size-3.5" />
                                    {{ hasSignature ? 'Signature enregistrée' : 'Signature manquante' }}
                                </span>
                                <span
                                    v-if="user?.email_verified_at"
                                    class="inline-flex items-center gap-1 text-xs text-violet-200"
                                >
                                    <CheckCircle2 class="size-3.5" /> E-mail vérifié
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid gap-6 xl:grid-cols-2">
                    <!-- Informations -->
                    <section class="rounded-2xl border border-border bg-card shadow-sm">
                        <div class="border-b border-border px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="flex size-10 items-center justify-center rounded-xl bg-violet-100 text-violet-700 dark:bg-violet-950/50 dark:text-violet-300">
                                    <UserCircle class="size-5" />
                                </div>
                                <div>
                                    <h3 class="font-semibold text-foreground">Informations du compte</h3>
                                    <p class="text-xs text-muted-foreground">
                                        Nom et e-mail utilisés dans l’application.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="p-6">
                            <Form
                                v-bind="ProfileController.update.form()"
                                class="space-y-5"
                                v-slot="{ errors, processing, recentlySuccessful }"
                            >
                                <div class="space-y-2">
                                    <Label for="name">Nom complet</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        class="h-10"
                                        :default-value="user?.name"
                                        required
                                        autocomplete="name"
                                        placeholder="Prénom et nom"
                                    />
                                    <InputError :message="errors.name" />
                                </div>

                                <div class="space-y-2">
                                    <Label for="email">Adresse e-mail</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        name="email"
                                        class="h-10"
                                        :default-value="user?.email"
                                        required
                                        autocomplete="username"
                                        placeholder="prenom.nom@exemple.com"
                                    />
                                    <InputError :message="errors.email" />
                                </div>

                                <div
                                    v-if="mustVerifyEmail && !user?.email_verified_at"
                                    class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100"
                                >
                                    <p>
                                        Votre adresse e-mail n’est pas encore vérifiée.
                                        <Link
                                            :href="send()"
                                            as="button"
                                            class="font-medium text-violet-700 underline underline-offset-2 hover:text-violet-800 dark:text-violet-300"
                                        >
                                            Renvoyer l’e-mail de vérification
                                        </Link>
                                    </p>
                                    <p
                                        v-if="status === 'verification-link-sent'"
                                        class="mt-2 font-medium text-emerald-700 dark:text-emerald-400"
                                    >
                                        Un nouveau lien a été envoyé.
                                    </p>
                                </div>

                                <div class="flex flex-col gap-3 border-t border-border pt-4 sm:flex-row sm:items-center">
                                    <Button
                                        type="submit"
                                        :disabled="processing"
                                        data-test="update-profile-button"
                                        class="bg-violet-600 hover:bg-violet-700"
                                    >
                                        {{ processing ? 'Enregistrement…' : 'Enregistrer' }}
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
                                            class="flex items-center gap-2 text-sm font-medium text-emerald-700 dark:text-emerald-400"
                                        >
                                            <CheckCircle2 class="size-4 shrink-0" />
                                            Enregistré.
                                        </p>
                                    </Transition>
                                </div>
                            </Form>
                        </div>
                    </section>

                    <!-- Signature -->
                    <section class="rounded-2xl border border-border bg-card shadow-sm">
                        <div class="border-b border-border px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="flex size-10 items-center justify-center rounded-xl bg-violet-100 text-violet-700 dark:bg-violet-950/50 dark:text-violet-300">
                                    <PenLine class="size-5" />
                                </div>
                                <div>
                                    <h3 class="font-semibold text-foreground">Signature électronique</h3>
                                    <p class="text-xs text-muted-foreground">
                                        {{ hasSignature
                                            ? 'Modifiez votre signature si nécessaire.'
                                            : 'Requise pour signer ou valider les FED.' }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-5 p-6">
                            <div
                                v-if="!hasSignature"
                                class="flex gap-3 rounded-lg border border-violet-100 bg-violet-50/60 px-4 py-3 text-sm text-violet-950 dark:border-violet-900/50 dark:bg-violet-950/30 dark:text-violet-100"
                            >
                                <Shield class="mt-0.5 size-4 shrink-0 text-violet-600 dark:text-violet-400" />
                                <p>
                                    Dessinez votre signature ou importez une image (PNG, JPEG, WebP).
                                </p>
                            </div>

                            <SignatureInput
                                ref="signatureInputRef"
                                :saved-signature="savedSignature"
                            />

                            <div class="flex flex-col gap-3 border-t border-border pt-4 sm:flex-row sm:items-center">
                                <Button
                                    type="button"
                                    :disabled="signatureSaving"
                                    class="bg-violet-600 hover:bg-violet-700"
                                    @click="saveSignature"
                                >
                                    {{ signatureSaving ? 'Enregistrement…' : 'Enregistrer la signature' }}
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
                                        v-show="signatureSaved || status === 'signature-saved'"
                                        class="flex items-center gap-2 text-sm font-medium text-emerald-700 dark:text-emerald-400"
                                    >
                                        <CheckCircle2 class="size-4 shrink-0" />
                                        Signature enregistrée.
                                    </p>
                                </Transition>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
