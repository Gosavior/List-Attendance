 

const { createServer } = require('http');
const { Server } = require('socket.io');
const mysql = require('mysql2/promise');

const PORT = process.env.SOCKET_PORT || 3001;


const db = mysql.createPool({
    host: '145.79.8.194',
    user: 'arth_Staff_database',
    password: 'Info-asa1.com',
    database: 'arth_Staff',
    charset: 'utf8mb4',
    waitForConnections: true,
    connectionLimit: 10,
    queueLimit: 0
});


const httpServer = createServer((req, res) => {
    
    if (req.url === '/health') {
        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ status: 'ok', uptime: process.uptime() }));
        return;
    }

    
    if (req.method === 'POST' && req.url === '/notify') {
        let body = '';
        req.on('data', chunk => { body += chunk; });
        req.on('end', () => {
            try {
                const data = JSON.parse(body);
                const targetUserIds = data.userIds; 
                const payload = {
                    type: data.type || 'general',
                    message: data.message || '',
                    timestamp: new Date().toISOString()
                };

                if (Array.isArray(targetUserIds) && targetUserIds.length > 0) {
                    
                    targetUserIds.forEach(uid => {
                        const sockets = onlineUsers.get(parseInt(uid));
                        if (sockets) {
                            sockets.forEach(sid => {
                                io.to(sid).emit('notification_update', payload);
                            });
                        }
                    });
                } else {
                    
                    io.emit('notification_update', payload);
                }

                res.writeHead(200, { 'Content-Type': 'application/json' });
                res.end(JSON.stringify({ success: true }));
            } catch (e) {
                res.writeHead(400, { 'Content-Type': 'application/json' });
                res.end(JSON.stringify({ success: false, error: e.message }));
            }
        });
        return;
    }

    res.writeHead(404);
    res.end();
});

const io = new Server(httpServer, {
    cors: {
        origin: [
            'https://arthasolusiaditama.com',
            'https://staff.arthasolusiaditama.com',
            'https://www.arthasolusiaditama.com',
            'http://arthasolusiaditama.com',
            'http://staff.arthasolusiaditama.com',
            'http://localhost'
        ],
        credentials: true
    },
    pingTimeout: 60000,
    pingInterval: 25000
});


const onlineUsers = new Map();


io.use(async (socket, next) => {
    try {
        const token = socket.handshake.auth.token;
        const userId = parseInt(socket.handshake.auth.userId);
        
        if (!token || !userId) {
            return next(new Error('Authentication required'));
        }

        
        const [rows] = await db.query(
            'SELECT id, full_name, username, avatar, role FROM users WHERE id = ? AND is_active = 1',
            [userId]
        );

        if (!rows.length) {
            return next(new Error('User not found'));
        }

        socket.user = rows[0];
        socket.user.id = parseInt(rows[0].id);
        next();
    } catch (err) {
        console.error('Auth error:', err.message);
        next(new Error('Auth failed'));
    }
});


