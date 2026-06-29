<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="flex min-h-0 flex-1 flex-col overflow-x-hidden bg-muted/20">
            <header class="flex h-14 shrink-0 items-center border-b border-border/60 px-4 md:px-6">
                <SidebarTrigger class="-ml-1" />
            </header>
            <div class="flex min-h-0 flex-1 flex-col overflow-y-auto">
                <slot />
            </div>
        </AppContent>
    </AppShell>
</template>
