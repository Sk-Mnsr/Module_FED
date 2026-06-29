<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { hydrateNavGroups } from '@/lib/navIcons';
import { type NavGroup } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const page = usePage();

const portalUrl = computed(() => {
    const nav = page.props.navigation as { groups?: { items?: { href?: string }[] }[] } | undefined;
    const href = nav?.groups?.[0]?.items?.[0]?.href;

    return href ?? '/portal';
});

const mainNavGroups = computed<NavGroup[]>(() => {
    const groups = (page.props.navigation as { groups?: NavGroup[] } | undefined)?.groups ?? [];

    return hydrateNavGroups(groups);
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader class="pb-4">
            <SidebarMenu>
                <SidebarMenuItem>
                    <Link :href="portalUrl" class="flex items-center p-2">
                        <AppLogo />
                    </Link>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="pt-4">
            <NavMain :groups="mainNavGroups" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
