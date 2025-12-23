// High-level service to orchestrate the Magento payment flow.

import { createGuestCart } from '../graphql/guestCart';
import { setGuestEmailOnCart } from '../graphql/emailToCart';
import { setPaymentMethod } from '../graphql/paymentMethod';
import { setShippingMethod } from '../graphql/shippingMethod';
import { setShippingAddress } from '../graphql/shippingAddress';
import { setBillingAddress } from '../graphql/billingAddress';
import { placeOrder } from '../graphql/placeOrder';
import { productCart } from '../graphql/productCart';

import {
  MAGENTO_PRODUCT_SKU,
  MAGENTO_GUEST_EMAIL,
  MAGENTO_SHIPPING_FIRSTNAME,
  MAGENTO_SHIPPING_LASTNAME,
  MAGENTO_SHIPPING_STREET,
  MAGENTO_SHIPPING_CITY,
  MAGENTO_SHIPPING_POSTCODE,
  MAGENTO_SHIPPING_COUNTRY,
  MAGENTO_SHIPPING_TELEPHONE,
} from '../config';

function getPaymentUrl(orderResult) {
  return orderResult?.orderV2?.payment_methods
    ?.flatMap((pm) => pm.additional_data || [])
    ?.find((item) => item.name === 'payplug_payment_url')?.value;
}

export async function createPayment({ baseClient, methodCode }) {
  try {
    const cartId = await createGuestCart(baseClient);

    if (!cartId) {
      throw new Error('Unable to create guest cart');
    }

    await productCart(baseClient, cartId, MAGENTO_PRODUCT_SKU);
    await setGuestEmailOnCart(baseClient, cartId, MAGENTO_GUEST_EMAIL);

    const shippingAddress = {
      firstname: MAGENTO_SHIPPING_FIRSTNAME,
      lastname: MAGENTO_SHIPPING_LASTNAME,
      street: [MAGENTO_SHIPPING_STREET],
      city: MAGENTO_SHIPPING_CITY,
      postcode: MAGENTO_SHIPPING_POSTCODE,
      country_code: MAGENTO_SHIPPING_COUNTRY,
      telephone: MAGENTO_SHIPPING_TELEPHONE,
    };

    await setShippingAddress(baseClient, cartId, shippingAddress);
    await setShippingMethod(baseClient, cartId);
    await setBillingAddress(baseClient, cartId, { same_as_shipping: true });
    await setPaymentMethod(baseClient, cartId, methodCode);

    const orderResult = await placeOrder(baseClient, cartId);
    const paymentUrl = getPaymentUrl(orderResult);

    if (!paymentUrl) {
      throw new Error('Payment URL not found in order response');
    }

    return {
      order: orderResult,
      url: paymentUrl,
    };
  } catch (error) {
    // Re-throw with more context if needed
    if (error.message) {
      throw error;
    }
    throw new Error(`Payment creation failed: ${error.message || 'Unknown error'}`);
  }
}
