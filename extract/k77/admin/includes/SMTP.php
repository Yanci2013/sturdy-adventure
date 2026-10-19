<?php
namespace PHPMailer\PHPMailer;

class SMTP
{
    private $socket;

    public function connect($host, $port, $secure)
    {
        $scheme = $secure === 'ssl' ? 'ssl://' : '';
        $this->socket = fsockopen($scheme . $host, $port);
        fgets($this->socket, 512);
    }

    public function authenticate($user, $pass)
    {
        fputs($this->socket, "EHLO localhost\r\n");
        fgets($this->socket, 512);
        fputs($this->socket, "AUTH LOGIN\r\n");
        fgets($this->socket, 512);
        fputs($this->socket, base64_encode($user) . "\r\n");
        fgets($this->socket, 512);
        fputs($this->socket, base64_encode($pass) . "\r\n");
        fgets($this->socket, 512);
    }

    public function mail($from)
    {
        fputs($this->socket, "MAIL FROM:<$from>\r\n");
        fgets($this->socket, 512);
    }

    public function recipient($to)
    {
        fputs($this->socket, "RCPT TO:<$to>\r\n");
        fgets($this->socket, 512);
    }

    public function data($msg)
    {
        fputs($this->socket, "DATA\r\n");
        fgets($this->socket, 512);
        fputs($this->socket, $msg . "\r\n.\r\n");
        fgets($this->socket, 512);
    }

    public function close()
    {
        fputs($this->socket, "QUIT\r\n");
        fclose($this->socket);
    }
}