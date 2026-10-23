<?php
/**
 * SMTP configuration loaded from the environment.
 * Never commit credentials. Set NCHIRE_SMTP_USERNAME and NCHIRE_SMTP_PASSWORD
 * in the deployment environment (or a local untracked env file).
 */

if (!function_exists('nc_mail_config')) {
    function nc_mail_config(): array
    {
        return [
            'host' => getenv('NCHIRE_SMTP_HOST') ?: 'smtp.gmail.com',
            'port' => (int)(getenv('NCHIRE_SMTP_PORT') ?: 465),
            'secure' => getenv('NCHIRE_SMTP_SECURE') ?: 'ssl',
            'username' => (string)(getenv('NCHIRE_SMTP_USERNAME') ?: ''),
            'password' => (string)(getenv('NCHIRE_SMTP_PASSWORD') ?: ''),
            'from_email' => getenv('NCHIRE_SMTP_FROM_EMAIL') ?: 'no-reply@nchire.local',
            'from_name' => getenv('NCHIRE_SMTP_FROM_NAME') ?: 'NCHire - Norzagaray College',
        ];
    }
}

if (!function_exists('nc_app_url')) {
    function nc_app_url(string $path = ''): string
    {
        $base = rtrim((string)(getenv('NCHIRE_APP_URL') ?: ''), '/');
        if ($base === '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $base = $scheme . '://' . $host;
        }
        return $base . $path;
    }
}

if (!function_exists('nc_apply_mail_config')) {
    function nc_apply_mail_config($mail): void
    {
        $config = nc_mail_config();
        $mail->isSMTP();
        $mail->Host = $config['host'];
        $mail->SMTPAuth = $config['username'] !== '';
        $mail->Username = $config['username'];
        $mail->Password = $config['password'];
        $mail->SMTPSecure = $config['secure'] === 'tls'
            ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS
            : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = $config['port'];
    }
}
