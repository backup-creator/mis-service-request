<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';

type ErrorValue = string | string[] | undefined;

interface HardwareRequestValues {
    device_type: string;
    location: string;
    property_tag: string;
    selected_symptoms: string[];
    issue_summary: string;
    full_hardware_details: string;
}

const props = withDefaults(
    defineProps<{
        initial?: Partial<HardwareRequestValues>;
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
    submit: [payload: HardwareRequestValues];
}>();

const deviceTypes = [
    { value: 'Desktop Computer', icon: 'computer' },
    { value: 'Laptop', icon: 'laptop_chromebook' },
    { value: 'Printer / Scanner', icon: 'print' },
    { value: 'UPS / Power System', icon: 'battery_charging_full' },
    { value: 'Network Device / Router', icon: 'router' },
    { value: 'Peripherals / Others', icon: 'devices_other' },
];

const symptomOptions = [
    'No Power / Will Not Turn On',
    'Blue Screen / System Crash',
    'Display / Monitor Issue',
    'Overheating / Noisy Fan',
    'Paper Jam / Printing Defect',
    'Peripheral Malfunction',
];

const local = reactive<HardwareRequestValues>({
    device_type: '',
    location: '',
    property_tag: '',
    selected_symptoms: [],
    issue_summary: '',
    full_hardware_details: '',
});
const localErrors = ref<Record<string, string>>({});

watch(
    () => props.initial,
    (initial) => {
        local.device_type = initial.device_type ?? '';
        local.location = initial.location ?? '';
        local.property_tag = initial.property_tag ?? '';
        local.selected_symptoms = [...(initial.selected_symptoms ?? [])];
        local.issue_summary = initial.issue_summary ?? '';
        local.full_hardware_details = initial.full_hardware_details ?? '';
    },
    { immediate: true, deep: true },
);

const inputClass =
    'mt-2 block w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-4 focus:ring-indigo-500/10 dark:border-slate-800 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-500';

function getFieldError(field: string): string | undefined {
    const error: ErrorValue = props.validationErrors[field] ?? props.validationErrors[`metadata.${field}`];

    return localErrors.value[field] ?? (Array.isArray(error) ? error[0] : error);
}

function toggleSymptom(symptom: string) {
    local.selected_symptoms = local.selected_symptoms.includes(symptom)
        ? local.selected_symptoms.filter((selected) => selected !== symptom)
        : [...local.selected_symptoms, symptom];
}

const fullHardwareDetails = computed(() => {
    const details = [
        `Device: ${local.device_type}`,
        `Office / Department & Room: ${local.location}`,
        `Property / ICS No.: ${local.property_tag}`,
        local.selected_symptoms.length ? `Common Problems: ${local.selected_symptoms.join(', ')}` : '',
        `Issue Description: ${local.issue_summary}`,
    ].filter(Boolean);

    return details.join('\n');
});

function submitForm() {
    localErrors.value = {};
    if (!local.device_type) {
        localErrors.value.device_type = 'Select a device type.';
    }
    if (!local.location.trim()) {
        localErrors.value.location = 'Enter the office, department, and room location.';
    }
    if (!local.issue_summary.trim()) {
        localErrors.value.issue_summary = 'Describe the hardware issue.';
    }
    if (Object.keys(localErrors.value).length) {
        return;
    }

    const propertyIcsNo = local.property_tag.trim();
    local.property_tag = !propertyIcsNo || /^(?:na|n\/a|none)$/i.test(propertyIcsNo) ? 'NA' : propertyIcsNo;
    local.full_hardware_details = fullHardwareDetails.value;
    emit('submit', {
        ...local,
        selected_symptoms: [...local.selected_symptoms],
        full_hardware_details: local.full_hardware_details,
    });
}

defineExpose({ submitForm });
</script>

<template>
    <form class="animate-fadeIn space-y-7" @submit.prevent="submitForm">
        <section class="space-y-3">
            <div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Device type <span class="text-rose-500">*</span></h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Choose the equipment that needs service.</p>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <button
                    v-for="device in deviceTypes"
                    :key="device.value"
                    type="button"
                    :aria-pressed="local.device_type === device.value"
                    class="group flex min-h-28 items-center gap-4 rounded-2xl border p-4 text-left transition focus:outline-none focus:ring-4 focus:ring-indigo-500/15"
                    :class="
                        local.device_type === device.value
                            ? 'border-indigo-500 bg-indigo-50 text-indigo-800 shadow-sm dark:border-indigo-400 dark:bg-indigo-950/50 dark:text-indigo-200'
                            : 'border-slate-200 bg-white text-slate-700 hover:border-indigo-300 hover:bg-indigo-50/50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-indigo-700'
                    "
                    @click="local.device_type = device.value"
                >
                    <span class="material-symbols-outlined text-3xl text-indigo-600 dark:text-indigo-400" aria-hidden="true">{{ device.icon }}</span>
                    <span class="text-sm font-semibold">{{ device.value }}</span>
                </button>
            </div>
            <p v-if="getFieldError('device_type')" class="text-xs text-rose-600">{{ getFieldError('device_type') }}</p>
        </section>

        <section class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="hardware-location" class="block text-sm font-semibold text-slate-900 dark:text-white">
                    Office / Department &amp; Room Location <span class="text-rose-500">*</span>
                </label>
                <input
                    id="hardware-location"
                    v-model="local.location"
                    :class="inputClass"
                    type="text"
                    maxlength="255"
                    required
                    placeholder="e.g., Admin Office - Room 102"
                    autocomplete="organization-title"
                />
                <p v-if="getFieldError('location')" class="mt-2 text-xs text-rose-600">{{ getFieldError('location') }}</p>
            </div>
            <div>
                <label for="hardware-property-tag" class="block text-sm font-semibold text-slate-900 dark:text-white">
                    Property / ICS No. <span class="font-normal text-slate-500">(Optional)</span>
                </label>
                <input
                    id="hardware-property-tag"
                    v-model="local.property_tag"
                    :class="inputClass"
                    type="text"
                    maxlength="255"
                    placeholder="e.g., 2024-22-1234 or NA"
                />
                <span class="mt-2 block text-xs text-slate-500">Format: e.g., 2024-22-1234 (or enter 'NA' if unavailable).</span>
                <p v-if="getFieldError('property_tag')" class="mt-2 text-xs text-rose-600">{{ getFieldError('property_tag') }}</p>
            </div>
        </section>

        <section>
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                Common problem tags <span class="font-normal text-slate-500">(Optional)</span>
            </h3>
            <div class="mt-3 flex flex-wrap gap-2">
                <button
                    v-for="symptom in symptomOptions"
                    :key="symptom"
                    type="button"
                    :aria-pressed="local.selected_symptoms.includes(symptom)"
                    class="rounded-full border px-3 py-2 text-xs font-semibold transition focus:outline-none focus:ring-4 focus:ring-indigo-500/15"
                    :class="
                        local.selected_symptoms.includes(symptom)
                            ? 'border-indigo-600 bg-indigo-600 text-white dark:border-indigo-400 dark:bg-indigo-500'
                            : 'border-slate-200 bg-white text-slate-600 hover:border-indigo-300 hover:text-indigo-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300'
                    "
                    @click="toggleSymptom(symptom)"
                >
                    {{ symptom }}
                </button>
            </div>
            <p v-if="getFieldError('selected_symptoms')" class="mt-2 text-xs text-rose-600">
                {{ getFieldError('selected_symptoms') }}
            </p>
        </section>

        <section>
            <label for="hardware-issue-summary" class="block text-sm font-semibold text-slate-900 dark:text-white">
                Detailed Issue Description <span class="text-rose-500">*</span>
            </label>
            <textarea
                id="hardware-issue-summary"
                v-model="local.issue_summary"
                :class="inputClass"
                rows="5"
                maxlength="4000"
                required
                placeholder="Describe what happened, when the issue started, and any steps you have already tried."
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
