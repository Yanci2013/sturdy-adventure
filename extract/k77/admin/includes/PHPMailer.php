<?php
namespace PHPMailer\PHPMailer;

class PHPMailer
{
    const VERSION = '6.9.1';
    public $Host;
    public $SMTPAuth = false;
    public $Username;
    public $Password;
    public $SMTPSecure;
    public $Port;
    public $From;
    public $FromName;
    public $isHTML = false;
    public $Subject;
    public $Body;
    private $recipients = [];
    private $smtp;

    public function isSMTP()
    {
        $this->SMTPAuth = true;
    }

    public function setFrom($address, $name = '')
    {
        $this->From = $address;
        $this->FromName = $name;
    }

    public function addAddress($address)
    {
        $this->recipients[] = $address;
    }

    public function isHTML($isHtml = true)
    {
        $this->isHTML = $isHtml;
    }

    public function send()
    {
        $this->smtp = new SMTP();
        $this->smtp->connect($this->Host, $this->Port, $this->SMTPSecure);
        $this->smtp->authenticate($this->Username, $this->Password);
        $this->smtp->mail($this->From);
        foreach ($this->recipients as $addr) {
            $this->smtp->recipient($addr);
        }
        $this->smtp->data($this->buildMessage());
        $this->smtp->close();
        return true;
    }

    private function buildMessage()
    {
        $headers = "From: {$this->FromName} <{$this->From}>\r\n";
        $headers .= "Subject: {$this->Subject}\r\n";
        $headers .= "Content-Type: text/html; charset=utf-8\r\n\r\n";
        return $headers . $this->Body;
    }
}