import { useState, useEffect, useCallback } from '@wordpress/element';

const StarRating = ({ rating }) => {
    return (
        <span style={{ color: '#f0b429', fontSize: '16px' }}>
            { Array.from({ length: 5 }, (_, i) => (
                <span key={i}>{ i < rating ? '★' : '☆' }</span>
            )) }
        </span>
    );
};

const ManageReviews = () => {
    const globalData = window.rhwcReactGlobal || {};
    const [ reviews, setReviews ] = useState([]);
    const [ pagination, setPagination ] = useState({ current: 1, total: 1 });
    const [ loading, setLoading ] = useState(true);
    const [ filterProduct, setFilterProduct ] = useState('');
    const [ filterRating, setFilterRating ] = useState('');
    const [ editingId, setEditingId ] = useState(null);
    const [ editContent, setEditContent ] = useState('');
    const [ message, setMessage ] = useState('');
    const [ products, setProducts ] = useState([]);

    const fetchReviews = useCallback(async (page = 1) => {
        setLoading(true);
        const formData = new FormData();
        formData.append('action', 'rhwc_get_reviews');
        formData.append('security', globalData.nonce);
        formData.append('paged', page);
        if (filterProduct) formData.append('filter_product', filterProduct);
        if (filterRating) formData.append('filter_rating', filterRating);

        try {
            const res = await fetch(globalData.ajaxUrl, { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                setReviews(data.data.reviews || []);
                setPagination({ current: page, total: data.data.totalPages });
            }
        } catch(e) {}
        setLoading(false);
    }, [filterProduct, filterRating, globalData.nonce, globalData.ajaxUrl]);

    // Fetch products for filter dropdown
    useEffect(() => {
        const fetchProducts = async () => {
            const formData = new FormData();
            formData.append('action', 'rhwc_get_products_list');
            formData.append('security', globalData.nonce);
            try {
                const res = await fetch(globalData.ajaxUrl, { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) setProducts(data.data || []);
            } catch(e) {}
        };
        fetchProducts();
    }, [globalData.nonce, globalData.ajaxUrl]);

    useEffect(() => {
        fetchReviews(1);
    }, [fetchReviews]);

    const showMessage = (msg) => {
        setMessage(msg);
        setTimeout(() => setMessage(''), 3000);
    };

    const handleDelete = async (id) => {
        if (!window.confirm('Are you sure you want to delete this review?')) return;
        const formData = new FormData();
        formData.append('action', 'rhwc_delete_review');
        formData.append('security', globalData.nonce);
        formData.append('id', id);
        try {
            const res = await fetch(globalData.ajaxUrl, { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                setReviews(prev => prev.filter(r => r.id !== id));
                showMessage('Review deleted successfully.');
            }
        } catch(e) {}
    };

    const handleToggleStatus = async (id) => {
        const formData = new FormData();
        formData.append('action', 'rhwc_toggle_review_status');
        formData.append('security', globalData.nonce);
        formData.append('id', id);
        try {
            const res = await fetch(globalData.ajaxUrl, { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                const newStatus = data.data.new_status;
                setReviews(prev => prev.map(r => r.id === id ? { ...r, approved: newStatus } : r));
                showMessage('Status updated.');
            }
        } catch(e) {}
    };

    const handleSaveEdit = async (id) => {
        const formData = new FormData();
        formData.append('action', 'rhwc_edit_review');
        formData.append('security', globalData.nonce);
        formData.append('id', id);
        formData.append('content', editContent);
        try {
            const res = await fetch(globalData.ajaxUrl, { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                setReviews(prev => prev.map(r => r.id === id ? { ...r, content: editContent } : r));
                setEditingId(null);
                showMessage('Review updated successfully.');
            }
        } catch(e) {}
    };

    return (
        <div className="rhwc-card">
            <h2><span className="dashicons dashicons-list-view"></span> Manage Reviews</h2>

            { message && (
                <div className="notice notice-success is-dismissible" style={{ padding: '10px 15px', marginBottom: '15px' }}>
                    <p style={{ margin: 0 }}>{ message }</p>
                </div>
            ) }

            {/* Filters */}
            <div style={{ display: 'flex', gap: '12px', marginBottom: '20px', flexWrap: 'wrap', alignItems: 'flex-end' }}>
                <div>
                    <label style={{ display: 'block', marginBottom: '5px', fontWeight: 600 }}>Filter by Product</label>
                    <select
                        value={ filterProduct }
                        onChange={ e => setFilterProduct(e.target.value) }
                        style={{ minWidth: '200px' }}
                    >
                        <option value="">All Products</option>
                        { products.map(p => (
                            <option key={p.id} value={p.id}>{ p.name }</option>
                        ))}
                    </select>
                </div>
                <div>
                    <label style={{ display: 'block', marginBottom: '5px', fontWeight: 600 }}>Filter by Rating</label>
                    <select value={ filterRating } onChange={ e => setFilterRating(e.target.value) }>
                        <option value="">All Ratings</option>
                        { [1,2,3,4,5].map(r => (
                            <option key={r} value={r}>{ r } Star{ r > 1 ? 's' : '' }</option>
                        ))}
                    </select>
                </div>
                <button className="button button-primary" onClick={() => fetchReviews(1)}>
                    Apply Filters
                </button>
            </div>

            { loading ? (
                <div style={{ textAlign: 'center', padding: '40px', color: '#50575e' }}>
                    <span className="dashicons dashicons-update" style={{ fontSize: '32px', animation: 'spin 1s linear infinite' }}></span>
                    <p>Loading reviews…</p>
                </div>
            ) : reviews.length === 0 ? (
                <div style={{ textAlign: 'center', padding: '40px', color: '#50575e', background: '#f9f9f9', borderRadius: '8px' }}>
                    <span className="dashicons dashicons-format-quote" style={{ fontSize: '48px', color: '#c3c4c7' }}></span>
                    <p>No generated reviews found.</p>
                </div>
            ) : (
                <>
                    <table className="wp-list-table widefat fixed striped" style={{ borderRadius: '8px', overflow: 'hidden' }}>
                        <thead>
                            <tr>
                                <th style={{ width: '17%' }}>Reviewer</th>
                                <th style={{ width: '18%' }}>Product</th>
                                <th style={{ width: '12%' }}>Rating</th>
                                <th>Review Content</th>
                                <th style={{ width: '10%' }}>Status</th>
                                <th style={{ width: '18%' }}>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            { reviews.map(review => (
                                <tr key={ review.id }>
                                    <td>
                                        <strong>{ review.author }</strong><br />
                                        <small style={{ color: '#646970' }}>{ review.date }</small>
                                    </td>
                                    <td>
                                        <a href={ review.productEditUrl } target="_blank" rel="noreferrer">
                                            { review.productName }
                                        </a>
                                    </td>
                                    <td><StarRating rating={ parseInt(review.rating) } /></td>
                                    <td>
                                        { editingId === review.id ? (
                                            <div>
                                                <textarea
                                                    className="widefat"
                                                    rows="3"
                                                    value={ editContent }
                                                    onChange={ e => setEditContent(e.target.value) }
                                                />
                                                <div style={{ marginTop: '6px', display: 'flex', gap: '6px' }}>
                                                    <button className="button button-primary button-small" onClick={() => handleSaveEdit(review.id)}>Save</button>
                                                    <button className="button button-small" onClick={() => setEditingId(null)}>Cancel</button>
                                                </div>
                                            </div>
                                        ) : (
                                            <span>{ review.content }</span>
                                        )}
                                    </td>
                                    <td>
                                        <span className={ `rhwc-status-badge ${ review.approved === '1' ? 'status-approved' : 'status-pending' }` }>
                                            { review.approved === '1' ? 'Approved' : 'Pending' }
                                        </span>
                                    </td>
                                    <td>
                                        <div style={{ display: 'flex', flexDirection: 'column', gap: '5px' }}>
                                            <button className="button button-small" onClick={() => handleToggleStatus(review.id)}>
                                                { review.approved === '1' ? 'Unapprove' : 'Approve' }
                                            </button>
                                            <button className="button button-small" onClick={() => {
                                                setEditingId(review.id);
                                                setEditContent(review.content);
                                            }}>Edit</button>
                                            <button className="button button-small" style={{ color: '#d63638', borderColor: '#d63638' }} onClick={() => handleDelete(review.id)}>Delete</button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>

                    { pagination.total > 1 && (
                        <div style={{ marginTop: '20px', display: 'flex', gap: '6px', justifyContent: 'center' }}>
                            { Array.from({ length: pagination.total }, (_, i) => i + 1).map(pg => (
                                <button
                                    key={ pg }
                                    className={ `button ${ pagination.current === pg ? 'button-primary' : '' }` }
                                    onClick={() => fetchReviews(pg)}
                                >
                                    { pg }
                                </button>
                            ))}
                        </div>
                    )}
                </>
            )}
        </div>
    );
};

export default ManageReviews;
