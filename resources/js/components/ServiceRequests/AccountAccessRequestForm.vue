<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';

type ErrorValue = string | string[] | Record<string, string[] | string> | undefined;
type InitialValues = Record<string, unknown>;

interface ApplicantRow {
    id_number: string;
    first_name: string;
    middle_name: string;
    middle_initial?: string;
    last_name: string;
    suffix: string;
    personal_email: string;
    department_office: string;
}

const props = withDefaults(
    defineProps<{
        initial?: InitialValues;
        loading?: boolean;
        validationErrors?: Record<string, ErrorValue>;
        submitLabel?: string;
        showFooter?: boolean;
    }>(),
    {
        initial: () => ({}),
        loading: false,
        validationErrors: () => ({}),
        submitLabel: 'Submit',
        showFooter: false,
    },
);

const emit = defineEmits<{
    submit: [payload: Record<string, unknown>];
    cancel: [];
}>();

const accountTypes = [
    { value: 'Student Email Account', label: 'Student Profile', description: 'Official student identity (@students.capsu.edu.ph)' },
    { value: 'Faculty / Staff Email Account', label: 'Faculty / Staff', description: 'Personnel and administration identity (@capsu.edu.ph)' },
];

const emailActions = [
    { value: 'New Email Request', label: 'Create New Email' },
    { value: 'Recover Account', label: 'Forgot Password / Unblock / Recovery' },
];

const suffixOptions = ['', 'Jr.', 'Sr.', 'II', 'III', 'IV', 'V'];
const initialApplicants = Array.isArray(props.initial.applicants)
    ? (props.initial.applicants as Partial<ApplicantRow>[])
    : Array.isArray(props.initial.students)
      ? (props.initial.students as Array<Partial<ApplicantRow> & { student_id?: string }>).map((row) => ({
            ...row,
            id_number: row.id_number ?? row.student_id ?? '',
        }))
      : [];
const local = reactive({
    account_type: typeof props.initial.account_type === 'string' ? props.initial.account_type : '',
    email_action: typeof props.initial.email_action === 'string' ? props.initial.email_action : '',
    student_id: typeof props.initial.student_id === 'string' ? props.initial.student_id : '',
    employee_id: typeof props.initial.employee_id === 'string' ? props.initial.employee_id : '',
    department_office: typeof props.initial.department_office === 'string' ? props.initial.department_office : '',
    first_name: typeof props.initial.first_name === 'string' ? props.initial.first_name : '',
    middle_name: typeof props.initial.middle_name === 'string' ? props.initial.middle_name : '',
    last_name: typeof props.initial.last_name === 'string' ? props.initial.last_name : '',
    suffix: typeof props.initial.suffix === 'string' ? props.initial.suffix : '',
    capsu_email: typeof props.initial.capsu_email === 'string' ? props.initial.capsu_email : '',
    personal_email: typeof props.initial.personal_email === 'string' ? props.initial.personal_email : '',
    preferred_username: typeof props.initial.preferred_username === 'string' ? props.initial.preferred_username : '',
    reason: typeof props.initial.reason === 'string' ? props.initial.reason : '',
    bulk_mode: props.initial.is_bulk === true,
    bulk_input_mode: 'manual' as 'manual' | 'csv',
    applicants: initialApplicants.map((applicant) => makeApplicant(applicant)),
});

const allowCustomUsername = ref(false);
const csvError = ref('');
const batchErrors = ref<string[]>([]);
const clientErrors = ref<Record<string, string>>({});

