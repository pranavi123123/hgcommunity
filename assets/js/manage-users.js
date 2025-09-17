// Manage Users JavaScript
class UserManager {
    constructor() {
        this.init();
    }
    
    init() {
        this.loadUsers();
        this.setupEventListeners();
    }
    
    setupEventListeners() {
        const refreshBtn = document.getElementById('refresh-users');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => this.loadUsers());
        }
    }
    
    async loadUsers() {
        try {
            const response = await fetch('api/users.php');
            const data = await response.json();
            
            if (data.success) {
                this.displayUsers(data.users);
            } else {
                console.error('Failed to load users:', data.message);
                this.showError('Failed to load users: ' + data.message);
            }
        } catch (error) {
            console.error('Error loading users:', error);
            this.showError('Error loading users');
        }
    }
    
    displayUsers(users) {
        const tbody = document.getElementById('users-tbody');
        tbody.innerHTML = '';
        
        users.forEach(user => {
            const row = this.createUserRow(user);
            tbody.appendChild(row);
        });
    }
    
    createUserRow(user) {
        const row = document.createElement('tr');
        
        const lastActive = user.last_active ? 
            new Date(user.last_active).toLocaleDateString() : 'Never';
        
        const statusClass = user.status === 'active' ? 'status-active' : 
                           user.status === 'banned' ? 'status-banned' : 'status-inactive';
        
        row.innerHTML = `
            <td>${this.escapeHtml(user.username)}</td>
            <td>${this.escapeHtml(user.email)}</td>
            <td>
                <select class="role-select" data-user-id="${user.id}" ${user.id === window.currentUser.id ? 'disabled' : ''}>
                    <option value="member" ${user.role === 'member' ? 'selected' : ''}>Member</option>
                    <option value="moderator" ${user.role === 'moderator' ? 'selected' : ''}>Moderator</option>
                    <option value="admin" ${user.role === 'admin' ? 'selected' : ''}>Admin</option>
                </select>
            </td>
            <td>
                <select class="status-select" data-user-id="${user.id}" ${user.id === window.currentUser.id ? 'disabled' : ''}>
                    <option value="active" ${user.status === 'active' ? 'selected' : ''}>Active</option>
                    <option value="restricted" ${user.status === 'restricted' ? 'selected' : ''}>Restricted</option>
                    <option value="muted" ${user.status === 'muted' ? 'selected' : ''}>Muted</option>
                    <option value="banned" ${user.status === 'banned' ? 'selected' : ''}>Banned</option>
                </select>
            </td>
            <td>${lastActive}</td>
            <td>
                <button class="btn-primary btn-sm" onclick="userManager.updateUser(${user.id})" ${user.id === window.currentUser.id ? 'disabled' : ''}>
                    Update
                </button>
            </td>
        `;
        
        return row;
    }
    
    async updateUser(userId) {
        const roleSelect = document.querySelector(`.role-select[data-user-id="${userId}"]`);
        const statusSelect = document.querySelector(`.status-select[data-user-id="${userId}"]`);
        
        if (!roleSelect || !statusSelect) return;
        
        const updateData = {
            user_id: userId,
            role: roleSelect.value,
            status: statusSelect.value
        };
        
        try {
            const response = await fetch('api/users.php', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(updateData)
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showSuccess('User updated successfully');
                this.loadUsers(); // Refresh the list
            } else {
                this.showError('Failed to update user: ' + data.message);
            }
        } catch (error) {
            console.error('Error updating user:', error);
            this.showError('Error updating user');
        }
    }
    
    showError(message) {
        // Create or update error message
        let errorDiv = document.getElementById('error-message');
        if (!errorDiv) {
            errorDiv = document.createElement('div');
            errorDiv.id = 'error-message';
            errorDiv.className = 'alert alert-error';
            document.querySelector('.admin-content').insertBefore(errorDiv, document.querySelector('.users-section'));
        }
        errorDiv.textContent = message;
        errorDiv.style.display = 'block';
        
        setTimeout(() => {
            errorDiv.style.display = 'none';
        }, 5000);
    }
    
    showSuccess(message) {
        // Create or update success message
        let successDiv = document.getElementById('success-message');
        if (!successDiv) {
            successDiv = document.createElement('div');
            successDiv.id = 'success-message';
            successDiv.className = 'alert alert-success';
            document.querySelector('.admin-content').insertBefore(successDiv, document.querySelector('.users-section'));
        }
        successDiv.textContent = message;
        successDiv.style.display = 'block';
        
        setTimeout(() => {
            successDiv.style.display = 'none';
        }, 3000);
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.userManager = new UserManager();
});