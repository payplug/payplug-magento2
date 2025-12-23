import './styles/App.css';

import { useApolloClient } from '@apollo/client';
import { usePaymentFlow } from './hooks/usePaymentFlow';
import Loader from './components/Loader/Loader';
import Header from './components/Header/Header';
import PaymentButtons from './components/PaymentButtons/PaymentButtons';
import ErrorMessage from './components/ErrorMessage/ErrorMessage';

function App() {
  const client = useApolloClient();
  const { loading, error, payWithStandard, payWithAmex } =
    usePaymentFlow(client);

  return (
    <div className="App">
      <Header />
      <main className="App-content">
        {loading ? <Loader /> : null}
        <PaymentButtons
          onStandardPayment={payWithStandard}
          onAmexPayment={payWithAmex}
        />
        <ErrorMessage message={error} />
      </main>
    </div>
  );
}

export default App;
