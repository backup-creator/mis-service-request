<script setup lang="ts">
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from '@/components/ui/breadcrumb';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { usePage } from '@inertiajs/vue3';
import type { BreadcrumbItemType, SharedData } from '@/types';
import { computed } from 'vue';

defineProps<{
    breadcrumbs?: BreadcrumbItemType[];
}>();

const page = usePage<SharedData>();
const serverName = computed(() => (page.props.server_info?.name === 'Server 2' ? 'Server 2' : 'Server 1'));
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center justify-between gap-2 border-b border-sidebar-border/70 px-4 transition-[width,height] ease-linear group-has-[[data-collapsible=icon]]/sidebar-wrapper:h-12 sm:px-6 md:px-4"
    >
        <div class="flex min-w-0 items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs.length > 0">
                <Breadcrumb>
                    <BreadcrumbList>
                        <template v-for="(item, index) in breadcrumbs" :key="index">
                            <BreadcrumbItem>
                                <template v-if="index === breadcrumbs.length - 1">
                                    <BreadcrumbPage>{{ item.title }}</BreadcrumbPage>
                                </template>
                                <template v-else>
                                    <BreadcrumbLink :href="item.href">
                                        {{ item.title }}
                                    </BreadcrumbLink>
                                </template>
                            </BreadcrumbItem>
                            <BreadcrumbSeparator v-if="index !== breadcrumbs.length - 1" />
                        </template>
                    </BreadcrumbList>
                </Breadcrumb>
            </template>
        </div>
        <div
            class="inline-flex max-w-[60vw] shrink-0 items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[10px] font-medium leading-4 text-emerald-800 sm:max-w-none sm:gap-2 sm:px-3 sm:text-xs"
            role="status"
            aria-label="Connected server"
        >
            <span class="size-1.5 shrink-0 rounded-full bg-emerald-500 ring-2 ring-emerald-200 sm:size-2" aria-hidden="true" />
            <span class="whitespace-nowrap">Connected to:</span>
            <span class="truncate">{{ serverName }}</span>
            <span class="hidden text-emerald-700 sm:inline">({{ page.props.server_info.ip }})</span>
            <span class="truncate text-emerald-700 sm:hidden">({{ page.props.server_info.ip }})</span>
        </div>
    </header>
</template>
