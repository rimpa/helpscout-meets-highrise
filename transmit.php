<?php

define('highrise_authentication_account', '***');
define('highrise_authentication_token', '***');
define('helpscout_webhook_secret_key', '***');

function br2nl($string)
{
	$return = eregi_replace('<br[[:space:]]*/?' .
			'[[:space:]]*>', chr(13) . chr(10), $string);
	return $return;
}

require_once('lib/ignaciovazquez/Highrise-PHP-Api/lib/HighriseAPI.class.php');
require_once('lib/helpscout/helpscout-api-php/src/HelpScout/Webhook.php');
$webhook = new \HelpScout\Webhook(helpscout_webhook_secret_key);
if (!$webhook->isValid())
{
	throw new \Exception('Webhook is not correctly signed.');
}
$eventType = $webhook->getEventType();
switch ($eventType)
{
	case 'convo.created':
	case 'convo.customer.reply.created':
	case 'convo.agent.reply.created':
	case 'convo.note.created':
		$conversation = $webhook->getConversation();

		$hr = new \HighriseAPI();
		$hr->debug = false;
		$hr->setAccount(highrise_authentication_account);
		$hr->setToken(highrise_authentication_token);

		$email = $conversation->getCustomer()->getEmail();
		$people = $hr->findPeopleByEmail($email);

		if (count($people))
		{
			$person = $people[0];
		}
		if (!isset($person))
		{
			$person = new \HighrisePerson($hr);
			$person->setFirstName($conversation->getCustomer()->getFirstName());
			$person->setLastName($conversation->getCustomer()->getLastName());
			$person->addEmailAddress($email);
			$person->save();
		}
		$threads = $conversation->getThreads();
		$body = strip_tags(br2nl($threads[0]->getBody()));
		$sender = $threads[0]->getCreatedBy();
		if ($email != $sender->getEmail())
		{
			$title = "Reply by " . $sender->getFirstName() . ' ' . $sender->getLastName() . ':' . $conversation->getSubject();
		}
		else
		{
			$title = $conversation->getSubject();
		}

		$new_email = new \HighriseEmail($hr);
		$new_email->debug = true;
		$new_email->setSubjectType("Party");
		$new_email->setSubjectId($person->getId());
		$new_email->setTitle($title);
		$new_email->setBody($body);
		try
		{
			$new_email->save();
		}
		catch (Exception $e)
		{
			// TODO: log error
		}
		break;
	case 'convo.deleted':

		break;
	case 'customer.created':
		break;
}

echo "done";