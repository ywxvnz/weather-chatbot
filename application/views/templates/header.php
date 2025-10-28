<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Weather Chatbot Dashboard</title>

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Bootstrap Icons (optional) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

  <!-- Custom CSS -->
  <link rel="stylesheet" href="<?php echo base_url('assets/css/style.css'); ?>">
  <link rel="stylesheet" href="<?php echo base_url('assets/css/header.css'); ?>">
  <link rel="stylesheet" href="<?php echo base_url('assets/css/dashboard.css'); ?>">
</head>
<body>

  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg custom-navbar">
    <div class="container">
      <!-- Mobile: logo left, burger right (visible only on <lg) -->
      <a class="navbar-brand d-lg-none" href="<?php echo site_url('dashboard'); ?>">
        <img src="<?php echo base_url('assets/icons/logo.png'); ?>" alt="Weather Logo" class="site-logo" />
        <span class="visually-hidden">Weather Chatbot</span>
      </a>

      <button class="navbar-toggler ms-auto d-lg-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
      </button>

      <!-- Menu: on large screens it's expanded and centered; on smaller screens it collapses to a floating panel -->
      <div class="collapse navbar-collapse justify-content-center" id="navbarNav">
        <ul class="navbar-nav nav-center d-flex align-items-center">
          <li class="nav-item"><a class="nav-link" href="<?php echo site_url('dashboard'); ?>">Home</a></li>
          <li class="nav-item"><a class="nav-link" href="<?php echo site_url('weather'); ?>">Weather</a></li>

          <!-- Desktop logo centered (only visible >= lg) -->
          <li class="nav-item d-none d-lg-flex logo-item mx-3">
            <a class="navbar-brand text-center" href="<?php echo site_url('dashboard'); ?>">
              <img src="<?php echo base_url('assets/icons/logo.png'); ?>" alt="Weather Logo" class="site-logo" />
              <span class="visually-hidden">Weather Chatbot</span>
            </a>
          </li>

          <li class="nav-item"><a class="nav-link" href="<?php echo site_url('notification'); ?>">Notification</a></li>
          <li class="nav-item"><a class="nav-link" href="<?php echo site_url('chatbot'); ?>">Chatbot</a></li>
        </ul>
      </div>
    </div>
  </nav>

  <main class="container my-5">
