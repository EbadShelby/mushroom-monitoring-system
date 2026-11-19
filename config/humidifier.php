<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mist Output Rate
    |--------------------------------------------------------------------------
    |
    | Estimated mist output of ONE humidifier unit in millilitres per second.
    |
    */

    'mist_rate_ml_per_second' => (float) env('HUMIDIFIER_MIST_RATE', 0.013),

    /*
    |--------------------------------------------------------------------------
    | Humidifier Units
    |--------------------------------------------------------------------------
    |
    | Number of humidifier units wired to the single humidifier relay. They
    | switch on and off together, each with its own tank.
    |
    */

    'unit_count' => (int) env('HUMIDIFIER_UNIT_COUNT', 4),

    /*
    |--------------------------------------------------------------------------
    | Humidity Control Margins
    |--------------------------------------------------------------------------
    |
    | The humidifier switches ON at (humidity_low + on margin) and OFF at
    | (humidity_high - off margin), so humidity is corrected before it leaves
    | the target range. Example (75-95%) with margins 3 and 1:
    | ON at 78% or below, OFF at 94% or above.
    |
    */

    'humidity_on_margin' => (float) env('HUMIDIFIER_HUMIDITY_ON_MARGIN', 3),

    'humidity_off_margin' => (float) env('HUMIDIFIER_HUMIDITY_OFF_MARGIN', 1),

    /*
    |--------------------------------------------------------------------------
    | Usable Tank Capacity
    |--------------------------------------------------------------------------
    |
    | Usable water in millilitres per refill of ONE unit's tank. The physical tank holds more,
    | but the intake stick cannot reach the last portion.
    |
    */

    'tank_capacity_ml' => (float) env('HUMIDIFIER_TANK_CAPACITY', 300),

    /*
    |--------------------------------------------------------------------------
    | Low Tank Warning
    |--------------------------------------------------------------------------
    |
    | Remaining water percentage at or below which an SMS warning is sent,
    | and how many minutes to wait before sending it again.
    |
    */

    'low_tank_percent' => (int) env('HUMIDIFIER_LOW_TANK_PERCENT', 10),

    'low_tank_alert_cooldown_minutes' => 180,

];
