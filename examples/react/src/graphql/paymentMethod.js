import { gql } from '@apollo/client';

export const SET_PAYMENT_METHOD = gql`
  mutation setPaymentMethodOnCart($input: SetPaymentMethodOnCartInput!) {
    setPaymentMethodOnCart(input: $input) {
      cart {
        selected_payment_method {
          code
          title
        }
      }
    }
  }
`;

export async function setPaymentMethod(client, cartId, methodCode) {
  try {
    const { data } = await client.mutate({
      mutation: SET_PAYMENT_METHOD,
      variables: {
        input: {
          cart_id: cartId,
          payment_method: {
            code: methodCode,
          },
        },
      },
    });

    if (!data?.setPaymentMethodOnCart?.cart?.selected_payment_method) {
      throw new Error('Failed to set payment method: Invalid response');
    }

    return data.setPaymentMethodOnCart.cart.selected_payment_method;
  } catch (error) {
    if (error.message && error.message.includes('Failed to set payment method')) {
      throw error;
    }
    throw new Error(`Failed to set payment method: ${error.message || 'Unknown error'}`);
  }
}
