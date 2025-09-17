// HG Community Main JavaScript
class HGCommunity {
    constructor() {
        this.currentChannel = null;
        this.currentUser = window.currentUser || null;
        this.init();
    }
    
    init() {
        this.loadChannels();
        this.setupEventListeners();
        this.loadOnlineMembers();
        
        // Auto-refresh messages every 5 seconds
        setInterval(() => {
            if (this.currentChannel) {
                this.loadMessages(this.currentChannel.id, false);
            }
        }, 5000);
    }
    
    setupEventListeners() {
        // Message form
        const messageForm = document.getElementById('message-form');
        if (messageForm) {
            messageForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.sendMessage();
            });
        }
        
        // File input
        const fileBtn = document.getElementById('file-btn');
        const fileInput = document.getElementById('file-input');
        if (fileBtn && fileInput) {
            fileBtn.addEventListener('click', () => fileInput.click());
            fileInput.addEventListener('change', this.handleFileSelect.bind(this));
        }
        
        // Create channel modal
        const createChannelBtn = document.getElementById('create-channel-btn');
        const createChannelModal = document.getElementById('create-channel-modal');
        if (createChannelBtn && createChannelModal) {
            createChannelBtn.addEventListener('click', () => {
                createChannelModal.style.display = 'block';
            });
        }
        
        // Create invite modal
        const createInviteBtn = document.getElementById('create-invite-btn');
        const createInviteModal = document.getElementById('create-invite-modal');
        if (createInviteBtn && createInviteModal) {
            createInviteBtn.addEventListener('click', () => {
                createInviteModal.style.display = 'block';
            });
        }
        
        // Modal close buttons
        document.querySelectorAll('.modal .close').forEach(closeBtn => {
            closeBtn.addEventListener('click', (e) => {
                e.target.closest('.modal').style.display = 'none';
            });
        });
        
        // Close modals when clicking outside
        window.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) {
                e.target.style.display = 'none';
            }
        });
        
        // Create channel form
        const createChannelForm = document.getElementById('create-channel-form');
        if (createChannelForm) {
            createChannelForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.createChannel();
            });
        }
        
        // Create invite form
        const createInviteForm = document.getElementById('create-invite-form');
        if (createInviteForm) {
            createInviteForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.createInvite();
            });
        }
        
        // Channel type change
        const channelType = document.getElementById('channel-type');
        const teamNameGroup = document.getElementById('team-name-group');
        if (channelType && teamNameGroup) {
            channelType.addEventListener('change', (e) => {
                teamNameGroup.style.display = e.target.value === 'team' ? 'block' : 'none';
            });
        }
        
        // Logout button
        const logoutBtn = document.getElementById('logout-btn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', () => this.logout());
        }
    }
    
    async loadChannels() {
        try {
            const response = await fetch('api/channels.php');
            const data = await response.json();
            
            if (data.success) {
                this.displayChannels(data.channels);
            } else {
                console.error('Failed to load channels:', data.message);
            }
        } catch (error) {
            console.error('Error loading channels:', error);
        }
    }
    
    displayChannels(channels) {
        const channelContainers = {
            announcement: document.getElementById('announcement-channels'),
            team: document.getElementById('team-channels'),
            technical: document.getElementById('technical-channels'),
            general: document.getElementById('general-channels')
        };
        
        // Clear existing channels
        Object.values(channelContainers).forEach(container => {
            if (container) container.innerHTML = '';
        });
        
        channels.forEach(channel => {
            const container = channelContainers[channel.type];
            if (container) {
                const channelElement = this.createChannelElement(channel);
                container.appendChild(channelElement);
            }
        });
    }
    
    createChannelElement(channel) {
        const channelDiv = document.createElement('div');
        channelDiv.className = 'channel-item';
        channelDiv.dataset.channelId = channel.id;
        
        const icon = this.getChannelIcon(channel.type);
        const displayName = channel.team_name ? `${channel.name} (${channel.team_name})` : channel.name;
        
        channelDiv.innerHTML = `
            <span class="channel-icon">${icon}</span>
            <span class="channel-name">${displayName}</span>
        `;
        
        channelDiv.addEventListener('click', () => this.selectChannel(channel));
        
        return channelDiv;
    }
    
    getChannelIcon(type) {
        const icons = {
            announcement: '📢',
            team: '👥',
            technical: '💻',
            general: '💬'
        };
        return icons[type] || '💬';
    }
    
    selectChannel(channel) {
        // Update UI
        document.querySelectorAll('.channel-item').forEach(item => {
            item.classList.remove('active');
        });
        
        const channelElement = document.querySelector(`[data-channel-id="${channel.id}"]`);
        if (channelElement) {
            channelElement.classList.add('active');
        }
        
        // Update current channel
        this.currentChannel = channel;
        document.getElementById('current-channel').textContent = channel.name;
        document.getElementById('current-channel-id').value = channel.id;
        
        // Show message input
        document.querySelector('.message-input-container').style.display = 'block';
        
        // Load messages
        this.loadMessages(channel.id);
    }
    
    async loadMessages(channelId, scrollToBottom = true) {
        try {
            const response = await fetch(`api/messages.php?channel_id=${channelId}`);
            const data = await response.json();
            
            if (data.success) {
                this.displayMessages(data.messages, scrollToBottom);
            } else {
                console.error('Failed to load messages:', data.message);
            }
        } catch (error) {
            console.error('Error loading messages:', error);
        }
    }
    
    displayMessages(messages, scrollToBottom = true) {
        const container = document.getElementById('messages-container');
        container.innerHTML = '';
        
        messages.forEach(message => {
            const messageElement = this.createMessageElement(message);
            container.appendChild(messageElement);
        });
        
        if (scrollToBottom) {
            container.scrollTop = container.scrollHeight;
        }
    }
    
    createMessageElement(message) {
        const messageDiv = document.createElement('div');
        messageDiv.className = 'message';
        
        const timestamp = new Date(message.created_at).toLocaleTimeString();
        const displayName = message.display_name || message.username;
        const roleClass = `role-${message.role}`;
        
        messageDiv.innerHTML = `
            <div class="message-header">
                <img src="${message.avatar || 'assets/images/default-avatar.png'}" alt="Avatar" class="message-avatar">
                <span class="message-author ${roleClass}">${displayName}</span>
                <span class="message-timestamp">${timestamp}</span>
            </div>
            <div class="message-content">${this.escapeHtml(message.content)}</div>
        `;
        
        return messageDiv;
    }
    
    async sendMessage() {
        const content = document.getElementById('message-input').value.trim();
        const channelId = document.getElementById('current-channel-id').value;
        
        if (!content || !channelId) return;
        
        try {
            const response = await fetch('api/messages.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    channel_id: parseInt(channelId),
                    content: content
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                document.getElementById('message-input').value = '';
                this.loadMessages(channelId);
            } else {
                alert('Error sending message: ' + data.message);
            }
        } catch (error) {
            console.error('Error sending message:', error);
            alert('Error sending message');
        }
    }
    
    async createChannel() {
        const formData = new FormData(document.getElementById('create-channel-form'));
        const channelData = {
            name: formData.get('name'),
            description: formData.get('description'),
            type: formData.get('type'),
            team_name: formData.get('team_name')
        };
        
        try {
            const response = await fetch('api/channels.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(channelData)
            });
            
            const data = await response.json();
            
            if (data.success) {
                document.getElementById('create-channel-modal').style.display = 'none';
                document.getElementById('create-channel-form').reset();
                this.loadChannels();
                alert('Channel created successfully!');
            } else {
                alert('Error creating channel: ' + data.message);
            }
        } catch (error) {
            console.error('Error creating channel:', error);
            alert('Error creating channel');
        }
    }
    
    async createInvite() {
        const formData = new FormData(document.getElementById('create-invite-form'));
        const inviteData = {
            email: formData.get('email'),
            phone: formData.get('phone'),
            role: formData.get('role'),
            expiry_hours: parseInt(formData.get('expiry_hours'))
        };
        
        try {
            const response = await fetch('api/invites.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(inviteData)
            });
            
            const data = await response.json();
            
            if (data.success) {
                document.getElementById('invite-url').value = data.invite.url;
                document.getElementById('invite-code').value = data.invite.code;
                document.getElementById('invite-result').style.display = 'block';
                document.getElementById('create-invite-form').style.display = 'none';
            } else {
                alert('Error creating invite: ' + data.message);
            }
        } catch (error) {
            console.error('Error creating invite:', error);
            alert('Error creating invite');
        }
    }
    
    handleFileSelect(event) {
        const file = event.target.files[0];
        if (file) {
            const preview = document.getElementById('file-preview');
            preview.innerHTML = `
                <div class="file-item">
                    <span>${file.name}</span>
                    <button type="button" onclick="this.parentElement.parentElement.style.display='none'">×</button>
                </div>
            `;
            preview.style.display = 'block';
        }
    }
    
    loadOnlineMembers() {
        // Placeholder for online members functionality
        const membersList = document.getElementById('members-list');
        const onlineCount = document.getElementById('online-count');
        
        if (membersList && onlineCount) {
            membersList.innerHTML = '<div class="member-item">Loading members...</div>';
            onlineCount.textContent = '0 online';
        }
    }
    
    logout() {
        if (confirm('Are you sure you want to logout?')) {
            window.location.href = 'api/logout.php';
        }
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Copy invite link function
function copyInviteLink() {
    const inviteUrl = document.getElementById('invite-url');
    inviteUrl.select();
    document.execCommand('copy');
    alert('Invite link copied to clipboard!');
}

// Initialize the app when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.app = new HGCommunity();
});