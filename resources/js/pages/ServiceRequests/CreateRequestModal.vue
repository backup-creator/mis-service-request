<script setup lang="ts">
import Modal from '@/components/Modal.vue';
import AccountAccessRequestForm from '@/components/ServiceRequests/AccountAccessRequestForm.vue';
import CategorySelection from '@/components/ServiceRequests/CategorySelection.vue';
import IdRequestForm from '@/components/ServiceRequests/Forms/IdRequestForm.vue';
import HardwareSupportRequestForm from '@/components/ServiceRequests/HardwareSupportRequestForm.vue';
import JobRequestForm from '@/components/ServiceRequests/JobRequestForm.vue';
import NetworkSupportRequestForm from '@/components/ServiceRequests/NetworkSupportRequestForm.vue';
import OtherRequestForm from '@/components/ServiceRequests/OtherRequestForm.vue';
import ServiceRequestForm from '@/components/ServiceRequests/ServiceRequestForm.vue';
import SoftwareSupportRequestForm from '@/components/ServiceRequests/SoftwareSupportRequestForm.vue';
import TechnicalAssistanceRequestForm from '@/components/ServiceRequests/TechnicalAssistanceRequestForm.vue';
import { Button } from '@/components/ui/button';
import { useServiceRequestWizard } from '@/composables/useServiceRequestWizard.js';
import { usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type { SharedData } from '@/types';

interface LookupOption {
    id: number;
    name: string;
    code: string;
    description?: string | null;
}

const props = withDefaults(
    defineProps<{
        show?: boolean;
        categories: LookupOption[];
        priorities: LookupOption[];
    }>(),
    {
        show: true,
    },
);

const emit = defineEmits<{
    close: [];
}>();

const page = usePage<SharedData>();

const {
    selectedCategoryName,
    selectedCategoryCode,
    currentStep,
    form,
    submissionPayload,
    selectCategory,
    backToCategories,
    appendFiles,
    removeFile,
    attachmentError,
    idRequestValidationErrors,
    submitIdRequest,
    submitAccountAccess,
    submitHardwareSupport,
    submitJobRequest,
    submitNetworkSupport,
    submitOtherRequest,
    submitSoftwareSupport,
    submitTechnicalAssistance,
    stageSubmission,
    submit,
} = useServiceRequestWizard({
    onSuccess: () => emit('close'),
    priorities: props.priorities,
    locationId: page.props.auth.user.location_id,
    departmentId: page.props.auth.user.department_id,
});

const availableDepartments = computed(() =>
    page.props.officeOptions.departments.filter((department) => department.location_id === Number(form.location_id)),
);
const idRequestForm = ref<{ submitForm: () => void } | null>(null);
const accountAccessForm = ref<{ submitForm: () => void } | null>(null);
const hardwareSupportForm = ref<{ submitForm: () => void } | null>(null);
const jobRequestForm = ref<{ submitForm: () => void } | null>(null);
const networkSupportForm = ref<{ submitForm: () => void } | null>(null);
const otherRequestForm = ref<{ submitForm: () => void } | null>(null);
const softwareSupportForm = ref<{ submitForm: () => void } | null>(null);
const technicalAssistanceForm = ref<{ submitForm: () => void } | null>(null);
const serviceRequestForm = ref<{ submitForm: () => void } | null>(null);
const validationErrorMessages = computed(() => Object.entries(form.errors).map(([field, message]) => `${field}: ${message}`));
const wizardSteps = [
    { number: 1, label: 'Select Category' },
    { number: 2, label: 'Request Details' },
    { number: 3, label: 'Preview & Submit' },
];
const previewMetadata = computed(() =>
    Object.entries(form.metadata ?? {}).map(([key, value]) => ({
        label: key.replace(/_/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase()),
        value: Array.isArray(value)
            ? value.every((item) => typeof item !== 'object' || item === null)
                ? value.map(String).join(', ') || 'None'
                : `${value.length} entries`
            : typeof value === 'object' && value !== null
              ? JSON.stringify(value)
              : String(value ?? '—'),
    })),
);

function stageGenericRequest() {
    stageSubmission({
        title: form.title,
        service_request_priority_id: form.service_request_priority_id,
        subtype: form.subtype,
        description: form.description,
        attachments: [...form.attachments],
    });
}

function goToPreview() {
    if (selectedCategoryCode.value === 'ID_REQUEST') {
        idRequestForm.value?.submitForm();
    } else if (selectedCategoryCode.value === 'ACCESS') {
        accountAccessForm.value?.submitForm();
    } else if (selectedCategoryCode.value === 'HARDWARE') {
        hardwareSupportForm.value?.submitForm();
    } else if (selectedCategoryCode.value === 'SOFTWARE') {
        softwareSupportForm.value?.submitForm();
    } else if (selectedCategoryCode.value === 'NETWORK') {
        networkSupportForm.value?.submitForm();
    } else if (selectedCategoryCode.value === 'JOB_REQUEST') {
        jobRequestForm.value?.submitForm();
    } else if (selectedCategoryCode.value === 'TECH_ASSIST') {
        technicalAssistanceForm.value?.submitForm();
    } else if (selectedCategoryCode.value === 'OTHERS') {
        otherRequestForm.value?.submitForm();
    } else {
        serviceRequestForm.value?.submitForm();
    }
}
</script>

<template>
    <Modal :show="props.show" max-width="5xl" @close="emit('close')">
        <template #title>
            {{ currentStep === 1 ? 'New Service Request' : currentStep === 2 ? 'Tell us what you need' : 'Review your request' }}
        </template>
        <template #description>
            <span class="font-semibold text-indigo-600">MIS Service Desk</span>
            <span class="mx-1">·</span>
            {{ currentStep === 1 ? 'Choose a category to get started.' : `Category: ${selectedCategoryName}` }}
        </template>

        <div class="p-5 sm:p-8">
            <nav aria-label="Request submission progress" class="mb-7">
                <ol class="flex items-center">
                    <template v-for="(wizardStep, index) in wizardSteps" :key="wizardStep.number">
                        <li class="flex min-w-0 items-center gap-2">
                            <span
                                class="flex size-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold"
                                :class="
                                    currentStep === wizardStep.number
                                        ? 'bg-blue-600 text-white'
                                        : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'
                                "
                                :aria-current="currentStep === wizardStep.number ? 'step' : undefined"
                            >
                                {{ wizardStep.number }}
                            </span>
                            <span
                                class="hidden text-xs font-medium sm:inline"
                                :class="currentStep === wizardStep.number ? 'text-slate-900 dark:text-white' : 'text-slate-500 dark:text-slate-400'"
                            >
                                {{ wizardStep.label }}
                            </span>
                        </li>
                        <li
                            v-if="index < wizardSteps.length - 1"
                            aria-hidden="true"
                            class="mx-2 h-px min-w-4 flex-1 bg-slate-200 dark:bg-slate-700 sm:mx-4"
                        />
                    </template>
                </ol>
            </nav>

            <div
                v-if="validationErrorMessages.length"
                role="alert"
                class="mb-5 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"
            >
                <p class="font-semibold">The request could not be submitted. Please review the following:</p>
                <ul class="mt-2 list-inside list-disc space-y-1">
                    <li v-for="error in validationErrorMessages" :key="error">{{ error }}</li>
                </ul>
            </div>

            <div>
                <Transition name="fade">
                    <div v-show="currentStep === 1">
                        <CategorySelection :categories="categories" @select="selectCategory" />
                    </div>
                </Transition>
                <Transition name="fade">
                    <div v-show="currentStep === 2" :key="selectedCategoryCode">
                        <div class="mb-5 grid gap-5 rounded-xl border border-slate-200 bg-slate-50 p-4 sm:grid-cols-2 dark:border-slate-700 dark:bg-slate-800">
                            <div>
                                <label for="request-location" class="block text-sm font-semibold text-slate-900 dark:text-white">
                                    Location / Campus *
                                </label>
                                <select
                                    id="request-location"
                                    v-model="form.location_id"
                                    required
                                    class="mt-2 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    @change="form.department_id = ''"
                                >
                                    <option value="" disabled>Select a campus</option>
                                    <option
                                        v-for="location in page.props.officeOptions.locations"
                                        :key="location.id"
                                        :value="location.id"
                                    >
                                        {{ location.name }}
                                    </option>
                                </select>
                                <p v-if="form.errors.location_id" class="mt-2 text-sm text-rose-600">{{ form.errors.location_id }}</p>
                            </div>
                            <div>
                                <label for="request-department" class="block text-sm font-semibold text-slate-900 dark:text-white">
                                    Department / Office *
                                </label>
                                <select
                                    id="request-department"
                                    v-model="form.department_id"
                                    required
                                    :disabled="!form.location_id"
                                    class="mt-2 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-slate-100"
                                >
                                    <option value="" disabled>Select a department</option>
                                    <option v-for="department in availableDepartments" :key="department.id" :value="department.id">
                                        {{ department.name }}
                                    </option>
                                </select>
                                <p v-if="form.errors.department_id" class="mt-2 text-sm text-rose-600">{{ form.errors.department_id }}</p>
                            </div>
                        </div>
                        <IdRequestForm
                            v-if="selectedCategoryCode === 'ID_REQUEST'"
                            ref="idRequestForm"
                            id="id-request-form"
                            :initial="form.metadata"
                            :loading="form.processing"
                            :validation-errors="idRequestValidationErrors()"
                            @submit="submitIdRequest"
                        />
                        <AccountAccessRequestForm
                            v-else-if="selectedCategoryCode === 'ACCESS'"
                            ref="accountAccessForm"
                            :initial="form.metadata"
                            :loading="form.processing"
                            :validation-errors="form.errors"
                            @submit="submitAccountAccess"
                        />
                        <HardwareSupportRequestForm
                            v-else-if="selectedCategoryCode === 'HARDWARE'"
                            ref="hardwareSupportForm"
                            :initial="form.metadata"
                            :loading="form.processing"
                            :validation-errors="form.errors"
                            @submit="submitHardwareSupport"
                        />
                        <SoftwareSupportRequestForm
                            v-else-if="selectedCategoryCode === 'SOFTWARE'"
                            ref="softwareSupportForm"
                            :initial="form.metadata"
                            :loading="form.processing"
                            :validation-errors="form.errors"
                            @submit="submitSoftwareSupport"
                        />
                        <NetworkSupportRequestForm
                            v-else-if="selectedCategoryCode === 'NETWORK'"
                            ref="networkSupportForm"
                            :initial="form.metadata"
                            :loading="form.processing"
                            :validation-errors="form.errors"
                            @submit="submitNetworkSupport"
                        />
                        <JobRequestForm
                            v-else-if="selectedCategoryCode === 'JOB_REQUEST'"
                            ref="jobRequestForm"
                            :initial="form.metadata"
                            :loading="form.processing"
                            :validation-errors="form.errors"
                            @submit="submitJobRequest"
                        />
                        <TechnicalAssistanceRequestForm
                            v-else-if="selectedCategoryCode === 'TECH_ASSIST'"
                            ref="technicalAssistanceForm"
                            :initial="form.metadata"
                            :loading="form.processing"
                            :validation-errors="form.errors"
                            @submit="submitTechnicalAssistance"
                        />
                        <OtherRequestForm
                            v-else-if="selectedCategoryCode === 'OTHERS'"
                            ref="otherRequestForm"
                            :initial="form.metadata"
                            :loading="form.processing"
                            :validation-errors="form.errors"
                            @submit="submitOtherRequest"
                        />
                        <ServiceRequestForm
                            v-else
                            ref="serviceRequestForm"
                            :title="form.title"
                            :service-request-priority-id="form.service_request_priority_id"
                            :description="form.description"
                            :subtype="form.subtype"
                            :attachments="form.attachments"
                            :errors="form.errors"
                            :priorities="priorities"
                            :append-files="appendFiles"
                            :remove-file="removeFile"
                            :attachment-error="attachmentError"
                            @update:title="form.title = $event"
                            @update:service-request-priority-id="form.service_request_priority_id = $event"
                            @update:description="form.description = $event"
                            @update:subtype="form.subtype = $event"
                            @submit="stageGenericRequest"
                        />
                    </div>
                </Transition>

                <Transition name="fade">
                    <section
                        v-show="currentStep === 3 && submissionPayload"
                        class="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900"
                    >
                        <div>
                            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Review your request</h2>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                Check these details before submitting. You can go back to make changes.
                            </p>
                        </div>
                        <dl class="grid gap-4 sm:grid-cols-2">
                            <div class="rounded-xl bg-slate-50 p-4 dark:bg-slate-800">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Category</dt>
                                <dd class="mt-1 text-sm font-medium text-slate-900 dark:text-white">{{ selectedCategoryName }}</dd>
                            </div>
                            <div class="rounded-xl bg-slate-50 p-4 dark:bg-slate-800">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Request title</dt>
                                <dd class="mt-1 text-sm font-medium text-slate-900 dark:text-white">{{ form.title }}</dd>
                            </div>
                            <div class="rounded-xl bg-slate-50 p-4 dark:bg-slate-800">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Priority</dt>
                                <dd class="mt-1 text-sm font-medium text-slate-900 dark:text-white">
                                    {{ priorities.find((priority) => priority.id === form.service_request_priority_id)?.name || 'Not selected' }}
                                </dd>
                            </div>
                            <div class="rounded-xl bg-slate-50 p-4 dark:bg-slate-800">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Location / Campus</dt>
                                <dd class="mt-1 text-sm font-medium text-slate-900 dark:text-white">
                                    {{ page.props.officeOptions.locations.find((location) => location.id === Number(form.location_id))?.name || 'Not selected' }}
                                </dd>
                            </div>
                            <div class="rounded-xl bg-slate-50 p-4 dark:bg-slate-800">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Department / Office</dt>
                                <dd class="mt-1 text-sm font-medium text-slate-900 dark:text-white">
                                    {{ availableDepartments.find((department) => department.id === Number(form.department_id))?.name || 'Not selected' }}
                                </dd>
                            </div>
                            <div v-if="form.subtype" class="rounded-xl bg-slate-50 p-4 dark:bg-slate-800">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Request type</dt>
                                <dd class="mt-1 text-sm font-medium text-slate-900 dark:text-white">{{ form.subtype }}</dd>
                            </div>
                            <div class="rounded-xl bg-slate-50 p-4 dark:bg-slate-800 sm:col-span-2">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Description</dt>
                                <dd class="mt-1 whitespace-pre-line text-sm text-slate-700 dark:text-slate-200">{{ form.description }}</dd>
                            </div>
                            <div v-for="item in previewMetadata" :key="item.label" class="rounded-xl bg-slate-50 p-4 dark:bg-slate-800">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ item.label }}</dt>
                                <dd class="mt-1 break-words text-sm text-slate-700 dark:text-slate-200">{{ item.value }}</dd>
                            </div>
                            <div v-if="form.attachments.length" class="rounded-xl bg-slate-50 p-4 dark:bg-slate-800 sm:col-span-2">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Attachments</dt>
                                <dd class="mt-2 text-sm text-slate-700 dark:text-slate-200">
                                    {{ form.attachments.map((file) => file.name).join(', ') }}
                                </dd>
                            </div>
                        </dl>
                    </section>
                </Transition>
            </div>
        </div>

        <template #footer>
            <div v-if="currentStep === 2" class="flex flex-col-reverse gap-3 px-5 py-4 sm:flex-row sm:justify-between sm:px-8">
                <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    @click="backToCategories"
                >
                    Back to categories
                </button>
                <Button
                    type="button"
                    :disabled="form.processing"
                    class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-60"
                    @click="goToPreview"
                >
                    Next: Preview
                </Button>
            </div>
            <div v-else-if="currentStep === 3" class="flex flex-col-reverse gap-3 px-5 py-4 sm:flex-row sm:justify-between sm:px-8">
                <button
                    type="button"
                    :disabled="form.processing"
                    class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-60 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                    @click="currentStep = 2"
                >
                    Back to details
                </button>
                <Button
                    type="button"
                    :disabled="form.processing"
                    class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-60"
                    @click="submit"
                >
                    {{ form.processing ? 'Submitting…' : 'Submit Request' }}
                </Button>
            </div>
        </template>
    </Modal>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 150ms ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
