import { Oval } from 'react-loader-spinner';
import styles from './Loader.module.css';

function Loader() {
    return (
        <div className={styles.overlay}>
            <div className={styles.loader}>
                <Oval
                    height="60"
                    width="60"
                    color="white"
                    secondaryColor="white"
                    ariaLabel="audio-loading"
                    wrapperStyle={{}}
                    wrapperClass="wrapper-class"
                    visible={true}
                />
            </div>
        </div>
    );
}

export default Loader;