const isStudentMode = computed(() => local.account_type === 'Student Email Account');
const isRecoveryOrResetMode = computed(() => local.email_action === 'Recover Account');
const activeDomainExtension = computed(() => (isStudentMode.value ? '@students.capsu.edu.ph' : '@capsu.edu.ph'));
const sanitizedCapsuEmailPrefix = computed(() => local.capsu_email.trim().toLowerCase().split('@')[0] ?? '');
const generatedEmailSuggestion = computed(() => makeUsername(local.first_name, local.middle_name, local.last_name, local.suffix));
const identifierLabel = computed(() => (isStudentMode.value ? 'Student ID' : 'Employee ID'));
const csvHeaders = computed(() =>
    isStudentMode.value
        ? ['student_id', 'first_name', 'middle_name', 'last_name', 'suffix', 'personal_email']
        : ['employee_id', 'first_name', 'middle_name', 'last_name', 'suffix', 'personal_email', 'department_office'],
);
watch(
    [generatedEmailSuggestion, () => local.email_action],
    ([suggestion, action]) => {
        if (action === 'New Email Request' && !allowCustomUsername.value) {
            local.preferred_username = suggestion;
        }
    },
    { immediate: true },
);

watch(
    () => props.initial,
    (values) => {
        if (!values || Object.keys(values).length === 0) {
            return;
        }

        for (const key of [
            'account_type',
            'email_action',
            'student_id',
            'employee_id',
            'department_office',
            'first_name',
            'middle_name',
            'last_name',
            'suffix',
            'capsu_email',
            'personal_email',
            'preferred_username',
            'reason',
        ] as const) {
            const value = values[key];
            local[key] = typeof value === 'string' ? value : '';
        }

        if (Array.isArray(values.applicants)) {
            local.applicants = (values.applicants as Partial<ApplicantRow>[]).map((applicant) => makeApplicant(applicant));
        } else if (Array.isArray(values.students)) {
            local.applicants = (values.students as Array<Partial<ApplicantRow> & { student_id?: string }>).map((row) =>
                makeApplicant({ ...row, id_number: row.id_number ?? row.student_id ?? '' }),
            );
        }
        local.bulk_mode = values.is_bulk === true;
    },
    { deep: true },
);

function makeApplicant(values: Partial<ApplicantRow> = {}): ApplicantRow {
    return {
        id_number: values.id_number ?? '',
        first_name: values.first_name ?? '',
        middle_name: values.middle_name ?? values.middle_initial ?? '',
        last_name: values.last_name ?? '',
        suffix: values.suffix ?? '',
        personal_email: values.personal_email ?? '',
        department_office: values.department_office ?? '',
    };
}

function makeUsername(firstName: string, middleName: string, lastName: string, suffix: string) {
    const first = firstName.trim().toLowerCase();
    const middle = middleName.trim().charAt(0).toLowerCase();
    const last = lastName.trim().toLowerCase().replace(/\s+/g, '');
    const suffixValue = suffix.trim().toLowerCase().replace(/[.\s]/g, '');

    if (!first || !last) {
        return '';
    }

    return `${first[0]}${middle[0] ?? ''}${last}${suffixValue}`;
}

function getFieldError(field: string): string | null {
    if (clientErrors.value[field]) {
        return clientErrors.value[field];
    }

    const error =
        props.validationErrors[field] ??
        props.validationErrors[`metadata.${field}`] ??
        props.validationErrors[`metadata.${field.replace(/^metadata\./, '')}`];

    if (Array.isArray(error)) {
        return error[0] ?? null;
    }

    return typeof error === 'string' ? error : null;
}

function rowError(index: number, field: keyof ApplicantRow): string | null {
    return (
        getFieldError(`metadata.applicants.${index}.${field}`) ??
        getFieldError(`applicants.${index}.${field}`) ??
        getFieldError(`metadata.students.${index}.${field}`) ??
        getFieldError(`students.${index}.${field}`)
    );
}

function addApplicantRow() {
    local.applicants.push(makeApplicant());
}

function removeApplicantRow(index: number) {
    local.applicants.splice(index, 1);
}

function downloadCsvTemplate() {
    const csv = `${csvHeaders.value.join(',')}\r\n`;
    const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
    const link = document.createElement('a');
    link.href = url;
    link.download = `capsu-${isStudentMode.value ? 'student' : 'faculty-staff'}-account-template.csv`;
    document.body.append(link);
    link.click();
    link.remove();
    window.setTimeout(() => URL.revokeObjectURL(url), 1000);
}

