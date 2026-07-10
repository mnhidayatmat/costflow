<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\WccRecord;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * The demo accounts and records carried over from the prototype, so the
     * dashboard and analytics pages have something to draw on first run.
     */
    public function run(): void
    {
        $users = collect([
            ['Mimi Nor Zalikha', 'admin', User::ROLE_IT, '0123456789'],
            ['Datuk Ir. Ts. Mohd Isnari B. Idris', 'isnari', User::ROLE_MANAGEMENT, '0129876543'],
            ['Ira Lee', 'ira.lee', User::ROLE_MANAGEMENT, '0132223344'],
            ['Encik Alfi', 'alfi', User::ROLE_ENGINEER, '0175556677'],
        ])->mapWithKeys(function (array $row) {
            [$name, $handle, $role, $phone] = $row;

            $user = User::firstOrCreate(
                ['email' => $handle.'@'.config('costflow.email_domain')],
                [
                    'name' => $name,
                    'role' => $role,
                    'phone' => $phone,
                    'password' => 'Costflow@123',
                    'email_verified_at' => now(),
                ]
            );

            return [$handle => $user];
        });

        $engineer = $users['alfi'];

        if (WccRecord::exists()) {
            $this->command->info('WCC records already present — skipping demo records.');

            return;
        }

        // [quo, client, title, dept, manager, cost, selling, actual, status, months ago]
        $demo = [
            ['BPE-Q-2603', 'Petronas Carigali', 'Subsea cable IR test & termination', 'Subsea Cable', 'AZWAN', 48200, 69890, 47100, WccRecord::APPROVED, 0],
            ['BPE-Q-2599', 'TNB Grid', 'Substation battery bank replacement', 'UPS & Battery', 'AZIZI', 23100, 33500, 0, WccRecord::APPROVED, 0],
            ['BPE-Q-2597', 'MISC Marine', 'Diesel generator overhaul 850kVA', 'Diesel Generator', 'OMAR', 36150, 52420, 35800, WccRecord::APPROVED, 1],
            ['BPE-Q-2591', 'Petronas Gas', 'CCVT calibration campaign', 'CCVT', 'AZIZI', 15400, 22330, 14950, WccRecord::APPROVED, 2],
            ['BPE-Q-2606', 'Sarawak Energy', 'Instrument loop checking Pkg 2', 'Instrument', 'ALFI', 19800, 28710, 0, WccRecord::SUBMITTED, 0],
            ['BPE-Q-2607', 'Petronas Carigali', 'NWK 99 comms rack upgrade', 'NWK 99', 'ALFI', 8900, 12905, 0, WccRecord::COSTED, 0],
            ['BPE-Q-2608', 'TNB Repair', 'MV switchboard preventive maintenance', 'Electrical', 'SHAHIR', 0, 0, 0, WccRecord::DRAFT, 0],
            ['BPE-Q-2589', 'Vestigo', 'Offshore electrical fault finding', 'Electrical', 'OMAR', 27600, 40020, 0, WccRecord::RETURNED, 1],
            ['BPE-Q-2584', 'MMHE', 'Subsea cable spare mobilization', 'Subsea Cable', 'AZWAN', 31200, 45240, 30490, WccRecord::APPROVED, 3],
            ['BPE-Q-2580', 'TNB Grid', 'Protection relay testing', 'Electrical', 'IRA LEE', 12750, 18490, 12300, WccRecord::APPROVED, 4],
        ];

        foreach ($demo as [$quo, $client, $title, $dept, $manager, $cost, $sell, $actual, $status, $monthsAgo]) {
            $at = Carbon::now()->subMonths($monthsAgo)->setDay(random_int(6, 24));

            // Never date a demo record into the future — the current month is
            // only part-elapsed, so a random day can overshoot today.
            $at = $at->min(Carbon::now());

            $record = WccRecord::create([
                'quo_no' => $quo,
                'client' => $client,
                'title' => $title,
                'dept' => $dept,
                'manager' => $manager,
                'planned_cost' => $cost,
                'selling' => $sell,
                'actual' => $actual,
                'snapshot' => null,
            ]);

            // status and timestamps are guarded, so set them after creation
            $record->forceFill([
                'status' => $status,
                'approved_at' => $status === WccRecord::APPROVED ? $at : null,
                'created_by' => $engineer->id,
                'created_at' => $at,
                'updated_at' => $at,
            ])->save();

            $record->histories()->create([
                'user_id' => $engineer->id,
                'from_status' => null,
                'to_status' => $status,
                'created_at' => $at,
            ]);
        }

        AuditLog::create([
            'actor' => 'system',
            'action' => 'System initialized',
            'detail' => 'COSTFLOW v'.config('costflow.version').' first run — demo data seeded',
        ]);

        $this->command->info('Seeded '.count($demo).' WCC records and '.$users->count().' users (password: Costflow@123).');
    }
}
