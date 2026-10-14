<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Demo accounts mirror seed_demo_users() in legacy-api/bootstrap.php so the
     * login page credentials stay the same through both entry points. The dean
     * and program_chair accounts need a college (and programs for the chair)
     * because their rows carry a mandatory college_id foreign key.
     */
    public function run(): void
    {
        $cas = DB::table('colleges')->insertGetId([
            'college_code' => 'CAS',
            'college_name' => 'College of Arts and Sciences',
            'is_active' => true,
        ]);

        $chairPrograms = [];
        foreach ([['CS', 'Computer Science'], ['IT', 'Information Technology'], ['EMC', 'EMC']] as [$code, $name]) {
            $chairPrograms[] = DB::table('programs')->insertGetId([
                'college_id' => $cas,
                'program_code' => $code,
                'program_name' => $name,
                'represents_college' => false,
                'normal_years' => 4,
                'is_active' => true,
            ]);
        }

        $demoUsers = [
            ['Ryan Registrar', 'admin@gmail.com', '12345', 'super_admin', null, []],
            ['Records Officer', 'admin2@gmail.com', '123452', 'admin', null, []],
            ['VPAA Viewer', 'viewing2@gmail.com', 'qwerty2', 'vpaa', null, []],
            ['CAS Dean', 'viewing@gmail.com', 'qwerty', 'dean', $cas, []],
            ['CS / IT / EMC Chair', 'viewingph@gmail.com', 'asdfg', 'program_chair', $cas, $chairPrograms],
        ];

        foreach ($demoUsers as [$name, $email, $password, $role, $collegeId, $programIds]) {
            $user = User::factory()->create([
                'full_name' => $name,
                'email' => $email,
                'password_hash' => Hash::make($password),
                'role' => $role,
                'college_id' => $collegeId,
                'is_active' => true,
            ]);
            foreach ($programIds as $programId) {
                DB::table('user_programs')->insert([
                    'user_id' => $user->user_id,
                    'program_id' => $programId,
                ]);
            }
        }
    }
}
