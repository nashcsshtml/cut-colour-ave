<?php

namespace AmeliaBooking\Application\Commands\Notification;

use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Services\Notification\AbstractWhatsAppNotificationService;
use AmeliaBooking\Domain\Entity\Notification\NotificationLog;
use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Infrastructure\Repository\Notification\NotificationLogRepository;
use Exception;
use Interop\Container\Exception\ContainerException;
use Slim\Exception\ContainerValueNotFoundException;

/**
 * Class WhatsAppWebhookCommandHandler
 *
 * @package AmeliaBooking\Application\Commands\Notification
 */
class WhatsAppWebhookCommandHandler extends CommandHandler
{
    /**
     * @param WhatsAppWebhookCommand $command
     *
     * @return CommandResult
     * @throws ContainerValueNotFoundException
     * @throws ContainerException
     * @throws Exception
     */
    public function handle(WhatsAppWebhookCommand $command)
    {
        $result = new CommandResult();

        /** @var SettingsService $settingsService */
        $settingsService = $this->container->get('domain.settings.service');

        /** @var NotificationLogRepository $notificationLogRepo */
        $notificationLogRepo = $this->container->get('domain.notificationLog.repository');

        if (!$settingsService->isFeatureEnabled('whatsapp')) {
            $result->setResult(CommandResult::RESULT_SUCCESS);
            $result->setMessage('WhatsApp feature not enabled');
            $result->setData([]);

            return $result;
        }

        /** @var AbstractWhatsAppNotificationService $whatsAppNotificationService */
        $whatsAppNotificationService = $this->getContainer()->get('application.whatsAppNotification.service');

        $data = $command->getFields();

        $phones = [];
        $waIds  = [];

        $data = apply_filters('amelia_before_whatsapp_webhook_filter', $data);

        do_action('amelia_before_whatsapp_webhook', $data);

        foreach ($data['entry'] as $entry) {
            foreach ($entry['changes'] as $change) {
                if ($change['field'] === 'messages') {
                    if (!empty($change['value']['messages'])) {
                        foreach ($change['value']['messages'] as $message) {
                            $phones[] = $message['from'];
                            $enabled = $settingsService->getSetting('notifications', 'whatsAppReplyEnabled');

                            if (!empty($enabled)) {
                                $whatsAppNotificationService->sendMessage($message['from']);
                            }
                        }
                    }
                    if (!empty($change['value']['statuses'])) {
                        foreach ($change['value']['statuses'] as $message) {
                            $waIds[] = $message['id'];
                            try {
                                $notificationLog = $notificationLogRepo->getByEntityId($message['id'], 'messageId');
                                /** @var NotificationLog $log */
                                foreach ($notificationLog->getItems() as $log) {
                                    $logData = $log->getData() ? $log->getData()->getValue() : null;
                                    $logData = $logData ? json_decode($logData, true) : [];
                                    $logData['webhook_data'] = $message;
                                    $notificationLogRepo->updateFieldById($log->getId()->getValue(), json_encode($logData), 'data');
                                }
                            } catch (Exception $e) {
                            }
                        }
                    }
                }
            }
        }

        do_action('amelia_after_whatsapp_webhook', $data, $phones);

        $result->setResult(CommandResult::RESULT_SUCCESS);
        $result->setMessage('Webhook successfully processed');
        $result->setData([
            'status_ids' => $waIds,
            'auto_reply_phones' => $phones
        ]);

        return $result;
    }
}
