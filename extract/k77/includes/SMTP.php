<?php
namespace PHPMailer\PHPMailer;

class SMTP
{
    /**
     * @var resource|null
     */
    private $socket;

    /**
     * 连接SMTP服务器
     * @param string $host
     * @param int $port
     * @param string $secure ssl/tls
     * @return bool
     */
    public function connect(string $host, int $port, string $secure = ''): bool
    {
        $scheme = $secure === 'ssl' ? 'ssl://' : '';
        $this->socket = fsockopen($scheme . $host, $port, $errno, $errstr, 15);
        if (!$this->socket) {
            return false;
        }
        // 读取欢迎信息
        fgets($this->socket, 1024);
        return true;
    }

    /**
     * 登录授权
     * @param string $username
     * @param string $password
     * @return bool
     */
    public function authenticate(string $username, string $password): bool
    {
        $this->sendCmd("EHLO localhost");
        $this->sendCmd("AUTH LOGIN");
        $this->sendCmd(base64_encode($username));
        $this->sendCmd(base64_encode($password));
        return true;
    }

    /**
     * 设置发件人
     * @param string $from
     * @return bool
     */
    public function mail(string $from): bool
    {
        $this->sendCmd("MAIL FROM:<$from>");
        return true;
    }

    /**
     * 设置收件人
     * @param string $to
     * @return bool
     */
    public function recipient(string $to): bool
    {
        $this->sendCmd("RCPT TO:<$to>");
        return true;
    }

    /**
     * 发送邮件正文
     * @param string $msg
     * @return bool
     */
    public function data(string $msg): bool
    {
        $this->sendCmd("DATA");
        $this->sendCmd($msg . "\r\n.");
        return true;
    }

    /**
     * 关闭连接
     */
    public function close(): void
    {
        $this->sendCmd("QUIT");
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }
    }

    /**
     * 发送一条指令并读取返回
     * @param string $cmd
     * @return string
     */
    private function sendCmd(string $cmd): string
    {
        fputs($this->socket, $cmd . "\r\n");
        return fgets($this->socket, 1024);
    }
}