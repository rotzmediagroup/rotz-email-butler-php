/**
 * ROTZ Email Butler - Real-Time Collaboration System
 * Enables team collaboration on email management with real-time updates
 */

const WebSocket = require('ws');
const Redis = require('redis');
const mysql = require('mysql2/promise');
const jwt = require('jsonwebtoken');
const { v4: uuidv4 } = require('uuid');

class RealTimeCollaboration {
    constructor(config = {}) {
        this.config = {
            port: config.port || 8080,
            redis: config.redis || { host: 'localhost', port: 6379 },
            mysql: config.mysql || {
                host: 'localhost',
                user: 'root',
                password: '',
                database: 'rotz_email_butler'
            },
            jwt_secret: config.jwt_secret || 'your-secret-key'
        };
        
        this.wss = null;
        this.redis = null;
        this.mysql = null;
        this.clients = new Map();
        this.rooms = new Map();
        
        this.init();
    }
    
    async init() {
        try {
            // Initialize Redis
            this.redis = Redis.createClient(this.config.redis);
            await this.redis.connect();
            console.log('Redis connected for collaboration');
            
            // Initialize MySQL
            this.mysql = await mysql.createConnection(this.config.mysql);
            console.log('MySQL connected for collaboration');
            
            // Initialize WebSocket server
            this.wss = new WebSocket.Server({ 
                port: this.config.port,
                verifyClient: this.verifyClient.bind(this)
            });
            
            this.wss.on('connection', this.handleConnection.bind(this));
            console.log(`Collaboration server running on port ${this.config.port}`);
            
            // Subscribe to Redis channels for cross-server communication
            const subscriber = this.redis.duplicate();
            await subscriber.connect();
            
            subscriber.subscribe('email_updates', this.handleEmailUpdate.bind(this));
            subscriber.subscribe('user_activity', this.handleUserActivity.bind(this));
            subscriber.subscribe('team_notifications', this.handleTeamNotification.bind(this));
            
        } catch (error) {
            console.error('Failed to initialize collaboration server:', error);
        }
    }
    
    verifyClient(info) {
        try {
            const url = new URL(info.req.url, 'http://localhost');
            const token = url.searchParams.get('token');
            
            if (!token) {
                return false;
            }
            
            const decoded = jwt.verify(token, this.config.jwt_secret);
            info.req.user = decoded;
            return true;
        } catch (error) {
            console.error('Token verification failed:', error);
            return false;
        }
    }
    
    handleConnection(ws, req) {
        const user = req.user;
        const clientId = uuidv4();
        
        console.log(`User ${user.id} connected with client ${clientId}`);
        
        // Store client information
        this.clients.set(clientId, {
            ws,
            user,
            rooms: new Set(),
            lastActivity: Date.now()
        });
        
        // Send welcome message
        this.sendToClient(clientId, {
            type: 'connected',
            clientId,
            user: {
                id: user.id,
                name: user.name,
                email: user.email
            }
        });
        
        // Handle messages
        ws.on('message', (data) => {
            try {
                const message = JSON.parse(data);
                this.handleMessage(clientId, message);
            } catch (error) {
                console.error('Invalid message format:', error);
            }
        });
        
        // Handle disconnection
        ws.on('close', () => {
            this.handleDisconnection(clientId);
        });
        
        // Update user activity
        this.updateUserActivity(user.id, 'online');
    }
    
    async handleMessage(clientId, message) {
        const client = this.clients.get(clientId);
        if (!client) return;
        
        client.lastActivity = Date.now();
        
        switch (message.type) {
            case 'join_room':
                await this.joinRoom(clientId, message.roomId);
                break;
                
            case 'leave_room':
                await this.leaveRoom(clientId, message.roomId);
                break;
                
            case 'email_action':
                await this.handleEmailAction(clientId, message);
                break;
                
            case 'team_message':
                await this.handleTeamMessage(clientId, message);
                break;
                
            case 'typing_indicator':
                await this.handleTypingIndicator(clientId, message);
                break;
                
            case 'email_assignment':
                await this.handleEmailAssignment(clientId, message);
                break;
                
            case 'collaboration_request':
                await this.handleCollaborationRequest(clientId, message);
                break;
                
            case 'screen_share':
                await this.handleScreenShare(clientId, message);
                break;
                
            default:
                console.log('Unknown message type:', message.type);
        }
    }
    
