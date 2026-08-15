<?php
/**
 * Mango Number - Pure PHP Socket-based SMTP Mailer Client
 * Connects directly to Brevo SMTP servers using socket connections and TLS
 */
class Mailer {
    /**
     * Send email via raw SMTP socket connection with TLS
     * @param string $to Recipient email address
     * @param string $subject Email subject line
     * @param string $body Email content body (HTML)
     * @return bool True on success, throws exception on failure
     */
    public static function send($to, $subject, $body) {
        // Fetch settings from database if defined and non-empty, otherwise use environment configuration
        $db = get_db_connection();
        $smtp = null;
        if ($db) {
            try {
                $stmt = $db->query("SELECT * FROM smtp_settings WHERE active = 1 LIMIT 1");
                $smtp = $stmt->fetch();
            } catch (PDOException $e) {
                // Table might not exist yet
            }
        }

        if ($smtp) {
            $host = $smtp['host'];
            $port = (int)$smtp['port'];
            $username = $smtp['username'];
            $password = $smtp['password'];
            $encryption = strtolower($smtp['encryption'] ?? 'tls');
            $from_address = $smtp['from_email'];
            $from_name = $smtp['from_name'];
        } else {
            // Fallback to environment configurations (or get_system_setting fallback)
            $db_host = function_exists('get_system_setting') ? get_system_setting('mail_host') : '';
            $host = !empty($db_host) ? $db_host : ($_ENV['MAIL_HOST'] ?? getenv('MAIL_HOST') ?: 'smtp-relay.brevo.com');

            $db_port = function_exists('get_system_setting') ? get_system_setting('mail_port') : '';
            $port = !empty($db_port) ? (int)$db_port : (int)($_ENV['MAIL_PORT'] ?? getenv('MAIL_PORT') ?: 587);

            $db_user = function_exists('get_system_setting') ? get_system_setting('mail_username') : '';
            $username = !empty($db_user) ? $db_user : ($_ENV['MAIL_USERNAME'] ?? getenv('MAIL_USERNAME') ?: '');

            $db_pass = function_exists('get_system_setting') ? get_system_setting('mail_password') : '';
            $password = !empty($db_pass) ? $db_pass : ($_ENV['MAIL_PASSWORD'] ?? getenv('MAIL_PASSWORD') ?: '');

            $db_from = function_exists('get_system_setting') ? get_system_setting('mail_from_address') : '';
            $from_address = !empty($db_from) ? $db_from : ($_ENV['MAIL_FROM_ADDRESS'] ?? getenv('MAIL_FROM_ADDRESS') ?: 'no-reply@mangonumbers.com');

            $db_name = function_exists('get_system_setting') ? get_system_setting('mail_from_name') : '';
            $from_name = !empty($db_name) ? $db_name : ($_ENV['MAIL_FROM_NAME'] ?? getenv('MAIL_FROM_NAME') ?: 'Mango Numbers');

            $db_enc = function_exists('get_system_setting') ? get_system_setting('mail_encryption') : '';
            $encryption = !empty($db_enc) ? strtolower($db_enc) : strtolower($_ENV['MAIL_ENCRYPTION'] ?? getenv('MAIL_ENCRYPTION') ?: 'tls');
        }

        // Connect to the server
        $connectionHost = ($encryption === 'ssl') ? "ssl://" . $host : $host;
        $socket = fsockopen($connectionHost, $port, $errno, $errstr, 15);
        if (!$socket) {
            throw new Exception("SMTP Socket connection failed: $errstr ($errno)");
        }

        // Helper read function
        $read = function() use ($socket) {
            $data = '';
            while ($str = fgets($socket, 515)) {
                $data .= $str;
                if (substr($str, 3, 1) == ' ') {
                    break;
                }
            }
            return $data;
        };

        // Helper write function
        $write = function($cmd) use ($socket) {
            fwrite($socket, $cmd . "\r\n");
        };

        // Handshake
        $read(); 
        
        $write("EHLO " . gethostname());
        $read();

        if ($encryption === 'tls') {
            // Start TLS
            $write("STARTTLS");
            $res = $read();
            if (strpos($res, '220') === false) {
                throw new Exception("STARTTLS failed: " . $res);
            }

            // Upgrade connection to secure stream using TLS (supporting TLS 1.2 and TLS 1.3)
            $crypto_method = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                $crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            }
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                $crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
            }
            if (!stream_socket_enable_crypto($socket, true, $crypto_method)) {
                throw new Exception("TLS encryption negotiation failed");
            }

            // Authenticate EHLO again over TLS
            $write("EHLO " . gethostname());
            $read();
        }

        // Authenticate credentials
        $write("AUTH LOGIN");
        $read();
        $write(base64_encode($username));
        $read();
        $write(base64_encode($password));
        $res = $read();
        if (strpos($res, '235') === false) {
            throw new Exception("SMTP AUTH failed: " . $res);
        }

        // Set Sender and Recipient addresses
        $write("MAIL FROM: <$from_address>");
        $read();
        $write("RCPT TO: <$to>");
        $read();

        // Write Mail Data
        $write("DATA");
        $read();

        // Compile standard HTML headers
        $headers = [
            "MIME-Version: 1.0",
            "Content-type: text/html; charset=utf-8",
            "To: <$to>",
            "From: \"$from_name\" <$from_address>",
            "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=",
            "Date: " . date('r'),
            "Message-ID: <" . time() . "-" . md5($to) . "@" . gethostname() . ">"
        ];

        // Send payload and close connection
        $write(implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.");
        $read();

        $write("QUIT");
        fclose($socket);
        return true;
    }
}
