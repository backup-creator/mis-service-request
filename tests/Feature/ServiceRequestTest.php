<?php

use App\Filament\Resources\ServiceRequestResource;
use App\Filament\Resources\ServiceRequestResource\Pages\ListServiceRequests;
use App\Filament\Resources\ServiceRequestResource\Pages\ViewServiceRequest;
use App\Filament\Resources\MaintenanceChecklistResource;
use App\Filament\Resources\MaintenanceChecklistResource\Pages\CreateMaintenanceChecklist;
use App\Filament\Resources\DepartmentResource;
use App\Filament\Resources\DepartmentResource\Pages\CreateDepartment;
use App\Filament\Resources\LocationResource;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\LatestServiceRequests;
use App\Filament\Widgets\RequestsByCategoryChart;
use App\Filament\Widgets\RequestsByDepartmentChart;
use App\Filament\Widgets\RequestsByPriorityChart;
use App\Filament\Widgets\RequestsPerMonthChart;
use App\Models\MaintenanceChecklist;
use App\Models\Department;
use App\Models\Location;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestCategory;
use App\Models\ServiceRequestPriority;
use App\Models\ServiceRequestStatus;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\ServiceRequestLookupSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;

test('service request lookup seeder creates the exact active request categories', function () {
    $this->seed(ServiceRequestLookupSeeder::class);

    expect(ServiceRequestStatus::query()->where('slug', 'accepted')->value('name'))
        ->toBe('Accepted');

    expect(ServiceRequestCategory::query()
        ->where('is_active', true)
        ->orderBy('sort_order')
        ->get(['name', 'code', 'description'])
        ->map(fn (ServiceRequestCategory $category): array => [
            'name' => $category->name,
            'code' => $category->code,
            'description' => $category->description,
        ])
        ->all())->toBe([
            [
                'name' => 'ID Request',
                'code' => 'ID_REQUEST',
                'description' => 'Request a new ID, replacement, or status update for faculty or staff records.',
            ],
            [
                'name' => 'Account & Access',
                'code' => 'ACCESS',
                'description' => 'Request account creation, password resets, and access changes for systems and services.',
            ],
            [
                'name' => 'Hardware Support',
                'code' => 'HARDWARE',
                'description' => 'Report equipment issues, request replacements, or ask for help with device setup.',
            ],
            [
                'name' => 'Software Support',
                'code' => 'SOFTWARE',
                'description' => 'Request help with software installation, licensing, or application troubleshooting.',
            ],
            [
                'name' => 'Network & Internet',
                'code' => 'NETWORK',
                'description' => 'Report connectivity issues, Wi-Fi concerns, or network-related service requests.',
            ],
            [
                'name' => 'Job Request',
                'code' => 'JOB_REQUEST',
                'description' => 'Submit maintenance, deployment, installation, or recurring support work requests.',
            ],
            [
                'name' => 'Technical Assistance',
                'code' => 'TECH_ASSIST',
                'description' => 'Get help with general technical issues or urgent assistance for day-to-day support.',
            ],
            [
                'name' => 'Others',
                'code' => 'OTHERS',
                'description' => 'Choose this option when your request does not fit one of the standard categories.',
            ],
        ]);
});

test('database seeder creates or updates the default user roles', function () {
    $this->seed(DatabaseSeeder::class);

    expect(User::query()->where('email', 'maeslabonjr@capsu.edu.ph')->firstOrFail())
        ->name->toBe('Marvin Arlante Eslabon Jr.')
        ->role->toBe('user')
        ->is_admin->toBeFalse();

    expect(User::query()->where('email', 'admin@capsu.edu.ph')->firstOrFail())
        ->name->toBe('Administrator')
        ->role->toBe('admin')
        ->is_admin->toBeTrue();

    expect(User::query()->where('email', 'mis@capsu.edu.ph')->firstOrFail())
        ->name->toBe('MIS Staff')
        ->role->toBe('mis_staff')
        ->is_admin->toBeFalse();

    expect(User::query()->where('email', 'hr@capsu.edu.ph')->firstOrFail())
        ->name->toBe('HR Department')
        ->role->toBe('hr')
        ->is_admin->toBeFalse();
});

test('location and department seeders create and associate the central administration departments idempotently', function () {
    $this->seed(LocationSeeder::class);
    $this->seed(DepartmentSeeder::class);
    $this->seed(LocationSeeder::class);
    $this->seed(DepartmentSeeder::class);

    $centralAdministration = Location::query()
        ->where('name', 'Central Administration')
        ->firstOrFail();

    expect(Location::query()->count())->toBe(6)
        ->and(Location::query()->orderBy('name')->pluck('name')->all())->toBe([
            'Central Administration',
            'Dayao Satellite Campus',
            'Pontevedra Satellite Campus',
            'Roxas Main Campus',
            'Sigma Satellite Campus',
            'Tapaz Satellite Campus',
        ])
        ->and(Department::query()->count())->toBe(31)
        ->and(Department::query()->where('name', 'MIS Office')->firstOrFail()->location_id)
        ->toBe($centralAdministration->id)
        ->and($centralAdministration->departments()->count())->toBe(31);
});

test('users can save a department belonging to their selected location', function () {
    $this->seed(LocationSeeder::class);
    $centralAdministration = Location::query()->where('name', 'Central Administration')->firstOrFail();
    $roxasCampus = Location::query()->where('name', 'Roxas Main Campus')->firstOrFail();
    $centralDepartment = Department::query()->create([
        'name' => 'MIS Office',
        'location_id' => $centralAdministration->id,
    ]);
    $campusDepartment = Department::query()->create([
        'name' => 'MIS Office',
        'location_id' => $roxasCampus->id,
    ]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.office.update'), [
            'location_id' => $roxasCampus->id,
            'department_id' => $campusDepartment->id,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'location_id' => $roxasCampus->id,
        'department_id' => $campusDepartment->id,
    ]);

    $this->actingAs($user)
        ->patch(route('profile.office.update'), [
            'location_id' => $centralAdministration->id,
            'department_id' => $campusDepartment->id,
        ])
        ->assertSessionHasErrors('department_id');

    expect($centralDepartment->location_id)->toBe($centralAdministration->id);
});

