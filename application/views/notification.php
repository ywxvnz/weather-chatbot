<?php
$notifications = [
    ['icon' => 'fa-sun', 'title' => 'Weather Update', 'description' => 'It will be sunny this afternoon.', 'time' => '2 mins ago'],
    ['icon' => 'fa-cloud-rain', 'title' => 'Rain Alert', 'description' => 'Light rain expected in your area.', 'time' => '30 mins ago'],
    ['icon' => 'fa-bell', 'title' => 'System Notice', 'description' => 'New chatbot version available.', 'time' => '1 hour ago'],
    ['icon' => 'fa-sun', 'title' => 'Good Morning!', 'description' => 'Perfect weather to start your day 🌞', 'time' => 'Today, 7:00 AM'],
];
?>

<?php include 'templates/header.php'; ?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link rel="stylesheet" href="<?= base_url('assets/css/notification.css'); ?>">


<div class="container">
    <?php foreach ($notifications as $note): ?>
        <div class="notification">
            <div class="icon"><i class="fas <?= $note['icon']; ?>"></i></div>
            <div class="content">
                <div class="title"><?= $note['title']; ?></div>
                <div class="description"><?= $note['description']; ?></div>
            </div>
            <div class="time"><?= $note['time']; ?></div>
        </div>
    <?php endforeach; ?>
</div>

<?php include 'templates/footer.php'; ?>
