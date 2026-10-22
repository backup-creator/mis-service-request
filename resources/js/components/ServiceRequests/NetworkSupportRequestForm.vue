<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';

type ErrorValue = string | string[] | undefined;

interface NetworkRequestValues {
    network_type: string;
    location: string;
    selected_symptoms: string[];
    issue_summary: string;
    formatted_details: string;
}

const props = withDefaults(
    defineProps<{
        initial?: Partial<NetworkRequestValues>;
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
    submit: [payload: NetworkRequestValues];
}>();

const networkTypes = [
    { value: 'WiFi Connection', icon: 'wifi', description: 'Connection, weak signal, or portal login issues.' },
    { value: 'Wired / LAN', icon: 'settings_ethernet', description: 'Broken port, disconnected cable, or no network access.' },
    { value: 'General Internet / ISP', icon: 'public', description: 'Completely offline or slow browsing.' },
    { value: 'VPN Access', icon: 'vpn_key', description: 'Cannot connect to the campus VPN.' },
    { value: 'Others', icon: 'router', description: 'Router, switch, or IP conflict issues.' },
];

const symptomOptions = [
    'No Internet Access',
    'Slow / Intermittent Connection',
    'Cannot See WiFi Network',
    'Damaged Wall Port / Cable',
    'IP Address Conflict',
    'Cannot Authenticate / Login',
];

const local = reactive<NetworkRequestValues>({
    network_type: '',
    location: '',
    selected_symptoms: [],
    issue_summary: '',
    formatted_details: '',
});
const localErrors = ref<Record<string, string>>({});

watch(
    () => props.initial,
    (initial) => {
        local.network_type = initial.network_type ?? '';
        local.location = initial.location ?? '';
        local.selected_symptoms = [...(initial.selected_symptoms ?? [])];
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
    () => `Category: ${local.network_type} | Location: ${local.location} | Issues: ${local.selected_symptoms.join(', ')}`,
);

function submitForm() {
    localErrors.value = {};
    if (!local.network_type) {
        localErrors.value.network_type = 'Select a network category.';
    }
    if (!local.location.trim()) {
        localErrors.value.location = 'Enter the department, office, and exact room.';
    }
    if (!local.issue_summary.trim()) {
        localErrors.value.issue_summary = 'Describe the network issue.';
    }

    if (Object.keys(localErrors.value).length) {
        return;
    }

    local.location = local.location.trim();
    local.formatted_details = formattedDetails.value;
    emit('submit', {
        ...local,
        selected_symptoms: [...local.selected_symptoms],
    });
}

defineExpose({ submitForm });
</script>

<template>
    <form class="animate-fadeIn space-y-7" @submit.prevent="submitForm">
        <section class="space-y-3">
            <div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Network category <span class="text-rose-500">*</span></h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Choose the network service area that best matches the issue.</p>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <button
                    v-for="network in networkTypes"
                    :key="network.value"
                    type="button"
                    :aria-pressed="local.network_type === network.value"
                    class="group flex min-h-32 items-start gap-4 rounded-2xl border p-4 text-left transition focus:outline-none focus:ring-4 focus:ring-indigo-500/15"
                    :class="
                        local.network_type === network.value
                            ? 'border-indigo-500 bg-indigo-50 text-indigo-800 shadow-sm dark:border-indigo-400 dark:bg-indigo-950/50 dark:text-indigo-200'
                            : 'border-slate-200 bg-white text-slate-700 hover:border-indigo-300 hover:bg-indigo-50/50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-indigo-700'
                    "
                    @click="local.network_type = network.value"
                >
                    <span class="material-symbols-outlined text-3xl text-indigo-600 dark:text-indigo-400" aria-hidden="true">
                        {{ network.icon }}
                    </span>
                    <span>
                        <span class="block text-sm font-semibold">{{ network.value }}</span>
                        <span class="mt-1 block text-xs text-slate-500 dark:text-slate-400">{{ network.description }}</span>
                    </span>
                </button>
            </div>
            <p v-if="getFieldError('network_type')" class="text-xs text-rose-600">{{ getFieldError('network_type') }}</p>
        </section>

        <section>
            <label for="network-location" class="block text-sm font-semibold text-slate-900 dark:text-white">
                Department / Office &amp; exact Room <span class="text-rose-500">*</span>
            </label>
            <input
                id="network-location"
                v-model="local.location"
                :class="inputClass"
                type="text"
                maxlength="255"
                required
                autocomplete="organization-title"
                placeholder="e.g., Library - 2nd Floor Reading Area"
            />
            <p v-if="getFieldError('location')" class="mt-2 text-xs text-rose-600">{{ getFieldError('location') }}</p>
        </section>

        <section>
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                Common network symptoms <span class="font-normal text-slate-500">(Optional)</span>
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
            <label for="network-issue-summary" class="block text-sm font-semibold text-slate-900 dark:text-white">
                Detailed Issue Description <span class="text-rose-500">*</span>
            </label>
            <textarea
                id="network-issue-summary"
                v-model="local.issue_summary"
                :class="inputClass"
                rows="5"
                maxlength="4000"
                required
                placeholder="Please describe the network issue, when it started, and any troubleshooting steps you have tried."
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
