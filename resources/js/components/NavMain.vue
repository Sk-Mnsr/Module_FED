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
import { toUrl, urlIsActive } from '@/lib/utils';
import { type NavGroup, type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import type { InertiaLinkProps } from '@inertiajs/vue3';

defineProps<{
    groups: NavGroup[];
}>();

const page = usePage();

const isItemActive = (item: NavItem): boolean => {
    if (item.href) {
        return urlIsActive(item.href, page.url);
    }
    if (item.items) {
        return item.items.some(subItem => isItemActive(subItem));
    }
    return false;
};

const isSubItemActive = (href?: NonNullable<InertiaLinkProps['href']>): boolean => {
    if (!href) return false;
    return urlIsActive(toUrl(href), page.url);
};
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
                <!-- Séparateur avant l'item -->
                <div v-if="item.separator" class="my-4 border-t border-sidebar-border" />
                <SidebarMenuItem>
                    <!-- Menu avec sous-menus -->
                    <Collapsible v-if="item.items && item.items.length > 0" :default-open="isItemActive(item)">
                        <template #default="{ open }">
                            <CollapsibleTrigger as-child>
                                <SidebarMenuButton
                                    :is-active="isItemActive(item)"
                                    :tooltip="item.title"
                                >
                                    <component :is="item.icon" />
                                    <span>{{ item.title }}</span>
                                    <ChevronRight class="ml-auto size-5 transition-transform duration-200" :class="{ 'rotate-90': open }" />
                                </SidebarMenuButton>
                            </CollapsibleTrigger>
                            <CollapsibleContent>
                                <SidebarMenuSub>
                                    <SidebarMenuSubItem v-for="subItem in item.items" :key="subItem.title">
                                        <!-- Sous-item avec ses propres sous-items (niveau 3) -->
                                        <Collapsible v-if="subItem.items && subItem.items.length > 0" :default-open="isItemActive(subItem)">
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
                                        <!-- Sous-item simple avec href -->
                                        <SidebarMenuSubButton
                                            v-else-if="subItem.href"
                                            as-child
                                            :is-active="isSubItemActive(subItem.href)"
                                        >
                                            <Link :href="subItem.href">
                                                <span>{{ subItem.title }}</span>
                                            </Link>
                                        </SidebarMenuSubButton>
                                        <!-- Sous-item avec onClick -->
                                        <SidebarMenuSubButton
                                            v-else-if="subItem.onClick"
                                            :is-active="false"
                                            @click="subItem.onClick"
                                        >
                                            <span>{{ subItem.title }}</span>
                                        </SidebarMenuSubButton>
                                        <!-- Sous-item désactivé -->
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
                    <!-- Menu simple sans sous-menus -->
                    <SidebarMenuButton
                        v-else
                        as-child
                        :is-active="urlIsActive(item.href!, page.url)"
                        :tooltip="item.title"
                    >
                        <Link :href="item.href">
                            <component :is="item.icon" />
                            <span>{{ item.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </template>
        </SidebarMenu>
    </SidebarGroup>
</template>
