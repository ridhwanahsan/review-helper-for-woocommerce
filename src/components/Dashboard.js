const Dashboard = ({ setActiveTab, globalData }) => {
    return (
        <div className="rhwc-dashboard-grid">
            <div className="rhwc-card rhwc-overview-card" style={{ gridColumn: '1 / -1' }}>
                <div className="rhwc-hero-header">
                    <div className="rhwc-hero-content">
                        <h1 style={{ color: '#fff', margin: '0 0 10px 0', fontSize: '24px', display: 'flex', alignItems: 'center', gap: '10px' }}>
                            Review Helper for WooCommerce <span className="rhwc-version-badge">v1.0.0</span>
                        </h1>
                        <p style={{ margin: 0, fontSize: '16px', opacity: 0.9, maxWidth: '600px', color: '#fff' }}>
                            Effortlessly generate, manage, and scale sample reviews to enhance your store's credibility.
                        </p>
                    </div>
                </div>
            </div>

            <div className="rhwc-card rhwc-overview-card">
                <h2><span className="dashicons dashicons-chart-pie"></span> Stats Overview</h2>
                <p className="description">Quick insights into your generated evaluations.</p>
                <div className="rhwc-stats-grid">
                    <div className="rhwc-stat-box">
                        <span className="dashicons dashicons-star-filled rhwc-stat-icon"></span>
                        <h3>{ globalData.stats?.totalGenerated || 0 }</h3>
                        <p>Total Generated Reviews</p>
                    </div>
                    <div className="rhwc-stat-box">
                        <span className="dashicons dashicons-calendar-alt rhwc-stat-icon"></span>
                        <h3 style={{ fontSize: '24px' }}>{ globalData.stats?.lastGeneratedDate || 'Never' }</h3>
                        <p>Last Generated</p>
                    </div>
                </div>
            </div>

            <div className="rhwc-card rhwc-quick-links-card">
                <h2><span className="dashicons dashicons-admin-links"></span> Quick Actions</h2>
                <p className="description">Jump exactly where you need to go.</p>
                <ul className="rhwc-quick-links">
                    { globalData.settings?.enableBulk === 'yes' && (
                        <li>
                            <button onClick={() => setActiveTab('bulk')} className="rhwc-action-btn primary">
                                <span className="dashicons dashicons-update"></span>
                                <div className="rhwc-action-text">
                                    <strong>Generate Bulk Reviews</strong>
                                    <small>Create reviews for multiple products</small>
                                </div>
                            </button>
                        </li>
                    ) }
                    <li>
                        <button onClick={() => setActiveTab('manage')} className="rhwc-action-btn">
                            <span className="dashicons dashicons-list-view"></span>
                            <div className="rhwc-action-text">
                                <strong>Manage Reviews</strong>
                                <small>View, edit, or delete existing reviews</small>
                            </div>
                        </button>
                    </li>
                    <li>
                        <button onClick={() => setActiveTab('settings')} className="rhwc-action-btn">
                            <span className="dashicons dashicons-admin-settings"></span>
                            <div className="rhwc-action-text">
                                <strong>Plugin Settings</strong>
                                <small>Configure default counts, ratings, and names</small>
                            </div>
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    );
};

export default Dashboard;
