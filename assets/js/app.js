/**
 * ROTZ Email Butler - Main Application JavaScript
 */

class ROTZEmailButler {
    constructor() {
        this.currentView = 'dashboard';
        this.aiProviders = [];
        this.emailAccounts = [];
        this.emails = [];
        
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.loadDashboardData();
        this.loadAIProviders();
        this.loadEmailAccounts();
        this.loadEmails();
        this.loadSettings();
    }
    
    bindEvents() {
        // Navigation
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                const view = item.dataset.view;
                this.showView(view);
            });
        });
        
        // Modal events
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) {
                this.closeModal(e.target.id);
            }
        });
        
        // Form submissions
        this.bindFormEvents();
    }
    
    bindFormEvents() {
        // AI Provider form
        const aiProviderForm = document.getElementById('add-ai-provider-form');
        if (aiProviderForm) {
            aiProviderForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.addAIProvider(new FormData(aiProviderForm));
            });
            
            // Update models when provider changes
            const providerSelect = aiProviderForm.querySelector('[name="provider_name"]');
            if (providerSelect) {
                providerSelect.addEventListener('change', () => {
                    this.updateModelOptions(providerSelect.value);
                });
            }
        }
        
        // Email Account form
        const emailAccountForm = document.getElementById('add-email-account-form');
        if (emailAccountForm) {
            emailAccountForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.addEmailAccount(new FormData(emailAccountForm));
            });
        }
    }
    
    showView(viewName) {
        // Hide all views
        document.querySelectorAll('.view').forEach(view => {
            view.classList.remove('active');
        });
        
        // Show selected view
        const targetView = document.getElementById(`${viewName}-view`);
        if (targetView) {
            targetView.classList.add('active');
        }
        
        // Update navigation
        document.querySelectorAll('.nav-item').forEach(item => {
            item.classList.remove('active');
        });
        
        const activeNavItem = document.querySelector(`[data-view="${viewName}"]`);
        if (activeNavItem) {
            activeNavItem.classList.add('active');
        }
        
        this.currentView = viewName;
        
        // Load view-specific data
        switch (viewName) {
            case 'dashboard':
                this.loadDashboardData();
                break;
            case 'ai-providers':
                this.loadAIProviders();
                break;
            case 'email-accounts':
                this.loadEmailAccounts();
                break;
            case 'emails':
                this.loadEmails();
                break;
            case 'analytics':
                this.loadAnalytics();
                break;
            case 'admin':
                this.loadAdminData();
                break;
        }
    }
    
    async loadDashboardData() {
        try {
            const response = await fetch('/api/dashboard');
            const data = await response.json();
            
            if (data.success) {
                this.updateDashboardStats(data.stats);
                this.updateRecentEmails(data.recent_emails);
                this.updateAIProviderStatus(data.ai_status);
            }
        } catch (error) {
            console.error('Failed to load dashboard data:', error);
        }
    }
    
    updateDashboardStats(stats) {
        document.getElementById('total-emails').textContent = stats.total_emails || 0;
        document.getElementById('active-ai-providers').textContent = stats.active_ai_providers || 0;
        document.getElementById('connected-accounts').textContent = stats.connected_accounts || 0;
        document.getElementById('processing-accuracy').textContent = (stats.processing_accuracy || 0) + '%';
    }
    
    updateRecentEmails(emails) {
        const container = document.getElementById('recent-emails');
        if (!emails || emails.length === 0) {
            container.innerHTML = '<p class="empty-state">No recent emails to display</p>';
            return;
        }
        
        container.innerHTML = emails.map(email => `
            <div class="email-item">
                <div class="email-avatar">${email.sender_name.charAt(0).toUpperCase()}</div>
                <div class="email-content">
                    <div class="email-sender">${this.escapeHtml(email.sender_name)}</div>
                    <div class="email-subject">${this.escapeHtml(email.subject)}</div>
                    <div class="email-preview">${this.escapeHtml(email.preview)}</div>
                </div>
                <div class="email-meta">
                    <div class="email-category category-${email.category}">${email.category}</div>
                    <div class="email-time">${this.formatTime(email.received_at)}</div>
                </div>
            </div>
        `).join('');
    }
    
    updateAIProviderStatus(providers) {
        const container = document.getElementById('ai-provider-status');
        if (!providers || providers.length === 0) {
            container.innerHTML = '<p class="empty-state">No AI providers configured</p>';
            return;
        }
        
        container.innerHTML = providers.map(provider => `
            <div class="provider-status-item">
                <span class="provider-name">${provider.name}</span>
                <span class="provider-status status-${provider.status}">${provider.status}</span>
            </div>
        `).join('');
    }
    
    async loadAIProviders() {
        try {
            const response = await fetch('/api/ai-providers');
            const data = await response.json();
            
            if (data.success) {
                this.aiProviders = data.providers;
                this.renderAIProviders();
            }
        } catch (error) {
            console.error('Failed to load AI providers:', error);
        }
    }
    
    renderAIProviders() {
        const container = document.getElementById('ai-providers-list');
        if (!this.aiProviders || this.aiProviders.length === 0) {
            container.innerHTML = '<p class="empty-state">No AI providers configured. Click "Add AI Provider" to get started.</p>';
            return;
        }
        
        container.innerHTML = this.aiProviders.map(provider => `
            <div class="provider-card">
                <div class="provider-header">
                    <div class="provider-name">${provider.provider_name}</div>
                    <div class="provider-status status-${provider.status}">${provider.status}</div>
                </div>
                <div class="provider-details">
                    <div><strong>Model:</strong> ${provider.model_name}</div>
                    <div><strong>Cost:</strong> $${provider.total_cost || 0}</div>
                    <div><strong>Requests:</strong> ${provider.total_requests || 0}</div>
                    <div><strong>Accuracy:</strong> ${provider.accuracy || 0}%</div>
                </div>
                <div class="provider-toggle">
                    <div class="toggle-switch ${provider.is_enabled ? 'active' : ''}" 
                         onclick="app.toggleAIProvider(${provider.id})">
                    </div>
                    <span>Enable Provider</span>
                </div>
                <div class="provider-actions">
                    <button class="btn btn-secondary" onclick="app.testAIProvider(${provider.id})">Test</button>
                    <button class="btn btn-warning" onclick="app.editAIProvider(${provider.id})">Edit</button>
                    <button class="btn btn-error" onclick="app.deleteAIProvider(${provider.id})">Delete</button>
                </div>
            </div>
        `).join('');
    }
    
    async toggleAIProvider(providerId) {
        try {
            const response = await fetch(`/api/ai-providers/${providerId}/toggle`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            });
            
            const data = await response.json();
            if (data.success) {
                this.loadAIProviders(); // Reload to update UI
                this.showNotification('AI Provider status updated', 'success');
            } else {
                this.showNotification(data.message || 'Failed to update provider status', 'error');
            }
        } catch (error) {
            console.error('Failed to toggle AI provider:', error);
            this.showNotification('Failed to update provider status', 'error');
        }
    }
    
    async testAIProvider(providerId) {
        try {
            const response = await fetch(`/api/ai-providers/${providerId}/test`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            });
            
            const data = await response.json();
            if (data.success) {
                this.showNotification(`Test successful! Response time: ${data.response_time}ms`, 'success');
            } else {
                this.showNotification(data.message || 'Test failed', 'error');
            }
        } catch (error) {
            console.error('Failed to test AI provider:', error);
            this.showNotification('Test failed', 'error');
        }
    }
    
    async deleteAIProvider(providerId) {
        if (!confirm('Are you sure you want to delete this AI provider?')) {
            return;
        }
        
        try {
            const response = await fetch(`/api/ai-providers/${providerId}`, {
                method: 'DELETE'
            });
            
            const data = await response.json();
            if (data.success) {
                this.loadAIProviders(); // Reload to update UI
                this.showNotification('AI Provider deleted successfully', 'success');
            } else {
                this.showNotification(data.message || 'Failed to delete provider', 'error');
            }
        } catch (error) {
            console.error('Failed to delete AI provider:', error);
            this.showNotification('Failed to delete provider', 'error');
        }
    }
    
    updateModelOptions(providerName) {
        const modelSelect = document.querySelector('[name="model_name"]');
        if (!modelSelect) return;
        
        const models = {
            'openai': [
                { value: 'gpt-4', text: 'GPT-4' },
                { value: 'gpt-4-turbo', text: 'GPT-4 Turbo' },
                { value: 'gpt-3.5-turbo', text: 'GPT-3.5 Turbo' }
            ],
            'anthropic': [
                { value: 'claude-3-5-sonnet-20241022', text: 'Claude 3.5 Sonnet' },
                { value: 'claude-3-haiku-20240307', text: 'Claude 3 Haiku' },
                { value: 'claude-3-opus-20240229', text: 'Claude 3 Opus' }
            ],
            'google': [
                { value: 'gemini-pro', text: 'Gemini Pro' },
                { value: 'gemini-pro-vision', text: 'Gemini Pro Vision' },
                { value: 'gemini-ultra', text: 'Gemini Ultra' }
            ],
            'qwen': [
                { value: 'qwen2.5-max', text: 'Qwen2.5-Max' },
                { value: 'qvq-72b-preview', text: 'QVQ-72B-Preview' },
                { value: 'qwen2.5-coder-32b-instruct', text: 'Qwen2.5-Coder-32B' }
            ],
            'groq': [
                { value: 'llama-3.1-70b-versatile', text: 'Llama 3.1 70B' },
                { value: 'mixtral-8x7b-32768', text: 'Mixtral 8x7B' },
                { value: 'gemma2-9b-it', text: 'Gemma2 9B' }
            ],
            'cohere': [
                { value: 'command-r-plus', text: 'Command R+' },
                { value: 'command-r', text: 'Command R' },
                { value: 'command-light', text: 'Command Light' }
            ],
            'mistral': [
                { value: 'mistral-large-latest', text: 'Mistral Large' },
                { value: 'mistral-medium-latest', text: 'Mistral Medium' },
                { value: 'mistral-small-latest', text: 'Mistral Small' }
            ],
            'together': [
                { value: 'meta-llama/Llama-3-70b-chat-hf', text: 'Llama 3 70B' },
                { value: 'mistralai/Mixtral-8x7B-Instruct-v0.1', text: 'Mixtral 8x7B' },
                { value: 'NousResearch/Nous-Hermes-2-Mixtral-8x7B-DPO', text: 'Nous Hermes 2' }
            ]
        };
        
        const providerModels = models[providerName] || [];
        
        modelSelect.innerHTML = '<option value="">Select Model</option>' + 
            providerModels.map(model => `<option value="${model.value}">${model.text}</option>`).join('');
    }
    
    async addAIProvider(formData) {
        try {
            const response = await fetch('/api/ai-providers', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            if (data.success) {
                this.closeModal('add-ai-provider-modal');
                this.loadAIProviders(); // Reload to update UI
                this.showNotification('AI Provider added successfully', 'success');
            } else {
                this.showNotification(data.message || 'Failed to add AI provider', 'error');
            }
        } catch (error) {
            console.error('Failed to add AI provider:', error);
            this.showNotification('Failed to add AI provider', 'error');
        }
    }
    
    async loadEmailAccounts() {
        try {
            const response = await fetch('/api/email-accounts');
            const data = await response.json();
            
            if (data.success) {
                this.emailAccounts = data.accounts;
                this.renderEmailAccounts();
            }
        } catch (error) {
            console.error('Failed to load email accounts:', error);
        }
    }
    
    renderEmailAccounts() {
        const container = document.getElementById('email-accounts-list');
        if (!this.emailAccounts || this.emailAccounts.length === 0) {
            container.innerHTML = '<p class="empty-state">No email accounts connected. Click "Connect Email Account" to get started.</p>';
            return;
        }
        
        container.innerHTML = this.emailAccounts.map(account => `
            <div class="account-card">
                <div class="account-header">
                    <div class="account-name">${account.email_address}</div>
                    <div class="account-status status-${account.status}">${account.status}</div>
                </div>
                <div class="account-details">
                    <div><strong>Provider:</strong> ${account.provider_type}</div>
                    <div><strong>Last Sync:</strong> ${this.formatTime(account.last_sync)}</div>
                    <div><strong>Total Emails:</strong> ${account.email_count || 0}</div>
                </div>
                <div class="account-actions">
                    <button class="btn btn-primary" onclick="app.syncEmailAccount(${account.id})">Sync Now</button>
                    <button class="btn btn-warning" onclick="app.editEmailAccount(${account.id})">Edit</button>
                    <button class="btn btn-error" onclick="app.deleteEmailAccount(${account.id})">Delete</button>
                </div>
            </div>
        `).join('');
    }
    
    async syncEmailAccount(accountId) {
        try {
            const response = await fetch(`/api/email-accounts/${accountId}/sync`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            });
            
            const data = await response.json();
            if (data.success) {
                this.loadEmailAccounts(); // Reload to update UI
                this.showNotification(`Synced ${data.new_emails} new emails`, 'success');
            } else {
                this.showNotification(data.message || 'Sync failed', 'error');
            }
        } catch (error) {
            console.error('Failed to sync email account:', error);
            this.showNotification('Sync failed', 'error');
        }
    }
    
    updateEmailProviderFields() {
        const providerType = document.querySelector('[name="provider_type"]').value;
        const fieldsContainer = document.getElementById('email-provider-fields');
        
        if (!providerType) {
            fieldsContainer.innerHTML = '';
            return;
        }
        
        let fields = '';
        
        // Common fields for most providers
        if (['custom_imap', 'custom_exchange'].includes(providerType)) {
            fields = `
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email_address" required>
                </div>
                <div class="form-group">
                    <label>Password / App Password</label>
                    <input type="password" name="password" required>
                </div>
                <div class="form-group">
                    <label>IMAP Server</label>
                    <input type="text" name="imap_host" placeholder="imap.example.com" required>
                </div>
                <div class="form-group">
                    <label>IMAP Port</label>
                    <input type="number" name="imap_port" value="993" required>
                </div>
                <div class="form-group">
                    <label>SMTP Server</label>
                    <input type="text" name="smtp_host" placeholder="smtp.example.com" required>
                </div>
                <div class="form-group">
                    <label>SMTP Port</label>
                    <input type="number" name="smtp_port" value="587" required>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="use_ssl" checked>
                        Use SSL/TLS
                    </label>
                </div>
            `;
        } else if (providerType.includes('api')) {
            fields = `
                <div class="form-group">
                    <label>API Key</label>
                    <input type="password" name="api_key" required>
                </div>
                <div class="form-group">
                    <label>API Domain (if applicable)</label>
                    <input type="text" name="api_domain" placeholder="mg.example.com">
                </div>
            `;
        } else {
            // Standard providers (Gmail, Outlook, etc.)
            fields = `
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email_address" required>
                </div>
                <div class="form-group">
                    <label>Password / App Password</label>
                    <input type="password" name="password" required>
                    <small>For Gmail and Outlook, use an App Password instead of your regular password</small>
                </div>
            `;
        }
        
        fieldsContainer.innerHTML = fields;
    }
    
    async addEmailAccount(formData) {
        try {
            const response = await fetch('/api/email-accounts', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            if (data.success) {
                this.closeModal('add-email-account-modal');
                this.loadEmailAccounts(); // Reload to update UI
                this.showNotification('Email account connected successfully', 'success');
            } else {
                this.showNotification(data.message || 'Failed to connect email account', 'error');
            }
        } catch (error) {
            console.error('Failed to add email account:', error);
            this.showNotification('Failed to connect email account', 'error');
        }
    }
    
    async loadEmails() {
        try {
            const response = await fetch('/api/emails');
            const data = await response.json();
            
            if (data.success) {
                this.emails = data.emails;
                this.renderEmails();
            }
        } catch (error) {
            console.error('Failed to load emails:', error);
        }
    }
    
    renderEmails() {
        const container = document.getElementById('emails-list');
        if (!this.emails || this.emails.length === 0) {
            container.innerHTML = '<p class="empty-state">No emails to display. Connect an email account and sync to get started.</p>';
            return;
        }
        
        container.innerHTML = this.emails.map(email => `
            <div class="email-item">
                <div class="email-avatar">${email.sender_name.charAt(0).toUpperCase()}</div>
                <div class="email-content">
                    <div class="email-sender">${this.escapeHtml(email.sender_name)} &lt;${this.escapeHtml(email.sender_email)}&gt;</div>
                    <div class="email-subject">${this.escapeHtml(email.subject)}</div>
                    <div class="email-preview">${this.escapeHtml(email.preview)}</div>
                </div>
                <div class="email-meta">
                    <div class="email-category category-${email.category}">${email.category}</div>
                    <div class="email-priority priority-${email.priority}">${email.priority} priority</div>
                    <div class="email-time">${this.formatTime(email.received_at)}</div>
                    <div class="email-confidence">AI: ${email.ai_confidence}%</div>
                </div>
            </div>
        `).join('');
    }
    
    async syncAllEmails() {
        try {
            const response = await fetch('/api/emails/sync-all', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            });
            
            const data = await response.json();
            if (data.success) {
                this.loadEmails(); // Reload to update UI
                this.showNotification(`Synced ${data.total_new_emails} new emails`, 'success');
            } else {
                this.showNotification(data.message || 'Sync failed', 'error');
            }
        } catch (error) {
            console.error('Failed to sync emails:', error);
            this.showNotification('Sync failed', 'error');
        }
    }
    
    async processEmails() {
        try {
            const response = await fetch('/api/emails/process', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            });
            
            const data = await response.json();
            if (data.success) {
                this.loadEmails(); // Reload to update UI
                this.showNotification(`Processed ${data.processed_emails} emails with AI`, 'success');
            } else {
                this.showNotification(data.message || 'Processing failed', 'error');
            }
        } catch (error) {
            console.error('Failed to process emails:', error);
            this.showNotification('Processing failed', 'error');
        }
    }
    
    async loadSettings() {
        try {
            const response = await fetch('/api/settings');
            const data = await response.json();
            
            if (data.success) {
                this.updateSettingsUI(data.settings);
            }
        } catch (error) {
            console.error('Failed to load settings:', error);
        }
    }
    
    updateSettingsUI(settings) {
        const allowRegistration = document.getElementById('allow-registration');
        if (allowRegistration) {
            allowRegistration.checked = settings.allow_registration === '1';
        }
        
        const autoProcess = document.getElementById('auto-process');
        if (autoProcess) {
            autoProcess.checked = settings.auto_process_emails === '1';
        }
    }
    
    async toggleRegistration() {
        const checkbox = document.getElementById('allow-registration');
        const value = checkbox.checked ? '1' : '0';
        
        try {
            const response = await fetch('/api/settings', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    setting: 'allow_registration',
                    value: value
                })
            });
            
            const data = await response.json();
            if (data.success) {
                this.showNotification('Registration setting updated', 'success');
            } else {
                this.showNotification(data.message || 'Failed to update setting', 'error');
                checkbox.checked = !checkbox.checked; // Revert
            }
        } catch (error) {
            console.error('Failed to update setting:', error);
            this.showNotification('Failed to update setting', 'error');
            checkbox.checked = !checkbox.checked; // Revert
        }
    }
    
    async toggleAutoProcess() {
        const checkbox = document.getElementById('auto-process');
        const value = checkbox.checked ? '1' : '0';
        
        try {
            const response = await fetch('/api/settings', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    setting: 'auto_process_emails',
                    value: value
                })
            });
            
            const data = await response.json();
            if (data.success) {
                this.showNotification('Auto-process setting updated', 'success');
            } else {
                this.showNotification(data.message || 'Failed to update setting', 'error');
                checkbox.checked = !checkbox.checked; // Revert
            }
        } catch (error) {
            console.error('Failed to update setting:', error);
            this.showNotification('Failed to update setting', 'error');
            checkbox.checked = !checkbox.checked; // Revert
        }
    }
    
    // Modal functions
    showModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('active');
        }
    }
    
    closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('active');
        }
    }
    
    // Utility functions
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    formatTime(timestamp) {
        if (!timestamp) return 'Never';
        const date = new Date(timestamp);
        const now = new Date();
        const diff = now - date;
        
        if (diff < 60000) return 'Just now';
        if (diff < 3600000) return Math.floor(diff / 60000) + 'm ago';
        if (diff < 86400000) return Math.floor(diff / 3600000) + 'h ago';
        if (diff < 604800000) return Math.floor(diff / 86400000) + 'd ago';
        
        return date.toLocaleDateString();
    }
    
    showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;
        
        // Add to page
        document.body.appendChild(notification);
        
        // Show with animation
        setTimeout(() => notification.classList.add('show'), 100);
        
        // Remove after 5 seconds
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 5000);
    }
}

