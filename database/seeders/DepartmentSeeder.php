<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Location;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            'MIS Office',
            'Records',
            'Planning',
            'PMO',
            'Office of the President',
            'Legal Office',
            'Board Secretary',
            'CAO',
            'VP - Academic Affairs',
            'VP - Administration and Finance',
            'VP - Research, Development and Extension',
            'VP - External Affairs and Linkages',
            'HRMO',
            'Accounting',
            'Budget',
            'Cashier',
            'Internal Audit Service',
            'Supply',
            'QA',
            'OSA',
            'QMS',
            'GAD',
            'GSU',
            'Sports',
            'DRRM',
            'UBAO',
            'Landholdings',
            'BAC',
            'IPMO',
            'SUC President',
            'Public Information Office',
        ];

        $centralAdministration = Location::query()
            ->where('name', 'Central Administration')
            ->firstOrFail();

        foreach ($departments as $department) {
            Department::updateOrCreate(
                [
                    'name' => $department,
                    'location_id' => $centralAdministration->id,
                ],
                [],
            );
        }
    }
}
