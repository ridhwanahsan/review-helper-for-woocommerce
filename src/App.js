import { useState } from '@wordpress/element';
import Dashboard from './components/Dashboard';
import BulkGenerate from './components/BulkGenerate';
import ManageReviews from './components/ManageReviews';
import Settings from './components/Settings';

const App = () => {
    const globalData = window.rhwcReactGlobal || { settings: {} };
    const [ activeTab, setActiveTab ] = useState( 'dashboard' );

    const renderTab = () => {
        switch ( activeTab ) {
            case 'bulk':
                return <BulkGenerate />;
            case 'manage':
                return <ManageReviews />;
            case 'settings':
                return <Settings />;
            case 'dashboard':
            default:
                return <Dashboard setActiveTab={ setActiveTab } globalData={globalData} />;
        }
    };

    return (
        <div className="wrap rhwc-react-wrapper">
            <h1 className="wp-heading-inline" style={{ display: 'none' }}>Review Helper</h1>
            <h2 className="nav-tab-wrapper">
                <button
                    className={ `nav-tab ${ activeTab === 'dashboard' ? 'nav-tab-active' : '' }` }
                    onClick={ () => setActiveTab( 'dashboard' ) }
                >
                    Dashboard
                </button>
                { globalData.settings?.enableBulk === 'yes' && (
                    <button
                        className={ `nav-tab ${ activeTab === 'bulk' ? 'nav-tab-active' : '' }` }
                        onClick={ () => setActiveTab( 'bulk' ) }
                    >
                        Bulk Generate
                    </button>
                ) }
                <button
                    className={ `nav-tab ${ activeTab === 'manage' ? 'nav-tab-active' : '' }` }
                    onClick={ () => setActiveTab( 'manage' ) }
                >
                    Manage Reviews
                </button>
                <button
                    className={ `nav-tab ${ activeTab === 'settings' ? 'nav-tab-active' : '' }` }
                    onClick={ () => setActiveTab( 'settings' ) }
                >
                    Settings
                </button>
            </h2>

            <div className="rhwc-tab-content">
                { renderTab() }
            </div>
        </div>
    );
};

export default App;
