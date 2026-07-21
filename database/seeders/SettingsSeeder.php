<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            ['key' => 'temp_min', 'value' => '24'],
            ['key' => 'temp_max', 'value' => '30'],
            ['key' => 'humidity_min', 'value' => '80'],
            ['key' => 'humidity_max', 'value' => '95'],
            ['key' => 'co2_max', 'value' => '1000'],
            ['key' => 'soil_dry', 'value' => '30'],
            ['key' => 'soil_critical', 'value' => '20'],
            ['key' => 'led_on_time', 'value' => '06:00'],
            ['key' => 'led_off_time', 'value' => '18:00'],
            ['key' => 'sms_cooldown', 'value' => '15'],
            ['key' => 'sms_recipients', 'value' => ''],
            ['key' => 'system_name', 'value' => 'IoT Mushroom Monitoring System'],
            ['key' => 'school_name', 'value' => 'Cotabato State University'],
            ['key' => 'snapshot_note', 'value' => 'Upload daily growth photos'],
        ];

        foreach ($settings as $setting) {
            Setting::firstOrCreate(
                ['key' => $setting['key']],
                ['value' => $setting['value']]
            );
        }
    }
}
