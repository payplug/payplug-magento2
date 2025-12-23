import { gql } from '@apollo/client';

export const SET_SHIPPING_METHOD = gql`
  mutation setShippingMethodsOnCart($input: SetShippingMethodsOnCartInput!) {
    setShippingMethodsOnCart(input: $input) {
      cart {
        shipping_addresses {
          selected_shipping_method {
            carrier_code
            method_code
            carrier_title
            method_title
          }
        }
      }
    }
  }
`;

export async function setShippingMethod(client, cartId) {
  try {
    const { data } = await client.mutate({
      mutation: SET_SHIPPING_METHOD,
      variables: {
        input: {
          cart_id: cartId,
          shipping_methods: [
            {
              carrier_code: 'freeshipping',
              method_code: 'freeshipping',
            },
          ],
        },
      },
    });

    if (!data?.setShippingMethodsOnCart?.cart?.shipping_addresses) {
      throw new Error('Failed to set shipping method: Invalid response');
    }

    return data.setShippingMethodsOnCart.cart.shipping_addresses;
  } catch (error) {
    if (error.message && error.message.includes('Failed to set shipping method')) {
      throw error;
    }
    throw new Error(`Failed to set shipping method: ${error.message || 'Unknown error'}`);
  }
}
