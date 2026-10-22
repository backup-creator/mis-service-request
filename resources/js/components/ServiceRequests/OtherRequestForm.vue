<script setup lang="ts">
import { reactive, ref, watch } from 'vue';

type ErrorValue = string | string[] | undefined;

interface OtherRequestValues {
    subject: string;
    location: string;
    urgency: string;
    issue_summary: string;
    formatted_details: string;
}

const props = withDefaults(
    defineProps<{
        initial?: Partial<OtherRequestValues>;
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
    submit: [payload: OtherRequestValues];
}>();

const urgencyOptions = ['Low (Routine / Inquiry)', 'Normal (Standard Request)', 'High (Impacting my work)', 'Critical (System Down / Widespread)'];

const local = reactive<OtherRequestValues>({
    subject: '',
    location: '',
    urgency: '',
    issue_summary: '',
    formatted_details: '',
});
const localErrors = ref<Record<string, string>>({});

watch(
    () => props.initial,
    (initial) => {
        local.subject = initial.subject ?? '';
        local.location = initial.location ?? '';
        local.urgency = initial.urgency ?? '';
        local.issue_summary = initial.issue_summary ?? '';
        local.formatted_details = initial.formatted_details ?? '';
    },
    { immediate: true, deep: true },
);

const inputClass =
    'mt-2 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-4 focus:ring-indigo-500/10 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-500';

function getFieldError(field: string): string | undefined {
    const error: ErrorValue = props.validationErrors[field] ?? props.validationErrors[`metadata.${field}`];

    return localErrors.value[field] ?? (Array.isArray(error) ? error[0] : error);
}

function submitForm() {
    localErrors.value = {};
    if (!local.subject.trim()) {
        localErrors.value.subject = 'Enter a subject or request title.';
    }
    if (!local.location.trim()) {
        localErrors.value.location = 'Enter your department or location.';
    }
    if (!local.urgency) {
        localErrors.value.urgency = 'Select an urgency or impact level.';
    }
    if (!local.issue_summary.trim()) {
        localErrors.value.issue_summary = 'Provide details about your request.';
    }

    if (Object.keys(localErrors.value).length) {
        return;
    }

    local.subject = local.subject.trim();
    local.location = local.location.trim();
    local.issue_summary = local.issue_summary.trim();
    local.formatted_details = `Subject: ${local.subject} | Urgency: ${local.urgency} | Location: ${local.location}`;
    emit('submit', { ...local });
}

defineExpose({ submitForm });
</script>

<template>
    <form
        class="animate-fadeIn space-y-5 rounded-[24px] border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700/80 dark:bg-slate-950/80"
        @submit.prevent="submitForm"
    >
        <header class="rounded-[20px] border border-slate-200 bg-slate-50 p-4 dark:border-slate-700/70 dark:bg-slate-900/70">
            <h2 class="text-base font-bold text-slate-900 dark:text-white">Other Request</h2>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Tell us what you need and how it affects your work.</p>
        </header>

        <section class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="other-request-subject" class="block text-sm font-semibold text-slate-900 dark:text-white">
                    Subject / Request Title <span class="text-rose-500">*</span>
                </label>
                <input
                    id="other-request-subject"
                    v-model="local.subject"
                    :class="inputClass"
                    type="text"
                    maxlength="255"
                    required
                    placeholder="Brief title of what you need"
                />
                <p v-if="getFieldError('subject')" class="mt-2 text-xs text-rose-600">{{ getFieldError('subject') }}</p>
            </div>
            <div>
                <label for="other-request-location" class="block text-sm font-semibold text-slate-900 dark:text-white">
                    Department / Location <span class="text-rose-500">*</span>
                </label>
                <input
                    id="other-request-location"
                    v-model="local.location"
                    :class="inputClass"
                    type="text"
                    maxlength="255"
                    required
                    placeholder="e.g., MIS Office - Room 102"
                />
                <p v-if="getFieldError('location')" class="mt-2 text-xs text-rose-600">{{ getFieldError('location') }}</p>
            </div>
        </section>

        <section>
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Urgency / Impact Level <span class="text-rose-500">*</span></h3>
            <div class="mt-3 flex flex-wrap gap-2">
                <button
                    v-for="urgency in urgencyOptions"
                    :key="urgency"
                    type="button"
                    :aria-pressed="local.urgency === urgency"
                    class="rounded-full border px-4 py-2.5 text-sm font-semibold transition focus:outline-none focus:ring-4 focus:ring-indigo-500/15"
                    :class="
                        local.urgency === urgency
                            ? 'border-indigo-600 bg-indigo-600 text-white dark:border-indigo-400 dark:bg-indigo-500'
                            : 'border-slate-200 bg-slate-50 text-slate-700 hover:border-indigo-300 hover:text-indigo-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300'
                    "
                    @click="local.urgency = urgency"
                >
                    {{ urgency }}
                </button>
            </div>
            <p v-if="getFieldError('urgency')" class="mt-2 text-xs text-rose-600">{{ getFieldError('urgency') }}</p>
        </section>

        <section>
            <label for="other-request-summary" class="block text-sm font-semibold text-slate-900 dark:text-white">
                Detailed Description <span class="text-rose-500">*</span>
            </label>
            <textarea
                id="other-request-summary"
                v-model="local.issue_summary"
                :class="`${inputClass} min-h-[120px]`"
                rows="5"
                maxlength="4000"
                required
                placeholder="Please provide as much detail as possible about your request..."
            />
            <p v-if="getFieldError('issue_summary')" class="mt-2 text-xs text-rose-600">
                {{ getFieldError('issue_summary') }}
            </p>
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
