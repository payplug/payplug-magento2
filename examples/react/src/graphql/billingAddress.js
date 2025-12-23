import { gql } from '@apollo/client';

export const SET_BILLING_ADDRESS_ON_CART = gql`
  mutation setBillingAddressOnCart($input: SetBillingAddressOnCartInput!) {
    setBillingAddressOnCart(input: $input) {
      cart {
        billing_address {
          firstname
          lastname
          company
          street
          city
          postcode
          telephone
          country {
            code
            label
          }
        }
      }
    }
  }
`;

export async function setBillingAddress(client, cartId, billingAddress) {
  try {
    const { data } = await client.mutate({
      mutation: SET_BILLING_ADDRESS_ON_CART,
      variables: {
        input: {
          cart_id: cartId,
          billing_address: billingAddress,
        },
      },
    });

    if (!data?.setBillingAddressOnCart?.cart?.billing_address) {
      throw new Error('Failed to set billing address: Invalid response');
    }

    return data.setBillingAddressOnCart.cart.billing_address;
  } catch (error) {
    if (error.message && error.message.includes('Failed to set billing address')) {
      throw error;
    }
    throw new Error(`Failed to set billing address: ${error.message || 'Unknown error'}`);
  }
}
