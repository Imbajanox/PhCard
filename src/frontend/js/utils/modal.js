// Modal Utility System
// Replaces generic alerts and confirms with styled modals

class ModalSystem {
    constructor() {
        this.createModalContainer();
    }

    createModalContainer() {
        // Remove any existing modal container
        const existing = document.getElementById('global-modal-container');
        if (existing) {
            existing.remove();
        }

        // Create modal container
        const container = document.createElement('div');
        container.id = 'global-modal-container';
        container.className = 'modal';
        container.innerHTML = `
            <div class="modal-content" id="modal-content-wrapper">
                <h2 id="modal-title"></h2>
                <p id="modal-message"></p>
                <div id="modal-buttons"></div>
            </div>
        `;
        document.body.appendChild(container);
    }

    /**
     * Show a simple alert modal
     * @param {string} message - The message to display
     * @param {string} title - The title of the modal (optional)
     */
    alert(message, title = 'Hinweis') {
        return new Promise((resolve) => {
            const modal = document.getElementById('global-modal-container');
            const titleEl = document.getElementById('modal-title');
            const messageEl = document.getElementById('modal-message');
            const buttonsEl = document.getElementById('modal-buttons');

            titleEl.textContent = title;
            messageEl.innerHTML = message.replace(/\n/g, '<br>');
            buttonsEl.innerHTML = '';

            const okBtn = document.createElement('button');
            okBtn.textContent = 'OK';
            okBtn.className = 'modal-btn modal-btn-primary';
            okBtn.onclick = () => {
                this.closeModal();
                resolve(true);
            };
            buttonsEl.appendChild(okBtn);

            modal.classList.add('active');
            okBtn.focus();
        });
    }

    /**
     * Show a confirmation modal
     * @param {string} message - The message to display
     * @param {string} title - The title of the modal (optional)
     */
    confirm(message, title = 'Bestätigung') {
        return new Promise((resolve) => {
            const modal = document.getElementById('global-modal-container');
            const titleEl = document.getElementById('modal-title');
            const messageEl = document.getElementById('modal-message');
            const buttonsEl = document.getElementById('modal-buttons');

            titleEl.textContent = title;
            messageEl.innerHTML = message.replace(/\n/g, '<br>');
            buttonsEl.innerHTML = '';

            const cancelBtn = document.createElement('button');
            cancelBtn.textContent = 'Abbrechen';
            cancelBtn.className = 'modal-btn modal-btn-secondary';
            cancelBtn.onclick = () => {
                this.closeModal();
                resolve(false);
            };

            const confirmBtn = document.createElement('button');
            confirmBtn.textContent = 'Bestätigen';
            confirmBtn.className = 'modal-btn modal-btn-primary';
            confirmBtn.onclick = () => {
                this.closeModal();
                resolve(true);
            };

            buttonsEl.appendChild(cancelBtn);
            buttonsEl.appendChild(confirmBtn);

            modal.classList.add('active');
            confirmBtn.focus();
        });
    }

    /**
     * Show a prompt modal
     * @param {string} message - The message to display
     * @param {string} defaultValue - The default value
     * @param {string} title - The title of the modal (optional)
     */
    prompt(message, defaultValue = '', title = 'Eingabe') {
        return new Promise((resolve) => {
            const modal = document.getElementById('global-modal-container');
            const titleEl = document.getElementById('modal-title');
            const messageEl = document.getElementById('modal-message');
            const buttonsEl = document.getElementById('modal-buttons');

            titleEl.textContent = title;
            messageEl.innerHTML = message.replace(/\n/g, '<br>');
            
            // Add input field
            const inputWrapper = document.createElement('div');
            inputWrapper.style.margin = '20px 0';
            const input = document.createElement('input');
            input.type = 'text';
            input.value = defaultValue;
            input.className = 'modal-input';
            input.style.width = '100%';
            input.style.padding = '10px';
            input.style.background = '#0a0e27';
            input.style.border = '2px solid #00ff00';
            input.style.color = '#00ff00';
            input.style.fontFamily = 'Courier New, monospace';
            inputWrapper.appendChild(input);
            messageEl.appendChild(inputWrapper);
            
            buttonsEl.innerHTML = '';

            const cancelBtn = document.createElement('button');
            cancelBtn.textContent = 'Abbrechen';
            cancelBtn.className = 'modal-btn modal-btn-secondary';
            cancelBtn.onclick = () => {
                this.closeModal();
                resolve(null);
            };

            const confirmBtn = document.createElement('button');
            confirmBtn.textContent = 'OK';
            confirmBtn.className = 'modal-btn modal-btn-primary';
            confirmBtn.onclick = () => {
                this.closeModal();
                resolve(input.value);
            };

            buttonsEl.appendChild(cancelBtn);
            buttonsEl.appendChild(confirmBtn);

            modal.classList.add('active');
            input.focus();
            input.select();

            // Handle Enter key
            input.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    confirmBtn.click();
                }
            });
        });
    }

    closeModal() {
        const modal = document.getElementById('global-modal-container');
        modal.classList.remove('active');
    }
}

// Create global modal instance
const modalSystem = new ModalSystem();

// Override window.alert, window.confirm, and window.prompt
window.alert = (message) => modalSystem.alert(message);
window.confirm = (message) => modalSystem.confirm(message);
window.prompt = (message, defaultValue) => modalSystem.prompt(message, defaultValue);
