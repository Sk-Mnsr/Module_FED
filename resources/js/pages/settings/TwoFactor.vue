<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import TwoFactorRecoveryCodes from '@/components/TwoFactorRecoveryCodes.vue';
import TwoFactorSetupModal from '@/components/TwoFactorSetupModal.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTwoFactorAuth } from '@/composables/useTwoFactorAuth';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { show } from '@/routes/two-factor';
import { BreadcrumbItem } from '@/types';
import { Form, Head } from '@inertiajs/vue3';
import { Shield, ShieldBan, ShieldCheck } from 'lucide-vue-next';
import { onUnmounted, ref } from 'vue';

interface Props {
    requiresConfirmation?: boolean;
    twoFactorEnabled?: boolean;
}

withDefaults(defineProps<Props>(), {
    requiresConfirmation: false,
    twoFactorEnabled: false,
});

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Paramètres',
        href: show.url(),
    },
];

const { hasSetupData, clearTwoFactorAuthData } = useTwoFactorAuth();
const showSetupModal = ref<boolean>(false);

const enableTwoFactorForm = {
    action: '/user/two-factor-authentication',
    method: 'post' as const,
};

const disableTwoFactorForm = {
    action: '/user/two-factor-authentication',
    method: 'post' as const,
};

onUnmounted(() => {
    clearTwoFactorAuthData();
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Double authentification" />
        <SettingsLayout>
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
                            <Shield class="h-6 w-6" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <HeadingSmall
                                title="Double authentification (2FA)"
                                description="Renforcez la sécurité : une application d’authentification (TOTP) sur votre téléphone fournira un code à saisir à la connexion."
                            />
                        </div>
                    </div>
                </div>

                <div class="space-y-6 px-6 py-6 md:px-8 md:py-8">
                    <div
                        v-if="!twoFactorEnabled"
                        class="flex flex-col items-start space-y-4"
                    >
                        <Badge variant="destructive" class="text-xs font-medium">
                            Désactivée
                        </Badge>

                        <p class="max-w-prose text-sm leading-relaxed text-muted-foreground">
                            Après activation, un code à usage unique vous sera demandé à chaque connexion
                            (via une app du type Google Authenticator, Microsoft Authenticator, etc.).
                        </p>

                        <div class="flex flex-wrap gap-3 pt-1">
                            <Button
                                v-if="hasSetupData"
                                class="h-11 bg-violet-600 px-6 font-medium hover:bg-violet-700"
                                @click="showSetupModal = true"
                            >
                                <ShieldCheck class="mr-2 h-4 w-4" />
                                Poursuivre la configuration
                            </Button>
                            <Form
                                v-else
                                v-bind="enableTwoFactorForm"
                                #default="{ processing }"
                                @success="showSetupModal = true"
                            >
                                <Button
                                    type="submit"
                                    class="h-11 bg-violet-600 px-6 font-medium hover:bg-violet-700"
                                    :disabled="processing"
                                >
                                    <ShieldCheck class="mr-2 h-4 w-4" />
                                    Activer la 2FA
                                </Button>
                            </Form>
                        </div>
                    </div>

                    <div
                        v-else
                        class="flex flex-col items-start space-y-5"
                    >
                        <Badge class="bg-emerald-600 text-xs font-medium hover:bg-emerald-600">
                            Activée
                        </Badge>

                        <p class="max-w-prose text-sm leading-relaxed text-muted-foreground">
                            La double authentification est active. Conservez vos
                            <strong class="text-foreground">codes de récupération</strong>
                            dans un endroit sûr au cas où vous perdriez l’accès à votre téléphone.
                        </p>

                        <TwoFactorRecoveryCodes />

                        <div class="pt-1">
                            <Form v-bind="disableTwoFactorForm" #default="{ processing }">
                                <input type="hidden" name="_method" value="DELETE" />
                                <Button
                                    variant="destructive"
                                    type="submit"
                                    class="h-11 px-6"
                                    :disabled="processing"
                                >
                                    <ShieldBan class="mr-2 h-4 w-4" />
                                    Désactiver la 2FA
                                </Button>
                            </Form>
                        </div>
                    </div>
                </div>

                <TwoFactorSetupModal
                    v-model:isOpen="showSetupModal"
                    :requiresConfirmation="requiresConfirmation"
                    :twoFactorEnabled="twoFactorEnabled"
                />
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
