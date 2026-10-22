<script setup lang="ts">
import IdCardPreview from '@/components/ServiceRequests/IdCardPreview.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, ArrowUpRight, CheckCircle2, Clock3, FileText, Paperclip } from 'lucide-vue-next';
import { computed } from 'vue';

type MetadataValue = string | number | boolean | null | MetadataValue[] | { [key: string]: MetadataValue };

interface RequestAttachment {
    id: number;
    file_name: string;
    url: string;
    mime_type: string;
    size_bytes: number;
}

interface RequestDetails {
    request_reference: string;
    title: string;
    description: string;
    submitted_at: string;
    category: { name: string | null; code: string | null };
    priority: string | null;
    status: string;
    status_slug: string;
    assigned_staff: string | null;
    resolution_summary: string | null;
    contact_number: string | null;
    subtype: string | null;
    metadata: Record<string, MetadataValue>;
    attachments: RequestAttachment[];
}

const props = defineProps<{
    serviceRequest: RequestDetails;
}>();

const isIdRequest = computed(
    () =>
        props.serviceRequest.category?.code?.toLowerCase() === 'id_request' ||
        props.serviceRequest.category?.name?.toLowerCase().includes('id') === true,
);
const idPhotoUrl = computed(() => stringValue(props.serviceRequest.metadata.id_photo_url));
const signatureUrl = computed(() => stringValue(props.serviceRequest.metadata.client_signature_url));
const idTin = computed(() => formatTin(stringValue(props.serviceRequest.metadata.id_tin)));
const idBirthdate = computed(() => formatDateOnly(props.serviceRequest.metadata.id_birthdate));
const idNumber = computed(
    () => props.serviceRequest.metadata.id_number || props.serviceRequest.metadata.number || props.serviceRequest.metadata.official_id_number,
);
const contactNumberVisible = computed(() => {
    const contactNumber = props.serviceRequest.contact_number?.replace(/\D/g, '') ?? '';
    const emergencyPhone = stringValue(props.serviceRequest.metadata.id_emergency_phone || props.serviceRequest.metadata.emergency_phone).replace(
        /\D/g,
        '',
    );

    return Boolean(props.serviceRequest.contact_number) && !(isIdRequest.value && contactNumber && contactNumber === emergencyPhone);
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

const remainingMetadata = computed(() =>
    Object.fromEntries(
        Object.entries(props.serviceRequest.metadata).filter(([key, value]) => {
            const normalizedKey = key.toLowerCase().replace(/[\s_]+/g, '');
            const idRequestKey = normalizedKey.replace(/^id/, '');

            return (
                !normalizedExcludedMetadataKeys.has(normalizedKey) &&
                !(isIdRequest.value && normalizedExcludedMetadataKeys.has(idRequestKey)) &&
                !isEmpty(value)
            );
        }),
    ),
);

const genericMetadata = computed(() =>
    Object.entries(remainingMetadata.value).map(([key, value]) => ({
        key,
        label: humanize(key),
        value: metadataFileUrls(value).length
            ? metadataFileUrls(value)
                  .map((file) => file.name)
                  .join(', ')
            : formatMetadataValue(key, value),
        files: metadataFileUrls(value),
    })),
);

function stringValue(value: MetadataValue | undefined): string {
    return typeof value === 'string' || typeof value === 'number' ? String(value) : '';
}

function displayValue(value: MetadataValue | undefined): string {
    return isEmpty(value) ? '—' : formatMetadataValue('', value);
}

function formatTin(value: string) {
    const digits = value.replace(/\D/g, '');

    return digits.length === 9 ? `${digits.slice(0, 3)}-${digits.slice(3, 6)}-${digits.slice(6)}` : value || '—';
}

function formatDateOnly(value: MetadataValue | undefined) {
    if (typeof value !== 'string' || !value) {
        return '—';
    }

    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);
    const date = match ? new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3])) : new Date(value);

    return Number.isNaN(date.getTime()) ? value : new Intl.DateTimeFormat('en-US', { month: 'long', day: 'numeric', year: 'numeric' }).format(date);
}

function isEmpty(value: MetadataValue | undefined): boolean {
    if (value === null || value === undefined || (typeof value === 'string' && value.trim() === '')) {
        return true;
    }

    if (Array.isArray(value)) {
        return value.length === 0 || value.every((item) => isEmpty(item));
    }

    return typeof value === 'object' && Object.keys(value).length === 0;
}

