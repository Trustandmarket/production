<?php

// src/Services/ServiceManager.php

//Couche d'acces au données

namespace App\Service;

use App\Entity\WpCommentmeta;
use App\Entity\WpComments;
use App\Entity\WpOptions;
use App\Entity\WpPostmeta;
use App\Entity\WpPosts;
use App\Entity\Translation\WpPostsTranslation;
use App\Entity\Translation\WpPostmetaTranslation;
use App\Entity\Translation\WpTermsTranslation;
use App\Entity\WpTermRelationships;
use App\Entity\WpTerms;
use App\Entity\WpTermTaxonomy;
use App\Entity\WpUsermeta;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * Class ServiceManager
 * @package App\Service
 */
class ToolsMeta
{
    private $router;
    private $entityManager;
    /**
     * ServiceManager constructor.
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        UrlGeneratorInterface $router,
        MailerInterface $mailer
    ) {
        $this->entityManager = $entityManager;
        $this->router = $router;
        $this->mailer = $mailer;
        /* $url_local = 'http://localhost:8000/wp-content';  $url_dev = 'https://dev.trustandmarket.com/wp-content'; $url_prod = 'https://trustandmarket.com/wp-content'; */
        $this->local = $_SERVER['APP_FILES_LOCAL_URL'];
    }

    /**
     * @param string $from
     * @param string $to
     * @param string $subject
     * @param string $template
     * @param array $data
     */
    public function sendEmail(string $from, string $to, string $subject, string $template, array $data)
    {
        $email = (new TemplatedEmail())
            ->from($from)
            ->to(new Address($to))
            ->subject($subject)
            ->htmlTemplate($template)
            ->context($data);
        $this->mailer->send($email);
        return true;
    }


    /**
     * @param string $from
     * @param string $to
     * @param string $subject
     * @param string $template
     * @param array $data
     */
    public function sendEmailsToMultiples(string $from, array $to, string $subject, string $template, array $data)
    {
        $email = (new TemplatedEmail())
            ->from($from)
            ->to(...$to)
            ->subject($subject)
            ->htmlTemplate($template)
            ->context($data);
        $this->mailer->send($email);
        return true;
    }

    /**
     * @param $startDate
     * @param $endDate
     * @return array
     */
    public function getBetweenDates($startDate, $endDate) {
        $rangArray = [];

        $startDate = strtotime($startDate);
        $endDate = strtotime($endDate);

        for ($currentDate = $startDate; $currentDate <= $endDate; $currentDate += (86400)) {
            $date = date('d-m-Y', $currentDate);
            $rangArray[] = $date;
        }

        return $rangArray;
    }
}
