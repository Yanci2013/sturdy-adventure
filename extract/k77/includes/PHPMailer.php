<?php
namespace PHPMailer\PHPMailer;

class PHPMailer
{
    public const VERSION = '6.9.1';

    /**
     * SMTP配置
     * @var string
     */
    public $Host = '';
    public $SMTPAuth = false;
    public $Username = '';
    public $Password = '';
    public $SMTPSecure = '';
    public $Port = 25;

    /**
     * 发件人
     * @var string
     */
    public $From = '';
    public $FromName = '';

    /**
     * 收件人列表
     * @var array
     */
    private $addresses = [];

    /**
     * 邮件内容
     * @var bool
     */
    public $isHTML = false;
    public $Subject = '';
    public $Body = '';

    /**
     * @var SMTP
     */
    private $smtp;

    public function __construct()
    {
        $this->smtp = new SMTP();
    }

    /**
     * 启用SMTP模式
     * @return void
     */
    public function isSMTP(): void
    {
        $this->SMTPAuth = true;
    }

    /**
     * 设置发件人
     * @param string $address
     * @param string $name
     * @return void
     */
    public function setFrom(string $address, string $name = ''): void
    {
        $this->From = $address;
        $this->FromName = $name;
    }

    /**
     * 添加收件人
     * @param string $address
     * @return void
     */
    public function addAddress(string $address): void
    {
        $this->addresses[] = $address;
    }

    /**
     * 设置HTML格式
     * @param bool $isHtml
     * @return void
     */
    public function isHTML(bool $isHtml = true): void
    {
        $this->isHTML = $isHtml;
    }

    /**
     * 发送邮件
     * @return bool
     */
    public function send(): bool
    {
        // 连接服务器
        if (!$this->smtp->connect($this->Host, $this->Port, $this->SMTPSecure)) {
            return false;
        }
        // 登录
        $this->smtp->authenticate($this->Username, $this->Password);
        // 发件人
        $this->smtp->mail($this->From);
        // 收件人
        foreach ($this->addresses as $addr) {
            $this->smtp->recipient($addr);
        }
        // 构建头部
        $headers = "From: {$this->FromName} <{$this->From}>\r\n";
        $headers .= "Subject: {$this->Subject}\r\n";
        $headers .= "Content-Type: text/html; charset=utf-8\r\n\r\n";
        // 发送内容
        $this->smtp->data($headers . $this->Body);
        $this->smtp->close();
        return true;
    }
}