export interface GrowingCycle {
    id: number;
    name: string;
    mushroom_variety: string;
    substrate_type: string;
    start_date: string;
    end_date: string | null;
    status: 'active' | 'completed' | 'cancelled';
    notes: string | null;
    day_count?: number;
}

export interface CameraSnapshot {
    id: number;
    growing_cycle_id: number;
    file_path: string;
    file_name: string;
    captured_at?: string;
    captured_date?: string;
    flush_number?: number;
    notes?: string | null;
    uploaded_by?: number;
    growing_cycle?: { id: number; name: string };
}

export interface AlertLog {
    id: number;
    sensor: string;
    value_at_alert: number;
    threshold_exceeded: string;
    recipient_number: string;
    message: string;
    status: string;
    sent_at: string;
}

export interface ActuatorLog {
    id: number;
    actuator: string;
    action: 'on' | 'off';
    trigger: 'auto' | 'manual' | 'schedule';
    triggered_by: string | null;
    triggered_at: string;
}

export interface UserLog {
    id: number;
    user_id: number;
    user?: {
        id: number;
        name: string;
        email: string;
        role: string;
    };
    action: string;
    details: string | null;
    ip_address: string | null;
    performed_at: string;
}

export interface AppUser {
    id: number;
    name: string;
    email: string;
    role: 'admin' | 'faculty' | 'student';
    contact_number: string | null;
    is_active: boolean;
    created_at: string;
}

export interface SystemSettings {
    threshold_temperature_min?: string;
    threshold_temperature_max?: string;
    threshold_humidity_low?: string;
    threshold_humidity_high?: string;
    threshold_co2_max?: string;
    threshold_soil_warning?: string;
    threshold_soil_critical?: string;
    led_on_hour?: string;
    led_off_hour?: string;
    sms_recipients?: string;
    system_name?: string;
}

export interface LedSchedule {
    on_hour: number;
    off_hour: number;
}

export interface Thresholds {
    temp_max: number;
    humidity_low: number;
    humidity_high: number;
    co2_max: number;
    soil_warning: number;
    soil_critical: number;
}

export interface SensorReading {
    id: number;
    recorded_at: string;
    temperature: number | null;
    humidity: number | null;
    co2_raw: number | null;
    light_level: number | null;
    soil_moisture: number | null;
    soil_status: string | null;
}

export interface ChartPoint {
    time: string;
    temperature: number | null;
    humidity: number | null;
}

export interface MushroomMeasurement {
    id: number;
    growing_cycle_id: number;
    user_id: number;
    user?: { id: number; name: string };
    observed_date: string;
    flush_number: number;
    height_cm: number | null;
    cap_diameter_cm: number | null;
    fruiting_body_count: number | null;
    notes: string | null;
}

export interface DailySensorAverage {
    date: string;
    avg_temperature: number | null;
    avg_humidity: number | null;
    avg_co2: number | null;
    avg_light: number | null;
}

export interface ThresholdBreachSummary {
    temperature: number;
    humidity: number;
    co2: number;
    soil_moisture: number;
    total_readings: number;
}

/** Generic Laravel paginator shape */
export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: { url: string | null; label: string; active: boolean }[];
}
