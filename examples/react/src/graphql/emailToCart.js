import { gql } from '@apollo/client';

export const SET_GUEST_EMAIL_ON_CART = gql`
  mutation setGuestEmailOnCart($cartId: String!, $email: String!) {
    setGuestEmailOnCart(input: { cart_id: $cartId, email: $email }) {
      cart {
        email
      }
    }
  }
`;

export async function setGuestEmailOnCart(client, cartId, email) {
  try {
    const { data } = await client.mutate({
      mutation: SET_GUEST_EMAIL_ON_CART,
      variables: {
        cartId,
        email,
      },
    });

    if (!data?.setGuestEmailOnCart?.cart) {
      throw new Error('Failed to set guest email: Invalid response');
    }

    return data.setGuestEmailOnCart.cart.email;
  } catch (error) {
    if (error.message && error.message.includes('Failed to set guest email')) {
      throw error;
    }
    throw new Error(`Failed to set guest email: ${error.message || 'Unknown error'}`);
  }
}
