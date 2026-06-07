import type { LucideIcon } from 'lucide-vue-next';
import {
    Calculator,
    CreditCard,
    FileText,
    Layers,
    LayoutGrid,
    Link2,
    Settings,
    Shield,
    ShoppingCart,
    Table2,
    Users,
} from 'lucide-vue-next';
import type { NavItem } from '@/types';

const iconMap: Record<string, LucideIcon> = {
    'layout-grid': LayoutGrid,
    'file-text': FileText,
    shield: Shield,
    'shopping-cart': ShoppingCart,
    'table-2': Table2,
    users: Users,
    'link-2': Link2,
    calculator: Calculator,
    'credit-card': CreditCard,
    layers: Layers,
    settings: Settings,
};

export function resolveNavIcon(icon?: string | LucideIcon): LucideIcon | undefined {
    if (!icon) {
        return undefined;
    }

    if (typeof icon !== 'string') {
        return icon;
    }

    return iconMap[icon];
}

export function hydrateNavItems(items: NavItem[]): NavItem[] {
    return items.map((item) => ({
        ...item,
        icon: resolveNavIcon(item.icon),
        items: item.items ? hydrateNavItems(item.items) : undefined,
    }));
}

export function hydrateNavGroups(groups: Array<{ label?: string; items: NavItem[] }>) {
    return groups.map((group) => ({
        ...group,
        items: hydrateNavItems(group.items),
    }));
}
