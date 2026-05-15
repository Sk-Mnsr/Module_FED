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
import { type NavGroup, type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { LayoutGrid, UserCog, Shield, User, Settings, FileText, Users, CreditCard, Link2, ShoppingCart, Table2, Calculator, Layers } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const page = usePage();
const auth = computed(() => page.props.auth as any);

const mainNavGroups = computed<NavGroup[]>(() => {
    const fedItems: NavItem[] = [
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

    const stockItems: NavItem[] = [];
    stockItems.push({
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

    if (roleSlugs.includes('n_plus_1')) {
        fedItems.push({
            title: 'Validations',
            href: '/feds/n1',
            icon: Shield,
        });
    }

    if (roleSlugs.includes('responsable_achats')) {
        fedItems.push({
            title: 'Demandes en cours',
            href: '/feds/achats',
            icon: ShoppingCart,
        });
        fedItems.push({
            title: 'TDR',
            href: '/appel-offres',
            icon: FileText,
        });
        fedItems.push({
            title: 'Tableaux comparatifs',
            href: '/achats/tableaux-comparatifs',
            icon: Table2,
        });
        fedItems.push({
            title: 'Fournisseurs',
            href: '/fournisseurs',
            icon: Users,
        });
        fedItems.push({
            title: 'Bons de commande',
            href: '/bons-de-commande',
            icon: FileText,
        });
    }

    if (hasConfigAccess || roleSlugs.includes('responsable_achats') || roleSlugs.includes('responsable_stock')) {
        stockItems.push({
            title: 'Gestion de Stock',
            href: '/stock',
            icon: Table2,
        });
    }

    if (roleSlugs.includes('responsable_facilities')) {
        fedItems.push({
            title: 'Facilities',
            href: '/feds/facilities',
            icon: Link2,
        });
    }

    if (roleSlugs.includes('controle_de_gestion')) {
        fedItems.push({
            title: 'Contrôle de Gestion',
            href: '/feds/cg',
            icon: Calculator,
        });
    }

    if (roleSlugs.includes('daf')) {
        fedItems.push({
            title: 'Validations DAF',
            href: '/feds/daf',
            icon: Shield,
        });
    }

    if (roleSlugs.includes('dga')) {
        fedItems.push({
            title: 'Validations DGA',
            href: '/feds/dga',
            icon: Shield,
        });
        fedItems.push({
            title: 'Bons de commande',
            href: '/bons-de-commande',
            icon: FileText,
        });
    }

    // Afficher l'onglet Comité si l'utilisateur est acheteur OU s'il fait partie d'un comité
    if (roleSlugs.includes('responsable_achats') || auth.value?.isInCommittee) {
        fedItems.push({
            title: 'Comité',
            href: '/comites',
            icon: Users,
        });
    }


    const budgetItems: NavItem[] = [];
    if (hasConfigAccess) {
        budgetItems.push({
            title: 'Budgets',
            href: '/budgets',
            icon: CreditCard,
        });
    } else {
        budgetItems.push({
            title: 'Budgets',
            href: roleSlugs.includes('n_plus_1') ? '/budgets/n1' : '/budgets',
            icon: CreditCard,
        });
    }


    const ecrituresComptablesItems: NavItem[] = [];
    if (hasConfigAccess || roleSlugs.includes('controle_de_gestion') || roleSlugs.includes('daf')) {
        ecrituresComptablesItems.push({
            title: 'Écritures Comptables',
            href: '/ecritures-comptables',
            icon: FileText,
        });
    }


    const monetiqueModuleRoles = ['monetique', 'responsable_monetique', 'chef_agence_ca', 'charge_clientele_cc', 'caissier'];
    const hasMonetiqueModule =
        hasConfigAccess ||
        profileType === 'monetique' ||
        roleSlugs.some((r) => monetiqueModuleRoles.includes(r));

    const canMonetiqueCentral = auth.value?.canMonetiqueCentral === true || hasConfigAccess;

    const canResponsableMonetique = auth.value?.canResponsableMonetique === true || hasConfigAccess;

    const canEncaissement =
        hasConfigAccess ||
        roleSlugs.includes('caissier') ||
        roleSlugs.includes('monetique') ||
        roleSlugs.includes('it');

    const coficartePilotageParamItems = {
        title: 'Pilotage & paramètres',
        items: [
            { title: 'Tableau de bord', href: '/monetique/pilotage' },
            { title: 'Campagnes', href: '/monetique/campagnes' },
            { title: 'Seuils & objectifs', href: '/monetique/parametrage/seuils-stock' },
        ],
    };

    const coficarteRechargesItems = {
        title: 'Recharges',
        items: [
            ...(auth.value?.canInitiateCoficarteRecharge
                ? [{ title: 'Nouvelle recharge', href: '/monetique/recharges/nouveau' }]
                : []),
            { title: 'Historique', href: '/monetique/recharges/historique' },
        ],
    };

    const coficarteEncaissementsItems = {
        title: 'Caisse',
        items: [{ title: 'Encaissement', href: '/monetique/encaissements' }],
    };

    const coficarteCartesCentralItems = [
        ...(canResponsableMonetique
            ? [
                  { title: 'Ajouter', href: '/monetique/cartes/ajouter' },
                  { title: 'Modifier prix', href: '/monetique/cartes/modifier-prix' },
              ]
            : []),
        { title: 'En Stock', href: '/monetique/cartes/en-stock' },
        { title: 'Vendus', href: '/monetique/cartes/vendus' },
    ];

    const coficarteCentralItems = [
        coficartePilotageParamItems,
        {
            title: 'Cartes',
            items: coficarteCartesCentralItems,
        },
        {
            title: 'Transferts & approvisionnement',
            items: [
                { title: "Demandes d'agences", href: '/monetique/demandes-approvisionnement' },
                { title: 'Nouveau transfert', href: '/monetique/transferts/nouveau' },
                { title: 'En attente', href: '/monetique/transferts/en-attente' },
                { title: 'Historique', href: '/monetique/transferts/historique' },
            ],
        },
        {
            title: 'Ventes',
            items: [
                { title: 'Nouveau', href: '/monetique/ventes/nouveau' },
                { title: 'Historique', href: '/monetique/ventes/historique' },
            ],
        },
        coficarteRechargesItems,
        ...(canEncaissement ? [coficarteEncaissementsItems] : []),
    ];

    const coficarteChefAgenceVentesItems = roleSlugs.includes('charge_clientele_cc')
        ? [
              { title: 'Nouvelle vente', href: '/monetique/ventes/nouveau' },
              { title: 'Historique des ventes', href: '/monetique/ventes/historique' },
          ]
        : [{ title: 'Historique des ventes', href: '/monetique/ventes/historique' }];

    const coficarteAgenceSectionItems = [
        { title: 'Retour au siège', href: '/monetique/agence/retour-cartes' },
        ...(roleSlugs.includes('charge_clientele_cc')
            ? [{ title: 'Délester vers le chef d’agence', href: '/monetique/cc/delester-chef-agence' }]
            : []),
        { title: 'Approvisionnement CC', href: '/monetique/agence/approvisionnement-cc' },
        { title: 'Apporteurs', href: '/monetique/agence/apporteurs' },
        { title: 'Suivi ventes & recharges', href: '/monetique/agence/suivi' },
    ];

    const coficarteChefAgenceItems = [
        {
            title: 'Cartes (stock agence)',
            items: [
                { title: 'En stock', href: '/monetique/cartes/en-stock' },
                { title: 'Vendus', href: '/monetique/cartes/vendus' },
            ],
        },
        {
            title: 'Transferts & approvisionnement',
            items: [
                { title: 'Demandes au siège', href: '/monetique/agence/demandes-approvisionnement' },
                { title: 'Réception des cartes', href: '/monetique/transferts/en-attente' },
                { title: 'Historique des transferts', href: '/monetique/transferts/historique' },
            ],
        },
        {
            title: 'Agence',
            items: coficarteAgenceSectionItems,
        },
        {
            title: 'Ventes',
            items: coficarteChefAgenceVentesItems,
        },
        coficarteRechargesItems,
        ...(canEncaissement ? [coficarteEncaissementsItems] : []),
        {
            title: 'Pilotage',
            items: [{ title: 'Vue agence', href: '/monetique/pilotage' }],
        },
    ];

    const coficarteOperationalVentesItems = auth.value?.canInitiateCoficarteVente
        ? [
              { title: 'Nouvelle vente', href: '/monetique/ventes/nouveau' },
              { title: 'Historique', href: '/monetique/ventes/historique' },
          ]
        : [{ title: 'Historique', href: '/monetique/ventes/historique' }];

    const coficarteOperationalItems = [
        {
            title: 'Pilotage',
            items: [{ title: 'Indicateurs', href: '/monetique/pilotage' }],
        },
        {
            title: 'Cartes',
            items: [
                { title: 'En stock', href: '/monetique/cartes/en-stock' },
                { title: 'Vendus', href: '/monetique/cartes/vendus' },
            ],
        },
        ...(roleSlugs.includes('charge_clientele_cc')
            ? [
                  {
                      title: 'Délestage (CC)',
                      items: [{ title: 'Délester vers le chef d’agence', href: '/monetique/cc/delester-chef-agence' }],
                  },
              ]
            : []),
        {
            title: 'Ventes',
            items: coficarteOperationalVentesItems,
        },
        coficarteRechargesItems,
        ...(canEncaissement ? [coficarteEncaissementsItems] : []),
    ];

    const monetiqueItems: NavItem[] = [];
    if (hasMonetiqueModule) {
        let coficarteChildren;
        if (canMonetiqueCentral) {
            coficarteChildren = coficarteCentralItems;
        } else if (roleSlugs.includes('chef_agence_ca')) {
            coficarteChildren = coficarteChefAgenceItems;
        } else {
            coficarteChildren = coficarteOperationalItems;
        }

        monetiqueItems.push({
            title: 'Coficarte',
            icon: CreditCard,
            items: coficarteChildren,
        });
    }

    const canAccessOperationsDiverses =
        hasConfigAccess
        || roleSlugs.includes('ops')
        || roleSlugs.includes('controle_de_gestion')
        || roleSlugs.includes('daf');

    const odItems: NavItem[] = [];
    if (canAccessOperationsDiverses) {
        odItems.push({
            title: 'Opérations diverses',
            icon: Layers,
            items: [
                { title: 'Pièce comptable', href: '/operations-diverses/piece-comptable' },
                { title: 'Archivage', href: '/operations-diverses/archivage' },
            ],
        });
    }

    const configItems: NavItem[] = [];
    if (hasConfigAccess) {
        configItems.push({
            title: 'Paramétrage',
            icon: Settings,
            items: [
                { title: 'Départements', href: '/departments' },
                { title: 'Typologies de dépenses', href: '/typologies' },
                { title: 'Catégories de dépenses', href: '/categories' },
                { title: 'Banques', href: '/banques' },
                { title: 'Fournisseurs', href: '/fournisseurs' },
                { title: 'Types de dépense', href: '/type-depenses' },
                { title: 'Fiches d\'Intégration', href: '/fiche-integrations' },
                { title: 'Agences', href: '/agences' },
                { title: 'Apporteurs d’affaires', href: '/apporteurs-affaires' },
                { title: 'Articles', href: '/articles' },
                { title: 'Familles de produits', href: '/familles' },
                { title: 'Utilisateurs', href: '/users' },
                { title: 'Paramètres applicatifs', href: '/settings/app' },
            ],
        });
    }

    const groups: NavGroup[] = [{ label: 'FED', items: fedItems }];
    if (budgetItems.length > 0) {
        groups.push({ label: 'Budget', items: budgetItems });
    }
    if (stockItems.length > 0) {
        groups.push({ label: 'Gestion de stock', items: stockItems });
    }
    if (ecrituresComptablesItems.length > 0) {
        groups.push({ label: 'Écritures comptables', items: ecrituresComptablesItems });
    }
    if (monetiqueItems.length > 0) {
        groups.push({ label: 'Monétique', items: monetiqueItems });
    }
    if (odItems.length > 0) {
        groups.push({ label: 'OD', items: odItems });
    }
    if (configItems.length > 0) {
        groups.push({ label: 'Configuration', items: configItems });
    }

    return groups;
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
            <NavMain :groups="mainNavGroups" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
