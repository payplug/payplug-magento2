import PropTypes from 'prop-types';
import PaymentButton from '../PaymentButton/PaymentButton';
import styles from './PaymentButtons.module.css';

function PaymentButtons({ disabled, onStandardPayment, onAmexPayment }) {
    return (
        <div className={styles.actions}>
            <PaymentButton
                onClick={onStandardPayment}
                disabled={disabled}
            >
                Payer par carte
            </PaymentButton>
            <PaymentButton
                onClick={onAmexPayment}
                disabled={disabled}
            >
                Payer via AMEX
            </PaymentButton>
        </div>
    );
}

PaymentButtons.propTypes = {
    disabled: PropTypes.bool,
    onStandardPayment: PropTypes.func.isRequired,
    onAmexPayment: PropTypes.func.isRequired,
};

export default PaymentButtons;

