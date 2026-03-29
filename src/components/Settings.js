import { useState } from '@wordpress/element';

const Settings = () => {
    const globalData = window.rhwcReactGlobal || { settings: {} };
    const s = globalData.settings || {};

    const [ defaultCount, setDefaultCount ] = useState(s.defaultCount || '5');
    const [ defaultRating, setDefaultRating ] = useState(s.defaultRating || '4-5');
    const [ defaultNames, setDefaultNames ] = useState(s.defaultNames || '');
    const [ customComments, setCustomComments ] = useState(s.customComments || '');
    const [ contentStyle, setContentStyle ] = useState(s.contentStyle || 'medium');
    const [ enableBulk, setEnableBulk ] = useState(s.enableBulk || 'yes');
    const [ deleteDataOnUninstall, setDeleteDataOnUninstall ] = useState(s.deleteDataOnUninstall || 'no');
    const [ saving, setSaving ] = useState(false);
    const [ message, setMessage ] = useState('');
    const [ messageType, setMessageType ] = useState('success');

    const handleSave = async () => {
        setSaving(true);
        setMessage('');
        const formData = new FormData();
        formData.append('action', 'rhwc_save_settings');
        formData.append('security', globalData.nonce);
        formData.append('default_count', defaultCount);
        formData.append('default_rating', defaultRating);
        formData.append('default_names', defaultNames);
        formData.append('custom_comments', customComments);
        formData.append('content_style', contentStyle);
        formData.append('enable_bulk', enableBulk);
        formData.append('delete_data_on_uninstall', deleteDataOnUninstall);

        try {
            const res = await fetch(globalData.ajaxUrl, { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                setMessageType('success');
                setMessage('Settings saved successfully!');
                // Update the global object so other components pick up changes
                if (window.rhwcReactGlobal) {
                    window.rhwcReactGlobal.settings = {
                        ...window.rhwcReactGlobal.settings,
                        defaultCount, defaultRating, defaultNames,
                        customComments, contentStyle, enableBulk,
                        deleteDataOnUninstall
                    };
                }
            } else {
                setMessageType('error');
                setMessage('Error: ' + (data.data?.message || 'Could not save settings.'));
            }
        } catch (e) {
            setMessageType('error');
            setMessage('Server connection error. Please try again.');
        }
        setSaving(false);
    };

    const fieldStyle = {
        display: 'block',
        padding: '14px 18px',
        background: '#f6f7f7',
        borderLeft: '4px solid #0073aa',
        borderRadius: '4px',
        marginBottom: '24px'
    };

    const sectionHeader = {
        fontSize: '13px',
        fontWeight: 700,
        textTransform: 'uppercase',
        letterSpacing: '0.5px',
        color: '#0073aa',
        margin: '28px 0 12px',
        borderBottom: '1px solid #e2e4e7',
        paddingBottom: '8px'
    };

    return (
        <div className="rhwc-card">
            <h2><span className="dashicons dashicons-admin-settings"></span> Plugin Settings</h2>
            <p className="description">Configure the default values used when generating reviews.</p>

            { message && (
                <div className={ `notice notice-${ messageType } is-dismissible` } style={{ padding: '10px 15px', marginBottom: '20px' }}>
                    <p style={{ margin: 0 }}>{ message }</p>
                </div>
            ) }

            <div>
                {/* ─── Generation Defaults ─── */}
                <p style={ sectionHeader }>Generation Defaults</p>

                <div style={{ marginBottom: '20px' }}>
                    <label style={{ display: 'block', fontWeight: 600, marginBottom: '6px' }}>
                        Default Review Count <small style={{ color: '#646970', fontWeight: 400 }}>(per product)</small>
                    </label>
                    <input
                        type="number"
                        value={ defaultCount }
                        onChange={ e => setDefaultCount(e.target.value) }
                        min="1" max="100"
                        style={{ width: '80px' }}
                    />
                </div>

                <div style={{ marginBottom: '20px' }}>
                    <label style={{ display: 'block', fontWeight: 600, marginBottom: '6px' }}>
                        Default Rating
                        <small style={{ color: '#646970', fontWeight: 400 }}> – e.g. <code>5</code> or <code>4-5</code></small>
                    </label>
                    <select value={ defaultRating } onChange={ e => setDefaultRating(e.target.value) }>
                        <option value="5">5 Stars</option>
                        <option value="4">4 Stars</option>
                        <option value="3">3 Stars</option>
                        <option value="4-5">4–5 Stars (Random)</option>
                        <option value="3-5">3–5 Stars (Random)</option>
                        <option value="1-5">1–5 Stars (Random)</option>
                    </select>
                </div>

                <div style={{ marginBottom: '20px' }}>
                    <label style={{ display: 'block', fontWeight: 600, marginBottom: '6px' }}>
                        Fallback Content Style
                        <small style={{ color: '#646970', fontWeight: 400 }}> – used only when the comments list below is empty</small>
                    </label>
                    <select value={ contentStyle } onChange={ e => setContentStyle(e.target.value) }>
                        <option value="short">Short (1–2 sentences)</option>
                        <option value="medium">Medium (2–3 sentences)</option>
                        <option value="long">Long (full paragraph)</option>
                    </select>
                </div>

                <div style={{ marginBottom: '20px' }}>
                    <label style={{ display: 'block', fontWeight: 600, marginBottom: '6px' }}>Enable Bulk Generation</label>
                    <label style={{ display: 'flex', alignItems: 'center', gap: '8px', cursor: 'pointer' }}>
                        <input
                            type="checkbox"
                            checked={ enableBulk === 'yes' }
                            onChange={ e => setEnableBulk(e.target.checked ? 'yes' : 'no') }
                            style={{ width: '18px', height: '18px' }}
                        />
                        <span>Show the Bulk Generate tab in the dashboard</span>
                    </label>
                </div>

                {/* ─── Reviewer Names ─── */}
                <p style={ sectionHeader }>Reviewer Names</p>

                <div style={{ marginBottom: '20px' }}>
                    <label style={{ display: 'block', fontWeight: 600, marginBottom: '6px' }}>Default Reviewer Names List</label>
                    <textarea
                        value={ defaultNames }
                        onChange={ e => setDefaultNames(e.target.value) }
                        className="large-text"
                        rows="8"
                    />
                    <p className="description">One name per line. These will be randomly picked during generation.</p>
                </div>

                {/* ─── Review Comments ─── */}
                <p style={ sectionHeader }>Review Comments</p>

                <div style={{ marginBottom: '8px' }}>
                    <label style={{ display: 'block', fontWeight: 600, marginBottom: '6px' }}>Default Review Comments List</label>
                    <textarea
                        value={ customComments }
                        onChange={ e => setCustomComments(e.target.value) }
                        className="large-text"
                        rows="8"
                    />
                    <div style={ fieldStyle }>
                        <strong>💡 Tip:</strong> Add one comment per line. Use <code>{'{product_name}'}</code> anywhere to dynamically
                        insert the product's title. For example:<br /><br />
                        <code>Very satisfied with {'{product_name}'}. Highly recommended!</code><br />
                        <code>The {'{product_name}'} exceeded my expectations.</code><br /><br />
                        If the list is <strong>empty</strong>, the plugin falls back to its built-in templates using the Fallback Content Style above.
                    </div>
                </div>

                {/* ─── Danger Zone ─── */}
                <p style={{ ...sectionHeader, color: '#d63638', borderBottomColor: '#f9d0d0' }}>Danger Zone</p>

                <div style={{ marginBottom: '20px', padding: '16px', border: '1px solid #d63638', borderRadius: '6px', background: '#fff5f5' }}>
                    <label style={{ display: 'flex', alignItems: 'center', gap: '8px', cursor: 'pointer', fontWeight: 600, color: '#d63638' }}>
                        <input
                            type="checkbox"
                            checked={ deleteDataOnUninstall === 'yes' }
                            onChange={ e => setDeleteDataOnUninstall(e.target.checked ? 'yes' : 'no') }
                            style={{ width: '18px', height: '18px' }}
                        />
                        <span>Delete all plugin data when uninstalling</span>
                    </label>
                    <p className="description" style={{ marginTop: '8px', color: '#646970' }}>
                        ⚠️ If checked, all plugin settings and generated review metadata will be permanently deleted when you uninstall this plugin.
                        Reviews themselves (WordPress comments) will remain unless deleted manually.
                    </p>
                </div>

                <div style={{ marginTop: '24px' }}>
                    <button
                        className="button button-primary button-hero"
                        onClick={ handleSave }
                        disabled={ saving }
                    >
                        { saving ? 'Saving…' : '💾 Save Settings' }
                    </button>
                </div>
            </div>
        </div>
    );
};

export default Settings;
