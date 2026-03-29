import { useEffect, useState } from '@wordpress/element';

const STAR = '\u2605';

const StarRating = ( { value } ) => {
    const stars = [];

    for ( let index = 1; index <= 5; index++ ) {
        stars.push(
            <span key={ index } className={ index <= parseInt( value, 10 ) ? 'rhwc-stars' : 'rhwc-stars--empty' }>
                { STAR }
            </span>
        );
    }

    return <span>{ stars }</span>;
};

const ManageReviews = () => {
    const globalData = window.rhwcReactGlobal || {};

    const [ reviews, setReviews ] = useState( [] );
    const [ loading, setLoading ] = useState( true );
    const [ page, setPage ] = useState( 1 );
    const [ totalPages, setTotalPages ] = useState( 1 );
    const [ filterProduct, setFilterProduct ] = useState( '' );
    const [ filterRating, setFilterRating ] = useState( '' );
    const [ editingId, setEditingId ] = useState( null );
    const [ editContent, setEditContent ] = useState( '' );
    const [ notice, setNotice ] = useState( null );

    const fetchReviews = async ( currentPage = 1 ) => {
        setLoading( true );

        const formData = new FormData();
        formData.append( 'action', 'rhwc_get_reviews' );
        formData.append( 'security', globalData.nonce );
        formData.append( 'paged', currentPage );
        formData.append( 'filter_product', filterProduct );
        formData.append( 'filter_rating', filterRating );

        try {
            const res = await fetch( globalData.ajaxUrl, { method: 'POST', body: formData } );
            const data = await res.json();

            if ( data.success ) {
                setReviews( data.data.reviews );
                setTotalPages( data.data.totalPages );
                setPage( currentPage );
            }
        } catch ( error ) {
            showNotice( 'Failed to load reviews.', 'error' );
        }

        setLoading( false );
    };

    useEffect( () => {
        fetchReviews( 1 );
    }, [] );

    const showNotice = ( message, type = 'success' ) => {
        setNotice( { msg: message, type } );
        setTimeout( () => setNotice( null ), 3500 );
    };

    const ajaxAction = async ( action, extra = {} ) => {
        const formData = new FormData();
        formData.append( 'action', action );
        formData.append( 'security', globalData.nonce );
        Object.entries( extra ).forEach( ( [ key, value ] ) => formData.append( key, value ) );

        const res = await fetch( globalData.ajaxUrl, { method: 'POST', body: formData } );
        return res.json();
    };

    const handleDelete = async ( id ) => {
        if ( ! window.confirm( 'Delete this review?' ) ) {
            return;
        }

        const data = await ajaxAction( 'rhwc_delete_review', { id } );

        if ( data.success ) {
            setReviews( reviews.filter( ( review ) => review.id !== id ) );
            showNotice( 'Review deleted.' );
        } else {
            showNotice( 'Failed to delete.', 'error' );
        }
    };

    const handleToggleStatus = async ( id ) => {
        const data = await ajaxAction( 'rhwc_toggle_review_status', { id } );

        if ( data.success ) {
            setReviews(
                reviews.map( ( review ) =>
                    review.id === id ? { ...review, approved: data.data.new_status } : review
                )
            );
        }
    };

    const startEdit = ( review ) => {
        setEditingId( review.id );
        setEditContent( review.content );
    };

    const handleSaveEdit = async ( id ) => {
        const data = await ajaxAction( 'rhwc_edit_review', { id, content: editContent } );

        if ( data.success ) {
            setReviews(
                reviews.map( ( review ) =>
                    review.id === id ? { ...review, content: editContent } : review
                )
            );
            setEditingId( null );
            showNotice( 'Review updated.' );
        } else {
            showNotice( 'Failed to update.', 'error' );
        }
    };

    const approvedCount = reviews.filter( ( review ) => review.approved === '1' ).length;
    const pendingCount = reviews.length - approvedCount;
    const activeFiltersCount = [ filterProduct, filterRating ].filter( Boolean ).length;

    return (
        <div className="rhwc-page-shell">
            { notice && (
                <div className={ `rhwc-notice rhwc-notice--${ notice.type }` }>
                    { notice.msg }
                </div>
            ) }

            <div className="rhwc-page-hero rhwc-page-hero--manage">
                <div className="rhwc-page-hero__main">
                    <span className="rhwc-page-hero__eyebrow">Review moderation</span>
                    <h2 className="rhwc-page-hero__title">Manage generated reviews</h2>
                    <p className="rhwc-page-hero__description">
                        Filter generated comments, approve or unapprove them, and quickly edit review copy without leaving the workspace.
                    </p>

                    <div className="rhwc-page-hero__chips">
                        <span className="rhwc-page-hero__chip">{ `${ reviews.length } loaded reviews` }</span>
                        <span className="rhwc-page-hero__chip">{ `${ activeFiltersCount } active filters` }</span>
                        <span className="rhwc-page-hero__chip">{ `Page ${ page } of ${ totalPages }` }</span>
                    </div>
                </div>

                <div className="rhwc-page-hero__stats">
                    <div className="rhwc-page-stat">
                        <span className="rhwc-page-stat__label">Approved</span>
                        <strong className="rhwc-page-stat__value">{ approvedCount }</strong>
                    </div>
                    <div className="rhwc-page-stat">
                        <span className="rhwc-page-stat__label">Pending</span>
                        <strong className="rhwc-page-stat__value">{ pendingCount }</strong>
                    </div>
                </div>
            </div>

            <div className="rhwc-manage-metrics">
                <div className="rhwc-manage-metric">
                    <span className="rhwc-manage-metric__label">Visible reviews</span>
                    <strong className="rhwc-manage-metric__value">{ reviews.length }</strong>
                </div>
                <div className="rhwc-manage-metric">
                    <span className="rhwc-manage-metric__label">Approved on this page</span>
                    <strong className="rhwc-manage-metric__value">{ approvedCount }</strong>
                </div>
                <div className="rhwc-manage-metric">
                    <span className="rhwc-manage-metric__label">Pending on this page</span>
                    <strong className="rhwc-manage-metric__value">{ pendingCount }</strong>
                </div>
            </div>

            <div className="rhwc-card rhwc-card--soft">
                <div className="rhwc-card-header">
                    <div>
                        <h2>Review table</h2>
                        <p>Use the filters below to narrow the list, then moderate or update the generated content inline.</p>
                    </div>
                    <span className="rhwc-card-badge">Review queue</span>
                </div>

                <div className="rhwc-filters">
                    <div className="rhwc-filter-field">
                        <label htmlFor="rhwc-filter-product">Product ID</label>
                        <input
                            id="rhwc-filter-product"
                            type="number"
                            className="rhwc-input"
                            value={ filterProduct }
                            onChange={ ( event ) => setFilterProduct( event.target.value ) }
                            placeholder="Any"
                        />
                    </div>

                    <div className="rhwc-filter-field">
                        <label htmlFor="rhwc-filter-rating">Rating</label>
                        <select
                            id="rhwc-filter-rating"
                            className="rhwc-select"
                            value={ filterRating }
                            onChange={ ( event ) => setFilterRating( event.target.value ) }
                        >
                            <option value="">All ratings</option>
                            { [ 5, 4, 3, 2, 1 ].map( ( ratingValue ) => (
                                <option key={ ratingValue } value={ ratingValue }>
                                    { `${ ratingValue } stars` }
                                </option>
                            ) ) }
                        </select>
                    </div>

                    <div className="rhwc-filters__actions">
                        <button
                            type="button"
                            className="rhwc-btn rhwc-btn--primary"
                            onClick={ () => fetchReviews( 1 ) }
                        >
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={ 2.5 }>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            Filter reviews
                        </button>
                        <button
                            type="button"
                            className="rhwc-btn rhwc-btn--secondary"
                            onClick={ () => {
                                setFilterProduct( '' );
                                setFilterRating( '' );
                                setTimeout( () => fetchReviews( 1 ), 0 );
                            } }
                        >
                            Reset
                        </button>
                    </div>
                </div>

                { loading ? (
                    <div className="rhwc-loading">
                        <div className="rhwc-spinner" />
                        <p>Loading reviews...</p>
                    </div>
                ) : reviews.length === 0 ? (
                    <div className="rhwc-empty">
                        <svg width="56" height="56" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={ 1 }>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                        <p>No reviews found. Try generating some from the Bulk Generate tab.</p>
                    </div>
                ) : (
                    <>
                        <div className="rhwc-manage-summary">
                            <span>{ `Showing ${ reviews.length } reviews` }</span>
                            <span>{ activeFiltersCount ? `${ activeFiltersCount } filters applied` : 'No filters applied' }</span>
                        </div>

                        <div className="rhwc-table-wrap">
                            <table className="rhwc-table">
                                <thead>
                                    <tr>
                                        <th>Reviewer</th>
                                        <th>Product</th>
                                        <th>Rating</th>
                                        <th>Review</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    { reviews.map( ( review ) => (
                                        <tr key={ review.id }>
                                            <td>
                                                <div className="rhwc-reviewer-cell">
                                                    <strong>{ review.author }</strong>
                                                    <span>{ review.date.split( ' ' )[ 0 ] }</span>
                                                </div>
                                            </td>

                                            <td>
                                                <a
                                                    className="rhwc-product-link"
                                                    href={ review.productEditUrl }
                                                    target="_blank"
                                                    rel="noreferrer"
                                                >
                                                    { review.productName }
                                                </a>
                                            </td>

                                            <td>
                                                <StarRating value={ review.rating } />
                                            </td>

                                            <td className="rhwc-review-cell">
                                                { editingId === review.id ? (
                                                    <div>
                                                        <textarea
                                                            className="rhwc-edit-textarea"
                                                            rows="3"
                                                            value={ editContent }
                                                            onChange={ ( event ) => setEditContent( event.target.value ) }
                                                        />
                                                        <div className="rhwc-edit-actions">
                                                            <button
                                                                type="button"
                                                                className="rhwc-btn rhwc-btn--primary rhwc-btn--sm"
                                                                onClick={ () => handleSaveEdit( review.id ) }
                                                            >
                                                                Save
                                                            </button>
                                                            <button
                                                                type="button"
                                                                className="rhwc-btn rhwc-btn--secondary rhwc-btn--sm"
                                                                onClick={ () => setEditingId( null ) }
                                                            >
                                                                Cancel
                                                            </button>
                                                        </div>
                                                    </div>
                                                ) : (
                                                    <span className="rhwc-review-excerpt">{ review.content }</span>
                                                ) }
                                            </td>

                                            <td>
                                                <span className={ `rhwc-badge ${ review.approved === '1' ? 'rhwc-badge--approved' : 'rhwc-badge--pending' }` }>
                                                    { review.approved === '1' ? 'Approved' : 'Pending' }
                                                </span>
                                            </td>

                                            <td>
                                                <div className="rhwc-table-actions">
                                                    <button
                                                        type="button"
                                                        className="rhwc-btn rhwc-btn--secondary rhwc-btn--sm"
                                                        onClick={ () => handleToggleStatus( review.id ) }
                                                    >
                                                        { review.approved === '1' ? 'Unapprove' : 'Approve' }
                                                    </button>
                                                    <button
                                                        type="button"
                                                        className="rhwc-btn rhwc-btn--secondary rhwc-btn--sm"
                                                        onClick={ () => startEdit( review ) }
                                                    >
                                                        Edit
                                                    </button>
                                                    <button
                                                        type="button"
                                                        className="rhwc-btn rhwc-btn--danger rhwc-btn--sm"
                                                        onClick={ () => handleDelete( review.id ) }
                                                    >
                                                        Delete
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ) ) }
                                </tbody>
                            </table>
                        </div>

                        { totalPages > 1 && (
                            <div className="rhwc-pagination">
                                { Array.from( { length: totalPages }, ( _, index ) => index + 1 ).map( ( paginationPage ) => (
                                    <button
                                        key={ paginationPage }
                                        type="button"
                                        className={ `rhwc-page-btn ${ paginationPage === page ? 'rhwc-page-btn--active' : '' }` }
                                        onClick={ () => fetchReviews( paginationPage ) }
                                    >
                                        { paginationPage }
                                    </button>
                                ) ) }
                            </div>
                        ) }
                    </>
                ) }
            </div>
        </div>
    );
};

export default ManageReviews;
