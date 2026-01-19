# Headless

This documentation describes how to use Payplug standard card payment with a headless application.

## Prerequisites

> **Disclaimer:**  
> The Payplug module will only work with the **redirected option**.
>
> So please set the `Payment Page` option to `Redirected` for it to work :
>```bash
>bin/magento config:set payplug_payments/general/payment_page redirected
>```

## Developments

Payplug can work very well in a headless setup with only a few small adjustments.

### Method code

You will need to set and store the Payplug payment method code in your Adobe Commerce (Magento) instance when the user clicks on a payment method.

To do this, use the GraphQL `setPaymentMethodCodeOnCart` mutation ([official documentation](https://developer.adobe.com/commerce/webapi/graphql/schema/cart/mutations/set-payment-method/)) and set an available Payplug method code:

```graphql
mutation {
  setPaymentMethodOnCart(input: {
      cart_id: "rMQdWEecBZr4SVWZwj2AF6y0dNCKQ8uH"
      payment_method: {
          code: "payplug_payments_standard"
      }
  }) {
    cart {
      selected_payment_method {
        code
        title
      }
    }
  }
}
```

Check the example at `examples/react/src/graphql/paymentMethod.js`.

Available methods:
* payplug_payments_standard
* payplug_payments_amex

### Redirection

Once the payment has been validated by the user in the purchase funnel, you will have to redirect them to the Payplug payment interface.

To do this, Payplug provides you with the redirection URL in the response of the GraphQL `placeOrder` mutation ([official documentation](https://developer.adobe.com/commerce/webapi/graphql/schema/cart/mutations/place-order/)) request, under the name `payplug_payment_url` in the `additional_data`.

So add the `additional_data` key to your `placeOrder` mutation in order to retrieve this information:

```graphql
  mutation placeOrder($input: PlaceOrderInput!) {
    placeOrder(input: $input) {
      orderV2 {
        number
        token
        payment_methods {
          additional_data {
            name
            value
          }
        }
      }
      errors {
        message
        code
      }
    }
  }
```

You then just need to retrieve the value of `payplug_payment_url` and perform the redirection:

```js
function getPaymentUrl(orderResult) {
  return orderResult?.orderV2?.payment_methods
    ?.flatMap((pm) => pm.additional_data || [])
    ?.find((item) => item.name === 'payplug_payment_url')?.value;
}

// Results of the placeOrder mutation request
const orderResult = await placeOrder(baseClient, cartId);

// Extract the payplug_payment_url value from the placeOrder results
const paymentUrl = getPaymentUrl(orderResult);

// Redirection
window.location.replace(paymentUrl);
```

Check the example at `examples/react/src/graphql/placeOrder.js`, `examples/react/src/services/payment.js` and `examples/react/src/hooks/usePaymentFlow.js`.

### Return pages

Once the redirection has been carried out and the payment has been finalized by the user in the dedicated interface, Payplug will redirect the user back to the headless application.

It will therefore be necessary to **provide three return URLs to Payplug** so that it can redirect the user according to the status of the payment: **success**, **cancel**, **fail**.

To do this, you will have to declare the following three new variables in your `placeOrder` mutation ([official documentation](https://developer.adobe.com/commerce/webapi/graphql/schema/cart/mutations/place-order/)):

```js
{
    mutation: PLACE_ORDER,
    variables: {
        input: {
            cart_id: cartId,
            payplug_after_success_url: 'https://www.headless-instance.com/payment-after-success',
            payplug_after_cancel_url: 'https://www.headless-instance.com/payment-after-cancel',
            payplug_after_failure_url: 'https://www.headless-instance.com/payment-after-failure',
        },
    },
}
```

Check the example at `examples/react/src/graphql/placeOrder.js`.

## Postman collection

A collection of GraphQL queries for the Postman application is available at: `examples/postman/PAYPLUG.postman_collection.json`.

This collection allows you to **automatically generate an order** without having to go through a series of often tedious manual actions (product sheet, adding one or more products, checkout, selection of a delivery address, selection of a delivery method, etc.).

## React

A React.js example is also available at the following address: `examples/react`.

This application allows you to see a concrete example of an implementation, analyze the code and test a standard payment and an American Express payment via a simplified interface.

To install the application, please follow the `examples/react/README.md` file.

### How it works

The application automatically generates an order from a guest user via a series of GraphQL queries performed via the following methods with the Apollo library:
* `createGuestCart()`: creation of a cart in guest mode
* `productCart()`: adding a product to the cart
* `setGuestEmailOnCart()`: adding the user email
* `setShippingAddress()`: adding the delivery address
* `setShippingMethod()`: adding a delivery method
* `setBillingAddress()`: adding the billing address

All these steps are very common on Magento. The part specific to Payplug is visible from `examples/react/src/services/payment.js:54`:
* `setPaymentMethod()`: method to add the Payplug payment method code
* `getPaymentUrl()`: extracts the Payplug payment URL from the placeOrder mutation results

You will also find the redirection from Adobe Commerce to the Payplug payment interface in `examples/react/src/hooks/usePaymentFlow.js:27` and the addition of return URLs in `examples/react/src/graphql/placeOrder.js:37` that allows the Payplug payment interface to redirect the user to the headless instance depending on payment status (success, cancel or fail).
