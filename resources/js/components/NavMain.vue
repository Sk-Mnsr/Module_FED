<script setup lang="ts">
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { navUrlMatchScore, toUrl } from '@/lib/utils';
import { resolveNavIcon } from '@/lib/navIcons';
import { type NavGroup, type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import type { InertiaLinkProps } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    groups: NavGroup[];
}>();

const page = usePage();
const openStates = ref<Record<string, boolean>>({});

const menuKey = (...parts: (string | number)[]): string => parts.join(':');

type NavMatch = {
    href: string;
    score: number;
    openKeys: string[];
};

const findBestNavMatch = (items: NavItem[], prefix: string, openKeys: string[] = []): NavMatch | null => {
    let best: NavMatch | null = null;

    items.forEach((item, index) => {
        const key = menuKey(prefix, index);
        const branchOpenKeys = item.items?.length ? [...openKeys, key] : openKeys;

        if (item.items?.length) {
            const childMatch = findBestNavMatch(item.items, key, branchOpenKeys);
            if (childMatch && (!best || childMatch.score > best.score)) {
                best = childMatch;
            }
        }

        if (item.href) {
            const href = toUrl(item.href);
            const score = navUrlMatchScore(item.href, page.url);

            if (score >= 0 && (!best || score > best.score)) {
                best = { href, score, openKeys: branchOpenKeys };
            }
        }
    });

    return best;
};

const activeNavMatch = computed(() => {
    let best: NavMatch | null = null;

    props.groups.forEach((group, groupIndex) => {
        const match = findBestNavMatch(group.items, `g${groupIndex}`);
        if (match && (!best || match.score > best.score)) {
            best = match;
        }
    });

    return best;
});

const isItemActive = (item: NavItem): boolean => {
    if (item.href) {
        return activeNavMatch.value?.href === toUrl(item.href);
    }

    if (item.items) {
        return item.items.some((subItem) => isItemActive(subItem));
    }

    return false;
};

const isSubItemActive = (href?: NonNullable<InertiaLinkProps['href']>): boolean => {
    if (!href || !activeNavMatch.value) {
        return false;
    }

    return activeNavMatch.value.href === toUrl(href);
};

const collapsibleSiblingKeys = (items: NavItem[], keyForIndex: (index: number) => string): string[] =>
    items
        .map((item, index) => (item.items?.length ? keyForIndex(index) : null))
        .filter((key): key is string => key !== null);

const syncOpenStatesFromRoute = (): void => {
    const states: Record<string, boolean> = {};
    activeNavMatch.value?.openKeys.forEach((key) => {
        states[key] = true;
    });

    openStates.value = states;
};

const isMenuOpen = (key: string): boolean => openStates.value[key] === true;

const setMenuOpen = (key: string, open: boolean, siblingKeys: string[]): void => {
    if (open) {
        siblingKeys.forEach((siblingKey) => {
            openStates.value[siblingKey] = false;
        });
        openStates.value[key] = true;
        return;
    }

    openStates.value[key] = false;
};

watch(() => page.url, syncOpenStatesFromRoute, { immediate: true });

watch(
    () => props.groups,
    () => syncOpenStatesFromRoute(),
    { deep: true },
);
</script>

