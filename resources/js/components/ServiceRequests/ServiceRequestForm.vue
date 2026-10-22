<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { ref } from 'vue';

interface RequestFormData {
    title: string;
    service_request_category_id: number | '';
    service_request_priority_id: number | '';
    description: string;
    subtype: string;
    contact_number: string;
    attachments: File[];
}

interface LookupOption {
    id: number;
    name: string;
}

defineProps<{
    title: string;
    serviceRequestPriorityId: number | '';
    description: string;
    subtype: string;
    attachments: File[];
    errors: Partial<Record<keyof RequestFormData | `attachments.${number}`, string>>;
    priorities: LookupOption[];
    appendFiles: (event: Event) => void;
    removeFile: (index: number) => void;
    attachmentError: (index: number) => string | undefined;
}>();

const emit = defineEmits<{
    submit: [];
    'update:title': [value: string];
    'update:serviceRequestPriorityId': [value: number | ''];
    'update:description': [value: string];
    'update:subtype': [value: string];
}>();

const requestForm = ref<HTMLFormElement | null>(null);

function submitForm() {
    requestForm.value?.requestSubmit();
}

defineExpose({ submitForm });
</script>

<template>
    <form ref="requestForm" class="space-y-5" @submit.prevent="emit('submit')">
        <div>
            <label for="request-title" class="block text-sm font-semibold text-slate-900">Title *</label>
            <input
                id="request-title"
                :value="title"
                @input="emit('update:title', ($event.target as HTMLInputElement).value)"
                type="text"
                required
                maxlength="255"
                placeholder="Briefly describe what you need"
                class="mt-2 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            />
            <InputError class="mt-2" :message="errors.title" />
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="request-priority" class="block text-sm font-semibold text-slate-900">Priority *</label>
                <select
                    id="request-priority"
                    :value="serviceRequestPriorityId"
                    @change="emit('update:serviceRequestPriorityId', Number(($event.target as HTMLSelectElement).value))"
                    required
                    class="mt-2 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option :value="0" disabled>Select a priority</option>
                    <option v-for="priority in priorities" :key="priority.id" :value="priority.id">
                        {{ priority.name }}
                    </option>
                </select>
                <InputError class="mt-2" :message="errors.service_request_priority_id" />
            </div>
            <div>
                <label for="request-subtype" class="block text-sm font-semibold text-slate-900">Request type</label>
                <input
                    id="request-subtype"
                    :value="subtype"
                    @input="emit('update:subtype', ($event.target as HTMLInputElement).value)"
                    type="text"
                    maxlength="255"
                    placeholder="Optional"
                    class="mt-2 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
                <InputError class="mt-2" :message="errors.subtype" />
            </div>
        </div>

        <div>
            <label for="request-description" class="block text-sm font-semibold text-slate-900">Description *</label>
            <textarea
                id="request-description"
                :value="description"
                @input="emit('update:description', ($event.target as HTMLTextAreaElement).value)"
                required
                rows="5"
                placeholder="Share the details that will help us understand and resolve your request."
                class="mt-2 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            />
            <InputError class="mt-2" :message="errors.description" />
        </div>

        <div>
            <label for="request-attachments" class="block text-sm font-semibold text-slate-900">Attachments</label>
            <p class="mt-1 text-sm text-slate-500">JPG, PNG, PDF, DOC, or DOCX; up to 5 MB per file.</p>
            <input
                id="request-attachments"
                type="file"
                multiple
                accept=".jpg,.jpeg,.png,.pdf,.doc,.docx"
                class="mt-2 block w-full text-sm text-slate-600 file:mr-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100"
                @change="appendFiles"
            />
            <InputError class="mt-2" :message="errors.attachments" />
            <ul v-if="attachments.length" class="mt-3 space-y-2">
                <li
                    v-for="(file, index) in attachments"
                    :key="`${file.name}-${index}`"
                    class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-700"
                >
                    <span class="truncate">{{ file.name }}</span>
                    <button type="button" class="ml-4 shrink-0 font-medium text-red-600 hover:text-red-500" @click="removeFile(index)">Remove</button>
                </li>
            </ul>
            <InputError v-for="(_, index) in attachments" :key="`file-error-${index}`" class="mt-1" :message="attachmentError(index)" />
        </div>
    </form>
</template>
