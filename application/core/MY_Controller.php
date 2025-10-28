<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class MY_Controller extends CI_Controller {

    public function __construct() {
        parent::__construct();
        // Load URL helper globally
        $this->load->helper('url');
    }

    /**
     * Render a view with header and footer
     *
     * @param string $view The main view file (inside /views)
     * @param array $data Optional data to pass to the view
     */
    protected function render($view, $data = []) {
        $this->load->view('templates/header', $data);
        $this->load->view($view, $data);
        $this->load->view('templates/footer', $data);   
    }
}
