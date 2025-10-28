<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Dashboard extends MY_Controller {

    public function index() {
        // You can pass data if needed (optional)
        $data['title'] = 'Weather Chatbot Dashboard';
        $data['location'] = 'Manila, PH';
        $data['temperature'] = '30°';
        $data['condition'] = 'Sunny';
        $data['greeting'] = 'Hello! Welcome to Nubi.';
        $this->render('dashboard', $data);
    }
}