    async joinRoom(clientId, roomId) {
        const client = this.clients.get(clientId);
        if (!client) return;
        
        // Verify user has access to this room
        const hasAccess = await this.verifyRoomAccess(client.user.id, roomId);
        if (!hasAccess) {
            this.sendToClient(clientId, {
                type: 'error',
                message: 'Access denied to room'
            });
            return;
        }
        
        // Add client to room
        if (!this.rooms.has(roomId)) {
            this.rooms.set(roomId, new Set());
        }
        
        this.rooms.get(roomId).add(clientId);
        client.rooms.add(roomId);
        
        // Notify other room members
        this.broadcastToRoom(roomId, {
            type: 'user_joined',
            user: {
                id: client.user.id,
                name: client.user.name,
                email: client.user.email
            },
            roomId
        }, clientId);
        
        // Send room state to new member
        const roomState = await this.getRoomState(roomId);
        this.sendToClient(clientId, {
            type: 'room_joined',
            roomId,
            state: roomState
        });
        
        console.log(`User ${client.user.id} joined room ${roomId}`);
    }
    
    async leaveRoom(clientId, roomId) {
        const client = this.clients.get(clientId);
        if (!client) return;
        
        // Remove client from room
        if (this.rooms.has(roomId)) {
            this.rooms.get(roomId).delete(clientId);
            
            // Clean up empty rooms
            if (this.rooms.get(roomId).size === 0) {
                this.rooms.delete(roomId);
            }
        }
        
        client.rooms.delete(roomId);
        
        // Notify other room members
        this.broadcastToRoom(roomId, {
            type: 'user_left',
            user: {
                id: client.user.id,
                name: client.user.name
            },
            roomId
        });
        
        console.log(`User ${client.user.id} left room ${roomId}`);
    }
    
    async handleEmailAction(clientId, message) {
        const client = this.clients.get(clientId);
        if (!client) return;
        
        const { emailId, action, data } = message;
        
        try {
            // Verify user has access to this email
            const hasAccess = await this.verifyEmailAccess(client.user.id, emailId);
            if (!hasAccess) {
                this.sendToClient(clientId, {
                    type: 'error',
                    message: 'Access denied to email'
                });
                return;
            }
            
            // Process the email action
            await this.processEmailAction(emailId, action, data, client.user.id);
            
            // Broadcast to relevant rooms
            const roomIds = await this.getEmailRooms(emailId);
            for (const roomId of roomIds) {
                this.broadcastToRoom(roomId, {
                    type: 'email_updated',
                    emailId,
                    action,
                    data,
                    user: {
                        id: client.user.id,
                        name: client.user.name
                    },
                    timestamp: new Date().toISOString()
                });
            }
            
            // Publish to Redis for cross-server sync
            await this.redis.publish('email_updates', JSON.stringify({
                emailId,
                action,
                data,
                userId: client.user.id,
                timestamp: new Date().toISOString()
            }));
            
        } catch (error) {
            console.error('Error handling email action:', error);
            this.sendToClient(clientId, {
                type: 'error',
                message: 'Failed to process email action'
            });
        }
    }
    
    async handleTeamMessage(clientId, message) {
        const client = this.clients.get(clientId);
        if (!client) return;
        
        const { roomId, content, type: messageType } = message;
        
        // Save message to database
        const messageId = await this.saveTeamMessage({
            roomId,
            userId: client.user.id,
            content,
            type: messageType || 'text',
            timestamp: new Date()
        });
        
        // Broadcast to room
        this.broadcastToRoom(roomId, {
            type: 'team_message',
            messageId,
            roomId,
            content,
            messageType,
            user: {
                id: client.user.id,
                name: client.user.name,
                email: client.user.email
            },
            timestamp: new Date().toISOString()
        });
    }
    
