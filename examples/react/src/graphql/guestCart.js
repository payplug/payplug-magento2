import { gql } from '@apollo/client';

export const CREATE_GUEST_CART = gql`
  mutation createGuestCart {
    createGuestCart {
      cart {
        id
      }
    }
  }
`;

export async function createGuestCart(client) {
  try {
    const { data } = await client.mutate({
      mutation: CREATE_GUEST_CART,
    });

    if (!data?.createGuestCart?.cart?.id) {
      throw new Error('Failed to create guest cart: Invalid response');
    }

    return data.createGuestCart.cart.id;
  } catch (error) {
    if (error.message && error.message.includes('Failed to create guest cart')) {
      throw error;
    }
    throw new Error(`Failed to create guest cart: ${error.message || 'Unknown error'}`);
  }
}
