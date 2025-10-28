<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Weather extends MY_Controller {

    public function index() {
        // You can pass data if needed (optional)
        $data['title'] = 'Weather Chatbot Dashboard';
        $this->render('weather', $data);
    }
}
