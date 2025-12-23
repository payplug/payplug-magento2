import logo from '../../assets/images/payplug-logo.svg';
import styles from './Header.module.css';

function Header() {
    return (
        <header className={styles.header}>
            <a
                className={styles.link}
                href="https://www.payplug.com/"
                target="_blank"
                rel="noopener noreferrer"
            >
                <img src={logo} alt="Payplug" />
            </a>
        </header>
    );
}

export default Header;
