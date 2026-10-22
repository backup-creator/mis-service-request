import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

export function useServiceRequestWizard({ onSuccess, priorities, locationId, departmentId }) {
    const currentStep = ref(1);
    const selectedCategory = ref(null);
    const submissionPayload = ref(null);

    const form = useForm({
        title: '',
        service_request_category_id: '',
        service_request_priority_id: /** @type {number | ''} */ (''),
        description: '',
        subtype: '',
        contact_number: '',
        location_id: locationId ?? '',
        department_id: departmentId ?? '',
        return_route: '',
        metadata: {},
        attachments: [],
    });

    const selectedCategoryName = computed(() => selectedCategory.value?.name ?? '');
    const selectedCategoryCode = computed(() => selectedCategory.value?.code ?? '');

    function selectCategory(category) {
        if (selectedCategory.value && selectedCategory.value.id !== category.id) {
            form.title = '';
            form.service_request_priority_id = '';
            form.description = '';
            form.subtype = '';
            form.contact_number = '';
            form.metadata = {};
            form.attachments = [];
            submissionPayload.value = null;
            form.clearErrors();
        }

        selectedCategory.value = category;
        form.service_request_category_id = category.id;
        currentStep.value = 2;
    }

    function backToCategories() {
        currentStep.value = 1;
        submissionPayload.value = null;
        form.clearErrors();
    }

    function stageSubmission(payload) {
        form.clearErrors();
        submissionPayload.value = payload;
        currentStep.value = 3;
    }

    function appendFiles(event) {
        const input = event.target;

        if (input.files) {
            form.attachments = [...form.attachments, ...Array.from(input.files)];
        }

        input.value = '';
    }

    function removeFile(index) {
        form.attachments.splice(index, 1);
    }

    function attachmentError(index) {
        return Object.entries(form.errors).find(([key]) => key === `attachments.${index}`)?.[1];
    }

    /** @returns {Record<string, string[]>} */
    function idRequestValidationErrors() {
        /** @type {Record<string, string[]>} */
        const errors = {};

        for (const [key, message] of Object.entries(form.errors)) {
            if (key.startsWith('metadata.')) {
                errors[key.slice('metadata.'.length)] = [message];
            }
        }

        if (form.errors['attachments.0']) {
            errors.id_photo = [form.errors['attachments.0']];
        }

        if (form.errors['attachments.1']) {
            errors.client_signature = [form.errors['attachments.1']];
        }

        return errors;
    }

    function submitIdRequest(payload) {
        const { id_photo: idPhoto, client_signature: clientSignature, ...metadata } = payload;
        const fullName = [metadata.id_first_name, metadata.id_middle_name, metadata.id_last_name, metadata.id_suffix].filter(Boolean).join(' ');
        const preferredPriority = priorities.find((priority) => ['standard', 'normal'].includes(priority.name.toLowerCase())) ?? priorities[0];

        form.title = `${metadata.id_request_type} ID - ${fullName}`.slice(0, 255);
        form.description = `ID request for ${fullName} (${metadata.id_request_type}).`;
        form.service_request_priority_id = preferredPriority?.id ?? '';
        form.contact_number = metadata.id_emergency_phone ?? '';
        form.metadata = metadata;
        form.attachments = [idPhoto, clientSignature].filter(Boolean);

        stageSubmission(payload);
    }

    function submitAccountAccess(payload) {
        const metadata = payload.metadata;
        const preferredPriority = priorities.find((priority) => ['standard', 'normal'].includes(priority.name.toLowerCase())) ?? priorities[0];
        const isBulk = metadata.is_bulk === true;
        const fullName =
            metadata.full_name || [metadata.first_name, metadata.middle_initial, metadata.last_name, metadata.suffix].filter(Boolean).join(' ');

        form.title = isBulk
            ? `Bulk ${metadata.account_classification} Email Account Request (${metadata.applicants.length} applicants)`
            : `${metadata.email_action}: ${fullName}`.slice(0, 255);
        form.description = isBulk
            ? `Bulk ${metadata.account_classification} email account request for ${metadata.applicants.length} applicants.`
            : `${metadata.access_type} for ${fullName}.${metadata.reason ? ` ${metadata.reason}` : ''}`;
        form.service_request_priority_id = preferredPriority?.id ?? '';
        form.subtype = metadata.email_action;
        form.contact_number = '';
        form.metadata = metadata;

        stageSubmission(payload);
    }

    function submitHardwareSupport(payload) {
        const preferredPriority = priorities.find((priority) => ['standard', 'normal'].includes(priority.name.toLowerCase())) ?? priorities[0];
        const fullName = payload.full_hardware_details;

        form.title = `${payload.device_type} Support Request`.slice(0, 255);
        form.description = fullName;
        form.service_request_priority_id = preferredPriority?.id ?? '';
        form.subtype = payload.device_type;
        form.contact_number = '';
        form.metadata = {
            device_type: payload.device_type,
            location: payload.location,
            property_tag: payload.property_tag,
            selected_symptoms: payload.selected_symptoms,
            issue_summary: payload.issue_summary,
            full_hardware_details: fullName,
        };

        stageSubmission(payload);
    }

    function submitSoftwareSupport(payload) {
        const preferredPriority = priorities.find((priority) => ['standard', 'normal'].includes(priority.name.toLowerCase())) ?? priorities[0];
        const details = [payload.formatted_details, `Property / ICS No.: ${payload.asset_tag}`, `Issue Context: ${payload.issue_summary}`]
            .filter(Boolean)
            .join('\n');

        form.title = `${payload.software_type} - ${payload.action_type}`.slice(0, 255);
        form.description = details;
        form.service_request_priority_id = preferredPriority?.id ?? '';
        form.subtype = payload.action_type;
        form.contact_number = '';
        form.metadata = {
            software_type: payload.software_type,
            action_type: payload.action_type,
            selected_symptoms: payload.selected_symptoms,
            asset_tag: payload.asset_tag,
            issue_summary: payload.issue_summary,
            formatted_details: payload.formatted_details,
        };

        stageSubmission(payload);
    }

    function submitNetworkSupport(payload) {
        const preferredPriority = priorities.find((priority) => ['standard', 'normal'].includes(priority.name.toLowerCase())) ?? priorities[0];
        const details = [payload.formatted_details, `Issue Description: ${payload.issue_summary}`].join('\n');

        form.title = `${payload.network_type} Support Request`.slice(0, 255);
        form.description = details;
        form.service_request_priority_id = preferredPriority?.id ?? '';
        form.subtype = payload.network_type;
        form.contact_number = '';
        form.metadata = {
            network_type: payload.network_type,
            location: payload.location,
            selected_symptoms: payload.selected_symptoms,
            issue_summary: payload.issue_summary,
            formatted_details: payload.formatted_details,
        };

        stageSubmission(payload);
    }

    function submitJobRequest(payload) {
        const preferredPriority = priorities.find((priority) => ['standard', 'normal'].includes(priority.name.toLowerCase())) ?? priorities[0];
        const details = [
            payload.formatted_details,
            `Preferred Schedule: ${payload.preferred_schedule || 'Not specified'}`,
            `Job Summary: ${payload.issue_summary}`,
        ].join('\n');

        form.title = `${payload.job_type} Job Request`.slice(0, 255);
        form.description = details;
        form.service_request_priority_id = preferredPriority?.id ?? '';
        form.subtype = payload.job_type;
        form.contact_number = '';
        form.metadata = {
            job_type: payload.job_type,
            location: payload.location,
            equipment_involved: payload.equipment_involved,
            preferred_schedule: payload.preferred_schedule,
            issue_summary: payload.issue_summary,
            formatted_details: payload.formatted_details,
        };

        stageSubmission(payload);
    }

    function submitTechnicalAssistance(payload) {
        const preferredPriority = priorities.find((priority) => ['standard', 'normal'].includes(priority.name.toLowerCase())) ?? priorities[0];
        const details = [payload.formatted_details, `Support Summary: ${payload.issue_summary}`].join('\n');

        form.title = `${payload.assistance_type} Technical Assistance`.slice(0, 255);
        form.description = details;
        form.service_request_priority_id = preferredPriority?.id ?? '';
        form.subtype = payload.assistance_type;
        form.contact_number = '';
        form.metadata = {
            assistance_type: payload.assistance_type,
            venue: payload.venue,
            schedule: payload.schedule,
            equipment_needed: payload.equipment_needed,
            issue_summary: payload.issue_summary,
            formatted_details: payload.formatted_details,
        };

        stageSubmission(payload);
    }

    function submitOtherRequest(payload) {
        const preferredPriority = priorities.find((priority) => ['standard', 'normal'].includes(priority.name.toLowerCase())) ?? priorities[0];
        const details = `${payload.formatted_details}\n\n${payload.issue_summary}`;

        form.title = payload.subject.slice(0, 255);
        form.description = details;
        form.service_request_priority_id = preferredPriority?.id ?? '';
        form.subtype = 'Other Request';
        form.contact_number = '';
        form.metadata = {
            subject: payload.subject,
            location: payload.location,
            urgency: payload.urgency,
            issue_summary: payload.issue_summary,
            formatted_details: payload.formatted_details,
        };

        stageSubmission(payload);
    }

    function submit() {
        if (currentStep.value !== 3) {
            return;
        }

        const dashboardPath = new URL(route('dashboard'), window.location.origin).pathname;
        form.return_route = window.location.pathname === dashboardPath ? 'dashboard' : 'service-requests.index';

        form.post(route('service-requests.store'), {
            forceFormData: true,
            onSuccess: () => {
                form.reset();
                form.clearErrors();
                selectedCategory.value = null;
                submissionPayload.value = null;
                currentStep.value = 1;
                onSuccess();
            },
            onError: (errors) => {
                console.error('Service request submission failed validation.', errors);
            },
        });
    }

    return {
        currentStep,
        submissionPayload,
        selectedCategoryName,
        selectedCategoryCode,
        form,
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
    };
}
