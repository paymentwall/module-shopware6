const { Component, Mixin } = Shopware;
import template from './pw-button-validate-credential.html.twig';

Component.register('pw-button-validate-credential', {
    template,
    props: ['label'],
    inject: ['paymentwallApiTestCredential'],

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            isLoading: false,
            isSaveSuccessful: false,
        };
    },

    computed: {
        getSaleChanelId() {
            let $parent = this.$parent;

            while (!$parent.actualConfigData) {
                $parent = $parent.$parent;
            }

            return ($parent.currentSalesChannelId !== undefined) ? $parent.currentSalesChannelId : null ;
        }
    },

    methods: {
        saveFinish() {
            this.isSaveSuccessful = false;
        },

        getConfigValue() {
            const salesChannelId = this.getSaleChanelId;
            const config = this.getPluginConfig(salesChannelId);

            let credentials = {};
            if (config.hasOwnProperty('Paym1PaymentwallPayment6.config.projectKey')) {
                credentials['projectKey'] =  config['Paym1PaymentwallPayment6.config.projectKey'];
            }
            if (config.hasOwnProperty('Paym1PaymentwallPayment6.config.secretKey')) {
                credentials['secretKey'] =  config['Paym1PaymentwallPayment6.config.secretKey'];
            }
            return credentials;
        },

        getPluginConfig(saleChanelId) {
            let $parent = this.$parent;

            while ($parent.actualConfigData === undefined) {
                $parent = $parent.$parent;
            }

            if (!saleChanelId) {
                return $parent.actualConfigData.null
            }
            return $parent.actualConfigData[saleChanelId];
        },

        check() {
            this.isLoading = true;

            this.paymentwallApiTestCredential.check(this.getConfigValue()).then((res) => {
                if (res.success) {
                    this.isSaveSuccessful = true;
                    this.createNotificationSuccess({
                        title: this.$tc('pw-button-validate-credential.title'),
                        message: this.$tc('pw-button-validate-credential.success')
                    });
                } else {
                    this.createNotificationError({
                        title: this.$tc('pw-button-validate-credential.title'),
                        message: this.$tc('pw-button-validate-credential.error')
                    });
                }

                this.isLoading = false;
            });
        }
    }
})
