<?php

namespace Neo\MySQLBackup;

use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class Mail
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function setConfig(array $config)
    {
        $this->config = $config;

        return $this;
    }

    /**
     * Send email
     *
     * @param string       $subject     邮件主题
     * @param string       $body        邮件内容
     * @param array|string $addresses   收件人地址，可以多个
     *                                  * 'a@z.com'
     *                                  * ['a@z.com','b@z.com']
     *                                  * ['to'=>['a@z.com','b@z.com'], 'cc'=>'a@z.com', 'bcc'=>['a@z.com','b@z.com']]
     * @param array        $attachments 附件绝对路径
     * @param string       $contentType text/plain 或者 text/html
     * @param int          $priority    优先级，默认Email::PRIORITY_NORMAL
     *
     * @throws NeoException
     * @return true
     */
    public function send(string $subject, string $body, array|string $addresses, ?array $attachments = null, string $contentType = 'text/plain', int $priority = Email::PRIORITY_NORMAL)
    {
        $config = $this->getConfig();

        // 文本邮件还是html邮件
        $contentFunc = $contentType != 'text/plain' ? 'html' : 'text';

        // 解析收件人
        $to = null;
        $cc = [];
        $bcc = [];

        if (is_array($addresses) && array_key_exists('to', $addresses)) {
            // 键值对：['to'=>['a@z.com','b@z.com'], 'cc'=>'a@z.com', 'bcc'=>['a@z.com','b@z.com']]
            $to = $addresses['to'];

            $cc = $addresses['cc'] ?? [];
            if (is_string($cc)) {
                $cc = (array) $cc;
            }

            $bcc = $addresses['bcc'] ?? [];
            if (is_string($bcc)) {
                $bcc = (array) $bcc;
            }
        } else {
            // 索引数组：['a@z.com','b@z.com']
            // 字符串：a@z.com
            $to = $addresses;
        }

        // 字符串：a@z.com
        if (is_string($to)) {
            $to = (array) $to;
        }

        try {
            $email = (new Email())
                ->from(new Address($config['frommail'], $config['fromname']))
                ->to(...$to)
                ->priority($priority)
                ->subject($subject)
                ->{$contentFunc}($body);

            foreach ($attachments as $attachment) {
                if ($attachment && is_file($attachment)) {
                    $email->attachFromPath($attachment);
                }
            }

            if ($cc) {
                $email->cc(...$cc);
            }
            if ($bcc) {
                $email->bcc(...$bcc);
            }

            $MAILER_DSN = sprintf('smtp://%s:%s@%s:%s', $config['username'], $config['password'], $config['host'], $config['port']);
            $mailer = new Mailer(Transport::fromDsn($MAILER_DSN));
            $mailer->send($email);

            return true;
        } catch (TransportExceptionInterface $ex) {
            throw $ex;
        }
    }
}
