import Plugin from 'src/plugin-system/plugin.class';
import DomAccess from 'src/helper/dom-access.helper';
import HttpClient from 'src/service/http-client.service';
import PseudoModalUtil from 'src/utility/modal-extension/pseudo-modal.util';

export default class Paym1Paymentwall extends Plugin {

    init() {
        this._initPaymentMethod();
        this._client = new HttpClient();
    }

    onPaymentSelect(event) {
        let target = event.currentTarget;
        this.selectedPaymentMethod = target.getAttribute('data-pw-payment-method');
        if (this.selectedPaymentMethod) {
            this.requestPaymentMethod(this.selectedPaymentMethod);
        }
    }

    _initPaymentMethod() {
        this.elPaymentMethod = DomAccess.querySelectorAll(this.el, '.pw-payment-method input[type=radio]', false);

        if (this.elPaymentMethod) {
            this._registerButtonEvents();
        }
    }

    _registerButtonEvents() {
        this.elPaymentMethod.forEach((radio) => {
            radio.addEventListener('change', this.onPaymentSelect.bind(this));
        });
    }

    requestPaymentMethod(selectedPaymentMethod) {
        const url = window.paymentwall['save.paymentsystem'];
        this._client.post(url, selectedPaymentMethod, (response) => {
            const responseData = JSON.parse(response);
            if (!responseData.status) {
                const psSelect = DomAccess.querySelector(this.el, '#paymentMethod' + responseData.data.id)
                psSelect.checked = false;
                this.openModal(responseData.message)
            }
        });
    }

    openModal(response) {
        const pseudoModal = new PseudoModalUtil(response);
        pseudoModal.open();
    }
}
