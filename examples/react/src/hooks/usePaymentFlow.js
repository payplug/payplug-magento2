// Custom hook that orchestrates the payment flow and exposes UI-friendly state.

import { useCallback, useState } from 'react';
import { createPayment } from '../services/payment';

export function usePaymentFlow(client) {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const payWithMethod = useCallback(
    async (methodCode) => {
      setLoading(true);
      setError(null);

      try {
        const { order, url } = await createPayment({
          baseClient: client,
          methodCode,
        });

        if (!url) {
          const errorMessage = 'Payment URL not available. Please contact support.';
          setError(errorMessage);
          throw new Error(errorMessage);
        }

        window.location.replace(url);
        return order;
      } catch (err) {
        const errorMessage = err.message || 'Unexpected error during payment';
        setError(errorMessage);
        throw err;
      }
    },
    [client]
  );

  const payWithStandard = useCallback(
    () => payWithMethod('payplug_payments_standard'),
    [payWithMethod]
  );

  const payWithAmex = useCallback(
    () => payWithMethod('payplug_payments_amex'),
    [payWithMethod]
  );

  return {
    loading,
    error,
    payWithStandard,
    payWithAmex,
  };
}
