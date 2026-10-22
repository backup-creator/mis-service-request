<script setup lang="ts">
import Modal from '@/components/Modal.vue';
import IdCardPreview from '@/components/ServiceRequests/IdCardPreview.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import CreateRequestModal from '@/pages/ServiceRequests/CreateRequestModal.vue';
import type { BreadcrumbItem, SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowUpRight, CheckCircle2, ClipboardList, Clock3, Paperclip, Plus, Send } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

type MetadataValue = string | number | boolean | null | MetadataValue[] | { [key: string]: MetadataValue };

interface RecentRequest {
    id: number;
    request_reference: string;
    title: string;
    description: string;
    created_at: string;
    submitted_at: string | null;
    category: { name: string | null; code: string | null };
    priority: string | null;
    status: string;
    status_slug: string;
    assigned_staff: string | null;
    resolution_summary: string | null;
    contact_number: string | null;
    subtype: string | null;
    metadata: Record<string, MetadataValue>;
    attachments: {
        id: number;
        file_name: string;
        url: string;
        mime_type: string;
        size_bytes: number;
    }[];
}

interface LookupOption {
    id: number;
    name: string;
    code: string;
    description?: string | null;
}

interface DashboardStats {
    total: number;
    pending: number;
    inProgress: number;
    completed: number;
}

const props = defineProps<{
    recentRequests: RecentRequest[];
    stats: DashboardStats;
    categories: LookupOption[];
    priorities: LookupOption[];
    openCreateModal?: boolean;
    success?: string | null;
}>();

const page = usePage<SharedData>();
const firstName = computed(() => page.props.auth.user?.name?.split(' ')[0] || '');
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];
const isCreateModalOpen = ref(false);
const isDetailsModalOpen = ref(false);
const selectedRequest = ref<RecentRequest | null>(null);
onMounted(() => {
    if (props.openCreateModal) {
        isCreateModalOpen.value = true;
    }
});

const selectedIsIdRequest = computed(
    () =>
        selectedRequest.value?.category.code?.toLowerCase() === 'id_request' ||
        selectedRequest.value?.category.name?.toLowerCase().includes('id') === true,
);
const selectedContactNumberVisible = computed(() => {
    const request = selectedRequest.value;
    const contactNumber = request?.contact_number?.replace(/\D/g, '') ?? '';
    const emergencyPhone = request ? String(request.metadata.id_emergency_phone ?? request.metadata.emergency_phone ?? '').replace(/\D/g, '') : '';

    return Boolean(request?.contact_number) && !(selectedIsIdRequest.value && contactNumber && contactNumber === emergencyPhone);
});
const selectedIsBulkRequest = computed(() => selectedRequest.value?.metadata.is_bulk === true);
const selectedBulkApplicants = computed(() => {
    const applicants = selectedRequest.value?.metadata.applicants ?? selectedRequest.value?.metadata.students;

    return Array.isArray(applicants)
        ? applicants.filter(
              (applicant): applicant is Record<string, MetadataValue> =>
                  typeof applicant === 'object' && applicant !== null && !Array.isArray(applicant),
          )
        : [];
});
const excludedMetadataKeys = [
    'requesttype',
    'subtype',
    'type',
    'servicerequesttype',
    'description',
    'contactnumber',
    'first_name',
    'middle_name',
    'last_name',
    'suffix',
    'position',
    'office',
    'department',
    'tin',
    'gsis',
    'birthdate',
    'birth_date',
    'emergency_name',
    'emergency_phone',
    'emergency_address',
    'contact_number',
    'id_number',
    'number',
    'photo',
    'signature',
    'id_photo',
    'digital_signature',
    'dob',
    'id_dob',
    'official_id_number',
    'id_request_type',
    'emergency_contact_name',
    'emergency_contact_phone',
    'id_photo_path',
    'id_photo_url',
    'client_signature_path',
    'client_signature_url',
];
const normalizedExcludedMetadataKeys = new Set(excludedMetadataKeys.map((key) => key.toLowerCase().replace(/[\s_]+/g, '')));
const remainingMetadata = computed<Record<string, MetadataValue>>(() => {
    if (!selectedRequest.value) {
        return {};
    }

    return Object.fromEntries(
        Object.entries(selectedRequest.value.metadata).filter(([key, value]) => {
            const normalizedKey = key.toLowerCase().replace(/[\s_]+/g, '');
            const idRequestKey = normalizedKey.replace(/^id/, '');
            const empty =
                value === null ||
                (typeof value === 'string' && value.trim() === '') ||
                (Array.isArray(value) && value.length === 0) ||
                (typeof value === 'object' && value !== null && !Array.isArray(value) && Object.keys(value).length === 0);

            return (
                !['id_photo_path', 'client_signature_path', 'id_photo_url', 'client_signature_url'].includes(key) &&
                !['students', 'applicants', 'is_bulk'].includes(key) &&
                !normalizedExcludedMetadataKeys.has(normalizedKey) &&
                !(selectedIsIdRequest.value && normalizedExcludedMetadataKeys.has(idRequestKey)) &&
                !empty
            );
        }),
    );
});
const selectedMetadata = computed(() =>
    Object.entries(remainingMetadata.value).map(([key, value]) => ({
        key,
        label: key
            .replace(/^id_/, '')
            .replace(/_/g, ' ')
            .replace(/\b\w/g, (letter) => letter.toUpperCase()),
        value:
            typeof value === 'string' && /date/i.test(key)
                ? formatLongDate(value)
                : Array.isArray(value)
                  ? value.map((item) => (typeof item === 'object' && item !== null ? JSON.stringify(item) : String(item))).join(', ')
                  : typeof value === 'object' && value !== null
                    ? JSON.stringify(value)
                    : String(value),
    })),
);

