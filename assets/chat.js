(() => {
    const messagesEl = document.getElementById('messages');
    const formEl = document.getElementById('chat-form');
    const inputEl = document.getElementById('message-input');
    const statusEl = document.getElementById('chat-status');
    const getMessagesUrl = window.chatConfig?.getMessagesUrl || 'index.php?ajax=get_messages';
    const sendMessageUrl = window.chatConfig?.sendMessageUrl || 'index.php?ajax=send_message';
    let lastId = Number(window.chatConfig?.initialLastId || 0);

    if (!messagesEl || !formEl || !inputEl) {
        console.error('No se pudo inicializar el chat: faltan elementos del DOM');
        return;
    }

    const escapeHtml = (text) => text
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const appendMessage = (item) => {
        const wrapper = document.createElement('article');
        wrapper.className = 'message';
        wrapper.innerHTML = `
            <div class="meta">
                <strong>${escapeHtml(String(item.username))}</strong>
                <small>${escapeHtml(String(item.created_at))}</small>
            </div>
            <p>${escapeHtml(String(item.message)).replaceAll('\n', '<br>')}</p>
        `;
        messagesEl.appendChild(wrapper);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    };

    const setStatus = (text) => {
        if (!statusEl) {
            return;
        }
        statusEl.textContent = text;
    };

    const fetchMessages = async () => {
        try {
            const body = new URLSearchParams({
                _chat_action: 'get_messages',
                last_id: String(lastId)
            });
            const response = await fetch(getMessagesUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                credentials: 'same-origin',
                body
            });
            if (!response.ok) {
                setStatus(`Error cargando mensajes (${response.status})`);
                return;
            }

            const data = await response.json();
            if (!data.ok || !Array.isArray(data.messages)) {
                setStatus('No se pudieron cargar los mensajes');
                return;
            }

            data.messages.forEach((item) => {
                appendMessage(item);
                lastId = Math.max(lastId, Number(item.id));
            });
            setStatus('');
        } catch (error) {
            console.error(error);
            setStatus('Fallo de red al cargar mensajes');
        }
    };

    formEl.addEventListener('submit', async (event) => {
        event.preventDefault();
        event.stopPropagation();
        const message = inputEl.value.trim();
        if (!message) {
            return;
        }

        try {
            const body = new URLSearchParams({
                _chat_action: 'send_message',
                message
            });
            const response = await fetch(sendMessageUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                credentials: 'same-origin',
                body
            });

            if (response.ok) {
                inputEl.value = '';
                setStatus('');
                await fetchMessages();
            } else {
                const errorBody = await response.text();
                console.error('Error send_message:', response.status, errorBody);
                setStatus(`No se pudo enviar (${response.status})`);
            }
        } catch (error) {
            console.error(error);
            setStatus('Fallo de red al enviar');
        }
    });

    messagesEl.scrollTop = messagesEl.scrollHeight;
    setInterval(fetchMessages, 2000);
})();
