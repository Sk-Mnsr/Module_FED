<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { Separator } from '@/components/ui/separator';
import { cn, toUrl, urlIsActive } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { show } from '@/routes/two-factor';
import { edit as editPassword } from '@/routes/user-password';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { KeyRound, Palette, ShieldCheck, UserRound } from 'lucide-vue-next';

const sidebarNavItems: NavItem[] = [
    { title: 'Profil', href: editProfile(), icon: UserRound },
    { title: 'Mot de passe', href: editPassword(), icon: KeyRound },
    { title: 'Double authentification', href: show(), icon: ShieldCheck },
    { title: 'Apparence', href: editAppearance(), icon: Palette },
];

const page = usePage();
const currentPath = computed(() => page.url.split('?')[0] ?? '');
</script>

<template>
    <div class="mx-auto w-full max-w-6xl px-4 py-6 lg:px-8">
        <Heading
            title="Paramètres"
            description="Profil, sécurité et préférences de votre compte"
        />

        <div class="mt-2 flex flex-col gap-6 lg:flex-row lg:gap-10">
            <aside class="lg:w-56 lg:shrink-0">
                <nav
                    class="flex gap-1 overflow-x-auto rounded-xl border border-border bg-card p-1 lg:flex-col lg:overflow-visible lg:p-1.5"
                    aria-label="Navigation paramètres"
                >
                    <Link
                        v-for="item in sidebarNavItems"
                        :key="toUrl(item.href!)"
                        :href="item.href!"
                        :class="cn(
                            'inline-flex min-w-max items-center gap-2 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors lg:w-full',
                            urlIsActive(item.href, currentPath)
                                ? 'bg-violet-100 text-violet-900 dark:bg-violet-950/50 dark:text-violet-100'
                                : 'text-muted-foreground hover:bg-muted/60 hover:text-foreground',
                        )"
                    >
                        <component :is="item.icon" class="size-4 shrink-0" />
                        {{ item.title }}
                    </Link>
                </nav>
            </aside>

            <Separator class="lg:hidden" />

            <div class="min-w-0 flex-1">
                <slot />
            </div>
        </div>
    </div>
</template>