function openDetails(request: RecentRequest) {
    selectedRequest.value = request;
    isDetailsModalOpen.value = true;
}

function closeDetails() {
    isDetailsModalOpen.value = false;
    selectedRequest.value = null;
}

const statCards = [
    { label: 'Total Requests', key: 'total', icon: ClipboardList, color: 'text-indigo-600', background: 'bg-indigo-50' },
    { label: 'Pending / Submitted', key: 'pending', icon: Send, color: 'text-amber-600', background: 'bg-amber-50' },
    { label: 'In Progress / Processing', key: 'inProgress', icon: Clock3, color: 'text-blue-600', background: 'bg-blue-50' },
    { label: 'Completed / Ready', key: 'completed', icon: CheckCircle2, color: 'text-emerald-600', background: 'bg-emerald-50' },
] as const;

function formatDate(value: string) {
    return new Intl.DateTimeFormat('en-US', { month: 'short', day: 'numeric', year: 'numeric' }).format(new Date(value));
}

function formatLongDate(value: string) {
    const dateOnly = value.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    const date = dateOnly ? new Date(Number(dateOnly[1]), Number(dateOnly[2]) - 1, Number(dateOnly[3])) : new Date(value);

    return Number.isNaN(date.getTime()) ? value : new Intl.DateTimeFormat('en-US', { month: 'long', day: 'numeric', year: 'numeric' }).format(date);
}

