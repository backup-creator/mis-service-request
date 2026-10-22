<?php

namespace Database\Seeders;

use App\Models\ServiceRequestCategory;
use App\Models\ServiceRequestPriority;
use App\Models\ServiceRequestStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ServiceRequestLookupSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
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
        ];

        foreach ($categories as $order => $category) {
            ServiceRequestCategory::updateOrCreate(
                ['code' => $category['code']],
                [
                    ...$category,
                    'sort_order' => $order + 1,
                    'is_active' => true,
                ],
            );
        }

        ServiceRequestCategory::query()
            ->whereNotIn('code', array_column($categories, 'code'))
            ->update(['is_active' => false]);

        foreach (ServiceRequestCategory::query()->whereIn('name', ['Hardware', 'Software', 'Network', 'Account Access', 'Other'])->get() as $legacyCategory) {
            $legacyCategory->update(['is_active' => false]);
        }

        foreach (['Low', 'Normal', 'High', 'Urgent'] as $order => $name) {
            ServiceRequestPriority::updateOrCreate(
                ['name' => $name],
                [
                    'code' => Str::upper(Str::slug($name)),
                    'sort_order' => $order + 1,
                    'is_active' => true,
                ],
            );
        }

        foreach (['Submitted', 'Accepted', 'In Progress', 'Resolved', 'Closed'] as $order => $name) {
            ServiceRequestStatus::updateOrCreate(
                ['slug' => str($name)->slug()],
                [
                    'name' => $name,
                    'code' => Str::upper(Str::slug($name)),
                    'sort_order' => $order + 1,
                    'is_active' => true,
                ],
            );
        }
    }
}
