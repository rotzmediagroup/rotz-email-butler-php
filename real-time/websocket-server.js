/**
 * ROTZ Email Butler - WebSocket Server
 * Real-time communication server for live updates
 */

const WebSocket = require('ws');
const http = require('http');
const express = require('express');
const cors = require('cors');
const jwt = require('jsonwebtoken');
const mysql = require('mysql2/promise');
const Redis = require('redis');
const { v4: uuidv4 } = require('uuid');

// Configuration
const PORT = process.env.WS_PORT || 8080;
const JWT_SECRET = process.env.JWT_SECRET || 'your-jwt-secret-key';
const REDIS_URL = process.env.REDIS_URL || 'redis://localhost:6379';

// Database configuration
const DB_CONFIG = {
  host: process.env.DB_HOST || 'localhost',
  user: process.env.DB_USER || 'root',
  password: process.env.DB_PASSWORD || '',
  database: process.env.DB_NAME || 'rotz_email_butler',
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0
};

// Initialize Express app
const app = express();
app.use(cors());
app.use(express.json());

// Create HTTP server
const server = http.createServer(app);

// Create WebSocket server
const wss = new WebSocket.Server({ 
  server,
  verifyClient: (info) => {
    // Basic verification - can be enhanced with more security
    return true;
  }
});

// Initialize database connection pool
const dbPool = mysql.createPool(DB_CONFIG);

// Initialize Redis client
const redisClient = Redis.createClient({ url: REDIS_URL });
redisClient.on('error', (err) => console.error('Redis Client Error', err));

// Connected clients storage
const clients = new Map();
const userSessions = new Map();

// WebSocket connection handler
wss.on('connection', async (ws, request) => {
  const clientId = uuidv4();
  console.log(`New WebSocket connection: ${clientId}`);

  // Store client connection
  clients.set(clientId, {
    ws,
    userId: null,
    authenticated: false,
    subscriptions: new Set(),
    lastActivity: Date.now()
  });

  // Send welcome message
  ws.send(JSON.stringify({
    type: 'connection',
    message: 'Connected to ROTZ Email Butler WebSocket server',
    clientId
  }));

  // Message handler
  ws.on('message', async (message) => {
    try {
      const data = JSON.parse(message);
      await handleMessage(clientId, data);
    } catch (error) {
      console.error('Message handling error:', error);
      sendError(clientId, 'Invalid message format');
    }
  });

  // Connection close handler
  ws.on('close', () => {
    console.log(`WebSocket connection closed: ${clientId}`);
    const client = clients.get(clientId);
    if (client && client.userId) {
      // Remove from user sessions
      const userClients = userSessions.get(client.userId) || new Set();
      userClients.delete(clientId);
      if (userClients.size === 0) {
        userSessions.delete(client.userId);
      } else {
        userSessions.set(client.userId, userClients);
      }
    }
    clients.delete(clientId);
  });

  // Error handler
  ws.on('error', (error) => {
    console.error(`WebSocket error for client ${clientId}:`, error);
  });
});

// Message handling function
async function handleMessage(clientId, data) {
  const client = clients.get(clientId);
  if (!client) return;

  client.lastActivity = Date.now();

  switch (data.type) {
    case 'authenticate':
      await handleAuthentication(clientId, data);
      break;
    
    case 'subscribe':
      await handleSubscription(clientId, data);
      break;
    
    case 'unsubscribe':
      await handleUnsubscription(clientId, data);
      break;
    
    case 'ping':
      sendMessage(clientId, { type: 'pong', timestamp: Date.now() });
      break;
    
    case 'email_sync_request':
      await handleEmailSyncRequest(clientId, data);
      break;
    
    case 'ai_process_request':
      await handleAIProcessRequest(clientId, data);
      break;
    
    case 'get_real_time_stats':
      await handleRealTimeStats(clientId);
      break;
    
    default:
      sendError(clientId, `Unknown message type: ${data.type}`);
  }
}

// Authentication handler
async function handleAuthentication(clientId, data) {
  try {
    const { token } = data;
    
    if (!token) {
      sendError(clientId, 'Authentication token required');
      return;
    }

    // Verify JWT token
    const decoded = jwt.verify(token, JWT_SECRET);
    const userId = decoded.userId;

    // Verify user exists in database
    const [users] = await dbPool.execute(
      'SELECT id, email, display_name, role FROM users WHERE id = ? AND status = "active"',
      [userId]
    );

    if (users.length === 0) {
      sendError(clientId, 'Invalid user');
      return;
    }

    const user = users[0];
    const client = clients.get(clientId);
    
    // Update client info
    client.userId = userId;
    client.authenticated = true;
    client.user = user;

    // Add to user sessions
    if (!userSessions.has(userId)) {
      userSessions.set(userId, new Set());
    }
    userSessions.get(userId).add(clientId);

    // Send authentication success
    sendMessage(clientId, {
      type: 'authenticated',
      user: {
        id: user.id,
        email: user.email,
        displayName: user.display_name,
        role: user.role
      }
    });

    // Send initial data
    await sendInitialData(clientId);

    console.log(`User ${user.email} authenticated on client ${clientId}`);

  } catch (error) {
    console.error('Authentication error:', error);
    sendError(clientId, 'Authentication failed');
  }
}