function humanize(key: string) {
    const label = key.replace(/^id_/, '').replace(/_/g, ' ');

    return label.replace(/\b\w+/g, (word) => {
        const upper = word.toUpperCase();

        return ['IP', 'TIN', 'GSIS', 'ID'].includes(upper) ? upper : word[0].toUpperCase() + word.slice(1);
    });
}

function formatMetadataValue(key: string, value: MetadataValue | undefined): string {
    if (value === undefined) {
        return '';
    }

    if (/(date|dob|birth)/i.test(key) && typeof value === 'string') {
        return formatDateOnly(value);
    }

    if (Array.isArray(value)) {
        return value.map((item) => formatMetadataValue(key, item)).join(', ');
    }

    if (typeof value === 'object' && value !== null) {
        return Object.entries(value)
            .filter(([, item]) => !isEmpty(item))
            .map(([childKey, item]) => `${humanize(childKey)}: ${formatMetadataValue(childKey, item)}`)
            .join(' · ');
    }

    if (typeof value === 'boolean') {
        return value ? 'Yes' : 'No';
    }

    return value === null ? '' : String(value);
}

function metadataFileUrls(value: MetadataValue): { name: string; url: string }[] {
    if (Array.isArray(value)) {
        return value.flatMap((item) => metadataFileUrls(item));
    }

    if (typeof value !== 'string' || !/\.(?:jpe?g|png|gif|webp|pdf|docx?|xlsx?|zip)(?:\?.*)?$/i.test(value)) {
        return [];
    }

    const url = /^(?:https?:\/\/|\/)/i.test(value) ? value : `/storage/${value.replace(/^(?:(?:public|storage)\/)+/, '')}`;
    const fileName = value.split(/[\\/]/).pop()?.split('?')[0] || value;

    return [{ name: fileName, url }];
}