function parseCsv(text: string): string[][] {
    const rows: string[][] = [];
    let row: string[] = [];
    let cell = '';
    let quoted = false;

    for (let index = 0; index < text.length; index += 1) {
        const character = text[index];
        const next = text[index + 1];

        if (character === '"' && quoted && next === '"') {
            cell += '"';
            index += 1;
        } else if (character === '"') {
            quoted = !quoted;
        } else if (character === ',' && !quoted) {
            row.push(cell.trim());
            cell = '';
        } else if ((character === '\n' || character === '\r') && !quoted) {
            if (character === '\r' && next === '\n') {
                index += 1;
            }
            row.push(cell.trim());
            if (row.some(Boolean)) {
                rows.push(row);
            }
            row = [];
            cell = '';
        } else {
            cell += character;
        }
    }

    if (quoted) {
        throw new Error('The CSV contains an unmatched quote.');
    }
    if (cell || row.length) {
        row.push(cell.trim());
        if (row.some(Boolean)) {
            rows.push(row);
        }
    }

    return rows;
}

async function importCsv(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    csvError.value = '';

    if (!file) {
        return;
    }

    if (!file.name.toLowerCase().endsWith('.csv')) {
        csvError.value = 'Please upload a CSV file.';
        input.value = '';
        return;
    }

    try {
        const rows = parseCsv(await file.text());
        const expectedHeaders = csvHeaders.value;
        const headers = rows.shift()?.map((header) => header.toLowerCase().replace(/^\uFEFF/, '')) ?? [];

        if (expectedHeaders.some((header) => !headers.includes(header))) {
            throw new Error(`CSV headers must include: ${expectedHeaders.join(', ')}.`);
        }

        const positions = expectedHeaders.map((header) => headers.indexOf(header));
        const imported = rows.map((values) =>
            makeApplicant({
                id_number: values[positions[0]] ?? '',
                first_name: values[positions[1]] ?? '',
                middle_name: values[positions[2]] ?? '',
                last_name: values[positions[3]] ?? '',
                suffix: values[positions[4]] ?? '',
                personal_email: values[positions[5]] ?? '',
                department_office: isStudentMode.value ? '' : (values[positions[6]] ?? ''),
            }),
        );

        if (imported.length === 0) {
            throw new Error('The CSV does not contain any applicant rows.');
        }

        local.applicants = imported;
        local.bulk_input_mode = 'csv';
        local.bulk_mode = true;
    } catch (error) {
        csvError.value = error instanceof Error ? error.message : 'Unable to read the CSV file.';
    } finally {
        input.value = '';
    }
}

function validateApplicants(): boolean {
    batchErrors.value = [];
    local.applicants.forEach((applicant, index) => {
        if (!applicant.id_number.trim() || !applicant.first_name.trim() || !applicant.last_name.trim() || !applicant.personal_email.trim()) {
            batchErrors.value.push(`Row ${index + 1}: ${identifierLabel.value}, first name, last name, and personal email are required.`);
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(applicant.personal_email.trim())) {
            batchErrors.value.push(`Row ${index + 1}: Enter a valid personal email address.`);
        }
    });

    if (local.applicants.length === 0) {
        batchErrors.value.push('Add at least one applicant to submit a bulk request.');
    } else if (local.applicants.length > 500) {
        batchErrors.value.push('A batch can contain no more than 500 applicants.');
    }

    return batchErrors.value.length === 0;
}

function selectAccountType(value: string) {
    local.account_type = value;
}

function selectEmailAction(value: string) {
    local.email_action = value;
    if (value !== 'New Email Request') {
        local.bulk_mode = false;
    }
}