// Subscription handler
async function handleSubscription(clientId, data) {
  const client = clients.get(clientId);
  if (!client || !client.authenticated) {
    sendError(clientId, 'Authentication required');
    return;
  }

  const { channels } = data;
  if (!Array.isArray(channels)) {
    sendError(clientId, 'Channels must be an array');
    return;
  }

  // Add subscriptions
  channels.forEach(channel => {
    if (isValidChannel(channel, client.userId)) {
      client.subscriptions.add(channel);
    }
  });

  sendMessage(clientId, {
    type: 'subscribed',
    channels: Array.from(client.subscriptions)
  });
}

// Unsubscription handler
async function handleUnsubscription(clientId, data) {
  const client = clients.get(clientId);
  if (!client) return;

  const { channels } = data;
  if (!Array.isArray(channels)) {
    sendError(clientId, 'Channels must be an array');
    return;
  }

  // Remove subscriptions
  channels.forEach(channel => {
    client.subscriptions.delete(channel);
  });

  sendMessage(clientId, {
    type: 'unsubscribed',
    channels: Array.from(client.subscriptions)
  });
}

// Email sync request handler
async function handleEmailSyncRequest(clientId, data) {
  const client = clients.get(clientId);
  if (!client || !client.authenticated) {
    sendError(clientId, 'Authentication required');
    return;
  }

  try {
    const { accountId } = data;
    
    // Trigger email sync (this would typically call your PHP API)
    const response = await fetch(`http://localhost/api/email-accounts/${accountId}/sync`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${data.token}`,
        'Content-Type': 'application/json'
      }
    });

    const result = await response.json();

    if (result.success) {
      // Broadcast sync completion to user's clients
      broadcastToUser(client.userId, {
        type: 'email_sync_complete',
        accountId,
        newEmails: result.new_emails
      });
    } else {
      sendError(clientId, `Sync failed: ${result.message}`);
    }

  } catch (error) {
    console.error('Email sync error:', error);
    sendError(clientId, 'Email sync failed');
  }
}

// AI processing request handler
async function handleAIProcessRequest(clientId, data) {
  const client = clients.get(clientId);
  if (!client || !client.authenticated) {
    sendError(clientId, 'Authentication required');
    return;
  }

  try {
    // Trigger AI processing (this would typically call your PHP API)
    const response = await fetch('http://localhost/api/emails/process', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${data.token}`,
        'Content-Type': 'application/json'
      }
    });

    const result = await response.json();

    if (result.success) {
      // Broadcast processing completion to user's clients
      broadcastToUser(client.userId, {
        type: 'ai_processing_complete',
        processedEmails: result.processed_emails
      });
    } else {
      sendError(clientId, `AI processing failed: ${result.message}`);
    }

  } catch (error) {
    console.error('AI processing error:', error);
    sendError(clientId, 'AI processing failed');
  }
}

// Real-time stats handler
async function handleRealTimeStats(clientId) {
  const client = clients.get(clientId);
  if (!client || !client.authenticated) {
    sendError(clientId, 'Authentication required');
    return;
  }

  try {
    // Get real-time statistics from database
    const [emailStats] = await dbPool.execute(`
      SELECT 
        COUNT(*) as total_emails,
        COUNT(CASE WHEN category IS NOT NULL THEN 1 END) as processed_emails,
        COUNT(CASE WHEN received_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 1 END) as recent_emails,
        AVG(ai_confidence) as avg_confidence
      FROM emails 
      WHERE user_id = ?
    `, [client.userId]);

    const [providerStats] = await dbPool.execute(`
      SELECT 
        COUNT(*) as total_providers,
        COUNT(CASE WHEN is_enabled = 1 THEN 1 END) as active_providers
      FROM ai_providers 
      WHERE user_id = ?
    `, [client.userId]);

    const [accountStats] = await dbPool.execute(`
      SELECT 
        COUNT(*) as total_accounts,
        COUNT(CASE WHEN status = 'active' THEN 1 END) as active_accounts
      FROM email_accounts 
      WHERE user_id = ?
    `, [client.userId]);

    sendMessage(clientId, {
      type: 'real_time_stats',
      stats: {
        emails: emailStats[0],
        providers: providerStats[0],
        accounts: accountStats[0],
        timestamp: Date.now()
      }
    });

  } catch (error) {
    console.error('Real-time stats error:', error);
    sendError(clientId, 'Failed to get real-time stats');
  }
}

// Send initial data to newly authenticated client
async function sendInitialData(clientId) {
  const client = clients.get(clientId);
  if (!client || !client.authenticated) return;

  try {
    // Send dashboard data
    await handleRealTimeStats(clientId);

    // Send recent activity
    const [recentActivity] = await dbPool.execute(`
      SELECT action, description, created_at 
      FROM activity_logs 
      WHERE user_id = ? 
      ORDER BY created_at DESC 
      LIMIT 10
    `, [client.userId]);

    sendMessage(clientId, {
      type: 'recent_activity',
      activities: recentActivity
    });

  } catch (error) {
    console.error('Initial data error:', error);
  }
}

