<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import ProfileOfficeOnboardingModal from '@/components/ProfileOfficeOnboardingModal.vue';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <slot />
    </AppLayout>
    <ProfileOfficeOnboardingModal />
</template>
