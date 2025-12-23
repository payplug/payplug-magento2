import PropTypes from 'prop-types';
import styles from './PaymentButton.module.css';

function PaymentButton({ onClick, disabled, children }) {
    return (
        <button
            className={styles.button}
            onClick={onClick}
            disabled={disabled}
            type="button"
        >
            {children}
        </button>
    );
}

PaymentButton.propTypes = {
    onClick: PropTypes.func.isRequired,
    disabled: PropTypes.bool,
    children: PropTypes.node.isRequired,
};

export default PaymentButton;

