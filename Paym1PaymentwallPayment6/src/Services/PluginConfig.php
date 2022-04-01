<?php

namespace Paym1PaymentwallPayment6\Services;

class PluginConfig
{
    private $rawConfig;

    public function __construct(array $rawConfig)
    {
        $this->rawConfig = $rawConfig;
    }

    public function getProjectKey(): ?string
    {
        return $this->getConfigValueOrNull('projectKey');
    }

    public function getSecretKey(): ?string
    {
        return $this->getConfigValueOrNull('secretKey');
    }

    public function getTestMode(): ?bool
    {
        return $this->getConfigValueOrNull('testMode');
    }

    public function getWidgetCode(): ?string
    {
        return $this->getConfigValueOrNull('widgetCode');
    }

    public function isRefundEnabled(): ?bool
    {
        return $this->getConfigValueOrNull('isRefundEnabled');
    }

    private function getConfigValueOrNull($configKey)
    {
        return $this->rawConfig[$configKey] ?? null;
    }
}