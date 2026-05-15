<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import AppearanceTabs from '@/components/AppearanceTabs.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { type BreadcrumbItem } from '@/types';

import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit } from '@/routes/appearance';
import { Palette } from 'lucide-vue-next';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Paramètres',
        href: edit().url,
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Apparence" />

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
                            <Palette class="h-6 w-6" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <HeadingSmall
                                title="Thème d’affichage"
                                description="Choisissez un thème clair, sombre ou celui du système. Le réglage est mémorisé sur cet appareil."
                            />
                        </div>
                    </div>
                </div>
                <div class="px-6 py-6 md:px-8 md:py-8">
                    <AppearanceTabs />
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
