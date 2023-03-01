<?php

namespace Debva\Elnix;

use Ratchet\Http\HttpServer;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Factory as LoopFactory;
use React\Socket\Server as Reactor;

abstract class Websocket
{
    protected $clients;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
    }

    public function onOpen(ConnectionInterface $conn)
    {
        // Menambahkan koneksi ke daftar koneksi
        $this->clients->attach($conn);
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        // Mengirim pesan ke semua koneksi yang terhubung ke chat room
        foreach ($this->clients as $client) {
            if ($client !== $from) {
                $client->send($msg);
            }
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        // Menghapus koneksi dari daftar koneksi
        $this->clients->detach($conn);
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        // Menangani kesalahan
        echo "Error: " . $e->getMessage() . "\n";
        $conn->close();
    }
}

// RewriteEngine On

// # Aktifkan modul proxy
// RewriteCond %{REQUEST_URI}  ^/socket.io               [NC]
// RewriteCond %{QUERY_STRING} transport=websocket    [NC]
// RewriteRule /(.*)           ws://localhost:80/$1    [P,L]

// # Forward request ke index.php
// RewriteCond %{REQUEST_FILENAME} !-f
// RewriteRule . index.php [L]

// $httpServer = new HttpServer(
//     new WsServer(
//         new ChatRoom()
//     )
// );

// // Mengatur server WebSocket ke port 80
// $loop = LoopFactory::create();
// $webSocket = new Reactor('tcp://0.0.0.0:80', $loop);

// // Menjalankan server HTTP dan WebSocket secara bersamaan
// $httpServer->listen($webSocket);

// // Memulai loop event
// $loop->run();