    async handleTypingIndicator(clientId, message) {
        const client = this.clients.get(clientId);
        if (!client) return;
        
        const { roomId, isTyping } = message;
        
        // Broadcast typing indicator to room (except sender)
        this.broadcastToRoom(roomId, {
            type: 'typing_indicator',
            user: {
                id: client.user.id,
                name: client.user.name
            },
            isTyping,
            roomId
        }, clientId);
    }
    
    async handleEmailAssignment(clientId, message) {
        const client = this.clients.get(clientId);
        if (!client) return;
        
        const { emailId, assigneeId, note } = message;
        
        try {
            // Update email assignment in database
            await this.assignEmail(emailId, assigneeId, client.user.id, note);
            
            // Get assignee information
            const assignee = await this.getUserById(assigneeId);
            
            // Notify relevant users
            const notification = {
                type: 'email_assigned',
                emailId,
                assignee: {
                    id: assignee.id,
                    name: assignee.name,
                    email: assignee.email
                },
                assignedBy: {
                    id: client.user.id,
                    name: client.user.name
                },
                note,
                timestamp: new Date().toISOString()
            };
            
            // Send to assignee
            this.sendToUser(assigneeId, notification);
            
            // Broadcast to relevant rooms
            const roomIds = await this.getEmailRooms(emailId);
            for (const roomId of roomIds) {
                this.broadcastToRoom(roomId, notification);
            }
            
        } catch (error) {
            console.error('Error handling email assignment:', error);
            this.sendToClient(clientId, {
                type: 'error',
                message: 'Failed to assign email'
            });
        }
    }
    
    async handleCollaborationRequest(clientId, message) {
        const client = this.clients.get(clientId);
        if (!client) return;
        
        const { targetUserId, emailId, requestType } = message;
        
        // Send collaboration request to target user
        this.sendToUser(targetUserId, {
            type: 'collaboration_request',
            from: {
                id: client.user.id,
                name: client.user.name,
                email: client.user.email
            },
            emailId,
            requestType,
            timestamp: new Date().toISOString()
        });
    }
    
    async handleScreenShare(clientId, message) {
        const client = this.clients.get(clientId);
        if (!client) return;
        
        const { roomId, action, data } = message;
        
        // Broadcast screen share data to room
        this.broadcastToRoom(roomId, {
            type: 'screen_share',
            action,
            data,
            user: {
                id: client.user.id,
                name: client.user.name
            },
            timestamp: new Date().toISOString()
        }, clientId);
    }
    
    handleDisconnection(clientId) {
        const client = this.clients.get(clientId);
        if (!client) return;
        
        console.log(`User ${client.user.id} disconnected`);
        
        // Leave all rooms
        for (const roomId of client.rooms) {
            this.leaveRoom(clientId, roomId);
        }
        
        // Remove client
        this.clients.delete(clientId);
        
        // Update user activity
        this.updateUserActivity(client.user.id, 'offline');
    }
    
    // Utility methods
    
    sendToClient(clientId, message) {
        const client = this.clients.get(clientId);
        if (client && client.ws.readyState === WebSocket.OPEN) {
            client.ws.send(JSON.stringify(message));
        }
    }
    
    sendToUser(userId, message) {
        for (const [clientId, client] of this.clients) {
            if (client.user.id === userId) {
                this.sendToClient(clientId, message);
            }
        }
    }
    
    broadcastToRoom(roomId, message, excludeClientId = null) {
        if (!this.rooms.has(roomId)) return;
        
        for (const clientId of this.rooms.get(roomId)) {
            if (clientId !== excludeClientId) {
                this.sendToClient(clientId, message);
            }
        }
    }
    
    async verifyRoomAccess(userId, roomId) {
        try {
            const [rows] = await this.mysql.execute(
                'SELECT 1 FROM team_members tm JOIN teams t ON tm.team_id = t.id WHERE tm.user_id = ? AND t.room_id = ?',
                [userId, roomId]
            );
            return rows.length > 0;
        } catch (error) {
            console.error('Error verifying room access:', error);
            return false;
        }
    }
    
