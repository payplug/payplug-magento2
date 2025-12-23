import { gql } from '@apollo/client';

export const SET_SHIPPING_ADDRESSES_ON_CART = gql`
  mutation setShippingAddressesOnCart(
    $input: SetShippingAddressesOnCartInput!
  ) {
    setShippingAddressesOnCart(input: $input) {
      cart {
        shipping_addresses {
          firstname
          lastname
          company
          street
          city
          region {
            code
            label
          }
          postcode
          telephone
          country {
            code
            label
          }
          selected_shipping_method {
            carrier_code
            carrier_title
            method_code
            method_title
          }
        }
      }
    }
  }
`;

export async function setShippingAddress(client, cartId, address) {
  try {
    const { data } = await client.mutate({
      mutation: SET_SHIPPING_ADDRESSES_ON_CART,
      variables: {
        input: {
          cart_id: cartId,
          shipping_addresses: [
            {
              address: address,
            },
          ],
        },
      },
    });

    if (!data?.setShippingAddressesOnCart?.cart?.shipping_addresses) {
      throw new Error('Failed to set shipping address: Invalid response');
    }

    return data.setShippingAddressesOnCart.cart.shipping_addresses;
  } catch (error) {
    if (error.message && error.message.includes('Failed to set shipping address')) {
      throw error;
    }
    throw new Error(`Failed to set shipping address: ${error.message || 'Unknown error'}`);
  }
}
