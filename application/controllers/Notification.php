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
        // Default coordinates (Imus)
        $defaultLat = 14.4297;
        $defaultLon = 120.9367;

        // Use GET params if provided (JS will send these)
        $lat = $this->input->get('lat') ? floatval($this->input->get('lat')) : $defaultLat;
        $lon = $this->input->get('lon') ? floatval($this->input->get('lon')) : $defaultLon;

        $notifications = [];

        $url = "https://api.open-meteo.com/v1/forecast"
             . "?latitude={$lat}&longitude={$lon}&alerts=true&timezone=Asia/Manila";

        $response = @file_get_contents($url);
        if ($response !== false) {
            $data = json_decode($response, true);

            if (!empty($data['alerts'])) {
                foreach ($data['alerts'] as $alert) {
                    $icon = 'fa-bell';
                    $severity = strtolower($alert['severity'] ?? '');

                    if (strpos($severity, 'severe') !== false) {
                        $icon = 'fa-triangle-exclamation text-danger';
                    } elseif (strpos($severity, 'moderate') !== false) {
                        $icon = 'fa-cloud-rain text-warning';
                    } elseif (strpos($severity, 'minor') !== false) {
                        $icon = 'fa-circle-info text-info';
                    }

                    $time = isset($alert['start'])
                        ? date('M d, g:i A', $alert['start'])
                        : 'Today';

                    $notifications[] = [
                        'icon' => $icon,
                        'title' => $alert['event'] ?? 'Weather Alert',
                        'description' => $alert['description'] ?? 'Weather alert issued.',
                        'time' => $time
                    ];
                }
            }
        }

        // Return JSON
        $this->output
             ->set_content_type('application/json')
             ->set_output(json_encode($notifications));
    }
}
