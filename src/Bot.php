<?php

namespace RubikaBot;

use RubikaBot\Models\Update;
use RubikaBot\Handlers\Dispatcher;

class Bot extends RubikaClient
{
    /** @var Dispatcher */
    private $dispatcher;

    public function __construct($token)
    {
        parent::__construct($token);
        $this->dispatcher = new Dispatcher();
    }

    /**
     * @return Dispatcher
     */
    public function dispatcher()
    {
        return $this->dispatcher;
    }

    /**
     * @return array
     */
    public function getMe()
    {
        return parent::getMe();
    }

    /**
     * @param string $url
     * @param string $type
     * @return array
     */
    public function updateWebhook($url, $type = 'ReceiveUpdate')
    {
        return parent::updateWebhook($url, $type);
    }

    /**
     * @param mixed $params
     * @param string|null $text
     * @param array $options
     * @return array
     */
    public function sendMessage($params, $text = null, array $options = array())
    {
        return parent::sendMessage($params, $text, $options);
    }

    /**
     * @param array $updateData
     */
    public function handleWebhook(array $updateData)
    {
        // Persist incoming request when possible
        if (function_exists('\RubikaBot\saveIncomingRequest')) {
            try {
                \RubikaBot\saveIncomingRequest($updateData);
            } catch (\Throwable $e) {
                // ignore logging failures
            }
        } else {
            try {
                (new Logger())->saveIncomingRequest($updateData);
            } catch (\Throwable $e) {
                // ignore logging failures
            }
        }

        // Normalize payload: doc shows webhooks wrap payload in 'update' or 'inline_message'
        if (isset($updateData['update'])) {
            $payload = $updateData['update'];
        } elseif (isset($updateData['inline_message'])) {
            $inline = $updateData['inline_message'];
            // Convert inline message to an Update-shaped payload so Dispatcher can handle it
            $payload = array(
                'type' => \RubikaBot\Enums\UpdateType::NEW_MESSAGE,
                'chat_id' => isset($inline['chat_id']) ? $inline['chat_id'] : null,
                'new_message' => $inline,
            );
        } else {
            $payload = $updateData;
        }

        $this->dispatcher->dispatch($payload);
    }
}
