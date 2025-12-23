import { gql } from '@apollo/client';

import {
  MAGENTO_SUCCESS_URL,
  MAGENTO_CANCEL_URL,
  MAGENTO_FAIL_URL,
} from '../config';

export const PLACE_ORDER = gql`
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
`;

export async function placeOrder(client, cartId) {
  try {
    const { data } = await client.mutate({
      mutation: PLACE_ORDER,
      variables: {
        input: {
          cart_id: cartId,
          payplug_after_success_url: MAGENTO_SUCCESS_URL,
          payplug_after_cancel_url: MAGENTO_CANCEL_URL,
          payplug_after_failure_url: MAGENTO_FAIL_URL,
        },
      },
    });

    // Check for GraphQL errors in the response
    if (data?.placeOrder?.errors && data.placeOrder.errors.length > 0) {
      const errorMessages = data.placeOrder.errors
        .map((err) => err.message || err.code)
        .join(', ');
      throw new Error(`Order placement failed: ${errorMessages}`);
    }

    return data.placeOrder;
  } catch (error) {
    // Re-throw with more context if it's not already a formatted error
    if (error.message && error.message.includes('Order placement failed')) {
      throw error;
    }
    throw new Error(`Failed to place order: ${error.message || 'Unknown error'}`);
  }
}