function toggleCustomUsername() {
    allowCustomUsername.value = !allowCustomUsername.value;

    if (!allowCustomUsername.value || !local.preferred_username) {
        local.preferred_username = generatedEmailSuggestion.value;
    }
}

function handleSubmit() {
    clientErrors.value = {};

    if (!local.account_type || !local.email_action) {
        if (!local.account_type) {
            clientErrors.value.account_type = 'Select an account profile.';
        }
        if (!local.email_action) {
            clientErrors.value.email_action = 'Select the type of account assistance needed.';
        }
        return;
    }

    if (local.bulk_mode) {
        if (local.email_action !== 'New Email Request') {
            batchErrors.value = ['Bulk mode is available only for new email account requests.'];
            return;
        }
        if (!validateApplicants()) {
            return;
        }

        const applicants = local.applicants.map((applicant) => ({
            ...applicant,
            middle_initial: applicant.middle_name.trim().charAt(0).toUpperCase(),
            department_office: isStudentMode.value ? '' : applicant.department_office.trim(),
            generated_email: `${makeUsername(applicant.first_name, applicant.middle_name, applicant.last_name, applicant.suffix)}${activeDomainExtension.value}`,
            status: 'pending',
        }));
        const metadata = {
            is_bulk: true,
            account_type: local.account_type,
            account_classification: isStudentMode.value ? 'Student' : 'Faculty/Staff',
            email_action: local.email_action,
            access_type: `${local.account_type} (${local.email_action})`,
            domain_used: activeDomainExtension.value,
            applicants,
        };

        emit('submit', {
            is_bulk: true,
            account_type: local.account_type,
            account_classification: metadata.account_classification,
            email_action: local.email_action,
            applicants,
            metadata,
        });

        return;
    }

    if (isStudentMode.value && !local.student_id.trim()) {
        clientErrors.value.student_id = 'Enter the student ID.';
    } else if (!isStudentMode.value && !local.employee_id.trim()) {
        clientErrors.value.employee_id = 'Enter the employee ID.';
    }
    if (!local.first_name.trim()) {
        clientErrors.value.first_name = 'Enter the first name.';
    }
    if (!local.last_name.trim()) {
        clientErrors.value.last_name = 'Enter the last name.';
    }
    if (!local.personal_email.trim()) {
        clientErrors.value.personal_email = 'Enter a personal email address.';
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(local.personal_email.trim())) {
        clientErrors.value.personal_email = 'Enter a valid personal email address.';
    }
    if (isRecoveryOrResetMode.value && !local.capsu_email.trim()) {
        clientErrors.value.capsu_email = 'Enter the CAPSU email username to recover.';
    }
    if (!isRecoveryOrResetMode.value && !(local.preferred_username || generatedEmailSuggestion.value).trim()) {
        clientErrors.value.preferred_username = 'Enter an institutional email username.';
    }

    if (Object.keys(clientErrors.value).length > 0) {
        return;
    }

    const cleanUsername =
        local.email_action === 'New Email Request'
            ? (local.preferred_username || generatedEmailSuggestion.value).trim().toLowerCase().split('@')[0]
            : sanitizedCapsuEmailPrefix.value;
    const fullEmail = cleanUsername ? `${cleanUsername}${activeDomainExtension.value}` : '';
    const middleInitial = local.middle_name.trim().charAt(0).toUpperCase();
    const middleString = middleInitial ? ` ${middleInitial}.` : '';
    const fullName = `${local.first_name.trim()}${middleString} ${local.last_name.trim()}${local.suffix ? ` ${local.suffix}` : ''}`.trim();
    const metadata = {
        is_bulk: false,
        account_type: local.account_type,
        account_classification: isStudentMode.value ? 'Student' : 'Faculty/Staff',
        email_action: local.email_action,
        student_id: local.student_id,
        employee_id: local.employee_id,
        department_office: local.department_office.trim(),
        first_name: local.first_name.trim(),
        middle_name: local.middle_name.trim(),
        middle_initial: middleInitial,
        last_name: local.last_name.trim(),
        suffix: local.suffix,
        capsu_email: local.capsu_email.trim(),
        personal_email: local.personal_email.trim(),
        reason: local.reason.trim(),
        full_name: fullName,
        access_type: `${local.account_type} (${local.email_action})`,
        capsu_email_prefix: cleanUsername,
        full_institutional_email: fullEmail,
        preferred_username: cleanUsername,
        domain_used: activeDomainExtension.value,
    };

    emit('submit', { ...metadata, is_bulk: false, metadata });
}

