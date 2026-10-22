<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';

type ErrorValue = string | string[] | undefined;

interface SoftwareRequestValues {
    software_type: string;
    action_type: string;
    selected_symptoms: string[];
    asset_tag: string;
    issue_summary: string;
    formatted_details: string;
}

const props = withDefaults(
    defineProps<{
        initial?: Partial<SoftwareRequestValues>;
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
    submit: [payload: SoftwareRequestValues];
}>();

const softwareTypes = [
    { value: 'Windows / OS', icon: 'desktop_windows', description: 'Windows OS installation, updates, and boot errors.' },
    { value: 'Microsoft Office / Productivity', icon: 'description', description: 'Word, Excel, PowerPoint, and Outlook.' },
    { value: 'Antivirus & Security', icon: 'shield', description: 'Virus scans, malware cleanup, and firewall setup.' },
    { value: 'Specialized / Licensed Software', icon: 'apps', description: 'Campus-licensed tools such as SPSS or AutoCAD.' },
    { value: 'Drivers & Utilities', icon: 'tune', description: 'Audio, display, or printer driver issues.' },
    { value: 'Others / General App Issue', icon: 'extension', description: 'Other software tools and application issues.' },
];

const actionTypes = ['Software Installation', 'License Activation / Product Key', 'Bug / Crash / Error Fix', 'Version Upgrade Request'];

const symptomOptions = [
    'License Expired / Product Key Invalid',
    'App Freezing / Crashing on Launch',
    'Missing DLL / System Driver Error',
    'Virus / Malware Suspected',
    'Installation File Corrupted',
    'Slow App Performance',
];

const local = reactive<SoftwareRequestValues>({
    software_type: '',
    action_type: '',
    selected_symptoms: [],
    asset_tag: '',
    issue_summary: '',
    formatted_details: '',
});
const localErrors = ref<Record<string, string>>({});

watch(
    () => props.initial,
    (initial) => {
        local.software_type = initial.software_type ?? '';
        local.action_type = initial.action_type ?? '';
        local.selected_symptoms = [...(initial.selected_symptoms ?? [])];
        local.asset_tag = initial.asset_tag ?? '';
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

function toggleSymptom(symptom: string) {
    local.selected_symptoms = local.selected_symptoms.includes(symptom)
        ? local.selected_symptoms.filter((selected) => selected !== symptom)
        : [...local.selected_symptoms, symptom];
}

const formattedDetails = computed(
    () => `Category: ${local.software_type} | Action: ${local.action_type} | Issues: ${local.selected_symptoms.join(', ')}`,
);

function submitForm() {
    localErrors.value = {};
    if (!local.software_type) {
        localErrors.value.software_type = 'Select a software category.';
    }
    if (!local.action_type) {
        localErrors.value.action_type = 'Select the assistance needed.';
    }
    if (!local.issue_summary.trim()) {
        localErrors.value.issue_summary = 'Describe the software issue or request.';
    }

    if (Object.keys(localErrors.value).length) {
        return;
    }

    const propertyIcsNo = local.asset_tag.trim();
    local.asset_tag = !propertyIcsNo || /^(?:na|n\/a|none)$/i.test(propertyIcsNo) ? 'NA' : propertyIcsNo;
    local.formatted_details = formattedDetails.value;
    emit('submit', { ...local, selected_symptoms: [...local.selected_symptoms] });
}

defineExpose({ submitForm });
</script>

<template>
    <form class="animate-fadeIn space-y-7" @submit.prevent="submitForm">
        <section class="space-y-3">
            <div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Software category <span class="text-rose-500">*</span></h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Choose the software area that best matches your request.</p>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <button
                    v-for="software in softwareTypes"
                    :key="software.value"
                    type="button"
                    :aria-pressed="local.software_type === software.value"
                    class="group flex min-h-32 items-start gap-4 rounded-2xl border p-4 text-left transition focus:outline-none focus:ring-4 focus:ring-indigo-500/15"
                    :class="
                        local.software_type === software.value
                            ? 'border-indigo-500 bg-indigo-50 text-indigo-800 shadow-sm dark:border-indigo-400 dark:bg-indigo-950/50 dark:text-indigo-200'
                            : 'border-slate-200 bg-white text-slate-700 hover:border-indigo-300 hover:bg-indigo-50/50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-indigo-700'
                    "
                    @click="local.software_type = software.value"
                >
                    <span class="material-symbols-outlined text-3xl text-indigo-600 dark:text-indigo-400" aria-hidden="true">
                        {{ software.icon }}
                    </span>
                    <span>
                        <span class="block text-sm font-semibold">{{ software.value }}</span>
                        <span class="mt-1 block text-xs leading-5 text-slate-500 dark:text-slate-400">{{ software.description }}</span>
                    </span>
                </button>
            </div>
            <p v-if="getFieldError('software_type')" class="text-xs text-rose-600">{{ getFieldError('software_type') }}</p>
        </section>

        <section>
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">Assistance needed <span class="text-rose-500">*</span></h3>
            <div class="mt-3 flex flex-wrap gap-2">
                <button
                    v-for="action in actionTypes"
                    :key="action"
                    type="button"
                    :aria-pressed="local.action_type === action"
                    class="rounded-full border px-4 py-2.5 text-sm font-semibold transition focus:outline-none focus:ring-4 focus:ring-indigo-500/15"
                    :class="
                        local.action_type === action
                            ? 'border-indigo-600 bg-indigo-600 text-white dark:border-indigo-400 dark:bg-indigo-500'
                            : 'border-slate-200 bg-white text-slate-600 hover:border-indigo-300 hover:text-indigo-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300'
                    "
                    @click="local.action_type = action"
                >
                    {{ action }}
                </button>
            </div>
            <p v-if="getFieldError('action_type')" class="mt-2 text-xs text-rose-600">{{ getFieldError('action_type') }}</p>
        </section>

        <section>
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                Common software symptoms <span class="font-normal text-slate-500">(Optional)</span>
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
            <p v-if="getFieldError('selected_symptoms')" class="mt-2 text-xs text-rose-600">{{ getFieldError('selected_symptoms') }}</p>
        </section>

        <section class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="software-asset-tag" class="block text-sm font-semibold text-slate-900 dark:text-white">
                    Property / ICS No. <span class="font-normal text-slate-500">(Optional)</span>
                </label>
                <input
                    id="software-asset-tag"
                    v-model="local.asset_tag"
                    :class="inputClass"
                    type="text"
                    maxlength="255"
                    placeholder="e.g., 2024-22-1234 or NA"
                />
                <span class="mt-2 block text-xs text-slate-500">Format: e.g., 2024-22-1234 (or enter 'NA' if unavailable).</span>
                <p v-if="getFieldError('asset_tag')" class="mt-2 text-xs text-rose-600">{{ getFieldError('asset_tag') }}</p>
            </div>
        </section>

        <section>
            <label for="software-issue-summary" class="block text-sm font-semibold text-slate-900 dark:text-white">
                Detailed Issue Context <span class="text-rose-500">*</span>
            </label>
            <textarea
                id="software-issue-summary"
                v-model="local.issue_summary"
                :class="inputClass"
                rows="5"
                maxlength="4000"
                required
                placeholder="Include the software name and version, what you were doing, any error messages, and when the issue started."
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
