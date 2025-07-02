/**
 * ROTZ Email Butler - Load Testing
 * Performance testing with k6
 */

import http from 'k6/http';
import ws from 'k6/ws';
import { check, sleep } from 'k6';
import { Rate, Trend, Counter } from 'k6/metrics';

// Custom metrics
const errorRate = new Rate('errors');
const responseTime = new Trend('response_time');
const wsConnections = new Counter('websocket_connections');

// Test configuration
export const options = {
  stages: [
    { duration: '2m', target: 10 },   // Ramp up to 10 users
    { duration: '5m', target: 10 },   // Stay at 10 users
    { duration: '2m', target: 20 },   // Ramp up to 20 users
    { duration: '5m', target: 20 },   // Stay at 20 users
    { duration: '2m', target: 50 },   // Ramp up to 50 users
    { duration: '5m', target: 50 },   // Stay at 50 users
    { duration: '5m', target: 0 },    // Ramp down to 0 users
  ],
  thresholds: {
    http_req_duration: ['p(95)<2000'], // 95% of requests must complete below 2s
    http_req_failed: ['rate<0.1'],     // Error rate must be below 10%
    errors: ['rate<0.1'],              // Custom error rate below 10%
  },
};

// Test data
const BASE_URL = __ENV.BASE_URL || 'http://localhost';
const WS_URL = __ENV.WS_URL || 'ws://localhost:8080';

const testUsers = [
  { email: 'test1@example.com', password: 'password123' },
  { email: 'test2@example.com', password: 'password123' },
  { email: 'test3@example.com', password: 'password123' },
];

let authToken = '';

export function setup() {
  // Setup test data
  console.log('Setting up load test...');
  
  // Create test users if needed
  const setupResponse = http.post(`${BASE_URL}/api/test/setup`, {
    users: testUsers,
    emails: 100, // Create 100 test emails per user
    ai_providers: 5, // Enable 5 AI providers
  });
  
  check(setupResponse, {
    'setup successful': (r) => r.status === 200,
  });
  
  return { setupComplete: true };
}

export default function(data) {
  // Test scenario selection
  const scenario = Math.random();
  
  if (scenario < 0.3) {
    testWebInterface();
  } else if (scenario < 0.6) {
    testAPIEndpoints();
  } else {
    testWebSocketConnection();
  }
  
  sleep(1);
}

function testWebInterface() {
  const group = 'Web Interface';
  
  // Login page
  let response = http.get(`${BASE_URL}/`);
  check(response, {
    [`${group} - login page loads`]: (r) => r.status === 200,
    [`${group} - login page has form`]: (r) => r.body.includes('login-form'),
  }) || errorRate.add(1);
  
  responseTime.add(response.timings.duration);
  
  // Login
  const user = testUsers[Math.floor(Math.random() * testUsers.length)];
  response = http.post(`${BASE_URL}/api/auth/login`, {
    email: user.email,
    password: user.password,
  });
  
  check(response, {
    [`${group} - login successful`]: (r) => r.status === 200,
    [`${group} - token received`]: (r) => r.json('token') !== undefined,
  }) || errorRate.add(1);
  
  if (response.status === 200) {
    authToken = response.json('token');
    
    // Dashboard
    response = http.get(`${BASE_URL}/dashboard`, {
      headers: { Authorization: `Bearer ${authToken}` },
    });
    
    check(response, {
      [`${group} - dashboard loads`]: (r) => r.status === 200,
      [`${group} - dashboard has stats`]: (r) => r.body.includes('dashboard-stats'),
    }) || errorRate.add(1);
    
    // Emails page
    response = http.get(`${BASE_URL}/emails`, {
      headers: { Authorization: `Bearer ${authToken}` },
    });
    
    check(response, {
      [`${group} - emails page loads`]: (r) => r.status === 200,
    }) || errorRate.add(1);
    
    // AI Providers page
    response = http.get(`${BASE_URL}/ai-providers`, {
      headers: { Authorization: `Bearer ${authToken}` },
    });
    
    check(response, {
      [`${group} - AI providers page loads`]: (r) => r.status === 200,
    }) || errorRate.add(1);
  }
}

