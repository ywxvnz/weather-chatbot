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

<!-- CSS -->
<style>
/* 🌤 Full-page vertical gradient background */
html, body {
    height: 100%;
    margin: 0;
    padding: 0;
    background: linear-gradient(to bottom, #fffaf3 0%, #f8e9d6 100%);
    background-attachment: fixed;
    font-family: 'Poppins', sans-serif;
}

/* Wrapper centers content vertically */
.chatbot-wrapper {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Main chatbot card */
.chatbot-container {
    max-width: 600px;
    background: rgba(255, 255, 255, 0.9);
    border-radius: 20px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    color: #333;
}

/* Avatar placeholder */
.avatar-placeholder {
    width: 120px;
    height: 120px;
    border: 2px solid #d9cbb6;
    border-radius: 50%;
    background: #fdf7ef;
}

/* Greeting text */
.chat-greeting {
    font-size: 1.25rem;
    color: #444;
}

/* Input area */
.chat-input input {
    flex: 1;
    padding: 10px 15px;
    border-radius: 25px;
}

.chat-input button {
    background-color: #e6b980;
    border: none;
    padding: 10px 15px;
    transition: background-color 0.2s;
}

.chat-input button:hover {
    background-color: #d49c6e;
}

/* Responsive adjustments */
@media (max-width: 576px) {
    .chatbot-container {
        margin: 20px;
        padding: 25px;
    }

    .avatar-placeholder {
        width: 90px;
        height: 90px;
    }

    .chat-greeting {
        font-size: 1.1rem;
    }
}
</style>