<template>
    <SidebarGroup
        v-for="(group, groupIndex) in groups"
        :key="groupIndex"
        class="px-2 py-0"
    >
        <SidebarGroupLabel v-if="group.label">{{ group.label }}</SidebarGroupLabel>
        <SidebarMenu>
            <template v-for="(item, itemIndex) in group.items" :key="`${groupIndex}-${itemIndex}-${item.title || 'sep'}`">
                <div v-if="item.separator" class="my-4 border-t border-sidebar-border" />
                <SidebarMenuItem>
                    <Collapsible
                        v-if="item.items && item.items.length > 0"
                        :open="isMenuOpen(menuKey(`g${groupIndex}`, itemIndex))"
                        @update:open="(open) => setMenuOpen(
                            menuKey(`g${groupIndex}`, itemIndex),
                            open,
                            collapsibleSiblingKeys(group.items, (index) => menuKey(`g${groupIndex}`, index)),
                        )"
                    >
                        <template #default="{ open }">
                            <CollapsibleTrigger as-child>
                                <SidebarMenuButton
                                    :is-active="isItemActive(item)"
                                    :tooltip="item.title"
                                >
                                    <component :is="resolveNavIcon(item.icon)" v-if="resolveNavIcon(item.icon)" />
                                    <span>{{ item.title }}</span>
                                    <ChevronRight class="ml-auto size-5 transition-transform duration-200" :class="{ 'rotate-90': open }" />
                                </SidebarMenuButton>
                            </CollapsibleTrigger>
                            <CollapsibleContent>
                                <SidebarMenuSub>
                                    <SidebarMenuSubItem v-for="(subItem, subIndex) in item.items" :key="subItem.title">
                                        <Collapsible
                                            v-if="subItem.items && subItem.items.length > 0"
                                            :open="isMenuOpen(menuKey(`g${groupIndex}`, itemIndex, subIndex))"
                                            @update:open="(open) => setMenuOpen(
                                                menuKey(`g${groupIndex}`, itemIndex, subIndex),
                                                open,
                                                collapsibleSiblingKeys(item.items!, (index) => menuKey(`g${groupIndex}`, itemIndex, index)),
                                            )"
                                        >
                                            <template #default="{ open: subOpen }">
                                                <CollapsibleTrigger as-child>
                                                    <SidebarMenuSubButton
                                                        :is-active="isItemActive(subItem)"
                                                    >
                                                        <span>{{ subItem.title }}</span>
                                                        <ChevronRight class="ml-auto size-4 transition-transform duration-200" :class="{ 'rotate-90': subOpen }" />
                                                    </SidebarMenuSubButton>
                                                </CollapsibleTrigger>
                                                <CollapsibleContent>
                                                    <SidebarMenuSub>
                                                        <SidebarMenuSubItem v-for="subSubItem in subItem.items" :key="subSubItem.title">
                                                            <SidebarMenuSubButton
                                                                v-if="subSubItem.href"
                                                                as-child
                                                                :is-active="isSubItemActive(subSubItem.href)"
                                                            >
                                                                <Link :href="subSubItem.href">
                                                                    <span>{{ subSubItem.title }}</span>
                                                                </Link>
                                                            </SidebarMenuSubButton>
                                                            <SidebarMenuSubButton
                                                                v-else-if="subSubItem.onClick"
                                                                :is-active="false"
                                                                @click="subSubItem.onClick"
                                                            >
                                                                <span>{{ subSubItem.title }}</span>
                                                            </SidebarMenuSubButton>
                                                            <SidebarMenuSubButton
                                                                v-else
                                                                :is-active="false"
                                                                disabled
                                                            >
                                                                <span>{{ subSubItem.title }}</span>
                                                            </SidebarMenuSubButton>
                                                        </SidebarMenuSubItem>
                                                    </SidebarMenuSub>
                                                </CollapsibleContent>
                                            </template>
                                        </Collapsible>
                                        <SidebarMenuSubButton
                                            v-else-if="subItem.href"
                                            as-child
                                            :is-active="isSubItemActive(subItem.href)"
                                        >
                                            <Link :href="subItem.href">
                                                <span>{{ subItem.title }}</span>
                                            </Link>
                                        </SidebarMenuSubButton>
                                        <SidebarMenuSubButton
                                            v-else-if="subItem.onClick"
                                            :is-active="false"
                                            @click="subItem.onClick"
                                        >
                                            <span>{{ subItem.title }}</span>
                                        </SidebarMenuSubButton>
                                        <SidebarMenuSubButton
                                            v-else
                                            :is-active="false"
                                            disabled
                                        >
                                            <span>{{ subItem.title }}</span>
                                        </SidebarMenuSubButton>
                                    </SidebarMenuSubItem>
                                </SidebarMenuSub>
                            </CollapsibleContent>
                        </template>
                    </Collapsible>
                    <SidebarMenuButton
                        v-else
                        as-child
                        :is-active="isSubItemActive(item.href)"
                        :tooltip="item.title"
                    >
                        <Link :href="item.href">
                            <component :is="resolveNavIcon(item.icon)" v-if="resolveNavIcon(item.icon)" />
                            <span>{{ item.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </template>
        </SidebarMenu>
    </SidebarGroup>
</template>
