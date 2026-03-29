import { useState } from '@wordpress/element';

const BulkGenerate = () => {
    const globalData = window.rhwcReactGlobal || { settings: {} };
    // States
    const [ products, setProducts ] = useState('all');
    const [ specificIds, setSpecificIds ] = useState('');
    const [ count, setCount ] = useState(globalData.settings.defaultCount || 5);
    const [ rating, setRating ] = useState(globalData.settings.defaultRating || '4-5');
    const [ style, setStyle ] = useState(globalData.settings.contentStyle || 'medium');
    const [ isGenerating, setIsGenerating ] = useState(false);
    const [ progress, setProgress ] = useState('');
    const [ names, setNames ] = useState(globalData.settings.defaultNames || '');
    
    // Fallback if settings empty
    const handleGenerate = async () => {
        setIsGenerating(true);
        setProgress('Preparing to generate. Please wait...');

        try {
            const formData = new FormData();
            formData.append('action', 'rhwc_generate_bulk');
            formData.append('security', globalData.nonce);
            formData.append('product_ids', products === 'all' ? 'all' : specificIds);
            formData.append('count', count);
            formData.append('rating', rating);
            formData.append('style', style);
            formData.append('names', names);
            formData.append('exclude_generated', true);

            const response = await fetch(globalData.ajaxUrl, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            
            if (data.success) {
                setProgress(data.data.message);
            } else {
                setProgress('Error: ' + (data.data?.message || 'Failed'));
            }
        } catch (error) {
            setProgress('Error connecting to server.');
        }

        setIsGenerating(false);
    };

    return (
        <div className="rhwc-card">
            <h2>Bulk Generate Reviews</h2>
            <p>Generate reviews for multiple products at once.</p>

            <table className="form-table">
                <tbody>
                    <tr>
                        <th scope="row">Target Products</th>
                        <td>
                            <label style={{ display: 'block', marginBottom: '10px' }}>
                                <input 
                                    type="radio" 
                                    name="target" 
                                    value="all" 
                                    checked={products === 'all'}
                                    onChange={() => setProducts('all')}
                                /> All Products (Excluding previously generated)
                            </label>
                            <label style={{ display: 'block' }}>
                                <input 
                                    type="radio" 
                                    name="target" 
                                    value="specific" 
                                    checked={products === 'specific'}
                                    onChange={() => setProducts('specific')}
                                /> Specific Product IDs
                            </label>
                            {products === 'specific' && (
                                <div style={{ marginTop: '10px' }}>
                                    <input 
                                        type="text" 
                                        value={specificIds}
                                        onChange={(e) => setSpecificIds(e.target.value)}
                                        placeholder="e.g. 15, 23, 105" 
                                        className="regular-text" 
                                    />
                                    <p className="description">Comma-separated product IDs.</p>
                                </div>
                            )}
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Review Count (per product)</th>
                        <td>
                            <input 
                                type="number" 
                                value={count}
                                onChange={(e) => setCount(e.target.value)}
                                min="1" max="100" 
                            />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Rating (e.g. 5 or 4-5)</th>
                        <td>
                            <input 
                                type="text" 
                                value={rating}
                                onChange={(e) => setRating(e.target.value)}
                            />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Reviewer Names</th>
                        <td>
                            <textarea 
                                value={names}
                                onChange={(e) => setNames(e.target.value)}
                                className="large-text" rows="5"
                            ></textarea>
                            <p className="description">One name per line.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Fallback Content Style</th>
                        <td>
                            <select value={style} onChange={(e) => setStyle(e.target.value)}>
                                <option value="short">Short</option>
                                <option value="medium">Medium</option>
                                <option value="long">Long</option>
                            </select>
                        </td>
                    </tr>
                </tbody>
            </table>

            <p className="submit">
                <button 
                    disabled={isGenerating}
                    onClick={handleGenerate}
                    className="button button-primary button-hero"
                >
                    {isGenerating ? 'Generating...' : 'Start Bulk Generation'}
                </button>
            </p>
            { progress && (
                <div style={{ padding: '15px', background: '#f0f0f1', borderLeft: '4px solid #0073aa', marginTop: '15px' }}>
                    <strong>Status:</strong> {progress}
                </div>
            ) }
        </div>
    );
};

export default BulkGenerate;
