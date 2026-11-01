<?php
namespace PHPMailer\PHPMailer;

class SMTP {
    const VERSION = '6.9.1';
    const CRLF = "\r\n";
    const DEFAULT_PORT = 25;
    const MAX_LINE_LENGTH = 998;

    public $do_debug = 0;
    public $Debugoutput = 'echo';
    public $do_verp = false;
    public $Timeout = 15;
    public $Timelimit = 30;

    protected $smtp_conn;
    protected $error = [];
    protected $helo_rply;
    protected $server_caps;
    protected $last_reply = '';

    public function connect($host, $port = null, $timeout = 15, $options = []) {
        $this->error = [];
        if ($this->connected()) {
            return true;
        }
        if (empty($port)) {
            $port = self::DEFAULT_PORT;
        }
        $errno = 0;
        $errstr = '';
        $socket_context = stream_context_create($options);
        $this->smtp_conn = @stream_socket_client(
            $host . ':' . $port,
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT,
            $socket_context
        );
        if (!is_resource($this->smtp_conn)) {
            $this->error = ['error' => $errstr, 'errno' => $errno];
            return false;
        }
        stream_set_timeout($this->smtp_conn, $timeout);
        $this->last_reply = $this->get_lines();
        return true;
    }

    public function connected() {
        return is_resource($this->smtp_conn) && !feof($this->smtp_conn);
    }

    public function close() {
        $this->error = [];
        $this->server_caps = null;
        $this->helo_rply = null;
        if (is_resource($this->smtp_conn)) {
            @fclose($this->smtp_conn);
            $this->smtp_conn = null;
        }
    }

    public function sendAndReceive($cmd, $expect = [250]) {
        if (!$this->connected()) return false;
        @fwrite($this->smtp_conn, $cmd . self::CRLF);
        $this->last_reply = $this->get_lines();
        $code = (int)substr($this->last_reply, 0, 3);
        return in_array($code, (array)$expect, true);
    }

    public function hello($host = '') {
        return $this->sendAndReceive('EHLO ' . ($host ?: 'localhost')) || $this->sendAndReceive('HELO ' . ($host ?: 'localhost'));
    }

    public function authenticate($username, $password, $authtype = 'LOGIN') {
        if (!$this->sendAndReceive('AUTH LOGIN', [334])) return false;
        if (!$this->sendAndReceive(base64_encode($username), [334])) return false;
        if (!$this->sendAndReceive(base64_encode($password), [235])) return false;
        return true;
    }

    public function mail($from) {
        return $this->sendAndReceive('MAIL FROM:<' . $from . '>');
    }

    public function recipient($to) {
        return $this->sendAndReceive('RCPT TO:<' . $to . '>');
    }

    public function data($msg_data) {
        if (!$this->sendAndReceive('DATA', [354])) return false;
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $msg_data));
        foreach ($lines as $line) {
            if (isset($line[0]) && $line[0] === '.') {
                $line = '.' . $line;
            }
            @fwrite($this->smtp_conn, $line . self::CRLF);
        }
        return $this->sendAndReceive('.', [250]);
    }

    public function quit() {
        return $this->sendAndReceive('QUIT', [221]);
    }

    protected function get_lines() {
        $data = '';
        while ($this->connected() && !feof($this->smtp_conn)) {
            $str = @fgets($this->smtp_conn, 515);
            $data .= $str;
            if (isset($str[3]) && $str[3] === ' ') {
                break;
            }
        }
        return $data;
    }

    public function getLastError() {
        return $this->error;
    }

    public function getLastReply() {
        return $this->last_reply;
    }
}