// Global functions for onclick handlers
function showAddAIProvider() {
    app.showModal('add-ai-provider-modal');
}

function showAddEmailAccount() {
    app.showModal('add-email-account-modal');
}

function closeModal(modalId) {
    app.closeModal(modalId);
}

function updateEmailProviderFields() {
    app.updateEmailProviderFields();
}

function syncAllEmails() {
    app.syncAllEmails();
}

function processEmails() {
    app.processEmails();
}

function toggleRegistration() {
    app.toggleRegistration();
}

function toggleAutoProcess() {
    app.toggleAutoProcess();
}

// Initialize app when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.app = new ROTZEmailButler();
});

// Add notification styles
const notificationStyles = `
.notification {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 16px 20px;
    border-radius: 8px;
    color: white;
    font-weight: 500;
    z-index: 3000;
    transform: translateX(400px);
    transition: transform 0.3s ease;
    max-width: 400px;
}

.notification.show {
    transform: translateX(0);
}

.notification-success {
    background: #48bb78;
}

.notification-error {
    background: #f56565;
}

.notification-warning {
    background: #ed8936;
}

.notification-info {
    background: #4299e1;
}
`;

// Add styles to head
const styleSheet = document.createElement('style');
styleSheet.textContent = notificationStyles;
document.head.appendChild(styleSheet);

