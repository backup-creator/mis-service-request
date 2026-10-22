import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
}

export interface SharedData extends Record<string, unknown> {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    server_info: {
        name: string;
        ip: string;
    };
    officeOptions: {
        locations: OfficeOption[];
        departments: DepartmentOption[];
    };
    ziggy: {
        location: string;
        url: string;
        port: null | number;
        defaults: Record<string, unknown>;
        routes: Record<string, string>;
    };
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    location_id: number | null;
    department_id: number | null;
}

export interface OfficeOption {
    id: number;
    name: string;
}

export interface DepartmentOption extends OfficeOption {
    location_id: number;
}

export type BreadcrumbItemType = BreadcrumbItem;
