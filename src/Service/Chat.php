<?php

namespace App\Service;

use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;

use App\Entity\Message;
use App\Entity\Channel;
use App\Entity\User;

use Symfony\Bridge\Doctrine\RegistryInterface;

class Chat implements MessageComponentInterface
{
    private $clients;

    private $users = [];

    private $botName = 'ChatBot';

    private $defaultChannel = 'general';

    private $em;

    public function __construct($em, $mailer, $twig)
    {
        $this->em = $em;
        $this->mailer = $mailer;
        $this->twig = $twig;
        $this->clients = new \SplObjectStorage();
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
        $this->users[$conn->resourceId] = [
            'connection' => $conn,
            'user' => '',
            'channels' => []
        ];
        echo sprintf("L'utilisateur %d vient de se connecter\n", $conn->resourceId);
    }

    public function onClose(ConnectionInterface $closedConnection)
    {
        // Suppression de la connexion des utilisateurs
        unset($this->users[$closedConnection->resourceId]);

        $this->clients->detach($closedConnection);
        echo sprintf("L'utilisateur %d s'est déconnecté\n", $closedConnection->resourceId);
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        $conn->send('An error has occurred: '.$e->getMessage());
        $conn->close();
    }

    public function onMessage(ConnectionInterface $conn, $message)
    {
        $messageData = json_decode($message);
        if ($messageData === null) {
            return false;
        }

        $action = $messageData->action ?? 'unknown';
        $channel = $messageData->channel ?? $this->defaultChannel;
        $user = $messageData->user ?? $this->botName;
        $userId = $messageData->userId ?? '';
        $message = $messageData->message ?? '';
        $date = $messageData->date ?? date("d/m/Y à H:i:s");
        $vraieDate = $messageData->vraiedate ?? date("Y-m-d H:i:s");

        switch ($action) {
            case 'subscribe':
                $this->subscribeToChannel($conn, $channel, $user);
                return true;
            case 'unsubscribe':
                $this->unsubscribeFromChannel($conn, $channel, $user);
                return true;
            case 'message':
                $channel = $this->em->getRepository(Channel::class)->findOneBy(['name' => $channel]);
                $user = $this->em->getRepository(User::class)->findOneBy(
                    array('id' => $userId)
                );
                $addressee = ($channel->getFirstUser()->getId() == $userId) ? $channel->getSecondUser() : $channel->getFirstUser();
                $sender = ($channel->getFirstUser()->getId() != $userId) ? $channel->getSecondUser() : $channel->getFirstUser();

                $msg = new Message();
                $msg->setContent($message);
                $msg->setChannel($channel);
                $msg->setAddressee($addressee);
                $msg->setSender($sender);
                $vraieDate = \DateTime::createFromFormat('Y-m-d H:i:s', date('Y-m-d H:i:s'));
                $msg->setSentDate($vraieDate);

                $this->em->persist($msg);
                $this->em->flush();

                $date = $msg->getSentDate()->format("d/m/Y à H:i:s");
                $timestamp = $msg->getSentDate()->getTimestamp();

                $connected = false;
                foreach ($this->users as $connectionId => $userConnection) {
                    if ($userConnection['user'] == $addressee->getUsername()) {
                        $connected = true;
                    }
                }

                /*if(!$connected){
                    if($this->sendEmailToNotConnectedChannel($conn, $channel, $user, $userId)){
                        echo sprintf("Message envoyé à %s\n", $addressee->getUsername());
                    }
                }*/

                return $this->sendMessageToChannel($conn, $channel->getName(), $user->getUsername(), $message, $date, $timestamp, $connected);
            case 'is_tiping':
                return $this->sendIsTipingToChannel($conn, $channel, $user, $userId);
            case 'is_read':
                return $this->sendIsReadToChannel($conn, $channel, $user, $userId);
            default:
                echo sprintf("L'action '%s' n'est pas supportée!\n", $action);
                break;
        }
        return false;
    }

    private function subscribeToChannel(ConnectionInterface $conn, $channel, $user)
    {
        $this->users[$conn->resourceId]['channels'][$channel] = $channel;
        $this->users[$conn->resourceId]['user'] = $user;
        echo sprintf("%s a rejoint la discussion qui a pour chaine : #%s\n", $user, $channel);
    }

    private function unsubscribeFromChannel(ConnectionInterface $conn, $channel, $user)
    {
        if (array_key_exists($channel, $this->users[$conn->resourceId]['channels'])) {
            unset($this->users[$conn->resourceId]['channels']);
        }
        echo sprintf("%s a quitté la discussion qui a pour chaine : #%s\n", $user, $channel);
    }

    private function sendMessageToChannel(ConnectionInterface $conn, $channel, $user, $message, $date, $timestamp, $connected)
    {
        if (!isset($this->users[$conn->resourceId]['channels'][$channel])) {
            return false;
        }
        foreach ($this->users as $connectionId => $userConnection) {
            if (array_key_exists($channel, $userConnection['channels'])) {
                $userConnection['connection']->send(json_encode([
                    'action' => 'message',
                    'channel' => $channel,
                    'user' => $user,
                    'message' => $message,
                    'date' => $date ?? date("d/m/Y à H:i:s"),
                    'timestamp' => $timestamp,
                    'connected' => $connected,
                ]));
            }
        }
        return true;
    }

    private function sendIsTipingToChannel(ConnectionInterface $conn, $channel, $user, $userId)
    {
        if (!isset($this->users[$conn->resourceId]['channels'][$channel])) {
            return false;
        }
        foreach ($this->users as $connectionId => $userConnection) {
            if (array_key_exists($channel, $userConnection['channels'])) {
                $userConnection['connection']->send(json_encode([
                    'action' => 'is_tiping',
                    'channel' => $channel,
                    'user' => $user,
                    'userId' => $userId,
                ]));
            }
        }
        return true;
    }

    private function sendEmailToNotConnectedChannel(ConnectionInterface $conn, $channel, $user, $userId)
    {
        if (!isset($this->users[$conn->resourceId]['channels'][$channel])) {
            return false;
        }
        foreach ($this->users as $connectionId => $userConnection) {
            if (array_key_exists($channel, $userConnection['channels'])) {
                $userConnection['connection']->send(json_encode([
                    'action' => 'send_email',
                    'channel' => $channel,
                    'user' => $user,
                    'userId' => $userId,
                ]));
            }
        }
        return true;
    }

    private function sendIsReadToChannel(ConnectionInterface $conn, $channel, $user, $userId)
    {
        if (!isset($this->users[$conn->resourceId]['channels'][$channel])) {
            return false;
        }
        foreach ($this->users as $connectionId => $userConnection) {
            if (array_key_exists($channel, $userConnection['channels'])) {
                $chan = $this->em->getRepository(Channel::class)->findOneBy(['name' => $channel]);
                if ($chan) {
                    $this->em->getRepository(Message::class)->markAsReadByChannelIdAndReceiverId($chan->getId(), $userId);
                }
                $userConnection['connection']->send(json_encode([
                    'action' => 'is_read',
                    'channel' => $channel,
                    'user' => $user,
                    'userId' => $userId,
                ]));
            }
        }
        return true;
    }
}
