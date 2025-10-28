<div class="chatbot-wrapper d-flex align-items-center justify-content-center">
    <div class="container chatbot-container py-5">

        <!-- Top Info Row -->
        <div class="row align-items-center mb-4 text-center text-md-start">
            <div class="col-md-8 mb-2 mb-md-0">
                <span class="me-2">📍 <?= $location ?></span>
                <span class="me-2"><?= $temperature ?></span>
                <span><?= $condition ?></span>
            </div>
            <div class="col-md-4 d-flex justify-content-center justify-content-md-end">
                <div class="input-group input-group-sm" style="max-width: 200px;">
                    <input type="text" class="form-control rounded-pill" id="searchInput" placeholder="Search...">
                    <button class="btn btn-outline-secondary rounded-pill ms-2" id="searchBtn">🔍</button>
                </div>
            </div>
        </div>

        <!-- Center Section -->
        <div class="text-center my-5">
            <div class="avatar-placeholder mx-auto mb-3"></div>
            <h5 class="chat-greeting"><?= $greeting ?></h5>
        </div>

        <!-- Message Input -->
        <div class="chat-input d-flex justify-content-center align-items-center mt-4">
            <input type="text" class="form-control rounded-pill me-2" id="userMessage" placeholder="Type your message..." />
            <button class="btn btn-primary rounded-circle" id="sendBtn">➤</button>
        </div>
    </div>
</div>

<!-- JS -->
<script>
document.getElementById('sendBtn').addEventListener('click', function() {
    const message = document.getElementById('userMessage').value.trim();
    if (message === '') return;

    fetch('<?= site_url("chatbot/send_message") ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'message=' + encodeURIComponent(message)
    })
    .then(response => response.json())
    .then(data => {
        alert(data.reply);
    });

    document.getElementById('userMessage').value = '';
});
</script>