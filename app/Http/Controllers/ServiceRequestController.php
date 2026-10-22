<?php

namespace App\Http\Controllers;

use App\Models\ServiceRequest;
use App\Models\ServiceRequestCategory;
use App\Models\ServiceRequestPriority;
use App\Models\ServiceRequestStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

class ServiceRequestController extends Controller
{
    public function index(): Response
    {
        $serviceRequests = ServiceRequest::query()
            ->with(['category', 'priority', 'status'])
            ->where('requester_id', Auth::id())
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $categories = ServiceRequestCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'code', 'description']);
        $priorities = ServiceRequestPriority::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name']);

        return Inertia::render('ServiceRequests/Index', [
            'serviceRequests' => $serviceRequests,
            'categories' => $categories,
            'priorities' => $priorities,
            'success' => session('success'),
        ]);
    }

    public function show(ServiceRequest $serviceRequest): Response
    {
        abort_unless($serviceRequest->requester_id === Auth::id(), 404);

        $serviceRequest->load(['category', 'priority', 'status', 'assignedStaff', 'attachments']);
        $metadata = $serviceRequest->metadata ?? [];
        $metadata['id_photo_url'] = filled($metadata['id_photo_path'] ?? null)
            ? Storage::disk('public')->url($metadata['id_photo_path'])
            : null;
        $metadata['client_signature_url'] = filled($metadata['client_signature_path'] ?? null)
            ? Storage::disk('public')->url($metadata['client_signature_path'])
            : null;

        return Inertia::render('ServiceRequests/Show', [
            'serviceRequest' => [
                'request_reference' => $serviceRequest->request_reference,
                'title' => $serviceRequest->title,
                'description' => $serviceRequest->description,
                'submitted_at' => $serviceRequest->submitted_at ?? $serviceRequest->created_at,
                'category' => [
                    'name' => $serviceRequest->category?->name,
                    'code' => $serviceRequest->category?->code,
                ],
                'priority' => $serviceRequest->priority?->name,
                'status' => $serviceRequest->status?->name ?? 'Submitted',
                'status_slug' => $serviceRequest->status?->slug ?? 'submitted',
                'assigned_staff' => $serviceRequest->assignedStaff?->name,
                'resolution_summary' => $serviceRequest->resolution_summary,
                'contact_number' => $metadata['contact_number'] ?? $metadata['id_emergency_phone'] ?? null,
                'subtype' => $metadata['subtype'] ?? $metadata['id_request_type'] ?? null,
                'metadata' => $metadata,
                'attachments' => $serviceRequest->attachments
                    ->map(fn ($attachment): array => [
                        'id' => $attachment->id,
                        'file_name' => $attachment->file_name,
                        'url' => Storage::disk('public')->url($attachment->file_path),
                        'mime_type' => $attachment->mime_type,
                        'size_bytes' => $attachment->size_bytes,
                    ])
                    ->all(),
            ],
        ]);
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('dashboard', ['createRequest' => 1]);
    }

    public function store(Request $request): RedirectResponse
    {
        $categoryCode = ServiceRequestCategory::query()
            ->whereKey($request->input('service_request_category_id'))
            ->value('code');
        $categoryCode = strtoupper((string) $categoryCode);
        $isIdRequest = $categoryCode === 'ID_REQUEST';
        $isAccessRequest = $categoryCode === 'ACCESS';
        $isHardwareRequest = $categoryCode === 'HARDWARE';
        $isSoftwareRequest = $categoryCode === 'SOFTWARE';
        $isNetworkRequest = $categoryCode === 'NETWORK';
        $isJobRequest = $categoryCode === 'JOB_REQUEST';
        $isTechnicalAssistanceRequest = $categoryCode === 'TECH_ASSIST';
        $isOtherRequest = $categoryCode === 'OTHERS';
        $isBulkAccessRequest = $isAccessRequest && filter_var(
            $request->input('metadata.is_bulk', false),
            FILTER_VALIDATE_BOOLEAN,
        );
        $isLegacyBulkStudentRequest = $isBulkAccessRequest
            && $request->has('metadata.students')
            && ! $request->has('metadata.applicants');
        $requiredIdText = $isIdRequest ? ['required', 'string', 'max:255'] : ['nullable', 'string', 'max:255'];
        $requiredAccessText = $isAccessRequest && ! $isBulkAccessRequest
            ? ['required', 'string', 'max:255']
            : ['nullable', 'string', 'max:255'];
        $requiredAccessEmail = $isAccessRequest && ! $isBulkAccessRequest;
        $accountMetadataKeys = 'account_type,account_classification,email_action,student_id,employee_id,department_office,first_name,middle_name,middle_initial,last_name,suffix,capsu_email,personal_email,preferred_username,reason,full_name,access_type,capsu_email_prefix,full_institutional_email,domain_used,is_bulk,students,applicants';
        $hardwareMetadataKeys = 'device_type,location,property_tag,selected_symptoms,issue_summary,full_hardware_details';
        $softwareMetadataKeys = 'software_type,action_type,selected_symptoms,asset_tag,issue_summary,formatted_details';
        $networkMetadataKeys = 'network_type,location,selected_symptoms,issue_summary,formatted_details';
        $jobMetadataKeys = 'job_type,location,equipment_involved,preferred_schedule,issue_summary,formatted_details';
        $technicalAssistanceMetadataKeys = 'assistance_type,venue,schedule,equipment_needed,issue_summary,formatted_details';
        $otherMetadataKeys = 'subject,location,urgency,issue_summary,formatted_details';

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'service_request_category_id' => [
                'required',
                'integer',
                Rule::exists('service_request_categories', 'id')->where('is_active', true),
            ],
            'service_request_priority_id' => [
                'required',
                'integer',
                Rule::exists('service_request_priorities', 'id')->where('is_active', true),
            ],
            'description' => ['required', 'string'],
            'subtype' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:32'],
            'location_id' => ['nullable', 'required_with:department_id', 'integer', Rule::exists('locations', 'id')],
            'department_id' => [
                'nullable',
                'required_with:location_id',
                'integer',
                Rule::exists('departments', 'id')->where('location_id', $request->input('location_id')),
            ],
            'return_route' => ['nullable', Rule::in(['dashboard', 'service-requests.index'])],
            'metadata' => [
                $isHardwareRequest || $isSoftwareRequest || $isNetworkRequest || $isJobRequest || $isTechnicalAssistanceRequest || $isOtherRequest || $isAccessRequest || $isIdRequest ? 'required' : 'nullable',
                'array:id_request_type,id_first_name,id_middle_name,id_last_name,id_suffix,id_position,id_office,id_tin,id_gsis,id_birthdate,id_emergency_name,id_emergency_phone,id_emergency_address,'.$accountMetadataKeys.','.$hardwareMetadataKeys.','.$softwareMetadataKeys.','.$networkMetadataKeys.','.$jobMetadataKeys.','.$technicalAssistanceMetadataKeys.','.$otherMetadataKeys,
            ],
            'metadata.id_request_type' => $requiredIdText,
            'metadata.id_first_name' => $requiredIdText,
            'metadata.id_middle_name' => ['nullable', 'string', 'max:255'],
            'metadata.id_last_name' => $requiredIdText,
            'metadata.id_suffix' => ['nullable', 'string', 'max:32'],
            'metadata.id_position' => $requiredIdText,
            'metadata.id_office' => $requiredIdText,
            'metadata.id_tin' => ['nullable', 'regex:/^\d{3}-\d{3}-\d{3}$/'],
            'metadata.id_gsis' => ['nullable', 'digits:10'],
            'metadata.id_birthdate' => $isIdRequest
                ? ['required', 'date', 'before_or_equal:today']
                : ['nullable', 'date', 'before_or_equal:today'],
            'metadata.id_emergency_name' => $requiredIdText,
            'metadata.id_emergency_phone' => $isIdRequest
                ? ['required', 'regex:/^\d{11}$/']
                : ['nullable', 'regex:/^\d{11}$/'],
            'metadata.id_emergency_address' => $requiredIdText,
            'metadata.device_type' => $isHardwareRequest
                ? ['required', Rule::in([
                    'Desktop Computer',
                    'Laptop',
                    'Printer / Scanner',
                    'UPS / Power System',
                    'Network Device / Router',
                    'Peripherals / Others',
                ])]
                : ['nullable', 'string', 'max:255'],
            'metadata.property_tag' => ['nullable', 'string', 'max:255'],
            'metadata.selected_symptoms' => ['nullable', 'array'],
            'metadata.selected_symptoms.*' => ['string', Rule::in([
                'No Power / Will Not Turn On',
                'Blue Screen / System Crash',
                'Display / Monitor Issue',
                'Overheating / Noisy Fan',
                'Paper Jam / Printing Defect',
                'Peripheral Malfunction',
                'License Expired / Product Key Invalid',
                'App Freezing / Crashing on Launch',
                'Missing DLL / System Driver Error',
                'Virus / Malware Suspected',
                'Installation File Corrupted',
                'Slow App Performance',
                'No Internet Access',
                'Slow / Intermittent Connection',
                'Cannot See WiFi Network',
                'Damaged Wall Port / Cable',
                'IP Address Conflict',
                'Cannot Authenticate / Login',
            ])],
            'metadata.full_hardware_details' => ['nullable', 'string', 'max:6000'],
            'metadata.software_type' => $isSoftwareRequest
                ? ['required', Rule::in([
                    'Windows / OS',
                    'Microsoft Office / Productivity',
                    'Antivirus & Security',
                    'Specialized / Licensed Software',
                    'Drivers & Utilities',
                    'Others / General App Issue',
                ])]
                : ['nullable', 'string', 'max:255'],
            'metadata.action_type' => $isSoftwareRequest
                ? ['required', Rule::in([
                    'Software Installation',
                    'License Activation / Product Key',
                    'Bug / Crash / Error Fix',
                    'Version Upgrade Request',
                ])]
                : ['nullable', 'string', 'max:255'],
            'metadata.asset_tag' => ['nullable', 'string', 'max:255'],
            'metadata.issue_summary' => $isHardwareRequest || $isSoftwareRequest || $isNetworkRequest || $isJobRequest || $isTechnicalAssistanceRequest || $isOtherRequest
                ? ['required', 'string', 'max:4000']
                : ['nullable', 'string', 'max:4000'],
            'metadata.formatted_details' => $isSoftwareRequest || $isNetworkRequest || $isJobRequest || $isTechnicalAssistanceRequest || $isOtherRequest
                ? ['required', 'string', 'max:4000']
                : ['nullable', 'string', 'max:4000'],
            'metadata.network_type' => $isNetworkRequest
                ? ['required', Rule::in([
                    'WiFi Connection',
                    'Wired / LAN',
                    'General Internet / ISP',
                    'VPN Access',
                    'Others',
                ])]
                : ['nullable', 'string', 'max:255'],
            'metadata.job_type' => $isJobRequest
                ? ['required', Rule::in([
                    'Deployment / Installation',
                    'Transfer / Relocation',
                    'Preventive Maintenance',
                    'Inspection / Assessment',
                    'Others',
                ])]
                : ['nullable', 'string', 'max:255'],
            'metadata.location' => $isHardwareRequest || $isNetworkRequest || $isJobRequest || $isOtherRequest
                ? ['required', 'string', 'max:255']
                : ['nullable', 'string', 'max:255'],
            'metadata.equipment_involved' => ['nullable', 'string', 'max:255'],
            'metadata.preferred_schedule' => ['nullable', 'string', 'max:255'],
            'metadata.assistance_type' => $isTechnicalAssistanceRequest
                ? ['required', Rule::in([
                    'Virtual Meeting',
                    'Event / Seminar',
                    'AV / Equipment Setup',
                    'Others',
                ])]
                : ['nullable', 'string', 'max:255'],
            'metadata.venue' => $isTechnicalAssistanceRequest ? ['required', 'string', 'max:255'] : ['nullable', 'string', 'max:255'],
            'metadata.schedule' => $isTechnicalAssistanceRequest ? ['required', 'date'] : ['nullable', 'date'],
            'metadata.equipment_needed' => ['nullable', 'array'],
            'metadata.equipment_needed.*' => ['string', Rule::in([
                'Projector & Screen',
                'Sound System / Speakers',
                'Microphones',
                'Extension Wires',
                'Webcam / PTZ Camera',
                'Internet/WiFi Voucher',
            ])],
            'metadata.subject' => $isOtherRequest ? ['required', 'string', 'max:255'] : ['nullable', 'string', 'max:255'],
            'metadata.urgency' => $isOtherRequest
                ? ['required', Rule::in([
                    'Low (Routine / Inquiry)',
                    'Normal (Standard Request)',
                    'High (Impacting my work)',
                    'Critical (System Down / Widespread)',
                ])]
                : ['nullable', 'string', 'max:255'],
            'metadata.account_type' => $isAccessRequest
                ? ['required', Rule::in(['Student Email Account', 'Faculty / Staff Email Account'])]
                : ['nullable', 'string'],
            'metadata.account_classification' => $isAccessRequest
                ? [
                    Rule::requiredIf(! $isLegacyBulkStudentRequest),
                    Rule::in(['Student', 'Faculty/Staff']),
                    Rule::prohibitedIf(
                        ($request->input('metadata.account_type') === 'Student Email Account'
                            && $request->input('metadata.account_classification') !== 'Student')
                        || ($request->input('metadata.account_type') === 'Faculty / Staff Email Account'
                            && $request->input('metadata.account_classification') !== 'Faculty/Staff'),
                    ),
                ]
                : ['nullable', 'string'],
            'metadata.email_action' => $isAccessRequest
                ? ['required', Rule::in(['New Email Request', 'Recover Account'])]
                : ['nullable', 'string'],
            'metadata.is_bulk' => $isAccessRequest
                ? [
                    'nullable',
                    'boolean',
                    Rule::prohibitedIf($isBulkAccessRequest && $request->input('metadata.email_action') !== 'New Email Request'),
                ]
                : ['nullable', 'boolean'],
            'metadata.student_id' => $isAccessRequest && ! $isBulkAccessRequest
                ? [Rule::requiredIf(fn (): bool => $request->input('metadata.account_type') === 'Student Email Account'), 'nullable', 'string', 'max:100']
                : ['nullable', 'string', 'max:100'],
            'metadata.employee_id' => $isAccessRequest && ! $isBulkAccessRequest
                ? [Rule::requiredIf(fn (): bool => $request->input('metadata.account_type') === 'Faculty / Staff Email Account'), 'nullable', 'string', 'max:100']
                : ['nullable', 'string', 'max:100'],
            'metadata.department_office' => ['nullable', 'string', 'max:255'],
            'metadata.first_name' => $requiredAccessText,
            'metadata.middle_name' => ['nullable', 'string', 'max:255'],
            'metadata.middle_initial' => ['nullable', 'string', 'max:10'],
            'metadata.last_name' => $requiredAccessText,
            'metadata.suffix' => ['nullable', 'string', 'max:32'],
            'metadata.capsu_email' => $isAccessRequest && $request->input('metadata.email_action') === 'Recover Account' && ! $isBulkAccessRequest
                ? ['required', 'string', 'max:255']
                : ['nullable', 'string', 'max:255'],
            'metadata.personal_email' => $requiredAccessEmail
                ? ['required', 'email', 'max:255']
                : ['nullable', 'email', 'max:255'],
            'metadata.preferred_username' => $isAccessRequest && ! $isBulkAccessRequest && $request->input('metadata.email_action') === 'New Email Request'
                ? ['required', 'string', 'max:64', 'regex:/^[a-zA-Z0-9._-]+$/']
                : ['nullable', 'string', 'max:64'],
            'metadata.reason' => ['nullable', 'string', 'max:2000'],
            'metadata.students' => $isLegacyBulkStudentRequest
                ? ['required', 'array', 'min:1', 'max:500']
                : ['nullable', 'array', Rule::prohibitedIf(! $isLegacyBulkStudentRequest)],
            'metadata.students.*' => ['array:student_id,first_name,middle_name,middle_initial,last_name,suffix,personal_email,generated_email,status,full_name'],
            'metadata.students.*.student_id' => $isBulkAccessRequest ? ['required', 'string', 'max:100'] : ['nullable', 'string', 'max:100'],
            'metadata.students.*.first_name' => $isBulkAccessRequest ? ['required', 'string', 'max:255'] : ['nullable', 'string', 'max:255'],
            'metadata.students.*.middle_name' => ['nullable', 'string', 'max:255'],
            'metadata.students.*.middle_initial' => ['nullable', 'string', 'max:10'],
            'metadata.students.*.last_name' => $isBulkAccessRequest ? ['required', 'string', 'max:255'] : ['nullable', 'string', 'max:255'],
            'metadata.students.*.suffix' => ['nullable', 'string', 'max:32'],
            'metadata.students.*.personal_email' => $isBulkAccessRequest ? ['required', 'email', 'max:255'] : ['nullable', 'email', 'max:255'],
            'metadata.students.*.generated_email' => ['nullable', 'email', 'max:255'],
            'metadata.students.*.status' => ['nullable', 'string', 'max:64'],
            'metadata.students.*.full_name' => ['nullable', 'string', 'max:255'],
            'metadata.applicants' => $isBulkAccessRequest && ! $isLegacyBulkStudentRequest
                ? ['required', 'array', 'min:1', 'max:500']
                : ['nullable', 'array', Rule::prohibitedIf(! ($isBulkAccessRequest && ! $isLegacyBulkStudentRequest))],
            'metadata.applicants.*' => ['array:id_number,first_name,middle_name,middle_initial,last_name,suffix,personal_email,generated_email,department_office,status,full_name'],
            'metadata.applicants.*.id_number' => $isBulkAccessRequest && ! $isLegacyBulkStudentRequest ? ['required', 'string', 'max:100'] : ['nullable', 'string', 'max:100'],
            'metadata.applicants.*.first_name' => $isBulkAccessRequest && ! $isLegacyBulkStudentRequest ? ['required', 'string', 'max:255'] : ['nullable', 'string', 'max:255'],
            'metadata.applicants.*.middle_name' => ['nullable', 'string', 'max:255'],
            'metadata.applicants.*.middle_initial' => ['nullable', 'string', 'max:10'],
            'metadata.applicants.*.last_name' => $isBulkAccessRequest && ! $isLegacyBulkStudentRequest ? ['required', 'string', 'max:255'] : ['nullable', 'string', 'max:255'],
            'metadata.applicants.*.suffix' => ['nullable', 'string', 'max:32'],
            'metadata.applicants.*.personal_email' => $isBulkAccessRequest && ! $isLegacyBulkStudentRequest ? ['required', 'email', 'max:255'] : ['nullable', 'email', 'max:255'],
            'metadata.applicants.*.generated_email' => ['nullable', 'email', 'max:255'],
            'metadata.applicants.*.department_office' => ['nullable', 'string', 'max:255'],
            'metadata.applicants.*.status' => ['nullable', 'string', 'max:64'],
            'metadata.applicants.*.full_name' => ['nullable', 'string', 'max:255'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => [
                'nullable',
                'file',
                'max:5120',
                'mimes:jpg,jpeg,png,pdf,doc,docx',
            ],
            'attachments.0' => $isIdRequest
                ? ['required', 'image', 'max:5120']
                : ['nullable'],
            'attachments.1' => $isIdRequest
                ? ['required', 'image', 'max:5120']
                : ['nullable'],
        ]);

        $uploadedFiles = $request->file('attachments', []);
        $storedPaths = [];

        try {
            $serviceRequest = DB::transaction(function () use ($validated, $uploadedFiles, $isIdRequest, $isAccessRequest, $isBulkAccessRequest, &$storedPaths) {
                $metadata = [
                    ...($validated['metadata'] ?? []),
                    'subtype' => $validated['subtype'] ?? null,
                    'contact_number' => $validated['contact_number'] ?? null,
                ];
                if ($isAccessRequest) {
                    $metadata['is_bulk'] = $metadata['is_bulk'] ?? false;
                }
                if ($isBulkAccessRequest || isset($metadata['account_type'])) {
                    $metadata['is_bulk'] = $metadata['is_bulk'] ?? false;
                }

                if (isset($metadata['account_classification'])) {
                    $domain = $metadata['account_classification'] === 'Student'
                        ? '@students.capsu.edu.ph'
                        : '@capsu.edu.ph';
                    $emailPrefix = $metadata['email_action'] === 'Recover Account'
                        ? (string) Str::before(Str::lower(trim($metadata['capsu_email'] ?? '')), '@')
                        : (string) Str::before(Str::lower(trim($metadata['preferred_username'] ?? '')), '@');
                    $metadata['capsu_email_prefix'] = $emailPrefix;
                    $metadata['domain_used'] = $domain;

                    if ($metadata['email_action'] === 'New Email Request') {
                        $metadata['preferred_username'] = $emailPrefix;
                        $metadata['full_institutional_email'] = $emailPrefix !== '' ? $emailPrefix.$domain : '';
                    }
                }

                if ($isBulkAccessRequest) {
                    $domain = ($metadata['account_classification'] ?? 'Student') === 'Student'
                        ? '@students.capsu.edu.ph'
                        : '@capsu.edu.ph';
                    if (isset($metadata['applicants'])) {
                        $metadata['applicants'] = $this->prepareBulkApplicants($metadata['applicants'], $domain);
                    }
                    if (isset($metadata['students'])) {
                        $metadata['students'] = collect($metadata['students'])
                            ->map(function (array $student): array {
                                $firstInitial = mb_strtolower(mb_substr(trim($student['first_name']), 0, 1));
                                $middleName = trim($student['middle_name'] ?? $student['middle_initial'] ?? '');
                                $middleInitial = mb_strtolower(mb_substr($middleName, 0, 1));
                                $lastName = mb_strtolower((string) preg_replace('/\s+/', '', trim($student['last_name'])));
                                $suffix = mb_strtolower(str_replace('.', '', trim($student['suffix'] ?? '')));

                                return [
                                    ...$student,
                                    'middle_name' => $middleName,
                                    'middle_initial' => mb_strtoupper(mb_substr($middleName, 0, 1)),
                                    'full_name' => trim(implode(' ', array_filter([
                                        trim($student['first_name']),
                                        $middleName !== ''
                                            ? mb_strtoupper(mb_substr($middleName, 0, 1)).'.'
                                            : '',
                                        trim($student['last_name']),
                                        trim($student['suffix'] ?? ''),
                                    ]))),
                                    'generated_email' => $firstInitial.$middleInitial.$lastName.$suffix.'@students.capsu.edu.ph',
                                    'status' => 'pending',
                                ];
                            })
                            ->all();
                    }
                }

                $statusId = ServiceRequestStatus::query()
                    ->where('slug', 'submitted')
                    ->value('id')
                    ?? ServiceRequestStatus::query()
                        ->where('slug', 'pending')
                        ->value('id');

                if ($statusId === null) {
                    throw new RuntimeException('No submitted or pending service request status is configured.');
                }

                $serviceRequest = ServiceRequest::create([
                    'request_reference' => 'SR-'.now()->format('Y').'-'.Str::upper(Str::random(8)),
                    'requester_id' => Auth::id(),
                    'location_id' => $validated['location_id'] ?? null,
                    'department_id' => $validated['department_id'] ?? null,
                    'service_request_category_id' => $validated['service_request_category_id'],
                    'service_request_priority_id' => $validated['service_request_priority_id'],
                    'service_request_status_id' => $statusId,
                    'title' => $validated['title'],
                    'description' => $validated['description'],
                    'submitted_at' => now(),
                    'metadata' => $metadata,
                ]);

                foreach ($uploadedFiles as $index => $file) {
                    if ($file === null) {
                        continue;
                    }

                    $mimeType = $file->getMimeType();
                    $sizeBytes = $file->getSize();
                    $fileName = $file->getClientOriginalName();

                    if ($isIdRequest && in_array($index, [0, 1], true)) {
                        [$path, $mimeType, $sizeBytes] = $this->storeOptimizedIdImage(
                            $file,
                            $index === 0 ? 'id-photo' : 'digital-signature',
                        );
                        $fileName = $index === 0
                            ? '2x2-ID-Picture.jpg'
                            : 'Digital-Signature.'.($mimeType === 'image/png' ? 'png' : 'jpg');
                    } else {
                        $path = $file->store('attachments/'.now()->format('Y/m'), 'public');

                        if ($path === false) {
                            throw new RuntimeException('The attachment could not be stored.');
                        }
                    }

                    $storedPaths[] = $path;

                    if ($isIdRequest && $index === 0) {
                        $metadata['id_photo_path'] = $path;
                    } elseif ($isIdRequest && $index === 1) {
                        $metadata['client_signature_path'] = $path;
                    }

                    $serviceRequest->attachments()->create([
                        'file_name' => $fileName,
                        'file_path' => $path,
                        'mime_type' => $mimeType,
                        'size_bytes' => $sizeBytes,
                    ]);
                }

                if ($isIdRequest) {
                    $serviceRequest->update(['metadata' => $metadata]);
                }

                return $serviceRequest;
            });
        } catch (Throwable $exception) {
            Log::error('Service request creation failed.', [
                'exception' => $exception,
                'requester_id' => Auth::id(),
                'category_id' => $validated['service_request_category_id'],
            ]);
            Storage::disk('public')->delete($storedPaths);

            throw $exception;
        }

        return redirect()
            ->route($validated['return_route'] ?? 'service-requests.index')
            ->with('success', "Service request {$serviceRequest->request_reference} was submitted successfully.");
    }

    /**
     * @param  array<int, array<string, mixed>>  $applicants
     * @return array<int, array<string, mixed>>
     */
    private function prepareBulkApplicants(array $applicants, string $domain): array
    {
        return collect($applicants)
            ->map(function (array $applicant) use ($domain): array {
                $firstInitial = mb_strtolower(mb_substr(trim($applicant['first_name']), 0, 1));
                $middleName = trim($applicant['middle_name'] ?? $applicant['middle_initial'] ?? '');
                $middleInitial = mb_strtolower(mb_substr($middleName, 0, 1));
                $lastName = mb_strtolower((string) preg_replace('/\s+/', '', trim($applicant['last_name'])));
                $suffix = mb_strtolower((string) preg_replace('/[.\s]/', '', trim($applicant['suffix'] ?? '')));
                $name = trim(implode(' ', array_filter([
                    trim($applicant['first_name']),
                    $middleName !== '' ? mb_strtoupper(mb_substr($middleName, 0, 1)).'.' : '',
                    trim($applicant['last_name']),
                    trim($applicant['suffix'] ?? ''),
                ])));

                return [
                    ...$applicant,
                    'middle_name' => $middleName,
                    'middle_initial' => mb_strtoupper(mb_substr($middleName, 0, 1)),
                    'full_name' => $name,
                    'generated_email' => $firstInitial.$middleInitial.$lastName.$suffix.$domain,
                    'status' => 'pending',
                ];
            })
            ->all();
    }

    /**
     * Store an optimized ID photo or signature and return its path, MIME type, and size.
     *
     * @return array{string, string, int}
     */
    private function storeOptimizedIdImage(UploadedFile $file, string $imageType): array
    {
        $sourceContents = file_get_contents($file->getRealPath());

        if ($sourceContents === false) {
            throw new RuntimeException('The uploaded ID request image could not be read.');
        }

        $source = @imagecreatefromstring($sourceContents);

        if ($source === false) {
            throw new RuntimeException('The uploaded ID request image could not be processed.');
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $isIdPhoto = $imageType === 'id-photo';

        if ($isIdPhoto) {
            $cropSize = min($sourceWidth, $sourceHeight);
            $image = imagecreatetruecolor(600, 600);

            if ($image === false) {
                imagedestroy($source);

                throw new RuntimeException('Unable to allocate memory for the ID photo.');
            }

            imagecopyresampled(
                $image,
                $source,
                0,
                0,
                (int) floor(($sourceWidth - $cropSize) / 2),
                (int) floor(($sourceHeight - $cropSize) / 2),
                600,
                600,
                $cropSize,
                $cropSize,
            );
            $mimeType = 'image/jpeg';
            $extension = 'jpg';
        } else {
            $scale = min(1, 800 / $sourceWidth);
            $width = max(1, (int) round($sourceWidth * $scale));
            $height = max(1, (int) round($sourceHeight * $scale));
            $image = imagecreatetruecolor($width, $height);

            if ($image === false) {
                imagedestroy($source);

                throw new RuntimeException('Unable to allocate memory for the digital signature.');
            }

            $isPng = $file->getMimeType() === 'image/png';

            if ($isPng) {
                imagealphablending($image, false);
                imagesavealpha($image, true);
                $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
                imagefill($image, 0, 0, $transparent);
            }

            imagecopyresampled($image, $source, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);
            $mimeType = $isPng ? 'image/png' : 'image/jpeg';
            $extension = $isPng ? 'png' : 'jpg';
        }

        ob_start();
        $encoded = $mimeType === 'image/png'
            ? imagepng($image, null, 6)
            : imagejpeg($image, null, 80);
        $contents = ob_get_clean();
        imagedestroy($image);
        imagedestroy($source);

        if (! $encoded || $contents === false) {
            throw new RuntimeException('The ID request image could not be encoded.');
        }

        $path = 'service-requests/'.now()->format('Y/m').'/'.$imageType.'-'.Str::uuid().'.'.$extension;

        if (! Storage::disk('public')->put($path, $contents)) {
            throw new RuntimeException('The optimized ID request image could not be stored.');
        }

        return [$path, $mimeType, strlen($contents)];
    }
}