function testAPIEndpoints() {
  const group = 'API Endpoints';
  
  // Login first
  const user = testUsers[Math.floor(Math.random() * testUsers.length)];
  let response = http.post(`${BASE_URL}/api/auth/login`, {
    email: user.email,
    password: user.password,
  });
  
  if (response.status !== 200) {
    errorRate.add(1);
    return;
  }
  
  authToken = response.json('token');
  const headers = { Authorization: `Bearer ${authToken}` };
  
  // Test various API endpoints
  const endpoints = [
    { method: 'GET', url: '/api/dashboard/stats', name: 'dashboard stats' },
    { method: 'GET', url: '/api/emails', name: 'emails list' },
    { method: 'GET', url: '/api/email-accounts', name: 'email accounts' },
    { method: 'GET', url: '/api/ai-providers', name: 'AI providers' },
    { method: 'GET', url: '/api/analytics/summary', name: 'analytics summary' },
  ];
  
  endpoints.forEach(endpoint => {
    response = http.request(endpoint.method, `${BASE_URL}${endpoint.url}`, null, { headers });
    
    check(response, {
      [`${group} - ${endpoint.name} success`]: (r) => r.status === 200,
      [`${group} - ${endpoint.name} response time`]: (r) => r.timings.duration < 1000,
    }) || errorRate.add(1);
    
    responseTime.add(response.timings.duration);
  });
  
  // Test email processing
  response = http.post(`${BASE_URL}/api/emails/process`, {
    email_ids: [1, 2, 3, 4, 5],
    ai_providers: ['openai', 'anthropic', 'google'],
  }, { headers });
  
  check(response, {
    [`${group} - email processing started`]: (r) => r.status === 200 || r.status === 202,
  }) || errorRate.add(1);
  
  // Test AI provider toggle
  response = http.put(`${BASE_URL}/api/ai-providers/1/toggle`, {
    enabled: Math.random() > 0.5,
  }, { headers });
  
  check(response, {
    [`${group} - AI provider toggle`]: (r) => r.status === 200,
  }) || errorRate.add(1);
}

function testWebSocketConnection() {
  const group = 'WebSocket';
  
  // Login first to get token
  const user = testUsers[Math.floor(Math.random() * testUsers.length)];
  const response = http.post(`${BASE_URL}/api/auth/login`, {
    email: user.email,
    password: user.password,
  });
  
  if (response.status !== 200) {
    errorRate.add(1);
    return;
  }
  
  authToken = response.json('token');
  
  // WebSocket connection test
  const wsResponse = ws.connect(WS_URL, {}, function (socket) {
    wsConnections.add(1);
    
    socket.on('open', function () {
      console.log('WebSocket connected');
      
      // Authenticate
      socket.send(JSON.stringify({
        type: 'authenticate',
        token: authToken,
      }));
    });
    
    socket.on('message', function (message) {
      const data = JSON.parse(message);
      
      check(data, {
        [`${group} - valid message format`]: (d) => d.type !== undefined,
      }) || errorRate.add(1);
      
      if (data.type === 'authenticated') {
        // Subscribe to channels
        socket.send(JSON.stringify({
          type: 'subscribe',
          channels: [`user:${data.user.id}`, `user:${data.user.id}:emails`],
        }));
        
        // Request real-time stats
        socket.send(JSON.stringify({
          type: 'get_real_time_stats',
        }));
        
        // Send ping
        socket.send(JSON.stringify({
          type: 'ping',
        }));
      }
    });
    
    socket.on('error', function (error) {
      console.log('WebSocket error:', error);
      errorRate.add(1);
    });
    
    // Keep connection open for a bit
    sleep(5);
    
    socket.close();
  });
  
  check(wsResponse, {
    [`${group} - connection established`]: (r) => r && r.status === 101,
  }) || errorRate.add(1);
}

export function teardown(data) {
  // Cleanup test data
  console.log('Cleaning up load test...');
  
  const cleanupResponse = http.post(`${BASE_URL}/api/test/cleanup`);
  check(cleanupResponse, {
    'cleanup successful': (r) => r.status === 200,
  });
}

export function handleSummary(data) {
  return {
    'tests/performance/results/load-test-summary.json': JSON.stringify(data, null, 2),
    'tests/performance/results/load-test-summary.html': htmlReport(data),
  };
}

function htmlReport(data) {
  return `
<!DOCTYPE html>
<html>
<head>
    <title>ROTZ Email Butler - Load Test Results</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .metric { margin: 10px 0; padding: 10px; border: 1px solid #ddd; }
        .pass { background-color: #d4edda; }
        .fail { background-color: #f8d7da; }
        .summary { font-size: 18px; font-weight: bold; }
    </style>
</head>
<body>
    <h1>ROTZ Email Butler - Load Test Results</h1>
    <div class="summary">
        <p>Test Duration: ${data.state.testRunDurationMs / 1000}s</p>
        <p>Total Requests: ${data.metrics.http_reqs.values.count}</p>
        <p>Failed Requests: ${data.metrics.http_req_failed.values.rate * 100}%</p>
        <p>Average Response Time: ${data.metrics.http_req_duration.values.avg}ms</p>
        <p>95th Percentile: ${data.metrics.http_req_duration.values['p(95)']}ms</p>
    </div>
    
    <h2>Thresholds</h2>
    ${Object.entries(data.thresholds).map(([name, threshold]) => `
        <div class="metric ${threshold.ok ? 'pass' : 'fail'}">
            <strong>${name}:</strong> ${threshold.ok ? 'PASS' : 'FAIL'}
        </div>
    `).join('')}
    
    <h2>Detailed Metrics</h2>
    ${Object.entries(data.metrics).map(([name, metric]) => `
        <div class="metric">
            <strong>${name}:</strong>
            <ul>
                ${Object.entries(metric.values).map(([key, value]) => `
                    <li>${key}: ${value}</li>
                `).join('')}
            </ul>
        </div>
    `).join('')}
</body>
</html>
  `;
}

