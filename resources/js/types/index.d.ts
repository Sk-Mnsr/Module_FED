import { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    user: User | null;
    profil?: string | null;
    roles?: string[];
    isSuperAdmin?: boolean;
    isInCommittee?: boolean;
    canMonetiqueCentral?: boolean;
    canResponsableMonetique?: boolean;
    canInitiateCoficarteVente?: boolean;
    canInitiateCoficarteRecharge?: boolean;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href?: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | string;
    isActive?: boolean;
    items?: NavItem[];
    onClick?: () => void;
    separator?: boolean;
}

/** Groupe du menu latéral (ex. FED, Gestion de stock). */
export interface NavGroup {
    label?: string;
    items: NavItem[];
}

export type AppPageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    sidebarOpen: boolean;
    navigation: {
        groups: NavGroup[];
    };
};

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;
