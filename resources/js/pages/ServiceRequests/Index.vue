<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import CreateRequestModal from '@/pages/ServiceRequests/CreateRequestModal.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

interface ServiceRequestItem {
    id: number;
    request_reference: string;
    title: string;
    description: string;
    created_at: string;
    category: { name: string } | null;
    priority: { name: string } | null;
    status: { name: string } | null;
}

interface LookupOption {
    id: number;
    name: string;
    code: string;
    description?: string | null;
}

interface PaginatedRequests {
    data: ServiceRequestItem[];
    links: { url: string | null; label: string; active: boolean }[];
}

defineProps<{
    serviceRequests: PaginatedRequests;
    categories: LookupOption[];
    priorities: LookupOption[];
    success?: string | null;
}>();

const isCreateModalOpen = ref(false);

function formatDate(value: string) {
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(value));
}

function paginationLabel(label: string) {
    if (label.includes('Previous')) {
        return '← Previous';
    }

    if (label.includes('Next')) {
        return 'Next →';
    }

    return label;
}
</script>

<template>
    <Head title="My service requests" />

    <AppLayout>
        <main class="mx-auto w-full max-w-6xl px-4 py-10 sm:px-6 lg:px-8">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">Support</p>
                    <h1 class="mt-2 text-3xl font-bold tracking-tight text-gray-900">My service requests</h1>
                    <p class="mt-2 text-sm text-gray-600">Track your requests and submit a new one when you need help.</p>
                </div>
                <button
                    type="button"
                    @click="isCreateModalOpen = true"
                    class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500"
                >
                    New Service Request
                </button>
            </div>

            <div
                v-if="success"
                role="status"
                class="mt-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800"
            >
                {{ success }}
            </div>

            <div v-if="serviceRequests.data.length" class="mt-8 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <ul class="divide-y divide-gray-200">
                    <li v-for="request in serviceRequests.data" :key="request.id" class="p-5 sm:p-6">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ request.request_reference }}</p>
                                <h2 class="mt-1 truncate text-lg font-semibold text-gray-900">{{ request.title }}</h2>
                                <p class="mt-1 line-clamp-2 text-sm text-gray-600">{{ request.description }}</p>
                                <div class="mt-3 flex flex-wrap gap-2 text-xs">
                                    <span class="rounded-full bg-gray-100 px-3 py-1 font-medium text-gray-700">
                                        {{ request.category?.name ?? 'Uncategorized' }}
                                    </span>
                                    <span class="rounded-full bg-indigo-50 px-3 py-1 font-medium text-indigo-700">
                                        {{ request.priority?.name ?? 'No priority' }}
                                    </span>
                                    <span class="rounded-full bg-amber-50 px-3 py-1 font-medium text-amber-800">
                                        {{ request.status?.name ?? 'Submitted' }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex shrink-0 items-center justify-between gap-4 sm:flex-col sm:items-end">
                                <time class="text-sm text-gray-500">{{ formatDate(request.created_at) }}</time>
                                <Link
                                    :href="route('service-requests.show', request.id)"
                                    class="inline-flex items-center rounded-lg border border-indigo-200 px-3 py-2 text-sm font-semibold text-indigo-700 transition hover:border-indigo-300 hover:bg-indigo-50 focus:outline-none focus:ring-4 focus:ring-indigo-500/15"
                                >
                                    View Details
                                </Link>
                            </div>
                        </div>
                    </li>
                </ul>

                <nav v-if="serviceRequests.links.length > 3" aria-label="Pagination" class="flex flex-wrap gap-2 border-t border-gray-200 p-4">
                    <Link
                        v-for="(link, index) in serviceRequests.links"
                        :key="index"
                        :href="link.url ?? '#'"
                        :aria-disabled="!link.url"
                        class="rounded-md px-3 py-2 text-sm"
                        :class="[
                            link.active ? 'bg-indigo-600 font-semibold text-white' : 'text-gray-700 hover:bg-gray-100',
                            !link.url ? 'pointer-events-none opacity-50' : '',
                        ]"
                    >
                        {{ paginationLabel(link.label) }}
                    </Link>
                </nav>
            </div>

            <div v-else class="mt-8 rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-16 text-center">
                <h2 class="text-lg font-semibold text-gray-900">No service requests yet</h2>
                <p class="mt-2 text-sm text-gray-600">Your submitted requests will appear here.</p>
                <button
                    type="button"
                    @click="isCreateModalOpen = true"
                    class="mt-5 inline-flex rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500"
                >
                    Create your first request
                </button>
            </div>
        </main>

        <CreateRequestModal v-if="isCreateModalOpen" :categories="categories" :priorities="priorities" @close="isCreateModalOpen = false" />
    </AppLayout>
</template>