function formatDate(value: string) {
    return new Intl.DateTimeFormat('en-US', {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(value));
}

function formatFileSize(size: number) {
    if (size < 1024) {
        return `${size} B`;
    }

    return size < 1024 * 1024 ? `${(size / 1024).toFixed(1)} KB` : `${(size / (1024 * 1024)).toFixed(1)} MB`;
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
    <Head :title="serviceRequest.request_reference" />

    <AppLayout>
        <main class="mx-auto w-full max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
            <Link :href="route('dashboard')" class="inline-flex items-center gap-2 text-sm font-semibold text-indigo-600 hover:text-indigo-500">
                <ArrowLeft class="size-4" />
                Back to dashboard
            </Link>

            <section class="mt-6 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <header class="border-b border-gray-200 bg-gray-50 px-6 py-6 sm:px-8">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Reference Number</p>
                            <p class="mt-1 font-mono text-sm font-semibold text-gray-800">{{ serviceRequest.request_reference }}</p>
                            <h1 class="mt-3 text-2xl font-bold tracking-tight text-gray-900">{{ serviceRequest.title }}</h1>
                        </div>
                        <span
                            class="inline-flex w-fit items-center gap-2 rounded-full px-3 py-1.5 text-sm font-semibold ring-1 ring-inset"
                            :class="statusClasses(serviceRequest.status, serviceRequest.status_slug)"
                        >
                            <Clock3 class="size-4" />
                            {{ serviceRequest.status }}
                        </span>
                    </div>
                    <div class="mt-5 flex flex-wrap items-center gap-2 text-sm">
                        <span class="rounded-full bg-indigo-50 px-3 py-1 font-medium text-indigo-700">
                            {{ serviceRequest.category?.name || 'Uncategorized' }}
                        </span>
                        <span v-if="serviceRequest.priority" class="rounded-full bg-gray-100 px-3 py-1 font-medium text-gray-700">
                            {{ serviceRequest.priority }} priority
                        </span>
                        <span class="text-gray-500">Submitted {{ formatDate(serviceRequest.submitted_at) }}</span>
                    </div>
                </header>

                <div class="space-y-8 px-6 py-6 sm:px-8">
                    <section v-if="contactNumberVisible || serviceRequest.subtype" class="grid gap-4 sm:grid-cols-2">
                        <div v-if="contactNumberVisible" class="rounded-xl border border-gray-200 p-4">
                            <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Contact Number</h2>
                            <p class="mt-2 text-sm font-medium text-gray-900">{{ serviceRequest.contact_number }}</p>
                        </div>
                        <div v-if="serviceRequest.subtype" class="rounded-xl border border-gray-200 p-4">
                            <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Subtype / Request Type</h2>
                            <p class="mt-2 text-sm font-medium text-gray-900">{{ serviceRequest.subtype }}</p>
                        </div>
                    </section>

                    <section class="rounded-xl border border-gray-200 p-5">
                        <h2 class="text-base font-semibold text-gray-900">Full Description</h2>
                        <p class="mt-3 whitespace-pre-line text-sm leading-6 text-gray-700">{{ serviceRequest.description }}</p>
                    </section>

                    <section v-if="isIdRequest" class="space-y-6">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">ID Request Details</h2>
                            <p class="mt-1 text-sm text-gray-500">Employee information submitted with this request.</p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="rounded-xl border border-gray-200 p-4">
                                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">First Name</h3>
                                <p class="mt-2 text-sm text-gray-900">{{ displayValue(serviceRequest.metadata.id_first_name) }}</p>
                            </div>
                            <div class="rounded-xl border border-gray-200 p-4">
                                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Middle Name</h3>
                                <p class="mt-2 text-sm text-gray-900">{{ displayValue(serviceRequest.metadata.id_middle_name) }}</p>
                            </div>
                            <div class="rounded-xl border border-gray-200 p-4">
                                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Last Name</h3>
                                <p class="mt-2 text-sm text-gray-900">{{ displayValue(serviceRequest.metadata.id_last_name) }}</p>
                            </div>
                            <div class="rounded-xl border border-gray-200 p-4">
                                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Suffix</h3>
                                <p class="mt-2 text-sm text-gray-900">{{ displayValue(serviceRequest.metadata.id_suffix) }}</p>
                            </div>
                            <div class="rounded-xl border border-gray-200 p-4">
                                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Position</h3>
                                <p class="mt-2 text-sm text-gray-900">{{ displayValue(serviceRequest.metadata.id_position) }}</p>
                            </div>
                            <div class="rounded-xl border border-gray-200 p-4">
                                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Office / Department</h3>
                                <p class="mt-2 text-sm text-gray-900">{{ displayValue(serviceRequest.metadata.id_office) }}</p>
                            </div>
                            <div class="rounded-xl border border-gray-200 p-4">
                                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">TIN</h3>
                                <p class="mt-2 text-sm text-gray-900">{{ idTin }}</p>
                            </div>
                            <div class="rounded-xl border border-gray-200 p-4">
                                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">GSIS Number</h3>
                                <p class="mt-2 text-sm text-gray-900">{{ displayValue(serviceRequest.metadata.id_gsis) }}</p>
                            </div>
                            <div class="rounded-xl border border-gray-200 p-4">
                                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Date of Birth</h3>
                                <p class="mt-2 text-sm text-gray-900">{{ idBirthdate }}</p>
                            </div>
                            <div class="rounded-xl border border-gray-200 p-4">
                                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Employee ID Number</h3>
                                <p class="mt-2 text-sm text-gray-900">{{ displayValue(idNumber) }}</p>
                            </div>
                            <div class="rounded-xl border border-gray-200 p-5 sm:col-span-2">
                                <h3 class="text-base font-semibold text-gray-900">Emergency Contact</h3>
                                <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Emergency Contact Name</dt>
                                        <dd class="mt-1 text-sm text-gray-900">
                                            {{ displayValue(serviceRequest.metadata.id_emergency_name || serviceRequest.metadata.emergency_name) }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Emergency Contact Phone</dt>
                                        <dd class="mt-1 text-sm font-semibold text-gray-900">
                                            {{ displayValue(serviceRequest.metadata.id_emergency_phone || serviceRequest.metadata.emergency_phone) }}
                                        </dd>
                                    </div>
                                    <div class="sm:col-span-2">
                                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Emergency Address</dt>
                                        <dd class="mt-1 text-sm text-gray-900">
                                            {{
                                                displayValue(
                                                    serviceRequest.metadata.id_emergency_address || serviceRequest.metadata.emergency_address,
                                                )
                                            }}
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        </div>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <div class="rounded-xl border border-gray-200 p-5">
                                <h3 class="text-sm font-semibold text-gray-900">2x2 ID Photo</h3>
                                <a v-if="idPhotoUrl" :href="idPhotoUrl" target="_blank" rel="noopener noreferrer" class="mt-3 inline-block">
                                    <img
                                        :src="idPhotoUrl"
                                        alt="Submitted 2x2 ID photo"
                                        class="h-48 w-40 rounded-lg border border-gray-200 bg-gray-50 object-cover"
                                    />
                                </a>
                                <p v-else class="mt-3 text-sm text-gray-500">No photo uploaded.</p>
                            </div>
                            <div class="rounded-xl border border-gray-200 p-5">
                                <h3 class="text-sm font-semibold text-gray-900">Digital Signature</h3>
                                <a
                                    v-if="signatureUrl"
                                    :href="signatureUrl"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="mt-3 inline-flex h-48 w-full items-center justify-center rounded-lg border border-gray-200 bg-gray-50 p-3"
                                >
                                    <img :src="signatureUrl" alt="Submitted digital signature" class="max-h-full max-w-full object-contain" />
                                </a>
                                <p v-else class="mt-3 text-sm text-gray-500">No signature uploaded.</p>
                            </div>
                        </div>

                        <IdCardPreview :metadata="serviceRequest.metadata" />
                    </section>

                    <section v-else-if="!isIdRequest && Object.keys(remainingMetadata).length > 0" class="rounded-xl border border-gray-200 p-5">
                        <h2 class="text-base font-semibold text-gray-900">Submitted Details</h2>
                        <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <div v-for="item in genericMetadata" :key="item.key" class="min-w-0 rounded-lg bg-gray-50 p-4">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ item.label }}</dt>
                                <dd class="mt-2 break-words text-sm text-gray-900">
                                    <span v-if="item.files.length" class="flex flex-col items-start gap-1">
                                        <a
                                            v-for="file in item.files"
                                            :key="file.url"
                                            :href="file.url"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-500"
                                        >
                                            {{ file.name }}
                                            <ArrowUpRight class="size-4 shrink-0" />
                                        </a>
                                    </span>
                                    <template v-else>{{ item.value }}</template>
                                </dd>
                            </div>
                        </dl>
                    </section>

                    <section v-if="serviceRequest.attachments.length" class="rounded-xl border border-gray-200 p-5">
                        <h2 class="flex items-center gap-2 text-base font-semibold text-gray-900">
                            <Paperclip class="size-4" />
                            Attachments
                        </h2>
                        <ul class="mt-4 divide-y divide-gray-100">
                            <li
                                v-for="attachment in serviceRequest.attachments"
                                :key="attachment.id"
                                class="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0"
                            >
                                <div class="flex min-w-0 items-center gap-3">
                                    <FileText class="size-5 shrink-0 text-gray-400" />
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-gray-900">{{ attachment.file_name }}</p>
                                        <p class="mt-0.5 text-xs text-gray-500">{{ formatFileSize(attachment.size_bytes) }}</p>
                                    </div>
                                </div>
                                <a
                                    :href="attachment.url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex shrink-0 items-center gap-1 text-sm font-semibold text-indigo-600 hover:text-indigo-500"
                                >
                                    View / Download
                                    <ArrowUpRight class="size-4" />
                                </a>
                            </li>
                        </ul>
                    </section>

                    <section class="grid gap-6 border-t border-gray-100 pt-6 sm:grid-cols-2">
                        <div>
                            <h2 class="text-sm font-semibold text-gray-900">Assigned to</h2>
                            <p class="mt-2 text-sm text-gray-600">{{ serviceRequest.assigned_staff || 'Not assigned yet' }}</p>
                        </div>
                        <div v-if="serviceRequest.resolution_summary">
                            <h2 class="text-sm font-semibold text-gray-900">Resolution / remarks</h2>
                            <p class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-600">{{ serviceRequest.resolution_summary }}</p>
                        </div>
                    </section>

                    <div class="flex items-center gap-2 rounded-lg bg-indigo-50 px-4 py-3 text-sm text-indigo-800">
                        <CheckCircle2 class="size-5 shrink-0" />
                        This page shows the latest tracking information available for your request.
                    </div>

                    <Link
                        :href="route('service-requests.index')"
                        class="inline-flex items-center gap-2 text-sm font-semibold text-indigo-600 hover:text-indigo-500"
                    >
                        <FileText class="size-4" />
                        View all my requests
                    </Link>
                </div>
            </section>
        </main>
    </AppLayout>
</template>
