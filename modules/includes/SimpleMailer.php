<?php
class SimpleMailer
{
    private $host;
    private $port;
    private $username;
    private $password;
    private $socket;
    private $debug = false;

    public function __construct($host, $port, $username, $password)
    {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
    }

    public function send($to, $subject, $body, $fromName = 'Admin System')
    {
        try {
            $this->connect();
            $this->auth();

            $this->sendCommand("MAIL FROM: <" . $this->username . ">");
            $this->sendCommand("RCPT TO: <" . $to . ">");
            $this->sendCommand("DATA");

            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type: text/html; charset=utf-8\r\n";
            $headers .= "To: $to\r\n";
            $headers .= "From: $fromName <" . $this->username . ">\r\n";
            $headers .= "Subject: $subject\r\n";

            $message = $headers . "\r\n" . $body . "\r\n.";
            $this->sendCommand($message);

            $this->sendCommand("QUIT");
            fclose($this->socket);

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    private function connect()
    {
        $this->socket = fsockopen($this->host, $this->port, $errno, $errstr, 10);
        if (!$this->socket) {
            throw new Exception("Connection failed: $errno $errstr");
        }
        $this->getResponse(); // Banner
        $this->sendCommand("EHLO " . gethostname());
        if ($this->port == 587) { // STARTTLS
            $this->sendCommand("STARTTLS");
            stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $this->sendCommand("EHLO " . gethostname());
        }
    }

    private function auth()
    {
        $this->sendCommand("AUTH LOGIN");
        $this->sendCommand(base64_encode($this->username));
        $this->sendCommand(base64_encode($this->password));
    }

    private function sendCommand($cmd)
    {
        fputs($this->socket, $cmd . "\r\n");
        $this->getResponse();
    }

    private function getResponse()
    {
        $response = "";
        while ($str = fgets($this->socket, 515)) {
            $response .= $str;
            if (substr($str, 3, 1) == " ")
                break;
        }
        return $response;
    }
}
?>