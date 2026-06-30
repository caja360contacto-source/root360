<?php
/**
 * SmtpMailer - cliente SMTP mínimo (AUTH LOGIN sobre STARTTLS) pensado para Gmail.
 * No depende de composer ni de PHPMailer. Pensado para 1 a 1, no para envíos masivos.
 */

class SmtpMailer
{
    private $host;
    private $port;
    private $email;
    private $password;
    private $socket;

    public function __construct(string $host, int $port, string $email, string $password)
    {
        $this->host     = $host;
        $this->port     = $port;
        $this->email    = $email;
        $this->password = $password;
    }

    public function enviar(string $para, string $asunto, string $cuerpo, ?string $enRespuestaA = null): void
    {
        $this->conectar();

        $this->comando("EHLO gmail.com", 250);
        $this->comando("STARTTLS", 220);

        if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new Exception("No se pudo iniciar TLS con el servidor SMTP");
        }

        $this->comando("EHLO gmail.com", 250);
        $this->comando("AUTH LOGIN", 334);
        $this->comando(base64_encode($this->email), 334);
        $this->comando(base64_encode($this->password), 235);

        $this->comando("MAIL FROM:<{$this->email}>", 250);
        $this->comando("RCPT TO:<{$para}>", 250);
        $this->comando("DATA", 354);

        $nombreRemitente = defined('NOMBRE_REMITENTE') ? NOMBRE_REMITENTE : $this->email;
        $fecha  = date('r');
        $cuerpoEscapado = str_replace("\n.", "\n..", $cuerpo); // escape de líneas que empiezan con "."

        $headers = [];
        $headers[] = "From: {$nombreRemitente} <{$this->email}>";
        $headers[] = "To: <{$para}>";
        $headers[] = "Subject: " . $this->codificarAsunto($asunto);
        $headers[] = "Date: {$fecha}";
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-Type: text/plain; charset=UTF-8";
        $headers[] = "Content-Transfer-Encoding: 8bit";
        if ($enRespuestaA) {
            $headers[] = "In-Reply-To: {$enRespuestaA}";
            $headers[] = "References: {$enRespuestaA}";
        }

        $mensaje = implode("\r\n", $headers) . "\r\n\r\n" . $cuerpoEscapado . "\r\n.";

        $this->comando($mensaje, 250);
        $this->comando("QUIT", 221);

        fclose($this->socket);
    }

    private function conectar(): void
    {
        $this->socket = stream_socket_client(
            "tcp://{$this->host}:{$this->port}",
            $errno,
            $errstr,
            15
        );

        if (!$this->socket) {
            throw new Exception("No se pudo conectar al servidor SMTP: {$errstr} ({$errno})");
        }

        $this->leerRespuesta(); // saludo inicial del servidor
    }

    private function comando(string $cmd, int $codigoEsperado): string
    {
        fwrite($this->socket, $cmd . "\r\n");
        $respuesta = $this->leerRespuesta();
        $codigo = (int) substr($respuesta, 0, 3);

        if ($codigo !== $codigoEsperado) {
            throw new Exception("Error SMTP. Esperaba {$codigoEsperado}, recibí: {$respuesta}");
        }

        return $respuesta;
    }

    private function leerRespuesta(): string
    {
        $data = '';
        while ($linea = fgets($this->socket, 515)) {
            $data .= $linea;
            // las líneas multilinea de SMTP terminan cuando el 4to char es un espacio (no un '-')
            if (substr($linea, 3, 1) === ' ') {
                break;
            }
        }
        return $data;
    }

    private function codificarAsunto(string $asunto): string
    {
        // Codificación simple para que tildes/ñ no rompan el header
        if (preg_match('/[^\x20-\x7E]/', $asunto)) {
            return '=?UTF-8?B?' . base64_encode($asunto) . '?=';
        }
        return $asunto;
    }
}
