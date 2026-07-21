export interface SensorData {
    temperature: number | null;
    humidity: number | null;
    co2_raw: number | null;
    light_level: number | null;
    soil_moisture: number | null;
    soil_status: 'dry' | 'critical' | 'moist' | 'wet' | null;
    last_updated: string | null;
}

export interface ActuatorData {
    humidifier: 'on' | 'off';
    led: 'on' | 'off';
    fan: 'on' | 'off';
}
