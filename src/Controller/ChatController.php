<?php

namespace App\Controller;

use App\Entity\WpOptions;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\ServiceManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use App\Service\ToolsMeta;
use App\Entity\Channel;
use App\Entity\Message;
use App\Entity\User;
use Twig_Environment;

/**
 * The chat system controller of the app
 *
 * @Route("", requirements={"locale": "fr"}, name="chat_")
 */
class ChatController extends AbstractController
{
    private $sm;
    private $em;
    private $tools;
    /**
     * Constructor of ChatController
     *
     * @param ServiceManager $sm
     * @param Twig_Environment $twig
     * @param EntityManagerInterface $em
     */
    public function __construct(ServiceManager $sm, EntityManagerInterface $em, ToolsMeta $tools)
    {
        $this->sm = $sm;
        $this->em = $em;
        $this->tools = $tools;
    }


    /**
     * Method who return view of chat channel when we send it with ajax
     *
     * @Route("/{_locale}/chat/get/channel/messages", requirements={"_locale": "fr"}, name="get_channel_messages")
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getChannelMessages(Request $request)
    {
        $user = $this->getUser();

        $channel = $request->get("channel");

        if ($channel) {
            $channel = $this->em->getRepository(Channel::class)->findOneBy(['name'=>$channel]);
            if (!$channel) {
                return $this->redirectToRoute('chat_chat');
            }
            $this->em->getRepository(Message::class)->markAsReadByChannelIdAndReceiverId($channel->getId(), $user->getId());
            $destinataire = ($channel->getFirstUser() == $user) ? $channel->getSecondUser() : $channel->getFirstUser();
        }

        $messages = $this->em->getRepository(Message::class)->findBy(
            ['channel' => $channel],
            ['sentDate' => 'ASC']
        );

        $destinataireData = $this->sm->readProDataWithoutAnnounce($destinataire->getId());
        $destinateurData = $this->sm->readProDataWithoutAnnounce($user->getId());

        $all_messages_html = $this->renderView('chat/messages-content.html.twig', [
            'destinataire' => $destinataireData,
            'channel' => $channel,
            'messages' => $messages,
            'user' => $user,
            'destinateur' => $destinateurData,
        ]);

        $destinataire_details_html = $this->renderView('chat/destinataire-details.html.twig', [
            'destinataire' => $destinataireData
        ]);

        $destinataire_details_haut_html = $this->renderView('chat/destinataire-details-haut.html.twig', [
            'destinataire' => $destinataireData
        ]);

        return new JsonResponse(['html' => ['all_messages' => $all_messages_html, 'destinataire_details' => $destinataire_details_html, 'destinataire_details_haut' => $destinataire_details_haut_html]]);
    }

    /**
     * Method who return all channels of connected user
     *
     * @Route("/{_locale}/chat/get/channels/xhr", requirements={"_locale": "fr"}, name="get_all_channels_xhr")
     *
     * @return JsonResponse
     */
    public function getAllChannels(Request $request)
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error'=>"not-connected"]);
        }

        // On récupère toutes les chaines de l'utilisateur connecté
        $channels = $this->em->getRepository(Channel::class)->findAllByUserId($user->getId());
        $channelsArray = [];
        foreach ($channels as $value) {
            $message = $this->em->getRepository(Message::class)->findOneBy(
                ['channel' => $value],
                ['sentDate' => 'DESC']
            );
            $dest = ($value->getFirstUser() == $user) ? $value->getSecondUser() : $value->getFirstUser();
            $destData = $this->sm->readProDataWithoutAnnounce($dest->getId());
            $channelsArray[] = ['channel'=>$value, 'message'=> $message, 'destinataire'=>$destData ];
        }

        $html_channels = $this->renderView('chat/side-chat-container.html.twig', [
            'channels' => $channelsArray, 'channel' => $request->get('channel') ,
        ]);

        $channelsArrayName = [];
        foreach ($channels as $channel) {
            $channelsArrayName[] = ['name'=>$channel->getName() ];
        }

        return new JsonResponse(['channels' => $channelsArrayName, 'html_channels' => $html_channels]);
    }

    /**
     * @Route("/{_locale}/chat/mark/readed/message/xhr", requirements={"_locale": "fr"}, name="mark_as_read_xhr")
     */
    public function MarkAsReadMessages(Request $request)
    {
        $channel = $request->get('channel');
        $channel = $this->em->getRepository(Channel::class)->findOneBy(['name' => $channel]);
        $user = $this->getUser();
        if ($channel and $user) {
            $this->em->getRepository(Message::class)->markAsReadByChannelIdAndReceiverId($channel->getId(), $user->getId());
        }
        return new JsonResponse(true);
    }

    /**
     * @Route("/{_locale}/chat/i/have/new/messages/xhr", requirements={"_locale": "fr"}, name="i_have_new_messages_xhr")
     */
    public function iHaveNewMessages()
    {
        $message = $this->em->getRepository(Message::class)->findOneBy(
            ['addressee' => $this->getUser(),'receiptDate' => null]
        );
        if ($message) {
            return new JsonResponse(["statut" => "yes"]);
        } else {
            return new JsonResponse(["statut" => "no"]);
        }
    }

    /**
     * @Route("/{_locale}/chat/send/message/to/not/connected/user/xhr", requirements={"_locale": "fr"}, name="send_message_to_not_connected_user_xhr")
     */
    public function sendMessageToNotConnectedUser(Request $request)
    {
        $user = $this->getUser();

        $channel = $request->get("channel");

        if ($channel && $user) {
            $channel = $this->em->getRepository(Channel::class)->findOneBy(['name'=>$channel]);
            if ($channel) {
                $receiver = ($channel->getFirstUser() == $user) ? $channel->getSecondUser() : $channel->getFirstUser();

                return new JsonResponse(true);
            }
        }
        return new JsonResponse(false);
    }

    /**
     * @Route("/{_locale}/chat/send/message", requirements={"_locale": "fr"}, name="send_message")
     */
    public function sendMessage(Request $request)
    {
        $user = $this->getUser();
        $channel = $request->get('channel');

        if (!$channel) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(["status"=>"error","message"=>"Cette chaine n'existe pas"]);
            }
            $this->addFlash("error", "Cette chaine n'existe pas");
            return $this->redirect($request->server->get('HTTP_REFERER'));
        }

        $message = $request->get('message');

        if (!$message) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(["status"=>"error","message"=>"Veuillez envoyer un message s'il vous plait"]);
            }
            $this->addFlash("error", "Veuillez envoyer un message s'il vous plait");
            return $this->redirect($request->server->get('HTTP_REFERER'));
        }

        $channel = $this->em->getRepository(Channel::class)->findOneBy(['name' => $channel]);

        if (!$channel) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(["status"=>"error","message"=>"Cette chaine n'existe pas"]);
            }
            $this->addFlash("error", "Cette chaine n'existe pas");
            return $this->redirect($request->server->get('HTTP_REFERER'));
        }

        if ($channel->getFirstUser()->getId() != $user->getId() && $channel->getSecondUser()->getId() !=$user->getId()) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(["status"=>"error","message"=>"Ce n'est pas votre conversation ici"]);
            }
            $this->addFlash("error", "Ce n'est pas votre conversation ici");
            return $this->redirect($request->server->get('HTTP_REFERER'));
        }

        $addressee = ($channel->getFirstUser()->getId() == $user->getId()) ? $channel->getSecondUser() : $channel->getFirstUser();
        $sender = ($channel->getFirstUser()->getId() != $user->getId()) ? $channel->getSecondUser() : $channel->getFirstUser();

        $msg = new Message();
        $msg->setContent($message);
        $msg->setChannel($channel);
        $msg->setAddressee($addressee);
        $msg->setSender($sender);
        $vraieDate = DateTime::createFromFormat('Y-m-d H:i:s', date('Y-m-d H:i:s'));
        $msg->setSentDate($vraieDate);

        $this->em->persist($msg);
        $this->em->flush();

        $date = $msg->getSentDate()->format("d/m/Y à H:i:s");
        $timestamp = $msg->getSentDate()->getTimestamp();

        $data = ['date'=>$date,'timestamp'=>$timestamp];

        if (!$addressee->isActiveNow()) {
            // Prepare the data
            $data = [
                'to' => [
                    [
                        'email' => $addressee->getEmailCanonical(),
                        'name' => $addressee->getDisplayName()
                    ]
                ],
                'templateId' => 14,
                'params' => [
                    "email_contact" => $user->getEmailCanonical()
                ]
            ];

            // Initialize cURL
            $ch = curl_init();

            // Set the cURL options
            curl_setopt($ch, CURLOPT_URL, 'https://api.brevo.com/v3/smtp/email');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'accept: application/json',
                'api-key: ' . $_SERVER['SENDBLUE_API_KEY'], // Replace with your actual API key
                'content-type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

            // Execute the request
            $response = curl_exec($ch);
            // Close cURL session
            curl_close($ch);
        }

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(["status"=>"success","message"=>"Message envoyé avec succès","data"=>$data]);
        }

        $this->addFlash("success", "Message envoyé avec succès");
        return $this->redirect($request->server->get('HTTP_REFERER'));
    }

    /**
     * @Route("/{_locale}/chat", name="chat", requirements={"_locale": "fr"})
     * @param Request $request
     * @param string $channel The channel name
     * @return void
     */
    public function index(Request $request)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $user = $this->getUser();
        $channel = $request->query->get('channel');
        if ($request->query->get('destinataire')) {
            $destinataire = $this->em->getRepository(User::class)->findOneBy(['id'=>$request->query->get('destinataire')]);
            if ($destinataire and $destinataire->getId() != $user->getId()) {
                $channel = $this->em->getRepository(Channel::class)->findOneByUsers($user->getId(), $destinataire->getId());
                if (!$channel) {
                    $channel = new Channel();
                    $channel->setFirstUser($user);
                    $channel->setSecondUser($destinataire);
                    $channel->setName(uniqid());
                    $this->em->persist($channel);
                    $this->em->flush();
                }
                return $this->redirectToRoute('chat_chat', ['channel'=>$channel->getName()]);
            }
            return $this->redirectToRoute('chat_chat');
        }

        if ($channel) {
            $channel = $this->em->getRepository(Channel::class)->findOneBy(['name'=>$channel]);
            // Si la chaine n'existe pas on renvoie sur la route du chat global
            if (!$channel) {
                return $this->redirectToRoute('chat_chat');
            }
            $destinataire = ($channel->getFirstUser() == $user) ? $channel->getSecondUser() : $channel->getFirstUser();

            // On récupère tout les messages de la chaine
            $messages = $this->em->getRepository(Message::class)->findBy(
                ['channel' => $channel],
                ['sentDate' => 'ASC']
            );

            // On récupère les informations du destinataire
            $destinataireData = $this->sm->readProDataWithoutAnnounce($destinataire->getId());
            $destinataireAvis = $this->sm->countUserCommentReceived($destinataire->getId());

            // On récupère les informations de l'utilisateur connecté
            $destinateurData = $this->sm->readProDataWithoutAnnounce($user->getId());
        } else {
            $channel = new Channel();
            $messages = [];
            $destinataireData = [];
            $destinataireAvis = [];
            $destinateurData = [];
        }

        // On récupère toutes les chaines de l'utilisateur connecté
        $channels = $this->em->getRepository(Channel::class)->findAllByUserId($user->getId());
        $channelsArray = [];
        foreach ($channels as $value) {
            $message = $this->em->getRepository(Message::class)->findOneBy(
                ['channel' => $value],
                ['sentDate' => 'DESC']
            );
            $dest = ($value->getFirstUser() == $user) ? $value->getSecondUser() : $value->getFirstUser();
            $destData = $this->sm->readProDataWithoutAnnounce($dest->getId());

            $channelsArray[] = ['channel'=>$value, 'message'=> $message, 'destinataire'=>$destData ];
        }
        return $this->render('chat/index.html.twig', [
            'header' => $this->sm->naveMenuItem(10),
            'footer' => $this->sm->naveMenuItem(18),
            'destinataire' => $destinataireData,
            'destinataireAvis' => $destinataireAvis,
            'ws_url' => '0.0.0.0:8080',
            'channel' => $channel,
            'messages' => $messages,
            'channels' => $channelsArray,
            'user' => $user,
            'destinateur' => $destinateurData,
            'prestations' => $this->sm->postCategorieWithMultilang('product_cat', 0),
            'youtube_url' => $this->em->getRepository(WpOptions::class)->findOneByOptionName('home-youtube')
        ]);
    }
}
