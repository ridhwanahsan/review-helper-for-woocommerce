import { useState } from '@wordpress/element';

const RATING_LABELS = {
    '1-5': '1 to 5 stars',
    '3-5': '3 to 5 stars',
    '4-5': '4 to 5 stars',
    '3': '3 stars only',
    '4': '4 stars only',
    '5': '5 stars only',
};

const STYLE_LABELS = {
    short: 'Short blurbs',
    medium: 'Balanced paragraphs',
    long: 'Detailed paragraphs',
};

const countLines = ( value ) =>
    value
        .split( '\n' )
        .map( ( item ) => item.trim() )
        .filter( Boolean ).length;

const Settings = () => {
    const globalData = window.rhwcReactGlobal || {};
    const s = globalData.settings || {};

    const [ defaultCount, setDefaultCount ] = useState( s.defaultCount || '5' );
    const [ defaultRating, setDefaultRating ] = useState( s.defaultRating || '4-5' );
    const [ defaultNames, setDefaultNames ] = useState( s.defaultNames || '' );
    const [ customComments, setCustomComments ] = useState( s.customComments || '' );
    const [ contentStyle, setContentStyle ] = useState( s.contentStyle || 'medium' );
    const [ enableBulk, setEnableBulk ] = useState( s.enableBulk || 'yes' );
    const [ deleteDataOnUninstall, setDeleteDataOnUninstall ] = useState( s.deleteDataOnUninstall || 'no' );
    const [ saving, setSaving ] = useState( false );
    const [ message, setMessage ] = useState( '' );
    const [ msgType, setMsgType ] = useState( 'success' );

    const handleSave = async () => {
        setSaving( true );
        setMessage( '' );

        const formData = new FormData();
        formData.append( 'action', 'rhwc_save_settings' );
        formData.append( 'security', globalData.nonce );
        formData.append( 'default_count', defaultCount );
        formData.append( 'default_rating', defaultRating );
        formData.append( 'default_names', defaultNames );
        formData.append( 'custom_comments', customComments );
        formData.append( 'content_style', contentStyle );
        formData.append( 'enable_bulk', enableBulk );
        formData.append( 'delete_data_on_uninstall', deleteDataOnUninstall );

        try {
            const res = await fetch( globalData.ajaxUrl, { method: 'POST', body: formData } );
            const data = await res.json();

            if ( data.success ) {
                setMsgType( 'success' );
                setMessage( data.data.message || 'Settings saved.' );

                if ( window.rhwcReactGlobal ) {
                    window.rhwcReactGlobal.settings = {
                        ...window.rhwcReactGlobal.settings,
                        defaultCount,
                        defaultRating,
                        defaultNames,
                        customComments,
                        contentStyle,
                        enableBulk,
                        deleteDataOnUninstall,
                    };
                }
            } else {
                setMsgType( 'error' );
                setMessage( 'Failed to save. ' + ( data.data?.message || '' ) );
            }
        } catch ( error ) {
            setMsgType( 'error' );
            setMessage( 'Server error. Please try again.' );
        }

        setSaving( false );
        setTimeout( () => setMessage( '' ), 4000 );
    };

    const summaryItems = [
        {
            label: 'Default batch size',
            value: `${ defaultCount } reviews`,
        },
        {
            label: 'Default rating',
            value: RATING_LABELS[ defaultRating ] || '4 to 5 stars',
        },
        {
            label: 'Fallback style',
            value: STYLE_LABELS[ contentStyle ] || 'Balanced paragraphs',
        },
        {
            label: 'Bulk tab',
            value: enableBulk === 'yes' ? 'Visible in sidebar' : 'Hidden from sidebar',
        },
    ];

    return (
        <div className="rhwc-page-shell">
            <div className="rhwc-page-hero rhwc-page-hero--settings">
                <div className="rhwc-page-hero__main">
                    <span className="rhwc-page-hero__eyebrow">Workspace settings</span>
                    <h2 className="rhwc-page-hero__title">Defaults and safeguards</h2>
                    <p className="rhwc-page-hero__description">
                        Control the default review setup, manage your custom name and comment pools, and decide how the plugin behaves across the workspace.
                    </p>

                    <div className="rhwc-page-hero__chips">
                        <span className="rhwc-page-hero__chip">{ `${ defaultCount } default reviews` }</span>
                        <span className="rhwc-page-hero__chip">{ enableBulk === 'yes' ? 'Bulk tab enabled' : 'Bulk tab hidden' }</span>
                        <span className="rhwc-page-hero__chip">{ deleteDataOnUninstall === 'yes' ? 'Data removal enabled' : 'Data kept on uninstall' }</span>
                    </div>
                </div>

                <div className="rhwc-page-hero__stats">
                    <div className="rhwc-page-stat">
                        <span className="rhwc-page-stat__label">Names loaded</span>
                        <strong className="rhwc-page-stat__value">{ countLines( defaultNames ) || 0 }</strong>
                    </div>
                    <div className="rhwc-page-stat">
                        <span className="rhwc-page-stat__label">Custom comments</span>
                        <strong className="rhwc-page-stat__value">{ countLines( customComments ) || 0 }</strong>
                    </div>
                </div>
            </div>

            { message && (
                <div className={ `rhwc-notice rhwc-notice--${ msgType }` }>
                    { message }
                </div>
            ) }

            <div className="rhwc-page-grid rhwc-page-grid--sidebar">
                <div className="rhwc-settings-stack">
                    <div className="rhwc-card rhwc-card--soft">
                        <div className="rhwc-card-header">
                            <div>
                                <h2>Generation defaults</h2>
                                <p>Set the starting values used whenever new review batches are created.</p>
                            </div>
                            <span className="rhwc-card-badge">Core setup</span>
                        </div>

                        <div className="rhwc-form-grid">
                            <div className="rhwc-form-row">
                                <label className="rhwc-form__label" htmlFor="rhwc-default-count">Reviews per product</label>
                                <input
                                    id="rhwc-default-count"
                                    type="number"
                                    className="rhwc-input"
                                    min="1"
                                    max="100"
                                    value={ defaultCount }
                                    onChange={ ( event ) => setDefaultCount( event.target.value ) }
                                />
                            </div>

                            <div className="rhwc-form-row">
                                <label className="rhwc-form__label" htmlFor="rhwc-default-rating">Default rating</label>
                                <select
                                    id="rhwc-default-rating"
                                    className="rhwc-select"
                                    value={ defaultRating }
                                    onChange={ ( event ) => setDefaultRating( event.target.value ) }
                                >
                                    <option value="5">5 stars only</option>
                                    <option value="4">4 stars only</option>
                                    <option value="3">3 stars only</option>
                                    <option value="4-5">4 to 5 stars</option>
                                    <option value="3-5">3 to 5 stars</option>
                                    <option value="1-5">1 to 5 stars</option>
                                </select>
                            </div>

                            <div className="rhwc-form-row">
                                <label className="rhwc-form__label" htmlFor="rhwc-content-style">Content style</label>
                                <select
                                    id="rhwc-content-style"
                                    className="rhwc-select"
                                    value={ contentStyle }
                                    onChange={ ( event ) => setContentStyle( event.target.value ) }
                                >
                                    <option value="short">Short</option>
                                    <option value="medium">Medium</option>
                                    <option value="long">Long</option>
                                </select>
                            </div>
                        </div>

                        <label className="rhwc-toggle-card">
                            <input
                                type="checkbox"
                                checked={ enableBulk === 'yes' }
                                onChange={ ( event ) => setEnableBulk( event.target.checked ? 'yes' : 'no' ) }
                            />
                            <span className="rhwc-toggle-card__content">
                                <span className="rhwc-toggle-card__title">Enable Bulk Generate tab</span>
                                <span className="rhwc-toggle-card__desc">Hide or show the bulk generation section in the sidebar navigation.</span>
                            </span>
                        </label>
                    </div>

                    <div className="rhwc-card rhwc-card--soft">
                        <div className="rhwc-card-header">
                            <div>
                                <h2>Default reviewer names</h2>
                                <p>Add one reviewer name per line to build a reusable pool for generated content.</p>
                            </div>
                            <span className="rhwc-card-badge">{ countLines( defaultNames ) ? `${ countLines( defaultNames ) } names` : 'Optional' }</span>
                        </div>

                        <div className="rhwc-form-row">
                            <textarea
                                className="rhwc-textarea rhwc-textarea--wide"
                                rows="8"
                                value={ defaultNames }
                                onChange={ ( event ) => setDefaultNames( event.target.value ) }
                                placeholder="One name per line&#10;Jane Smith&#10;Michael Chen&#10;Sara Muller"
                            />
                            <div className="rhwc-tip-box">
                                One reviewer name per line. Leave this empty to keep using the plugin's built-in generic name pool.
                            </div>
                        </div>
                    </div>

                    <div className="rhwc-card rhwc-card--soft">
                        <div className="rhwc-card-header">
                            <div>
                                <h2>Custom review comments</h2>
                                <p>Store your own fallback review lines and let the generator reuse them during creation.</p>
                            </div>
                            <span className="rhwc-card-badge">{ countLines( customComments ) ? `${ countLines( customComments ) } comments` : 'Fallback content' }</span>
                        </div>

                        <div className="rhwc-form-row">
                            <textarea
                                className="rhwc-textarea rhwc-textarea--wide"
                                rows="10"
                                value={ customComments }
                                onChange={ ( event ) => setCustomComments( event.target.value ) }
                                placeholder="One comment per line&#10;Great product, very happy!&#10;Delivery was fast and packaging excellent."
                            />
                            <div className="rhwc-tip-box">
                                Use <code>&lt;product&gt;</code> inside a line to insert the product name automatically. Leave the list empty to use the selected content style instead.
                            </div>
                        </div>
                    </div>

                    <div className="rhwc-card rhwc-card--soft">
                        <div className="rhwc-card-header">
                            <div>
                                <h2>Danger zone</h2>
                                <p>These settings affect how much plugin data stays behind if the plugin is later removed.</p>
                            </div>
                            <span className="rhwc-card-badge rhwc-card-badge--danger">Use carefully</span>
                        </div>

                        <div className="rhwc-danger-zone">
                            <label className="rhwc-danger-zone__label">
                                <input
                                    type="checkbox"
                                    checked={ deleteDataOnUninstall === 'yes' }
                                    onChange={ ( event ) => setDeleteDataOnUninstall( event.target.checked ? 'yes' : 'no' ) }
                                />
                                Delete all plugin data when uninstalling
                            </label>
                            <p className="rhwc-danger-zone__desc">
                                Settings and plugin metadata will be removed when uninstalling. Generated WooCommerce reviews remain unless you delete them manually.
                            </p>
                        </div>

                        <div className="rhwc-action-bar">
                            <div className="rhwc-action-bar__content">
                                <strong>Save changes</strong>
                                <span>Your updated defaults will be used by the React dashboard right after saving.</span>
                            </div>

                            <button
                                type="button"
                                className="rhwc-btn rhwc-btn--primary"
                                disabled={ saving }
                                onClick={ handleSave }
                            >
                                { saving ? (
                                    <>
                                        <span className="rhwc-inline-spinner" />
                                        Saving...
                                    </>
                                ) : (
                                    <>
                                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={ 2.5 }>
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                        Save settings
                                    </>
                                ) }
                            </button>
                        </div>
                    </div>
                </div>

                <aside className="rhwc-card rhwc-card--soft">
                    <div className="rhwc-card-header">
                        <div>
                            <h2>Current setup</h2>
                            <p>A compact summary of the defaults that will be used across the dashboard.</p>
                        </div>
                    </div>

                    <div className="rhwc-info-list">
                        { summaryItems.map( ( item ) => (
                            <div key={ item.label } className="rhwc-info-list__item">
                                <span className="rhwc-info-list__label">{ item.label }</span>
                                <strong className="rhwc-info-list__value">{ item.value }</strong>
                            </div>
                        ) ) }
                    </div>

                    <div className="rhwc-note-card">
                        <strong>Suggestion</strong>
                        <p>Keep a small but varied custom comment list so generated reviews feel more natural across different products.</p>
                    </div>
                </aside>
            </div>
        </div>
    );
};

export default Settings;