function statusClasses(status: string, slug: string) {
    const normalized = `${slug} ${status}`.toLowerCase();

    if (normalized.includes('reject')) {
        return 'bg-red-50 text-red-700 ring-red-600/20';
    }

    if (normalized.includes('complete') || normalized.includes('resolved') || normalized.includes('closed') || normalized.includes('ready')) {
        return 'bg-emerald-50 text-emerald-700 ring-emerald-600/20';
    }

    if (normalized.includes('progress') || normalized.includes('processing') || normalized.includes('accepted')) {
        return 'bg-blue-50 text-blue-700 ring-blue-600/20';
    }

    return 'bg-amber-50 text-amber-800 ring-amber-600/20';
}
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <main class="mx-auto w-full max-w-7xl space-y-8 p-4 sm:p-6 lg:p-8">
            <section
                class="overflow-hidden rounded-2xl bg-gradient-to-r from-indigo-700 via-indigo-600 to-blue-600 px-6 py-8 text-white shadow-sm sm:px-8 sm:py-10"
            >
                <div class="flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold text-indigo-100">Service Request Portal</p>
                        <h1 class="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">Welcome, {{ firstName }}</h1>
                        <p class="mt-3 max-w-2xl text-sm text-indigo-100 sm:text-base">
                            View your request activity and keep track of support from the MIS team.
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="isCreateModalOpen = true"
                        class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg bg-white px-5 py-3 text-sm font-semibold text-indigo-700 shadow-sm transition hover:bg-indigo-50"
                    >
                        <Plus class="size-4" />
                        New Service Request
                    </button>
                </div>
            </section>

            <div
                v-if="props.success"
                role="status"
                class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800"
            >
                {{ props.success }}
            </div>

            <section aria-label="Request summary" class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article v-for="card in statCards" :key="card.key" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-gray-600">{{ card.label }}</p>
                        <span class="flex size-10 items-center justify-center rounded-lg" :class="card.background">
                            <component :is="card.icon" class="size-5" :class="card.color" aria-hidden="true" />
                        </span>
                    </div>
                    <p class="mt-4 text-3xl font-bold tracking-tight text-gray-900">{{ props.stats[card.key] }}</p>
                </article>
            </section>

            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="flex flex-col gap-3 border-b border-gray-200 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Recent Service Requests</h2>
                        <p class="mt-1 text-sm text-gray-500">Your latest submitted requests.</p>
                    </div>
                    <Link :href="route('service-requests.index')" class="text-sm font-semibold text-indigo-600 hover:text-indigo-500">
                        View all requests
                        <ArrowUpRight class="ml-1 inline size-4" />
                    </Link>
                </div>

                <div v-if="props.recentRequests.length" class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 sm:px-6">
                                    Reference
                                </th>
                                <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 sm:px-6">
                                    Title &amp; Category
                                </th>
                                <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 sm:px-6">
                                    Status
                                </th>
                                <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 sm:px-6">
                                    Date
                                </th>
                                <th scope="col" class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 sm:px-6">
                                    Action
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            <tr v-for="request in props.recentRequests" :key="request.id" class="hover:bg-gray-50/70">
                                <td class="whitespace-nowrap px-5 py-4 text-sm font-semibold text-gray-700 sm:px-6">
                                    {{ request.request_reference }}
                                </td>
                                <td class="min-w-56 px-5 py-4 sm:px-6">
                                    <p class="text-sm font-semibold text-gray-900">{{ request.title }}</p>
                                    <p class="mt-1 text-xs text-gray-500">{{ request.category?.name || 'Uncategorized' }}</p>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 sm:px-6">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset"
                                        :class="statusClasses(request.status, request.status_slug)"
                                    >
                                        {{ request.status }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-600 sm:px-6">
                                    {{ formatDate(request.submitted_at || request.created_at) }}
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-right sm:px-6">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-sm font-semibold text-indigo-600 hover:bg-indigo-50 hover:text-indigo-700"
                                        @click="openDetails(request)"
                                    >
                                        View Details
                                        <ArrowUpRight class="size-4" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else class="px-6 py-16 text-center">
                    <span class="mx-auto flex size-12 items-center justify-center rounded-full bg-indigo-50">
                        <ClipboardList class="size-6 text-indigo-600" aria-hidden="true" />
                    </span>
                    <h3 class="mt-4 text-base font-semibold text-gray-900">No requests found</h3>
                    <p class="mt-2 text-sm text-gray-600">Click “New Service Request” to get started.</p>
                    <button
                        type="button"
                        class="mt-5 inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500"
                        @click="isCreateModalOpen = true"
                    >
                        <Plus class="size-4" />
                        New Service Request
                    </button>
                </div>
            </section>

            <Modal
                :show="isDetailsModalOpen"
                :title="selectedRequest?.title || 'Request details'"
                :description="selectedRequest?.request_reference || ''"
                max-width="5xl"
                @close="closeDetails"
            >
                <div v-if="selectedRequest" class="space-y-6 p-5 sm:p-8">
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            class="inline-flex rounded-full px-3 py-1.5 text-sm font-semibold ring-1 ring-inset"
                            :class="statusClasses(selectedRequest.status, selectedRequest.status_slug)"
                        >
                            {{ selectedRequest.status }}
                        </span>
                        <span class="rounded-full bg-indigo-50 px-3 py-1.5 text-sm font-medium text-indigo-700">
                            {{ selectedRequest.category.name || 'Uncategorized' }}
                        </span>
                        <span v-if="selectedRequest.priority" class="rounded-full bg-gray-100 px-3 py-1.5 text-sm font-medium text-gray-700">
                            {{ selectedRequest.priority }} priority
                        </span>
                        <span class="text-sm text-gray-500"
                            >Submitted {{ formatDate(selectedRequest.submitted_at || selectedRequest.created_at) }}</span
                        >
                    </div>

                    <div v-if="selectedContactNumberVisible || selectedRequest.subtype" class="grid gap-4 sm:grid-cols-2">
                        <div v-if="selectedContactNumberVisible" class="rounded-xl border border-gray-200 p-4">
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Contact Number</h3>
                            <p class="mt-2 text-sm text-gray-900">{{ selectedRequest.contact_number }}</p>
                        </div>
                        <div v-if="selectedRequest.subtype" class="rounded-xl border border-gray-200 p-4">
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Subtype / Request Type</h3>
                            <p class="mt-2 text-sm text-gray-900">{{ selectedRequest.subtype }}</p>
                        </div>
                    </div>

                    <section class="rounded-xl border border-gray-200 p-4">
                        <h3 class="text-sm font-semibold text-gray-900">Description</h3>
                        <p class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-600">{{ selectedRequest.description }}</p>
                    </section>

                    <section v-if="selectedIsIdRequest" class="space-y-5">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="rounded-lg bg-gray-50 p-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">First Name</p>
                                <p class="mt-1 text-sm text-gray-900">{{ selectedRequest.metadata.id_first_name || '—' }}</p>
                            </div>
                            <div class="rounded-lg bg-gray-50 p-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Middle Name</p>
                                <p class="mt-1 text-sm text-gray-900">{{ selectedRequest.metadata.id_middle_name || '—' }}</p>
                            </div>
                            <div class="rounded-lg bg-gray-50 p-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Last Name</p>
                                <p class="mt-1 text-sm text-gray-900">{{ selectedRequest.metadata.id_last_name || '—' }}</p>
                            </div>
                            <div class="rounded-lg bg-gray-50 p-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Suffix</p>
                                <p class="mt-1 text-sm text-gray-900">{{ selectedRequest.metadata.id_suffix || '—' }}</p>
                            </div>
                            <div class="rounded-lg bg-gray-50 p-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Position</p>
                                <p class="mt-1 text-sm text-gray-900">{{ selectedRequest.metadata.id_position || '—' }}</p>
                            </div>
                            <div class="rounded-lg bg-gray-50 p-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Office / Department</p>
                                <p class="mt-1 text-sm text-gray-900">{{ selectedRequest.metadata.id_office || '—' }}</p>
                            </div>
                            <div class="rounded-lg bg-gray-50 p-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">TIN</p>
                                <p class="mt-1 text-sm text-gray-900">{{ selectedRequest.metadata.id_tin || '—' }}</p>
                            </div>
                            <div class="rounded-lg bg-gray-50 p-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">GSIS</p>
                                <p class="mt-1 text-sm text-gray-900">{{ selectedRequest.metadata.id_gsis || '—' }}</p>
                            </div>
                            <div class="rounded-lg bg-gray-50 p-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Birthdate</p>
                                <p class="mt-1 text-sm text-gray-900">
                                    {{
                                        typeof selectedRequest.metadata.id_birthdate === 'string'
                                            ? formatLongDate(selectedRequest.metadata.id_birthdate)
                                            : '—'
                                    }}
                                </p>
                            </div>
                            <div class="rounded-lg bg-gray-50 p-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Employee ID Number</p>
                                <p class="mt-1 text-sm text-gray-900">
                                    {{
                                        selectedRequest.metadata.id_number ||
                                        selectedRequest.metadata.number ||
                                        selectedRequest.metadata.official_id_number ||
                                        '—'
                                    }}
                                </p>
                            </div>
                        </div>
                        <div class="rounded-lg bg-gray-50 p-4">
                            <h3 class="text-sm font-semibold text-gray-900">Emergency Contact</h3>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                <div class="rounded-lg bg-white p-3">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Emergency Contact Name</p>
                                    <p class="mt-1 text-sm text-gray-900">
                                        {{ selectedRequest.metadata.id_emergency_name || selectedRequest.metadata.emergency_name || '—' }}
                                    </p>
                                </div>
                                <div class="rounded-lg bg-white p-3">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Emergency Contact Phone</p>
                                    <p class="mt-1 text-sm text-gray-900">
                                        {{ selectedRequest.metadata.id_emergency_phone || selectedRequest.metadata.emergency_phone || '—' }}
                                    </p>
                                </div>
                                <div class="rounded-lg bg-white p-3 sm:col-span-2">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Emergency Address</p>
                                    <p class="mt-1 text-sm text-gray-900">
                                        {{ selectedRequest.metadata.id_emergency_address || selectedRequest.metadata.emergency_address || '—' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div v-if="typeof selectedRequest.metadata.id_photo_url === 'string'" class="rounded-xl border border-gray-200 p-4">
                                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Uploaded Photo</h3>
                                <img
                                    :src="selectedRequest.metadata.id_photo_url"
                                    alt="Uploaded employee ID photo"
                                    class="mt-3 h-40 w-40 rounded-lg border border-gray-200 object-cover"
                                />
                            </div>
                            <div
                                v-if="typeof selectedRequest.metadata.client_signature_url === 'string'"
                                class="rounded-xl border border-gray-200 p-4"
                            >
                                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Uploaded Signature</h3>
                                <img
                                    :src="selectedRequest.metadata.client_signature_url"
                                    alt="Uploaded employee signature"
                                    class="mt-3 h-40 w-full rounded-lg border border-gray-200 bg-gray-50 object-contain p-2"
                                />
                            </div>
                        </div>
                        <IdCardPreview :metadata="selectedRequest.metadata" />
                    </section>

                    <section v-if="selectedIsBulkRequest" class="space-y-3">
                        <h3 class="text-sm font-semibold text-gray-900">Included Applicants</h3>
                        <div class="overflow-x-auto rounded-xl border border-gray-200">
                            <table class="min-w-full divide-y divide-gray-200 text-left text-sm">
                                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                                    <tr>
                                        <th class="px-4 py-3">Student / Employee ID</th>
                                        <th class="px-4 py-3">Name</th>
                                        <th class="px-4 py-3">Personal Email</th>
                                        <th class="px-4 py-3">Department / Office</th>
                                        <th class="px-4 py-3">Suggested Institutional Email</th>
                                        <th class="px-4 py-3">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr
                                        v-for="(applicant, index) in selectedBulkApplicants"
                                        :key="`${String(applicant.id_number ?? applicant.student_id)}-${index}`"
                                    >
                                        <td class="whitespace-nowrap px-4 py-3">{{ applicant.id_number ?? applicant.student_id }}</td>
                                        <td class="whitespace-nowrap px-4 py-3">
                                            {{
                                                applicant.full_name ??
                                                [applicant.first_name, applicant.middle_initial, applicant.last_name, applicant.suffix]
                                                    .filter(Boolean)
                                                    .join(' ')
                                            }}
                                        </td>
                                        <td class="px-4 py-3">{{ applicant.personal_email }}</td>
                                        <td class="px-4 py-3">{{ applicant.department_office || '—' }}</td>
                                        <td class="whitespace-nowrap px-4 py-3">{{ applicant.generated_email }}</td>
                                        <td class="px-4 py-3 capitalize">{{ applicant.status || 'pending' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section v-if="!selectedIsIdRequest && Object.keys(remainingMetadata).length > 0" class="rounded-xl border border-gray-200 p-4">
                        <h3 class="text-sm font-semibold text-gray-900">Submitted Details</h3>
                        <dl class="mt-3 grid gap-3 sm:grid-cols-2">
                            <div v-for="item in selectedMetadata" :key="item.key" class="rounded-lg bg-gray-50 p-3">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ item.label }}</dt>
                                <dd class="mt-1 break-words text-sm text-gray-900">{{ item.value }}</dd>
                            </div>
                        </dl>
                    </section>

                    <section v-if="selectedRequest.attachments.length" class="rounded-xl border border-gray-200 p-4">
                        <h3 class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                            <Paperclip class="size-4" />
                            Attachments
                        </h3>
                        <ul class="mt-3 divide-y divide-gray-100">
                            <li
                                v-for="attachment in selectedRequest.attachments"
                                :key="attachment.id"
                                class="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                            >
                                <span class="truncate text-sm text-gray-700">{{ attachment.file_name }}</span>
                                <a
                                    :href="attachment.url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="shrink-0 text-sm font-semibold text-indigo-600 hover:text-indigo-500"
                                >
                                    View / Download
                                    <ArrowUpRight class="ml-1 inline size-4" />
                                </a>
                            </li>
                        </ul>
                    </section>

                    <section class="grid gap-4 border-t border-gray-100 pt-5 sm:grid-cols-2">
                        <div>
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Assigned To</h3>
                            <p class="mt-1 text-sm text-gray-900">{{ selectedRequest.assigned_staff || 'Not assigned yet' }}</p>
                        </div>
                        <div v-if="selectedRequest.resolution_summary">
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Resolution / Remarks</h3>
                            <p class="mt-1 whitespace-pre-line text-sm text-gray-900">{{ selectedRequest.resolution_summary }}</p>
                        </div>
                    </section>
                </div>
            </Modal>

            <CreateRequestModal
                :show="isCreateModalOpen"
                :categories="props.categories"
                :priorities="props.priorities"
                @close="isCreateModalOpen = false"
            />
        </main>
    </AppLayout>
</template>
