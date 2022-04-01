// Import all necessary Storefront plugins
import PluginManager from 'src/plugin-system/plugin.manager';
import Paym1PaymentwallPayment6 from './Paym1PaymentwallPayment6/paym1-paymentwallpayment6.plugin';

// Register your plugin via the existing PluginManager
// const PluginManager = window.PluginManager;
PluginManager.register('Paym1PaymentwallPayment6', Paym1PaymentwallPayment6, '[data-pw-payment-methods-wrapper]');
