#!/usr/bin/env node

/**
 * Simple WebSocket broker for cursor tracking (no MQTT protocol)
 * Listen on ws://localhost:9001
 * Run: node mqtt-server.js
 */

const WebSocket = require('ws');
const http = require('http');

const PORT = 9001;
const channels = new Map(); // { roomName: Set<client> }

const httpServer = http.createServer((req, res) => {
    res.writeHead(200, { 'Content-Type': 'text/plain' });
    res.end('Cursor tracking WebSocket server running\n');
});

const wss = new WebSocket.Server({ server: httpServer });

console.log('[WS-BROKER] Starting WebSocket cursor tracking broker...\n');

wss.on('connection', function (ws, req) {
    const clientId = 'ws-' + Date.now() + '-' + Math.random().toString(16).slice(2, 8);
    let currentRoom = null;
    
    console.log(`[WS-BROKER] Client connected: ${clientId}`);

    ws.on('message', function (rawData) {
        try {
            const data = typeof rawData === 'string' ? rawData : rawData.toString();
            const message = JSON.parse(data);

            if (!message) return;

            // Join room (subscribe)
            if (message.action === 'join') {
                const room = message.room;
                if (!room) return;

                if (!channels.has(room)) {
                    channels.set(room, new Set());
                }
                
                // Leave previous room if in one
                if (currentRoom && channels.has(currentRoom)) {
                    channels.get(currentRoom).delete(ws);
                    if (channels.get(currentRoom).size === 0) {
                        channels.delete(currentRoom);
                    }
                }

                currentRoom = room;
                channels.get(room).add(ws);
                const subscribers = channels.get(room).size;
                
                console.log(`[WS-BROKER] ${clientId} joined room "${room}" (${subscribers} clients)`);

                // Send confirmation
                ws.send(JSON.stringify({
                    type: 'joined',
                    room: room,
                    clientId: clientId,
                    clientsInRoom: subscribers
                }));

                return;
            }

            // Broadcast cursor position to room
            if (message.action === 'cursor' && currentRoom) {
                const room = currentRoom;
                const subscribers = channels.get(room);
                
                if (subscribers) {
                    const response = JSON.stringify({
                        type: 'cursor',
                        clientId: message.clientId,
                        username: message.username,
                        x: message.x,
                        y: message.y,
                        color: message.color,
                        ts: message.ts
                    });

                    subscribers.forEach(function (client) {
                        if (client !== ws && client.readyState === WebSocket.OPEN) {
                            client.send(response);
                        }
                    });
                }
                return;
            }

            // Leave room
            if (message.action === 'leave' && currentRoom) {
                const room = currentRoom;
                if (channels.has(room)) {
                    channels.get(room).delete(ws);
                    console.log(`[WS-BROKER] ${clientId} left room "${room}"`);
                    
                    if (channels.get(room).size === 0) {
                        channels.delete(room);
                    }
                }
                currentRoom = null;
            }
        } catch (e) {
            // Silently ignore parse errors
        }
    });

    ws.on('close', function () {
        console.log(`[WS-BROKER] Client disconnected: ${clientId}`);
        
        if (currentRoom && channels.has(currentRoom)) {
            channels.get(currentRoom).delete(ws);
            if (channels.get(currentRoom).size === 0) {
                channels.delete(currentRoom);
            }
        }
    });

    ws.on('error', function (err) {
        console.error(`[WS-BROKER] Client ${clientId} error:`, err.message);
    });
});

httpServer.listen(PORT, 'localhost', function () {
    console.log(`[WS-BROKER] WebSocket server listening on ws://localhost:${PORT}`);
    console.log('[WS-BROKER] Ready for cursor tracking connections');
    console.log('[WS-BROKER] Press Ctrl+C to stop\n');
});

// Graceful shutdown
process.on('SIGINT', function () {
    console.log('\n[WS-BROKER] Shutting down...');
    wss.clients.forEach(function (ws) {
        ws.close(1000, 'Server shutting down');
    });
    httpServer.close(function () {
        console.log('[WS-BROKER] Server closed');
        process.exit(0);
    });
});
