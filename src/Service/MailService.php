<?php
// src/Service/MailService.php
namespace App\Service;

use App\Repository\MailsTypeRepository;
use InvalidArgumentException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

readonly class MailService
{
    public function __construct(
        private MailsTypeRepository $mailsTypeRepository,
        private string $smtpHost,
        private int $smtpPort,
        private string $smtpUsername,
        private string $smtpPassword,
        private string $smtpSenderEmail,
        private string $smtpSenderName = 'EcoRide'
    ) {
    }

    public function sendEmail(string $to, string $codeMailType, $strToReplace = []): void
    {
        // Récupération de l'entité Mail en fonction du typeMail
        $mail = $this->mailsTypeRepository->findOneBy(['code' => $codeMailType]);

        if (!$mail) {
            throw new InvalidArgumentException("Aucun mail trouvé pour le type : $codeMailType");
        }

        $subject = $mail->getSubject();
        $content = $mail->getContent();
        //on dé htmlspecialchar
        $subject = htmlspecialchars_decode($subject, ENT_QUOTES);
        $content = htmlspecialchars_decode($content, ENT_QUOTES);

        foreach ($strToReplace as $key => $value) {
            $subject = str_replace('{'.$key.'}', $value, $subject);
            $content = str_replace('{'.$key.'}', $value, $content);
        }

        $mailer = new PHPMailer(true);
        $mailer->CharSet = 'UTF-8';

        try {
            // Configuration du serveur
            $mailer->isSMTP();
            $mailer->Host = $this->smtpHost;
            $mailer->SMTPAuth = true;
            $mailer->Username = $this->smtpUsername;
            $mailer->Password = $this->smtpPassword;

            // Choix du chiffrement en fonction du port
            if ($this->smtpPort == 465) {
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }

            $mailer->Port = $this->smtpPort;

            // Expéditeur et destinataires
            $mailer->setFrom($this->smtpSenderEmail, $this->smtpSenderName);
            $mailer->addAddress($to);

            // Contenu
            $mailer->isHTML(true);
            $mailer->Subject = $subject;
            $mailer->Body = $content;
            $mailer->AltBody = strip_tags($content);

            $mailer->send();
        } catch (PHPMailerException $e) {
            throw new \RuntimeException("Erreur lors de l'envoi du mail : {$mailer->ErrorInfo}");
        }
    }
}