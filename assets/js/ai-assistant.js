/**
 * AI Assistant Floating Chat Widget
 * Provides AI-powered help and support for the application
 */

(function() {
    'use strict';

    // Widget State
    const state = {
        isOpen: false,
        isTyping: false,
        messages: [],
        conversationId: null,
        isFormActive: false
    };

    // DOM Elements
    let widget = null;
    let chatContainer = null;
    let messagesContainer = null;
    let inputField = null;
    let sendButton = null;
    let toggleButton = null;

    // Initialize Widget
    function init() {
        console.log('AI Assistant: Initializing...');
        
        // Create widget HTML
        createWidget();
        
        // Bind events
        bindEvents();
        
        // Load conversation history from localStorage
        loadConversation();
        
        // Show welcome message if first time
        if (state.messages.length === 0) {
            addMessage('assistant', 'Halo! 👋 Saya adalah AI Assistant yang siap membantu Anda. Ada yang bisa saya bantu terkait aplikasi Email Dispatcher?');
        }
        
        console.log('AI Assistant: Initialized successfully');
    }

    // Create Widget HTML
    function createWidget() {
        console.log('AI Assistant: Creating widget HTML...');
        
        const widgetHTML = `
            <div id="ai-assistant-widget" class="ai-assistant-widget">
                <!-- Toggle Button -->
                <button id="ai-assistant-toggle" class="ai-assistant-toggle" aria-label="Open AI Assistant">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                    </svg>
                    <span class="ai-assistant-badge">AI</span>
                </button>

                <!-- Chat Container -->
                <div id="ai-assistant-chat" class="ai-assistant-chat">
                    <!-- Header -->
                    <div class="ai-assistant-header">
                        <div class="ai-assistant-header-content">
                            <div class="ai-assistant-avatar">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <path d="M12 16v-4M12 8h.01"/>
                                </svg>
                            </div>
                            <div>
                                <h3>AI Assistant</h3>
                                <span class="ai-assistant-status">Online</span>
                            </div>
                        </div>
                        <button id="ai-assistant-close" class="ai-assistant-close" aria-label="Close">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M18 6L6 18M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Messages -->
                    <div id="ai-assistant-messages" class="ai-assistant-messages"></div>

                    <!-- Typing Indicator -->
                    <div id="ai-assistant-typing" class="ai-assistant-typing" style="display: none;">
                        <div class="typing-dots">
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>
                    </div>

                    <!-- Input Area -->
                    <div class="ai-assistant-input">
                        <textarea 
                            id="ai-assistant-input" 
                            placeholder="Ketik pesan Anda..." 
                            rows="1"
                            aria-label="Type your message"
                        ></textarea>
                        <button id="ai-assistant-send" class="ai-assistant-send" aria-label="Send message">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Quick Actions -->
                    <div class="ai-assistant-quick-actions">
                        <button class="quick-action" data-action="help">❓ Bantuan</button>
                        <button class="quick-action" data-action="docs">📚 Dokumentasi</button>
                        <button class="quick-action" data-action="troubleshoot">🔧 Troubleshoot</button>
                        <button class="quick-action" data-action="clear">🗑️ Clear Chat</button>
                    </div>
                </div>
            </div>
        `;

        // Insert widget into DOM
        document.body.insertAdjacentHTML('beforeend', widgetHTML);

        // Cache DOM elements
        widget = document.getElementById('ai-assistant-widget');
        chatContainer = document.getElementById('ai-assistant-chat');
        messagesContainer = document.getElementById('ai-assistant-messages');
        inputField = document.getElementById('ai-assistant-input');
        sendButton = document.getElementById('ai-assistant-send');
        toggleButton = document.getElementById('ai-assistant-toggle');

        console.log('AI Assistant: Widget created', {
            widget: !!widget,
            toggleButton: !!toggleButton,
            chatContainer: !!chatContainer
        });

        // Force visibility check
        if (widget) {
            widget.style.display = 'block';
            widget.style.visibility = 'visible';
            widget.style.position = 'fixed';
            widget.style.bottom = '3rem';
            widget.style.right = '3rem';
            widget.style.zIndex = '99999';
            console.log('AI Assistant: Widget styles applied', widget.style.cssText);
        }
    }

    // Bind Events
    function bindEvents() {
        // Toggle button
        toggleButton.addEventListener('click', toggleWidget);

        // Close button
        document.getElementById('ai-assistant-close').addEventListener('click', toggleWidget);

        // Send button
        sendButton.addEventListener('click', sendMessage);

        // Input field
        inputField.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });

        // Auto-resize textarea
        inputField.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        });

        // Quick actions
        document.querySelectorAll('.quick-action').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const action = e.target.dataset.action;
                handleQuickAction(action);
            });
        });

        // Close on outside click
        document.addEventListener('click', (e) => {
            if (state.isOpen && !widget.contains(e.target)) {
                toggleWidget();
            }
        });

        // Autohide when form inputs are focused
        setupFormAutohide();
    }

    // Setup autohide for form inputs
    function setupFormAutohide() {
        // Select all form inputs, textareas, and selects
        const formElements = document.querySelectorAll('input, textarea, select');

        formElements.forEach(element => {
            // Skip the AI assistant's own input field
            if (element.id === 'ai-assistant-input') return;

            // Hide widget when form element is focused
            element.addEventListener('focus', () => {
                if (!state.isFormActive) {
                    state.isFormActive = true;
                    hideWidget();
                }
            });

            // Show widget when form element loses focus
            element.addEventListener('blur', () => {
                // Small delay to allow clicking on widget if needed
                setTimeout(() => {
                    state.isFormActive = false;
                    showWidget();
                }, 300);
            });
        });

        // Also handle dynamically added form elements
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === 1) { // Element node
                        const newInputs = node.matches?.('input, textarea, select')
                            ? [node]
                            : node.querySelectorAll?.('input, textarea, select') || [];

                        newInputs.forEach(element => {
                            if (element.id === 'ai-assistant-input') return;

                            element.addEventListener('focus', () => {
                                if (!state.isFormActive) {
                                    state.isFormActive = true;
                                    hideWidget();
                                }
                            });

                            element.addEventListener('blur', () => {
                                setTimeout(() => {
                                    state.isFormActive = false;
                                    showWidget();
                                }, 300);
                            });
                        });
                    }
                });
            });
        });

        observer.observe(document.body, { childList: true, subtree: true });
    }

    // Hide widget (toggle button and chat)
    function hideWidget() {
        if (toggleButton) {
            toggleButton.style.transition = 'transform 0.3s ease, opacity 0.3s ease';
            toggleButton.style.transform = 'scale(0)';
            toggleButton.style.opacity = '0';
        }

        // Also close chat if open
        if (state.isOpen) {
            state.isOpen = false;
            chatContainer.classList.remove('open');
            toggleButton?.classList.remove('active');
        }
    }

    // Show widget
    function showWidget() {
        if (toggleButton) {
            toggleButton.style.transform = 'scale(1)';
            toggleButton.style.opacity = '1';
        }
    }

    // Toggle Widget Open/Close
    function toggleWidget() {
        state.isOpen = !state.isOpen;
        chatContainer.classList.toggle('open', state.isOpen);
        toggleButton.classList.toggle('active', state.isOpen);

        if (state.isOpen) {
            inputField.focus();
            // Mark messages as read
            widget.classList.remove('has-unread');
        }
    }

    // Send Message
    async function sendMessage() {
        const message = inputField.value.trim();
        if (!message || state.isTyping) return;

        // Add user message
        addMessage('user', message);

        // Clear input
        inputField.value = '';
        inputField.style.height = 'auto';

        // Show typing indicator
        showTypingIndicator();

        try {
            // Call AI assistant API
            const response = await fetch('api_ai_assistant.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    message: message,
                    history: state.messages
                })
            });

            const data = await response.json();

            hideTypingIndicator();

            if (data.response) {
                addMessage('assistant', data.response);
                console.log('AI Assistant: Response source:', data.source);
            } else {
                addMessage('assistant', 'Maaf, terjadi kesalahan. Silakan coba lagi.');
            }
        } catch (error) {
            console.error('AI Assistant: Error calling API', error);
            hideTypingIndicator();
            // Fallback to simulated response
            const fallbackResponse = generateAIResponse(message);
            addMessage('assistant', fallbackResponse);
        }
    }

    // Add Message to Chat
    function addMessage(type, content) {
        const message = {
            type,
            content,
            timestamp: new Date().toISOString()
        };

        state.messages.push(message);
        saveConversation();

        const messageEl = document.createElement('div');
        messageEl.className = `ai-message ai-message-${type}`;
        messageEl.innerHTML = `
            <div class="ai-message-content">
                <div class="ai-message-text">${escapeHtml(content)}</div>
                <div class="ai-message-time">${formatTime(message.timestamp)}</div>
            </div>
        `;

        messagesContainer.appendChild(messageEl);
        scrollToBottom();
    }

    // Show/Hide Typing Indicator
    function showTypingIndicator() {
        state.isTyping = true;
        document.getElementById('ai-assistant-typing').style.display = 'flex';
        scrollToBottom();
    }

    function hideTypingIndicator() {
        state.isTyping = false;
        document.getElementById('ai-assistant-typing').style.display = 'none';
    }

    // Handle Quick Actions
    function handleQuickAction(action) {
        switch(action) {
            case 'help':
                addMessage('user', 'Saya butuh bantuan');
                setTimeout(() => {
                    showTypingIndicator();
                    setTimeout(() => {
                        hideTypingIndicator();
                        addMessage('assistant', 'Tentu! Saya bisa membantu Anda dengan:\n\n• Manajemen kontak (add, edit, delete)\n• Compose & kirim email\n• Template email\n• Troubleshooting masalah\n• Panduan penggunaan\n\nApa yang ingin Anda bantu?');
                    }, 1000);
                }, 100);
                break;
            case 'docs':
                addMessage('user', 'Tunjukkan dokumentasi');
                setTimeout(() => {
                    showTypingIndicator();
                    setTimeout(() => {
                        hideTypingIndicator();
                        addMessage('assistant', 'Dokumentasi tersedia untuk:\n\n📖 Manual Operasional Lengkap\n👥 Modul 1: Manajemen Kontak\n✉️ Modul 2: Compose & Send Email\n🚀 Quick Start Guide\n\nAnda bisa mengakses dokumentasi lengkap di menu Help atau download file MD-nya.');
                    }, 1000);
                }, 100);
                break;
            case 'troubleshoot':
                addMessage('user', 'Saya ada masalah');
                setTimeout(() => {
                    showTypingIndicator();
                    setTimeout(() => {
                        hideTypingIndicator();
                        addMessage('assistant', 'Saya siap membantu troubleshooting! Silakan jelaskan masalah yang Anda alami:\n\n• Email tidak terkirim?\n• Kontak tidak muncul?\n• Error saat upload?\n• Template tidak berfungsi?\n\nCeritakan detail masalahnya ya.');
                    }, 1000);
                }, 100);
                break;
            case 'clear':
                state.messages = [];
                saveConversation();
                messagesContainer.innerHTML = '';
                addMessage('assistant', 'Chat telah dihapus. Mari mulai percakapan baru! Ada yang bisa saya bantu?');
                break;
        }
    }

    // Generate AI Response (Simulated - Replace with actual API)
    function generateAIResponse(userMessage) {
        const lowerMessage = userMessage.toLowerCase();

        // Simple keyword-based responses
        if (lowerMessage.includes('kontak') || lowerMessage.includes('contact')) {
            return 'Untuk manajemen kontak, Anda bisa:\n\n• Add kontak baru di halaman Contacts\n• Import dari file CSV/Excel\n• Edit atau delete kontak yang ada\n• Buat grup untuk segmentasi\n\nButuh bantuan lebih spesifik?';
        }
        if (lowerMessage.includes('email') || lowerMessage.includes('kirim') || lowerMessage.includes('send')) {
            return 'Untuk mengirim email:\n\n1. Buka halaman Compose\n2. Pilih template atau buat baru\n3. Pilih penerima (kontak/grup)\n4. Upload attachment jika perlu\n5. Klik Send untuk mengirim\n\nEmail akan diproses dan Anda bisa tracking status di halaman Logs.';
        }
        if (lowerMessage.includes('template')) {
            return 'Template email memudahkan Anda mengirim email dengan format konsisten. Anda bisa:\n\n• Buat template baru di halaman Templates\n• Gunakan placeholder seperti {nama}, {email}\n• Edit atau delete template yang ada\n• Preview template sebelum digunakan';
        }
        if (lowerMessage.includes('error') || lowerMessage.includes('gagal')) {
            return 'Maaf ada kendala. Mari troubleshooting:\n\n1. Cek koneksi internet\n2. Pastikan konfigurasi SMTP sudah benar\n3. Lihat detail error di halaman Logs\n4. Coba refresh halaman\n\nJika masih ada masalah, ceritakan detail error-nya ya.';
        }
        if (lowerMessage.includes('terima kasih') || lowerMessage.includes('thanks')) {
            return 'Sama-sama! Senang bisa membantu. 😊\n\nAda lagi yang ingin ditanyakan?';
        }

        // Default response
        const responses = [
            'Pertanyaan menarik! Bisa jelaskan lebih detail?',
            'Saya mengerti. Mari kita bahas lebih lanjut.',
            'Baik, saya siap membantu. Apa yang perlu saya jelaskan?',
            'Tentu! Saya bisa membantu dengan hal tersebut.'
        ];
        return responses[Math.floor(Math.random() * responses.length)];
    }

    // Utility Functions
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML.replace(/\n/g, '<br>');
    }

    function formatTime(timestamp) {
        const date = new Date(timestamp);
        return date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
    }

    function scrollToBottom() {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    function saveConversation() {
        localStorage.setItem('ai-assistant-conversation', JSON.stringify(state.messages));
    }

    function loadConversation() {
        const saved = localStorage.getItem('ai-assistant-conversation');
        if (saved) {
            state.messages = JSON.parse(saved);
            state.messages.forEach(msg => {
                const messageEl = document.createElement('div');
                messageEl.className = `ai-message ai-message-${msg.type}`;
                messageEl.innerHTML = `
                    <div class="ai-message-content">
                        <div class="ai-message-text">${escapeHtml(msg.content)}</div>
                        <div class="ai-message-time">${formatTime(msg.timestamp)}</div>
                    </div>
                `;
                messagesContainer.appendChild(messageEl);
            });
            scrollToBottom();
        }
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    console.log('AI Assistant: Script loaded, ready state:', document.readyState);

    // Fallback: Force widget creation after a delay
    setTimeout(() => {
        if (!document.getElementById('ai-assistant-widget')) {
            console.warn('AI Assistant: Widget not found, forcing creation');
            init();
        } else {
            console.log('AI Assistant: Widget exists in DOM');
        }
    }, 1000);

})();
