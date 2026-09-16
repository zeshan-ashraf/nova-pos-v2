<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BankSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        $banks = [
            ['name' => 'National Bank of Pakistan (NBP)'],
            ['name' => 'Habib Bank Limited (HBL)'],
            ['name' => 'United Bank Limited (UBL)'],
            ['name' => 'MCB Bank Limited'],
            ['name' => 'Allied Bank Limited (ABL)'],
            ['name' => 'Askari Bank Limited'],
            ['name' => 'Bank Alfalah Limited'],
            ['name' => 'Bank AL Habib Limited'],
            ['name' => 'Meezan Bank Limited (Islamic)'],
            ['name' => 'Faysal Bank Limited'],
            ['name' => 'JS Bank Limited'],
            ['name' => 'Soneri Bank Limited'],
            ['name' => 'Habib Metropolitan Bank (HabibMetro)'],
            ['name' => 'The Bank of Punjab (BoP)'],
            ['name' => 'The Bank of Khyber (BoK)'],
            ['name' => 'Meezan Bank (largest)'],
            ['name' => 'BankIslami'],
            ['name' => 'Dubai Islamic Bank Pakistan'],
            ['name' => 'AlBaraka Bank (Pakistan)'],
            ['name' => 'Standard Chartered Bank (Pakistan)'],
            ['name' => 'Citibank N.A.'],
            ['name' => 'Deutsche Bank AG'],
            ['name' => 'Industrial and Commercial Bank of China (ICBC)'],
            ['name' => 'First Women Bank (Public Sector)'],
            ['name' => 'Samba Bank'],
            ['name' => 'Sindh Bank'],
            ['name' => 'Zarai Taraqiati Bank (ZTBL)'],
        ];

        foreach ($banks as $bank) {
            DB::table('banks')->updateOrInsert(
                ['name' => $bank['name']],
                ['created_at' => $now, 'updated_at' => $now]
            );
        }
    }
}
