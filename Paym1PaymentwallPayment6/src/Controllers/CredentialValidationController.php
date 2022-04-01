<?php

namespace Paym1PaymentwallPayment6\Controllers;

use Paym1PaymentwallPayment6\Services\PluginConfigService;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class CredentialValidationController
{
    private $pluginConfigService;
    public function __construct(PluginConfigService $pluginConfigService)
    {
        $this->pluginConfigService = $pluginConfigService;
    }

    /**
     * @Route(path="/api/_action/pw-api-validate-credential/verify", name="api.action.paymentwall.validate-credential-v64", methods={"POST"})
     */
    public function validateCredentialsV64(Request $request)
    {
        return $this->validateCredentials($request);

    }

    /**
     * @Route(path="/api/v{version}/_action/pw-api-validate-credential/verify", name="api.action.paymentwall.validate-credential-v63", methods={"POST"})
     */
    public function validateCredentialsV63(Request $request)
    {
        return $this->validateCredentials($request);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    private function validateCredentials(Request $request) {

        $credentials = $this->prepareCredentials($request);

        $result = $this->pluginConfigService->validateCredentials($credentials);
        if ($result) {
            return new JsonResponse(['success' => true]);
        }

        return new JsonResponse(['success' => false]);
    }

    /**
     * @param Request $request
     * @return array
     */
    private function prepareCredentials(Request $request)
    {
        $projectKey = $request->get('projectKey');
        $secretKey = $request->get('secretKey');
        $pluginConfig = $this->pluginConfigService->getPaymentwallPluginConfigForSalesChannel();
        return [
            'public_key' => isset($projectKey) ? $projectKey : $pluginConfig->getProjectKey(),
            'private_key' => isset($secretKey) ? $secretKey : $pluginConfig->getSecretKey()
        ];
    }
}
