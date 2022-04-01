const ApiService = Shopware.Classes.ApiService;
const { Application } = Shopware;

class paymentwallApiTestCredential extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'pw-api-validate-credential') {
        super(httpClient, loginService, apiEndpoint);
    }

    check(values) {
        const headers = this.getBasicHeaders({});

        return this.httpClient
            .post(`_action/${this.getApiBasePath()}/verify`, values,{
                headers
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

Application.addServiceProvider('paymentwallApiTestCredential', (container) => {
    const initContainer = Application.getContainer('init');
    return new paymentwallApiTestCredential(initContainer.httpClient, container.loginService);
});
