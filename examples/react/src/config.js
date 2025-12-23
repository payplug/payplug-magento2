function requireEnv(name) {
  const value = process.env[name];

  if (!value) {
    throw new Error(`Missing required environment variable: ${name}`);
  }

  return value;
}

export const MAGENTO_GRAPHQL = requireEnv('REACT_APP_MAGENTO_GRAPHQL');
export const MAGENTO_SUCCESS_URL = requireEnv('REACT_APP_SUCCESS_URL');
export const MAGENTO_CANCEL_URL = requireEnv('REACT_APP_CANCEL_URL');
export const MAGENTO_FAIL_URL = requireEnv('REACT_APP_FAIL_URL');
export const MAGENTO_GUEST_EMAIL = requireEnv('REACT_APP_MAGENTO_GUEST_EMAIL');
export const MAGENTO_PRODUCT_SKU = requireEnv('REACT_APP_MAGENTO_PRODUCT_SKU');

// Shipping address configuration (optional, with defaults)
export const MAGENTO_SHIPPING_FIRSTNAME = requireEnv('REACT_APP_SHIPPING_FIRSTNAME');
export const MAGENTO_SHIPPING_LASTNAME = requireEnv('REACT_APP_SHIPPING_LASTNAME');
export const MAGENTO_SHIPPING_STREET = requireEnv('REACT_APP_SHIPPING_STREET');
export const MAGENTO_SHIPPING_CITY = requireEnv('REACT_APP_SHIPPING_CITY');
export const MAGENTO_SHIPPING_POSTCODE = requireEnv('REACT_APP_SHIPPING_POSTCODE');
export const MAGENTO_SHIPPING_COUNTRY = requireEnv('REACT_APP_SHIPPING_COUNTRY');
export const MAGENTO_SHIPPING_TELEPHONE = requireEnv('REACT_APP_SHIPPING_TELEPHONE');