    async verifyEmailAccess(userId, emailId) {
        try {
            const [rows] = await this.mysql.execute(
                'SELECT 1 FROM emails e JOIN email_accounts ea ON e.email_account_id = ea.id WHERE ea.user_id = ? AND e.id = ?',
                [userId, emailId]
            );
            return rows.length > 0;
        } catch (error) {
            console.error('Error verifying email access:', error);
            return false;
        }
    }
    
    async getRoomState(roomId) {
        try {
            // Get recent messages
            const [messages] = await this.mysql.execute(
                'SELECT * FROM team_messages WHERE room_id = ? ORDER BY created_at DESC LIMIT 50',
                [roomId]
            );
            
            // Get active users in room
            const activeUsers = [];
            if (this.rooms.has(roomId)) {
                for (const clientId of this.rooms.get(roomId)) {
                    const client = this.clients.get(clientId);
                    if (client) {
                        activeUsers.push({
                            id: client.user.id,
                            name: client.user.name,
                            email: client.user.email
                        });
                    }
                }
            }
            
            return {
                messages: messages.reverse(),
                activeUsers
            };
        } catch (error) {
            console.error('Error getting room state:', error);
            return { messages: [], activeUsers: [] };
        }
    }
    
    async processEmailAction(emailId, action, data, userId) {
        const actions = {
            'mark_read': 'UPDATE emails SET is_read = 1, read_at = NOW() WHERE id = ?',
            'mark_unread': 'UPDATE emails SET is_read = 0, read_at = NULL WHERE id = ?',
            'archive': 'UPDATE emails SET is_archived = 1, archived_at = NOW() WHERE id = ?',
            'unarchive': 'UPDATE emails SET is_archived = 0, archived_at = NULL WHERE id = ?',
            'delete': 'UPDATE emails SET is_deleted = 1, deleted_at = NOW() WHERE id = ?',
            'restore': 'UPDATE emails SET is_deleted = 0, deleted_at = NULL WHERE id = ?',
            'set_priority': 'UPDATE emails SET priority = ? WHERE id = ?',
            'add_label': 'INSERT INTO email_labels (email_id, label) VALUES (?, ?)',
            'remove_label': 'DELETE FROM email_labels WHERE email_id = ? AND label = ?'
        };
        
        if (actions[action]) {
            const params = action === 'set_priority' || action === 'add_label' || action === 'remove_label' 
                ? [data.value || data.label, emailId] 
                : [emailId];
            
            await this.mysql.execute(actions[action], params);
            
            // Log the action
            await this.mysql.execute(
                'INSERT INTO email_activity_log (email_id, user_id, action, data, created_at) VALUES (?, ?, ?, ?, NOW())',
                [emailId, userId, action, JSON.stringify(data)]
            );
        }
    }
    
    async getEmailRooms(emailId) {
        try {
            const [rows] = await this.mysql.execute(
                'SELECT DISTINCT t.room_id FROM emails e JOIN email_accounts ea ON e.email_account_id = ea.id JOIN team_members tm ON ea.user_id = tm.user_id JOIN teams t ON tm.team_id = t.id WHERE e.id = ?',
                [emailId]
            );
            return rows.map(row => row.room_id);
        } catch (error) {
            console.error('Error getting email rooms:', error);
            return [];
        }
    }
    
    async saveTeamMessage(messageData) {
        try {
            const [result] = await this.mysql.execute(
                'INSERT INTO team_messages (room_id, user_id, content, type, created_at) VALUES (?, ?, ?, ?, ?)',
                [messageData.roomId, messageData.userId, messageData.content, messageData.type, messageData.timestamp]
            );
            return result.insertId;
        } catch (error) {
            console.error('Error saving team message:', error);
            return null;
        }
    }
    
    async assignEmail(emailId, assigneeId, assignedById, note) {
        await this.mysql.execute(
            'INSERT INTO email_assignments (email_id, assignee_id, assigned_by_id, note, created_at) VALUES (?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE assignee_id = VALUES(assignee_id), assigned_by_id = VALUES(assigned_by_id), note = VALUES(note), updated_at = NOW()',
            [emailId, assigneeId, assignedById, note]
        );
    }
    
    async getUserById(userId) {
        try {
            const [rows] = await this.mysql.execute(
                'SELECT id, name, email FROM users WHERE id = ?',
                [userId]
            );
            return rows[0] || null;
        } catch (error) {
            console.error('Error getting user:', error);
            return null;
        }
    }
    