function submitForm() {
    handleSubmit();
}

defineExpose({ submitForm });
</script>

<template>
    <form class="space-y-6" @submit.prevent="handleSubmit">
        <section class="space-y-3">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">I am applying as a <span class="text-rose-500">*</span></h3>
            <div class="grid gap-3 sm:grid-cols-2">
                <button
                    v-for="type in accountTypes"
                    :key="type.value"
                    type="button"
                    :disabled="loading"
                    class="rounded-xl border p-4 text-left transition"
                    :class="
                        local.account_type === type.value
                            ? 'border-indigo-600 bg-indigo-50 ring-1 ring-indigo-600'
                            : 'border-slate-200 hover:border-indigo-300'
                    "
                    @click="selectAccountType(type.value)"
                >
                    <span class="text-sm font-semibold text-slate-900">{{ type.label }}</span>
                    <span class="mt-1 block text-xs text-slate-500">{{ type.description }}</span>
                </button>
            </div>
            <p v-if="getFieldError('account_type')" class="text-xs font-medium text-rose-600">{{ getFieldError('account_type') }}</p>
        </section>

        <section v-if="local.account_type" class="space-y-3">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">
                What do you need assistance with? <span class="text-rose-500">*</span>
            </h3>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="action in emailActions"
                    :key="action.value"
                    type="button"
                    :disabled="loading"
                    class="rounded-lg border px-4 py-2.5 text-sm font-semibold transition"
                    :class="
                        local.email_action === action.value
                            ? 'border-indigo-600 bg-indigo-600 text-white'
                            : 'border-slate-300 text-slate-700 hover:bg-slate-50'
                    "
                    @click="selectEmailAction(action.value)"
                >
                    {{ action.label }}
                </button>
            </div>
            <p v-if="getFieldError('email_action')" class="text-xs font-medium text-rose-600">{{ getFieldError('email_action') }}</p>
        </section>

        <template v-if="local.email_action">
            <section v-if="local.email_action === 'New Email Request'" class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="rounded-lg px-4 py-2 text-sm font-semibold"
                        :class="!local.bulk_mode ? 'bg-indigo-600 text-white' : 'bg-white text-slate-700'"
                        @click="local.bulk_mode = false"
                    >
                        Single Request
                    </button>
                    <button
                        type="button"
                        class="rounded-lg px-4 py-2 text-sm font-semibold"
                        :class="local.bulk_mode ? 'bg-indigo-600 text-white' : 'bg-white text-slate-700'"
                        @click="local.bulk_mode = true"
                    >
                        Bulk / Batch Request
                    </button>
                </div>
            </section>

            <section v-if="local.bulk_mode" class="space-y-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="font-semibold text-slate-900">{{ isStudentMode ? 'Student batch' : 'Faculty / staff batch' }}</h3>
                        <p class="mt-1 text-sm text-slate-500">Provide applicant records manually or import the matching CSV template.</p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-50"
                        @click="downloadCsvTemplate"
                    >
                        Download CSV Template
                    </button>
                </div>
                <div class="grid gap-4 sm:grid-cols-[1fr_auto] sm:items-end">
                    <div>
                        <label for="account-batch-csv" class="block text-sm font-semibold text-slate-800">Import CSV</label>
                        <input
                            id="account-batch-csv"
                            type="file"
                            accept=".csv,text/csv"
                            class="mt-2 block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:font-semibold file:text-indigo-700"
                            @change="importCsv"
                        />
                        <p v-if="csvError" class="mt-1 text-sm text-rose-600">{{ csvError }}</p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        @click="
                            local.bulk_input_mode = 'manual';
                            addApplicantRow();
                        "
                    >
                        + Add Person Row
                    </button>
                </div>
                <div v-if="local.applicants.length" class="overflow-x-auto rounded-xl border border-slate-200">
                    <table class="min-w-[1000px] divide-y divide-slate-200 text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-3 py-3">{{ identifierLabel }} *</th>
                                <th class="px-3 py-3">First Name *</th>
                                <th class="px-3 py-3">Middle Name</th>
                                <th class="px-3 py-3">Last Name *</th>
                                <th class="px-3 py-3">Suffix</th>
                                <th class="px-3 py-3">Personal Email *</th>
                                <th v-if="!isStudentMode" class="px-3 py-3">Department / Office</th>
                                <th class="px-3 py-3">Suggested Email</th>
                                <th class="px-3 py-3"><span class="sr-only">Remove</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="(applicant, index) in local.applicants" :key="index">
                                <td class="px-2 py-2">
                                    <input
                                        v-model="applicant.id_number"
                                        required
                                        class="w-28 rounded-md border-slate-300 text-sm"
                                        :aria-label="`${identifierLabel} for applicant ${index + 1}`"
                                    />
                                    <p v-if="rowError(index, 'id_number')" class="mt-1 text-xs text-rose-600">{{ rowError(index, 'id_number') }}</p>
                                </td>
                                <td class="px-2 py-2">
                                    <input
                                        v-model="applicant.first_name"
                                        required
                                        class="w-28 rounded-md border-slate-300 text-sm"
                                        :aria-label="`Applicant ${index + 1} first name`"
                                    />
                                    <p v-if="rowError(index, 'first_name')" class="mt-1 text-xs text-rose-600">{{ rowError(index, 'first_name') }}</p>
                                </td>
                                <td class="px-2 py-2">
                                    <input
                                        v-model="applicant.middle_name"
                                        class="w-20 rounded-md border-slate-300 text-sm"
                                        :aria-label="`Applicant ${index + 1} middle name`"
                                    />
                                </td>
                                <td class="px-2 py-2">
                                    <input
                                        v-model="applicant.last_name"
                                        required
                                        class="w-28 rounded-md border-slate-300 text-sm"
                                        :aria-label="`Applicant ${index + 1} last name`"
                                    />
                                    <p v-if="rowError(index, 'last_name')" class="mt-1 text-xs text-rose-600">{{ rowError(index, 'last_name') }}</p>
                                </td>
                                <td class="px-2 py-2">
                                    <select
                                        v-model="applicant.suffix"
                                        class="w-20 rounded-md border-slate-300 text-sm"
                                        :aria-label="`Applicant ${index + 1} suffix`"
                                    >
                                        <option v-for="suffix in suffixOptions" :key="suffix" :value="suffix">{{ suffix || '—' }}</option>
                                    </select>
                                </td>
                                <td class="px-2 py-2">
                                    <input
                                        v-model="applicant.personal_email"
                                        required
                                        type="email"
                                        class="w-44 rounded-md border-slate-300 text-sm"
                                        :aria-label="`Applicant ${index + 1} personal email`"
                                    />
                                    <p v-if="rowError(index, 'personal_email')" class="mt-1 text-xs text-rose-600">
                                        {{ rowError(index, 'personal_email') }}
                                    </p>
                                </td>
                                <td v-if="!isStudentMode" class="px-2 py-2">
                                    <input
                                        v-model="applicant.department_office"
                                        class="w-40 rounded-md border-slate-300 text-sm"
                                        :aria-label="`Applicant ${index + 1} department or office`"
                                    />
                                    <p v-if="rowError(index, 'department_office')" class="mt-1 text-xs text-rose-600">
                                        {{ rowError(index, 'department_office') }}
                                    </p>
                                </td>
                                <td class="px-2 py-2 text-xs text-slate-600">
                                    {{ makeUsername(applicant.first_name, applicant.middle_name, applicant.last_name, applicant.suffix)
                                    }}{{ activeDomainExtension }}
                                </td>
                                <td class="px-2 py-2">
                                    <button
                                        type="button"
                                        class="text-sm font-semibold text-rose-600 hover:text-rose-700"
                                        @click="removeApplicantRow(index)"
                                    >
                                        Remove
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="rounded-lg border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500">
                    Import a CSV or add a person row to begin.
                </p>
                <ul v-if="batchErrors.length" class="space-y-1 rounded-lg bg-rose-50 p-3 text-sm text-rose-700">
                    <li v-for="error in batchErrors" :key="error">{{ error }}</li>
                </ul>
                <p v-if="getFieldError('students')" class="text-sm text-rose-600">{{ getFieldError('students') }}</p>
            </section>

            <section v-else class="grid gap-5 sm:grid-cols-2">
                <div v-if="isStudentMode">
                    <label for="account-student-id" class="block text-sm font-semibold text-slate-800"
                        >Student ID <span class="text-rose-500">*</span></label
                    >
                    <input
                        id="account-student-id"
                        v-model="local.student_id"
                        required
                        :disabled="loading"
                        class="mt-2 w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <p v-if="getFieldError('student_id')" class="mt-1 text-xs text-rose-600">{{ getFieldError('student_id') }}</p>
                </div>
                <div v-else>
                    <label for="account-employee-id" class="block text-sm font-semibold text-slate-800"
                        >Employee ID <span class="text-rose-500">*</span></label
                    >
                    <input
                        id="account-employee-id"
                        v-model="local.employee_id"
                        required
                        :disabled="loading"
                        class="mt-2 w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <p v-if="getFieldError('employee_id')" class="mt-1 text-xs text-rose-600">{{ getFieldError('employee_id') }}</p>
                </div>
                <div>
                    <label for="account-first-name" class="block text-sm font-semibold text-slate-800"
                        >First Name <span class="text-rose-500">*</span></label
                    >
                    <input
                        id="account-first-name"
                        v-model="local.first_name"
                        required
                        :disabled="loading"
                        autocomplete="given-name"
                        class="mt-2 w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <p v-if="getFieldError('first_name')" class="mt-1 text-xs text-rose-600">{{ getFieldError('first_name') }}</p>
                </div>
                <div>
                    <label for="account-middle-name" class="block text-sm font-semibold text-slate-800">Middle Name</label>
                    <input
                        id="account-middle-name"
                        v-model="local.middle_name"
                        :disabled="loading"
                        placeholder="e.g., Arlante"
                        class="mt-2 w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <p v-if="getFieldError('middle_name')" class="mt-1 text-xs text-rose-600">{{ getFieldError('middle_name') }}</p>
                </div>
                <div>
                    <label for="account-last-name" class="block text-sm font-semibold text-slate-800"
                        >Last Name <span class="text-rose-500">*</span></label
                    >
                    <input
                        id="account-last-name"
                        v-model="local.last_name"
                        required
                        :disabled="loading"
                        autocomplete="family-name"
                        class="mt-2 w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <p v-if="getFieldError('last_name')" class="mt-1 text-xs text-rose-600">{{ getFieldError('last_name') }}</p>
                </div>
                <div>
                    <label for="account-suffix" class="block text-sm font-semibold text-slate-800">Suffix</label>
                    <select
                        id="account-suffix"
                        v-model="local.suffix"
                        :disabled="loading"
                        class="mt-2 w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option v-for="suffix in suffixOptions" :key="suffix" :value="suffix">{{ suffix || 'None' }}</option>
                    </select>
                    <p v-if="getFieldError('suffix')" class="mt-1 text-xs text-rose-600">{{ getFieldError('suffix') }}</p>
                </div>
                <div>
                    <label for="account-personal-email" class="block text-sm font-semibold text-slate-800"
                        >Personal Email <span class="text-rose-500">*</span></label
                    >
                    <input
                        id="account-personal-email"
                        v-model="local.personal_email"
                        type="email"
                        required
                        :disabled="loading"
                        autocomplete="email"
                        class="mt-2 w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <p v-if="getFieldError('personal_email')" class="mt-1 text-xs text-rose-600">{{ getFieldError('personal_email') }}</p>
                </div>
                <div v-if="!isStudentMode">
                    <label for="account-department-office" class="block text-sm font-semibold text-slate-800">Department / Office</label>
                    <input
                        id="account-department-office"
                        v-model="local.department_office"
                        :disabled="loading"
                        class="mt-2 w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <p v-if="getFieldError('department_office')" class="mt-1 text-xs text-rose-600">{{ getFieldError('department_office') }}</p>
                </div>

                <div v-if="isRecoveryOrResetMode" class="sm:col-span-2">
                    <label for="account-capsu-email" class="block text-sm font-semibold text-slate-800"
                        >CAPSU Email Address <span class="text-rose-500">*</span></label
                    >
                    <input
                        id="account-capsu-email"
                        v-model="local.capsu_email"
                        type="text"
                        required
                        :disabled="loading"
                        placeholder="username@domain or username"
                        class="mt-2 w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <p class="mt-1 text-xs text-slate-500">
                        Normalized account: {{ sanitizedCapsuEmailPrefix || 'username' }}{{ activeDomainExtension }}
                    </p>
                    <p v-if="getFieldError('capsu_email')" class="mt-1 text-xs text-rose-600">{{ getFieldError('capsu_email') }}</p>
                </div>

                <div v-else class="sm:col-span-2">
                    <div class="rounded-xl border border-indigo-100 bg-indigo-50/70 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-indigo-700">Suggested institutional email</p>
                        <div class="mt-2 flex flex-col gap-3 sm:flex-row sm:items-center">
                            <input
                                v-if="allowCustomUsername"
                                v-model="local.preferred_username"
                                type="text"
                                required
                                :disabled="loading"
                                aria-label="Preferred email username"
                                class="w-full rounded-lg border-indigo-200 bg-white text-sm focus:border-indigo-500 focus:ring-indigo-500 sm:max-w-xs"
                            />
                            <p v-else class="break-all text-lg font-bold text-slate-900">
                                {{ local.preferred_username || generatedEmailSuggestion || 'username'
                                }}<span class="text-indigo-700">{{ activeDomainExtension }}</span>
                            </p>
                            <button
                                type="button"
                                class="text-left text-sm font-semibold text-indigo-700 underline-offset-2 hover:underline"
                                @click="toggleCustomUsername"
                            >
                                {{ allowCustomUsername ? 'Use suggested username' : 'Customize Username' }}
                            </button>
                        </div>
                        <p v-if="getFieldError('preferred_username')" class="mt-1 text-xs text-rose-600">{{ getFieldError('preferred_username') }}</p>
                    </div>
                </div>

                <div class="sm:col-span-2">
                    <label for="account-reason" class="block text-sm font-semibold text-slate-800">Additional details</label>
                    <textarea
                        id="account-reason"
                        v-model="local.reason"
                        rows="3"
                        :disabled="loading"
                        class="mt-2 w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        placeholder="Share any details that will help us process your request."
                    />
                    <p v-if="getFieldError('reason')" class="mt-1 text-xs text-rose-600">{{ getFieldError('reason') }}</p>
                </div>
            </section>

            <button
                v-if="showFooter"
                type="submit"
                :disabled="loading"
                class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-60"
            >
                {{ loading ? 'Submitting…' : submitLabel }}
            </button>
        </template>
    </form>
</template>
