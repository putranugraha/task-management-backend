<?php

namespace Database\Seeders;

use App\Models\Division;
use App\Models\User;
use Illuminate\Database\Seeder;

class DivisionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $divisions = [
            ['code' => 'OPS', 'name' => 'Operations', 'description' => 'Mengelola operasional harian dan proses bisnis.'],
            ['code' => 'HR', 'name' => 'Human Resources', 'description' => 'Mengurus perekrutan, pelatihan, serta kesejahteraan karyawan.'],
            ['code' => 'FIN', 'name' => 'Finance', 'description' => 'Bertanggung jawab atas perencanaan dan laporan keuangan.'],
            ['code' => 'MKT', 'name' => 'Marketing', 'description' => 'Fokus pada strategi pemasaran dan komunikasi brand.'],
            ['code' => 'IT', 'name' => 'Information Technology', 'description' => 'Menangani infrastruktur teknologi dan pengembangan sistem.'],
        ];

        foreach ($divisions as $division) {
            Division::updateOrCreate(
                ['code' => $division['code']],
                ['name' => $division['name'], 'description' => $division['description']]
            );
        }

        // Tambah data acak bila dibutuhkan sebagai sampel tambahan
        Division::factory()->count(3)->create();

        $allDivisions = Division::all()->keyBy('code');

        $preferredAssignments = [
            'admin@example.com' => 'OPS',
            'manager@example.com' => 'HR',
            'member@example.com' => 'MKT',
        ];

        foreach ($preferredAssignments as $email => $divisionCode) {
            $user = User::where('email', $email)->first();
            if ($user && $allDivisions->has($divisionCode)) {
                $user->division()->associate($allDivisions->get($divisionCode));
                $user->save();
            }
        }

        $divisionsCollection = Division::all();
        User::whereNull('division_id')->get()->each(function (User $user) use ($divisionsCollection) {
            if ($divisionsCollection->isNotEmpty()) {
                $user->division()->associate($divisionsCollection->random());
                $user->save();
            }
        });
    }
}