test('inertia pages share configured server identity', function () {
    config([
        'app.server_name' => 'Server 2',
        'app.server_ip' => '192.168.1.20',
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('server_info.name', 'Server 2')
            ->where('server_info.ip', '192.168.1.20'));
});

test('Filament location and department resources support per-location departments', function () {
    $this->seed(LocationSeeder::class);
    $centralAdministration = Location::query()->where('name', 'Central Administration')->firstOrFail();
    $roxasCampus = Location::query()->where('name', 'Roxas Main Campus')->firstOrFail();
    $centralRegistrar = Department::query()->create([
        'name' => 'Registrar',
        'location_id' => $centralAdministration->id,
    ]);
    $campusRegistrar = Department::query()->create([
        'name' => 'Registrar',
        'location_id' => $roxasCampus->id,
    ]);
    $misStaff = User::factory()->create(['role' => 'mis_staff']);

    expect($centralRegistrar->id)->not->toBe($campusRegistrar->id)
        ->and($centralRegistrar->location_id)->toBe($centralAdministration->id)
        ->and($campusRegistrar->location_id)->toBe($roxasCampus->id);

    $this->actingAs($misStaff)
        ->get(route('filament.admin.resources.locations.index'))
        ->assertOk()
        ->assertSee('Reference Data');

    $this->get(route('filament.admin.resources.departments.index'))
        ->assertOk()
        ->assertSee('Filter by Location');

    Livewire::actingAs($misStaff)
        ->test(CreateDepartment::class)
        ->fillForm([
            'location_id' => $roxasCampus->id,
            'name' => 'MIS Office',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Department::query()
        ->where('location_id', $roxasCampus->id)
        ->where('name', 'MIS Office')
        ->exists())->toBeTrue();
});

test('service requests reject a department that does not belong to the selected location', function () {
    $this->seed(ServiceRequestLookupSeeder::class);
    $this->seed(LocationSeeder::class);
    $centralAdministration = Location::query()->where('name', 'Central Administration')->firstOrFail();
    $roxasCampus = Location::query()->where('name', 'Roxas Main Campus')->firstOrFail();
    $centralDepartment = Department::query()->create([
        'name' => 'Registrar',
        'location_id' => $centralAdministration->id,
    ]);
    $user = User::factory()->create();
    $request = ServiceRequest::query()->create([
        'request_reference' => 'SR-2026-LOCATION1',
        'requester_id' => $user->id,
        'service_request_category_id' => ServiceRequestCategory::query()->where('code', 'HARDWARE')->value('id'),
        'service_request_priority_id' => ServiceRequestPriority::query()->where('name', 'Normal')->value('id'),
        'title' => 'Location validation',
        'description' => 'Verify location and department match.',
    ]);

    expect(fn () => $request->update([
        'location_id' => $roxasCampus->id,
        'department_id' => $centralDepartment->id,
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);

    $request->update([
        'location_id' => $centralAdministration->id,
        'department_id' => $centralDepartment->id,
    ]);

    expect($request->fresh()->department_id)->toBe($centralDepartment->id);
});

test('the request form lists active categories and priorities in sort order', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    ServiceRequestCategory::create(['name' => 'Later', 'code' => 'LATER', 'sort_order' => 2, 'is_active' => true]);
    ServiceRequestCategory::create(['name' => 'Earlier', 'code' => 'EARLIER', 'sort_order' => 1, 'is_active' => true]);
    ServiceRequestCategory::create(['name' => 'Inactive', 'code' => 'INACTIVE', 'sort_order' => 0, 'is_active' => false]);
    ServiceRequestPriority::create(['name' => 'Normal', 'code' => 'NORMAL', 'sort_order' => 2, 'is_active' => true]);
    ServiceRequestPriority::create(['name' => 'Urgent', 'code' => 'URGENT', 'sort_order' => 1, 'is_active' => true]);

    $this->get(route('service-requests.create'))
        ->assertRedirect(route('dashboard', ['createRequest' => 1]));

    $this->get(route('dashboard', ['createRequest' => 1]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('openCreateModal', true)
            ->where('categories.0.name', 'Earlier')
            ->where('categories.1.name', 'Later')
            ->has('categories', 2)
            ->where('priorities.0.name', 'Urgent')
            ->where('priorities.1.name', 'Normal'));
});

test('authenticated users can submit requests and upload attachments', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $category = ServiceRequestCategory::create(['name' => 'General Support', 'code' => 'GENERAL', 'sort_order' => 1, 'is_active' => true]);
    $priority = ServiceRequestPriority::create(['name' => 'High', 'code' => 'HIGH', 'sort_order' => 1, 'is_active' => true]);
    $status = ServiceRequestStatus::create([
        'name' => 'Submitted',
        'slug' => 'submitted',
        'code' => 'SUBMITTED',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)->post(route('service-requests.store'), [
        'title' => 'Other service request',
        'service_request_category_id' => $category->id,
        'service_request_priority_id' => $priority->id,
        'description' => 'Please help resolve this technical issue.',
        'subtype' => 'Connectivity',
        'contact_number' => '555-0100',
        'attachments' => [
            UploadedFile::fake()->image('laptop.jpg'),
        ],
    ]);

    $serviceRequest = ServiceRequest::query()->firstOrFail();

    $response->assertRedirect(route('service-requests.index'));
    $response->assertSessionHas('success', "Service request {$serviceRequest->request_reference} was submitted successfully.");
    expect($serviceRequest->requester_id)->toBe($user->id)
        ->and($serviceRequest->service_request_status_id)->toBe($status->id)
        ->and($serviceRequest->metadata)->toBe([
            'subtype' => 'Connectivity',
            'contact_number' => '555-0100',
        ])
        ->and($serviceRequest->attachments)->toHaveCount(1);

    Storage::disk('public')->assertExists($serviceRequest->attachments[0]->file_path);
});

test('hardware support requests persist structured details and appear on the dashboard', function () {
    $user = User::factory()->create();
    $category = ServiceRequestCategory::create(['name' => 'Hardware Support', 'code' => 'HARDWARE', 'sort_order' => 1, 'is_active' => true]);
    $priority = ServiceRequestPriority::create(['name' => 'Normal', 'code' => 'NORMAL', 'sort_order' => 1, 'is_active' => true]);
    ServiceRequestStatus::create([
        'name' => 'Submitted',
        'slug' => 'submitted',
        'code' => 'SUBMITTED',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $details = "Device: Laptop\nOffice / Department & Room: Admin Office - Room 102\nProperty / Asset Tag: CAPSU-2024-HW-0012\nCommon Problems: No Power / Will Not Turn On\nIssue Description: Laptop does not start.";

    $response = $this->actingAs($user)->post(route('service-requests.store'), [
        'title' => 'Laptop Support Request',
        'service_request_category_id' => $category->id,
        'service_request_priority_id' => $priority->id,
        'description' => $details,
        'subtype' => 'Laptop',
        'return_route' => 'dashboard',
        'metadata' => [
            'device_type' => 'Laptop',
            'location' => 'Admin Office - Room 102',
            'property_tag' => 'CAPSU-2024-HW-0012',
            'selected_symptoms' => ['No Power / Will Not Turn On'],
            'issue_summary' => 'Laptop does not start.',
            'full_hardware_details' => $details,
        ],
    ]);

    $serviceRequest = ServiceRequest::query()->firstOrFail();
    $response->assertRedirect(route('dashboard'));
    expect($serviceRequest->requester_id)->toBe($user->id)
        ->and($serviceRequest->metadata)->toMatchArray([
            'device_type' => 'Laptop',
            'location' => 'Admin Office - Room 102',
            'property_tag' => 'CAPSU-2024-HW-0012',
            'selected_symptoms' => ['No Power / Will Not Turn On'],
            'issue_summary' => 'Laptop does not start.',
            'full_hardware_details' => $details,
        ]);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('recentRequests.0.id', $serviceRequest->id)
            ->where('recentRequests.0.category.code', 'HARDWARE')
            ->where('recentRequests.0.title', 'Laptop Support Request'));
});

test('software support requests persist structured details and appear on the dashboard', function () {
    $user = User::factory()->create();
    $category = ServiceRequestCategory::create(['name' => 'Software Support', 'code' => 'SOFTWARE', 'sort_order' => 1, 'is_active' => true]);
    $priority = ServiceRequestPriority::create(['name' => 'Normal', 'code' => 'NORMAL', 'sort_order' => 1, 'is_active' => true]);
    ServiceRequestStatus::create([
        'name' => 'Submitted',
        'slug' => 'submitted',
        'code' => 'SUBMITTED',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $formattedDetails = 'Category: Windows / OS | Action: Software Installation | Issues: Installation File Corrupted';
    $response = $this->actingAs($user)->post(route('service-requests.store'), [
        'title' => 'Windows / OS - Software Installation',
        'service_request_category_id' => $category->id,
        'service_request_priority_id' => $priority->id,
        'description' => $formattedDetails."\nComputer Asset Tag / PC Name: MIS-PC-04\nIssue Context: Installation fails with a corrupted file error.",
        'subtype' => 'Software Installation',
        'return_route' => 'dashboard',
        'metadata' => [
            'software_type' => 'Windows / OS',
            'action_type' => 'Software Installation',
            'selected_symptoms' => ['Installation File Corrupted'],
            'asset_tag' => 'MIS-PC-04',
            'issue_summary' => 'Installation fails with a corrupted file error.',
            'formatted_details' => $formattedDetails,
        ],
    ]);

    $serviceRequest = ServiceRequest::query()->firstOrFail();
    $response->assertRedirect(route('dashboard'));
    expect($serviceRequest->requester_id)->toBe($user->id)
        ->and($serviceRequest->metadata)->toMatchArray([
            'software_type' => 'Windows / OS',
            'action_type' => 'Software Installation',
            'selected_symptoms' => ['Installation File Corrupted'],
            'asset_tag' => 'MIS-PC-04',
            'issue_summary' => 'Installation fails with a corrupted file error.',
            'formatted_details' => $formattedDetails,
        ]);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('recentRequests.0.id', $serviceRequest->id)
            ->where('recentRequests.0.category.code', 'SOFTWARE')
            ->where('recentRequests.0.title', 'Windows / OS - Software Installation'));
});

test('network support requests persist structured details and appear on the dashboard', function () {
    $user = User::factory()->create();
    $category = ServiceRequestCategory::create(['name' => 'Network & Internet', 'code' => 'NETWORK', 'sort_order' => 1, 'is_active' => true]);
    $priority = ServiceRequestPriority::create(['name' => 'Normal', 'code' => 'NORMAL', 'sort_order' => 1, 'is_active' => true]);
    ServiceRequestStatus::create([
        'name' => 'Submitted',
        'slug' => 'submitted',
        'code' => 'SUBMITTED',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $formattedDetails = 'Category: WiFi Connection | Location: Library - 2nd Floor Reading Area | Issues: No Internet Access, Slow / Intermittent Connection';
    $response = $this->actingAs($user)->post(route('service-requests.store'), [
        'title' => 'WiFi Connection Support Request',
        'service_request_category_id' => $category->id,
        'service_request_priority_id' => $priority->id,
        'description' => $formattedDetails."\nIssue Description: Unable to connect in the reading area.",
        'subtype' => 'WiFi Connection',
        'return_route' => 'dashboard',
        'metadata' => [
            'network_type' => 'WiFi Connection',
            'location' => 'Library - 2nd Floor Reading Area',
            'selected_symptoms' => ['No Internet Access', 'Slow / Intermittent Connection'],
            'issue_summary' => 'Unable to connect in the reading area.',
            'formatted_details' => $formattedDetails,
        ],
    ]);

    $serviceRequest = ServiceRequest::query()->firstOrFail();
    $response->assertRedirect(route('dashboard'));
    expect($serviceRequest->requester_id)->toBe($user->id)
        ->and($serviceRequest->metadata)->toMatchArray([
            'network_type' => 'WiFi Connection',
            'location' => 'Library - 2nd Floor Reading Area',
            'selected_symptoms' => ['No Internet Access', 'Slow / Intermittent Connection'],
            'issue_summary' => 'Unable to connect in the reading area.',
            'formatted_details' => $formattedDetails,
        ]);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('recentRequests.0.id', $serviceRequest->id)
            ->where('recentRequests.0.category.code', 'NETWORK')
            ->where('recentRequests.0.title', 'WiFi Connection Support Request'));
});

test('job requests persist structured details and appear on the dashboard', function () {
    $user = User::factory()->create();
    $category = ServiceRequestCategory::create(['name' => 'Job Request', 'code' => 'JOB_REQUEST', 'sort_order' => 1, 'is_active' => true]);
    $priority = ServiceRequestPriority::create(['name' => 'Normal', 'code' => 'NORMAL', 'sort_order' => 1, 'is_active' => true]);
    ServiceRequestStatus::create([
        'name' => 'Submitted',
        'slug' => 'submitted',
        'code' => 'SUBMITTED',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $formattedDetails = 'Category: Transfer / Relocation | Location: From Room A to Room B | Equipment: 3 Desktop PCs';
    $response = $this->actingAs($user)->post(route('service-requests.store'), [
        'title' => 'Transfer / Relocation Job Request',
        'service_request_category_id' => $category->id,
        'service_request_priority_id' => $priority->id,
        'description' => $formattedDetails."\nPreferred Schedule: Friday Afternoon\nJob Summary: Relocate three workstations.",
        'subtype' => 'Transfer / Relocation',
        'return_route' => 'dashboard',
        'metadata' => [
            'job_type' => 'Transfer / Relocation',
            'location' => 'From Room A to Room B',
            'equipment_involved' => '3 Desktop PCs',
            'preferred_schedule' => 'Friday Afternoon',
            'issue_summary' => 'Relocate three workstations.',
            'formatted_details' => $formattedDetails,
        ],
    ]);

    $serviceRequest = ServiceRequest::query()->firstOrFail();
    $response->assertRedirect(route('dashboard'));
    expect($serviceRequest->requester_id)->toBe($user->id)
        ->and($serviceRequest->metadata)->toMatchArray([
            'job_type' => 'Transfer / Relocation',
            'location' => 'From Room A to Room B',
            'equipment_involved' => '3 Desktop PCs',
            'preferred_schedule' => 'Friday Afternoon',
            'issue_summary' => 'Relocate three workstations.',
            'formatted_details' => $formattedDetails,
        ]);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('recentRequests.0.id', $serviceRequest->id)
            ->where('recentRequests.0.category.code', 'JOB_REQUEST')
            ->where('recentRequests.0.title', 'Transfer / Relocation Job Request'));
});

test('technical assistance requests persist event details and appear on the dashboard', function () {
    $user = User::factory()->create();
    $category = ServiceRequestCategory::create(['name' => 'Technical Assistance', 'code' => 'TECH_ASSIST', 'sort_order' => 1, 'is_active' => true]);
    $priority = ServiceRequestPriority::create(['name' => 'Normal', 'code' => 'NORMAL', 'sort_order' => 1, 'is_active' => true]);
    ServiceRequestStatus::create([
        'name' => 'Submitted',
        'slug' => 'submitted',
        'code' => 'SUBMITTED',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $schedule = '2026-10-25T14:00';
    $formattedDetails = 'Category: Event / Seminar | Venue: Main Boardroom | Schedule: '.$schedule.' | Equipment: Projector & Screen, Microphones';
    $response = $this->actingAs($user)->post(route('service-requests.store'), [
        'title' => 'Event / Seminar Technical Assistance',
        'service_request_category_id' => $category->id,
        'service_request_priority_id' => $priority->id,
        'description' => $formattedDetails."\nSupport Summary: Support the campus seminar.",
        'subtype' => 'Event / Seminar',
        'return_route' => 'dashboard',
        'metadata' => [
            'assistance_type' => 'Event / Seminar',
            'venue' => 'Main Boardroom',
            'schedule' => $schedule,
            'equipment_needed' => ['Projector & Screen', 'Microphones'],
            'issue_summary' => 'Support the campus seminar.',
            'formatted_details' => $formattedDetails,
        ],
    ]);

    $serviceRequest = ServiceRequest::query()->firstOrFail();
    $response->assertRedirect(route('dashboard'));
    expect($serviceRequest->requester_id)->toBe($user->id)
        ->and($serviceRequest->metadata)->toMatchArray([
            'assistance_type' => 'Event / Seminar',
            'venue' => 'Main Boardroom',
            'schedule' => $schedule,
            'equipment_needed' => ['Projector & Screen', 'Microphones'],
            'issue_summary' => 'Support the campus seminar.',
            'formatted_details' => $formattedDetails,
        ]);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('recentRequests.0.id', $serviceRequest->id)
            ->where('recentRequests.0.category.code', 'TECH_ASSIST')
            ->where('recentRequests.0.title', 'Event / Seminar Technical Assistance'));
});

test('other requests persist structured details and appear on the dashboard', function () {
    $user = User::factory()->create();
    $category = ServiceRequestCategory::create(['name' => 'Others', 'code' => 'OTHERS', 'sort_order' => 1, 'is_active' => true]);
    $priority = ServiceRequestPriority::create(['name' => 'Normal', 'code' => 'NORMAL', 'sort_order' => 1, 'is_active' => true]);
    ServiceRequestStatus::create([
        'name' => 'Submitted',
        'slug' => 'submitted',
        'code' => 'SUBMITTED',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $formattedDetails = 'Subject: Need access to shared drive | Urgency: High (Impacting my work) | Location: Admin Office - Room 102';
    $response = $this->actingAs($user)->post(route('service-requests.store'), [
        'title' => 'Need access to shared drive',
        'service_request_category_id' => $category->id,
        'service_request_priority_id' => $priority->id,
        'description' => $formattedDetails."\n\nPlease help restore access to our shared drive.",
        'subtype' => 'Other Request',
        'return_route' => 'dashboard',
        'metadata' => [
            'subject' => 'Need access to shared drive',
            'location' => 'Admin Office - Room 102',
            'urgency' => 'High (Impacting my work)',
            'issue_summary' => 'Please help restore access to our shared drive.',
            'formatted_details' => $formattedDetails,
        ],
    ]);

    $serviceRequest = ServiceRequest::query()->firstOrFail();
    $response->assertRedirect(route('dashboard'));
    expect($serviceRequest->requester_id)->toBe($user->id)
        ->and($serviceRequest->metadata)->toMatchArray([
            'subject' => 'Need access to shared drive',
            'location' => 'Admin Office - Room 102',
            'urgency' => 'High (Impacting my work)',
            'issue_summary' => 'Please help restore access to our shared drive.',
            'formatted_details' => $formattedDetails,
        ]);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('recentRequests.0.id', $serviceRequest->id)
            ->where('recentRequests.0.category.code', 'OTHERS')
            ->where('recentRequests.0.title', 'Need access to shared drive'));
});

test('account access requests persist and display validated bulk student details', function () {
    $user = User::factory()->create();
    $category = ServiceRequestCategory::create(['name' => 'Account & Access', 'code' => 'ACCESS', 'sort_order' => 1, 'is_active' => true]);
    $priority = ServiceRequestPriority::create(['name' => 'Normal', 'code' => 'NORMAL', 'sort_order' => 1, 'is_active' => true]);
    ServiceRequestStatus::create([
        'name' => 'Submitted',
        'slug' => 'submitted',
        'code' => 'SUBMITTED',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $this->actingAs($user)->post(route('service-requests.store'), [
        'title' => 'Bulk student email accounts',
        'service_request_category_id' => $category->id,
        'service_request_priority_id' => $priority->id,
        'description' => 'Create student email accounts for this batch.',
        'subtype' => 'New Email Request',
        'metadata' => [
            'account_type' => 'Student Email Account',
            'account_classification' => 'Student',
            'email_action' => 'New Email Request',
            'is_bulk' => true,
            'applicants' => [
                [
                    'id_number' => '2026-1001',
                    'first_name' => 'Alex',
                    'middle_name' => 'Jordan',
                    'last_name' => 'Rivera',
                    'suffix' => '',
                    'personal_email' => 'alex@example.com',
                    'generated_email' => 'wrong@example.net',
                    'status' => 'approved-client-value',
                    'department_office' => '',
                ],
                [
                    'id_number' => '2026-1002',
                    'first_name' => 'Sam',
                    'middle_name' => '',
                    'last_name' => 'Lee',
                    'suffix' => 'Jr.',
                    'personal_email' => 'sam@example.com',
                    'department_office' => '',
                ],
            ],
        ],
    ])->assertRedirect(route('service-requests.index'));

    $serviceRequest = ServiceRequest::query()->firstOrFail();
    expect($serviceRequest->requester_id)->toBe($user->id)
        ->and($serviceRequest->metadata['is_bulk'])->toBeTrue()
        ->and($serviceRequest->metadata['account_classification'])->toBe('Student')
        ->and($serviceRequest->metadata['applicants'])->toHaveCount(2)
        ->and($serviceRequest->metadata['applicants'][0])->toMatchArray([
            'id_number' => '2026-1001',
            'middle_name' => 'Jordan',
            'middle_initial' => 'J',
            'full_name' => 'Alex J. Rivera',
            'generated_email' => 'ajrivera@students.capsu.edu.ph',
            'status' => 'pending',
        ])
        ->and($serviceRequest->metadata['applicants'][1]['generated_email'])->toBe('sleejr@students.capsu.edu.ph');

    $misStaff = User::factory()->create(['role' => 'mis_staff']);
    $this->actingAs($misStaff)
        ->get(route('filament.admin.resources.service-requests.view', $serviceRequest))
        ->assertOk()
        ->assertSee('Bulk Account Applicants')
        ->assertSee('2026-1001')
        ->assertSee('Alex J. Rivera')
        ->assertSee('alex@example.com')
        ->assertSee('ajrivera@students.capsu.edu.ph')
        ->assertSee('pending');
});

test('single account access requests retain identity and generated email metadata', function () {
    $user = User::factory()->create();
    $category = ServiceRequestCategory::create(['name' => 'Account & Access', 'code' => 'ACCESS', 'sort_order' => 1, 'is_active' => true]);
    $priority = ServiceRequestPriority::create(['name' => 'Normal', 'code' => 'NORMAL', 'sort_order' => 1, 'is_active' => true]);
    ServiceRequestStatus::create([
        'name' => 'Submitted',
        'slug' => 'submitted',
        'code' => 'SUBMITTED',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)->post(route('service-requests.store'), [
        'title' => 'New student email account: Alex Rivera',
        'service_request_category_id' => $category->id,
        'service_request_priority_id' => $priority->id,
        'description' => 'Student Email Account (New Email Request) for Alex Rivera.',
        'subtype' => 'New Email Request',
        'return_route' => 'dashboard',
        'metadata' => [
            'account_type' => 'Student Email Account',
            'account_classification' => 'Student',
            'email_action' => 'New Email Request',
            'student_id' => '2026-1001',
            'first_name' => 'Alex',
            'middle_name' => 'Jordan',
            'middle_initial' => 'J',
            'last_name' => 'Rivera',
            'suffix' => '',
            'personal_email' => 'alex@example.com',
            'preferred_username' => 'ajrivera',
            'full_name' => 'Alex J. Rivera',
            'full_institutional_email' => 'ajrivera@students.capsu.edu.ph',
        ],
    ]);

    $serviceRequest = ServiceRequest::query()->firstOrFail();
    $response->assertRedirect(route('dashboard'));
    expect($serviceRequest->metadata)->toMatchArray([
        'account_type' => 'Student Email Account',
        'email_action' => 'New Email Request',
        'is_bulk' => false,
        'student_id' => '2026-1001',
        'middle_name' => 'Jordan',
        'middle_initial' => 'J',
        'full_name' => 'Alex J. Rivera',
        'preferred_username' => 'ajrivera',
        'full_institutional_email' => 'ajrivera@students.capsu.edu.ph',
    ]);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('recentRequests.0.id', $serviceRequest->id)
            ->where('recentRequests.0.category.code', 'ACCESS')
            ->where('recentRequests.0.title', 'New student email account: Alex Rivera')
            ->where('recentRequests.0.status_slug', 'submitted')
            ->where('stats.total', 1)
            ->where('success', "Service request {$serviceRequest->request_reference} was submitted successfully."));
});

test('faculty account recovery retains applicant identity and sanitizes the pasted domain', function () {
    $user = User::factory()->create();
    $category = ServiceRequestCategory::create(['name' => 'Account & Access', 'code' => 'ACCESS', 'sort_order' => 1, 'is_active' => true]);
    $priority = ServiceRequestPriority::create(['name' => 'Normal', 'code' => 'NORMAL', 'sort_order' => 1, 'is_active' => true]);
    ServiceRequestStatus::create([
        'name' => 'Submitted',
        'slug' => 'submitted',
        'code' => 'SUBMITTED',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $this->actingAs($user)->post(route('service-requests.store'), [
        'title' => 'Recover faculty email account',
        'service_request_category_id' => $category->id,
        'service_request_priority_id' => $priority->id,
        'description' => 'Recover my account.',
        'subtype' => 'Recover Account',
        'metadata' => [
            'account_type' => 'Faculty / Staff Email Account',
            'account_classification' => 'Faculty/Staff',
            'email_action' => 'Recover Account',
            'is_bulk' => false,
            'employee_id' => 'EMP-042',
            'first_name' => 'Jordan',
            'middle_initial' => 'A',
            'last_name' => 'Santos',
            'suffix' => '',
            'personal_email' => 'jordan@example.com',
            'capsu_email' => 'Jordan.Santos@CAPSU.edu.ph',
            'department_office' => 'Information Technology',
        ],
    ])->assertRedirect(route('service-requests.index'));

    $serviceRequest = ServiceRequest::query()->firstOrFail();
    expect($serviceRequest->metadata)->toMatchArray([
        'employee_id' => 'EMP-042',
        'first_name' => 'Jordan',
        'middle_initial' => 'A',
        'last_name' => 'Santos',
        'department_office' => 'Information Technology',
        'capsu_email_prefix' => 'jordan.santos',
        'domain_used' => '@capsu.edu.ph',
    ]);
});

test('bulk account access requests reject student rows with missing required values', function () {
    $user = User::factory()->create();
    $category = ServiceRequestCategory::create(['name' => 'Account & Access', 'code' => 'ACCESS', 'sort_order' => 1, 'is_active' => true]);
    $priority = ServiceRequestPriority::create(['name' => 'Normal', 'code' => 'NORMAL', 'sort_order' => 1, 'is_active' => true]);

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->post(route('service-requests.store'), [
            'title' => 'Bulk student email accounts',
            'service_request_category_id' => $category->id,
            'service_request_priority_id' => $priority->id,
            'description' => 'Create student email accounts for this batch.',
            'metadata' => [
                'account_type' => 'Student Email Account',
                'account_classification' => 'Student',
                'email_action' => 'New Email Request',
                'is_bulk' => true,
                'applicants' => [[
                    'id_number' => '2026-1001',
                    'first_name' => '',
                    'last_name' => 'Rivera',
                    'personal_email' => 'invalid-email',
                ]],
            ],
        ])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasErrors([
            'metadata.applicants.0.first_name',
            'metadata.applicants.0.personal_email',
        ]);

    expect(ServiceRequest::query()->count())->toBe(0);
});

test('bulk faculty and staff requests generate institutional addresses and show office details in Filament', function () {
    $user = User::factory()->create();
    $category = ServiceRequestCategory::create(['name' => 'Account & Access', 'code' => 'ACCESS', 'sort_order' => 1, 'is_active' => true]);
    $priority = ServiceRequestPriority::create(['name' => 'Normal', 'code' => 'NORMAL', 'sort_order' => 1, 'is_active' => true]);
    ServiceRequestStatus::create([
        'name' => 'Submitted',
        'slug' => 'submitted',
        'code' => 'SUBMITTED',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $this->actingAs($user)->post(route('service-requests.store'), [
        'title' => 'Bulk Faculty/Staff email accounts',
        'service_request_category_id' => $category->id,
        'service_request_priority_id' => $priority->id,
        'description' => 'Create personnel email accounts for this batch.',
        'subtype' => 'New Email Request',
        'metadata' => [
            'account_type' => 'Faculty / Staff Email Account',
            'account_classification' => 'Faculty/Staff',
            'email_action' => 'New Email Request',
            'is_bulk' => true,
            'applicants' => [[
                'id_number' => 'EMP-042',
                'first_name' => 'Jordan',
                'middle_initial' => 'A',
                'last_name' => 'Santos',
                'suffix' => '',
                'personal_email' => 'jordan@example.com',
                'department_office' => 'Information Technology',
                'generated_email' => 'client-value@students.capsu.edu.ph',
            ]],
        ],
    ])->assertRedirect(route('service-requests.index'));

    $serviceRequest = ServiceRequest::query()->firstOrFail();
    expect($serviceRequest->metadata['applicants'][0])->toMatchArray([
        'id_number' => 'EMP-042',
        'full_name' => 'Jordan A. Santos',
        'department_office' => 'Information Technology',
        'generated_email' => 'jasantos@capsu.edu.ph',
        'status' => 'pending',
    ]);

    $misStaff = User::factory()->create(['role' => 'mis_staff']);
    $this->actingAs($misStaff)
        ->get(route('filament.admin.resources.service-requests.view', $serviceRequest))
        ->assertOk()
        ->assertSee('Bulk Account Applicants')
        ->assertSee('EMP-042')
        ->assertSee('Jordan A. Santos')
        ->assertSee('jordan@example.com')
        ->assertSee('Information Technology')
        ->assertSee('jasantos@capsu.edu.ph');
});

test('Filament service request infolist renders nested metadata values as strings', function () {
    $requester = User::factory()->create();
    $category = ServiceRequestCategory::create(['name' => 'Network & Internet', 'code' => 'NETWORK', 'sort_order' => 1, 'is_active' => true]);
    $priority = ServiceRequestPriority::create(['name' => 'Normal', 'code' => 'NORMAL', 'sort_order' => 1, 'is_active' => true]);
    $status = ServiceRequestStatus::create([
        'name' => 'Submitted',
        'slug' => 'submitted',
        'code' => 'SUBMITTED',
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $serviceRequest = ServiceRequest::create([
        'request_reference' => 'SR-2026-ARRAY001',
        'requester_id' => $requester->id,
        'service_request_category_id' => $category->id,
        'service_request_priority_id' => $priority->id,
        'service_request_status_id' => $status->id,
        'title' => 'Network connectivity issue',
        'description' => 'The office cannot connect.',
        'metadata' => [
            'selected_symptoms' => ['No Internet Access', 'IP Address Conflict'],
            'equipment_needed' => ['Projector & Screen', 'Microphones'],
            'nested' => ['room' => 'Admin Office', 'floor' => 2],
        ],
    ]);
    $staff = User::factory()->create(['role' => 'mis_staff']);

    Livewire::actingAs($staff)
        ->test(ViewServiceRequest::class, ['record' => $serviceRequest->getRouteKey()])
        ->assertSee('["No Internet Access","IP Address Conflict"]')
        ->assertSee('["Projector & Screen","Microphones"]')
        ->assertSee('{"room":"Admin Office","floor":2}');
});

test('authenticated users can submit id requests with employee metadata and required image attachments', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $category = ServiceRequestCategory::create([
        'name' => 'ID Request',
        'code' => 'ID_REQUEST',
        'description' => 'ID requests',
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $priority = ServiceRequestPriority::create(['name' => 'Normal', 'code' => 'NORMAL', 'sort_order' => 1, 'is_active' => true]);
    $status = ServiceRequestStatus::create([
        'name' => 'Submitted',
        'slug' => 'submitted',
        'code' => 'SUBMITTED',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)->post(route('service-requests.store'), [
        'title' => 'New ID - Alex Rivera',
        'service_request_category_id' => $category->id,
        'service_request_priority_id' => $priority->id,
        'description' => 'ID request for Alex Rivera (New ID).',
        'contact_number' => '09171234567',
        'metadata' => [
            'id_request_type' => 'New ID',
            'id_first_name' => 'Alex',
            'id_middle_name' => 'Lee',
            'id_last_name' => 'Rivera',
            'id_suffix' => '',
            'id_position' => 'Instructor I',
            'id_office' => 'College of IT',
            'id_tin' => '000-000-000',
            'id_gsis' => '1234567890',
            'id_birthdate' => '1990-01-01',
            'id_emergency_name' => 'Taylor Rivera',
            'id_emergency_phone' => '09204762122',
            'id_emergency_address' => '123 Main Street',
        ],
        'attachments' => [
            UploadedFile::fake()->image('id-photo.jpg', 1200, 800),
            UploadedFile::fake()->image('signature.png', 1600, 800),
        ],
    ]);

    $serviceRequest = ServiceRequest::query()->with('attachments')->firstOrFail();

    $response->assertRedirect(route('service-requests.index'));
    expect($serviceRequest->metadata)->toMatchArray([
        'id_request_type' => 'New ID',
        'id_first_name' => 'Alex',
        'id_middle_name' => 'Lee',
        'id_last_name' => 'Rivera',
        'id_position' => 'Instructor I',
        'id_office' => 'College of IT',
        'id_tin' => '000-000-000',
        'id_gsis' => '1234567890',
        'id_birthdate' => '1990-01-01',
        'id_emergency_name' => 'Taylor Rivera',
        'id_emergency_phone' => '09204762122',
        'id_emergency_address' => '123 Main Street',
        'id_photo_path' => $serviceRequest->attachments[0]->file_path,
        'client_signature_path' => $serviceRequest->attachments[1]->file_path,
    ])->and($serviceRequest->service_request_status_id)->toBe($status->id)
        ->and($serviceRequest->attachments)->toHaveCount(2);

    foreach ($serviceRequest->attachments as $attachment) {
        Storage::disk('public')->assertExists($attachment->file_path);
    }

    $this->actingAs($user)
        ->get(route('service-requests.show', $serviceRequest))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ServiceRequests/Show')
            ->where('serviceRequest.category.code', 'ID_REQUEST')
            ->where('serviceRequest.subtype', 'New ID')
            ->where('serviceRequest.contact_number', '09171234567')
            ->where('serviceRequest.metadata.id_photo_url', Storage::disk('public')->url($serviceRequest->metadata['id_photo_path']))
            ->where('serviceRequest.metadata.client_signature_url', Storage::disk('public')->url($serviceRequest->metadata['client_signature_path']))
            ->has('serviceRequest.attachments', 2));

    $photo = $serviceRequest->attachments[0];
    $signature = $serviceRequest->attachments[1];
    $photoDimensions = getimagesize(Storage::disk('public')->path($photo->file_path));
    $signatureDimensions = getimagesize(Storage::disk('public')->path($signature->file_path));

    expect($photo->file_path)->toStartWith('service-requests/')
        ->and($photo->mime_type)->toBe('image/jpeg')
        ->and($photoDimensions[0])->toBe(600)
        ->and($photoDimensions[1])->toBe(600)
        ->and($signature->file_path)->toStartWith('service-requests/')
        ->and($signature->mime_type)->toBe('image/png')
        ->and($signatureDimensions[0])->toBe(800)
        ->and($signatureDimensions[1])->toBe(400)
        ->and($photo->size_bytes)->toBe(filesize(Storage::disk('public')->path($photo->file_path)))
        ->and($signature->size_bytes)->toBe(filesize(Storage::disk('public')->path($signature->file_path)));

    $staff = User::factory()->create(['role' => 'mis_staff']);

    Livewire::actingAs($staff)
        ->test(ViewServiceRequest::class, ['record' => $serviceRequest->getRouteKey()])
        ->assertSee('MIS Management')
        ->assertSee('Status')
        ->assertSee('Assigned Technician')
        ->assertSee('Resolution / Remarks')
        ->assertSee('2x2 ID Picture')
        ->assertSee('Digital Signature')
        ->assertSee(Storage::disk('public')->url($serviceRequest->metadata['id_photo_path']))
        ->assertSee(Storage::disk('public')->url($serviceRequest->metadata['client_signature_path']))
        ->assertSee('Actual ID Card Preview')
        ->assertSee(asset('images/id-template-front.png'))
        ->assertSee(asset('images/id-template-back.png'))
        ->assertSee('2026-XXXXXXX')
        ->assertSee('Alex L. Rivera')
        ->assertSee('Instructor I')
        ->assertSee('January 1, 1990')
        ->assertSee('000-000-000')
        ->assertSee('1234567890')
        ->assertSee('09204762122')
        ->assertDontSee('id_suffix')
        ->assertDontSee('subtype');

    $hr = User::factory()->create(['role' => 'hr']);
    $this->actingAs($hr)
        ->get(route('filament.admin.resources.service-requests.index'))
        ->assertOk();
    $this->get(route('filament.admin.resources.service-requests.view', $serviceRequest))
        ->assertOk();

    Livewire::actingAs($hr)
        ->test(ViewServiceRequest::class, ['record' => $serviceRequest->getRouteKey()])
        ->assertActionVisible('assignIdNumber')
        ->assertActionHidden('accept')
        ->assertActionHidden('complete')
        ->assertActionHidden('edit')
        ->assertSee('SAMPLE ONLY')
        ->callAction('assignIdNumber', ['id_number' => '2024-3030102']);

    expect($serviceRequest->fresh()->metadata['id_number'])->toBe('2024-3030102');

    $metadata = $serviceRequest->metadata;
    $metadata['id_number'] = 'CAPSU-2026-00123';
    $serviceRequest->update(['metadata' => $metadata]);

    Livewire::actingAs($staff)
        ->test(ViewServiceRequest::class, ['record' => $serviceRequest->getRouteKey()])
        ->assertSee('CAPSU-2026-00123')
        ->assertDontSee('2026-XXXXXXX');
});

test('only admin or HR can assign ID numbers on ID requests', function () {
    $misStaff = User::factory()->create(['role' => 'mis_staff']);
    $requester = User::factory()->create();
    $category = ServiceRequestCategory::create([
        'name' => 'ID Request',
        'code' => 'ID_REQUEST',
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $priority = ServiceRequestPriority::create([
        'name' => 'Normal',
        'code' => 'NORMAL',
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $request = ServiceRequest::create([
        'request_reference' => 'SR-2026-HRASSIGN1',
        'requester_id' => $requester->id,
        'service_request_category_id' => $category->id,
        'service_request_priority_id' => $priority->id,
        'title' => 'Employee ID',
        'description' => 'Issue staff ID card.',
        'metadata' => [],
    ]);

    Livewire::actingAs($misStaff)
        ->test(ViewServiceRequest::class, ['record' => $request->getRouteKey()])
        ->assertActionExists('assignIdNumber')
        ->assertActionHidden('assignIdNumber')
        ->assertSee('EMPLOYEE NAME')
        ->assertSee('2026-XXXXXXX')
        ->assertSee(asset('images/id-template-front.png'))
        ->assertSee(asset('images/id-template-back.png'));
});

test('HR can only access ID requests and cannot access other Filament resources', function () {
    $hr = User::factory()->create(['role' => 'hr']);
    $requester = User::factory()->create();
    $idCategory = ServiceRequestCategory::create([
        'name' => 'ID Request',
        'code' => 'ID_REQUEST',
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $otherCategory = ServiceRequestCategory::create([
        'name' => 'Hardware Support',
        'code' => 'HARDWARE',
        'sort_order' => 2,
        'is_active' => true,
    ]);
    $priority = ServiceRequestPriority::create([
        'name' => 'Normal',
        'code' => 'NORMAL',
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $idRequest = ServiceRequest::create([
        'request_reference' => 'SR-2026-HRIDENT01',
        'requester_id' => $requester->id,
        'service_request_category_id' => $idCategory->id,
        'service_request_priority_id' => $priority->id,
        'title' => 'Employee ID',
        'description' => 'Issue an employee ID.',
        'metadata' => [],
    ]);
    $otherRequest = ServiceRequest::create([
        'request_reference' => 'SR-2026-HRIDENT02',
        'requester_id' => $requester->id,
        'service_request_category_id' => $otherCategory->id,
        'service_request_priority_id' => $priority->id,
        'title' => 'Hardware issue',
        'description' => 'Repair a device.',
        'metadata' => [],
    ]);

    $this->actingAs($hr);

    expect(ServiceRequestResource::getEloquentQuery()->pluck('id')->all())
        ->toBe([$idRequest->id]);

    $this->get(route('filament.admin.resources.service-requests.index'))->assertOk();
    $this->get(route('filament.admin.resources.service-requests.view', $idRequest))->assertOk();
    $this->get(route('filament.admin.resources.service-requests.view', $otherRequest))->assertNotFound();
    $this->get(route('filament.admin.resources.service-requests.edit', $idRequest))->assertForbidden();

    foreach ([
        'service-request-categories',
        'service-request-priorities',
        'service-request-statuses',
        'users',
    ] as $resource) {
        $this->get(route("filament.admin.resources.{$resource}.index"))->assertForbidden();
        $this->get(route("filament.admin.resources.{$resource}.create"))->assertForbidden();
    }
});

test('id request validation enforces the TIN and GSIS formats', function () {
    $user = User::factory()->create();
    $category = ServiceRequestCategory::create([
        'name' => 'ID Request',
        'code' => 'ID_REQUEST',
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $priority = ServiceRequestPriority::create([
        'name' => 'Normal',
        'code' => 'NORMAL',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $this->actingAs($user)->post(route('service-requests.store'), [
        'title' => 'ID request',
        'service_request_category_id' => $category->id,
        'service_request_priority_id' => $priority->id,
        'description' => 'ID request validation.',
        'metadata' => [
            'id_request_type' => 'New',
            'id_first_name' => 'Alex',
            'id_middle_name' => '',
            'id_last_name' => 'Rivera',
            'id_suffix' => '',
            'id_position' => 'Staff',
            'id_office' => 'MIS',
            'id_tin' => '123456789',
            'id_gsis' => '12345678901',
            'id_birthdate' => '1990-01-01',
            'id_emergency_name' => 'Taylor Rivera',
            'id_emergency_phone' => '0920476212',
            'id_emergency_address' => '123 Main Street',
        ],
        'attachments' => [
            UploadedFile::fake()->image('photo.jpg'),
            UploadedFile::fake()->image('signature.png'),
        ],
    ])->assertSessionHasErrors([
        'metadata.id_tin',
        'metadata.id_gsis',
        'metadata.id_emergency_phone',
    ]);
});

test('requesters only see their own service requests', function () {
    $requester = User::factory()->create();
    $otherUser = User::factory()->create();
    $category = ServiceRequestCategory::create(['name' => 'Software', 'code' => 'SOFTWARE', 'sort_order' => 1, 'is_active' => true]);
    $priority = ServiceRequestPriority::create(['name' => 'Normal', 'code' => 'NORMAL', 'sort_order' => 1, 'is_active' => true]);

    ServiceRequest::create([
        'request_reference' => 'SR-2026-OWN00001',
        'requester_id' => $requester->id,
        'service_request_category_id' => $category->id,
        'service_request_priority_id' => $priority->id,
        'title' => 'My request',
        'description' => 'Visible to me',
    ]);
    ServiceRequest::create([
        'request_reference' => 'SR-2026-OTHER001',
        'requester_id' => $otherUser->id,
        'service_request_category_id' => $category->id,
        'service_request_priority_id' => $priority->id,
        'title' => 'Another request',
        'description' => 'Not visible to me',
    ]);

    $this->actingAs($requester)
        ->get(route('service-requests.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ServiceRequests/Index')
            ->has('serviceRequests.data', 1)
            ->where('serviceRequests.data.0.title', 'My request'));
});

test('dashboard shows personal request statistics and recent request tracking', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $category = ServiceRequestCategory::create(['name' => 'Hardware', 'code' => 'HARDWARE', 'sort_order' => 1, 'is_active' => true]);
    $priority = ServiceRequestPriority::create(['name' => 'Normal', 'code' => 'NORMAL', 'sort_order' => 1, 'is_active' => true]);
    $submitted = ServiceRequestStatus::create([
        'name' => 'Submitted',
        'slug' => 'submitted',
        'code' => 'SUBMITTED',
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $inProgress = ServiceRequestStatus::create([
        'name' => 'In Progress',
        'slug' => 'in-progress',
        'code' => 'IN-PROGRESS',
        'sort_order' => 2,
        'is_active' => true,
    ]);
    $resolved = ServiceRequestStatus::create([
        'name' => 'Resolved',
        'slug' => 'resolved',
        'code' => 'RESOLVED',
        'sort_order' => 3,
        'is_active' => true,
    ]);

    $requests = collect([$submitted, $inProgress, $resolved])->map(function (ServiceRequestStatus $status, int $index) use ($user, $category, $priority): ServiceRequest {
        return ServiceRequest::create([
            'request_reference' => 'SR-2026-DASH'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
            'requester_id' => $user->id,
            'service_request_category_id' => $category->id,
            'service_request_priority_id' => $priority->id,
            'service_request_status_id' => $status->id,
            'title' => "Request {$status->name}",
            'description' => "Tracking description for {$status->name}.",
            'metadata' => [
                'subtype' => 'Computer repair',
                'contact_number' => '09171234567',
                'device_serial' => $index === 0 ? 'ABC-123' : null,
            ],
            'submitted_at' => now()->subDays($index),
        ]);
    });
    $requests[0]->attachments()->create([
        'file_name' => 'diagnostic.pdf',
        'file_path' => 'attachments/2026/10/diagnostic.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 2048,
    ]);
    $otherRequest = ServiceRequest::create([
        'request_reference' => 'SR-2026-DASHOTHER',
        'requester_id' => $otherUser->id,
        'service_request_category_id' => $category->id,
        'service_request_priority_id' => $priority->id,
        'service_request_status_id' => $submitted->id,
        'title' => 'Another user request',
        'description' => 'This must not appear on the dashboard.',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('stats.total', 3)
            ->where('stats.pending', 1)
            ->where('stats.inProgress', 1)
            ->where('stats.completed', 1)
            ->has('recentRequests', 3)
            ->where('recentRequests.0.title', 'Request Submitted')
            ->where('recentRequests.0.status_slug', 'submitted')
            ->where('recentRequests', fn ($requests): bool => collect($requests)->every(
                fn (array $request): bool => $request['id'] !== $otherRequest->id,
            )));

    $this->get(route('service-requests.show', $requests[0]->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ServiceRequests/Show')
            ->where('serviceRequest.request_reference', $requests[0]->request_reference)
            ->where('serviceRequest.status', 'Submitted')
            ->where('serviceRequest.category.code', 'HARDWARE')
            ->where('serviceRequest.subtype', 'Computer repair')
            ->where('serviceRequest.contact_number', '09171234567')
            ->where('serviceRequest.metadata.device_serial', 'ABC-123')
            ->where('serviceRequest.attachments.0.file_name', 'diagnostic.pdf')
            ->where('serviceRequest.attachments.0.url', Storage::disk('public')->url('attachments/2026/10/diagnostic.pdf')));

    $this->get(route('service-requests.show', $otherRequest->id))->assertNotFound();
});

test('MIS staff can manage requests but cannot manage users', function () {
    $user = User::factory()->create(['role' => 'mis_staff']);
    $category = ServiceRequestCategory::create(['name' => 'Hardware', 'code' => 'HARDWARE', 'sort_order' => 1, 'is_active' => true]);
    $priority = ServiceRequestPriority::create(['name' => 'Normal', 'code' => 'NORMAL', 'sort_order' => 1, 'is_active' => true]);
    $request = ServiceRequest::create([
        'request_reference' => 'SR-2026-FILAMENT1',
        'requester_id' => $user->id,
        'service_request_category_id' => $category->id,
        'service_request_priority_id' => $priority->id,
        'title' => 'Admin resource check',
        'description' => 'Ensure the resource pages render.',
        'submitted_at' => now(),
    ]);

    $this->actingAs($user);

    $this->get(route('filament.admin.resources.service-requests.index'))->assertOk();
    $this->get(route('filament.admin.resources.service-requests.create'))->assertForbidden();
    $this->get(route('filament.admin.resources.service-requests.view', $request))->assertOk();
    $this->get(route('filament.admin.resources.service-requests.edit', $request))->assertOk();
    $this->get(route('filament.admin.resources.users.index'))->assertForbidden();
    $this->get(route('filament.admin.resources.users.create'))->assertForbidden();

    foreach ([
        'service-request-categories',
        'service-request-priorities',
        'service-request-statuses',
    ] as $resource) {
        $this->get(route("filament.admin.resources.{$resource}.index"))->assertOk();
        $this->get(route("filament.admin.resources.{$resource}.create"))->assertOk();
    }

    $administrator = User::factory()->create(['role' => 'admin']);
    $this->actingAs($administrator)
        ->get(route('filament.admin.resources.users.index'))
        ->assertOk();

    $this->actingAs(User::factory()->create())
        ->get(route('filament.admin.resources.service-requests.index'))
        ->assertForbidden();
});

test('Filament dashboard displays live service request and maintenance metrics', function () {
    $misStaff = User::factory()->create(['role' => 'mis_staff']);

    $this->actingAs($misStaff)
        ->get(route('filament.admin.pages.dashboard'))
        ->assertOk()
        ->assertSee('app.filament.widgets.requests-per-month-chart')
        ->assertSee('app.filament.widgets.requests-by-category-chart')
        ->assertSee('app.filament.widgets.requests-by-priority-chart')
        ->assertSee('app.filament.widgets.requests-by-department-chart')
        ->assertSee('app.filament.widgets.latest-service-requests')
        ->assertDontSee('Welcome')
        ->assertDontSee('Filament v');

    Livewire::actingAs($misStaff)
        ->test(StatsOverview::class)
        ->assertSee('Total Service Requests')
        ->assertSee('Pending Requests')
        ->assertSee('Preventive Maintenance')
        ->assertSee('Resolved Today')
        ->assertSee('All recorded requests')
        ->assertSee('Awaiting staff review')
        ->assertSee('Total checklists submitted');
});

test('Filament stats overview calculates resolution time and technician SLA attainment', function () {
    $this->seed(ServiceRequestLookupSeeder::class);
    $user = User::factory()->create(['role' => 'mis_staff']);
    $category = ServiceRequestCategory::query()->where('code', 'HARDWARE')->firstOrFail();
    $priority = ServiceRequestPriority::query()->where('name', 'Normal')->firstOrFail();
    $resolvedStatus = ServiceRequestStatus::query()->where('slug', 'resolved')->firstOrFail();
    $baseTime = now()->startOfDay();

    foreach ([['SR-2026-SLA001', 4], ['SR-2026-SLA002', 12]] as [$reference, $hours]) {
        $request = ServiceRequest::query()->create([
            'request_reference' => $reference,
            'requester_id' => $user->id,
            'assigned_staff_id' => $user->id,
            'service_request_category_id' => $category->id,
            'service_request_priority_id' => $priority->id,
            'service_request_status_id' => $resolvedStatus->id,
            'title' => 'SLA metric test',
            'description' => 'Verify resolution metric.',
        ]);

        \Illuminate\Support\Facades\DB::table('service_requests')
            ->where('id', $request->id)
            ->update([
                'created_at' => $baseTime,
                'updated_at' => $baseTime->copy()->addHours($hours),
            ]);
    }

    Livewire::actingAs($user)
        ->test(StatsOverview::class)
        ->assertSee('Average Resolution Time')
        ->assertSee('8.0 Hours')
        ->assertSee('Technician SLA Attainment Rate')
        ->assertSee('50.0%')
        ->assertSee('Assigned requests resolved within 8 hours');
});

test('Filament command center charts and latest requests table show service request data', function () {
    $this->seed(ServiceRequestLookupSeeder::class);
    $user = User::factory()->create(['role' => 'mis_staff']);
    $category = ServiceRequestCategory::query()->where('code', 'HARDWARE')->firstOrFail();
    $priority = ServiceRequestPriority::query()->where('name', 'Normal')->firstOrFail();
    $submittedStatus = ServiceRequestStatus::query()->where('slug', 'submitted')->firstOrFail();

    ServiceRequest::query()->create([
        'request_reference' => 'SR-2026-CMDCTR1',
        'requester_id' => $user->id,
        'service_request_category_id' => $category->id,
        'service_request_priority_id' => $priority->id,
        'service_request_status_id' => $submittedStatus->id,
        'title' => 'Chart test request',
        'description' => 'Verify chart metrics.',
        'submitted_at' => now(),
    ]);

    Livewire::actingAs($user)
        ->test(RequestsPerMonthChart::class)
        ->assertSee('Monthly Service Request Trend')
        ->set('filter', 'last_year');

    Livewire::actingAs($user)
        ->test(RequestsByCategoryChart::class)
        ->assertSee('Service Requests by Category');

    Livewire::actingAs($user)
        ->test(LatestServiceRequests::class)
        ->assertTableActionExists('view')
        ->mountTableAction('view', ServiceRequest::query()->where('request_reference', 'SR-2026-CMDCTR1')->firstOrFail())
        ->assertTableActionMounted('view')
        ->assertSee('Latest Service Requests')
        ->assertSee('SR-2026-CMDCTR1')
        ->assertSee('Chart test request')
        ->assertSee('Description')
        ->assertSee('Resolution / Remarks')
        ->assertSee('Hardware')
        ->assertSee('Submitted');
});

test('Filament priority and department charts group real request data', function () {
    $this->seed(ServiceRequestLookupSeeder::class);
    $user = User::factory()->create(['role' => 'mis_staff']);
    $category = ServiceRequestCategory::query()->where('code', 'HARDWARE')->firstOrFail();
    $submittedStatus = ServiceRequestStatus::query()->where('slug', 'submitted')->firstOrFail();

    foreach ([
        ['Urgent', ['department_office' => 'MIS Office']],
        ['High', ['dept_office' => 'Library']],
        ['Low', ['id_office' => 'MIS Office']],
        ['Normal', []],
    ] as $index => [$priorityName, $metadata]) {
        $priority = ServiceRequestPriority::query()->where('name', $priorityName)->firstOrFail();

        ServiceRequest::query()->create([
            'request_reference' => sprintf('SR-2026-CHART%04d', $index + 1),
            'requester_id' => $user->id,
            'service_request_category_id' => $category->id,
            'service_request_priority_id' => $priority->id,
            'service_request_status_id' => $submittedStatus->id,
            'title' => 'Chart breakdown request',
            'description' => 'Verify dashboard grouping.',
            'metadata' => $metadata,
        ]);
    }

    $readChartData = function (string $widgetClass) use ($user): array {
        $widget = Livewire::actingAs($user)->test($widgetClass)->instance();
        $method = new ReflectionMethod($widgetClass, 'getData');
        $method->setAccessible(true);

        return $method->invoke($widget);
    };
    $readChartOptions = function (string $widgetClass) use ($user): array {
        $widget = Livewire::actingAs($user)->test($widgetClass)->instance();
        $method = new ReflectionMethod($widgetClass, 'getOptions');
        $method->setAccessible(true);

        return $method->invoke($widget);
    };

    $priorityData = $readChartData(RequestsByPriorityChart::class);
    expect($priorityData['labels'])->toContain('Urgent', 'High', 'Normal', 'Low')
        ->and(array_sum($priorityData['datasets'][0]['data']))->toBe(4);

    $expectedDoughnutOptions = [
        'scales' => [
            'x' => ['display' => false],
            'y' => ['display' => false],
        ],
        'plugins' => [
            'legend' => ['position' => 'bottom'],
        ],
    ];

    expect($readChartOptions(RequestsByCategoryChart::class))
        ->toBe($expectedDoughnutOptions)
        ->and($readChartOptions(RequestsByPriorityChart::class))
        ->toBe($expectedDoughnutOptions);

    $departmentData = $readChartData(RequestsByDepartmentChart::class);
    expect($departmentData['labels'])->toContain('MIS Office', 'Library', 'Not specified')
        ->and(array_sum($departmentData['datasets'][0]['data']))->toBe(4);
});

test('admins and MIS technicians can manage maintenance checklists while other roles cannot access them', function () {
    foreach (['admin', 'mis', 'mis_staff', 'technician'] as $role) {
        $user = User::factory()->create(['role' => $role]);
        $this->actingAs($user);

        $checklist = new MaintenanceChecklist();
        expect(MaintenanceChecklistResource::canViewAny())->toBeTrue()
            ->and(MaintenanceChecklistResource::canCreate())->toBeTrue()
            ->and(MaintenanceChecklistResource::canView($checklist))->toBeTrue()
            ->and(MaintenanceChecklistResource::canEdit($checklist))->toBeTrue()
            ->and(MaintenanceChecklistResource::canDelete($checklist))->toBeTrue()
            ->and(MaintenanceChecklistResource::canDeleteAny())->toBeTrue();

        $this->get(route('filament.admin.resources.maintenance-checklists.index'))
            ->assertOk()
            ->assertSee('Preventive Maintenance')
            ->assertSee('Operations & Maintenance');
        $this->get(route('filament.admin.resources.maintenance-checklists.create'))->assertOk();
    }

    $adminFlaggedUser = User::factory()->create(['role' => 'user', 'is_admin' => true]);
    $this->actingAs($adminFlaggedUser);

    expect(MaintenanceChecklistResource::canViewAny())->toBeTrue();
    $this->get(route('filament.admin.resources.maintenance-checklists.index'))->assertOk();

    foreach (['hr', 'user'] as $role) {
        $user = User::factory()->create(['role' => $role]);
        $this->actingAs($user);

        expect(MaintenanceChecklistResource::canViewAny())->toBeFalse();

        $this->get(route('filament.admin.resources.maintenance-checklists.index'))->assertForbidden();
        $this->get(route('filament.admin.resources.maintenance-checklists.create'))->assertForbidden();
    }
});

test('MIS staff can create a maintenance checklist with structured task statuses and remarks', function () {
    $this->seed(LocationSeeder::class);
    $this->seed(DepartmentSeeder::class);
    $misStaff = User::factory()->create(['role' => 'mis_staff']);
    $technician = User::factory()->create(['role' => 'mis_staff']);
    $location = Location::query()->where('name', 'Central Administration')->firstOrFail();
    $department = Department::query()
        ->where('location_id', $location->id)
        ->where('name', 'MIS Office')
        ->firstOrFail();
    $checklistItems = [
        'system_boot' => ['cold_start'],
        'system_log_in' => ['login_errors'],
        'network_settings' => ['tcp_ip', 'domain_name', 'security_settings', 'client_configurations', 'computer_name'],
        'computer_hardware_settings' => ['device_manager', 'bios', 'hard_disk', 'drive_firmware', 'memory', 'battery_runtime'],
        'browser_proxy_settings' => ['settings_operation'],
        'proper_software_loads' => ['required_software', 'windows_update', 'disk_defragmenter', 'backup_data'],
        'viruses_and_malware' => ['antivirus_installed', 'scan_disk'],
        'clearance' => ['unused_software', 'disk_cleanup', 'temporary_files', 'recycle_bin_caches', 'periphery_devices'],
        'interiors_and_cleaning' => ['dust_removed', 'loose_parts', 'airflow', 'cables', 'fans'],
        'peripheral_devices' => ['mouse', 'monitor', 'ups', 'printer'],
    ];
    $checklistData = collect($checklistItems)
        ->map(fn (array $checks): array => collect($checks)
            ->mapWithKeys(fn (string $check): array => [
                $check => [
                    'description' => $check,
                    'status' => 'OK',
                    'remarks' => 'Passed',
                ],
            ])
            ->all())
        ->all();

    Livewire::actingAs($misStaff)
        ->test(CreateMaintenanceChecklist::class)
        ->fillForm([
            'pc_name' => 'MIS-PC-04',
            'serial_no' => 'SN-2026-04',
            'end_user' => 'Alex User',
            'location_id' => $location->id,
            'department_id' => $department->id,
            'technician_id' => $technician->id,
            'scheduled_at' => '2026-10-20',
            'checklist_data' => $checklistData,
            'notes' => 'Routine preventive maintenance completed.',
            'other' => 'Additional observation.',
            'technician_signature' => 'MIS Technician',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $checklist = MaintenanceChecklist::query()->firstOrFail();

    expect($checklist->checklist_data)
        ->toHaveCount(10)
        ->and($checklist->checklist_data['system_boot']['cold_start']['status'])
        ->toBe('OK')
        ->and($checklist->other)
        ->toBe('Additional observation.')
        ->and($checklist->technician_signature)
        ->toBe('MIS Technician')
        ->and($checklist->scheduled_at->toDateString())
        ->toBe('2026-10-20')
        ->and($checklist->technician_id)
        ->toBe($technician->id)
        ->and($checklist->location_id)
        ->toBe($location->id)
        ->and($checklist->department_id)
        ->toBe($department->id)
        ->and($checklist->office)
        ->toBe('MIS Office');
});

test('MIS staff can accept an unassigned request and assign it to themselves', function () {
    $staff = User::factory()->create(['role' => 'mis_staff']);
    $requester = User::factory()->create();
    $category = ServiceRequestCategory::create(['name' => 'Hardware', 'code' => 'HARDWARE', 'sort_order' => 1, 'is_active' => true]);
    $priority = ServiceRequestPriority::create(['name' => 'Normal', 'code' => 'NORMAL', 'sort_order' => 1, 'is_active' => true]);
    $submitted = ServiceRequestStatus::create([
        'name' => 'Submitted',
        'slug' => 'submitted',
        'code' => 'SUBMITTED',
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $inProgress = ServiceRequestStatus::create([
        'name' => 'In Progress',
        'slug' => 'in-progress',
        'code' => 'IN-PROGRESS',
        'sort_order' => 2,
        'is_active' => true,
    ]);
    $request = ServiceRequest::create([
        'request_reference' => 'SR-2026-ACCEPT001',
        'requester_id' => $requester->id,
        'service_request_category_id' => $category->id,
        'service_request_priority_id' => $priority->id,
        'service_request_status_id' => $submitted->id,
        'title' => 'Needs technician',
        'description' => 'Please accept this request.',
    ]);

    Livewire::actingAs($staff)
        ->test(ListServiceRequests::class)
        ->assertTableActionDoesNotExist('accept')
        ->assertTableActionDoesNotExist('complete');

    Livewire::actingAs($staff)
        ->test(ViewServiceRequest::class, ['record' => $request->getRouteKey()])
        ->assertActionExists('edit')
        ->assertActionVisible('accept')
        ->callAction('accept');

    $request->refresh();

    expect($request->assigned_staff_id)->toBe($staff->id)
        ->and($request->service_request_status_id)->toBe($inProgress->id);
});

test('MIS staff can complete an in-progress request with required resolution remarks', function () {
    $staff = User::factory()->create(['role' => 'mis_staff']);
    $requester = User::factory()->create();
    $category = ServiceRequestCategory::create(['name' => 'Hardware', 'code' => 'HARDWARE', 'sort_order' => 1, 'is_active' => true]);
    $priority = ServiceRequestPriority::create(['name' => 'Normal', 'code' => 'NORMAL', 'sort_order' => 1, 'is_active' => true]);
    $inProgress = ServiceRequestStatus::create([
        'name' => 'In Progress',
        'slug' => 'in-progress',
        'code' => 'IN-PROGRESS',
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $resolved = ServiceRequestStatus::create([
        'name' => 'Resolved',
        'slug' => 'resolved',
        'code' => 'RESOLVED',
        'sort_order' => 2,
        'is_active' => true,
    ]);
    $request = ServiceRequest::create([
        'request_reference' => 'SR-2026-COMPLETE1',
        'requester_id' => $requester->id,
        'assigned_staff_id' => $staff->id,
        'service_request_category_id' => $category->id,
        'service_request_priority_id' => $priority->id,
        'service_request_status_id' => $inProgress->id,
        'title' => 'Repair laptop',
        'description' => 'Laptop needs repair.',
    ]);

    Livewire::actingAs($staff)
        ->test(ViewServiceRequest::class, ['record' => $request->getRouteKey()])
        ->assertActionVisible('complete')
        ->callAction('complete', [
            'remarks' => 'Replaced the faulty power adapter and verified operation.',
        ]);

    $request->refresh();

    expect($request->service_request_status_id)->toBe($resolved->id)
        ->and($request->resolution_summary)->toBe('Replaced the faulty power adapter and verified operation.');
});
