<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';

type ErrorValue = string | string[] | undefined;

interface JobRequestValues {
    job_type: string;
    location: string;
    equipment_involved: string;
    preferred_schedule: string;
    issue_summary: string;
    formatted_details: string;
}

const props = withDefaults(
    defineProps<{
        initial?: Partial<JobRequestValues>;
        loading?: boolean;
        validationErrors?: Record<string, ErrorValue>;
    }>(),
    {
        initial: () => ({}),
        loading: false,
        validationErrors: () => ({}),
    },
);

const emit = defineEmits<{
    submit: [payload: JobRequestValues];
}>();

const jobTypes = [
    { value: 'Deployment / Installation', icon: 'build', description: 'Set up new equipment or workstations.' },
    { value: 'Transfer / Relocation', icon: 'local_shipping', description: 'Move equipment to another office or room.' },
    { value: 'Preventive Maintenance', icon: 'cleaning_services', description: 'Routine cleaning, dusting, and check-ups.' },
    { value: 'Inspection / Assessment', icon: 'search', description: 'Assess defective items for repair or condemnation.' },
    { value: 'Others', icon: 'work', description: 'Request general IT or physical work.' },
];

const local = reactive<JobRequestValues>({
    job_type: '',
    location: '',
    equipment_involved: '',
    preferred_schedule: '',
    issue_summary: '',
    formatted_details: '',
});
const localErrors = ref<Record<string, string>>({});

watch(
    () => props.initial,
    (initial) => {
        local.job_type = initial.job_type ?? '';
        local.location = initial.location ?? '';
        local.equipment_involved = initial.equipment_involved ?? '';
        local.preferred_schedule = initial.preferred_schedule ?? '';
        local.issue_summary = initial.issue_summary ?? '';
        local.formatted_details = initial.formatted_details ?? '';
    },
    { immediate: true, deep: true },
);

const inputClass =
    'mt-2 block w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-4 focus:ring-indigo-500/10 dark:border-slate-800 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-500';

function getFieldError(field: string): string | undefined {
    const error: ErrorValue = props.validationErrors[field] ?? props.validationErrors[`metadata.${field}`];

    return localErrors.value[field] ?? (Array.isArray(error) ? error[0] : error);
}

const formattedDetails = computed(
    () => `Category: ${local.job_type} | Location: ${local.location} | Equipment: ${local.equipment_involved || 'N/A'}`,
);

function submitForm() {
    localErrors.value = {};
    if (!local.job_type) {
        localErrors.value.job_type = 'Select a job category.';
    }
    if (!local.location.trim()) {
        localErrors.value.location = 'Enter the location or office for this job.';
    }
    if (!local.issue_summary.trim()) {
        localErrors.value.issue_summary = 'Describe the work that needs to be done.';
    }

    if (Object.keys(localErrors.value).length) {
        return;
    }

    local.location = local.location.trim();
    local.equipment_involved = local.equipment_involved.trim();
    local.preferred_schedule = local.preferred_schedule.trim();
    local.formatted_details = formattedDetails.value;
    emit('submit', { ...local });
}

defineExpose({ submitForm });
</script>

<template>
    <form class="animate-fadeIn space-y-7" @submit.prevent="submitForm">
        <section class="space-y-3">
            <div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Job category <span class="text-rose-500">*</span></h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Choose the type of work you need.</p>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <button
                    v-for="job in jobTypes"
                    :key="job.value"
                    type="button"
                    :aria-pressed="local.job_type === job.value"
                    class="group flex min-h-32 items-start gap-4 rounded-2xl border p-4 text-left transition focus:outline-none focus:ring-4 focus:ring-indigo-500/15"
                    :class="
                        local.job_type === job.value
                            ? 'border-indigo-500 bg-indigo-50 text-indigo-800 shadow-sm dark:border-indigo-400 dark:bg-indigo-950/50 dark:text-indigo-200'
                            : 'border-slate-200 bg-white text-slate-700 hover:border-indigo-300 hover:bg-indigo-50/50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-indigo-700'
                    "
                    @click="local.job_type = job.value"
                >
                    <span class="material-symbols-outlined text-3xl text-indigo-600 dark:text-indigo-400" aria-hidden="true">
                        {{ job.icon }}
                    </span>
                    <span>
                        <span class="block text-sm font-semibold">{{ job.value }}</span>
                        <span class="mt-1 block text-xs text-slate-500 dark:text-slate-400">{{ job.description }}</span>
                    </span>
                </button>
            </div>
            <p v-if="getFieldError('job_type')" class="text-xs text-rose-600">{{ getFieldError('job_type') }}</p>
        </section>

        <section class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="job-location" class="block text-sm font-semibold text-slate-900 dark:text-white">
                    Location / Office <span class="text-rose-500">*</span>
                </label>
                <input
                    id="job-location"
                    v-model="local.location"
                    :class="inputClass"
                    type="text"
                    maxlength="255"
                    required
                    autocomplete="organization-title"
                    placeholder="e.g., From Room A to Room B"
                />
                <p v-if="getFieldError('location')" class="mt-2 text-xs text-rose-600">{{ getFieldError('location') }}</p>
            </div>
            <div>
                <label for="job-equipment" class="block text-sm font-semibold text-slate-900 dark:text-white">
                    Equipment / Asset Involved <span class="font-normal text-slate-500">(Optional)</span>
                </label>
                <input
                    id="job-equipment"
                    v-model="local.equipment_involved"
                    :class="inputClass"
                    type="text"
                    maxlength="255"
                    placeholder="e.g., 3 Desktop PCs or Server Rack"
                />
                <p v-if="getFieldError('equipment_involved')" class="mt-2 text-xs text-rose-600">
                    {{ getFieldError('equipment_involved') }}
                </p>
            </div>
        </section>

        <section>
            <label for="job-preferred-schedule" class="block text-sm font-semibold text-slate-900 dark:text-white">
                Preferred Schedule <span class="font-normal text-slate-500">(Optional)</span>
            </label>
            <input
                id="job-preferred-schedule"
                v-model="local.preferred_schedule"
                :class="inputClass"
                type="text"
                maxlength="255"
                placeholder="e.g., Friday Afternoon or Oct 25"
            />
            <p v-if="getFieldError('preferred_schedule')" class="mt-2 text-xs text-rose-600">
                {{ getFieldError('preferred_schedule') }}
            </p>
        </section>

        <section>
            <label for="job-issue-summary" class="block text-sm font-semibold text-slate-900 dark:text-white">
                Detailed Job Summary <span class="text-rose-500">*</span>
            </label>
            <textarea
                id="job-issue-summary"
                v-model="local.issue_summary"
                :class="inputClass"
                rows="5"
                maxlength="4000"
                required
                placeholder="Please describe the work that needs to be done and any details the team should know."
            />
            <p v-if="getFieldError('issue_summary')" class="mt-2 text-xs text-rose-600">{{ getFieldError('issue_summary') }}</p>
        </section>
    </form>
</template>

<style scoped>
@keyframes fade-in {
    from {
        opacity: 0;
        transform: translateY(4px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-fadeIn {
    animation: fade-in 180ms ease-out;
}
</style>
