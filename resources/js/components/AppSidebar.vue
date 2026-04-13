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
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { type NavItem } from '@/types';
import { Link, router, usePage } from '@inertiajs/vue3';
import { LayoutGrid, UserCog, Shield, User, Settings, FileText, Users, CreditCard, Link2, ShoppingCart, Table2, Calculator } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const page = usePage();
const auth = computed(() => page.props.auth as any);

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
        {
            title: 'Fiches de dépense',
            icon: FileText,
            items: [
                {
                    title: 'Nouvelle demande',
                    href: '/feds/create',
                },
                {
                    title: 'Mes demandes',
                    href: '/feds',
                },
            ],
        },
    ];

    const roleSlugs: string[] = auth.value?.roles || [];
    const profileType = auth.value?.user?.profile;

    const approachItems = [];
    if (!roleSlugs.includes('responsable_stock')) {
        approachItems.push({
            title: 'Nouvelle demande',
            href: '/demandes-approvisionnement/create',
        });
    }
    approachItems.push({
        title: 'Liste des demandes',
        href: '/demandes-approvisionnement',
    });

    items.push({
        title: 'Approvisionnement',
        icon: ShoppingCart,
        items: approachItems,
    });
    const hasConfigAccess = auth.value?.isSuperAdmin
        || auth.value?.isIt
        || auth.value?.isAdmin
        || roleSlugs.includes('it')
        || roleSlugs.includes('admin')
        || profileType === 'admin';

    if (hasConfigAccess) {
        items.push({
            title: 'Configuration',
            icon: Settings,
            items: [
                { title: 'Départements', href: '/departments' },
                { title: 'Budgets', href: '/budgets' },
                { title: 'Typologies de dépenses', href: '/typologies' },
                { title: 'Catégories de dépenses', href: '/categories' },
                { title: 'Banques', href: '/banques' },
                { title: 'Fournisseurs', href: '/fournisseurs' },
                { title: 'Types de dépense', href: '/type-depenses' },
                { title: 'Fiches d\'Intégration', href: '/fiche-integrations' },
                { title: 'Agences', href: '/agences' },
                { title: 'Articles', href: '/articles' },
                { title: 'Familles de produits', href: '/familles' },
                { title: 'Utilisateurs', href: '/users' },
                { title: 'Paramètres applicatifs', href: '/settings/app' },
            ],
        });
    }

    if (roleSlugs.includes('n_plus_1')) {
        items.push({
            title: 'Validations',
            href: '/feds/n1',
            icon: Shield,
        });
    }

    if (roleSlugs.includes('responsable_achats')) {
        items.push({
            title: 'Demandes en cours',
            href: '/feds/achats',
            icon: ShoppingCart,
        });
        items.push({
            title: 'TDR',
            href: '/appel-offres',
            icon: FileText,
        });
        items.push({
            title: 'Tableaux comparatifs',
            href: '/achats/tableaux-comparatifs',
            icon: Table2,
        });
        items.push({
            title: 'Fournisseurs',
            href: '/fournisseurs',
            icon: Users,
        });
        items.push({
            title: 'Bons de commande',
            href: '/bons-de-commande',
            icon: FileText,
        });
    }

    if (hasConfigAccess || roleSlugs.includes('responsable_achats') || roleSlugs.includes('responsable_stock')) {
        items.push({
            title: 'Gestion de Stock',
            href: '/stock',
            icon: Table2,
        });
    }

    if (roleSlugs.includes('responsable_facilities')) {
        items.push({
            title: 'Facilities',
            href: '/feds/facilities',
            icon: Link2,
        });
    }

    if (roleSlugs.includes('controle_de_gestion')) {
        items.push({
            title: 'Contrôle de Gestion',
            href: '/feds/cg',
            icon: Calculator,
        });
        items.push({
            title: 'Écritures Comptables',
            href: '/ecritures-comptables',
            icon: FileText,
        });
    }

    if (roleSlugs.includes('daf')) {
        items.push({
            title: 'Validations DAF',
            href: '/feds/daf',
            icon: Shield,
        });
        items.push({
            title: 'Écritures Comptables',
            href: '/ecritures-comptables',
            icon: FileText,
        });
    }

    if (roleSlugs.includes('dga')) {
        items.push({
            title: 'Validations DGA',
            href: '/feds/dga',
            icon: Shield,
        });
        items.push({
            title: 'Bons de commande',
            href: '/bons-de-commande',
            icon: FileText,
        });
    }

    // Afficher l'onglet Comité si l'utilisateur est acheteur OU s'il fait partie d'un comité
    if (roleSlugs.includes('responsable_achats') || auth.value?.isInCommittee) {
        items.push({
            title: 'Comité',
            href: '/comites',
            icon: Users,
        });
    }

    // Budgets : IT/Admin dans Configuration, N+1 vers /budgets/n1, les autres vers /budgets (lecture seule)
    if (!hasConfigAccess) {
        items.push({
            title: 'Budgets',
            href: roleSlugs.includes('n_plus_1') ? '/budgets/n1' : '/budgets',
            icon: CreditCard,
        });
    }

    return items;
});


</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader class="pb-4">
            <SidebarMenu>
                <SidebarMenuItem>
                    <Link :href="dashboard()" class="flex items-center p-2">
                        <AppLogo />
                    </Link>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="pt-4">
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