io.on('connection', async (socket) => {
    const user = socket.user;
    console.log(`[OK] ${user.full_name} connected (socket: ${socket.id})`);

    
    if (!onlineUsers.has(user.id)) {
        onlineUsers.set(user.id, new Set());
    }
    onlineUsers.get(user.id).add(socket.id);

    
    try {
        await db.query(
            'INSERT INTO user_online_status (user_id, is_online, last_seen, socket_id) VALUES (?, 1, NOW(), ?) ON DUPLICATE KEY UPDATE is_online = 1, last_seen = NOW(), socket_id = ?',
            [user.id, socket.id, socket.id]
        );
    } catch (e) {
        console.error('DB online status error:', e.message);
    }

    
    try {
        const [rooms] = await db.query(
            'SELECT room_id FROM chat_room_members WHERE user_id = ?',
            [user.id]
        );
        rooms.forEach(r => socket.join('room_' + r.room_id));
    } catch (e) {
        console.error('Error joining rooms:', e.message);
    }

    
    io.emit('user_online', { userId: user.id, fullName: user.full_name, online: true });

    
    socket.on('send_message', async (data, callback) => {
        try {
            const { roomId, message, replyToId } = data;
            if (!roomId || !message || !message.trim()) {
                return callback?.({ success: false, message: 'Invalid data' });
            }

            
            const [membership] = await db.query(
                'SELECT 1 FROM chat_room_members WHERE room_id = ? AND user_id = ?',
                [roomId, user.id]
            );
            if (!membership.length) {
                return callback?.({ success: false, message: 'Not a member' });
            }

            
            const [result] = await db.query(
                'INSERT INTO chat_messages (room_id, sender_id, message, reply_to_id) VALUES (?, ?, ?, ?)',
                [roomId, user.id, message.trim(), replyToId || null]
            );

            const msgData = {
                id: result.insertId,
                room_id: roomId,
                sender_id: user.id,
                sender_name: user.full_name,
                sender_username: user.username,
                sender_avatar: user.avatar,
                sender_role: user.role,
                message: message.trim(),
                message_type: 'text',
                reply_to_id: replyToId || null,
                forwarded_from_id: null,
                created_at: new Date().toISOString()
            };

            
            if (replyToId) {
                const [replyRows] = await db.query(
                    'SELECT cm.message, cm.sender_id, u.full_name as sender_name FROM chat_messages cm JOIN users u ON cm.sender_id = u.id WHERE cm.id = ?',
                    [replyToId]
                );
                if (replyRows.length) {
                    msgData.reply_message = replyRows[0].message;
                    msgData.reply_sender_id = replyRows[0].sender_id;
                    msgData.reply_sender_name = replyRows[0].sender_name;
                }
            }

            
            io.to('room_' + roomId).emit('new_message', msgData);

            
            await db.query(
                'UPDATE chat_room_members SET last_read_at = NOW() WHERE room_id = ? AND user_id = ?',
                [roomId, user.id]
            );

            callback?.({ success: true, data: msgData });
        } catch (err) {
            console.error('Send message error:', err.message);
            callback?.({ success: false, message: err.message });
        }
    });

    
    socket.on('delete_message', async (data, callback) => {
        try {
            const { messageId } = data;
            if (!messageId) return callback?.({ success: false, message: 'Invalid data' });

            
            const [msgRows] = await db.query(
                'SELECT * FROM chat_messages WHERE id = ? AND is_deleted = 0',
                [messageId]
            );
            if (!msgRows.length) return callback?.({ success: false, message: 'Message not found' });

            const msg = msgRows[0];
            const isAdmin = ['administrator', 'direktur', 'technician_manager'].includes(user.role);

            
            if (msg.sender_id !== user.id && !isAdmin) {
                return callback?.({ success: false, message: 'Not allowed' });
            }

            await db.query(
                'UPDATE chat_messages SET is_deleted = 1, deleted_by_id = ?, deleted_at = NOW() WHERE id = ?',
                [user.id, messageId]
            );

            
            io.to('room_' + msg.room_id).emit('message_deleted', {
                message_id: messageId,
                room_id: msg.room_id,
                deleted_by: user.full_name
            });

            callback?.({ success: true });
        } catch (err) {
            console.error('Delete message error:', err.message);
            callback?.({ success: false, message: err.message });
        }
    });

    
    socket.on('forward_message', async (data, callback) => {
        try {
            const { messageId, targetRoomId } = data;
            if (!messageId || !targetRoomId) {
                return callback?.({ success: false, message: 'Invalid data' });
            }

            
            const [origRows] = await db.query(
                'SELECT * FROM chat_messages WHERE id = ? AND is_deleted = 0',
                [messageId]
            );
            if (!origRows.length) return callback?.({ success: false, message: 'Original message not found' });

            
            const [targetMembership] = await db.query(
                'SELECT 1 FROM chat_room_members WHERE room_id = ? AND user_id = ?',
                [targetRoomId, user.id]
            );
            if (!targetMembership.length) return callback?.({ success: false, message: 'Not member of target room' });

            const origMsg = origRows[0];

            
            const [origSenderRows] = await db.query(
                'SELECT full_name FROM users WHERE id = ?', [origMsg.sender_id]
            );
            const origSenderName = origSenderRows.length ? origSenderRows[0].full_name : 'Unknown';

            
            const [result] = await db.query(
                'INSERT INTO chat_messages (room_id, sender_id, message, forwarded_from_id) VALUES (?, ?, ?, ?)',
                [targetRoomId, user.id, origMsg.message, messageId]
            );

            const msgData = {
                id: result.insertId,
                room_id: targetRoomId,
                sender_id: user.id,
                sender_name: user.full_name,
                sender_username: user.username,
                sender_avatar: user.avatar,
                sender_role: user.role,
                message: origMsg.message,
                message_type: 'text',
                reply_to_id: null,
                forwarded_from_id: messageId,
                forwarded_message: origMsg.message,
                forwarded_sender_id: origMsg.sender_id,
                forwarded_sender_name: origSenderName,
                created_at: new Date().toISOString()
            };

            
            io.to('room_' + targetRoomId).emit('new_message', msgData);

            
            await db.query(
                'UPDATE chat_room_members SET last_read_at = NOW() WHERE room_id = ? AND user_id = ?',
                [targetRoomId, user.id]
            );

            callback?.({ success: true, data: msgData });
        } catch (err) {
            console.error('Forward message error:', err.message);
            callback?.({ success: false, message: err.message });
        }
    });

    
    socket.on('typing', (data) => {
        socket.to('room_' + data.roomId).emit('user_typing', {
            userId: user.id,
            fullName: user.full_name,
            roomId: data.roomId,
            isTyping: data.isTyping
        });
    });

    
    socket.on('join_room', async (data) => {
        const roomId = data.roomId;
        socket.join('room_' + roomId);
        
        try {
            await db.query(
                'UPDATE chat_room_members SET last_read_at = NOW() WHERE room_id = ? AND user_id = ?',
                [roomId, user.id]
            );
        } catch (e) {}
    });

    
    socket.on('mark_read', async (data) => {
        try {
            await db.query(
                'UPDATE chat_room_members SET last_read_at = NOW() WHERE room_id = ? AND user_id = ?',
                [data.roomId, user.id]
            );
        } catch (e) {}
    });

    
    socket.on('get_online_users', (callback) => {
        const online = [];
        onlineUsers.forEach((sockets, uid) => {
            if (sockets.size > 0) online.push(uid);
        });
        callback?.({ online });
    });

    
    socket.on('broadcast_notification', (data) => {
        console.log(`[NOTIFY] ${user.full_name} triggered notification broadcast: ${data?.type || 'general'}`);
        
        io.emit('notification_update', {
            type: data?.type || 'general',
            triggeredBy: user.id,
            timestamp: new Date().toISOString()
        });
    });

    
    socket.on('disconnect', async () => {
        console.log(`[OFFLINE] ${user.full_name} disconnected (socket: ${socket.id})`);

        if (onlineUsers.has(user.id)) {
            onlineUsers.get(user.id).delete(socket.id);
            if (onlineUsers.get(user.id).size === 0) {
                onlineUsers.delete(user.id);
                
                try {
                    await db.query(
                        'UPDATE user_online_status SET is_online = 0, last_seen = NOW() WHERE user_id = ?',
                        [user.id]
                    );
                } catch (e) {}
                
                io.emit('user_online', { userId: user.id, fullName: user.full_name, online: false });
            }
        }
    });
});


httpServer.listen(PORT, () => {
    console.log(`[START] Socket.IO server running on port ${PORT}`);
});


process.on('SIGTERM', () => {
    console.log('SIGTERM received. Shutting down...');
    io.close();
    httpServer.close();
    db.end();
    process.exit(0);
});

process.on('SIGINT', () => {
    console.log('SIGINT received. Shutting down...');
    io.close();
    httpServer.close();
    db.end();
    process.exit(0);
});