// Utility functions
function sendMessage(clientId, message) {
  const client = clients.get(clientId);
  if (client && client.ws.readyState === WebSocket.OPEN) {
    client.ws.send(JSON.stringify(message));
  }
}

function sendError(clientId, error) {
  sendMessage(clientId, {
    type: 'error',
    message: error,
    timestamp: Date.now()
  });
}

function broadcastToUser(userId, message) {
  const userClients = userSessions.get(userId);
  if (userClients) {
    userClients.forEach(clientId => {
      sendMessage(clientId, message);
    });
  }
}

function broadcastToChannel(channel, message) {
  clients.forEach((client, clientId) => {
    if (client.authenticated && client.subscriptions.has(channel)) {
      sendMessage(clientId, message);
    }
  });
}

function isValidChannel(channel, userId) {
  // Validate channel access for user
  const validChannels = [
    `user:${userId}`,
    `user:${userId}:emails`,
    `user:${userId}:ai_processing`,
    `user:${userId}:sync`,
    'global:system_status'
  ];
  
  return validChannels.includes(channel);
}

// Periodic cleanup of inactive connections
setInterval(() => {
  const now = Date.now();
  const timeout = 5 * 60 * 1000; // 5 minutes

  clients.forEach((client, clientId) => {
    if (now - client.lastActivity > timeout) {
      console.log(`Cleaning up inactive client: ${clientId}`);
      client.ws.terminate();
      clients.delete(clientId);
    }
  });
}, 60000); // Check every minute

// Periodic stats broadcast
setInterval(async () => {
  // Broadcast system stats to all connected clients
  try {
    const [systemStats] = await dbPool.execute(`
      SELECT 
        COUNT(DISTINCT u.id) as total_users,
        COUNT(DISTINCT ea.id) as total_accounts,
        COUNT(DISTINCT e.id) as total_emails,
        COUNT(DISTINCT ap.id) as total_ai_providers
      FROM users u
      LEFT JOIN email_accounts ea ON u.id = ea.user_id
      LEFT JOIN emails e ON u.id = e.user_id
      LEFT JOIN ai_providers ap ON u.id = ap.user_id
    `);

    broadcastToChannel('global:system_status', {
      type: 'system_stats',
      stats: systemStats[0],
      timestamp: Date.now()
    });

  } catch (error) {
    console.error('System stats broadcast error:', error);
  }
}, 30000); // Every 30 seconds

// REST API endpoints for triggering real-time events
app.post('/api/trigger/email-received', async (req, res) => {
  try {
    const { userId, email } = req.body;
    
    broadcastToUser(userId, {
      type: 'new_email',
      email: {
        id: email.id,
        subject: email.subject,
        sender: email.sender_name,
        category: email.category,
        priority: email.priority,
        received_at: email.received_at
      }
    });

    res.json({ success: true });
  } catch (error) {
    res.status(500).json({ success: false, error: error.message });
  }
});

app.post('/api/trigger/ai-processing-complete', async (req, res) => {
  try {
    const { userId, results } = req.body;
    
    broadcastToUser(userId, {
      type: 'ai_processing_complete',
      results
    });

    res.json({ success: true });
  } catch (error) {
    res.status(500).json({ success: false, error: error.message });
  }
});

app.post('/api/trigger/sync-status', async (req, res) => {
  try {
    const { userId, accountId, status, error } = req.body;
    
    broadcastToUser(userId, {
      type: 'sync_status_update',
      accountId,
      status,
      error
    });

    res.json({ success: true });
  } catch (error) {
    res.status(500).json({ success: false, error: error.message });
  }
});

// Health check endpoint
app.get('/health', (req, res) => {
  res.json({
    status: 'healthy',
    connections: clients.size,
    uptime: process.uptime(),
    memory: process.memoryUsage(),
    timestamp: Date.now()
  });
});

// Initialize Redis connection
async function initializeRedis() {
  try {
    await redisClient.connect();
    console.log('Redis connected successfully');
  } catch (error) {
    console.error('Redis connection failed:', error);
  }
}

// Start server
async function startServer() {
  try {
    await initializeRedis();
    
    server.listen(PORT, () => {
      console.log(`ROTZ Email Butler WebSocket Server running on port ${PORT}`);
      console.log(`Health check: http://localhost:${PORT}/health`);
    });
  } catch (error) {
    console.error('Server startup error:', error);
    process.exit(1);
  }
}

// Graceful shutdown
process.on('SIGTERM', async () => {
  console.log('Shutting down WebSocket server...');
  
  // Close all WebSocket connections
  clients.forEach((client, clientId) => {
    client.ws.close();
  });
  
  // Close database connections
  await dbPool.end();
  
  // Close Redis connection
  await redisClient.quit();
  
  // Close HTTP server
  server.close(() => {
    console.log('WebSocket server shut down gracefully');
    process.exit(0);
  });
});

// Start the server
startServer();

