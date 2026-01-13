<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Notification extends MY_Controller {

    // Loads the notification page
    public function index() {
        // Render the notification view (keeps behavior consistent with Weather controller)
        $data['title'] = 'Notifications';
        $this->render('notification', $data);
    }

    // Returns weather alerts as JSON
    public function weather_alerts() {
        $defaultLat = 14.4297;
        $defaultLon = 120.9367;

        $lat = $this->input->get('lat') ? floatval($this->input->get('lat')) : $defaultLat;
        $lon = $this->input->get('lon') ? floatval($this->input->get('lon')) : $defaultLon;

        $notifications = [];

        // 1️⃣ Fetch WEATHER data
        $weatherUrl = "https://api.open-meteo.com/v1/forecast"
            . "?latitude={$lat}&longitude={$lon}"
            . "&hourly=temperature_2m,apparent_temperature,uv_index,precipitation_probability"
            . "&timezone=Asia/Manila";

        $weatherRes = @file_get_contents($weatherUrl);
        $weather = $weatherRes ? json_decode($weatherRes, true) : null;

        // 2️⃣ Fetch AIR QUALITY
        $airUrl = "https://air-quality-api.open-meteo.com/v1/air-quality"
            . "?latitude={$lat}&longitude={$lon}&hourly=us_aqi&timezone=Asia/Manila";

        $airRes = @file_get_contents($airUrl);
        $air = $airRes ? json_decode($airRes, true) : null;

        if (!$weather || empty($weather['hourly']['time'])) {
            $this->output->set_content_type('application/json')->set_output(json_encode([]));
            return;
        }

        // 3️⃣ Find nearest hour (same logic as your weather page)
        $now = time();
        $nearestIdx = 0;
        $minDiff = PHP_INT_MAX;

        foreach ($weather['hourly']['time'] as $i => $t) {
            $diff = abs(strtotime($t) - $now);
            if ($diff < $minDiff) {
                $minDiff = $diff;
                $nearestIdx = $i;
            }
        }

        $temp = $weather['hourly']['temperature_2m'][$nearestIdx] ?? null;
        $feels = $weather['hourly']['apparent_temperature'][$nearestIdx] ?? null;
        $uv = $weather['hourly']['uv_index'][$nearestIdx] ?? null;
        $rain = $weather['hourly']['precipitation_probability'][$nearestIdx] ?? null;
        $aqi = $air['hourly']['us_aqi'][$nearestIdx] ?? null;

        $timeLabel = date('M d, g:i A');

        // -------------------------
        // 🔔 GENERATE NOTIFICATIONS
        // -------------------------

        // Heat / feels like (add severity)
        if ($feels !== null && $feels >= 38) {
            $notifications[] = [
                'icon' => 'fa-temperature-high',
                'severity' => 'danger',
                'title' => 'Extreme Heat Advisory',
                'description' => 'Feels like ' . round($feels) . '°C. Stay hydrated and avoid prolonged outdoor activities.',
                'time' => $timeLabel
            ];
        } elseif ($feels !== null && $feels >= 33) {
            $notifications[] = [
                'icon' => 'fa-temperature-half',
                'severity' => 'warn',
                'title' => 'Heat Advisory',
                'description' => 'Feels like ' . round($feels) . '°C. Drink water and take breaks from the heat.',
                'time' => $timeLabel
            ];
        }

        // UV Index
        if ($uv !== null && $uv >= 8) {
            // Treat >=11 as more severe if needed; for now mark >=8 as warn
            $notifications[] = [
                'icon' => 'fa-sun',
                'severity' => ($uv >= 11 ? 'danger' : 'warn'),
                'title' => 'High UV Index',
                'description' => 'UV index is ' . round($uv) . '. Wear sunscreen and protective clothing.',
                'time' => $timeLabel
            ];
        }

        // Rain probability — consider daily max as fallback and use the
        // higher of hourly/daily so metrics and alerts align better.
        $dailyRain = $weather['daily']['precipitation_probability_max'][0] ?? null;
        $rainForAlert = null;
        if ($rain !== null && $dailyRain !== null) {
            $rainForAlert = max($rain, $dailyRain);
        } elseif ($rain !== null) {
            $rainForAlert = $rain;
        } elseif ($dailyRain !== null) {
            $rainForAlert = $dailyRain;
        }

        if ($rainForAlert !== null && $rainForAlert >= 70) {
            $notifications[] = [
                'icon' => 'fa-cloud-rain',
                'severity' => 'danger',
                'title' => 'Rain Advisory',
                'description' => 'High chance of rain (' . round($rainForAlert) . '%). Bring an umbrella.',
                'time' => $timeLabel
            ];
        }

        // Air Quality
        if ($aqi !== null && $aqi > 100) {
            $notifications[] = [
                'icon' => 'fa-smog',
                'severity' => 'danger',
                'title' => 'Air Quality Notice',
                'description' => 'Air quality is unhealthy for sensitive groups. Limit outdoor activity.',
                'time' => $timeLabel
            ];
        } elseif ($aqi !== null && $aqi > 50) {
            $notifications[] = [
                'icon' => 'fa-smog',
                'severity' => 'warn',
                'title' => 'Moderate Air Quality',
                'description' => 'Air quality is moderate. Sensitive individuals should take caution.',
                'time' => $timeLabel
            ];
        }

        // Return generated notifications
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($notifications));
    }

}
