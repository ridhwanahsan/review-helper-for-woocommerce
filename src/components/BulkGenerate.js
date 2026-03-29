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

const countNames = ( value ) =>
    value
        .split( '\n' )
        .map( ( item ) => item.trim() )
        .filter( Boolean ).length;

const countIds = ( value ) =>
    value
        .split( ',' )
        .map( ( item ) => item.trim() )
        .filter( Boolean ).length;

const BulkGenerate = () => {
    const globalData = window.rhwcReactGlobal || { settings: {} };
    const s = globalData.settings || {};

    const [ products, setProducts ] = useState( 'all' );
    const [ specificIds, setSpecificIds ] = useState( '' );
    const [ count, setCount ] = useState( s.defaultCount || 5 );
    const [ rating, setRating ] = useState( s.defaultRating || '4-5' );
    const [ style, setStyle ] = useState( s.contentStyle || 'medium' );
    const [ names, setNames ] = useState( s.defaultNames || '' );
    const [ isGenerating, setIsGenerating ] = useState( false );
    const [ status, setStatus ] = useState( '' );
    const [ statusType, setStatusType ] = useState( 'info' );

    const selectedIdsCount = countIds( specificIds );
    const reviewerPoolCount = countNames( names );

    const handleGenerate = async () => {
        setIsGenerating( true );
        setStatus( 'Preparing generation. Please wait...' );
        setStatusType( 'info' );

        const formData = new FormData();
        formData.append( 'action', 'rhwc_generate_bulk' );
        formData.append( 'security', globalData.nonce );
        formData.append( 'product_ids', products === 'all' ? 'all' : specificIds );
        formData.append( 'count', count );
        formData.append( 'rating', rating );
        formData.append( 'style', style );
        formData.append( 'names', names );
        formData.append( 'exclude_generated', true );

        try {
            const res = await fetch( globalData.ajaxUrl, { method: 'POST', body: formData } );
            const data = await res.json();

            if ( data.success ) {
                setStatusType( 'success' );
                setStatus( data.data.message );
            } else {
                setStatusType( 'error' );
                setStatus( 'Error: ' + ( data.data?.message || 'Generation failed.' ) );
            }
        } catch ( e ) {
            setStatusType( 'error' );
            setStatus( 'Server connection error. Please try again.' );
        }

        setIsGenerating( false );
    };

    return (
        <div className="rhwc-page-shell">
            <div className="rhwc-page-hero rhwc-page-hero--bulk">
                <div className="rhwc-page-hero__main">
                    <span className="rhwc-page-hero__eyebrow">Bulk generation</span>
                    <h2 className="rhwc-page-hero__title">Create reviews in batches</h2>
                    <p className="rhwc-page-hero__description">
                        Generate review sets for all published products or a hand-picked list, while reusing your saved rating and content preferences.
                    </p>

                    <div className="rhwc-page-hero__chips">
                        <span className="rhwc-page-hero__chip">{ `${ count } reviews per product` }</span>
                        <span className="rhwc-page-hero__chip">{ RATING_LABELS[ rating ] || '4 to 5 stars' }</span>
                        <span className="rhwc-page-hero__chip">{ STYLE_LABELS[ style ] || 'Balanced paragraphs' }</span>
                    </div>
                </div>

                <div className="rhwc-page-hero__stats">
                    <div className="rhwc-page-stat">
                        <span className="rhwc-page-stat__label">Target scope</span>
                        <strong className="rhwc-page-stat__value">
                            { products === 'all' ? 'All products' : `${ selectedIdsCount || 0 } IDs` }
                        </strong>
                    </div>
                    <div className="rhwc-page-stat">
                        <span className="rhwc-page-stat__label">Reviewer pool</span>
                        <strong className="rhwc-page-stat__value">
                            { reviewerPoolCount ? `${ reviewerPoolCount } names` : 'Built-in names' }
                        </strong>
                    </div>
                </div>
            </div>

            <div className="rhwc-page-grid rhwc-page-grid--sidebar">
                <div className="rhwc-card rhwc-card--soft">
                    <div className="rhwc-card-header">
                        <div>
                            <h2>Generation setup</h2>
                            <p>Choose the target products, review volume, and fallback style before starting the batch.</p>
                        </div>
                        <span className="rhwc-card-badge">Live form</span>
                    </div>

                    <div className="rhwc-form-section">
                        <div className="rhwc-form-section__head">
                            <h3>Target products</h3>
                            <p>Pick whether the batch should run across the whole catalog or a specific set of product IDs.</p>
                        </div>

                        <div className="rhwc-choice-grid">
                            <label className={ `rhwc-choice-card ${ products === 'all' ? 'rhwc-choice-card--active' : '' }` }>
                                <input
                                    type="radio"
                                    name="rhwc-target"
                                    value="all"
                                    checked={ products === 'all' }
                                    onChange={ () => setProducts( 'all' ) }
                                />
                                <span className="rhwc-choice-card__content">
                                    <span className="rhwc-choice-card__title">All published products</span>
                                    <span className="rhwc-choice-card__desc">Runs across the catalog and skips products that already received generated reviews.</span>
                                </span>
                            </label>

                            <label className={ `rhwc-choice-card ${ products === 'specific' ? 'rhwc-choice-card--active' : '' }` }>
                                <input
                                    type="radio"
                                    name="rhwc-target"
                                    value="specific"
                                    checked={ products === 'specific' }
                                    onChange={ () => setProducts( 'specific' ) }
                                />
                                <span className="rhwc-choice-card__content">
                                    <span className="rhwc-choice-card__title">Specific product IDs</span>
                                    <span className="rhwc-choice-card__desc">Target only selected products by entering a comma-separated ID list.</span>
                                </span>
                            </label>
                        </div>

                        { products === 'specific' && (
                            <div className="rhwc-inline-field">
                                <label className="rhwc-form__label" htmlFor="rhwc-specific-ids">Product IDs</label>
                                <input
                                    id="rhwc-specific-ids"
                                    type="text"
                                    className="rhwc-input rhwc-input--wide"
                                    value={ specificIds }
                                    onChange={ ( event ) => setSpecificIds( event.target.value ) }
                                    placeholder="e.g. 15, 23, 105"
                                />
                                <span className="rhwc-desc">Add one or more product IDs separated by commas.</span>
                            </div>
                        ) }
                    </div>

                    <div className="rhwc-form-section">
                        <div className="rhwc-form-section__head">
                            <h3>Review defaults</h3>
                            <p>These values shape the batch that gets created for each selected product.</p>
                        </div>

                        <div className="rhwc-form-grid">
                            <div className="rhwc-form-row">
                                <label className="rhwc-form__label" htmlFor="rhwc-bulk-count">Reviews per product</label>
                                <input
                                    id="rhwc-bulk-count"
                                    type="number"
                                    className="rhwc-input"
                                    value={ count }
                                    onChange={ ( event ) => setCount( event.target.value ) }
                                    min="1"
                                    max="100"
                                />
                            </div>

                            <div className="rhwc-form-row">
                                <label className="rhwc-form__label" htmlFor="rhwc-bulk-rating">Rating mix</label>
                                <select
                                    id="rhwc-bulk-rating"
                                    className="rhwc-select"
                                    value={ rating }
                                    onChange={ ( event ) => setRating( event.target.value ) }
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
                                <label className="rhwc-form__label" htmlFor="rhwc-bulk-style">Fallback style</label>
                                <select
                                    id="rhwc-bulk-style"
                                    className="rhwc-select"
                                    value={ style }
                                    onChange={ ( event ) => setStyle( event.target.value ) }
                                >
                                    <option value="short">Short</option>
                                    <option value="medium">Medium</option>
                                    <option value="long">Long</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div className="rhwc-form-section">
                        <div className="rhwc-form-section__head">
                            <h3>Reviewer names</h3>
                            <p>Provide one name per line to rotate through a custom pool during generation.</p>
                        </div>

                        <div className="rhwc-form-row">
                            <textarea
                                className="rhwc-textarea rhwc-textarea--wide"
                                rows="6"
                                value={ names }
                                onChange={ ( event ) => setNames( event.target.value ) }
                                placeholder="One name per line&#10;Jane Smith&#10;John Doe"
                            />
                            <span className="rhwc-desc">Leave this empty to use the plugin's built-in name list.</span>
                        </div>
                    </div>

                    <div className="rhwc-action-bar">
                        <div className="rhwc-action-bar__content">
                            <strong>Ready to start</strong>
                            <span>
                                { products === 'all'
                                    ? 'The batch will scan all published products and skip already-generated items.'
                                    : 'The batch will run only for the IDs you entered above.'
                                }
                            </span>
                        </div>

                        <button
                            type="button"
                            className="rhwc-btn rhwc-btn--primary"
                            disabled={ isGenerating }
                            onClick={ handleGenerate }
                        >
                            { isGenerating ? (
                                <>
                                    <span className="rhwc-inline-spinner" />
                                    Generating...
                                </>
                            ) : (
                                <>
                                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={ 2.5 }>
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                    Start bulk generation
                                </>
                            ) }
                        </button>
                    </div>

                    { status && (
                        <div className={ `rhwc-status-bar rhwc-status-bar--${ statusType }` }>
                            { status }
                        </div>
                    ) }
                </div>

                <aside className="rhwc-card rhwc-card--soft">
                    <div className="rhwc-card-header">
                        <div>
                            <h2>Batch summary</h2>
                            <p>A quick view of the current rules that will be used for this run.</p>
                        </div>
                    </div>

                    <div className="rhwc-info-list">
                        <div className="rhwc-info-list__item">
                            <span className="rhwc-info-list__label">Target</span>
                            <strong className="rhwc-info-list__value">
                                { products === 'all' ? 'All published products' : selectedIdsCount ? `${ selectedIdsCount } specific IDs` : 'Specific products' }
                            </strong>
                        </div>
                        <div className="rhwc-info-list__item">
                            <span className="rhwc-info-list__label">Reviews per product</span>
                            <strong className="rhwc-info-list__value">{ count }</strong>
                        </div>
                        <div className="rhwc-info-list__item">
                            <span className="rhwc-info-list__label">Rating profile</span>
                            <strong className="rhwc-info-list__value">{ RATING_LABELS[ rating ] || '4 to 5 stars' }</strong>
                        </div>
                        <div className="rhwc-info-list__item">
                            <span className="rhwc-info-list__label">Fallback content</span>
                            <strong className="rhwc-info-list__value">{ STYLE_LABELS[ style ] || 'Balanced paragraphs' }</strong>
                        </div>
                    </div>

                    <div className="rhwc-note-card">
                        <strong>Tip</strong>
                        <p>Use a custom reviewer list when you want more variation across the batch and a less repetitive feel.</p>
                    </div>
                </aside>
            </div>
        </div>
    );
};

export default BulkGenerate;
