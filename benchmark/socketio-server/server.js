const express = require('express');
const http = require('http');
const socketIo = require('socket.io');

const app = express();
const server = http.createServer(app);
const io = socketIo(server);

io.on('connection', (socket) => {
    socket.on('message', (msg) => socket.emit('message', msg));
    socket.on('disconnect', () => console.log('user disconnected'));
});

server.listen(8080, () => {
    console.log('listening on *:8080');
});
