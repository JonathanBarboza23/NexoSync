<?php
namespace PHPMailer\PHPMailer;

require_once __DIR__ . '/Exception.php';
require_once __DIR__ . '/SMTP.php';

class PHPMailer {
    const CHARSET_UTF8 = 'utf-8';
    const ENCODING_BASE64 = 'base64';

    public $Priority = null;
    public $CharSet = self::CHARSET_UTF8;
    public $ContentType = 'text/html';
    public $Encoding = '8bit';
    public $From = 'noreply@electrohogar.com';
    public $FromName = 'ElectroHogar Tienda';
    public $Sender = '';
    public $Subject = '';
    public $Body = '';
    public $AltBody = '';
    public $WordWrap = 0;
    public $Mailer = 'mail'; // 'mail' o 'smtp'
    public $Host = 'localhost';
    public $Port = 25;
    public $SMTPSecure = ''; // 'tls' o 'ssl'
    public $SMTPAuth = false;
    public $Username = '';
    public $Password = '';
    public $Timeout = 15;
    public $SMTPDebug = 0;

    protected $smtp = null;
    protected $to = [];
    protected $errorInfo = '';

    public function __construct($exceptions = null) {
        $this->smtp = new SMTP();
    }

    public function isSMTP() {
        $this->Mailer = 'smtp';
    }

    public function isHTML($isHtml = true) {
        $this->ContentType = $isHtml ? 'text/html' : 'text/plain';
    }

    public function setFrom($address, $name = '', $auto = true) {
        $this->From = $address;
        $this->FromName = $name;
        if ($auto && empty($this->Sender)) {
            $this->Sender = $address;
        }
        return true;
    }

    public function addAddress($address, $name = '') {
        $this->to[] = [$address, $name];
        return true;
    }

    public function clearAddresses() {
        $this->to = [];
    }

    public function send() {
        if (empty($this->to)) {
            $this->errorInfo = 'Falta especificar destinatario.';
            return false;
        }

        $headers = [];
        $headers[] = 'Date: ' . date('r');
        $headers[] = 'From: ' . ($this->FromName ? "=?UTF-8?B?" . base64_encode($this->FromName) . "?= <{$this->From}>" : $this->From);
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: ' . $this->ContentType . '; charset=' . $this->CharSet;
        $headers[] = 'Content-Transfer-Encoding: 8bit';
        $headers[] = 'X-Mailer: PHPMailer-ElectroHogar';

        $destinatarios = [];
        foreach ($this->to as $t) {
            $destinatarios[] = $t[1] ? "=?UTF-8?B?" . base64_encode($t[1]) . "?= <{$t[0]}>" : $t[0];
        }
        $to_str = implode(', ', $destinatarios);

        // Si se configuró SMTP activo
        if ($this->Mailer === 'smtp' && !empty($this->Host) && $this->Host !== 'localhost') {
            try {
                $host = $this->Host;
                if ($this->SMTPSecure === 'ssl') {
                    $host = 'ssl://' . $host;
                } elseif ($this->SMTPSecure === 'tls') {
                    $host = 'tcp://' . $host;
                }
                if ($this->smtp->connect($host, $this->Port, $this->Timeout)) {
                    $this->smtp->hello();
                    if ($this->SMTPAuth && !empty($this->Username)) {
                        $this->smtp->authenticate($this->Username, $this->Password);
                    }
                    $this->smtp->mail($this->From);
                    foreach ($this->to as $t) {
                        $this->smtp->recipient($t[0]);
                    }
                    $msg = implode("\r\n", $headers) . "\r\nTo: " . $to_str . "\r\nSubject: =?UTF-8?B?" . base64_encode($this->Subject) . "?=\r\n\r\n" . $this->Body;
                    $ok = $this->smtp->data($msg);
                    $this->smtp->quit();
                    $this->smtp->close();
                    if ($ok) return true;
                }
            } catch (\Throwable $e) {
                $this->errorInfo = $e->getMessage();
            }
        }

        // Fallback a mail() de PHP estándar
        $sub_enc = "=?UTF-8?B?" . base64_encode($this->Subject) . "?=";
        $head_str = implode("\r\n", $headers);
        $ok_mail = @mail($to_str, $sub_enc, $this->Body, $head_str);
        if (!$ok_mail) {
            $this->errorInfo = 'El servidor no pudo entregar el correo por SMTP ni por mail().';
        }
        return $ok_mail;
    }

    public function getErrorInfo() {
        return $this->errorInfo;
    }
}