    async updateUserActivity(userId, status) {
        try {
            await this.redis.hset(`user_activity:${userId}`, {
                status,
                last_seen: Date.now()
            });
            
            // Publish user activity update
            await this.redis.publish('user_activity', JSON.stringify({
                userId,
                status,
                timestamp: new Date().toISOString()
            }));
        } catch (error) {
            console.error('Error updating user activity:', error);
        }
    }
    
    // Redis event handlers
    
    async handleEmailUpdate(message) {
        try {
            const data = JSON.parse(message);
            
            // Broadcast to relevant clients
            const roomIds = await this.getEmailRooms(data.emailId);
            for (const roomId of roomIds) {
                this.broadcastToRoom(roomId, {
                    type: 'email_updated_external',
                    ...data
                });
            }
        } catch (error) {
            console.error('Error handling email update:', error);
        }
    }
    
    async handleUserActivity(message) {
        try {
            const data = JSON.parse(message);
            
            // Broadcast user activity to all clients
            for (const [clientId, client] of this.clients) {
                this.sendToClient(clientId, {
                    type: 'user_activity',
                    ...data
                });
            }
        } catch (error) {
            console.error('Error handling user activity:', error);
        }
    }
    
    async handleTeamNotification(message) {
        try {
            const data = JSON.parse(message);
            
            // Send notification to specific user or broadcast to team
            if (data.userId) {
                this.sendToUser(data.userId, {
                    type: 'team_notification',
                    ...data
                });
            } else if (data.teamId) {
                // Broadcast to all team members
                const teamMembers = await this.getTeamMembers(data.teamId);
                for (const member of teamMembers) {
                    this.sendToUser(member.user_id, {
                        type: 'team_notification',
                        ...data
                    });
                }
            }
        } catch (error) {
            console.error('Error handling team notification:', error);
        }
    }
    
    async getTeamMembers(teamId) {
        try {
            const [rows] = await this.mysql.execute(
                'SELECT user_id FROM team_members WHERE team_id = ?',
                [teamId]
            );
            return rows;
        } catch (error) {
            console.error('Error getting team members:', error);
            return [];
        }
    }
    
    // Health check and monitoring
    
    getServerStats() {
        return {
            connectedClients: this.clients.size,
            activeRooms: this.rooms.size,
            uptime: process.uptime(),
            memoryUsage: process.memoryUsage()
        };
    }
    
    // Cleanup inactive connections
    
    startCleanupInterval() {
        setInterval(() => {
            const now = Date.now();
            const timeout = 5 * 60 * 1000; // 5 minutes
            
            for (const [clientId, client] of this.clients) {
                if (now - client.lastActivity > timeout) {
                    console.log(`Cleaning up inactive client ${clientId}`);
                    client.ws.terminate();
                    this.handleDisconnection(clientId);
                }
            }
        }, 60000); // Check every minute
    }
}

// Start the collaboration server
if (require.main === module) {
    const config = {
        port: process.env.COLLABORATION_PORT || 8080,
        redis: {
            host: process.env.REDIS_HOST || 'localhost',
            port: process.env.REDIS_PORT || 6379
        },
        mysql: {
            host: process.env.DB_HOST || 'localhost',
            user: process.env.DB_USER || 'root',
            password: process.env.DB_PASSWORD || '',
            database: process.env.DB_NAME || 'rotz_email_butler'
        },
        jwt_secret: process.env.JWT_SECRET || 'your-secret-key'
    };
    
    const collaboration = new RealTimeCollaboration(config);
    collaboration.startCleanupInterval();
    
    // Graceful shutdown
    process.on('SIGINT', async () => {
        console.log('Shutting down collaboration server...');
        
        if (collaboration.redis) {
            await collaboration.redis.quit();
        }
        
        if (collaboration.mysql) {
            await collaboration.mysql.end();
        }
        
        if (collaboration.wss) {
            collaboration.wss.close();
        }
        
        process.exit(0);
    });
}

module.exports = RealTimeCollaboration;

