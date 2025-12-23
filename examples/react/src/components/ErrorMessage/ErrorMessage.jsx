import PropTypes from 'prop-types';
import styles from './ErrorMessage.module.css';

function ErrorMessage({ message }) {
    if (!message) return null;

    return (
        <p className={styles.error}>
            {message}
        </p>
    );
}

ErrorMessage.propTypes = {
    message: PropTypes.string,
};

export default ErrorMessage;

