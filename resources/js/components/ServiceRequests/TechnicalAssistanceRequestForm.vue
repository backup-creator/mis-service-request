<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';

type ErrorValue = string | string[] | undefined;

interface TechnicalAssistanceValues {
    assistance_type: string;
    venue: string;
    schedule: string;
    equipment_needed: string[];
    issue_summary: string;
    formatted_details: string;
}

const props = withDefaults(
    defineProps<{
        initial?: Partial<TechnicalAssistanceValues>;
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
    submit: [payload: TechnicalAssistanceValues];
}>();

const assistanceTypes = [
    { value: 'Virtual Meeting', icon: 'videocam', description: 'Zoom, Google Meet setup and hosting.' },
    { value: 'Event / Seminar', icon: 'campaign', description: 'Technical support for live events, conferences, and seminars.' },
    { value: 'AV / Equipment Setup', icon: 'speaker', description: 'Projector, sound system, and multimedia setup.' },
    { value: 'Others', icon: 'support_agent', description: 'General technical assistance.' },
];

const equipmentOptions = [
    'Projector & Screen',
    'Sound System / Speakers',
    'Microphones',
    'Extension Wires',
    'Webcam / PTZ Camera',
    'Internet/WiFi Voucher',
];

const local = reactive<TechnicalAssistanceValues>({
    assistance_type: '',
    venue: '',
    schedule: '',
    equipment_needed: [],
    issue_summary: '',
    formatted_details: '',
});
const localErrors = ref<Record<string, string>>({});

watch(
    () => props.initial,
    (initial) => {
        local.assistance_type = initial.assistance_type ?? '';
        local.venue = initial.venue ?? '';
        local.schedule = initial.schedule ?? '';
        local.equipment_needed = [...(initial.equipment_needed ?? [])];
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

function toggleEquipment(item: string) {
    local.equipment_needed = local.equipment_needed.includes(item)
        ? local.equipment_needed.filter((selected) => selected !== item)
        : [...local.equipment_needed, item];
}

const formattedDetails = computed(
    () =>
        `Category: ${local.assistance_type} | Venue: ${local.venue} | Schedule: ${local.schedule} | Equipment: ${local.equipment_needed.join(', ') || 'None'}`,
);

function submitForm() {
    localErrors.value = {};
    if (!local.assistance_type) {
        localErrors.value.assistance_type = 'Select a technical assistance category.';
    }
    if (!local.venue.trim()) {
        localErrors.value.venue = 'Enter the venue or location.';
    }
    if (!local.schedule) {
        localErrors.value.schedule = 'Choose the date and time assistance is needed.';
    }
    if (!local.issue_summary.trim()) {
        localErrors.value.issue_summary = 'Describe the event and technical assistance needed.';
    }

    if (Object.keys(localErrors.value).length) {
        return;
    }

    local.venue = local.venue.trim();
    local.formatted_details = formattedDetails.value;
    emit('submit', {
        ...local,
        equipment_needed: [...local.equipment_needed],
    });
}

defineExpose({ submitForm });
</script>

<template>
    <form class="animate-fadeIn space-y-7" @submit.prevent="submitForm">
        <section class="space-y-3">
            <div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Assistance category <span class="text-rose-500">*</span></h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Choose the type of technical support you need.</p>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <button
                    v-for="assistance in assistanceTypes"
                    :key="assistance.value"
                    type="button"
                    :aria-pressed="local.assistance_type === assistance.value"
                    class="group flex min-h-32 items-start gap-4 rounded-2xl border p-4 text-left transition focus:outline-none focus:ring-4 focus:ring-indigo-500/15"
                    :class="
                        local.assistance_type === assistance.value
                            ? 'border-indigo-500 bg-indigo-50 text-indigo-800 shadow-sm dark:border-indigo-400 dark:bg-indigo-950/50 dark:text-indigo-200'
                            : 'border-slate-200 bg-white text-slate-700 hover:border-indigo-300 hover:bg-indigo-50/50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-indigo-700'
                    "
                    @click="local.assistance_type = assistance.value"
                >
                    <span class="material-symbols-outlined text-3xl text-indigo-600 dark:text-indigo-400" aria-hidden="true">
                        {{ assistance.icon }}
                    </span>
                    <span>
                        <span class="block text-sm font-semibold">{{ assistance.value }}</span>
                        <span class="mt-1 block text-xs text-slate-500 dark:text-slate-400">{{ assistance.description }}</span>
                    </span>
                </button>
            </div>
            <p v-if="getFieldError('assistance_type')" class="text-xs text-rose-600">{{ getFieldError('assistance_type') }}</p>
        </section>

        <section class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="technical-assistance-venue" class="block text-sm font-semibold text-slate-900 dark:text-white">
                    Venue / Location <span class="text-rose-500">*</span>
                </label>
                <input
                    id="technical-assistance-venue"
                    v-model="local.venue"
                    :class="inputClass"
                    type="text"
                    maxlength="255"
                    required
                    placeholder="e.g., Main Boardroom or Gymnasium"
                />
                <p v-if="getFieldError('venue')" class="mt-2 text-xs text-rose-600">{{ getFieldError('venue') }}</p>
            </div>
            <div>
                <label for="technical-assistance-schedule" class="block text-sm font-semibold text-slate-900 dark:text-white">
                    Schedule / Date &amp; Time <span class="text-rose-500">*</span>
                </label>
                <input id="technical-assistance-schedule" v-model="local.schedule" :class="inputClass" type="datetime-local" required />
                <p v-if="getFieldError('schedule')" class="mt-2 text-xs text-rose-600">{{ getFieldError('schedule') }}</p>
            </div>
        </section>

        <section>
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                Equipment / Requirements <span class="font-normal text-slate-500">(Optional)</span>
            </h3>
            <div class="mt-3 flex flex-wrap gap-2">
                <button
                    v-for="item in equipmentOptions"
                    :key="item"
                    type="button"
                    :aria-pressed="local.equipment_needed.includes(item)"
                    class="rounded-full border px-3 py-2 text-xs font-semibold transition focus:outline-none focus:ring-4 focus:ring-indigo-500/15"
                    :class="
                        local.equipment_needed.includes(item)
                            ? 'border-indigo-600 bg-indigo-600 text-white dark:border-indigo-400 dark:bg-indigo-500'
                            : 'border-slate-200 bg-white text-slate-600 hover:border-indigo-300 hover:text-indigo-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300'
                    "
                    @click="toggleEquipment(item)"
                >
                    {{ item }}
                </button>
            </div>
            <p v-if="getFieldError('equipment_needed')" class="mt-2 text-xs text-rose-600">
                {{ getFieldError('equipment_needed') }}
            </p>
        </section>

        <section>
            <label for="technical-assistance-summary" class="block text-sm font-semibold text-slate-900 dark:text-white">
                Detailed Support Summary <span class="text-rose-500">*</span>
            </label>
            <textarea
                id="technical-assistance-summary"
                v-model="local.issue_summary"
                :class="inputClass"
                rows="5"
                maxlength="4000"
                required
                placeholder="Please describe the event and the specific technical assistance required, including any important setup details."
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
