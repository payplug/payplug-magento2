import { gql } from '@apollo/client';

export const ADD_SIMPLE_PRODUCTS_TO_CART = gql`
  mutation addSimpleProductsToCart($cartId: String!, $sku: String!) {
    addSimpleProductsToCart(
      input: {
        cart_id: $cartId
        cart_items: [{ data: { quantity: 1, sku: $sku } }]
      }
    ) {
      cart {
        itemsV2 {
          items {
            id
            product {
              sku
              stock_status
            }
            quantity
          }
        }
      }
    }
  }
`;

export async function productCart(client, cartId, sku) {
  try {
    const { data } = await client.mutate({
      mutation: ADD_SIMPLE_PRODUCTS_TO_CART,
      variables: {
        cartId,
        sku,
      },
    });

    if (!data?.addSimpleProductsToCart?.cart?.itemsV2?.items) {
      throw new Error('Failed to add product to cart: Invalid response');
    }

    return data.addSimpleProductsToCart.cart.itemsV2.items;
  } catch (error) {
    if (error.message && error.message.includes('Failed to add product')) {
      throw error;
    }
    throw new Error(`Failed to add product to cart: ${error.message || 'Unknown error'}`);
  }
}
