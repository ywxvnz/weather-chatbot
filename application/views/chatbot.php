<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<div class="chatbot-container">
    <!-- Top Info Row -->
    <div class="row align-items-center mb-4 text-center text-md-start">
        <div class="col-md-7 mb-2 mb-md-0">
            <span class="me-2"><?= $location ?></span>
            <span class="me-2"><?= $temperature ?></span>
            <span><?= $condition ?></span>
        </div>
        <div class="col-md-5 d-flex justify-content-center justify-content-md-end">
            <div class="chatbot-search-box">
                <i class="fas fa-search chatbot-search-icon"></i>
                <input type="text" id="locationSearch" placeholder="     Search for a location...">
                <i class="fas fa-map-marker-alt chatbot-location-icon"></i>
            </div>
        </div>
    </div>

    <!-- Greeting or Chat Area -->
    <div id="chatArea">
      <div class="text-center my-5" id="greetingSection">
        <div class="avatar-wrapper mx-auto mb-3">
          <img src="<?= base_url('assets/icons/avatar.png') ?>" alt="Chatbot Logo" class="avatar-logo">
        </div>
        <h5 class="chat-greeting"><?= $greeting ?></h5>
      </div>
    </div>

    <!--message input -->
    <div class="chat-section">
        <div class="chat-input-container">
            <input type="text" id="userMessage" placeholder="Type your message here...">
            <button class="send-button" id="sendBtn">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </div>
</div>

<script>
const chatArea = document.getElementById('chatArea');
const greetingSection = document.getElementById('greetingSection');
const userMessageInput = document.getElementById('userMessage');
const sendBtn = document.getElementById('sendBtn');

function addMessage(content, type) {
    if (greetingSection) greetingSection.style.display = 'none';
    const bubble = document.createElement('div');
    bubble.classList.add('chat-bubble', type);
    bubble.textContent = content;
    chatArea.appendChild(bubble);
    chatArea.scrollTop = chatArea.scrollHeight;
}

function simulateResponse(userMsg) {
    setTimeout(() => {
        addMessage("You said: " + userMsg, 'system');
    }, 800);
}

function sendMessage() {
    const msg = userMessageInput.value.trim();
    if (!msg) return;
    addMessage(msg, 'user');
    simulateResponse(msg);
    userMessageInput.value = '';
}

sendBtn.addEventListener('click', sendMessage);
userMessageInput.addEventListener('keypress', e => {
    if (e.key === 'Enter') sendMessage();
});
</script>
