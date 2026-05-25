<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['name' => 'Development Department', 'code' => 'DEV'],
            ['name' => 'AI Department', 'code' => 'AI'],
            ['name' => 'Marketing Department', 'code' => 'MKT'],
            ['name' => 'Designing Department', 'code' => 'DES'],
            ['name' => 'Sales Department', 'code' => 'SAL'],
            ['name' => 'Operation Department', 'code' => 'OPR'],
            ['name' => 'HR Department', 'code' => 'HR'],
            ['name' => 'Quality Assurance Department', 'code' => 'QA'],
            ['name' => 'Management Department', 'code' => 'MGT'],
        ];

        $codes = [];

        foreach ($departments as $department) {
            Department::updateOrCreate(['code' => $department['code']], $department);
            $codes[] = $department['code'];
        }

        Department::query()
            ->whereNotIn('code', $codes)
            ->whereDoesntHave('employees')
            ->delete();
    }
}
