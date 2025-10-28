<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Chatbot extends MY_Controller {

    public function index() {
        $data['location'] = 'Manila, PH';
        $data['temperature'] = '30°';
        $data['condition'] = 'Sunny';
        $data['greeting'] = 'Good day! How can I help you today?';
        $this->render('chatbot', $data);
    }

}
