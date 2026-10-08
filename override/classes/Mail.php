<?php
class Mail extends MailCore
{
    public static function sendMailTest(
        $smtpChecked,
        $smtpServer,
        $content,
        $subject,
        $type,
        $to,
        $from,
        $smtpLogin,
        $smtpPassword,
        $smtpPort,
        $smtpEncryption,
        $dkimEnable = false,
        $dkimKey = '',
        $dkimDomain = '',
        $dkimSelector = ''
    ) {
        try {
            if (version_compare(_PS_VERSION_, '9.0.0', '>=')) {
                return self::enviarPruebaSymfony(
                    $smtpChecked,
                    $smtpServer,
                    $content,
                    $subject,
                    $to,
                    $from,
                    $smtpLogin,
                    $smtpPassword,
                    $smtpPort,
                    $smtpEncryption,
                    $dkimEnable,
                    $dkimKey,
                    $dkimDomain,
                    $dkimSelector
                );
            }
            return self::enviarPruebaSwift(
                $smtpChecked,
                $smtpServer,
                $content,
                $subject,
                $to,
                $from,
                $smtpLogin,
                $smtpPassword,
                $smtpPort,
                $smtpEncryption,
                $dkimEnable,
                $dkimKey,
                $dkimDomain,
                $dkimSelector
            );
        } catch (Throwable $e) {
            return $e->getMessage();
        }
    }

    protected static function enviarPruebaSymfony(
        $smtpChecked,
        $smtpServer,
        $content,
        $subject,
        $to,
        $from,
        $smtpLogin,
        $smtpPassword,
        $smtpPort,
        $smtpEncryption,
        $dkimEnable,
        $dkimKey,
        $dkimDomain,
        $dkimSelector
    ) {
        if ($smtpChecked) {
            $tls = Tools::strtolower($smtpEncryption) !== 'off';
            $transport = (new \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport($smtpServer, $smtpPort, $tls))
                ->setUsername($smtpLogin)
                ->setPassword($smtpPassword);
        } else {
            $transport = new \Symfony\Component\Mailer\Transport\SendmailTransport();
        }
        $mensaje = (new \Symfony\Component\Mime\Email())
            ->from($from)
            ->to($to)
            ->subject($subject)
            ->text($content);
        self::forzarCabeceras($mensaje, $to);
        if ($dkimEnable && $dkimKey !== '' && $dkimDomain !== '' && $dkimSelector !== '') {
            $firmante = new \Symfony\Component\Mime\Crypto\DkimSigner($dkimKey, $dkimDomain, $dkimSelector);
            $mensaje = $firmante->sign($mensaje);
        }
        (new \Symfony\Component\Mailer\Mailer($transport))->send($mensaje);
        return true;
    }

    protected static function enviarPruebaSwift(
        $smtpChecked,
        $smtpServer,
        $content,
        $subject,
        $to,
        $from,
        $smtpLogin,
        $smtpPassword,
        $smtpPort,
        $smtpEncryption,
        $dkimEnable,
        $dkimKey,
        $dkimDomain,
        $dkimSelector
    ) {
        if ($smtpChecked) {
            $cifrado = Tools::strtolower($smtpEncryption) === 'off' ? false : $smtpEncryption;
            $transport = (new \Swift_SmtpTransport($smtpServer, $smtpPort, $cifrado))
                ->setUsername($smtpLogin)
                ->setPassword($smtpPassword);
        } else {
            $transport = new \Swift_SendmailTransport();
        }
        $mensaje = (new \Swift_Message())
            ->setFrom($from)
            ->setTo($to)
            ->setSubject($subject)
            ->setBody($content);
        self::forzarCabeceras($mensaje, $to);
        if ($dkimEnable && $dkimKey !== '' && $dkimDomain !== '' && $dkimSelector !== '') {
            $mensaje->attachSigner(new \Swift_Signers_DKIMSigner($dkimKey, $dkimDomain, $dkimSelector));
        }
        return (new \Swift_Mailer($transport))->send($mensaje) ? true : false;
    }

    protected static function forzarCabeceras($mensaje, $to)
    {
        $modulo = Module::getInstanceByName('ecom_mailbajaclick');
        if (!$modulo || !$modulo->active || !$modulo->anadirCabecerasPrueba($mensaje, $to)) {
            throw new Exception('No se pudieron añadir las cabeceras obligatorias a la prueba de correo.');
        }
    }
}
